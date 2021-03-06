<?php

/**
 * Custom search to Find Expert
 * PUM Senior Experts
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 16 March 2016
 * @license AGPL-3.0

 *
 */
class CRM_Findexpert_Form_Search_FindExpert extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

  // select list that need to be at class level
  private $_languagesWithLevels = array();
  private $_genericSkillsList = array();

  // custom table names needed
  private $_workHistoryCustomGroupTable = NULL;
  private $_educationCustomGroupTable = NULL;
  private $_expertDataCustomGroupTable = NULL;
  private $_languageCustomGroupTable = NULL;
  private $_prinsHistoryCustomGroupTable = NULL;
  private $_workHistoryCustomGroupId = NULL;
  private $_educationCustomGroupId = NULL;
  private $_expertDataCustomGroupId = NULL;
  private $_languageCustomGroupId = NULL;
  private $_prinsHistoryCustomGroupId = NULL;

  // custom field column names needed
  private $_whNameOfOrganizationColumn = NULL;
  private $_whDescriptionColumn = NULL;
  private $_whCompetencesUsedColumn = NULL;
  private $_whResponsibilitiesColumn = NULL;
  private $_whCountriesVisitedColumn = NULL;
  private $_llLanguagesColumn = NULL;
  private $_llLevelColumn = NULL;
  private $_expStatusColumn = NULL;
  private $_expGenericColumn = NULL;
  private $_expSideActivitiesColumn = NULL;
  private $_eduNameInstitutionColumn = NULL;
  private $_eduFieldOfStudyColumn = NULL;
  private $_phPrinsHistoryColumn = NULL;

  // Group IDs of which contact should be a member of.
  private $_candidateExpertGroupId = NULL;
  private $_activeExpertGroupId = NULL;

  // properties for clauses, params, searchColumns and likes
  private $_whereClauses = array();
  private $_whereParams = array();
  private $_whereIndex = NULL;
  private $_searchColumns = array();
  private $_searchLike = NULL;

  // property for restriction activity type id
  private $_restrictionsActivityTypeId = NULL;
  private $_scheduledActivityStatusValue = NULL;

  // properties for valid case types and case status for latest main activity
  private $_validCaseTypes = array();
  private $_validCaseStatus = array();

  private $caseStatusOptionGroupId;

  /**
   * CRM_Findexpert_Form_Search_FindExpert constructor.
   * @param $formValues
   * @throws Exception when unable to find option group with API
   */
  function __construct(&$formValues) {
    $this->setLanguagesWithLevels();
    $this->getGenericSkillsList();
    $this->setRequiredCustomTables();
    $this->setRequiredCustomColumns();
    $this->setActivityTypes();
    $this->setActivityStatus();
    $this->setValidCaseTypes();
    $this->setValidCaseStatus();
    $this->setGroupIds();

    parent::__construct($formValues);
  }

  /**
   * Prepare a set of search fields
   *
   * @param CRM_Core_Form $form modifiable
   * @return void
   */
  function buildForm(&$form) {
    CRM_Utils_System::setTitle(ts('Find Expert(s)'));

    $sectorList = $this->getSectorList();
    $form->add('select', 'sector_id', ts('Sector(s)'), $sectorList, FALSE,
      array('id' => 'sector_id', 'multiple' => 'multiple', 'title' => ts('- select -'))
    );

    $areasOfExpertiseList = $this->getAreasOfExpertiseList();
    $form->add('select', 'expertise_id', ts('Areas(s) of Expertise'), $areasOfExpertiseList, FALSE,
      array('id' => 'expertise_id', 'multiple' => 'multiple', 'title' => ts('- select -'),'size'=>10)
    );
    $form->assign('areas_of_expertise_list', json_encode($this->getAreasOfExpertiseListByParentId()));

    $form->add('select', 'generic_id', ts('Generic Skill(s)'), $this->_genericSkillsList, FALSE,
      array('id' => 'generic_id', 'multiple' => 'multiple', 'title' => ts('- select -'))
    );

    $languageList = $this->getLanguageList();
    $form->add('select', 'language_id', ts('Language(s)'), $languageList, FALSE,
      array('id' => 'language_id', 'multiple' => 'multiple', 'title' => ts('- select -'))
    );

    $form->add('text', 'overall_string', ts('Search Expert Data for'), array(
      'size' => CRM_Utils_Type::HUGE, 'maxlength' =>  255));

    $form->add('select', 'countries_visited', ts('Countries Visited in Work History'),
      CRM_Core_PseudoConstant::country(), FALSE, array('id' => 'countries_visited',
        'multiple' => 'multiple', 'title' => ts('- select -'))
    );

    $allCases = array(
      '1' => ts('Case insensitive (ignore upper/lower case when searching)'),
      '0' => ts('Case sensitive (respect upper/lower case when searching'),
    );
    $form->addRadio('ignore_cases', ts('Ignore capitals?'), $allCases, NULL, '<br />', TRUE);
    $defaults['ignore_cases'] = 1;
    $form->setDefaults($defaults);

    $form->assign('elements', array('sector_id', 'expertise_id', 'generic_id', 'language_id',
      'overall_string', 'countries_visited'));
    $form->addButtons(array(array('type' => 'refresh', 'name' => ts('Search'), 'isDefault' => TRUE,),));
  }


  /**
   * Method to get the list of sectors
   *
   * @return array
   * @access private
   */
  private function getSectorList() {
    $result = array();
    $sectors = civicrm_api3('Segment', 'Get', array('parent_id' => 'null', 'is_active' => 1));
    foreach ($sectors['values'] as $sectorId => $sector) {
      $result[$sectorId] = $sector['label'];
    }
    asort($result);
    return $result;
  }

  /**
   * Method to get list of areas of expertise. Initially all, jQuery in tpl will
   * determine what will be available based on selected sectors
   *
   * @return array
   * @access private
   */
  private function getAreasOfExpertiseList() {
    $result = array();
    $areas = civicrm_api3('Segment', 'Get', array('is_active' => 1));
    foreach ($areas['values'] as $areaId => $area) {
      if (!empty($area['parent_id'])) {
        $result[$areaId] = $area['label'];
      }
    }
    asort($result);
    return $result;
  }

  /**
   * Method to get list of areas of expertise. Initially all, jQuery in tpl will
   * determine what will be available based on selected sectors
   *
   * @return array
   * @access private
   */
  private function getAreasOfExpertiseListByParentId() {
    $result = array();
    $areas = civicrm_api3('Segment', 'Get', array());
    foreach ($areas['values'] as $areaId => $area) {
      if (!empty($area['parent_id'])) {
        $result[$area['parent_id']][] = array(
          'label' => $area['label'],
          'id' => $areaId,
        );
      }
    }
    return $result;
  }

  /**
   * Method to get generic skills
   *
   * @return void
   * @throws Exception when option group not found
   */
  private function getGenericSkillsList() {
    $genericSkillsParams = array('name' => 'generic_skilss_20140825142210', 'return' => 'id');
    try {
      $genericSkillsOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $genericSkillsParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find an option group for generic skills with name
      generic_skilss_20140825142210 in extension nl.pum.findexpert, contact your system administrator.
      Error from API OptionGroup Getvalue: '.$ex->getMessage().' with params '.implode('; ', $genericSkillsParams));
    }
    $this->_genericSkillsList = array();
    try {
      $optionValues = civicrm_api3('OptionValue', 'Get',
        array('option_group_id' => $genericSkillsOptionGroupId, 'is_active' => 1));
      foreach ($optionValues['values'] as $genericSkill) {
        $this->_genericSkillsList[$genericSkill['value']] = $genericSkill['label'];
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Method to build languages select list with levels
   *
   * @return array
   * @throws Exception when option group not found
   */
  private function getLanguageList() {
    $result = array();
    foreach ($this->_languagesWithLevels as $languageLevelId => $languageLevel) {
      $result[$languageLevelId] = $languageLevel['language_label'].' ('.$languageLevel['level_label'].')';
    }
    asort($result);
    return $result;
  }

  /**
   * Get a list of displayable columns
   *
   * @return array, keys are printable column headers and values are SQL column names
   */
  function &columns() {
    // return by reference
    $columns = array(
      ts('Contact Id') => 'contact_id',
      ts('Expert') => 'display_name',
      ts('Last Main Activity') => 'last_main',
      ts('Main Sector') => 'main_sector',
      ts('Expert Status') => 'expert_status',
      ts('No. of Main Act') => 'main_count',
      ts('Has Restrictions') => 'restrictions'
    );
    return $columns;
  }

  /**
   * Construct a full SQL query which returns one page worth of results
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   * @return string, sql
   */
  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    // delegate to $this->sql(), $this->select(), $this->from(), $this->where(), etc.
    return $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
  }

  /**
   * Construct a SQL SELECT clause
   *
   * @return string, sql fragment with SELECT arguments
   */
  function select() {
    return "DISTINCT(contact_a.id) AS contact_id, contact_a.display_name AS display_name,
    main.main_sector, exp.".$this->_expStatusColumn." AS expert_status, NULL AS restrictions, NULL as last_main,
    NULL as main_count";
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string, sql fragment with FROM and JOIN clauses
   */
  function from() {
    return "FROM civicrm_contact contact_a
    INNER JOIN civicrm_group_contact ON civicrm_group_contact.contact_id = contact_a.id
    LEFT JOIN pum_expert_main_sector main ON contact_a.id = main.contact_id
    LEFT JOIN pum_expert_other_sector other ON contact_a.id = other.contact_id
    LEFT JOIN pum_expert_areas_expertise areas ON contact_a.id = areas.contact_id
    LEFT JOIN ".$this->_educationCustomGroupTable." edu ON contact_a.id = edu.entity_id
    LEFT JOIN ".$this->_expertDataCustomGroupTable." exp ON contact_a.id = exp.entity_id
    LEFT JOIN ".$this->_languageCustomGroupTable." ll ON contact_a.id = ll.entity_id
    LEFT JOIN ".$this->_workHistoryCustomGroupTable." wh ON contact_a.id = wh.entity_id
    LEFT JOIN ".$this->_prinsHistoryCustomGroupTable." ph ON contact_a.id = ph.entity_id";
  }

  /**
   * Construct a SQL WHERE clause
   *
   * @param bool $includeContactIDs
   * @return string, sql fragment with conditional expressions
   */
  function where($includeContactIDs = FALSE) {
    $this->_whereClauses = array();
    $this->_whereParams = array();
    // basic where clauses that always apply: contact is expert and not deceased
    $this->addInitialWhereClauses();
    // sector clauses if selected
    $this->addSectorWhereClauses();
    // area of expertise clauses if selected
    $this->addExpertiseWhereClauses();
    // generic skills clauses if selected
    $this->addGenericSkillsWhereClauses();
    // language and level clauses if selected
    $this->addLanguageLevelWhereClauses();
    // overall search string clause if selected
    $this->addOverallSearchWhereClause();
    // countries visited clauses if selected
    $this->addCountriesVisitedWhereClause();

    if (!empty($this->_whereClauses)) {
      $where = implode(' AND ', $this->_whereClauses);
    }
    return $this->whereClause($where, $this->_whereParams);
  }

  /**
   * Method to add the countries visited where clause
   */
  private function addCountriesVisitedWhereClause() {
    if (isset($this->_formValues['countries_visited'])) {
      $countries = CRM_Core_PseudoConstant::country();
      $clauses = array();
      foreach ($this->_formValues['countries_visited'] as $countryVisitedId) {
        $this->_whereIndex++;
        $this->_whereParams[$this->_whereIndex] = array('%'.$countryVisitedId.'%', 'String');
        $clauses[] = 'wh.'.$this->_whCountriesVisitedColumn.' LIKE %'.$this->_whereIndex;

        /**
         * Issue #3460
         * Also search in Prins History for visited countries.
         */
        if (isset($countries[$countryVisitedId])) {
          $countryName = $countries[$countryVisitedId];
          $this->_whereIndex++;
          $this->_whereParams[$this->_whereIndex] = array(
            '%Country: %' . $countryName . '%',
            'String'
          );
          $clauses[] = 'ph.' . $this->_phPrinsHistoryColumn . ' LIKE %' . $this->_whereIndex;
        }
      }
      if (!empty($clauses)) {
        $this->_whereClauses[] = '('.implode(' OR ', $clauses).')';
      }
    }
  }

  /**
   * Method to add the overall search string where clause
   *
   * if first char = (, then all words separated by comma in overall string to be used in OR
   * (so if value is "CiviCRM, Drupal" the clause will be LIKE %CiviCRM% or LIKE %Drupal%
   *
   * if first char = { then all words separated by comma in overall string to be used in AND
   * (so if value is {CiviCRM Drupal} the clause will be LIKE %CiviCRM% and LIKE %Drupal%
   *
   * default LIKE %<complete string>%
   */
  private function addOverallSearchWhereClause() {
    if (isset($this->_formValues['ignore_cases']) && empty($this->_formValues['ignore_cases'])) {
      $this->_searchLike = 'LIKE BINARY';
    } else {
      $this->_searchLike = 'LIKE';
    }
    if (isset($this->_formValues['overall_string']) && !empty($this->_formValues['overall_string'])) {
      $this->_searchColumns = array($this->_expSideActivitiesColumn, $this->_eduFieldOfStudyColumn, $this->_eduNameInstitutionColumn,
        $this->_whCompetencesUsedColumn, $this->_whDescriptionColumn, $this->_whNameOfOrganizationColumn, $this->_whResponsibilitiesColumn);
      $firstChar = substr($this->_formValues['overall_string'], 0, 1);
      $lastChar = substr($this->_formValues['overall_string'], -1, 1);
      if ($firstChar == "(" && $lastChar == ")") {
        $this->stringMultipleClauses('OR');
      } elseif ($firstChar == "{" && $lastChar == "}") {
        $this->stringMultipleClauses('AND');
      } else {
        $this->stringSingleClause();
      }
    }
  }

  /**
   * Method for multiple string elements in overall search
   *
   * @param $operator
   */
  private function stringMultipleClauses($operator) {
    $trimmedSearch = substr($this->_formValues['overall_string'], 1, -1);
    $searchValues = explode(',', $trimmedSearch);
    $searchClauses = array();
    foreach ($searchValues as $searchValue) {
      $this->_whereIndex++;
      $this->_whereParams[$this->_whereIndex] = array('%'.trim($searchValue).'%', 'String');
      $clauses = array();
      foreach ($this->_searchColumns as $searchColumn) {
        $clauses[] = $searchColumn.' '.$this->_searchLike.' %'.$this->_whereIndex;
      }
      $searchClauses[] = '('.implode(' OR ', $clauses).')';
    }
    $this->_whereClauses[] = '('.implode(' '.$operator.' ', $searchClauses).')';
  }
  /**
   * Method for a single string in overall search
   */
  private function stringSingleClause() {
    $this->_whereIndex++;
    $this->_whereParams[$this->_whereIndex] = array('%'.$this->_formValues['overall_string'].'%', 'String');
    $clauses = array();
    foreach ($this->_searchColumns as $searchColumn) {
      $clauses[] = $searchColumn.' '.$this->_searchLike.' %'.$this->_whereIndex;
    }
    $this->_whereClauses[] = '('.implode(' OR ', $clauses).')';
  }

  /**
   * Method to add the language and level where clauses
   */
  private function addLanguageLevelWhereClauses() {
    if (isset($this->_formValues['language_id'])) {
      $languageLevelClauses = array();
      foreach ($this->_formValues['language_id'] as $languageLevelId) {
        $this->_whereIndex++;
        $clause = '('.$this->_llLanguagesColumn.' = %'.$this->_whereIndex;
        $this->_whereParams[$this->_whereIndex] = array($this->_languagesWithLevels[$languageLevelId]['language_id'], 'String');
        // only if a language with another level than 'Any' is selected a level part of the clause is required
        if (!empty($this->_languagesWithLevels[$languageLevelId]['level_id'])) {
          $this->_whereIndex++;
          $clause .= ' AND ' . $this->_llLevelColumn .' = %'.$this->_whereIndex;
          $this->_whereParams[$this->_whereIndex] = array($this->_languagesWithLevels[$languageLevelId]['level_id'], 'String');
        }
        $languageLevelClauses[] = $clause.')';
      }
      if (!empty($languageLevelClauses)) {
        $this->_whereClauses[] = '('.implode(' OR ', $languageLevelClauses).')';
      }
    }
  }

  /**
   * Method to add the generic skills where clauses
   */
  private function addGenericSkillsWhereClauses() {
    if (isset($this->_formValues['generic_id'])) {
      $genericClauses = array();
      foreach ($this->_formValues['generic_id'] as $genericId) {
        $this->_whereIndex++;
        $genericClauses[] = $this->_expGenericColumn.' LIKE %'.$this->_whereIndex;
        $this->_whereParams[$this->_whereIndex] = array('%'.$this->_genericSkillsList[$genericId].'%', 'String');
      }
      if (!empty($genericClauses)) {
        $this->_whereClauses[] = '('.implode(' OR ', $genericClauses).')';
      }
    }
  }

  /**
   * Method to add the area of expertise where clauses
   */
  private function addExpertiseWhereClauses() {
    if (isset($this->_formValues['expertise_id'])) {
      $expertiseIds = array();
      foreach ($this->_formValues['expertise_id'] as $expertiseId) {
        $this->_whereIndex++;
        $expertiseIds[$this->_whereIndex] = $expertiseId;
        $this->_whereParams[$this->_whereIndex] = array($expertiseId, 'Integer');
      }
      if (!empty($expertiseIds)) {
        $this->_whereClauses[] = '(areas.segment_id IN('.implode(', ', $expertiseIds).') AND areas.is_active = 1 AND (areas.start_date IS NULL OR areas.start_date < NOW()) AND (areas.end_date IS NULL OR areas.end_date > NOW()))';
      }
    }
  }

  /**
   * Method to add the sector where clauses
   */
  private function addSectorWhereClauses() {
    if (isset($this->_formValues['sector_id'])) {
      $sectorIds = array();
      foreach ($this->_formValues['sector_id'] as $sectorId) {
        $this->_whereIndex++;
        $sectorIds[$this->_whereIndex] = $sectorId;
        $this->_whereParams[$this->_whereIndex] = array($sectorId, 'Integer');
      }
      if (!empty($sectorIds)) {
        $this->_whereClauses[] = '(
          (main.segment_id IN('.implode(', ', $sectorIds).') AND main.is_active = 1 AND (main.start_date IS NULL OR main.start_date < NOW()) AND (main.end_date IS NULL OR main.end_date > NOW()) )
          OR (other.segment_id IN('.implode(', ', $sectorIds).') AND other.is_active = 1 AND (other.start_date IS NULL OR other.start_date < NOW()) AND (other.end_date IS NULL OR other.end_date > NOW()) )
          )';
      }
    }
  }

  /**
   * Determine the Smarty template for the search screen
   *
   * @return string, template path (findable through Smarty template path)
   */
  function templateFile() {
    return 'CRM/Findexpert/FindExpert.tpl';
  }

  /**
   * Modify the content of each row
   *
   * @param array $row modifiable SQL result row
   * @throws exception if function getOptionGroup not found
   * @return void
   */
  function alterRow(&$row) {
    $row['restrictions'] = $this->setRestrictions($row['contact_id']);
    $row['last_main'] = $this->setLastMain($row['contact_id']);
    if (method_exists('CRM_Threepeas_BAO_PumCaseRelation', 'getExpertNumberOfCases')) {
      $mainCount = CRM_Threepeas_BAO_PumCaseRelation::getExpertNumberOfCases($row['contact_id']);
    }
    if ($mainCount) {
      $row['main_count'] = CRM_Threepeas_BAO_PumCaseRelation::getExpertNumberOfCases($row['contact_id']);
    } else {
      $row['main_count'] = "";
    }
  }

  /**
   * Method to retrieve the latest case for the contact of case type
   * Advice, RemoteCoaching, Seminar or Business where case status is
   * either Matching, Execution, Debriefing, Preparation or Completed
   *
   * @param int $contactId
   * @return string
   * @throws Exception when no relationship type Expert found
   */
  private function setLastMain($contactId) {
    // build query for civicrm_relationship where type = Expert and case id is not empty
    // joined with case data of the right case type and status
    try {
      $expertRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Expert', 'return' => 'id'));
      $query = "SELECT CONCAT(cc.subject, ' (', status.label, ')')
        FROM civicrm_relationship rel
        JOIN civicrm_case cc ON rel.case_id = cc.id
        LEFT JOIN civicrm_value_main_activity_info main ON rel.case_id = main.entity_id
        LEFT JOIN civicrm_option_value status ON cc.status_id = status.value AND status.option_group_id = %1
        WHERE rel.relationship_type_id = %2 AND rel.contact_id_b = %3 AND cc.is_deleted = %4";
        $params = array(
          1 => array($this->caseStatusOptionGroupId, 'Integer'),
          2 => array($expertRelationshipTypeId, 'Integer'),
          3 => array($contactId, 'Integer'),
          4 => array(0, 'Integer')
        );
      $index = 4;
      // set where clauses for case status
      if (!empty($this->_validCaseStatus)) {
        $statusValues = array();
        foreach ($this->_validCaseStatus as $statusId => $statusName) {
          $index++;
          $params[$index] = array($statusId, 'Integer');
          $statusValues[] = '%' . $index;
        }
        $query .= ' AND cc.status_id IN(' . implode(', ', $statusValues).')';
      }
      // set where clauses for case types
      if (!empty($this->_validCaseTypes)) {
        $typeValues = array();
        foreach ($this->_validCaseTypes as $caseTypeId => $caseTypeName) {
          $index++;
          $params[$index] = array('%' . $caseTypeId . '%', 'String');
          $typeValues[] = 'cc.case_type_id LIKE %' . $index;
        }
        $query .= ' AND ('.implode(' OR ', $typeValues).')';
      }
      $query .= ' ORDER BY main.start_date DESC LIMIT 1';
      return CRM_Core_DAO::singleValueQuery($query, $params);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find a relationship type with name Expert in '.__METHOD__
        .', error from API RelationshipType Getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to check if there are active restrictions for expert
   *
   * @param $contactId
   * @return string
   */
  private function setRestrictions($contactId) {
    try {
      $activities = civicrm_api3('Activity', 'Getcount', array(
        'activity_type_id' => $this->_restrictionsActivityTypeId,
        'target_contact_id' => $contactId,
        'is_current_revision' => 1,
        'is_deleted' => 0,
        'status_id' => $this->_scheduledActivityStatusValue
      ));
      if ($activities > 0) {
        return 'Yes';
      } else {
        return 'No';
      }
    } catch (CiviCRM_API3_Exception $ex) {
      return 'No';
    }
  }

  /**
   * Method to initialize the list of languageLevels
   *
   * @throws Exception when error from API Option Value get
   * @return void
   */
  private function setLanguagesWithLevels() {
    $levelValues = array();
    $levelParams = array('name' => 'level_432_20140806134147', 'return' => 'id');
    try {
      $levelOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $levelParams);
      try {
        $levelOptionValues = civicrm_api3('OptionValue', 'Get',
          array('option_group_id' => $levelOptionGroupId, 'is_active' => 1));
        foreach ($levelOptionValues['values'] as $level) {
          $levelValues[$level['value']] = $level['label'];
        }
      } catch (CiviCRM_API3_Exception $ex) {}
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find an option group for level languages with name
      level_432_20140806134147 in extension nl.pum.findexpert ('.__METHOD__.'), contact your
      system administrator. Error from API OptionGroup Getvalue: '.$ex->getMessage()
        .' with params '.implode('; ', $levelParams));
    }
    $languageParams = array('name' => 'language_20140716104058', 'return' => 'id');
    try {
      $languageOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $languageParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find an option group for expert languages with name
      language_20140716104058 in extension nl.pum.findexpert ('.__METHOD__.'), contact your
      system administrator. Error from API OptionGroup Getvalue: '.$ex->getMessage()
        .' with params '.implode('; ', $languageParams));
    }
    try {
      $languageOptionValues = civicrm_api3('OptionValue', 'Get',
        array('option_group_id' => $languageOptionGroupId, 'is_active' => 1));
      foreach ($languageOptionValues['values'] as $language) {
        $languageLevel = array();
        $languageLevel['language_id'] = $language['value'];
        $languageLevel['language_label'] = $language['label'];
        $languageLevel['level_id'] = 0;
        $languageLevel['level_label'] = 'Any level';
        $this->_languagesWithLevels[] = $languageLevel;
        foreach ($levelValues as $levelId => $levelLabel) {
          $languageLevel = array();
          $languageLevel['language_id'] = $language['value'];
          $languageLevel['language_label'] = $language['label'];
          $languageLevel['level_id'] = $levelId;
          $languageLevel['level_label'] = $levelLabel;
          $this->_languagesWithLevels[] = $languageLevel;
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Method to set the table names of the required custom groups
   *
   * @return void
   */
  private function setRequiredCustomTables() {
    // define custom table names required
    $customGroups = array(
      array('name' => 'Education', 'property' => '_education'),
      array('name' => 'expert_data', 'property' => '_expertData'),
      array('name' => 'Languages', 'property' => '_language'),
      array('name' => 'Workhistory', 'property' => '_workHistory'),
      array('name' => 'prins_history', 'property' => '_prinsHistory'),
    );
    foreach ($customGroups as $customGroupData) {
      try {
        $apiData = civicrm_api3('CustomGroup', 'Getsingle', array('name' => $customGroupData['name']));
        $propertyTableLabel = $customGroupData['property'].'CustomGroupTable';
        $propertyIdLabel = $customGroupData['property'].'CustomGroupId';
        $this->$propertyIdLabel = $apiData['id'];
        $this->$propertyTableLabel = $apiData['table_name'];
      } catch (CiviCRM_API3_Exception $ex) {}
    }
  }

  /**
   * Method to set the column names required
   *
   * @return void
   */
  private function setRequiredCustomColumns() {
    // required columns from education
    $this->_eduFieldOfStudyColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_educationCustomGroupId, 'name' => 'Field_of_study_major',
      'return' => 'column_name'));
    $this->_eduNameInstitutionColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_educationCustomGroupId, 'name' => 'Name_of_Institution',
      'return' => 'column_name'));

    // required columns from expert_data
    $this->_expGenericColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_expertDataCustomGroupId, 'name' => 'generic_skills',
      'return' => 'column_name'));
    $this->_expStatusColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_expertDataCustomGroupId, 'name' => 'expert_status',
      'return' => 'column_name'));
    $this->_expSideActivitiesColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_expertDataCustomGroupId, 'name' => 'side_activities',
      'return' => 'column_name'));

    // required columns for languages
    $this->_llLanguagesColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_languageCustomGroupId, 'name' => 'Language',
      'return' => 'column_name'));
    $this->_llLevelColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_languageCustomGroupId, 'name' => 'Level',
      'return' => 'column_name'));

    // required columns for work history
    $this->_whNameOfOrganizationColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_workHistoryCustomGroupId, 'name' => 'Name_of_Organisation',
      'return' => 'column_name'));
    $this->_whDescriptionColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_workHistoryCustomGroupId, 'name' => 'Description',
      'return' => 'column_name'));
    $this->_whCompetencesUsedColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_workHistoryCustomGroupId, 'name' => 'Competences_used_in_this_job',
      'return' => 'column_name'));
    $this->_whResponsibilitiesColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_workHistoryCustomGroupId, 'name' => 'Responsibilities',
      'return' => 'column_name'));
    $this->_whCountriesVisitedColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_workHistoryCustomGroupId, 'name' => 'Countries_visited_in_relation_to_the_job',
      'return' => 'column_name'));

    $this->_phPrinsHistoryColumn = civicrm_api3('CustomField', 'Getvalue', array(
      'custom_group_id' => $this->_prinsHistoryCustomGroupId, 'name' => 'prins_history',
      'return' => 'column_name'));
  }

  /**
   * Method to set the initial where clauses that apply to each instance
   */
  private function addInitialWhereClauses() {
    $this->_whereClauses[] = 'contact_a.is_deleted = "0"';
    $this->_whereClauses[] = '(contact_a.contact_sub_type LIKE %1)';
    $this->_whereParams[1] = array('%Expert%', 'String');
    $this->_whereClauses[] = '(contact_a.is_deceased = %2)';
    $this->_whereParams[2] = array(0, 'Integer');
    $this->_whereClauses[] = '(exp.'.$this->_expStatusColumn.' NOT IN(%3))';
    $this->_whereParams[3] = array('Exit', 'String');
    $this->_whereClauses[] = '((civicrm_group_contact.group_id = %4 OR civicrm_group_contact.group_id = %5) AND civicrm_group_contact.status = "Added")';
    $this->_whereParams[4] = array($this->_activeExpertGroupId, 'Integer');
    $this->_whereParams[5] = array($this->_candidateExpertGroupId, 'Integer');
    $this->_whereIndex = 5;
  }

  /**
   * Method to set activity type properties
   *
   * @throws Exception when no option group activity type found
   */
  private function setActivityTypes() {
    try {
      $activityTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'activity_type', 'return' => 'id'));
      $restrictionsParams = array(
        'option_group_id' => $activityTypeOptionGroupId,
        'name' => 'Restrictions',
        'return' => 'value'
      );
      try {
        $this->_restrictionsActivityTypeId = civicrm_api3('OptionValue', 'Getvalue', $restrictionsParams);
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_restrictionsActivityTypeId = NULL;
      }
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find option group for activity type in '.__METHOD__.', error from API OptionGroup Getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to set activity status properties
   *
   * @throws Exception when no option group activity status found
   */
  private function setActivityStatus() {
    try {
      $activityStatusOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'activity_status', 'return' => 'id'));
      $scheduledParams = array(
        'option_group_id' => $activityStatusOptionGroupId,
        'name' => 'Scheduled',
        'return' => 'value'
      );
      try {
        $this->_scheduledActivityStatusValue = civicrm_api3('OptionValue', 'Getvalue', $scheduledParams);
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_scheduledActivityStatusValue = NULL;
      }
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find option group for activity status in '.__METHOD__.', error from API OptionGroup Getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to set the valid case types for latest main activity
   *
   * @throws Exception when no option group case type found
   */
  private function setValidCaseTypes() {
    $requiredCaseTypes = array('Advice', 'Business', 'RemoteCoaching', 'Seminar');
    try {
      $caseTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_type', 'return' => 'id'));
      $foundCaseTypes = civicrm_api3('OptionValue', 'Get', array('option_group_id' => $caseTypeOptionGroupId, 'is_active' => 1));
      foreach ($foundCaseTypes['values'] as $caseType) {
        if (in_array($caseType['name'], $requiredCaseTypes)) {
          $this->_validCaseTypes[$caseType['value']] = $caseType['name'];
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find option group for case type in '.__METHOD__.', error from API OptionGroup Getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to set the valid case status for latest main activity
   *
   * @throws Exception when no option group case status found
   */
  private function setValidCaseStatus() {
    $requiredCaseStatus = array('Execution', 'Matching', 'Preparation');
    try {
      $this->caseStatusOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_status', 'return' => 'id'));
      $foundCaseStatus = civicrm_api3('OptionValue', 'Get', array('option_group_id' => $this->caseStatusOptionGroupId, 'is_active' => 1));
      foreach ($foundCaseStatus['values'] as $caseStatus) {
        if (in_array($caseStatus['name'], $requiredCaseStatus)) {
          $this->_validCaseStatus[$caseStatus['value']] = $caseStatus['name'];
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find option group for case status in '.__METHOD__.', error from API OptionGroup Getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to set the group ids. This group ids is used to find only people who
   * are member of one of those groups.
   */
  private function setGroupIds() {
    $this->_activeExpertGroupId = civicrm_api3('Group', 'getvalue', array('name' => 'Active_Expert_48', 'return' => 'id'));
    $this->_candidateExpertGroupId = civicrm_api3('Group', 'getvalue', array('name' => 'Candidate_Expert_51', 'return' => 'id'));
  }
}
