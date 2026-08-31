<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$moduleRoot = dirname(__DIR__);

/**
 * Read a module source file for static UI contract checks.
 *
 * @param string $relativePath Path relative to the module root
 * @return string
 */
function readModuleSource($relativePath)
{
	global $moduleRoot;

	$content = file_get_contents($moduleRoot.'/'.$relativePath);
	if (!is_string($content)) {
		fwrite(STDERR, 'Unable to read '.$relativePath.PHP_EOL);
		exit(1);
	}

	return $content;
}

$library = readModuleSource('lib/lmdbvehiclemanagement.lib.php');
$vehicleCard = readModuleSource('vehicle_card.php');
$insurancePage = readModuleSource('vehicle_insurance.php');
$insuranceLink = readModuleSource('vehicle_insurance_link.php');
$insuranceCertificate = readModuleSource('insurancecontract_certificate.php');
$insuranceLibrary = readModuleSource('lib/lmdbvehicleinsurance.lib.php');
$insuranceCard = readModuleSource('insurancecontract_card.php');
$insuranceList = readModuleSource('insurancecontract_list.php');
$insuranceContractClass = readModuleSource('class/lmdbvehicleinsurancecontract.class.php');
$baseObjectClass = readModuleSource('class/lmdbvehiclemanagementobject.class.php');
$insuranceCertificateClass = readModuleSource('class/lmdbvehicleinsurancecertificate.class.php');
$insuranceAdmin = readModuleSource('admin/insurance.php');
$descriptor = readModuleSource('core/modules/modLmdbVehicleManagement.class.php');
$actionsHooks = readModuleSource('class/actions_lmdbvehiclemanagement.class.php');
$insuranceCron = readModuleSource('class/lmdbvehicleinsurancecron.class.php');
$insuranceContact = readModuleSource('insurancecontract_contact.php');
$insuranceNote = readModuleSource('insurancecontract_note.php');
$insuranceDocument = readModuleSource('insurancecontract_document.php');
$insuranceAgenda = readModuleSource('insurancecontract_agenda.php');
$insuranceSql = readModuleSource('sql/llx_lmdbvehiclemanagement_insurance_contract.sql');
$moduleDataSql = readModuleSource('sql/data.sql');
$vehicleClass = readModuleSource('class/lmdbvehicle.class.php');
$vehicleReferenceMigration = readModuleSource('class/lmdbvehiclereferencemigration.class.php');
$vehicleRegistrationNumbering = readModuleSource('core/modules/lmdbvehiclemanagement/mod_lmdbvehicle_registration.php');
$setupPage = readModuleSource('admin/setup.php');
$vehicleList = readModuleSource('vehicle_list.php');
$vehicleEventList = readModuleSource('vehicleevent_list.php');
$vehicleEventCard = readModuleSource('vehicleevent_card.php');
$consumptionClass = readModuleSource('class/lmdbvehicleconsumption.class.php');
$consumptionStats = readModuleSource('class/lmdbvehicleconsumptionstats.class.php');
$consumableClass = readModuleSource('class/lmdbvehicleconsumable.class.php');
$consumptionCard = readModuleSource('consumption_card.php');
$consumptionList = readModuleSource('consumption_list.php');
$consumptionIndex = readModuleSource('consumption_index.php');
$vehicleConsumption = readModuleSource('vehicle_consumption.php');
$vehicleHistory = readModuleSource('vehicle_history.php');
$vehicleHistoryClass = readModuleSource('class/lmdbvehiclehistory.class.php');
$moduleJavascript = readModuleSource('js/lmdbvehiclemanagement.js');
$consumptionSql = readModuleSource('sql/llx_lmdbvehiclemanagement_consumption.sql');
$checks = array();

$orderedTabs = array(
	'vehicle_card.php',
	'vehicle_assignment.php',
	'vehicle_odometer.php',
	'vehicle_consumption.php',
	'vehicle_history.php',
	'vehicle_note.php',
	'vehicle_document.php',
	'vehicle_agenda.php',
);
$previousPosition = -1;
foreach ($orderedTabs as $tabFile) {
	$position = strpos($library, $tabFile);
	$checks['tab_order_'.$tabFile] = $position !== false && $position > $previousPosition;
	if ($position !== false) {
		$previousPosition = $position;
	}
}

