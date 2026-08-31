<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Read one native Dolibarr date selector.
 *
 * @param string $prefix Input prefix
 * @return ?int
 */
function lmdbInsuranceGetDate($prefix)
{
	$day = GETPOSTINT($prefix.'day');
	$month = GETPOSTINT($prefix.'month');
	$year = GETPOSTINT($prefix.'year');
	if ($day <= 0 || $month <= 0 || $year <= 0) {
		return null;
	}

	return dol_mktime(12, 0, 0, $month, $day, $year);
}

/**
 * Translate errors exposed by a Dolibarr business object.
 *
 * @param object $object Business object
 * @return list<string>
 */
function lmdbInsuranceMessages($object)
{
	global $langs;

	$messages = array();
	if (isset($object->error) && is_string($object->error) && $object->error !== '') {
		$messages[] = $langs->trans($object->error);
	}
	if (isset($object->errors) && is_array($object->errors)) {
		foreach ($object->errors as $error) {
			if (is_string($error) && $error !== '') {
				$messages[] = $langs->trans($error);
			}
		}
	}
	if (empty($messages)) {
		$messages[] = $langs->trans('Error');
	}

	return array_values(array_unique($messages));
}

/**
 * Populate a contract from the shared form.
 *
 * @param LmdbVehicleInsuranceContract $contract Contract
 * @return void
 */
function lmdbInsurancePopulateContractFromPost($contract)
{
	$contract->fk_soc = GETPOSTINT('fk_soc');
	$contactId = GETPOSTINT('fk_contact');
	$contract->fk_contact = $contactId > 0 ? $contactId : null;
	$contract->policy_number = trim(GETPOST('policy_number', 'alphanohtml'));
	$contract->label = trim(GETPOST('contract_label', 'alphanohtml'));
	$contract->coverage_formula = trim(GETPOST('coverage_formula', 'restricthtml')) ?: null;
	$contract->date_start = (int) lmdbInsuranceGetDate('contract_start');
	$contract->date_end = lmdbInsuranceGetDate('contract_end');
	$contract->renewal_mode = GETPOST('renewal_mode', 'alpha') ?: 'fixed';
	$contract->notice_date = lmdbInsuranceGetDate('notice_date');
	$contract->assistance_phone = trim(GETPOST('assistance_phone', 'alphanohtml')) ?: null;
	$contract->assistance_email = trim(GETPOST('assistance_email', 'alphanohtml')) ?: null;
	$contract->claim_phone = trim(GETPOST('claim_phone', 'alphanohtml')) ?: null;
	$contract->claim_email = trim(GETPOST('claim_email', 'alphanohtml')) ?: null;
	$contract->description = GETPOST('contract_description', 'restricthtml') ?: null;
}

/**
 * Return vehicle coverage inputs from the shared form.
 *
 * @param LmdbVehicleInsuranceContract $contract Contract
 * @return array{vehicle_ids:list<int>,coverage_type:string,coverage_start:int,coverage_end:?int}
 */
function lmdbInsuranceGetCoverageFromPost($contract)
{
	$vehicleIds = GETPOSTISARRAY('vehicle_ids') ? GETPOST('vehicle_ids', 'array:int') : array();
	$vehicleIds = array_values(array_unique(array_filter(array_map('intval', is_array($vehicleIds) ? $vehicleIds : array()))));
	$coverageStart = lmdbInsuranceGetDate('coverage_start');
	$coverageEnd = lmdbInsuranceGetDate('coverage_end');

	return array(
		'vehicle_ids' => $vehicleIds,
		'coverage_type' => GETPOST('coverage_type', 'alpha') ?: LmdbVehicleInsuranceContract::COVERAGE_PRIMARY,
		'coverage_start' => (int) ($coverageStart ?: $contract->date_start),
		'coverage_end' => $coverageEnd !== null ? $coverageEnd : $contract->date_end,
	);
}

/**
 * Build the vehicle options available in one owner entity.
 *
 * @param DoliDB $db Database handler
 * @param int $entity Owner entity
 * @return array<int,string>
 */
function lmdbInsuranceGetVehicleOptions($db, $entity)
{
	$allowedEntities = array_map('intval', explode(',', getEntity('lmdbvehicle')));
	if ($entity <= 0 || !in_array((int) $entity, $allowedEntities, true)) {
		return array();
	}

	$options = array();
	$sql = 'SELECT rowid, ref, registration_number, label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
	$sql .= ' WHERE entity = '.((int) $entity).' ORDER BY ref';
	$resql = $db->query($sql);
	if (!$resql) {
		return array();
	}
	while (is_object($row = $db->fetch_object($resql))) {
		$options[(int) $row->rowid] = lmdbVehicleDisplayIdentifier((string) $row->ref, (string) $row->registration_number, (string) $row->label);
	}
	$db->free($resql);

	return $options;
}

