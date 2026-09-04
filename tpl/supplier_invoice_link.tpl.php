<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/**
 * @var LmdbVehicleEvent|LmdbVehicleRegulatoryControl $object
 * @var Form $form
 * @var User $user
 * @var Conf $conf
 * @var Translate $langs
 */
if (!defined('DOL_DOCUMENT_ROOT')) exit;
$invoiceLinkRight = $object instanceof LmdbVehicleEvent ? 'event' : 'regulatorycontrol';
if (LmdbVehicleManagementCompatibility::isFeatureAvailable('supplier_invoice_links') && (int) $object->entity === (int) $conf->entity
	&& $user->hasRight('lmdbvehiclemanagement', 'read') && $user->hasRight('lmdbvehiclemanagement', $invoiceLinkRight, 'write')
	&& $user->hasRight('fournisseur', 'facture', 'lire') && $user->hasRight('fournisseur', 'facture', 'creer') && empty($user->socid)) {
	$langs->loadLangs(array('bills', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="lmdb_link_invoice"><input type="hidden" name="id" value="'.((int) $object->id).'">';
	print '<label for="lmdb_invoice_id">'.$langs->trans('LmdbLinkSupplierInvoice').'</label> ';
	print $form->selectForForms('FactureFournisseur:fourn/class/fournisseur.facture.class.php:1:(t.entity:=:'.((int) $conf->entity).')', 'lmdb_invoice_id', 0, 1, '', '', 'maxwidth300');
	print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('ToLink').'"></form>';
}
