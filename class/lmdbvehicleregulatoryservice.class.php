<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycatalog.class.php');

/** Regulatory qualification, due-date calculation and blocking service. */
class LmdbVehicleRegulatoryService
{
	/** @var DoliDB */ private $db;
	/** @var string */ public $error = '';
	/** @var array<int,string> */ public $errors = array();

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @param object $vehicle Vehicle @return list<string> */
	public function suggestProfileCodes($vehicle)
	{
		$codes = array();
		$type = property_exists($vehicle, 'asset_type') ? (string) $vehicle->asset_type : '';
		if ($type === '' && property_exists($vehicle, 'fk_asset_type') && (int) $vehicle->fk_asset_type > 0) {
			$sql = 'SELECT code FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_asset_type';
			$sql .= ' WHERE rowid = '.((int) $vehicle->fk_asset_type).' AND active = 1';
			$resql = $this->db->query($sql);
			if ($resql && is_object($row = $this->db->fetch_object($resql))) {
				$type = (string) $row->code;
			}
			if ($resql) {
				$this->db->free($resql);
			}
		}
		$eu = strtoupper(property_exists($vehicle, 'eu_category') ? (string) $vehicle->eu_category : '');
		if ($type === 'passenger_vehicle' || strpos($eu, 'M1') === 0) $codes[] = 'ROAD_M1';
		if ($type === 'light_commercial' || strpos($eu, 'N1') === 0) $codes[] = 'ROAD_N1';
		if ($type === 'heavy_goods' || preg_match('/^(N[23]|O[34])/', $eu)) $codes[] = 'ROAD_HEAVY';
		if ($type === 'bus_coach' || preg_match('/^M[23]/', $eu)) $codes[] = 'PUBLIC_TRANSPORT';
		if ($type === 'category_l' || strpos($eu, 'L') === 0) $codes[] = 'CATEGORY_L';
		if ($type === 'construction_machine') $codes[] = 'CONSTRUCTION';
		if ($type === 'lifting_equipment') $codes[] = 'LIFTING';
		return array_values(array_unique($codes));
	}

	/**
	 * Return the active questionnaire and the vehicle's persisted answers.
	 *
	 * @param int $vehicleId Vehicle identifier
	 * @param int $entity Entity identifier
	 * @return array<int,array{id:int,code:string,label:string,description:string,date_label:string,answer_choice_id:int,answer_code:string,applicable_since:int,choices:array<int,array{id:int,code:string,label:string,requires_date:int}>}>
	 */
	public function getQualificationQuestionnaire($vehicleId, $entity)
	{
		$questions = array();
		$sql = 'SELECT q.rowid, q.code, q.label, q.description, q.date_label, a.fk_choice, a.applicable_since, selected.code AS answer_code';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_question AS q';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_answer AS a ON a.entity = q.entity AND a.fk_question = q.rowid AND a.fk_vehicle = '.((int) $vehicleId);
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_question_choice AS selected ON selected.rowid = a.fk_choice AND selected.entity = a.entity';
		$sql .= ' WHERE q.entity = '.((int) $entity).' AND q.active = 1 ORDER BY q.position, q.rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->fail();
			return array();
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$questionId = (int) $row->rowid;
			$questions[$questionId] = array(
				'id' => $questionId,
				'code' => (string) $row->code,
				'label' => (string) $row->label,
				'description' => (string) $row->description,
				'date_label' => (string) $row->date_label,
				'answer_choice_id' => (int) $row->fk_choice,
				'answer_code' => (string) $row->answer_code,
				'applicable_since' => !empty($row->applicable_since) ? $this->db->jdate($row->applicable_since) : 0,
				'choices' => array(),
			);
		}
		$this->db->free($resql);
		if (empty($questions)) return array();

