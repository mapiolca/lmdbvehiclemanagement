<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once __DIR__.'/lmdbvehiclemanagementobject.class.php';

/** Persistent ownership of QUARTIX observations, independent of the current device link. */
class LmdbVehicleQuartix extends LmdbVehicleManagementObject
{
	public $element = 'lmdbvehiclequartix';
	public $table_element = 'lmdbvehiclemanagement_qx_dataset';
	public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_QUARTIX';
	/** @var int */ public $fk_vehicle;
	/** @var string Historical identification only; never refreshed after access is lost. */
	public $snapshot_vehicle_label = '';
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'ID', 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'notnull' => 1, 'visible' => 0),
		'fk_vehicle' => array('type' => 'integer', 'label' => 'Vehicle', 'notnull' => 1, 'visible' => 0),
		'snapshot_vehicle_label' => array('type' => 'varchar(255)', 'label' => 'Vehicle', 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'visible' => 0),
		'fk_user_creat' => array('type' => 'integer', 'label' => 'UserAuthor', 'visible' => 0),
	);

	/** A collector needs an active local link, its persistent dataset and current vehicle access.
	 * Permissions are checked separately by the caller. @param DoliDB $db @param string $alias Link alias @return string
	 */
	public static function collectionSql($db, $alias = 'l')
	{
		global $conf;
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) throw new InvalidArgumentException('Invalid alias');
		return '('.$alias.'.entity='.(int) $conf->entity.' AND '.$alias.'.active=1 AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset cq WHERE cq.rowid='.$alias.'.fk_quartix AND cq.entity='.$alias.'.entity AND cq.fk_vehicle='.$alias.'.fk_vehicle) AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle cv WHERE cv.rowid='.$alias.'.fk_vehicle AND '.LmdbVehicleSharing::sql($db, 'lmdbvehicle', 'cv').'))';
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		global $conf, $user;
		if (!isModEnabled($this->module) || !empty($user->socid) || !$user->hasRight($this->module, 'read')
			|| empty($user->admin) || (int) $this->entity !== (int) $conf->entity
			|| !LmdbVehicleSharing::visible($this->db, 'lmdbvehicle', (int) $this->fk_vehicle)) {
			$this->error = 'QxAccessDenied'; return -1;
		}
		return 1;
	}

	/** @inheritdoc */
	public function fetch($id, $ref = null)
	{
		global $user;
		if (!isModEnabled($this->module) || !empty($user->socid) || !$user->hasRight($this->module, 'read')) return -1;
		return parent::fetch($id, $ref);
	}

	/** Persistent source identity is never removed with its association or observations. @inheritdoc */
	public function delete(User $user, $notrigger = 0)
	{
		$this->error = 'QxPersistentSource';
		return -1;
	}

	/** Dataset changes never dispatch a trigger on the vehicle owned by another entity.
	 * @param User $user Actor @param string $reason Internal mutation reason @return void
	 */
	public function changed($user, $reason)
	{
		global $conf;
		if ((int) $this->entity !== (int) $conf->entity || !$user->hasRight($this->module, 'read') || !empty($user->socid)) throw new RuntimeException('QxAccessDenied');
		$this->context = array('trigger_reason' => $reason, 'changed_fields' => array($reason));
		if ($this->call_trigger($this->TRIGGER_PREFIX.'_UPDATE', $user) < 0) throw new RuntimeException('QxDatabaseError');
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0) { return ''; }
	/** @inheritdoc */
	protected function getCardPage() { return '/lmdbvehiclemanagement/vehicle_quartix.php'; }
	/** @inheritdoc */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $langs;
		return '<a href="'.dol_buildpath($this->getCardPage(), 1).'?id='.(int) $this->fk_vehicle.'&amp;quartix_id='.(int) $this->id.'">'.dol_escape_htmltag($this->snapshot_vehicle_label ?: $langs->trans('QxHistoricalVehicle')).'</a>';
	}
}
