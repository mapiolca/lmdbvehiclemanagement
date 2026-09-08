<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Native Multicompany scopes and record access, shared by SQL and object entry points. */
class LmdbVehicleSharing
{
	/** @return array<string,array{table:string,label:string,legacy:string,vehicle:bool,contract:bool}> */
	public static function definitions()
	{
		return array(
			'lmdbvehicle' => array('table' => 'lmdbvehiclemanagement_vehicle', 'label' => 'Vehicle', 'legacy' => 'lmdbvehicle', 'vehicle' => false, 'contract' => false),
			'lmdbinsurancecontract' => array('table' => 'lmdbvehiclemanagement_insurance_contract', 'label' => 'InsuranceContract', 'legacy' => 'lmdbvehicle', 'vehicle' => false, 'contract' => false),
			'lmdbinsurancecertificate' => array('table' => 'lmdbvehiclemanagement_insurance_certificate', 'label' => 'InsuranceCertificate', 'legacy' => 'lmdbvehicle', 'vehicle' => true, 'contract' => true),
			'lmdbvehicleregulatorycontrol' => array('table' => 'lmdbvehiclemanagement_regulatory_control', 'label' => 'RegulatoryControl', 'legacy' => 'lmdbvehicleregulatorycontrol', 'vehicle' => true, 'contract' => false),
			'lmdbvehicleconsumption' => array('table' => 'lmdbvehiclemanagement_consumption', 'label' => 'ConsumptionEntry', 'legacy' => 'lmdbvehicleconsumption', 'vehicle' => true, 'contract' => false),
			'lmdbvehicleevent' => array('table' => 'lmdbvehiclemanagement_vehicle_event', 'label' => 'VehicleEvent', 'legacy' => 'lmdbvehicle', 'vehicle' => true, 'contract' => false),
			'lmdbvehicleassignment' => array('table' => 'lmdbvehiclemanagement_vehicle_assignment', 'label' => 'VehicleAssignment', 'legacy' => 'lmdbvehicle', 'vehicle' => true, 'contract' => false),
			'lmdbvehicleodometerreading' => array('table' => 'lmdbvehiclemanagement_odometer_reading', 'label' => 'OdometerReading', 'legacy' => 'lmdbvehicle', 'vehicle' => true, 'contract' => false),
		);
	}

	/** @param string $element Element, table or native tooltip alias @return string Empty for unrelated objects */
	public static function element($element)
	{
		if (substr($element, -strlen('@lmdbvehiclemanagement')) === '@lmdbvehiclemanagement') $element = substr($element, 0, -strlen('@lmdbvehiclemanagement'));
		if ($element === 'lmdbvehicleajaxtooltip') return 'lmdbvehicle';
		foreach (self::definitions() as $key => $definition) {
			if ($element === $key || $element === $definition['table'] || $element === MAIN_DB_PREFIX.$definition['table']) return $key;
		}
		return '';
	}

	/** Administrative elevation never bypasses object/entity restrictions. @param User $user User @return bool */
	public static function isAdmin($user)
	{
		return is_object($user) && empty($user->socid) && !empty($user->admin);
	}

	/** @param User $user User @param string $object Permission object (empty for module) @param string $action Action @return bool */
	public static function can($user, $object = '', $action = 'read')
	{
		if (!is_object($user) || !empty($user->socid) || !isModEnabled('lmdbvehiclemanagement')) return false;
		if (self::isAdmin($user)) return true;
		return $object === '' ? $user->hasRight('lmdbvehiclemanagement', $action) : $user->hasRight('lmdbvehiclemanagement', $object, $action);
	}

	/** @param bool $requireEnabled False only for cleaning native associations on deletion. @return bool Native UI/DAO contract supported from Multicompany 21 on Dolibarr 20/PHP 8. */
	public static function available($requireEnabled = true)
	{
		global $db;
		if (($requireEnabled && !isModEnabled('multicompany')) || version_compare(DOL_VERSION, '20.0.0', '<') || version_compare(PHP_VERSION, '8.0', '<')) return false;
		static $available = null;
		if ($available !== null) return $available;
		$path = dol_buildpath('/multicompany/core/modules/modMultiCompany.class.php', 0);
		if (!is_file($path)) return false;
		require_once $path;
		dol_include_once('/multicompany/class/actions_multicompany.class.php');
		$descriptor = new modMultiCompany($db);
		$available = version_compare((string) $descriptor->version, '21.0.0', '>=')
			&& method_exists('ActionsMulticompany', 'formSelectSharingByElement')
			&& method_exists('ActionsMulticompany', 'multiselectEntitiesForGranularity')
			&& method_exists('DaoMulticompany', 'setSharingsByElement')
			&& method_exists('DaoMulticompany', 'getListOfSharingsByElement');
		return $available;
	}

