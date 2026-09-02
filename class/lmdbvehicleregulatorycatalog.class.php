<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Versioned French regulatory reference data shipped with the module.
 *
 * Native rows are copied into each entity so that Multicompany sharing remains
 * explicit. They are never overwritten: a functional adaptation must be stored
 * as a separate entity override linked through fk_parent_rule.
 */
class LmdbVehicleRegulatoryCatalog
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @return array<string,string> */
	public static function getAssetTypes()
	{
		return array(
			'road_vehicle_unqualified' => 'AssetTypeRoadVehicleToQualify',
			'passenger_vehicle' => 'AssetTypePassengerVehicle',
			'light_commercial' => 'AssetTypeLightCommercial',
			'heavy_goods' => 'AssetTypeHeavyGoods',
			'bus_coach' => 'AssetTypeBusCoach',
			'category_l' => 'AssetTypeCategoryL',
			'trailer' => 'AssetTypeTrailer',
			'construction_machine' => 'AssetTypeConstructionMachine',
			'lifting_equipment' => 'AssetTypeLiftingEquipment',
			'other' => 'AssetTypeOther',
		);
	}

	/** @return array<string,string> */
	public static function getProfiles()
	{
		return array(
			'ROAD_M1' => 'RegulatoryProfileRoadM1',
			'ROAD_N1' => 'RegulatoryProfileRoadN1',
			'ROAD_HEAVY' => 'RegulatoryProfileRoadHeavy',
			'PUBLIC_TRANSPORT' => 'RegulatoryProfilePublicTransport',
			'CATEGORY_L' => 'RegulatoryProfileCategoryL',
			'CONSTRUCTION' => 'RegulatoryProfileConstruction',
			'LIFTING' => 'RegulatoryProfileLifting',
			'VGP_3M' => 'RegulatoryProfileVgp3Months',
			'VGP_6M' => 'RegulatoryProfileVgp6Months',
			'VGP_12M' => 'RegulatoryProfileVgp12Months',
			'TACHOGRAPH' => 'RegulatoryProfileTachograph',
			'ADR' => 'RegulatoryProfileAdr',
			'ATP' => 'RegulatoryProfileAtp',
			'SPECIAL_PUBLIC' => 'RegulatoryProfileSpecialPublic',
		);
	}

	/** @return array<string,string> */
	public static function getControlTypes()
	{
		return array(
			'ROADWORTHINESS' => 'ControlTypeRoadworthiness',
			'POLLUTION' => 'ControlTypePollution',
			'VGP' => 'ControlTypeVgp',
			'COMMISSIONING' => 'ControlTypeCommissioning',
			'TACHOGRAPH' => 'ControlTypeTachograph',
			'ADR' => 'ControlTypeAdr',
			'ATP' => 'ControlTypeAtp',
			'CUSTOM' => 'ControlTypeCustom',
		);
	}

	/** @return array<string,array{label:string,severity:int,recheck:int,blocking:int}> */
	public static function getResults()
	{
		return array(
			'compliant' => array('label' => 'ControlResultCompliant', 'severity' => 0, 'recheck' => 0, 'blocking' => 0),
			'compliant_observations' => array('label' => 'ControlResultCompliantWithObservations', 'severity' => 1, 'recheck' => 0, 'blocking' => 0),
			'recheck_required' => array('label' => 'ControlResultRecheckRequired', 'severity' => 2, 'recheck' => 1, 'blocking' => 0),
			'non_compliant' => array('label' => 'ControlResultNonCompliant', 'severity' => 3, 'recheck' => 1, 'blocking' => 1),
			'critical' => array('label' => 'ControlResultCriticalFailure', 'severity' => 4, 'recheck' => 1, 'blocking' => 1),
			'not_performed' => array('label' => 'ControlResultNotPerformed', 'severity' => 5, 'recheck' => 0, 'blocking' => 1),
		);
	}

	/**
	 * @return array<string,array{label:string,type:string,calculator:string,initial:?int,recurrence:?int,days:?int,recheck:?int,blocking:string,profiles:list<string>,source:string,source_title:string}>
	 */
	public static function getNativeRules()
	{
		$roadSource = 'https://www.ecologie.gouv.fr/politiques-publiques/controle-technique-vehicules';
		$constructionSource = 'https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000874070/2026-02-02';
		$liftingSource = 'https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000439029/2024-07-08/';
		return array(
			'FR_ROAD_LIGHT' => array('label' => 'RuleRoadLight', 'type' => 'ROADWORTHINESS', 'calculator' => 'road_light', 'initial' => 48, 'recurrence' => 24, 'days' => null, 'recheck' => 60, 'blocking' => 'both', 'profiles' => array('ROAD_M1', 'ROAD_N1'), 'source' => $roadSource, 'source_title' => 'OfficialSourceRoadworthiness'),
			'FR_N1_POLLUTION' => array('label' => 'RuleN1Pollution', 'type' => 'POLLUTION', 'calculator' => 'n1_pollution', 'initial' => 60, 'recurrence' => 12, 'days' => null, 'recheck' => null, 'blocking' => 'both', 'profiles' => array('ROAD_N1'), 'source' => $roadSource, 'source_title' => 'OfficialSourceRoadworthiness'),
			'FR_ROAD_HEAVY' => array('label' => 'RuleRoadHeavy', 'type' => 'ROADWORTHINESS', 'calculator' => 'periodic_months', 'initial' => 12, 'recurrence' => 12, 'days' => null, 'recheck' => 30, 'blocking' => 'both', 'profiles' => array('ROAD_HEAVY'), 'source' => $roadSource, 'source_title' => 'OfficialSourceRoadworthiness'),
			'FR_PUBLIC_TRANSPORT' => array('label' => 'RulePublicTransport', 'type' => 'ROADWORTHINESS', 'calculator' => 'periodic_months', 'initial' => 6, 'recurrence' => 6, 'days' => null, 'recheck' => 15, 'blocking' => 'both', 'profiles' => array('PUBLIC_TRANSPORT'), 'source' => $roadSource, 'source_title' => 'OfficialSourceRoadworthiness'),
			'FR_SPECIAL_PUBLIC' => array('label' => 'RuleSpecialPublicTransport', 'type' => 'ROADWORTHINESS', 'calculator' => 'periodic_months', 'initial' => 12, 'recurrence' => 12, 'days' => null, 'recheck' => 30, 'blocking' => 'both', 'profiles' => array('SPECIAL_PUBLIC'), 'source' => $roadSource, 'source_title' => 'OfficialSourceRoadworthiness'),
			'FR_CATEGORY_L' => array('label' => 'RuleCategoryL', 'type' => 'ROADWORTHINESS', 'calculator' => 'category_l', 'initial' => 60, 'recurrence' => 36, 'days' => null, 'recheck' => 90, 'blocking' => 'both', 'profiles' => array('CATEGORY_L'), 'source' => $roadSource, 'source_title' => 'OfficialSourceRoadworthiness'),
			'FR_VGP_3M' => array('label' => 'RuleVgp3Months', 'type' => 'VGP', 'calculator' => 'periodic_months', 'initial' => 3, 'recurrence' => 3, 'days' => null, 'recheck' => null, 'blocking' => 'both', 'profiles' => array('VGP_3M'), 'source' => $liftingSource, 'source_title' => 'OfficialSourceLifting'),
			'FR_VGP_6M' => array('label' => 'RuleVgp6Months', 'type' => 'VGP', 'calculator' => 'periodic_months', 'initial' => 6, 'recurrence' => 6, 'days' => null, 'recheck' => null, 'blocking' => 'both', 'profiles' => array('VGP_6M'), 'source' => $constructionSource, 'source_title' => 'OfficialSourceConstructionEquipment'),
			'FR_VGP_12M' => array('label' => 'RuleVgp12Months', 'type' => 'VGP', 'calculator' => 'periodic_months', 'initial' => 12, 'recurrence' => 12, 'days' => null, 'recheck' => null, 'blocking' => 'both', 'profiles' => array('VGP_12M'), 'source' => $constructionSource, 'source_title' => 'OfficialSourceConstructionEquipment'),
			'FR_COMMISSIONING' => array('label' => 'RuleCommissioningVerification', 'type' => 'COMMISSIONING', 'calculator' => 'commissioning_once', 'initial' => 0, 'recurrence' => null, 'days' => null, 'recheck' => null, 'blocking' => 'service', 'profiles' => array('CONSTRUCTION', 'LIFTING'), 'source' => $liftingSource, 'source_title' => 'OfficialSourceLifting'),
			'FR_RECOMMISSIONING' => array('label' => 'RuleRecommissioningVerification', 'type' => 'COMMISSIONING', 'calculator' => 'event_based', 'initial' => null, 'recurrence' => null, 'days' => null, 'recheck' => null, 'blocking' => 'service', 'profiles' => array('CONSTRUCTION', 'LIFTING'), 'source' => $liftingSource, 'source_title' => 'OfficialSourceLifting'),
			'FR_TACHOGRAPH_24M' => array('label' => 'RuleTachograph24Months', 'type' => 'TACHOGRAPH', 'calculator' => 'periodic_months', 'initial' => 24, 'recurrence' => 24, 'days' => null, 'recheck' => null, 'blocking' => 'both', 'profiles' => array('TACHOGRAPH'), 'source' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000029220521/', 'source_title' => 'OfficialSourceTachograph'),
			'FR_ADR' => array('label' => 'RuleAdrApproval', 'type' => 'ADR', 'calculator' => 'document_expiry', 'initial' => null, 'recurrence' => null, 'days' => null, 'recheck' => null, 'blocking' => 'both', 'profiles' => array('ADR'), 'source' => 'https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000051387173', 'source_title' => 'OfficialSourceAdr'),
			'FR_ATP' => array('label' => 'RuleAtpCertificate', 'type' => 'ATP', 'calculator' => 'atp', 'initial' => 72, 'recurrence' => 36, 'days' => null, 'recheck' => null, 'blocking' => 'both', 'profiles' => array('ATP'), 'source' => 'https://info.agriculture.gouv.fr/gedei/site/bo-agri/document_administratif-391791b6-f266-4e3a-9744-d4ab1841dea0/telechargement', 'source_title' => 'OfficialSourceAtp'),
		);
	}

	/** Seed dictionaries and immutable native rules without replacing existing rows. @param int $entity Entity @return int<-1,1> */
	public function seedDefaults($entity)
	{
		global $langs;
		$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
		$position = 10;
		foreach (self::getAssetTypes() as $code => $label) {
			if ($this->insertDictionary('c_lmdbvehiclemanagement_asset_type', $entity, $code, $label, $position) < 0) return -1;
			$position += 10;
		}
		$profileIds = array();
		$position = 10;
		foreach (self::getProfiles() as $code => $label) {
			if ($this->insertDictionary('c_lmdbvehiclemanagement_regulatory_profile', $entity, $code, $label, $position, true) < 0) return -1;
			$profileIds[$code] = $this->findDictionaryId('c_lmdbvehiclemanagement_regulatory_profile', $entity, $code);
			$position += 10;
		}
		$typeIds = array();
		$position = 10;
		foreach (self::getControlTypes() as $code => $label) {
			if ($this->insertDictionary('c_lmdbvehiclemanagement_control_type', $entity, $code, $label, $position, true) < 0) return -1;
			$typeIds[$code] = $this->findDictionaryId('c_lmdbvehiclemanagement_control_type', $entity, $code);
			$position += 10;
		}
		$position = 10;
		foreach (self::getResults() as $code => $definition) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result (entity, code, label, severity, requires_recheck, is_blocking, position, active)';
			$sql .= ' SELECT '.((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($definition['label'])."', ".((int) $definition['severity']).', '.((int) $definition['recheck']).', '.((int) $definition['blocking']).', '.$position.', 1';
			$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result WHERE entity = '.((int) $entity)." AND code = '".$this->db->escape($code)."')";
			if (!$this->db->query($sql)) return $this->fail();
			$position += 10;
		}
		$position = 10;
		foreach (self::getNativeRules() as $code => $rule) {
			$typeId = isset($typeIds[$rule['type']]) ? (int) $typeIds[$rule['type']] : 0;
			if ($typeId <= 0) continue;
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule (entity, code, label, fk_control_type, jurisdiction, territory, calculator_code, initial_delay_months, recurrence_months, recurrence_days, recheck_days, default_blocking_mode, source_title, source_url, source_review_date, is_native, active, date_creation)';
			$sql .= ' SELECT '.((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($rule['label'])."', ".$typeId.", 'FR', 'FR_ALL', '".$this->db->escape($rule['calculator'])."', ".$this->sqlInt($rule['initial']).', '.$this->sqlInt($rule['recurrence']).', '.$this->sqlInt($rule['days']).', '.$this->sqlInt($rule['recheck']).", '".$this->db->escape($rule['blocking'])."', '".$this->db->escape($rule['source_title'])."', '".$this->db->escape($rule['source'])."', '2026-09-02', 1, 1, '".$this->db->idate(dol_now())."'";
			$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule WHERE entity = '.((int) $entity)." AND code = '".$this->db->escape($code)."')";
			if (!$this->db->query($sql)) return $this->fail();
			$ruleId = $this->findDictionaryId('lmdbvehiclemanagement_regulatory_rule', $entity, $code);
			$allowedProfileIds = array();
			foreach ($rule['profiles'] as $profileCode) {
				if (!empty($profileIds[$profileCode])) $allowedProfileIds[] = (int) $profileIds[$profileCode];
			}
			if ($ruleId > 0) {
				$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile WHERE entity = '.((int) $entity).' AND fk_rule = '.$ruleId;
				if (!empty($allowedProfileIds)) $sql .= ' AND fk_profile NOT IN ('.implode(',', $allowedProfileIds).')';
				if (!$this->db->query($sql)) return $this->fail();
			}
			foreach ($rule['profiles'] as $profileCode) {
				$profileId = isset($profileIds[$profileCode]) ? (int) $profileIds[$profileCode] : 0;
				if ($ruleId <= 0 || $profileId <= 0) continue;
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile (entity, fk_rule, fk_profile) SELECT '.((int) $entity).', '.$ruleId.', '.$profileId;
				$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile WHERE entity = '.((int) $entity).' AND fk_rule = '.$ruleId.' AND fk_profile = '.$profileId.')';
				if (!$this->db->query($sql)) return $this->fail();
			}
			$position += 10;
		}
		return 1;
	}

	/**
	 * Create a versioned entity override without altering the native rule.
	 * Existing overrides are retained for audit but deactivated. Requirements
	 * already backed by a control are retained as inactive historical records.
	 *
	 * @param int $parentRuleId Native rule identifier
	 * @param array{label?:string,override_reason:string,initial_delay_months?:?int,recurrence_months?:?int,recurrence_days?:?int,recheck_days?:?int,default_blocking_mode?:string,effective_from?:?int,effective_to?:?int} $values Override values
	 * @param User $user Author
	 * @param int $entity Entity
	 * @return int New rule identifier, -1 on error
	 */
	public function createEntityOverride($parentRuleId, array $values, User $user, $entity)
	{
		$reason = trim((string) $values['override_reason']);
		if ($reason === '') {
			$this->error = 'RegulatoryRuleOverrideReasonRequired';
			return -1;
		}

		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule';
		$sql .= ' WHERE rowid = '.((int) $parentRuleId).' AND entity = '.((int) $entity).' AND is_native = 1 AND active = 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->fail();
		}
		$parent = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($parent)) {
			$this->error = 'InvalidParentRegulatoryRule';
			return -1;
		}

		$blockingMode = isset($values['default_blocking_mode']) ? (string) $values['default_blocking_mode'] : (string) $parent->default_blocking_mode;
		if (!in_array($blockingMode, array('none', 'assignment', 'service', 'both'), true)) {
			$blockingMode = (string) $parent->default_blocking_mode;
		}
		$label = trim(isset($values['label']) ? (string) $values['label'] : '');
		if ($label === '') {
			$label = (string) $parent->label;
		}
		$initialDelay = isset($values['initial_delay_months']) ? $values['initial_delay_months'] : ($parent->initial_delay_months === null ? null : (int) $parent->initial_delay_months);
		$recurrenceMonths = isset($values['recurrence_months']) ? $values['recurrence_months'] : ($parent->recurrence_months === null ? null : (int) $parent->recurrence_months);
		$recurrenceDays = isset($values['recurrence_days']) ? $values['recurrence_days'] : ($parent->recurrence_days === null ? null : (int) $parent->recurrence_days);
		$recheckDays = isset($values['recheck_days']) ? $values['recheck_days'] : ($parent->recheck_days === null ? null : (int) $parent->recheck_days);
		$effectiveFrom = isset($values['effective_from']) ? $values['effective_from'] : (!empty($parent->effective_from) ? $this->db->jdate($parent->effective_from) : null);
		$effectiveTo = isset($values['effective_to']) ? $values['effective_to'] : (!empty($parent->effective_to) ? $this->db->jdate($parent->effective_to) : null);
		if (!empty($effectiveFrom) && !empty($effectiveTo) && (int) $effectiveFrom > (int) $effectiveTo) {
			$this->error = 'RegulatoryRuleEffectivePeriodInvalid';
			return -1;
		}
		$code = 'OVR_'.substr((string) $parent->code, 0, 35).'_'.strtoupper(substr(sha1($entity.'|'.$parentRuleId.'|'.dol_now().'|'.microtime(true)), 0, 12));

		$this->db->begin();
		$oldRuleIds = array((int) $parentRuleId);
		$resql = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule WHERE entity = '.((int) $entity).' AND fk_parent_rule = '.((int) $parentRuleId).' AND active = 1');
		if (!$resql) {
			return $this->rollback();
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$oldRuleIds[] = (int) $row->rowid;
		}
		$this->db->free($resql);

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule SET active = 0, fk_user_modif = '.((int) $user->id);
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_parent_rule = '.((int) $parentRuleId).' AND active = 1';
		if (!$this->db->query($sql)) {
			return $this->rollback();
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule (entity, code, label, description, fk_control_type, jurisdiction, territory, calculator_code, initial_delay_months, recurrence_months, recurrence_days, recheck_days, effective_from, effective_to, default_blocking_mode, source_title, source_url, source_review_date, is_native, fk_parent_rule, override_reason, active, date_creation, fk_user_creat) VALUES (';
		$sql .= ((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($label)."', ".$this->sqlStringOrNull($parent->description).', '.((int) $parent->fk_control_type).", '".$this->db->escape((string) $parent->jurisdiction)."', '".$this->db->escape((string) $parent->territory)."', '".$this->db->escape((string) $parent->calculator_code)."', ";
		$sql .= $this->sqlInt($initialDelay).', '.$this->sqlInt($recurrenceMonths).', '.$this->sqlInt($recurrenceDays).', '.$this->sqlInt($recheckDays).', '.$this->sqlDate($effectiveFrom).', '.$this->sqlDate($effectiveTo).", '".$this->db->escape($blockingMode)."', ".$this->sqlStringOrNull($parent->source_title).', '.$this->sqlStringOrNull($parent->source_url).', '.$this->sqlDate(!empty($parent->source_review_date) ? $this->db->jdate($parent->source_review_date) : null).", 0, ".((int) $parentRuleId).", '".$this->db->escape($reason)."', 1, '".$this->db->idate(dol_now())."', ".((int) $user->id).')';
		if (!$this->db->query($sql)) {
			return $this->rollback();
		}
		$newRuleId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule');
		if ($newRuleId <= 0) {
			$this->error = 'FailedToCreateRegulatoryRuleOverride';
			return $this->rollback();
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile (entity, fk_rule, fk_profile)';
		$sql .= ' SELECT entity, '.$newRuleId.', fk_profile FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile WHERE entity = '.((int) $entity).' AND fk_rule = '.((int) $parentRuleId);
		if (!$this->db->query($sql)) {
			return $this->rollback();
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement (entity, fk_vehicle, fk_rule, requirement_kind, fk_source_control, qualification_status, status, blocking_mode, active, date_creation, fk_user_creat)';
		$sql .= ' SELECT DISTINCT vp.entity, vp.fk_vehicle, '.$newRuleId.", 'periodic', 0, 'incomplete', 'incomplete', '".$this->db->escape($blockingMode)."', 1, '".$this->db->idate(dol_now())."', ".((int) $user->id);
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile AS vp INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile AS rp ON rp.entity = vp.entity AND rp.fk_profile = vp.fk_profile AND rp.fk_rule = '.$newRuleId;
		$sql .= ' WHERE vp.entity = '.((int) $entity).' AND vp.confirmed = 1 AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS existing WHERE existing.entity = vp.entity AND existing.fk_vehicle = vp.fk_vehicle AND existing.fk_rule = '.$newRuleId." AND existing.requirement_kind = 'periodic' AND existing.fk_source_control = 0)";
		if (!$this->db->query($sql)) {
			return $this->rollback();
		}

		$oldIdsSql = implode(',', array_values(array_unique($oldRuleIds)));
		$sql = 'DELETE req FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req WHERE req.entity = '.((int) $entity).' AND req.fk_rule IN ('.$oldIdsSql.')';
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control AS ctl WHERE ctl.entity = req.entity AND ctl.fk_requirement = req.rowid)';
		if (!$this->db->query($sql)) {
			return $this->rollback();
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET active = 0 WHERE entity = '.((int) $entity).' AND fk_rule IN ('.$oldIdsSql.')';
		if (!$this->db->query($sql)) {
			return $this->rollback();
		}

		$this->db->commit();
		return $newRuleId;
	}

	/**
	 * Create a custom entity rule linked to one or more confirmed profiles.
	 *
	 * @param array{label:string,description?:string,reason:string,profile_ids:list<int>,calculator_code:string,initial_delay_months?:?int,recurrence_months?:?int,recurrence_days?:?int,recheck_days?:?int,default_blocking_mode?:string,effective_from?:?int,effective_to?:?int} $values Rule values
	 * @param User $user Author
	 * @param int $entity Entity
	 * @return int New rule identifier, -1 on error
	 */
	public function createEntityCustomRule(array $values, User $user, $entity)
	{
		$label = trim((string) $values['label']);
		$reason = trim((string) $values['reason']);
		$profileIds = array_values(array_unique(array_filter(array_map('intval', $values['profile_ids']))));
		$calculator = (string) $values['calculator_code'];
		if ($label === '') {
			$this->error = 'RegulatoryCustomRuleLabelRequired';
			return -1;
		}
		if ($reason === '') {
			$this->error = 'RegulatoryRuleOverrideReasonRequired';
			return -1;
		}
		if (empty($profileIds)) {
			$this->error = 'RegulatoryCustomRuleProfileRequired';
			return -1;
		}
		if (!in_array($calculator, array('periodic_months', 'periodic_days', 'document_expiry'), true)) {
			$this->error = 'RegulatoryCustomRuleCalculatorInvalid';
			return -1;
		}
		$effectiveFrom = isset($values['effective_from']) ? $values['effective_from'] : null;
		$effectiveTo = isset($values['effective_to']) ? $values['effective_to'] : null;
		if (!empty($effectiveFrom) && !empty($effectiveTo) && (int) $effectiveFrom > (int) $effectiveTo) {
			$this->error = 'RegulatoryRuleEffectivePeriodInvalid';
			return -1;
		}
		$blockingMode = isset($values['default_blocking_mode']) ? (string) $values['default_blocking_mode'] : 'none';
		if (!in_array($blockingMode, array('none', 'assignment', 'service', 'both'), true)) {
			$blockingMode = 'none';
		}

		$validProfileIds = array();
		$resql = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile WHERE entity = '.((int) $entity).' AND active = 1 AND rowid IN ('.implode(',', $profileIds).')');
		if (!$resql) {
			return $this->fail();
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$validProfileIds[] = (int) $row->rowid;
		}
		$this->db->free($resql);
		if (empty($validProfileIds)) {
			$this->error = 'RegulatoryCustomRuleProfileRequired';
			return -1;
		}
		$controlTypeId = $this->findDictionaryId('c_lmdbvehiclemanagement_control_type', $entity, 'CUSTOM');
		if ($controlTypeId <= 0) {
			$this->error = 'RegulatoryCustomControlTypeMissing';
			return -1;
		}

		$initialDelay = isset($values['initial_delay_months']) ? max(0, (int) $values['initial_delay_months']) : null;
		$recurrenceMonths = isset($values['recurrence_months']) ? max(0, (int) $values['recurrence_months']) : null;
		$recurrenceDays = isset($values['recurrence_days']) ? max(0, (int) $values['recurrence_days']) : null;
		$recheckDays = isset($values['recheck_days']) ? max(0, (int) $values['recheck_days']) : null;
		if ($calculator === 'periodic_months' && $initialDelay === null && $recurrenceMonths === null) {
			$this->error = 'RegulatoryCustomRulePeriodRequired';
			return -1;
		}
		if ($calculator === 'periodic_days' && $recurrenceDays === null) {
			$this->error = 'RegulatoryCustomRulePeriodRequired';
			return -1;
		}
		$code = 'CUSTOM_'.strtoupper(substr(sha1($entity.'|'.$label.'|'.dol_now().'|'.microtime(true)), 0, 20));

		$this->db->begin();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule (entity, code, label, description, fk_control_type, jurisdiction, territory, calculator_code, initial_delay_months, recurrence_months, recurrence_days, recheck_days, effective_from, effective_to, default_blocking_mode, is_native, override_reason, active, date_creation, fk_user_creat) VALUES (';
		$sql .= ((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($label)."', ".$this->sqlStringOrNull(isset($values['description']) ? $values['description'] : null).', '.$controlTypeId.", 'FR', 'FR_ALL', '".$this->db->escape($calculator)."', ".$this->sqlInt($initialDelay).', '.$this->sqlInt($recurrenceMonths).', '.$this->sqlInt($recurrenceDays).', '.$this->sqlInt($recheckDays).', '.$this->sqlDate($effectiveFrom).', '.$this->sqlDate($effectiveTo).", '".$this->db->escape($blockingMode)."', 0, '".$this->db->escape($reason)."', 1, '".$this->db->idate(dol_now())."', ".((int) $user->id).')';
		if (!$this->db->query($sql)) {
			return $this->rollback();
		}
		$newRuleId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule');
		if ($newRuleId <= 0) {
			$this->error = 'FailedToCreateRegulatoryCustomRule';
			return $this->rollback();
		}
		foreach ($validProfileIds as $profileId) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile (entity, fk_rule, fk_profile) VALUES ('.((int) $entity).', '.$newRuleId.', '.$profileId.')';
			if (!$this->db->query($sql)) {
				return $this->rollback();
			}
		}
		$this->db->commit();
		return $newRuleId;
	}

	/** @param string $table Table @param int $entity Entity @param string $code Code @param string $label Label @param int $position Position @param bool $description Add description @return int<-1,1> */
	private function insertDictionary($table, $entity, $code, $label, $position, $description = false)
	{
		$columns = $description ? '(entity, code, label, description, position, active)' : '(entity, code, label, position, active)';
		$values = $description ? ((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($label)."', NULL, ".$position.', 1' : ((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($label)."', ".$position.', 1';
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$table.' '.$columns.' SELECT '.$values;
		$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.$table.' WHERE entity = '.((int) $entity)." AND code = '".$this->db->escape($code)."')";
		return $this->db->query($sql) ? 1 : $this->fail();
	}

	/** @param string $table Table @param int $entity Entity @param string $code Code @return int */
	private function findDictionaryId($table, $entity, $code)
	{
		$resql = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$table.' WHERE entity = '.((int) $entity)." AND code = '".$this->db->escape($code)."' LIMIT 1");
		if (!$resql) return 0;
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return is_object($row) ? (int) $row->rowid : 0;
	}

	/** @param ?int $value Value @return string */
	private function sqlInt($value)
	{
		return $value === null ? 'NULL' : (string) ((int) $value);
	}

	/** @param mixed $value Value @return string */
	private function sqlStringOrNull($value)
	{
		return $value === null || (string) $value === '' ? 'NULL' : "'".$this->db->escape((string) $value)."'";
	}

	/** @param ?int $value Unix timestamp @return string */
	private function sqlDate($value)
	{
		return empty($value) ? 'NULL' : "'".$this->db->idate((int) $value)."'";
	}

	/** @return int<-1,-1> */
	private function rollback()
	{
		if ($this->error === '') {
			$this->error = $this->db->lasterror();
		}
		$this->db->rollback();
		return -1;
	}

	/** @return int<-1,-1> */
	private function fail()
	{
		$this->error = $this->db->lasterror();
		return -1;
	}
}