$vehicleTabPages = array(
	'vehicle_card.php',
	'vehicle_assignment.php',
	'vehicle_odometer.php',
	'vehicle_consumption.php',
	'vehicle_history.php',
	'vehicle_note.php',
	'vehicle_document.php',
	'vehicle_agenda.php',
);
foreach ($vehicleTabPages as $pageFile) {
	$checks['common_banner_'.$pageFile] = strpos(readModuleSource($pageFile), 'lmdbVehiclePrintBanner(') !== false;
}

$checks['banner_uses_native_helper'] = strpos($library, 'dol_banner_tab(') !== false;
$checks['banner_keeps_multicompany_badge'] = strpos($library, 'multicompany-entity-card-container') !== false;
$checks['registration_numbering_model_is_native'] = strpos($vehicleRegistrationNumbering, 'extends ModeleNumRefLmdbVehicle') !== false
	&& strpos($vehicleRegistrationNumbering, 'normalizeRegistrationNumber') !== false;
$checks['vehicle_ref_is_synchronized_on_create_and_update'] = substr_count($vehicleClass, 'usesRegistrationAsReference()') >= 2
	&& strpos($vehicleClass, '$this->ref = $this->registration_number;') !== false;
$checks['vehicle_reference_migration_is_transactional'] = strpos($vehicleReferenceMigration, '$this->db->begin();') !== false
	&& strpos($vehicleReferenceMigration, '$this->db->rollback();') !== false
	&& strpos($vehicleReferenceMigration, 'rollbackFilesystem()') !== false;
$checks['vehicle_reference_migration_updates_documents_and_ecm'] = strpos($vehicleReferenceMigration, 'getMultidirOutput(') !== false
	&& strpos($vehicleReferenceMigration, "MAIN_DB_PREFIX.'ecm_files") !== false
	&& strpos($vehicleReferenceMigration, 'last_main_doc') !== false;
$checks['vehicle_numbering_change_requires_confirmation'] = strpos($setupPage, "'confirm_setmod'") !== false
	&& strpos($setupPage, 'ConfirmVehicleReferenceMigration') !== false;
$checks['registration_mode_hides_redundant_ref_by_default'] = strpos($vehicleList, "'checked' => LmdbVehicle::usesRegistrationAsReference() ? 0 : 1") !== false;
$checks['description_uses_native_wysiwyg'] = strpos($vehicleCard, "new DolEditor('description'") !== false;
$checks['insurance_description_uses_native_wysiwyg'] = strpos($insuranceLibrary, "new DolEditor('contract_description'") !== false;
$checks['insurance_allows_new_contract_after_first_one'] = strpos($library, 'insurancecontract_card.php') !== false
	&& strpos($library, 'vehicle_id=') !== false;
$checks['description_uses_restricted_html_input'] = strpos($vehicleCard, "GETPOST('description', 'restricthtml')") !== false;
$checks['status_row_is_hidden'] = strpos($vehicleCard, "langs->trans('Status')") === false;
$checks['actions_use_native_buttons'] = strpos($vehicleCard, "dolGetButtonAction('', \$langs->trans('Validate')") !== false;
$ficheEndPosition = strpos($vehicleCard, 'print dol_get_fiche_end();');
$tabsActionPosition = strpos($vehicleCard, 'print \'<div class="tabsAction">\';');
$checks['actions_follow_native_fiche_end'] = $ficheEndPosition !== false && $tabsActionPosition !== false && $ficheEndPosition < $tabsActionPosition;
$settingsPosition = strpos($library, '/admin/setup.php');
$insurancePosition = strpos($library, '/admin/insurance.php');
$compatibilityPosition = strpos($library, '/admin/compatibility.php');
$checks['insurance_admin_tab_order'] = $settingsPosition !== false && $insurancePosition !== false && $compatibilityPosition !== false && $settingsPosition < $insurancePosition && $insurancePosition < $compatibilityPosition;
$checks['insurance_summary_is_in_half_right'] = strpos($vehicleCard, '<div class="fichehalfright">') !== false && strpos($vehicleCard, 'lmdbVehiclePrintInsuranceBlock($object)') !== false;
$checks['insurance_modal_route_is_removed'] = strpos($vehicleCard, 'mode=modal') === false
	&& strpos($vehicleCard, 'lmdb-insurance-modal-open') === false
	&& strpos($insurancePage, 'insurancecontract_certificate.php') !== false;
$checks['insurance_block_uses_contract_nomurl'] = strpos($library, '$contract->getNomUrl(1)') !== false;
$checks['insurance_block_title_has_no_redundant_picto'] = strpos($library, "img_picto('', 'shield-alt', 'class=\"pictofixedwidth\"').\$langs->trans('InsuranceContract')") === false
	&& strpos($library, '<span class="right marginleftonly">') !== false;
