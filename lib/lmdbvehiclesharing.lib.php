<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once __DIR__.'/../class/lmdbvehiclesharing.class.php';

/**
 * POST controller shared by existing cards, before business actions/hooks.
 * @param LmdbVehicleManagementObject $object Object
 * @param bool $redirectOnError False to redisplay the dedicated editor with its selection
 * @return void
 */
function lmdbSharingAction($object, $redirectOnError = true)
{
	global $langs;
	if (GETPOST('action', 'aZ09') !== 'lmdb_save_sharing') return;
	// main.inc.php checks the session token; require POST and a token even if core checking is optional.
	$token = GETPOST('token', 'alphanohtml');
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $token === '' || empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $token)) accessforbidden();
	$result = lmdbSharingSavePosted($object);
	if ($result < 0) {
		setEventMessages($langs->trans($object->error), null, 'errors');
		if (!$redirectOnError) return;
	} else setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	$url = lmdbSharingObjectUrl($object);
	header('Location: '.$url); exit;
}

/**
 * Save the native selection inside the caller's object transaction.
 * An absent embedded selector leaves existing grants untouched.
 * @param LmdbVehicleManagementObject $object Persisted object
 * @param bool $embedded Selection belongs to the create/edit form
 * @return int Positive on success, negative on invalid submission
 */
function lmdbSharingSavePosted($object, $embedded = false)
{
	global $user;
	$element = LmdbVehicleSharing::element((string) $object->element);
	$marker = GETPOST('lmdb_sharing_present', 'aZ09', 2);
	if ($embedded && $marker === '') return 1;
	$token = GETPOST('token', 'alphanohtml', 2);
	if (($embedded && $marker !== $element) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
		|| $token === '' || empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $token)) {
		$object->error = 'NotEnoughPermissions'; return -1;
	}
	$submitted = GETPOST($element.'_to', 'array', 2);
	if (!is_array($submitted)) { $object->error = 'NotEnoughPermissions'; return -1; }
	$ids = array();
	foreach ($submitted as $value) {
		if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) { $object->error = 'NotEnoughPermissions'; return -1; }
		$ids[] = (int) $value;
	}
	return $object->setSharingEntities($user, $ids);
}

/** @param LmdbVehicleManagementObject $object Object @return string Local card URL */
function lmdbSharingObjectUrl($object)
{
	$element = LmdbVehicleSharing::element((string) $object->element);
	if ($element === 'lmdbvehiclequartix') return dol_buildpath('/lmdbvehiclemanagement/vehicle_quartix.php', 1).'?id='.(int) $object->fk_vehicle.'&quartix_id='.(int) $object->id;
	$cards = array('lmdbvehicle' => 'vehicle_card.php', 'lmdbinsurancecontract' => 'insurancecontract_card.php', 'lmdbvehicleregulatorycontrol' => 'regulatorycontrol_card.php', 'lmdbvehicleconsumption' => 'consumption_card.php', 'lmdbvehicleevent' => 'vehicleevent_card.php');
	if (isset($cards[$element])) return dol_buildpath('/lmdbvehiclemanagement/'.$cards[$element], 1).'?id='.(int) $object->id;
	if ($element === 'lmdbinsurancecertificate') return dol_buildpath('/lmdbvehiclemanagement/insurancecontract_certificate.php', 1).'?id='.(int) $object->fk_contract.'&certificate_id='.(int) $object->id;
	if ($element === 'lmdbvehicleassignment') return dol_buildpath('/lmdbvehiclemanagement/vehicle_assignment.php', 1).'?id='.(int) $object->fk_vehicle.'&assignment_id='.(int) $object->id;
	return dol_buildpath('/lmdbvehiclemanagement/vehicle_odometer.php', 1).'?id='.(int) $object->fk_vehicle.'&reading_id='.(int) $object->id;
}

/**
 * Render the native selector in a guarded POST form or inside the caller's form.
 * @param LmdbVehicleManagementObject $object Existing object or a new form object
 * @param bool $embedded Omit the form wrapper and its Save button
 * @return void
 */
