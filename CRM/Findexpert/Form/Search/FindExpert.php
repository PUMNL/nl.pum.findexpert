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

  private $_languagesWithLevels = array();

  // custom table names needed
  private $_workHistoryCustomGroupTable = NULL;
  private $_educationCustomGroupTable = NULL;
  private $_expertDataCustomGroupTable = NULL;
  private $_languageCustomGroupTable = NULL;
  private $_workHistoryCustomGroupId = NULL;
  private $_educationCustomGroupId = NULL;
  private $_expertDataCustomGroupId = NULL;
  private $_languageCustomGroupId = NULL;

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
  private $_eduNameInstitutionColumn = NULL;
  private $_eduFieldOfStudyColumn = NULL;

  // properties for clauses and params
  private $_whereClauses = array();
  private $_whereParams = array();
  private $_whereIndex = NULL;

  /**
   * CRM_Findexpert_Form_Search_FindExpert constructor.
   * @param $formValues
   * @throws Exception when unable to find option groups with API
   */
  function __construct(&$formValues) {
    $this->setLanguagesWithLevels();
    $this->setRequiredCustomTables();
    $this->setRequiredCustomColumns();

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
      array('id' => 'expertise_id', 'multiple' => 'multiple', 'title' => ts('- select -'))
    );

    $genericSkillsList = $this->getGenericSkillsList();
    $form->add('select', 'generic_id', ts('Generic Skill(s)'), $genericSkillsList, FALSE,
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
    $sectors = civicrm_api3('Segment', 'Get', array('parent_id' => 'null'));
    foreach ($sectors['values'] as $sectorId => $sector) {
      $result[$sectorId] = $sector['label'];
    }
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
    $areas = civicrm_api3('Segment', 'Get', array());
    foreach ($areas['values'] as $areaId => $area) {
      if (!empty($area['parent_id'])) {
        $result[$areaId] = $area['label'];
      }
    }
    return $result;
  }

  /**
   * Method to get generic skills
   *
   * @return array
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
    $result = array();
    try {
      $optionValues = civicrm_api3('OptionValue', 'Get',
        array('option_group_id' => $genericSkillsOptionGroupId, 'is_active' => 1));
      foreach ($optionValues['values'] as $genericSkill) {
        $result[$genericSkill['value']] = $genericSkill['label'];
      }
    } catch (CiviCRM_API3_Exception $ex) {}
    return $result;
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
   * Get a list of summary data points
   *
   * @return mixed; NULL or array with keys:
   *  - summary: string
   *  - total: numeric
   */
  function summary() {
    return NULL;
    // return array(
    //   'summary' => 'This is a summary',
    //   'total' => 50.0,
    // );
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
    main.main_sector, exp.".$this->_expStatusColumn." AS expert_status";
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string, sql fragment with FROM and JOIN clauses
   */
  function from() {
    return "FROM civicrm_contact contact_a
    LEFT JOIN pum_expert_main_sector main ON contact_a.id = main.contact_id
    LEFT JOIN pum_expert_other_sector other ON contact_a.id = other.contact_id
    LEFT JOIN pum_expert_areas_expertise areas ON contact_a.id = areas.contact_id
    LEFT JOIN ".$this->_educationCustomGroupTable." edu ON contact_a.id = edu.entity_id
    LEFT JOIN ".$this->_expertDataCustomGroupTable." exp ON contact_a.id = exp.entity_id
    LEFT JOIN ".$this->_languageCustomGroupTable." ll ON contact_a.id = ll.entity_id
    LEFT JOIN ".$this->_workHistoryCustomGroupTable." wh ON contact_a.id = wh.entity_id";
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
    $this->setInitialWhereClauses();
    // sector clauses if selected
    $this->setSectorWhereClauses();
    // area of expertise clauses if selected
    //$this->setExpertiseWhereClauses();


    //CRM_Core_Error::debug('where clauses', $this->_whereClauses);
    //CRM_Core_Error::debug('where params', $this->_whereParams);
    //exit();

    if (!empty($this->_whereClauses)) {
      $where = implode(' AND ', $this->_whereClauses);
    }
    return $this->whereClause($where, $this->_whereParams);
  }

  /**
   * Method to set the sector where clauses
   */
  private function setSectorWhereClauses() {
    if (isset($this->_formValues['sector_id'])) {
      $sectorIds = array();
      foreach ($this->_formValues['sector_id'] as $sectorId) {
        $this->_whereIndex++;
        $sectorIds[$this->_whereIndex] = $sectorId;
        $this->_whereParams[$this->_whereIndex] = array($sectorId, 'Integer');
      }
      if (!empty($sectorIds)) {
        $this->_whereClauses[] = 'main.segment_id IN('.implode(', ', $sectorIds).') OR other.segment_id IN('.implode(', ', $sectorIds).')';
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
    // todo : add number of main, restrictions yes/no and latest main
    //CRM_Core_Error::debug('row', $row);
    //exit();
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
  }

  /**
   * Method to set the initial where clauses that apply to each instance
   */
  private function setInitialWhereClauses() {
    $this->_whereClauses[] = "contact_a.contact_sub_type LIKE %1";
    $this->_whereParams[1] = array('%Expert%', 'String');
    $this->_whereClauses[] = "contact_a.is_deceased = %2";
    $this->_whereParams[2] = array(0, 'Integer');
    $this->_whereIndex = 2;
  }
}
