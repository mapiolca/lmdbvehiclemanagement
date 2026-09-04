<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/**
 * @var array<int,LmdbVehicleEvent|LmdbVehicleRegulatoryControl> $linkedObjectBlock
 * @var CommonObject $object
 * @var User $user
 * @var Conf $conf
 * @var Translate $langs
 */
if (!defined('DOL_DOCUMENT_ROOT')) exit;
$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
foreach ($linkedObjectBlock as $linkId => $source) {
	if (!$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) continue;
	$isEvent = $source instanceof LmdbVehicleEvent;
	$scope = $isEvent ? 'lmdbvehicle' : 'lmdbvehicleregulatorycontrol';
	if (!in_array((int) $source->entity, array_map('intval', explode(',', getEntity($scope))), true)) continue;
	print '<tr class="oddeven"><td>'.$langs->trans($isEvent ? 'VehicleEvent' : 'RegulatoryControl').'</td><td>'.$source->getNomUrl(1).'</td>';
	print '<td>'.dol_escape_htmltag($isEvent ? (string) $source->label : (string) $source->document_ref).'</td><td>'.dol_print_date($isEvent ? $source->event_date : $source->control_date, 'day').'</td><td></td><td class="right">'.$source->getLibStatut(5).'</td><td class="right">';
	if ((int) $source->entity === (int) $conf->entity && (int) $object->entity === (int) $conf->entity && $user->hasRight('lmdbvehiclemanagement', $isEvent ? 'event' : 'regulatorycontrol', 'write') && $user->hasRight('fournisseur', 'facture', 'lire') && $user->hasRight('fournisseur', 'facture', 'creer')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=dellink&dellinkid='.((int) $linkId).'&token='.newToken().'">'.img_picto($langs->trans('RemoveLink'), 'unlink').'</a>';
	}
	print '</td></tr>';
}