$checks['insurance_contract_nomurl_uses_native_ajax_tooltip'] = strpos($insuranceContractClass, "getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')") !== false
	&& strpos($insuranceContractClass, "'objecttype' => \$this->element.'@'.\$this->module") !== false
	&& strpos($insuranceContractClass, 'classforajaxtooltip') !== false
	&& strpos($insuranceContractClass, 'public function getTooltipContentArray($params)') !== false
	&& strpos($insuranceContractClass, "trans('InsurancePolicyNumber')") !== false
	&& strpos($insuranceContractClass, "trans('InsuranceContractPeriod')") !== false;
$checks['insurance_block_uses_native_link_and_create_icons'] = strpos($library, "'fa fa-link'") !== false
	&& strpos($library, "'fa fa-plus-circle'") !== false;
$checks['insurance_compatibility_route_only_redirects'] = strpos($insurancePage, "header('Location: '") !== false
	&& strpos($insurancePage, '<form') === false;
$checks['insurance_mutations_use_csrf_tokens'] = substr_count($insuranceCertificate, "name=\"token\" value=\"'.newToken()") >= 3
	&& strpos($insuranceLink, "name=\"token\" value=\"'.newToken()") !== false;
$checks['insurance_uses_native_permission_checks'] = strpos($insuranceCertificate, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'write')") !== false
	&& strpos($insuranceCertificate, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'upload')") !== false
	&& strpos($insuranceCertificate, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'validate')") !== false
	&& strpos($insuranceCertificate, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'delete')") !== false;
$checks['insurance_download_is_read_only_route'] = strpos($insuranceCertificate, '$downloadCertificate === 1') !== false && strpos($insuranceCertificate, "\$action === 'download_certificate'") === false;
$checks['insurance_admin_uses_native_selects_and_switches'] = strpos($insuranceAdmin, 'ajax_constantonoff(') !== false && strpos($insuranceAdmin, "multiselectarray('recipient_users'") !== false && strpos($insuranceAdmin, "multiselectarray('recipient_groups'") !== false;
$checks['insurance_cron_is_declared'] = strpos($descriptor, "'method' => 'sendCertificateReminders'") !== false && strpos($insuranceCron, 'INSERT IGNORE INTO') !== false;
$checks['module_version_is_0112'] = strpos($descriptor, "\$this->version = '0.11.2';") !== false;
$checks['consumption_uses_native_quick_add_hook'] = strpos($descriptor, "'main',") !== false
	&& strpos($actionsHooks, 'function menuDropdownQuickaddItems(') !== false
	&& strpos($actionsHooks, "dol_buildpath('/lmdbvehiclemanagement/consumption_card.php', 1)") !== false
	&& strpos($actionsHooks, 'DOL_URL_ROOT_ALT') === false
	&& strpos($actionsHooks, "'title' => 'NewConsumption@lmdbvehiclemanagement'") !== false
	&& strpos($actionsHooks, "hasRight('lmdbvehiclemanagement', 'consumption', 'write')") !== false;
$checks['module_objects_use_native_ajax_tooltips'] = strpos($baseObjectClass, 'public function getTooltipContentArray($params)') !== false
	&& strpos($baseObjectClass, "getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')") !== false
	&& strpos($baseObjectClass, "'objecttype' => \$this->element.'@'.\$this->module") !== false
	&& strpos($baseObjectClass, 'classforajaxtooltip') !== false;
$checks['ajax_tooltips_resolve_all_business_objects'] = strpos($actionsHooks, "'lmdbvehicleassignment@lmdbvehiclemanagement'") !== false
	&& strpos($actionsHooks, "'lmdbvehicleodometerreading@lmdbvehiclemanagement'") !== false
	&& strpos($actionsHooks, "'lmdbvehicleconsumption@lmdbvehiclemanagement'") !== false
	&& strpos($actionsHooks, "'lmdbinsurancecertificate@lmdbvehiclemanagement'") !== false;
$checks['timeline_includes_consumptions'] = strpos($vehicleHistoryClass, "'consumption' AS source_code") !== false
	&& strpos($vehicleHistoryClass, "MAIN_DB_PREFIX.\"lmdbvehiclemanagement_consumption") !== false
	&& strpos($vehicleHistory, "'consumption' => \$langs->trans('TimelineSourceConsumption')") !== false;
