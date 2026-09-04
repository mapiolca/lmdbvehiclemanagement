<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/**
 * Scoped native template override: the core supplier row assumes a commercial
 * source->type and exposes unlink unconditionally. Other blocks use their core tpl.
 * @var CommonObject $object
 * @var array<int,CommonObject> $linkedObjectBlock
 * @var User $user
 * @var Translate $langs
 * @var Conf $conf
 */
if (!defined('DOL_DOCUMENT_ROOT')) exit;
if (!($object instanceof LmdbVehicleEvent) && !($object instanceof LmdbVehicleRegulatoryControl)) return 0;
if (!$linkedObjectBlock || !(reset($linkedObjectBlock) instanceof FactureFournisseur)) return 0;
$langs->load('bills');
$sourceRight = $object instanceof LmdbVehicleEvent ? 'event' : 'regulatorycontrol';
foreach ($linkedObjectBlock as $linkId => $invoice) {
	if (!$user->hasRight('fournisseur', 'facture', 'lire') || !in_array((int) $invoice->entity, array_map('intval', explode(',', getEntity('supplier_invoice'))), true)) continue;
	$supplier = $invoice->fetch_thirdparty() > 0 && is_object($invoice->thirdparty) ? $invoice->thirdparty->getNomUrl(1) : '';
	print '<tr class="oddeven"><td>'.$langs->trans('SupplierInvoice').'</td><td>'.$invoice->getNomUrl(1).'</td><td>'.dol_escape_htmltag((string) $invoice->ref_supplier).'<br>'.$supplier.'</td><td>'.dol_print_date($invoice->date, 'day').'</td>';
	print '<td class="right">'.price($invoice->multicurrency_code ? $invoice->multicurrency_total_ht : $invoice->total_ht).' '.dol_escape_htmltag((string) $invoice->multicurrency_code).'</td><td class="right">'.$invoice->getLibStatut(5).'</td><td class="right">';
	if ((int) $object->entity === (int) $conf->entity && (int) $invoice->entity === (int) $conf->entity && $user->hasRight('lmdbvehiclemanagement', $sourceRight, 'write') && $user->hasRight('fournisseur', 'facture', 'creer')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=dellink&dellinkid='.((int) $linkId).'&token='.newToken().'">'.img_picto($langs->trans('RemoveLink'), 'unlink').'</a>';
	}
	print '</td></tr>';
}
return 1;