function lmdbSharingRender($object, $embedded = false)
{
	global $db, $user, $conf, $langs;
	if ((!$embedded && empty($object->id)) || !LmdbVehicleSharing::available() || !$user->hasRight('lmdbvehiclemanagement', 'read')) return;
	if ($embedded) {
		$object = clone $object;
		if (empty($object->id)) $object->entity = (int) $conf->entity;
		if ((int) $object->entity !== (int) $conf->entity || !LmdbVehicleSharing::isAdmin($user)) return;
	}
	if ($object->element === 'lmdbvehicleodometerreading' && !empty($object->fk_quartix)) { print '<p>'.$langs->trans('QxSharingOnDataset').'</p>'; return; }
	$element = LmdbVehicleSharing::element((string) $object->element);
	if (!LmdbVehicleSharing::individual($element)) return;
	$langs->loadLangs(array('multicompany@multicompany', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	print '<div class="fichecenter"><div class="titre">'.$langs->trans('MulticompanySharedWithThisEntities').'</div>';
	if ((int) $object->entity === (int) $conf->entity && LmdbVehicleSharing::isAdmin($user)) {
		try { $destinations = LmdbVehicleSharing::destinations($db, $user, $object); }
		catch (Exception $e) { print '<div class="error">'.$langs->trans('LmdbSharingDatabaseError').'</div></div>'; return; }
		if (!$destinations && $element !== 'lmdbvehiclequartix') { print '<span class="opacitymedium">'.$langs->trans('LmdbSharingNoDestinations').'</span></div>'; return; }
		require_once __DIR__.'/../class/lmdbvehiclesharingform.class.php';
		$native = new LmdbVehicleSharingForm($db);
		$native->sharingObjectId = (int) $object->id;
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && GETPOST('lmdb_sharing_present', 'aZ09', 2) === $element) {
			$submitted = GETPOST($element.'_to', 'array', 2);
			$native->sharingSelection = is_array($submitted) ? array_values(array_filter($submitted, 'is_scalar')) : array();
			$native->sharingSelection = array_map('intval', $native->sharingSelection);
		}
		$selectorObject = clone $object;
		$selectorObject->id = 0;
		if (!$embedded) {
			print '<form method="POST" action="'.dol_escape_htmltag(lmdbSharingObjectUrl($object)).'">';
			print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="lmdb_save_sharing">';
		}
		print '<input type="hidden" name="lmdb_sharing_present" value="'.dol_escape_htmltag($element).'"'.($embedded ? ' disabled' : '').'>';

		$html = $native->formSelectSharingByElement($selectorObject, $element);
		$html = preg_replace_callback('~<option\b[^>]*value=["\']([0-9]+)["\'][^>]*>.*?</option>~s', static function ($match) use ($destinations) {
			return isset($destinations[(int) $match[1]]) ? $match[0] : '';
		}, $html);
		print $html;
		if ($embedded) {
			// Without the native widget's submit handler, unselected <option>s are
			// omitted by the browser. Never interpret a failed JS load as revocation.
			print '<script nonce="'.getNonce().'">if (window.jQuery) jQuery(function ($) {
				var selector = $("#multiselect_shared_'.$element.'");
				if (selector.data("crlcu.multiselect")) {
					var form = selector.closest("form");
					form.find("input[name=lmdb_sharing_present], [data-lmdb-sharing-save]").prop("disabled", false);
				}
			});</script>';
		}
		if (!$embedded) print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div></form>';
		if ($element !== 'lmdbvehicle') print '<span class="opacitymedium">'.$langs->trans($element === 'lmdbvehiclequartix' ? 'QxSharingHelp' : 'LmdbSharingParentHelp').'</span>';
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
	if ($object->element === 'lmdbvehicleodometerreading' && !empty($object->fk_quartix)) return '<a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_quartix.php', 1).'?id='.(int) $object->fk_vehicle.'&amp;quartix_id='.(int) $object->fk_quartix.'">'.$langs->trans('QxSource').'</a> ';
	if (!LmdbVehicleSharing::available() || !LmdbVehicleSharing::individual($object->element)) return '';
	return '<a class="marginrightonly" href="'.dol_escape_htmltag(lmdbSharingObjectUrl($object)).'">'.img_picto($langs->trans('LmdbSharedWith'), 'globe').'</a> ';
}

/** Inform without querying or disclosing the number of hidden source records. @return void */
function lmdbSharingPartialNotice()
{
	global $langs;
	if (LmdbVehicleSharing::partialView()) print '<div class="info">'.$langs->trans('LmdbSharingPartialView').'</div>';
}
