<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehicle.class.php';
require_once __DIR__.'/lmdbvehiclehistory.class.php';
require_once __DIR__.'/lmdbvehiclesupplierinvoice.class.php';
require_once __DIR__.'/lmdbvehicleconsumption.class.php';
require_once __DIR__.'/lmdbvehicleassignment.class.php';
require_once __DIR__.'/lmdbvehicleinsurancecontract.class.php';
require_once __DIR__.'/lmdbvehicleinsurancecertificate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * Assemble a vehicle dossier from authoritative objects, never from cached totals.
 * @phpstan-type DossierTable array{title:string,columns:list<string>,rows:list<list<string>>}
 * @phpstan-type DossierSection array{title:string,tables:list<DossierTable>}
 * @phpstan-type DossierFile array{source:string,path:string,size:int,sha256:string}
 * @phpstan-type DossierData array{sections:list<DossierSection>,files:list<DossierFile>,warnings:list<string>}
 */
class LmdbVehicleDossier
{
	/** @var DoliDB */ private $db;
	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }

	/**
	 * @param string $sql Ordered query without LIMIT
	 * @return Generator<int,object>
	 */
	private function rows($sql)
	{
		$offset = 0;
		do {
			$res = $this->db->query($sql.$this->db->plimit(200, $offset));
			if (!$res) throw new RuntimeException('LmdbDossierReadFailed');
			$count = 0;
			try { while (is_object($row = $this->db->fetch_object($res))) { $count++; yield $row; } }
			finally { $this->db->free($res); }
			$offset += $count;
		} while ($count === 200);
	}

	/**
	 * Human-readable object fields, excluding internal identifiers and private notes.
	 * @param CommonObject $object Source
	 * @param Translate $langs Output language
	 * @param list<string>|null $onlyFields Explicit field allowlist, null for all public fields
	 * @return list<list<string>> Label/value rows, with plain-text values
	 */
	private function describe($object, $langs, $onlyFields = null)
	{
		$lines = array();
		$excluded = array('rowid', 'entity', 'tms', 'import_key', 'model_pdf', 'last_main_doc', 'fk_user_creat', 'fk_user_modif', 'note_private', 'fk_vehicle', 'fk_payment_various', 'fk_odometer_reading', 'fk_requirement');
		foreach ($onlyFields ?? array_keys($object->fields) as $key) {
			if (in_array($key, $excluded, true) || !isset($object->fields[$key]) || !property_exists($object, $key)) continue;
			$field = $object->fields[$key];
			$value = $object->$key;
			if ($key === 'status') $formatted = $object->getLibStatut(5);
			elseif ($value === null || $value === '') $formatted = $langs->trans('NotDefined');
			elseif ($key === 'total_ttc') $formatted = price($value, 0, $langs).' '.($object instanceof LmdbVehicleConsumption ? $object->currency_snapshot : '');
			elseif ($key === 'result_code' && $object instanceof LmdbVehicleRegulatoryControl) {
				$formatted = (string) $value;
				foreach ($this->rows('SELECT label FROM '.MAIN_DB_PREFIX."c_lmdbvehiclemanagement_control_result WHERE code = '".$this->db->escape((string) $value)."' AND entity IN (".getEntity('c_lmdbvehiclemanagement_control_result').') ORDER BY rowid') as $row) $formatted = $langs->trans($row->label);
			}
			elseif (in_array($key, array('fk_asset_type', 'fk_rule'), true)) {
				$table = $key === 'fk_asset_type' ? 'c_lmdbvehiclemanagement_asset_type' : 'lmdbvehiclemanagement_regulatory_rule';
				$scope = $key === 'fk_asset_type' ? 'entity IN ('.getEntity($table).')' : 'entity = '.((int) $object->entity);
				$formatted = $langs->trans('NotDefined');
				foreach ($this->rows('SELECT label FROM '.MAIN_DB_PREFIX.$table.' WHERE rowid = '.((int) $value).' AND '.$scope.' ORDER BY rowid') as $row) $formatted = $langs->trans($row->label);
			}
			else $formatted = $object->showOutputField($field, $key, $value);
			// PDF text, not arbitrary stored HTML, scripts or image URLs.
			$text = html_entity_decode(strip_tags(str_replace(array('<br>', '<br/>', '<br />'), "\n", (string) $formatted)), ENT_QUOTES, 'UTF-8');
			$lines[] = array($langs->transnoentities((string) $field['label']), $text);
		}
		return $lines;
	}

	/**
	 * @param string $title Record reference, empty for a section-wide table
	 * @param list<list<string>> $rows Plain-text label/value pairs
	 * @param Translate $langs Output language
	 * @return DossierTable
	 */
	private function detailsTable($title, $rows, $langs)
	{
		return array('title' => $title, 'columns' => array($langs->transnoentities('Designation'), $langs->transnoentities('Value')), 'rows' => $rows);
	}

	/**
	 * Inventory original files only, confined to the object's native directory.
	 * @param CommonObject $object Owner
	 * @param string $directory Native owner directory
	 * @param string $archiveDir Relative ZIP directory
	 * @param DossierData $data Accumulator
	 * @param Translate $langs Output language
	 * @return list<string> Relative archive names for the PDF
	 */
	private function attachments($object, $directory, $archiveDir, &$data, $langs)
	{
		if (!is_string($directory) || $directory === '' || strpos($directory, 'error-diroutput-') === 0) throw new RuntimeException('LmdbDossierReadFailed');
		$names = array();
		$found = array();
		$root = realpath($directory);
		if ($root !== false) {
			if (!is_readable($root)) throw new RuntimeException('LmdbDossierReadFailed');
			$root = str_replace('\\', '/', $root);
			// Exclude temporary trees before traversal; never follow directory symlinks.
			$excludedFiles = '^(temp|thumbs|\\.[^\/]+|lmdb-dossier-.*)$|_preview|\\.meta$';
			foreach (dol_dir_list($root, 'all', 1, '', $excludedFiles, 'fullname', SORT_ASC, 0, 0, '', 1) as $file) {
				$full = str_replace('\\', '/', (string) $file['fullname']);
				$relative = substr($full, strlen($root) + 1);
				if (preg_match('~(^|/)(temp|thumbs|\\.[^/]+)(/|$)|(^|/)lmdb-dossier-|_preview|\\.meta$~i', $relative)) continue;
				$real = realpath($full);
				if ($real === false || !is_readable($full) || strpos(str_replace('\\', '/', $real), $root.'/') !== 0 || is_link($full)) throw new RuntimeException('LmdbDossierReadFailed');
				if (is_dir($full)) continue;
				$hash = hash_file('sha256', $full);
				$size = filesize($full);
				if ($hash === false || $size === false) throw new RuntimeException('LmdbDossierReadFailed');
				// Keep hierarchy to avoid collisions between equal basenames.
				$entry = $archiveDir.'/'.$relative;
				$data['files'][] = array('source' => $full, 'path' => $entry, 'size' => $size, 'sha256' => $hash);
				$names[] = $entry;
				$found[$full] = true;
			}
		}
		$types = array($object->element, $object->getElementType(), $object->element.'@lmdbvehiclemanagement', $object->table_element, $object->table_element.'@lmdbvehiclemanagement');
		$types = array_map(function ($type) { return "'".$this->db->escape($type)."'"; }, array_unique($types));
		$sql = 'SELECT filepath, filename FROM '.MAIN_DB_PREFIX.'ecm_files WHERE entity = '.((int) $object->entity).' AND src_object_id = '.((int) $object->id).' AND src_object_type IN ('.implode(',', $types).') ORDER BY rowid';
		foreach ($this->rows($sql) as $indexed) {
			if (preg_match('~(^|/)lmdb-dossier-|(^|/)(temp|thumbs)/|_preview|\\.meta$~i', $indexed->filepath.'/'.$indexed->filename)) continue;
			$path = str_replace('\\', '/', DOL_DATA_ROOT.'/'.trim($indexed->filepath, '/').'/'.$indexed->filename);
			if (!isset($found[$path])) $data['warnings'][] = $langs->trans('LmdbDossierMissingFile', $object->ref, basename($indexed->filename));
		}
		return $names;
	}

	/**
	 * @param LmdbVehicle $vehicle Vehicle
	 * @param Translate $langs Output language
	 * @return DossierData
	 */
	public function collect($vehicle, $langs)
	{
		global $user;
		if (!$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('fournisseur', 'facture', 'lire') || !empty($user->socid)
			|| !in_array((int) $vehicle->entity, array_map('intval', explode(',', getEntity('lmdbvehicle'))), true)) throw new RuntimeException('NotEnoughPermissions');
		$langs->loadLangs(array('main', 'bills', 'suppliers', 'companies', 'users', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$data = array('sections' => array(), 'files' => array(), 'warnings' => array());
		$technical = $this->describe($vehicle, $langs);
		$sql = 'SELECT d.label, d.unit, cap.capacity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity cap INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable d ON d.rowid = cap.fk_consumable AND d.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').') WHERE cap.entity = '.((int) $vehicle->entity).' AND cap.fk_vehicle = '.((int) $vehicle->id).' ORDER BY cap.rowid';
		foreach ($this->rows($sql) as $row) $technical[] = array($row->label, price($row->capacity, 0, $langs).' '.$row->unit);
		$sql = 'SELECT p.label, vp.confirmed FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile vp INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile p ON p.rowid = vp.fk_profile AND p.entity IN ('.getEntity('c_lmdbvehiclemanagement_regulatory_profile').') WHERE vp.entity = '.((int) $vehicle->entity).' AND vp.fk_vehicle = '.((int) $vehicle->id).' ORDER BY vp.rowid';
		foreach ($this->rows($sql) as $row) $technical[] = array($langs->transnoentities($row->label), $langs->transnoentities($row->confirmed ? 'QualificationConfirmed' : 'QualificationToConfirm'));
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extra = new ExtraFields($this->db);
		$extra->fetch_name_optionals_label($vehicle->table_element);
		if ($vehicle->fetch_optionals() < 0) throw new RuntimeException('LmdbDossierReadFailed');
		foreach (($extra->attributes[$vehicle->table_element]['label'] ?? array()) as $key => $label) {
			$attributes = $extra->attributes[$vehicle->table_element];
			// A dossier is readable by every vehicle/invoice reader: never embed a
			// field with a narrower permission expression into that shared artifact.
			if (!empty($attributes['perms'][$key]) && !in_array(trim($attributes['perms'][$key]), array('1', 'true'), true)) continue;
			if (isset($attributes['enabled'][$key]) && !dol_eval((string) $attributes['enabled'][$key], 1, 1, '2')) continue;
			if (isset($attributes['list'][$key]) && !dol_eval((string) $attributes['list'][$key], 1, 1, '2')) continue;
			$value = $vehicle->array_options['options_'.$key] ?? null;
			if ($value !== null && $value !== '') $technical[] = array($langs->transnoentities($label), html_entity_decode(strip_tags($extra->showOutputField($key, $value, '', $vehicle->table_element)), ENT_QUOTES, 'UTF-8'));
		}
		$data['sections'][] = array('title' => $langs->transnoentities('LmdbDossierTechnical'), 'tables' => array($this->detailsTable('', $technical, $langs)));
		$history = new LmdbVehicleHistory($this->db);
		$historyRows = array();
		$statusObjects = array('lmdbvehicleevent' => new LmdbVehicleEvent($this->db), 'lmdbvehicleassignment' => new LmdbVehicleAssignment($this->db),
			'lmdbvehicleodometerreading' => new LmdbVehicleOdometerReading($this->db), 'lmdbvehicleconsumption' => new LmdbVehicleConsumption($this->db),
			'lmdbinsurancecontract' => new LmdbVehicleInsuranceContract($this->db), 'lmdbinsurancecertificate' => new LmdbVehicleInsuranceCertificate($this->db));
		$typeLabels = array('quartix_estimate' => 'QxEstimate', 'contract_linked' => 'InsuranceHistoryContractLinked', 'certificate_submitted' => 'InsuranceHistoryCertificateSubmitted', 'certificate_validated' => 'InsuranceHistoryCertificateValidated', 'certificate_rejected' => 'InsuranceHistoryCertificateRejected');
		foreach (array('lmdbvehicleevent' => 'event_type', 'lmdbvehicleassignment' => 'assignment_type', 'lmdbvehicleodometerreading' => 'reading_kind', 'lmdbvehicleconsumption' => 'category_snapshot') as $element => $field) {
			$typeLabels = array_merge($typeLabels, $statusObjects[$element]->fields[$field]['arrayofkeyval'] ?? array());
		}
		$offset = 0;
		do {
			$batch = $history->getTimeline((int) $vehicle->id, array(), 200, $offset, array(), 'event_timestamp', 'ASC');
			if (!is_array($batch)) throw new RuntimeException('LmdbDossierReadFailed');
			foreach ($batch as $entry) {
				$statusObject = $statusObjects[$entry['source_object']] ?? null;
				$status = $statusObject ? strip_tags($statusObject->LibStatut($entry['status'], 5)) : '';
				$cells = array(dol_print_date($entry['date'], 'dayhour', 'tzuser', $langs), $langs->transnoentities('TimelineSource'.ucfirst($entry['source'])),
					$langs->transnoentities($typeLabels[$entry['type']] ?? $entry['type']), html_entity_decode(strip_tags($entry['label']), ENT_QUOTES, 'UTF-8'),
					html_entity_decode($status, ENT_QUOTES, 'UTF-8'), $entry['odometer_km'] !== null ? price($entry['odometer_km'], 0, $langs).' km' : '');
				$historyRows[] = array('date' => $entry['date'], 'cells' => $cells);
			}
			$offset += count($batch);
		} while (count($batch) === 200);
		$recordSections = array();
		$invoiceRefs = array();
		$invoiceSections = array();
		foreach (array('event' => array('vehicle_event', 'lmdbvehicle', 'VehicleEvent'), 'control' => array('regulatory_control', 'lmdbvehicleregulatorycontrol', 'RegulatoryControl')) as $kind => $definition) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$definition[0].' WHERE fk_vehicle = '.((int) $vehicle->id).' AND entity IN ('.getEntity($definition[1]).') ORDER BY rowid';
			$details = array();
			foreach ($this->rows($sql) as $row) {
				$service = new LmdbVehicleSupplierInvoice($this->db);
				$source = $service->fetchSource($kind, (int) $row->rowid);
				$description = $this->describe($source, $langs);
				if ($kind === 'control') $historyRows[] = array('date' => (int) $source->control_date, 'cells' => array(dol_print_date($source->control_date, 'dayhour', 'tzuser', $langs), $langs->transnoentities('RegulatoryControl'), '', $source->ref, html_entity_decode(strip_tags($source->getLibStatut(5)), ENT_QUOTES, 'UTF-8'), ''));
				$files = $this->attachments($source, getMultidirOutput($source, 'lmdbvehiclemanagement', 1), ($kind === 'event' ? 'events/' : 'controls/').((int) $source->entity).'-'.((int) $source->id).'-'.dol_sanitizeFileName($source->ref), $data, $langs);
				$description[] = array($langs->transnoentities('Files'), $files ? implode("\n", $files) : $langs->transnoentities('NoFileFound'));
				if ($source->fetchObjectLinked() < 0) throw new RuntimeException('LmdbDossierReadFailed');
				foreach (($source->linkedObjects['invoice_supplier'] ?? array()) as $invoice) {
					if (!($invoice instanceof FactureFournisseur) || !in_array((int) $invoice->entity, array_map('intval', explode(',', getEntity('supplier_invoice'))), true)) throw new RuntimeException('NotEnoughPermissions');
					$description[] = array($langs->transnoentities('SupplierInvoice'), $invoice->ref);
					if (isset($invoiceRefs[$invoice->id])) continue;
					$invoiceRefs[$invoice->id] = $invoice->ref;
					$dir = getMultidirOutput($invoice).'/'.get_exdir($invoice->id, 2, 0, 0, $invoice, 'invoice_supplier').dol_sanitizeFileName($invoice->ref);
					$invoiceFiles = $this->attachments($invoice, $dir, 'invoices/'.((int) $invoice->entity).'-'.((int) $invoice->id).'-'.dol_sanitizeFileName($invoice->ref), $data, $langs);
					if ($invoice->fetch_thirdparty() < 0) throw new RuntimeException('LmdbDossierReadFailed');
					$currency = $invoice->multicurrency_code ?: dolibarr_get_const($this->db, 'MAIN_MONNAIE', (int) $invoice->entity);
					$total = $invoice->multicurrency_code ? $invoice->multicurrency_total_ttc : $invoice->total_ttc;
					$invoiceSections[] = $this->detailsTable($invoice->ref, array(
						array($langs->transnoentities('RefSupplier'), (string) $invoice->ref_supplier),
						array($langs->transnoentities('Supplier'), is_object($invoice->thirdparty) ? (string) $invoice->thirdparty->name : ''),
						array($langs->transnoentities('Date'), dol_print_date($invoice->date, 'day', 'tzuser', $langs)),
						array($langs->transnoentities('Status'), html_entity_decode(strip_tags($invoice->getLibStatut(5)), ENT_QUOTES, 'UTF-8')),
						array($langs->transnoentities('TotalTTC'), price($total, 0, $langs).' '.$currency),
						array($langs->transnoentities('Files'), $invoiceFiles ? implode("\n", $invoiceFiles) : $langs->transnoentities('NoFileFound')),
					), $langs);
				}
				$details[] = $this->detailsTable($source->ref, $description, $langs);
			}
			$recordSections[] = array('title' => $langs->transnoentities($definition[2]), 'tables' => $details);
		}
		usort($historyRows, static function ($a, $b) { return $a['date'] <=> $b['date']; });
		$data['sections'][] = array('title' => $langs->transnoentities('History'), 'tables' => array(array('title' => '',
			'columns' => array($langs->transnoentities('Date'), $langs->transnoentities('TimelineSource'), $langs->transnoentities('Type'), $langs->transnoentities('Description'), $langs->transnoentities('Status'), $langs->transnoentities('OdometerKm')),
			'rows' => array_column($historyRows, 'cells'))));
		$data['sections'] = array_merge($data['sections'], $recordSections);
		$data['sections'][] = array('title' => $langs->transnoentities('SupplierInvoices'), 'tables' => $invoiceSections);
		$consumptions = array();
		// Keep one summary row per consumption; do not render excluded relations or amounts.
		$consumptionFields = array('ref', 'fk_consumable', 'category_snapshot', 'unit_snapshot', 'oil_reference');
		$consumption = new LmdbVehicleConsumption($this->db);
		$consumptionColumns = array($langs->transnoentities('ReadingDate'), $langs->transnoentities('OdometerKm'));
		foreach ($consumptionFields as $field) $consumptionColumns[] = $langs->transnoentities($consumption->fields[$field]['label']);
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption WHERE fk_vehicle = '.((int) $vehicle->id).' AND entity IN ('.getEntity('lmdbvehicleconsumption').') ORDER BY rowid';
		foreach ($this->rows($sql) as $row) {
			$consumption = new LmdbVehicleConsumption($this->db);
			if ($consumption->fetch((int) $row->rowid) <= 0) throw new RuntimeException('LmdbDossierReadFailed');
			$consumptions[] = array_merge(array(
				dol_print_date($consumption->reading_date, 'dayhour', 'tzuser', $langs),
				price($consumption->odometer_km, 0, $langs).' km',
			), array_column($this->describe($consumption, $langs, $consumptionFields), 1));
			// Deliberately no attachments() and no traversal of linked PaymentVarious.
		}
		$data['sections'][] = array('title' => $langs->transnoentities('LmdbDossierConsumptions'), 'tables' => array(array('title' => '', 'columns' => $consumptionColumns, 'rows' => $consumptions)));
		$files = $this->attachments($vehicle, getMultidirOutput($vehicle, 'lmdbvehiclemanagement', 1), 'vehicle', $data, $langs);
		$data['sections'][] = array('title' => $langs->transnoentities('Files'), 'tables' => array(array('title' => '', 'columns' => array($langs->transnoentities('File')), 'rows' => array_map(static function ($file) { return array($file); }, $files))));
		if ($data['warnings']) $data['sections'][] = array('title' => $langs->transnoentities('LmdbDossierMissingDocuments'), 'tables' => array(array('title' => '', 'columns' => array($langs->transnoentities('Description')), 'rows' => array_map(static function ($warning) { return array(html_entity_decode($warning, ENT_QUOTES, 'UTF-8')); }, array_values(array_unique($data['warnings']))))));
		return $data;
	}

	/**
	 * Build and verify both files before replacing the previous pair. Restore on failure.
	 * @param LmdbVehicle $vehicle Vehicle
	 * @param Translate $langs Language
	 * @param callable(LmdbVehicle,array,Translate,string):void $writePdf Native model renderer
	 * @return string Published PDF path
	 */
	public function build($vehicle, $langs, callable $writePdf)
	{
		global $user, $conf;
		if (!LmdbVehicleManagementCompatibility::isFeatureAvailable('vehicle_dossier')) throw new RuntimeException('LmdbDossierUnavailable');
		if (!$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('lmdbvehiclemanagement', 'lmdbvehicle', 'write') || !$user->hasRight('fournisseur', 'facture', 'lire') || !empty($user->socid)) throw new RuntimeException('NotEnoughPermissions');
		$dir = getMultidirOutput($vehicle, 'lmdbvehiclemanagement', 1);
		if (!is_string($dir) || $dir === '' || strpos($dir, 'error-diroutput-') === 0 || dol_mkdir($dir.'/temp') < 0) throw new RuntimeException('LmdbDossierWriteFailed');
		$dir = rtrim($dir, '/\\');
		$base = 'lmdb-dossier-'.((int) $vehicle->id);
		$lock = fopen($dir.'/temp/'.$base.'.lock', 'c');
		if ($lock === false) throw new RuntimeException('LmdbDossierWriteFailed');
		if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('LmdbDossierBusy'); }
		$temp = $dir.'/temp/'.$base.'-'.bin2hex(random_bytes(8));
		$backups = array(); $published = array(); $deleted = 0; $keepBackup = false;
		try {
			if (dol_mkdir($temp.'/package') < 0) throw new RuntimeException('LmdbDossierWriteFailed');
			$data = $this->collect($vehicle, $langs);
			foreach ($data['files'] as $file) {
				$target = $temp.'/package/'.$file['path'];
				if (dol_mkdir(dirname($target)) < 0 || dol_copy($file['source'], $target, '0', 1, 0, 0) <= 0 || hash_file('sha256', $target) !== $file['sha256']) throw new RuntimeException('LmdbDossierReadFailed');
			}
			$writePdf($vehicle, $data, $langs, $temp.'/package/'.$base.'.pdf');
			if (!is_file($temp.'/package/'.$base.'.pdf') || filesize($temp.'/package/'.$base.'.pdf') === 0) throw new RuntimeException('LmdbDossierWriteFailed');
			if (dol_compress_dir($temp.'/package', $temp.'/'.$base.'.zip') <= 0) throw new RuntimeException('LmdbDossierWriteFailed');
			$zip = new ZipArchive();
			if ($zip->open($temp.'/'.$base.'.zip', ZipArchive::CHECKCONS) !== true) throw new RuntimeException('LmdbDossierWriteFailed');
			try {
				$expected = $data['files'];
				$expected[] = array('source' => '', 'path' => $base.'.pdf', 'size' => filesize($temp.'/package/'.$base.'.pdf'), 'sha256' => hash_file('sha256', $temp.'/package/'.$base.'.pdf'));
				if ($zip->numFiles !== count($expected)) throw new RuntimeException('LmdbDossierWriteFailed');
				foreach ($expected as $file) {
					$stream = $zip->getStream($file['path']);
					if ($stream === false) throw new RuntimeException('LmdbDossierWriteFailed');
					$hash = hash_init('sha256'); hash_update_stream($hash, $stream); fclose($stream);
					if (hash_final($hash) !== $file['sha256']) throw new RuntimeException('LmdbDossierWriteFailed');
				}
			} finally { $zip->close(); }
			$this->db->begin();
			try {
				$vehicle->oldcopy = clone $vehicle;
				foreach (array('pdf' => $temp.'/package/'.$base.'.pdf', 'zip' => $temp.'/'.$base.'.zip') as $extension => $built) {
					$final = $dir.'/'.$base.'.'.$extension;
					if (is_file($final)) {
						$backup = $temp.'/previous.'.$extension;
						if (dol_copy($final, $backup, '0', 1, 0, 0) <= 0 || hash_file('sha256', $final) !== hash_file('sha256', $backup)) throw new RuntimeException('LmdbDossierWriteFailed');
						$backups[$final] = $backup;
					}
					// Native dol_move may remove an existing target before a failed retry.
					$published[] = $final;
					if (dol_move($built, $final, '0', 1, 0, 0) <= 0) throw new RuntimeException('LmdbDossierWriteFailed');
					if ($vehicle->indexFile($final, $extension === 'pdf' ? 1 : 0) < 0) throw new RuntimeException('LmdbDossierWriteFailed');
				}
				$vehicle->context['trigger_reason'] = 'document_generation';
				$vehicle->context['changed_fields'] = array('last_main_doc');
				$vehicle->context['generated_documents'] = array($base.'.pdf', $base.'.zip');
				if ($vehicle->call_trigger($vehicle->TRIGGER_PREFIX.'_UPDATE', $user) < 0) throw new RuntimeException('LmdbDossierWriteFailed');
				$this->db->commit();
			} catch (Throwable $e) {
				$this->db->rollback();
				foreach ($published as $final) {
					if (isset($backups[$final])) {
						if (dol_copy($backups[$final], $final, '0', 1, 0, 0) <= 0 || hash_file('sha256', $backups[$final]) !== hash_file('sha256', $final)) $keepBackup = true;
					} elseif (is_file($final) && dol_delete_file($final, 0, 0, 0, null, false, 0) <= 0) $keepBackup = true;
				}
				if ($keepBackup) throw new RuntimeException('LmdbDossierRecoveryFailed');
				throw $e;
			}
			return $dir.'/'.$base.'.pdf';
		} finally {
			// temp is a generated child of the validated owner directory, never a client path.
			if (!$keepBackup && is_dir($temp)) dol_delete_dir_recursive($temp, 0, 1, 0, $deleted, 0);
			flock($lock, LOCK_UN); fclose($lock);
		}
	}
}