$checks['timeline_defaults_to_newest_first'] = strpos($vehicleHistory, "?: 'event_timestamp'") !== false
	&& strpos($vehicleHistory, "=== 'ASC' ? 'ASC' : 'DESC'") !== false
	&& strpos($vehicleHistoryClass, "'event_timestamp' => 'timeline.event_timestamp'") !== false;
$checks['timeline_uses_native_field_filters_and_sorting'] = strpos($vehicleHistory, "selectDate(\$searchDateStart") !== false
	&& strpos($vehicleHistory, "multiselectarray('search_source'") !== false
	&& strpos($vehicleHistory, "getTitleFieldOfList('Date'") !== false
	&& strpos($vehicleHistory, "getTitleFieldOfList('TimelineDocuments'") !== false
	&& strpos($vehicleHistoryClass, 'private function buildFilterSql($filters)') !== false;
$checks['vehicle_event_keeps_native_required_validation'] = strpos($vehicleEventCard, 'name="label" maxlength="255" required') !== false;
$checks['vehicle_event_cancel_bypasses_required_validation'] = strpos($vehicleEventCard, 'name="cancel" value="\'.$langs->trans(\'Cancel\').\'" formnovalidate') !== false;
$checks['dedicated_top_menu_is_declared'] = strpos($descriptor, "'type' => 'top'") !== false
	&& strpos($descriptor, "'mainmenu' => 'lmdbvehiclemanagement'") !== false
	&& strpos($descriptor, "'fk_menu' => 'fk_mainmenu=tools'") === false;
$checks['dedicated_top_menu_uses_native_car_icon'] = strpos($descriptor, "img_picto('', \$this->picto, 'class=\"pictofixedwidth valignmiddle\"')") !== false
	&& strpos($descriptor, "'MAIN_MODULE_LMDBVEHICLEMANAGEMENT_ICON' => 'fa-car'") !== false;
$checks['insurance_menu_has_create_and_list'] = strpos($descriptor, '/insurancecontract_card.php?action=create') !== false
	&& strpos($descriptor, '/insurancecontract_list.php') !== false
	&& strpos($descriptor, '$user->hasRight("lmdbvehiclemanagement", "insurance", "write")') !== false;
$checks['insurance_contract_has_dedicated_card'] = strpos($insuranceContractClass, "return 'insurancecontract_card.php';") !== false;
$checks['insurance_list_uses_native_pattern'] = strpos($insuranceList, 'print_barre_liste(') !== false
	&& strpos($insuranceList, "multiSelectArrayWithCheckbox('selectedfields'") !== false
	&& strpos($insuranceList, 'div-table-responsive') !== false
	&& strpos($insuranceList, 'multicompany-entity-card-container') !== false;
$checks['insurance_list_bar_is_inside_form'] = strpos($insuranceList, "print '<form method=\"POST\"") < strpos($insuranceList, 'print_barre_liste(');
$checks['insurance_pages_use_native_permissions'] = strpos($insuranceCard, "\$user->hasRight('lmdbvehiclemanagement', 'read')") !== false
	&& strpos($insuranceCard, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'write')") !== false
	&& strpos($insuranceList, "\$user->hasRight('lmdbvehiclemanagement', 'read')") !== false;
$checks['insurance_form_is_shared'] = strpos($insuranceCard, 'lmdbInsurancePrintContractForm(') !== false
	&& strpos($insuranceLibrary, 'function lmdbInsurancePrintContractForm') !== false;
$insuranceCardClearPosition = strpos($insuranceCard, 'print \'<div class="clearboth"></div>\';');
$insuranceCardActionsPosition = strpos($insuranceCard, 'print \'<div class="tabsAction">\';');
$checks['insurance_card_status_row_is_hidden'] = strpos($insuranceCard, "langs->trans('Status')") === false;
$checks['insurance_card_actions_follow_native_clear'] = $insuranceCardClearPosition !== false
	&& $insuranceCardActionsPosition !== false
	&& $insuranceCardClearPosition < $insuranceCardActionsPosition;
