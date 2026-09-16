<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** @var LmdbVehicleQuartix $dataset */
/** @var bool $mileageView */
/** @var string $sourceSelectorHtml Authorized native source selector */
if (!$user->hasRight('lmdbvehiclemanagement', 'read')) return;
$sharingUrl = lmdbSharingObjectUrl($dataset);
print '<div class="tabsAction display-flex lmdb-quartix-actions">';
print '<div class="left">'.$sourceSelectorHtml.'</div>';
print '<div class="display-flex lmdb-quartix-buttons">';
print dolGetButtonAction('', $langs->trans($mileageView ? 'QxUsage' : 'QxOdometerHistory'), 'default', $sharingUrl.($mileageView ? '' : '&view=odometer'), 'qx-view-switch');
print '</div></div>';