	/** Preserve legacy entity scopes until a family is explicitly configured. @param string $element Canonical element @return string */
	public static function scopeElement($element)
	{
		global $conf;
		$definitions = self::definitions();
		if (!isset($definitions[$element])) throw new InvalidArgumentException('Unknown sharing element');
		$name = 'MULTICOMPANY_'.strtoupper($element).'_SHARING_ENABLED';
		return isset($conf->global->{$name}) ? $element : $definitions[$element]['legacy'];
	}

	/** @param string $element Canonical element @return bool */
	public static function individual($element)
	{
		return isModEnabled('multicompany') && getDolGlobalInt('MULTICOMPANY_SHARINGS_ENABLED')
			&& getDolGlobalInt('MULTICOMPANY_SHARING_BYELEMENT_ENABLED')
			&& getDolGlobalInt('MULTICOMPANY_'.strtoupper($element).'_SHARING_BYELEMENT_ENABLED');
	}

	/**
	 * No generic record SQL builder is exposed by Multicompany 21/22. Its DAO reads
	 * associations but cannot filter external lists/totals or their required parents.
	 * This predicate combines native getEntity() and native sharing rows, without
	 * duplicating their persistence or creating another sharing model.
	 * Predicate, without WHERE/AND. Aliases are developer-owned identifiers only.
	 * Native sharing rows are inclusions, or exclusions in SHARE_ALL_BY_DEFAULT mode.
	 * @param DoliDB $db Database @param string $element Element/table @param string $alias SQL alias
	 * @param bool $parents Also enforce parent access @param int $viewEntity Explicit destination for grant validation (0=current) @return string
	 */
	public static function sql($db, $element, $alias = 't', $parents = true, $viewEntity = 0)
	{
		global $conf;
		$element = self::element($element);
		$definitions = self::definitions();
		if ($element === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) throw new InvalidArgumentException('Invalid sharing query');
		$definition = $definitions[$element];
		$viewEntity = $viewEntity > 0 ? (int) $viewEntity : (int) $conf->entity;
		$scope = $db->sanitize(getEntity(self::scopeElement($element)));
		if ($viewEntity !== (int) $conf->entity) {
			$dao = new DaoMulticompany($db);
			if ($dao->fetch($viewEntity) <= 0 || empty($dao->active)) return '(1 = 0)';
			$scopeElement = self::scopeElement($element);
			$ids = array($viewEntity);
			if (getDolGlobalInt('MULTICOMPANY_SHARINGS_ENABLED') && getDolGlobalInt('MULTICOMPANY_'.strtoupper($scopeElement).'_SHARING_ENABLED') && isset($dao->options['sharings'][$scopeElement]) && is_array($dao->options['sharings'][$scopeElement])) $ids = array_merge($ids, array_map('intval', $dao->options['sharings'][$scopeElement]));
			$scope = implode(',', array_unique($ids));
		}
		$where = $alias.'.entity IN ('.$scope.')';
		if (self::individual($element) && !self::available()) {
			// Do not broaden a saved individual policy on an unsupported installation.
			$where .= ' AND '.$alias.'.entity = '.$viewEntity;
		} elseif (self::individual($element)) {
			$membership = "EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."entity_element_sharing lmdbshare WHERE lmdbshare.element = '".$element."' AND lmdbshare.fk_element = ".$alias.'.rowid AND lmdbshare.entity = '.$viewEntity.')';
			if (getDolGlobalInt('MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT')) $membership = 'NOT '.$membership;
			$where .= ' AND ('.$alias.'.entity = '.$viewEntity.' OR '.$membership.')';
		}
		if ($parents && $definition['vehicle']) {
			$parentAlias = $alias.'_vehicle';
			$parent = 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.$definitions['lmdbvehicle']['table'].' '.$parentAlias.' WHERE '.$parentAlias.'.rowid = '.$alias.'.fk_vehicle AND '.self::sql($db, 'lmdbvehicle', $parentAlias, true, $viewEntity).')';
			$where .= ' AND '.($definition['contract'] ? '('.$alias.'.fk_vehicle IS NULL OR '.$parent.')' : $parent);
		}
		if ($parents && $definition['contract']) {
			$parentAlias = $alias.'_contract';
			$where .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.$definitions['lmdbinsurancecontract']['table'].' '.$parentAlias.' WHERE '.$parentAlias.'.rowid = '.$alias.'.fk_contract AND '.self::sql($db, 'lmdbinsurancecontract', $parentAlias, true, $viewEntity).')';
		}
		return '('.$where.')';
	}