/**
 * Render the shared native contract form.
 *
 * @param LmdbVehicleInsuranceContract $contract Contract
 * @param Form $form Native form helper
 * @param array<int,string> $vehicleOptions Vehicle options
 * @param list<int> $linkedIds Selected vehicles
 * @param string $coverageType Coverage type
 * @param int $coverageStart Coverage start
 * @param ?int $coverageEnd Coverage end
 * @param string $actionUrl Form target
 * @param array<string,string|int> $hiddenFields Hidden inputs
 * @param bool $showCancel Show the native cancel action
 * @return void
 */
function lmdbInsurancePrintContractForm($contract, $form, $vehicleOptions, $linkedIds, $coverageType, $coverageStart, $coverageEnd, $actionUrl, $hiddenFields, $showCancel = false)
{
	global $langs;

	$contactSocId = (int) $contract->fk_soc > 0 ? (int) $contract->fk_soc : -1;
	$companyEvents = array(
		array(
			'method' => 'getContacts',
			'url' => dol_buildpath('/core/ajax/contacts.php', 1),
			'htmlname' => 'fk_contact',
			'params' => array('add-customer-contact' => 'disabled'),
		),
	);

	print '<form method="POST" action="'.dol_escape_htmltag($actionUrl).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	foreach ($hiddenFields as $name => $value) {
		print '<input type="hidden" name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag((string) $value).'">';
	}
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('InsuranceCompany').'</td><td>'.$form->select_company($contract->fk_soc ?: '', 'fk_soc', '', '-1', 0, 0, $companyEvents, 0, 'minwidth300').'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceContact').'</td><td>'.$form->selectcontacts($contactSocId, (int) $contract->fk_contact, 'fk_contact', 1, '', '', 0, 'minwidth300', 0, 0, 0, array(), '', '', false, 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('InsurancePolicyNumber').'</td><td><input class="flat minwidth300" name="policy_number" value="'.dol_escape_htmltag((string) $contract->policy_number).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Label').'</td><td><input class="flat minwidth500" name="contract_label" value="'.dol_escape_htmltag((string) $contract->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceCoverageFormula').'</td><td><input class="flat minwidth500" name="coverage_formula" value="'.dol_escape_htmltag((string) $contract->coverage_formula).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Period').'</td><td>'.$form->selectDate($contract->date_start ?: -1, 'contract_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate($contract->date_end ?: -1, 'contract_end', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceRenewalMode').'</td><td>'.$form->selectarray('renewal_mode', array('fixed' => $langs->trans('InsuranceRenewalFixed'), 'tacit' => $langs->trans('InsuranceRenewalTacit')), $contract->renewal_mode, 0, 0, 0, '', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceNoticeDate').'</td><td>'.$form->selectDate($contract->notice_date ?: -1, 'notice_date', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceAssistancePhone').'</td><td><input class="flat" name="assistance_phone" value="'.dol_escape_htmltag((string) $contract->assistance_phone).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceAssistanceEmail').'</td><td><input class="flat minwidth300" name="assistance_email" value="'.dol_escape_htmltag((string) $contract->assistance_email).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceClaimPhone').'</td><td><input class="flat" name="claim_phone" value="'.dol_escape_htmltag((string) $contract->claim_phone).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceClaimEmail').'</td><td><input class="flat minwidth300" name="claim_email" value="'.dol_escape_htmltag((string) $contract->claim_email).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Vehicles').'</td><td>'.$form->multiselectarray('vehicle_ids', $vehicleOptions, $linkedIds, 0, 0, 'minwidth500').'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceCoverageType').'</td><td>'.$form->selectarray('coverage_type', array(LmdbVehicleInsuranceContract::COVERAGE_PRIMARY => $langs->trans('InsuranceCoveragePrimary'), LmdbVehicleInsuranceContract::COVERAGE_COMPLEMENTARY => $langs->trans('InsuranceCoverageComplementary')), $coverageType, 0, 0, 0, '', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceCoveragePeriod').'</td><td>'.$form->selectDate($coverageStart ?: -1, 'coverage_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate($coverageEnd ?: -1, 'coverage_end', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>';
	$contractEditor = new DolEditor('contract_description', (string) $contract->description, '', 100, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '100%');
	print $contractEditor->Create(1);
	print '</td></tr>';
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	if ($showCancel) {
		print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'" formnovalidate>';
	}
	print '</div></form>';
}