$insuranceOrderedTabs = array(
	'insurancecontract_card.php',
	'insurancecontract_contact.php',
	'insurancecontract_note.php',
	'insurancecontract_document.php',
	'insurancecontract_agenda.php',
	'insurancecontract_certificate.php',
);
$previousInsuranceTabPosition = strpos($library, 'function lmdbInsuranceContractPrepareHead');
foreach ($insuranceOrderedTabs as $tabFile) {
	$position = strpos($library, $tabFile, $previousInsuranceTabPosition);
	$checks['insurance_tab_order_'.$tabFile] = $position !== false && $position > $previousInsuranceTabPosition;
	if ($position !== false) $previousInsuranceTabPosition = $position;
}
$insuranceTabPages = array(
	'insurancecontract_card.php' => $insuranceCard,
	'insurancecontract_contact.php' => $insuranceContact,
	'insurancecontract_note.php' => $insuranceNote,
	'insurancecontract_document.php' => $insuranceDocument,
	'insurancecontract_agenda.php' => $insuranceAgenda,
	'insurancecontract_certificate.php' => $insuranceCertificate,
);
foreach ($insuranceTabPages as $pageFile => $pageSource) {
	$expectedObject = $pageFile === 'insurancecontract_certificate.php' ? '$contract' : '$object';
	$checks['insurance_common_banner_'.$pageFile] = strpos($pageSource, 'lmdbInsuranceContractPrintBanner('.$expectedObject.')') !== false;
}
$checks['insurance_certificate_tab_owns_workflow'] = strpos($insuranceCertificate, "'submit_existing_certificate'") !== false
	&& strpos($insuranceCertificate, "'validate_certificate'") !== false
	&& strpos($insuranceCertificate, "'reject_certificate'") !== false
	&& strpos($insuranceCertificate, "'archive_certificate'") !== false;
$checks['insurance_link_is_transactional_and_non_replacing'] = strpos($insuranceContractClass, 'public function linkVehicle(') !== false
	&& strpos($insuranceContractClass, "'vehicle_link'") !== false
	&& strpos($insuranceLink, '->linkVehicle(') !== false;
$checks['insurance_card_uses_native_tabs'] = strpos($insuranceCard, "dol_get_fiche_head(\$head, 'card'") !== false
	&& strpos($insuranceCard, 'lmdbInsuranceContractPrepareHead($object)') !== false;
$checks['insurance_contacts_use_native_template'] = strpos($insuranceContact, '/contacts.tpl.php') !== false
	&& strpos($insuranceContact, '$object->socid = (int) $object->fk_soc') !== false
	&& strpos($insuranceContact, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'write')") !== false;
$checks['insurance_notes_use_native_actions_and_template'] = strpos($insuranceNote, '/core/actions_setnotes.inc.php') !== false
	&& strpos($insuranceNote, '/notes.tpl.php') !== false;
$checks['insurance_documents_use_native_actions_and_template'] = strpos($insuranceDocument, '/core/actions_linkedfiles.inc.php') !== false
	&& strpos($insuranceDocument, '/core/tpl/document_actions_post_headers.tpl.php') !== false
	&& strpos($insuranceDocument, "getMultidirOutput(\$object, 'lmdbvehiclemanagement', 1)") !== false;
$checks['insurance_agenda_uses_native_list_contract'] = strpos($insuranceAgenda, 'print_barre_liste(') !== false
	&& strpos($insuranceAgenda, "lmdbInsuranceContractAgendaWhere(\$object, 'a')") !== false
	&& strpos($insuranceAgenda, "\$origin = urlencode(\$object->element.'@'.\$object->module)") !== false;
$checks['insurance_notes_schema_is_migrated'] = strpos($insuranceContractClass, "'note_public' => array('type' => 'html'") !== false
	&& strpos($insuranceContractClass, "'note_private' => array('type' => 'html'") !== false
	&& strpos($insuranceSql, 'note_public text') !== false
	&& strpos($insuranceSql, 'note_private text') !== false
	&& strpos($descriptor, 'prepareInsuranceContractSchema()') !== false;
$checks['insurance_contact_roles_are_native_and_idempotent'] = strpos($moduleDataSql, "'lmdbinsurancecontract', 'internal', 'CONTRACTMANAGER'") !== false
	&& strpos($moduleDataSql, "'lmdbinsurancecontract', 'external', 'INSURANCECONTACT'") !== false
	&& substr_count($moduleDataSql, "WHERE element = 'lmdbinsurancecontract'") === 2
	&& substr_count($moduleDataSql, 'WHERE NOT EXISTS (') >= 2
	&& strpos($baseObjectClass, "ctc.element = '") !== false;
$checks['insurance_post_actions_use_native_button_size'] = strpos($insuranceCard, '<button type="submit" class="butAction">') !== false
	&& strpos($insuranceCard, "lmdbInsuranceContractPostButton(\$id, 'activate', \$langs->trans('Activate'))") !== false
	&& strpos($insuranceCard, "lmdbInsuranceContractPostButton(\$id, 'terminate', \$langs->trans('Terminate'))") !== false;
