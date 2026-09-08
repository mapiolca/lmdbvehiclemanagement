<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// CLI-only browser fixture renderer. Actual template and translator, synthetic settings/token.
if (PHP_SAPI !== 'cli') exit;
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$coreRoot || !is_file($coreRoot.'/core/lib/functions.lib.php')) exit(2);
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('DOL_URL_ROOT', '/erp');
$conf = (object) array('global' => (object) array(), 'entity' => 1, 'currency' => 'EUR',
	'file' => (object) array('dol_document_root' => array('main' => $coreRoot, 'alt0' => dirname(__DIR__, 2)),
		'dol_url_root' => array('main' => '', 'alt0' => '/modules')));
require_once $coreRoot.'/core/lib/functions.lib.php';
if (is_file($coreRoot.'/core/lib/html.lib.php')) require_once $coreRoot.'/core/lib/html.lib.php';
require_once $coreRoot.'/core/class/translate.class.php';
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$_SESSION['newtoken'] = 'fixture-only';
$routeInDialog = ($argv[2] ?? '') === 'dialog';
$dayId = (int) ($argv[3] ?? 1); $key = 'public-fixture';
$quartixId = 35;
$routeTitle = $langs->transnoentities('QxRouteView').' — Véhicule fictif';
$cfg = array('TILE_URL' => $argv[4] ?? '', 'TILE_ATTRIBUTION' => 'Local fixture');
include dirname(__DIR__).'/tpl/quartix_route.tpl.php';
