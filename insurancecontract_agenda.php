<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('agenda', 'users', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) $page = 0;
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'a.datep';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'ASC' ? 'ASC' : 'DESC';
$allowedSorts = array('a.ref', 'a.datep', 'a.label', 'a.code', 'a.percent', 'u.lastname');
if (!in_array($sortfield, $allowedSorts, true)) $sortfield = 'a.datep';
$searchLabel = GETPOST('search_label', 'alphanohtml');
if (GETPOST('button_removefilter', 'alpha')) $searchLabel = '';

$object = new LmdbVehicleInsuranceContract($db);
if (!isModEnabled('lmdbvehiclemanagement') || !isModEnabled('agenda') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$canReadAllAgenda = $user->hasRight('agenda', 'allactions', 'read');
if (!$user->hasRight('agenda', 'myactions', 'read') && !$canReadAllAgenda) accessforbidden();

$where = lmdbInsuranceContractAgendaWhere($object, 'a');
if ($searchLabel !== '') $where .= natural_search('a.label', $searchLabel);
$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'actioncomm AS a'.$where;
$resCount = $db->query($sqlCount);
$total = 0;
if (!$resCount) {
	dol_print_error($db);
	exit;
}
if (is_object($countRow = $db->fetch_object($resCount))) $total = (int) $countRow->total;
$db->free($resCount);
if ($offset > $total) {
	$page = 0;
	$offset = 0;
}
$sql = 'SELECT a.id, a.ref, a.datep, a.datep2, a.code, a.label, a.percent, a.fk_user_author, a.fk_user_action, u.login, u.firstname, u.lastname';
$sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm AS a LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = a.fk_user_action';
$sql .= $where.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

$form = new Form($db);
llxHeader('', $object->ref.' - '.$langs->trans('EventsAgenda'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card_agenda');
$head = lmdbInsuranceContractPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('InsuranceContract'), -1, $object->picto);
lmdbInsuranceContractPrintBanner($object);

$param = '&id='.$id.'&search_label='.urlencode($searchLabel);
$origin = urlencode($object->element.'@'.$object->module);
$backtopage = urlencode($_SERVER['PHP_SELF'].'?id='.$id);
$newButton = dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create&token='.newToken().'&origin='.$origin.'&originid='.$id.'&backtopage='.$backtopage, '', $user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create'));
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print_barre_liste($langs->trans('EventsAgenda'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $total, 'calendar', 0, $newButton, '', $limit, 0, 0, 1);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent liste">';
print '<tr class="liste_titre_filter"><td></td><td></td><td></td><td><input class="flat maxwidth300" name="search_label" value="'.dol_escape_htmltag($searchLabel).'"></td><td></td><td>'.$form->showFilterButtons().'</td></tr>';
print '<tr class="liste_titre">';
print getTitleFieldOfList('Ref', 0, $_SERVER['PHP_SELF'], 'a.ref', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('Date', 0, $_SERVER['PHP_SELF'], 'a.datep', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('Owner', 0, $_SERVER['PHP_SELF'], 'u.lastname', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('Type', 0, $_SERVER['PHP_SELF'], 'a.code', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('Title', 0, $_SERVER['PHP_SELF'], 'a.label', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 'a.percent', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
print '</tr>';
$rendered = 0;
while ($rendered < min($num, $limit) && is_object($row = $db->fetch_object($resql))) {
	$agendaObject = new ActionComm($db);
	$agendaObject->id = (int) $row->id;
	$agendaObject->ref = (string) $row->ref;
	$agendaObject->label = (string) $row->label;
	$agendaObject->datep = $db->jdate($row->datep);
	$agendaObject->percentage = (int) $row->percent;
	$agendaObject->authorid = (int) $row->fk_user_author;
	$agendaObject->userownerid = (int) $row->fk_user_action;
	$agendaObject->type_code = (string) $row->code;
	if (!$canReadAllAgenda) $agendaObject->userassigned[(int) $user->id] = array('id' => (int) $user->id, 'transparency' => 0);
	$owner = new User($db);
	$owner->id = (int) $row->fk_user_action;
	$owner->login = (string) $row->login;
	$owner->firstname = (string) $row->firstname;
	$owner->lastname = (string) $row->lastname;
	$owner->statut = 1;
	$ownerLink = $owner->id > 0 ? $owner->getNomUrl(1) : '';
	print '<tr class="oddeven"><td>'.$agendaObject->getNomUrl(1).'</td><td>'.dol_print_date($agendaObject->datep, 'dayhour').'</td><td>'.$ownerLink.'</td>';
	print '<td>'.dol_escape_htmltag((string) $row->code).'</td><td>'.dol_escape_htmltag((string) $row->label).'</td><td class="center">'.$agendaObject->LibStatut((int) $row->percent, 5).'</td></tr>';
	$rendered++;
}
if ($rendered === 0) print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>';
$db->free($resql);
print dol_get_fiche_end();
llxFooter();
$db->close();