		$sql = 'SELECT rowid, fk_question, code, label, requires_date FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_question_choice';
		$sql .= ' WHERE entity = '.((int) $entity).' AND active = 1 AND fk_question IN ('.implode(',', array_keys($questions)).') ORDER BY position, rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->fail();
			return array();
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$questionId = (int) $row->fk_question;
			if (!isset($questions[$questionId])) continue;
			$questions[$questionId]['choices'][(int) $row->rowid] = array(
				'id' => (int) $row->rowid,
				'code' => (string) $row->code,
				'label' => (string) $row->label,
				'requires_date' => (int) $row->requires_date,
			);
		}
		$this->db->free($resql);

		return $questions;
	}

	/**
	 * Persist qualification answers and recalculate profiles atomically.
	 *
	 * @param object $vehicle Vehicle object
	 * @param array<int,array{choice_id:int,applicable_since?:int}> $answers Answers keyed by question id
	 * @param list<int> $manualProfileIds Additional custom profiles
	 * @param User $user Author
	 * @param int $notrigger Disable the single vehicle UPDATE trigger
	 * @return int<-1,1>
	 */
	public function saveVehicleQualification($vehicle, array $answers, array $manualProfileIds, User $user, $notrigger = 0)
	{
		$entity = (int) $vehicle->entity;
		$vehicleId = (int) $vehicle->id;
		$questionnaire = $this->getQualificationQuestionnaire($vehicleId, $entity);
		if (empty($questionnaire) && $this->error !== '') return -1;
		$deducedProfileCodes = $this->suggestProfileCodes($vehicle);
		$answerProfileCodes = array();
		$complete = !empty($questionnaire);
		$validatedAnswers = array();
		foreach ($questionnaire as $questionId => $question) {
			$choiceId = isset($answers[$questionId]['choice_id']) ? (int) $answers[$questionId]['choice_id'] : 0;
			if ($choiceId <= 0 || !isset($question['choices'][$choiceId])) {
				$complete = false;
				continue;
			}
			$choice = $question['choices'][$choiceId];
			$applicableSince = isset($answers[$questionId]['applicable_since']) ? (int) $answers[$questionId]['applicable_since'] : 0;
			if ($choice['code'] === 'unknown' || (!empty($choice['requires_date']) && $applicableSince <= 0)) $complete = false;
			if (empty($choice['requires_date'])) $applicableSince = 0;
			$validatedAnswers[$questionId] = array('choice_id' => $choiceId, 'applicable_since' => $applicableSince);
		}

		$this->db->begin();
		$questionIds = array_map('intval', array_keys($questionnaire));
		if (!empty($questionIds)) {
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_answer';
			$sql .= ' WHERE entity = '.$entity.' AND fk_vehicle = '.$vehicleId.' AND fk_question IN ('.implode(',', $questionIds).')';
			if (!empty($validatedAnswers)) {
				$sql .= ' AND fk_question NOT IN ('.implode(',', array_map('intval', array_keys($validatedAnswers))).')';
			}
			if (!$this->db->query($sql)) return $this->rollback();
		}
		foreach ($validatedAnswers as $questionId => $answer) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_answer (entity, fk_vehicle, fk_question, fk_choice, origin, applicable_since, date_creation, fk_user_creat, fk_user_modif) VALUES (';
			$sql .= $entity.', '.$vehicleId.', '.((int) $questionId).', '.((int) $answer['choice_id']).", 'questionnaire', ".($answer['applicable_since'] > 0 ? "'".$this->db->idate($answer['applicable_since'])."'" : 'NULL').", '".$this->db->idate(dol_now())."', ".((int) $user->id).', '.((int) $user->id).')';
			$sql .= ' ON DUPLICATE KEY UPDATE fk_choice = VALUES(fk_choice), applicable_since = VALUES(applicable_since), origin = VALUES(origin), fk_user_modif = VALUES(fk_user_modif)';
			if (!$this->db->query($sql)) return $this->rollback();
		}

		$sql = 'SELECT DISTINCT choice_profile.profile_code FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_answer AS answer';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_question_choice AS choice_profile ON choice_profile.rowid = answer.fk_choice AND choice_profile.entity = answer.entity';
		$sql .= ' WHERE answer.entity = '.$entity.' AND answer.fk_vehicle = '.$vehicleId." AND choice_profile.profile_code IS NOT NULL AND choice_profile.profile_code <> ''";
		$resql = $this->db->query($sql);
		if (!$resql) return $this->rollback();
		while (is_object($row = $this->db->fetch_object($resql))) $answerProfileCodes[] = (string) $row->profile_code;
		$this->db->free($resql);
		$profileCodes = array_values(array_unique(array_merge($deducedProfileCodes, $answerProfileCodes)));
		$automaticIds = $this->getProfileIdsByCodes($entity, $profileCodes);
		$deducedIds = $this->getProfileIdsByCodes($entity, $deducedProfileCodes);
		$manualProfileIds = array_values(array_unique(array_filter(array_map('intval', $manualProfileIds))));
		$quotedManagedCodes = array();
		foreach (array_keys(LmdbVehicleRegulatoryCatalog::getProfiles()) as $managedCode) $quotedManagedCodes[] = "'".$this->db->escape($managedCode)."'";

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile WHERE entity = '.$entity.' AND fk_vehicle = '.$vehicleId;
		if (!$this->db->query($sql)) return $this->rollback();
		foreach ($automaticIds as $profileId) {
			$origin = in_array($profileId, $deducedIds, true) ? 'deduced' : 'questionnaire';
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile (entity, fk_vehicle, fk_profile, origin, confirmed, date_creation, fk_user_creat) VALUES (';
			$sql .= $entity.', '.$vehicleId.', '.((int) $profileId).", '".$origin."', 1, '".$this->db->idate(dol_now())."', ".((int) $user->id).')';
			if (!$this->db->query($sql)) return $this->rollback();
		}
		foreach ($manualProfileIds as $profileId) {
			if (in_array($profileId, $automaticIds, true)) continue;
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile (entity, fk_vehicle, fk_profile, origin, confirmed, date_creation, fk_user_creat)';
			$sql .= ' SELECT '.$entity.', '.$vehicleId.', '.$profileId.", 'manual', 1, '".$this->db->idate(dol_now())."', ".((int) $user->id);
			$sql .= ' FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile WHERE rowid = '.$profileId.' AND entity IN ('.getEntity('c_lmdbvehiclemanagement_regulatory_profile').') AND active = 1 AND code NOT IN ('.implode(',', $quotedManagedCodes).')';
			if (!$this->db->query($sql)) return $this->rollback();
		}
		if ($this->synchronizeRequirements($vehicle, $user) < 0) return $this->rollback();

		if (!$notrigger && method_exists($vehicle, 'call_trigger')) {
			if (!isset($vehicle->context) || !is_array($vehicle->context)) $vehicle->context = array();
			$vehicle->context['trigger_reason'] = 'regulatory_qualification_change';
			$vehicle->context['changed_fields'] = array('regulatory_qualification');
			$vehicle->context['regulatory_qualification_complete'] = $complete ? 1 : 0;
			if ($vehicle->call_trigger('LMDBVEHICLEMANAGEMENT_VEHICLE_UPDATE', $user) < 0) return $this->rollback();
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Compatibility entry point: preserve current answers and route every manual
	 * profile update through the transactional qualification workflow.
	 *
	 * @param object $vehicle Vehicle
	 * @param list<int> $profileIds Additional custom profiles
	 * @param User $user Author
	 * @return int<-1,1>
	 */
	public function saveVehicleProfiles($vehicle, $profileIds, User $user)
	{
		$questionnaire = $this->getQualificationQuestionnaire((int) $vehicle->id, (int) $vehicle->entity);
		if (empty($questionnaire) && $this->error !== '') return -1;
		$answers = array();
		foreach ($questionnaire as $questionId => $question) {
			$answers[(int) $questionId] = array(
				'choice_id' => (int) $question['answer_choice_id'],
				'applicable_since' => (int) $question['applicable_since'],
			);
		}

		return $this->saveVehicleQualification($vehicle, $answers, array_values(array_unique(array_filter(array_map('intval', $profileIds)))), $user);
	}

	/** @param object $vehicle Vehicle @param User $user Author @return int<-1,1> */
	public function initializeSuggestedProfiles($vehicle, User $user)
	{
		$codes = $this->suggestProfileCodes($vehicle);
		if (empty($codes)) return 1;
		$ids = $this->getProfileIdsByCodes((int) $vehicle->entity, $codes);
		foreach ($ids as $profileId) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile (entity, fk_vehicle, fk_profile, origin, confirmed, date_creation, fk_user_creat) SELECT ';
			$sql .= ((int) $vehicle->entity).', '.((int) $vehicle->id).', '.$profileId.", 'deduced', 0, '".$this->db->idate(dol_now())."', ".((int) $user->id);
			$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile WHERE entity = '.((int) $vehicle->entity).' AND fk_vehicle = '.((int) $vehicle->id).' AND fk_profile = '.$profileId.')';
			if (!$this->db->query($sql)) return $this->fail();
		}
		return $this->synchronizeRequirements($vehicle, $user);
	}

	/**
	 * Refresh only unconfirmed deductions after a vehicle classification change.
	 * Confirmed choices are user decisions and are never overwritten.
	 *
	 * @param object $vehicle Vehicle
	 * @param User $user Author
	 * @return int<-1,1>
	 */
	public function refreshSuggestedProfiles($vehicle, User $user)
	{
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile';
		$sql .= ' WHERE entity = '.((int) $vehicle->entity).' AND fk_vehicle = '.((int) $vehicle->id);
		$sql .= " AND origin = 'deduced' AND confirmed = 0";
		if (!$this->db->query($sql)) return $this->fail();

		return $this->initializeSuggestedProfiles($vehicle, $user);
	}

	/** @param object $vehicle Vehicle @param User $user Author @return int<-1,1> */
	public function synchronizeRequirements($vehicle, User $user)
	{
		$vehicleContext = $this->fetchVehicleContext((int) $vehicle->id, (int) $vehicle->entity);
		if ($vehicleContext === null) return -1;
		$selectedRules = array();
		$sql = 'SELECT DISTINCT r.rowid AS fk_rule, r.code, r.obligation_group, r.applicability_code, r.applicability_priority, r.default_blocking_mode, selected_profile.code AS profile_code FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile AS vp';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile AS selected_profile ON selected_profile.rowid = vp.fk_profile AND selected_profile.active = 1';
		// A shared dictionary row can belong to another entity. Rules stay local,
		// so resolve the selected profile through its stable code in the vehicle entity.
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile AS local_profile ON local_profile.entity = vp.entity AND local_profile.code = selected_profile.code AND local_profile.active = 1';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile AS rp ON rp.entity = vp.entity AND rp.fk_profile = local_profile.rowid';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r ON r.rowid = rp.fk_rule AND r.entity = vp.entity AND r.active = 1';
		$sql .= ' AND (r.effective_from IS NULL OR r.effective_from <= CURRENT_DATE) AND (r.effective_to IS NULL OR r.effective_to >= CURRENT_DATE)';
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS override_rule WHERE override_rule.entity = r.entity AND override_rule.fk_parent_rule = r.rowid AND override_rule.active = 1 AND (override_rule.effective_from IS NULL OR override_rule.effective_from <= CURRENT_DATE) AND (override_rule.effective_to IS NULL OR override_rule.effective_to >= CURRENT_DATE))';
		$sql .= ' WHERE vp.entity = '.((int) $vehicle->entity).' AND vp.fk_vehicle = '.((int) $vehicle->id).' AND vp.confirmed = 1';
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$applicable = $this->isRuleApplicable($row, $vehicleContext);
			if ($applicable === false) continue;
			$group = trim((string) $row->obligation_group);
			if ($group === '') $group = 'RULE_'.((int) $row->fk_rule);
			$priority = (int) $row->applicability_priority;
			if (!isset($selectedRules[$group]) || $priority > $selectedRules[$group]['priority'] || ($priority === $selectedRules[$group]['priority'] && strcmp((string) $row->code, $selectedRules[$group]['code']) < 0)) {
				$selectedRules[$group] = array(
					'id' => (int) $row->fk_rule,
					'code' => (string) $row->code,
					'priority' => $priority,
					'blocking_mode' => (string) $row->default_blocking_mode,
					'applicable' => $applicable,
					'applicability_date' => $this->getProfileApplicabilityDate((int) $vehicle->id, (int) $vehicle->entity, (string) $row->profile_code),
				);
			}
		}
		$this->db->free($resql);
		$ruleIds = array();
		foreach ($selectedRules as $selectedRule) {
			$ruleId = (int) $selectedRule['id'];
			$ruleIds[] = $ruleId;
			$applicabilityDateSql = $selectedRule['applicability_date'] > 0 ? "'".$this->db->idate($selectedRule['applicability_date'])."'" : 'NULL';
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement (entity, fk_vehicle, fk_rule, requirement_kind, fk_source_control, qualification_status, applicability_date, status, blocking_mode, date_creation, fk_user_creat)';
			$sql .= ' SELECT '.((int) $vehicle->entity).', '.((int) $vehicle->id).', '.$ruleId.", 'periodic', 0, ".($selectedRule['applicable'] === true ? "'complete'" : "'incomplete'").', '.$applicabilityDateSql.", 'incomplete', r.default_blocking_mode, '".$this->db->idate(dol_now())."', ".((int) $user->id);
			$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r WHERE r.rowid = '.$ruleId.' AND r.entity = '.((int) $vehicle->entity);
			$sql .= " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE entity = '.((int) $vehicle->entity).' AND fk_vehicle = '.((int) $vehicle->id).' AND fk_rule = '.$ruleId." AND requirement_kind = 'periodic' AND fk_source_control = 0)";
			if (!$this->db->query($sql)) return $this->fail();
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET applicability_date = '.$applicabilityDateSql;
			$sql .= ', qualification_status = '.($selectedRule['applicable'] === true ? "'complete'" : "'incomplete'").", blocking_mode = '".$this->db->escape($selectedRule['blocking_mode'])."'";
			$sql .= ' WHERE entity = '.((int) $vehicle->entity).' AND fk_vehicle = '.((int) $vehicle->id).' AND fk_rule = '.$ruleId." AND requirement_kind = 'periodic' AND fk_source_control = 0";
			if (!$this->db->query($sql)) return $this->fail();
		}
		if (!empty($ruleIds)) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET active = 0 WHERE entity = '.((int) $vehicle->entity).' AND fk_vehicle = '.((int) $vehicle->id).' AND requirement_kind = \'periodic\' AND fk_source_control = 0 AND fk_rule NOT IN ('.implode(',', $ruleIds).')';
			if (!$this->db->query($sql)) return $this->fail();
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET active = 1 WHERE entity = '.((int) $vehicle->entity).' AND fk_vehicle = '.((int) $vehicle->id).' AND requirement_kind = \'periodic\' AND fk_source_control = 0 AND fk_rule IN ('.implode(',', $ruleIds).')';
			if (!$this->db->query($sql)) return $this->fail();
		} else {
			$sql = 'UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_control_requirement SET active = 0 WHERE entity = ".((int) $vehicle->entity).' AND fk_vehicle = '.((int) $vehicle->id)." AND requirement_kind = 'periodic' AND fk_source_control = 0";
			if (!$this->db->query($sql)) return $this->fail();
		}
		return $this->recalculateVehicle((int) $vehicle->id, (int) $vehicle->entity);
	}

	/** @param int $vehicleId Vehicle @param int $entity Entity @return object|null */
	private function fetchVehicleContext($vehicleId, $entity)
	{
		$sql = 'SELECT v.rowid, v.entity, v.eu_category, v.national_genre, v.gvw_kg, v.seats, v.first_registration_date, v.commissioning_date, v.construction_date, energy.code AS energy_code';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy AS energy ON energy.rowid = v.fk_energy';
		$sql .= ' WHERE v.rowid = '.((int) $vehicleId).' AND v.entity = '.((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->fail();
			return null;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			$this->error = 'VehicleNotFound';
			$this->errors[] = $this->error;
			return null;
		}

		return $row;
	}

	/**
	 * Evaluate an applicability predicate. Null means that qualification data is
	 * insufficient: the obligation is kept visible without inventing a date.
	 *
	 * @param object $rule Rule row
	 * @param object $vehicle Vehicle context
	 * @return bool|null
	 */
	private function isRuleApplicable($rule, $vehicle)
	{
		if ((string) $rule->applicability_code !== 'n1_pollution') return true;
		$energy = strtoupper(trim((string) $vehicle->energy_code));
		if ($energy === '') return null;
		if (in_array($energy, array('GA', 'EL', 'AC', 'H2', 'HE', 'HH'), true)) return false;
		if (empty($vehicle->first_registration_date)) return null;
		$firstRegistration = $this->db->jdate($vehicle->first_registration_date);
		$sparkIgnition = array('ES', 'EG', 'EN', 'EE', 'ER', 'EM', 'EH', 'EQ', 'EP', 'FE', 'FG', 'FN', 'FL', 'FH', 'FR', 'FQ', 'FM', 'FP', 'GP', 'PE', 'PH', 'GN', 'NE', 'NH', 'ET', 'GZ', 'GE');
		$compressionIgnition = array('B1', 'BL', 'BH', 'GO', 'GL', 'GH', 'GF', '1A', 'G2', 'GM', 'GQ', 'GG');
		if (in_array($energy, $sparkIgnition, true)) return $firstRegistration >= dol_mktime(0, 0, 0, 10, 1, 1972);
		if (in_array($energy, $compressionIgnition, true)) return $firstRegistration >= dol_mktime(0, 0, 0, 1, 1, 1980);

		return true;
	}

	/** @param int $vehicleId Vehicle @param int $entity Entity @param string $profileCode Profile @return int */
	private function getProfileApplicabilityDate($vehicleId, $entity, $profileCode)
	{
		if ($profileCode === '') return 0;
		$sql = 'SELECT answer.applicable_since FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_answer AS answer';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_question_choice AS choice_answer ON choice_answer.rowid = answer.fk_choice AND choice_answer.entity = answer.entity';
		$sql .= ' WHERE answer.entity = '.((int) $entity).' AND answer.fk_vehicle = '.((int) $vehicleId)." AND choice_answer.profile_code = '".$this->db->escape($profileCode)."'";
		$sql .= ' ORDER BY answer.rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) return 0;
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($row) && !empty($row->applicable_since) ? $this->db->jdate($row->applicable_since) : 0;
	}

	/**
	 * Synchronize all qualified vehicles when rule validity or overrides change.
	 *
	 * @param int $entity Entity
	 * @param User $user Author used for newly materialized requirements
	 * @return int<-1,1>
	 */
	public function synchronizeEntityRequirements($entity, User $user)
	{
		$sql = 'SELECT v.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v';
		$sql .= ' WHERE v.entity = '.((int) $entity).' ORDER BY v.rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->fail();
		}
		$vehicleIds = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$vehicleIds[] = (int) $row->rowid;
		}
		$this->db->free($resql);

		foreach ($vehicleIds as $vehicleId) {
			$vehicle = new stdClass();
			$vehicle->id = $vehicleId;
			$vehicle->entity = (int) $entity;
			if ($this->synchronizeRequirements($vehicle, $user) < 0) {
				return -1;
			}
		}
		return 1;
	}

	/** @param int $vehicleId Vehicle @param int $entity Entity @return int<-1,1> */
	public function recalculateVehicle($vehicleId, $entity)
	{
		$sql = 'SELECT req.rowid, req.fk_rule, req.requirement_kind, req.fk_source_control, req.derogation_until, req.blocking_mode, req.applicability_date, r.calculator_code, r.applicability_code, r.initial_delay_months, r.recurrence_months, r.recurrence_days,';
		$sql .= ' v.eu_category, v.first_registration_date, v.commissioning_date, v.construction_date, energy.code AS energy_code, c.rowid AS control_id, c.control_date, c.result_code, c.official_valid_until, c.calculated_valid_until, c.retained_valid_until,';
		$sql .= ' cr.requires_recheck, cr.is_blocking';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r ON r.rowid = req.fk_rule AND r.entity = req.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = req.fk_vehicle AND v.entity = req.entity';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy AS energy ON energy.rowid = v.fk_energy';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control AS c ON c.rowid = (SELECT c2.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control AS c2 WHERE c2.entity = req.entity AND c2.fk_vehicle = req.fk_vehicle AND c2.fk_rule = req.fk_rule AND c2.status = 1 ORDER BY c2.control_date DESC, c2.rowid DESC LIMIT 1)';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result AS cr ON cr.code = c.result_code AND cr.entity = req.entity';
		$sql .= ' WHERE req.entity = '.((int) $entity).' AND req.fk_vehicle = '.((int) $vehicleId).' AND req.active = 1';
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$recheckResolved = (string) $row->requirement_kind === 'recheck' && !empty($row->control_id) && (int) $row->control_id !== (int) $row->fk_source_control;
			$applicable = $this->isRuleApplicable($row, $row);
			$calculated = $recheckResolved || $applicable !== true ? 0 : $this->calculateDueDate($row);
			$retained = $recheckResolved ? 0 : (!empty($row->retained_valid_until) ? $this->db->jdate($row->retained_valid_until) : (!empty($row->official_valid_until) ? $this->db->jdate($row->official_valid_until) : $calculated));
			$status = $recheckResolved ? 'up_to_date' : $this->resolveStatus($row, $retained);
			$qualification = $recheckResolved || ($applicable === true && $retained > 0) ? 'complete' : 'incomplete';
			$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET fk_last_control = '.(!empty($row->control_id) ? (int) $row->control_id : 'NULL');
			$sqlUpdate .= ', qualification_status = '.($qualification === 'complete' ? "'complete'" : "'incomplete'");
			$sqlUpdate .= ', calculated_due_date = '.($calculated > 0 ? "'".$this->db->idate($calculated)."'" : 'NULL');
			$sqlUpdate .= ', retained_due_date = '.($retained > 0 ? "'".$this->db->idate($retained)."'" : 'NULL');
			$sqlUpdate .= ", status = '".$this->db->escape($status)."', last_evaluated = '".$this->db->idate(dol_now())."' WHERE rowid = ".((int) $row->rowid).' AND entity = '.((int) $entity);
			if (!$this->db->query($sqlUpdate)) { $this->db->free($resql); return $this->fail(); }
		}
		$this->db->free($resql);
		return 1;
	}

	/** Materialize a separate counter-visit requirement linked to an adverse control. @param object $control Validated control @param User $user Author @return int<-1,1> */
	public function ensureRecheckRequirement($control, User $user)
	{
		if (empty($control->result_code)) return 1;
		$sql = 'SELECT r.code, r.recheck_days, r.default_blocking_mode, cr.requires_recheck, cr.is_blocking, v.regulatory_territory, v.eu_category FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result AS cr ON cr.code = \''.$this->db->escape((string) $control->result_code).'\' AND cr.entity = r.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = '.((int) $control->fk_vehicle).' AND v.entity = r.entity';
		$sql .= ' WHERE r.rowid = '.((int) $control->fk_rule).' AND r.entity = '.((int) $control->entity);
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail();
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row) || empty($row->requires_recheck)) return 1;
		$recheckDays = $this->resolveRecheckDays($row);
		$dueDate = $recheckDays > 0 ? dol_time_plus_duree((int) $control->control_date, $recheckDays, 'd') : 0;
		$blockingMode = !empty($row->is_blocking) ? (string) $row->default_blocking_mode : 'none';
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement (entity, fk_vehicle, fk_rule, requirement_kind, fk_source_control, fk_last_control, qualification_status, calculated_due_date, retained_due_date, status, blocking_mode, date_creation, fk_user_creat) SELECT ';
		$sql .= ((int) $control->entity).', '.((int) $control->fk_vehicle).', '.((int) $control->fk_rule).", 'recheck', ".((int) $control->id).', '.((int) $control->id).", 'complete', ".($dueDate > 0 ? "'".$this->db->idate($dueDate)."'" : 'NULL').', '.($dueDate > 0 ? "'".$this->db->idate($dueDate)."'" : 'NULL').", 'recheck_required', '".$this->db->escape($blockingMode)."', '".$this->db->idate(dol_now())."', ".((int) $user->id);
		$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE entity = '.((int) $control->entity).' AND fk_vehicle = '.((int) $control->fk_vehicle).' AND fk_rule = '.((int) $control->fk_rule)." AND requirement_kind = 'recheck' AND fk_source_control = ".((int) $control->id).')';
		return $this->db->query($sql) ? 1 : $this->fail();
	}

	/** @param int $entity Entity @return int<-1,1> */
	public function recalculateEntity($entity)
	{
		$sql = 'SELECT DISTINCT fk_vehicle FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE entity = '.((int) $entity).' AND active = 1';
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail();
		$vehicleIds = array();
		while (is_object($row = $this->db->fetch_object($resql))) $vehicleIds[] = (int) $row->fk_vehicle;
		$this->db->free($resql);
		foreach ($vehicleIds as $vehicleId) if ($this->recalculateVehicle($vehicleId, $entity) < 0) return -1;
		return 1;
	}

	/** @param object $row Requirement row @return int Unix timestamp, 0 if unreliable */
	private function calculateDueDate($row)
	{
		$calculator = (string) $row->calculator_code;
		if (!empty($row->official_valid_until)) return $this->db->jdate($row->official_valid_until);
		$lastControlDate = !empty($row->control_date) ? $this->db->jdate($row->control_date) : 0;
		if ($lastControlDate > 0 && !empty($row->recurrence_months)) return dol_time_plus_duree($lastControlDate, (int) $row->recurrence_months, 'm');
		if ($lastControlDate > 0 && !empty($row->recurrence_days)) return dol_time_plus_duree($lastControlDate, (int) $row->recurrence_days, 'd');
		$firstRegistration = !empty($row->first_registration_date) ? $this->db->jdate($row->first_registration_date) : 0;
		$commissioning = !empty($row->commissioning_date) ? $this->db->jdate($row->commissioning_date) : 0;
		$construction = !empty($row->construction_date) ? $this->db->jdate($row->construction_date) : 0;
		$applicabilityDate = !empty($row->applicability_date) ? $this->db->jdate($row->applicability_date) : 0;
		$base = in_array($calculator, array('road_light', 'n1_pollution', 'category_l'), true) ? $firstRegistration : ($commissioning ?: $construction);
		if ($calculator === 'document_expiry' || $calculator === 'event_based') return 0;
		if (in_array($calculator, array('special_annual', 'special_breakdown'), true)) {
			if ($applicabilityDate <= 0) return 0;
			if ($calculator === 'special_annual' && $firstRegistration > 0) {
				return max($applicabilityDate, dol_time_plus_duree($firstRegistration, 12, 'm'));
			}
			return dol_time_plus_duree($applicabilityDate, 12, 'm');
		}
		if ($calculator === 'category_l' && $firstRegistration > 0) {
			$year = (int) dol_print_date($firstRegistration, '%Y');
			if ($year <= 2016) {
				$monthDay = (int) dol_print_date($firstRegistration, '%m%d');
				if ($monthDay < 415) return dol_mktime(12, 0, 0, 8, 15, 2024);
				return $this->categoryLTransitionalDueDate($firstRegistration, 2024);
			}
			if ($year <= 2019) return $this->categoryLTransitionalDueDate($firstRegistration, 2025);
			if ($year <= 2021) return $this->categoryLTransitionalDueDate($firstRegistration, 2026);
		}
		if ($base <= 0) return 0;
		if ($row->initial_delay_months !== null) return dol_time_plus_duree($base, (int) $row->initial_delay_months, 'm');
		if ($calculator === 'periodic_months' && $row->recurrence_months !== null) return dol_time_plus_duree($base, (int) $row->recurrence_months, 'm');
		if ($calculator === 'periodic_days' && $row->recurrence_days !== null) return dol_time_plus_duree($base, (int) $row->recurrence_days, 'd');
		return 0;
	}

	/** @param object $row Rule and vehicle context @return int */
	private function resolveRecheckDays($row)
	{
		$code = (string) $row->code;
		if ($code === 'FR_CATEGORY_L' || $code === 'FR_ROAD_LIGHT' || strpos($code, 'FR_SPECIAL_') === 0) return 60;
		if (in_array($code, array('FR_ROAD_HEAVY', 'FR_PUBLIC_TRANSPORT'), true)) {
			$euCategory = strtoupper(trim((string) $row->eu_category));
			if (strpos($euCategory, 'M1') === 0) return 60;
			if (in_array((string) $row->regulatory_territory, array('FR_GUADELOUPE', 'FR_MARTINIQUE', 'FR_GUYANE', 'FR_REUNION', 'FR_MAYOTTE'), true)) return 60;
			return 30;
		}

		return !empty($row->recheck_days) ? (int) $row->recheck_days : 0;
	}

	/** @param object $row Requirement row @param int $dueDate Due date @return string */
	private function resolveStatus($row, $dueDate)
	{
		if (!empty($row->derogation_until) && $this->db->jdate($row->derogation_until) >= dol_now()) return 'derogation_active';
		if (!empty($row->is_blocking)) return 'non_compliant_blocking';
		if (!empty($row->requires_recheck)) return 'recheck_required';
		if ($dueDate <= 0) return 'incomplete';
		$today = dol_mktime(0, 0, 0, (int) dol_print_date(dol_now(), '%m'), (int) dol_print_date(dol_now(), '%d'), (int) dol_print_date(dol_now(), '%Y'));
		if ($dueDate < $today) return 'overdue';
		return $dueDate <= dol_time_plus_duree($today, getDolGlobalInt('LMDBVEHICLEMANAGEMENT_CONTROL_DUE_SOON_DAYS', 90), 'd') ? 'due_soon' : 'up_to_date';
	}

	/** @param int $vehicleId Vehicle @param string $action assignment or service @return int<-1,1> */
	public function vehicleActionIsAllowed($vehicleId, $action)
	{
		$modes = $action === 'assignment' ? array('assignment', 'both') : array('service', 'both');
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE fk_vehicle = '.((int) $vehicleId).' AND active = 1 AND entity IN ('.getEntity('lmdbvehicle').')';
		$sql .= " AND status IN ('overdue','non_compliant_blocking','recheck_required') AND blocking_mode IN ('".implode("','", $modes)."')";
		$sql .= ' AND (derogation_until IS NULL OR derogation_until < NOW()) LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail();
		$blocked = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);
		if ($blocked) {
			$this->error = $action === 'assignment' ? 'VehicleRegulatoryAssignmentBlocked' : 'VehicleRegulatoryServiceBlocked';
			$this->errors[] = $this->error;
			return 0;
		}
		return 1;
	}

	/** Return the four-month transitional category L deadline, capped at year end. @param int $firstRegistration First registration @param int $targetYear Transition year @return int */
	private function categoryLTransitionalDueDate($firstRegistration, $targetYear)
	{
		$anniversary = dol_mktime(12, 0, 0, (int) dol_print_date($firstRegistration, '%m'), (int) dol_print_date($firstRegistration, '%d'), $targetYear);
		$deadline = dol_time_plus_duree($anniversary, 4, 'm');
		$yearEnd = dol_mktime(12, 0, 0, 12, 31, $targetYear);
		return min($deadline, $yearEnd);
	}

	/** @param int $entity Entity @param list<string> $codes Codes @return list<int> */
	private function getProfileIdsByCodes($entity, $codes)
	{
		if (empty($codes)) return array();
		$quoted = array();
		foreach ($codes as $code) $quoted[] = "'".$this->db->escape($code)."'";
		$ids = array();
		$resql = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile WHERE entity = '.((int) $entity).' AND code IN ('.implode(',', $quoted).') AND active = 1');
		if ($resql) {
			while (is_object($row = $this->db->fetch_object($resql))) $ids[] = (int) $row->rowid;
			$this->db->free($resql);
		}
		return $ids;
	}

	/** @return int<-1,-1> */ private function fail() { $this->error = $this->db->lasterror(); $this->errors[] = $this->error; return -1; }
	/** @return int<-1,-1> */ private function rollback() { $this->error = $this->db->lasterror(); $this->errors[] = $this->error; $this->db->rollback(); return -1; }
}