	/** Query authorization without calling fetch/render recursively. @param DoliDB $db Database @param string $element Element @param int $id Id @return bool */
	public static function visible($db, $element, $id)
	{
		$element = self::element($element);
		$definitions = self::definitions();
		if ($element === '' || $id <= 0) return false;
		$res = $db->query('SELECT t.rowid FROM '.MAIN_DB_PREFIX.$definitions[$element]['table'].' t WHERE t.rowid = '.((int) $id).' AND '.self::sql($db, $element));
		if (!$res) return false;
		$found = is_object($db->fetch_object($res));
		$db->free($res);
		return $found;
	}

	/** @param DoliDB $db Database @param User $user User @param CommonObject $object Object @return bool */
	public static function canReadObject($db, $user, $object)
	{
		return self::can($user) && is_object($object) && self::visible($db, (string) $object->element, (int) $object->id);
	}

	/** Native destination cohort, limited to active entities the administrator may enter. @param DoliDB $db Database @param User $user Administrator @param CommonObject $object Object @return array<int,string> */
	public static function destinations($db, $user, $object)
	{
		global $conf;
		if (!self::isAdmin($user) || !self::available() || (int) $object->entity !== (int) $conf->entity) return array();
		$dao = new DaoMulticompany($db);
		if ($dao->getEntities(false, array((int) $conf->entity), true) < 0) throw new RuntimeException('LmdbSharingDatabaseError');
		$element = self::element($object->element);
		$result = array();
		foreach ($dao->entities as $entity) {
			$shares = isset($entity->options['sharings'][$element]) && is_array($entity->options['sharings'][$element]) ? $entity->options['sharings'][$element] : array();
			if ((int) $entity->id !== (int) $conf->entity && !empty($entity->active) && (int) $entity->visible !== 2 && $dao->verifyRight((int) $entity->id, (int) $user->id) > 0 && in_array((int) $object->entity, array_map('intval', $shares), true)) $result[(int) $entity->id] = (string) $entity->label;
		}
		return $result;
	}

	/** Read raw native inclusions/exclusions. No cached grants survive an update. @param DoliDB $db Database @param string $element Element @param int $id Id @return list<int> */
	public static function stored($db, $element, $id)
	{
		$element = self::element($element);
		if ($element === '' || $id <= 0) throw new InvalidArgumentException('Invalid sharing object');
		$res = $db->query("SELECT entity FROM ".MAIN_DB_PREFIX."entity_element_sharing WHERE element = '".$element."' AND fk_element = ".((int) $id).' ORDER BY entity');
		if (!$res) throw new RuntimeException('LmdbSharingDatabaseError');
		$ids = array();
		while (is_object($row = $db->fetch_object($res))) $ids[] = (int) $row->entity;
		$db->free($res);
		return array_values(array_unique($ids));
	}

