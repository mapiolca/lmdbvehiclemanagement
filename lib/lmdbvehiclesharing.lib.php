<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once __DIR__.'/../class/lmdbvehiclesharing.class.php';

/** POST controller shared by existing cards, before their business actions/hooks. @param LmdbVehicleManagementObject $object Object @return void */
function lmdbSharingAction($object)
{
	global $user, $langs;
	if (GETPOST('action', 'aZ09') !== 'lmdb_save_sharing') return;
	// main.inc.php checks the session token; require POST and a token even if core checking is optional.
	$token = GETPOST('token', 'alphanohtml');
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $token === '' || empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $token)) accessforbidden();
	$element = LmdbVehicleSharing::element((string) $object->element);
	$submitted = GETPOST($element.'_to', 'array', 2);
	if (!is_array($submitted)) accessforbidden();
	$ids = array();
	foreach ($submitted as $value) {
		if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) accessforbidden();
		$ids[] = (int) $value;
	}
	$result = $object->setSharingEntities($user, $ids);
	if ($result < 0) setEventMessages($langs->trans($object->error), null, 'errors');
	else setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	$url = lmdbSharingObjectUrl($object);
	header('Location: '.$url); exit;
}

/** @param LmdbVehicleManagementObject $object Object @return string Local card URL */
function lmdbSharingObjectUrl($object)
{
	$element = LmdbVehicleSharing::element((string) $object->element);
	$cards = array('lmdbvehicle' => 'vehicle_card.php', 'lmdbinsurancecontract' => 'insurancecontract_card.php', 'lmdbvehicleregulatorycontrol' => 'regulatorycontrol_card.php', 'lmdbvehicleconsumption' => 'consumption_card.php', 'lmdbvehicleevent' => 'vehicleevent_card.php');
	if (isset($cards[$element])) return dol_buildpath('/lmdbvehiclemanagement/'.$cards[$element], 1).'?id='.(int) $object->id;
	if ($element === 'lmdbinsurancecertificate') return dol_buildpath('/lmdbvehiclemanagement/insurancecontract_certificate.php', 1).'?id='.(int) $object->fk_contract.'&certificate_id='.(int) $object->id;
	if ($element === 'lmdbvehicleassignment') return dol_buildpath('/lmdbvehiclemanagement/vehicle_assignment.php', 1).'?id='.(int) $object->fk_vehicle.'&assignment_id='.(int) $object->id;
	return dol_buildpath('/lmdbvehiclemanagement/vehicle_odometer.php', 1).'?id='.(int) $object->fk_vehicle.'&reading_id='.(int) $object->id;
}

/** Native selector is reused with a module-owned, guarded POST action. @param LmdbVehicleManagementObject $object Object @return void */
function lmdbSharingRender($object)
{
	global $db, $user, $conf, $langs;
	if (empty($object->id) || !LmdbVehicleSharing::available()) return;
	$element = LmdbVehicleSharing::element((string) $object->element);
	if (!LmdbVehicleSharing::individual($element)) return;
	$langs->loadLangs(array('multicompany@multicompany', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	print '<div class="fichecenter"><div class="titre">'.$langs->trans('MulticompanySharedWithThisEntities').'</div>';
	if ((int) $object->entity === (int) $conf->entity && LmdbVehicleSharing::isAdmin($user)) {
		try { $destinations = LmdbVehicleSharing::destinations($db, $user, $object); }
		catch (Exception $e) { print '<div class="error">'.$langs->trans('LmdbSharingDatabaseError').'</div></div>'; return; }
		if (!$destinations) { print '<span class="opacitymedium">'.$langs->trans('LmdbSharingNoDestinations').'</span></div>'; return; }
		require_once __DIR__.'/../class/lmdbvehiclesharingform.class.php';
		$native = new LmdbVehicleSharingForm($db);
		$native->sharingObjectId = (int) $object->id;
		$selectorObject = clone $object;
		$selectorObject->id = 0;
		print '<form method="POST" action="'.dol_escape_htmltag(lmdbSharingObjectUrl($object)).'">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="lmdb_save_sharing">';

		$html = $native->formSelectSharingByElement($selectorObject, $element);
		$html = preg_replace_callback('~<option\b[^>]*value=["\']([0-9]+)["\'][^>]*>.*?</option>~s', static function ($match) use ($destinations) {
			return isset($destinations[(int) $match[1]]) ? $match[0] : '';
		}, $html);
		print $html;
		print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div></form>';
		print '<span class="opacitymedium">'.$langs->trans('LmdbSharingParentHelp').'</span>';
	} else {
		// No destination inventory is disclosed to beneficiary entities.
		print '<span class="opacitymedium">'.$langs->trans('LmdbSharingOwnerOnly').'</span>';
	}
	print '</div><br>';
}

/** Sharing entry for objects managed in existing sublists. @param LmdbVehicleManagementObject $object @return string */
function lmdbSharingLink($object)
{
	global $langs;
	if (!LmdbVehicleSharing::available() || !LmdbVehicleSharing::individual($object->element)) return '';
	return '<a class="marginrightonly" href="'.dol_escape_htmltag(lmdbSharingObjectUrl($object)).'">'.img_picto($langs->trans('LmdbSharedWith'), 'globe').'</a> ';
}

/** Inform without querying or disclosing the number of hidden source records. @return void */
function lmdbSharingPartialNotice()
{
	global $langs;
	if (LmdbVehicleSharing::partialView()) print '<div class="info">'.$langs->trans('LmdbSharingPartialView').'</div>';
}