$checks['insurance_card_uses_native_transverse_blocks'] = strpos($insuranceCard, "getMultidirOutput(\$object, 'lmdbvehiclemanagement', 1)") !== false
	&& strpos($insuranceCard, '$formfile->showdocuments(') !== false
	&& strpos($insuranceCard, '$form->showLinkedObjectBlock($object)') !== false
	&& strpos($insuranceCard, "\$formActions->showactions(\$object, \$object->element.'@'.\$object->module") !== false;
$checks['insurance_card_transverse_blocks_follow_actions'] = strpos($insuranceCard, '$formfile->showdocuments(') > $insuranceCardActionsPosition
	&& strpos($insuranceCard, '$form->showLinkedObjectBlock($object)') > $insuranceCardActionsPosition
	&& strpos($insuranceCard, '$formActions->showactions(') > $insuranceCardActionsPosition;
$checks['insurance_required_fields_use_native_style'] = strpos($insuranceLibrary, 'titlefieldcreate fieldrequired') !== false
	&& substr_count($insuranceLibrary, 'fieldrequired') >= 4
	&& strpos($insuranceLibrary, ' required') === false;
$checks['insurance_cancel_bypasses_browser_validation'] = strpos($insuranceLibrary, 'formnovalidate') !== false;
$checks['insurance_required_fields_use_commonobject_validation'] = strpos($insuranceContractClass, '$this->validateField($this->fields, $fieldKey') !== false
	&& strpos($insuranceLibrary, 'lmdbInsuranceContractInvalidFields') === false;
$checks['insurance_contact_is_filtered_by_company'] = strpos($insuranceLibrary, "'method' => 'getContacts'") !== false
	&& strpos($insuranceLibrary, "dol_buildpath('/core/ajax/contacts.php', 1)") !== false
	&& strpos($insuranceLibrary, "'htmlname' => 'fk_contact'") !== false
	&& strpos($insuranceLibrary, '$contactSocId') !== false;
$checks['insurance_controls_keep_native_text_color'] = strpos($insurancePage, 'invalid_fields') === false
	&& strpos($vehicleCard, 'response.invalid_fields') === false
	&& strpos($insuranceLibrary, "' error'") === false;
$checks['insurance_pages_use_native_error_messages'] = strpos($insuranceCertificate, 'setEventMessages(') !== false
	&& strpos($insuranceLink, 'setEventMessages(') !== false
	&& strpos($vehicleCard, 'alert(') === false;
$checks['contract_trigger_zero_commits'] = strpos($insuranceContractClass, 'if ($triggerResult < 0)') !== false
	&& strpos($insuranceContractClass, '$this->db->commit();') !== false
	&& strpos($insuranceContractClass, 'return 1;') !== false;
$checks['certificate_trigger_zero_commits'] = strpos($insuranceCertificateClass, 'if ($triggerResult < 0)') !== false
	&& strpos($insuranceCertificateClass, '$this->db->commit();') !== false
	&& strpos($insuranceCertificateClass, 'return 1;') !== false;
$checks['consumption_menus_use_native_permissions'] = strpos($descriptor, '/consumption_index.php') !== false
	&& strpos($descriptor, '/consumption_card.php?action=create') !== false
	&& strpos($descriptor, '$user->hasRight("lmdbvehiclemanagement", "consumption", "write")') !== false;
$checks['consumption_owns_one_odometer_reading'] = strpos($consumptionSql, 'fk_odometer_reading integer NOT NULL') !== false
	&& strpos($consumptionSql, 'odometer_km') === false
	&& strpos($consumptionSql, 'reading_date') === false;
$checks['consumption_synchronizes_odometer_transactionally'] = strpos($consumptionClass, '$this->db->begin();') !== false
	&& strpos($consumptionClass, 'createFromConsumption(') !== false
	&& strpos($consumptionClass, 'updateFromConsumption(') !== false
	&& strpos($consumptionClass, 'deleteFromConsumption(') !== false;
$checks['consumption_form_uses_native_required_style'] = strpos($consumptionCard, 'titlefieldcreate fieldrequired') !== false
	&& substr_count($consumptionCard, 'fieldrequired') >= 6
	&& strpos($consumptionCard, ' required') === false;
$checks['consumption_pages_use_direct_rights'] = strpos($consumptionCard, "\$user->hasRight('lmdbvehiclemanagement', 'consumption', 'write')") !== false
	&& strpos($consumptionList, "\$user->hasRight('lmdbvehiclemanagement', 'read')") !== false;