	/** A cached regulatory requirement must not reveal a hidden source control.
	 * @param DoliDB $db @param string $alias Requirement SQL alias @return string
	 */
	public static function requirementSql($db, $alias = 'req')
	{
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) throw new InvalidArgumentException('Invalid alias');
		return 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle rqv WHERE rqv.rowid = '.$alias.'.fk_vehicle AND '.self::sql($db, 'lmdbvehicle', 'rqv').')'
			.' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control rqc WHERE rqc.entity = '.$alias.'.entity AND rqc.fk_vehicle = '.$alias.'.fk_vehicle AND rqc.fk_rule = '.$alias.'.fk_rule AND NOT '.self::sql($db, 'lmdbvehicleregulatorycontrol', 'rqc').')';
	}

	/** Explain a possibly partial view without revealing existence/counts of private rows. @return bool */
	public static function partialView()
	{
		foreach (array_keys(self::definitions()) as $element) if (self::individual($element)) return true;
		// Global scopes can also differ between a vehicle and its related families.
		if (isModEnabled('multicompany') && getDolGlobalInt('MULTICOMPANY_SHARINGS_ENABLED')) {
			$scope = getEntity(self::scopeElement('lmdbvehicle'));
			foreach (array_keys(self::definitions()) as $element) if (getEntity(self::scopeElement($element)) !== $scope) return true;
		}
		return false;
	}

	/** Authorize a physical module document, including a certificate inside a contract directory.
	 * Null means outside this module. Paths and owner roots are checked before any read.
	 * @param DoliDB $db @param User $actor @param string $path @return bool|null
	 */
	public static function documentAccess($db, $actor, $path)
	{
		global $conf;
		$path = str_replace('\\', '/', $path);
		$roots = isset($conf->lmdbvehiclemanagement->multidir_output) && is_array($conf->lmdbvehiclemanagement->multidir_output) ? $conf->lmdbvehiclemanagement->multidir_output : array();
		if (!empty($conf->lmdbvehiclemanagement->dir_output) && !isset($roots[(int) $conf->entity])) $roots[(int) $conf->entity] = $conf->lmdbvehiclemanagement->dir_output;
		foreach ($roots as $entity => $root) {
			$root = rtrim(str_replace('\\', '/', $root), '/').'/';
			if (strpos($path, $root) !== 0) continue;
			if (!self::can($actor) || empty($actor->id) || preg_match('~(?:^|/)(?:\.\.?|temp)(?:/|$)|\.meta$~i', substr($path, strlen($root)))) return false;
			$parts = explode('/', substr($path, strlen($root)));
			if (count($parts) < 2 || $parts[0] === '') return false;
			$classes = array('lmdbvehicle' => 'LmdbVehicle', 'lmdbinsurancecontract' => 'LmdbVehicleInsuranceContract', 'lmdbvehicleevent' => 'LmdbVehicleEvent', 'lmdbvehicleconsumption' => 'LmdbVehicleConsumption', 'lmdbvehicleregulatorycontrol' => 'LmdbVehicleRegulatoryControl');
			foreach ($classes as $element => $class) {
				require_once __DIR__.'/'.strtolower($class).'.class.php';
				$target = new $class($db);
				if ($target->fetch(0, $parts[0]) <= 0 || (int) $target->entity !== (int) $entity) continue;
				$directory = getMultidirOutput($target, 'lmdbvehiclemanagement', 1);
				if (!is_string($directory) || strpos($path, rtrim(str_replace('\\', '/', $directory), '/').'/') !== 0) continue;
				$realDirectory = realpath($directory); $realFile = realpath($path);
				if ($realDirectory === false || $realFile === false || strpos($realFile, $realDirectory.DIRECTORY_SEPARATOR) !== 0) return false;
				if ($element === 'lmdbinsurancecontract' && ($parts[1] ?? '') === 'certificates') {
					$file = implode('/', array_slice($parts, 1));
					$res = $db->query("SELECT cert.rowid FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_certificate cert WHERE cert.fk_contract = ".((int) $target->id)." AND cert.file_name = '".$db->escape($file)."' AND ".self::sql($db, 'lmdbinsurancecertificate', 'cert'));
					if (!$res) return false;
					$found = is_object($db->fetch_object($res)); $db->free($res); return $found;
				}
				if (preg_match('~^lmdb-dossier-([0-9]+)(?:[.-]|$)~', basename($path), $match)) {
					if ($element !== 'lmdbvehicle' || (int) $match[1] !== (int) $target->id || !$actor->hasRight('fournisseur', 'facture', 'lire')) return false;
					$manifestPath = $directory.'/lmdb-dossier-'.((int) $target->id).'.sharing.meta';
					if (!is_file($manifestPath)) return !self::partialView() && (int) $target->entity === (int) $conf->entity;
					$manifest = json_decode((string) file_get_contents($manifestPath), true);
					if (!is_array($manifest) || empty($manifest['objects']) || !isset($manifest['invoices']) || !is_array($manifest['invoices'])) return false;
					$extension = substr(basename($path), -4) === '.zip' ? 'zip' : 'pdf';
					$original = $directory.'/lmdb-dossier-'.((int) $target->id).'.'.$extension;
					if (!isset($manifest[$extension]) || !is_string($manifest[$extension]) || !is_file($original) || !hash_equals($manifest[$extension], (string) hash_file('sha256', $original))) return false;
					if ($path !== $original && filemtime($path) < filemtime($original)) return false;
					foreach ($manifest['invoices'] as $invoiceId) {
						if (!is_int($invoiceId) || $invoiceId <= 0) return false;
						$res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture_fourn WHERE rowid = '.$invoiceId.' AND entity IN ('.$db->sanitize(getEntity('supplier_invoice')).')');
						if (!$res) return false;
						$found = is_object($db->fetch_object($res)); $db->free($res); if (!$found) return false;
					}
					foreach ($manifest['objects'] as $source) {
						if (!is_array($source) || count($source) !== 2 || !self::visible($db, (string) $source[0], (int) $source[1])) return false;
					}
				}
				return true;
			}
			return false;
		}
		return null;
	}
}
