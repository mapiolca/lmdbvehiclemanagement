<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** @var LmdbVehicleQuartix $dataset */
// The selected source owns this form, including when only its historical vehicle is available.
if ($user->hasRight('lmdbvehiclemanagement', 'read') && LmdbVehicleSharing::isAdmin($user)
	&& (int) $dataset->entity === (int) $conf->entity && LmdbVehicleSharing::available()
	&& LmdbVehicleSharing::individual('lmdbvehiclequartix')) {
	$sharingUrl = lmdbSharingObjectUrl($dataset);
	$sharingOpen = in_array(GETPOST('action', 'aZ09'), array('qx_sharing', 'lmdb_save_sharing'), true);
	print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('QxManageSharing'), 'default', $sharingUrl.'&action=qx_sharing&token='.newToken(), 'qx-sharing-open').'</div>';
	// The direct link remains usable without jQuery UI; editing needs the native MC widget.
	print '<div id="qx-sharing-dialog"'.($sharingOpen ? '' : ' hidden').' title="'.dol_escape_htmltag($langs->trans('QxManageSharing')).'" data-auto-open="'.($sharingOpen ? '1' : '0').'">';
	print '<form method="POST" action="'.dol_escape_htmltag($sharingUrl).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="lmdb_save_sharing">';
	lmdbSharingRender($dataset, true);
	print '<div class="center"><input type="submit" class="button button-save" data-lmdb-sharing-save disabled value="'.$langs->trans('Save').'"> ';
	print '<a class="button button-cancel" id="qx-sharing-cancel" href="'.dol_escape_htmltag($sharingUrl).'">'.$langs->trans('Cancel').'</a></div>';
	print '</form></div>';
	print '<script src="'.dol_buildpath('/lmdbvehiclemanagement/js/quartix_sharing.js', 1).'"></script>';
}