$checks['consumption_creator_is_default_driver'] = strpos($consumptionClass, 'if (empty($this->fk_user_driver))') !== false
	&& strpos($consumptionClass, '$this->fk_user_driver = (int) $user->id;') !== false
	&& strpos($consumptionCard, '$object->fk_user_driver = (int) $user->id;') !== false
	&& strpos($consumptionCard, 'suggestedDriver') === false;
$checks['consumption_effective_driver_is_used_everywhere'] = substr_count($consumptionClass, 'fk_user_driver') > 0
	&& substr_count($consumptionList, 'COALESCE(t.fk_user_driver, t.fk_user_creat)') >= 3
	&& strpos($consumptionIndex, "\$statsFilters['user_id'] = \$driverId") !== false
	&& substr_count($descriptor, 'COALESCE(t.fk_user_driver, t.fk_user_creat)') === 1;
$checks['consumption_summary_filters_are_server_side'] = strpos($consumptionIndex, "\$statsFilters['vehicle_id'] = \$vehicleId") !== false
	&& strpos($consumptionIndex, "\$statsFilters['user_id'] = \$driverId") !== false
	&& strpos($consumptionIndex, "\$statsFilters['consumable_id'] = \$consumableId") !== false
	&& strpos($consumptionIndex, "\$statsFilters['category'] = \$category") !== false
	&& strpos($consumptionIndex, "\$statsFilters['date_start'] = \$dateStart") !== false
	&& strpos($consumptionIndex, "array('date_end' => \$effectiveDateEnd)") !== false
	&& strpos($consumptionIndex, "\$statsFilters['entity_ids'] = \$safeEntities") !== false
	&& strpos($consumptionStats, "t.fk_vehicle = '.((int) \$filters['vehicle_id'])") !== false
	&& strpos($consumptionStats, "t.fk_consumable = '.((int) \$filters['consumable_id'])") !== false
	&& strpos($consumptionStats, "t.category_snapshot = '") !== false
	&& strpos($consumptionStats, 'r.reading_date >=') !== false
	&& strpos($consumptionStats, 'r.reading_date <=') !== false
	&& strpos($consumptionStats, "implode(',', \$ids)") !== false;
$checks['consumption_summary_filters_use_native_reset_and_safe_dates'] = strpos($consumptionIndex, "GETPOST('button_removefilter', 'alpha')") !== false
	&& strpos($consumptionIndex, '$form->showFilterButtons()') !== false
	&& strpos($consumptionIndex, '$dateStartDay > 0 && $dateStartMonth > 0 && $dateStartYear > 0') !== false
	&& strpos($consumptionIndex, '$dateEndDay > 0 && $dateEndMonth > 0 && $dateEndYear > 0') !== false;
$checks['consumption_summary_period_is_empty_and_open_ended'] = substr_count($consumptionIndex, '?: -1') >= 2
	&& strpos($consumptionIndex, '$effectiveDateEnd = $dateEnd > 0 ? $dateEnd : dol_now();') !== false
	&& strpos($consumptionIndex, "\$statsFilters['date_start'] = \$dateStart") !== false
	&& strpos($consumptionIndex, "array('date_end' => \$effectiveDateEnd)") !== false;
$checks['consumption_empty_selectors_do_not_filter'] = substr_count($consumptionIndex, ' <= 0)') >= 3
	&& strpos($consumptionIndex, "!in_array(\$category, array('fuel', 'additive'), true)") !== false
	&& substr_count($consumptionIndex, ' > 0 ? $') >= 3
	&& strpos($consumptionIndex, "select_dolusers(\$driverId > 0 ? \$driverId : -1") !== false
	&& substr_count($consumptionIndex, 'return $entityId > 0;') >= 1
	&& substr_count($consumptionStats, "isset(\$filters[") >= 3
	&& strpos($consumptionStats, "return \$entityId > 0;") !== false;
$checks['consumption_stats_filter_uses_effective_driver'] = strpos($consumptionClass, '$this->fk_user_driver = (int) $this->fk_user_creat;') !== false
	&& substr_count($consumptionStats, 'COALESCE(t.fk_user_driver, t.fk_user_creat)') >= 3;
$checks['consumption_list_is_native'] = strpos($consumptionList, 'print_barre_liste(') !== false
	&& strpos($consumptionList, "multiSelectArrayWithCheckbox('selectedfields'") !== false
	&& strpos($consumptionList, 'multicompany-entity-card-container') !== false
	&& strpos($consumptionList, "trans('NoRecordFound')") !== false;
$mainListSources = array($vehicleList, $vehicleEventList, $insuranceList, $consumptionList);
$checks['main_lists_reserve_native_height'] = count(array_filter($mainListSources, static function ($source) {
	return strpos($source, 'div-table-responsive') !== false
		&& strpos($source, 'div-table-responsive-no-min') === false;
})) === count($mainListSources);
$checks['main_list_column_selectors_use_native_page_context'] = count(array_filter($mainListSources, static function ($source) {
	return strpos($source, "\$contextpage = GETPOST('contextpage', 'aZ09');") !== false
		&& strpos($source, "\$varpage = empty(\$contextpage) ? \$_SERVER['PHP_SELF'] : \$contextpage;") !== false
		&& strpos($source, "multiSelectArrayWithCheckbox('selectedfields', \$arrayfields, \$varpage") !== false;
})) === count($mainListSources);
$checks['main_list_column_selectors_submit_with_native_filter_action'] = count(array_filter($mainListSources, static function ($source) {
	return strpos($source, 'name="formfilteraction" id="formfilteraction" value="list"') !== false;
})) === count($mainListSources);
$checks['main_list_action_column_follows_native_position'] = count(array_filter($mainListSources, static function ($source) {
	return strpos($source, "multiSelectArrayWithCheckbox('selectedfields', \$arrayfields, \$varpage, \$conf->main_checkbox_left_column)") !== false
		&& strpos($source, "if (\$conf->main_checkbox_left_column) print '<td class=\"liste_titre center maxwidthsearch actioncolumn\">'.\$form->showFilterButtons('left')") !== false
		&& strpos($source, "if (!\$conf->main_checkbox_left_column) print '<td class=\"liste_titre center maxwidthsearch actioncolumn\">'.\$form->showFilterButtons()") !== false
		&& substr_count($source, 'getTitleFieldOfList($selectedfields') === 2
		&& strpos($source, 'center maxwidthsearch ') !== false;
})) === count($mainListSources);
$checks['consumption_analytics_use_dolgraph_only'] = strpos($consumptionIndex, 'lmdbVehicleConsumptionRenderGraph(') !== false
	&& strpos($vehicleConsumption, 'lmdbVehicleConsumptionRenderGraph(') !== false
	&& strpos($library, 'new DolGraph()') !== false;
$checks['consumption_graphs_use_stable_native_tables'] = strpos($library, '<table class="noborder centpercent tableforfield" style="table-layout: fixed;">') !== false
	&& strpos($library, '<tr class="liste_titre"><th>') !== false
	&& strpos($library, '<td class="center valignmiddle" style="height: 220px;">') !== false;
$checks['consumption_empty_graphs_keep_table_and_title'] = strpos($library, "\$content = '<span class=\"opacitymedium\">'.\$langs->trans('NoRecordFound')") !== false
	&& strpos($library, "if (empty(\$data)) return") === false
	&& strpos($library, 'SetTitle($title)') === false;
$checks['vehicle_capacity_labels_and_units_are_rendered_separately'] = strpos($vehicleCard, '$consumableDictionary->getCapacityOptions()') !== false
	&& strpos($vehicleCard, "dol_escape_htmltag(\$capacityLabel)") !== false
	&& strpos($vehicleCard, "dol_escape_htmltag(\$consumableOption['unit'])") !== false
	&& strpos($consumableClass, 'html_entity_decode($label, ENT_QUOTES | ENT_HTML5') !== false;
$checks['vehicle_capacities_follow_selected_energy'] = strpos($vehicleCard, 'data-energy-ids=') !== false
	&& strpos($descriptor, "'js' => array('/lmdbvehiclemanagement/js/lmdbvehiclemanagement.js')") !== false
	&& strpos($moduleJavascript, "select[name=\"fk_energy\"]") !== false
	&& strpos($moduleJavascript, "document.addEventListener('change'") !== false
	&& strpos($moduleJavascript, "select2:select select2:clear") !== false
	&& strpos($moduleJavascript, 'input.disabled = !visible') !== false
	&& substr_count($vehicleCard, 'getCapacityOptions((int) $object->fk_energy)') >= 3
	&& strpos($vehicleClass, 'DELETE cap FROM') !== false
	&& strpos($vehicleClass, "AND ce.fk_energy = '.((int) \$this->fk_energy)") !== false;

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed UI contract checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' UI contract checks passed'.PHP_EOL;
