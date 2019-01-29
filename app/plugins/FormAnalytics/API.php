<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\FormAnalytics;

use Piwik\Archive;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugin\ReportsProvider;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgTimeSpent;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateConversion;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateResubmitter;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateStarters;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateSubmitter;
use Piwik\Plugins\FormAnalytics\Dao\LogForm;
use Piwik\Plugins\FormAnalytics\Tracker\RuleMatcher;
use Piwik\Plugins\FormAnalytics\Input\Validator;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Exception;

/**
 * The <a href='http://plugins.matomo.org/FormAnalytics' target='_blank'>Form Analytics</a> API lets you 1) manage forms within Matomo and 2) request all your form analytics reports and metrics.
 *<br/><br/>
 * 1) You can create, update, delete forms, as well as request any form and also <a href='http://matomo.org/faq' target='_blank'>archive</a> them.
 *<br/><br/>
 * 2) Request all metrics and reports about how users interact with your forms:
 * <br/>- Form usage by page URL to see whether the same form is used differently on different pages.
 * <br/>- Entry fields to see where they start filling out your forms.
 * <br/>- Drop off fields to see where your users leave your forms.
 * <br/>- Field timings report to see where your users spent the most time.
 * <br/>- Field size report to see how much text your users type.
 * <br/>- Most corrected fields report to learn more about where users have problems filling out your form.
 * <br/>- Unneeded fields report to see which fields are often left blank.
 * <br/>- Several evolution reports of all metrics to see how your forms perform over time.
 *
 * <br/><br/>And the following metrics:
 * <br/>- How often was a form field interacted with (eg. focus or change).
 * <br/>- Which fields did your visitors interact with first when they started filling out a form.
 * <br/>- Which fields caused a visitor to stop filling out a form (drop offs).
 * <br/>- How often your visitors changed a form field or made amendments.
 * <br/>- How often a field was refocused or corrected (eg usage of backspace or delete key, cursor keys, …).
 * <br/>- How much text they type into each of your text fields.
 * <br/>- Which fields are unneeded and often left blank.
 * <br/>- How long visitors hesitated (waited) before they started changing a field.
 * <br/>- How much time your visitors spent on each field.
 *
 * @method static \Piwik\Plugins\FormAnalytics\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var FormsModel
     */
    private $formsModel;

    /**
     * @var LogForm
     */
    private $logForm;

    /**
     * @var SystemSettings
     */
    private $settings;

    public function __construct(Validator $validator, FormsModel $formsModel, LogForm $logForm, SystemSettings $settings)
    {
        $this->validator = $validator;
        $this->formsModel = $formsModel;
        $this->logForm = $logForm;
        $this->settings = $settings;
    }

    /**
     * Adds a new form to the specified website.
     *
     * By default, Matomo will create a form automatically as soon as it detects a new form and calling this method
     * will not be needed. Disabling the auto-creation of forms can be disabled in "Administration => General Settings".
     *
     * @param int $idSite
     * @param string $name   The name of the form, will be shown in the report for this form
     * @param string $description Optional description for this form, if given will be shown in the report of this form.
     * @param bool $matchFormRules An array of rules / criteria on which of your online forms should be tracked into this form.
     *                             As soon as any of the specified rules match (logical OR) for a form, data will be tracked into
     *                             this form. See the method "FormAnalytics.getAvailableFormRules" for a set of available rules.
     *                             Example: array(array('attribute' => 'form_name', 'pattern' => 'equals', 'value' => 'myformname'))
     * @param bool $matchPageRules An array of rules to optionally restrict the tracking of data for this form only on certain
     *                             pages. Only when any of the specified rules match (logical OR) for a page, data will be tracked into
     *                             this form. See the method "FormAnalytics.getAvailablePageRules" for a set of available rules.
     *                             Example: array(array('attribute' => 'path', 'pattern' => 'equals', 'value' => '/sign-up'))
     * @param bool $conversionRules Matomo differentiates between form submits and form conversions. You can specify a set of rules
     *                              to automatically track a form conversion as soon as visitor views any of the specified pages.
     *                              See the method "FormAnalytics.getAvailablePageRules" for a set of available rules.
     *                              Example: array(array('attribute' => 'path', 'pattern' => 'equals', 'value' => '/sign-up-success'))
     * @return int
     */
    public function addForm($idSite, $name, $description = '', $matchFormRules = false, $matchPageRules = false, $conversionRules = false)
    {
        $this->validator->checkWritePermission($idSite);
        $this->validator->checkSiteExists($idSite);// lets not configure any forms for not yet existing sites

        $autoCreated = false;
        $createdDate = Date::now()->getDatetime();

        $matchFormRules = $this->unsanitizeRules($matchFormRules);
        $matchPageRules = $this->unsanitizeRules($matchPageRules);
        $conversionRules = $this->unsanitizeRules($conversionRules);

        return $this->formsModel->createForm($idSite, $name, $description, $matchFormRules, $matchPageRules, $conversionRules, $createdDate, $autoCreated);
    }

    private function unsanitizeRules($rules)
    {
        if (!empty($rules) && is_array($rules)) {
            foreach ($rules as $index => $rule) {
                if (!empty($rule['value']) && is_string($rule['value'])) {
                    $rules[$index]['value'] = Common::unsanitizeInputValue($rule['value']);
                }
            }
        }

        return $rules;
    }

    /**
     * Updates an existing form.
     *
     * @param int $idSite
     * @param int $idForm
     * @param string $name   The name of the form, will be shown in the report for this form
     * @param string $description Optional description for this form, if given will be shown in the report of this form.
     * @param bool $matchFormRules An array of rules / criteria on which of your online forms should be tracked into this form.
     *                             As soon as any of the specified rules match (logical OR) for a form, data will be tracked into
     *                             this form. See the method "FormAnalytics.getAvailableFormRules" for a set of available rules.
     *                             Example: array(array('attribute' => 'form_name', 'pattern' => 'equals', 'value' => 'myformname'))
     * @param bool $matchPageRules An array of rules to optionally restrict the tracking of data for this form only on certain
     *                             pages. Only when any of the specified rules match (logical OR) for a page, data will be tracked into
     *                             this form. See the method "FormAnalytics.getAvailablePageRules" for a set of available rules.
     *                             Example: array(array('attribute' => 'path', 'pattern' => 'equals', 'value' => '/sign-up'))
     * @param bool $conversionRules Matomo differentiates between form submits and form conversions. You can specify a set of rules
     *                              to automatically track a form conversion as soon as visitor views any of the specified pages.
     *                              See the method "FormAnalytics.getAvailablePageRules" for a set of available rules.
     *                              Example: array(array('attribute' => 'path', 'pattern' => 'equals', 'value' => '/sign-up-success'))
     */
    public function updateForm($idSite, $idForm, $name, $description = '', $matchFormRules = false, $matchPageRules = false, $conversionRules = false)
    {
        $this->validator->checkWritePermission($idSite);
        $this->validator->checkSiteExists($idSite);// lets not configure any forms for not yet existing sites
        $this->formsModel->checkFormExists($idSite, $idForm);

        $updatedDate = Date::now()->getDatetime();

        $matchFormRules = $this->unsanitizeRules($matchFormRules);
        $matchPageRules = $this->unsanitizeRules($matchPageRules);
        $conversionRules = $this->unsanitizeRules($conversionRules);

        $this->formsModel->updateForm($idSite, $idForm, $name, $description, $matchFormRules, $matchPageRules, $conversionRules, $updatedDate);
    }

    /**
     * Get a specific form by its ID.
     *
     * @param int $idSite
     * @param int $idForm
     * @return array|false
     */
    public function getForm($idSite, $idForm)
    {
        $this->validator->checkReportViewPermission($idSite);
        $this->validator->checkSiteExists($idSite);// lets not return any forms of no longer existing sites
        $this->formsModel->checkFormExists($idSite, $idForm);

        return $this->formsModel->getForm($idSite, $idForm);
    }

    /**
     * Get all forms for a specific website or app.
     *
     * It will return running as well as currently archived forms.
     *
     * @param int $idSite
     * @return array
     */
    public function getForms($idSite)
    {
        $this->validator->checkReportViewPermission($idSite);

        return $this->formsModel->getAllFormsForSite($idSite);
    }

    /**
     * Get a list of forms by status(es). To get a list of available statuses call "FormAnalytics.getAvailableStatuses".
     *
     * @param int $idSite
     * @param string|array $statuses
     * @return array
     * @throws Exception If no status given.
     */
    public function getFormsByStatuses($idSite, $statuses)
    {
        $this->validator->checkWritePermission($idSite);

        if (empty($statuses)) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorXNotProvided', 'status'));
        }

        return $this->formsModel->getFormsByStatuses($idSite, $statuses);
    }

    /**
     * Deletes the given form.
     *
     * When a form is deleted, the report will be no longer available in the API and tracked data for this
     * form might be removed at some point by the system. Be aware that when the auto-creation of forms is enabled,
     * and Matomo detects this form again, a new form will be created again automatically. If you do not want this
     * behaviour, archive the form instead.
     *
     * @param int $idSite
     * @param int $idForm
     */
    public function deleteForm($idSite, $idForm)
    {
        $this->validator->checkWritePermission($idSite);

        // we do only a soft delete by default
        $this->formsModel->deactivateForm($idSite, $idForm);
    }

    /**
     * Archives the given form.
     *
     * When a form is archived, no new data will be tracked for this form anymore and reports for this form will be no
     * longer available. When Matomo discovers the same form again, it will not create a new form automatically and
     * previously tracked data will not be deleted for this form. This allows you to temporarily pause the tracking
     * for a specific form and to keep the data for later purposes.
     *
     * @param int $idSite
     * @param int $idForm
     */
    public function archiveForm($idSite, $idForm)
    {
        $this->validator->checkWritePermission($idSite);

        $this->formsModel->archiveForm($idSite, $idForm);
    }

    /**
     * Get a form overview report.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idForm  If given, will return form overview metrics for the given form, otherwise it will return the
     *                      metrics for the usage of all forms.
     * @param bool|string $segment
     * @return DataTable
     */
    public function get($idSite, $period, $date, $idForm = false, $segment = false, $columns = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        if (!empty($idForm)) {
            $this->formsModel->checkFormExists($idSite, $idForm);
        }

        $requestedColumns = Piwik::getArrayFromApiParameter($columns);

        // we make sure to fetch only requested metrics, if only some were requested for faster performance
        $report = ReportsProvider::factory('FormAnalytics', 'get');
        $columns = $report->getMetricsRequiredForReport(null, $requestedColumns);

        $recordNames = Archiver::getNumericFormRecordNames($columns, $idForm);

        $archive = Archive::build($idSite, $period, $date, $segment);
        $table = $archive->getDataTableFromNumeric($recordNames);

        $columnMapping = array();
        foreach ($recordNames as $recordName) {
            $columnMapping[$recordName] = Archiver::getMetricNameFromNumericRecordName($recordName, $idForm);
        }

        $table->filter('ReplaceColumnNames', array($columnMapping));

        if (!empty($requestedColumns)) {
            $table->queueFilter('ColumnDelete', array($columnsToRemove = array(), $requestedColumns));
        }

        return $table;
    }

    /**
     * Get the entry fields report.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int    $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getEntryFields($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);
        $this->formsModel->checkFormExists($idSite, $idForm);

        $recordName = Archiver::completeRecordName(Archiver::FORM_ENTRY_FIELDS_RECORD, $idForm);
        $table = $this->getDataTable($recordName, $idSite, $period, $date, $segment);

        $form = $this->formsModel->getForm($idSite, $idForm);
        $table->queueFilter('Piwik\Plugins\FormAnalytics\DataTable\Filter\ReplaceFormFieldLabel', array($form));

        return $table;
    }

    /**
     * Get the drop off fields report.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int    $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getDropOffFields($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);
        $this->formsModel->checkFormExists($idSite, $idForm);

        $recordName = Archiver::completeRecordName(Archiver::FORM_DROP_OFF_RECORD, $idForm);
        $table = $this->getDataTable($recordName, $idSite, $period, $date, $segment);

        $form = $this->formsModel->getForm($idSite, $idForm);
        $table->queueFilter('Piwik\Plugins\FormAnalytics\DataTable\Filter\ReplaceFormFieldLabel', array($form));

        return $table;
    }

    /**
     * Get form overview metrics for each page. This is useful when your form is embedded on several pages and you want
     * to see how each form performs on the different pages.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getPageUrls($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);
        $this->formsModel->checkFormExists($idSite, $idForm);

        $recordName = Archiver::completeRecordName(Archiver::FORM_PAGE_URLS_RECORD, $idForm);
        $table = $this->getDataTable($recordName, $idSite, $period, $date, $segment);

        $table->filter(function (DataTable $table) {
            foreach ($table->getRowsWithoutSummaryRow() as $row) {
                $row->setMetadata('url', $row->getColumn('label'));
            }
        });

        return $table;
    }

    /**
     * Get the field timings report to see how long visitors spent on each field or to see for how long they waited
     * before they filled out a form field.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getFieldTimings($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $table = $this->getFormFieldsReport($idSite, $period, $date, $idForm, $segment, __FUNCTION__);

        return $table;
    }

    /**
     * Get the field size report to see how many characters visitors typed into your text fields.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getFieldSize($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $table = $this->getFormFieldsReport($idSite, $period, $date, $idForm, $segment, __FUNCTION__);

        // we make sure to remove fields where nothing has been entered. Now we could also remove only non-text fields,
        // but this would mean we would need to maintain a list of which text fields possibly exist etc.
        $table->filter(function (DataTable $table) {
            $idsToDelete = array();
            foreach ($table->getRowsWithoutSummaryRow() as $id => $row) {
                $size = $row->getColumn(Metrics::SUM_FIELD_FIELDSIZE);
                if ($size <= 0) {
                    $idsToDelete[] = $id;
                }
            }
            $table->deleteRows($idsToDelete);
        });

        return $table;
    }

    /**
     * Get the unneeded fields report to see which fields were often left blank when your visitors submitted your forms.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getUneededFields($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $table = $this->getFormFieldsReport($idSite, $period, $date, $idForm, $segment, __FUNCTION__);

        return $table;
    }

    /**
     * Get the most used fields to see which fields were most interacted and changed.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getMostUsedFields($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $table = $this->getFormFieldsReport($idSite, $period, $date, $idForm, $segment, __FUNCTION__);

        return $table;
    }

    /**
     * Get the field corrections report to see which fields were corrected the most. For example backspaces, amendmends,
     * refocuses, usage of cursors keys, etc.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idForm
     * @param bool|string $segment
     * @return DataTable
     */
    public function getFieldCorrections($idSite, $period, $date, $idForm, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $table = $this->getFormFieldsReport($idSite, $period, $date, $idForm, $segment, __FUNCTION__);

        return $table;
    }

    /**
     * Lets you update known form fields to set a display name.
     *
     * @param int $idSite
     * @param int $idForm
     * @param array $fields   an array of fields where you want to update the display name eg array(array('name' => 'input1', 'displayName' => 'email))
     */
    public function updateFormFieldDisplayName($idSite, $idForm, $fields = array())
    {
        $this->validator->checkWritePermission($idSite);
        $this->formsModel->checkFormExists($idSite, $idForm);

        $form = $this->formsModel->getForm($idSite, $idForm);

        if (!empty($fields) && !empty($form['fields']) && is_array($form['fields'])) {

            foreach ($form['fields'] as &$existingField) {
                foreach ($fields as $updateField) {
                    if (isset($updateField['name'])
                        && isset($existingField['name'])
                        && $updateField['name'] === $existingField['name']) {
                        if (isset($updateField['displayName'])) {
                            $existingField['displayName'] = $updateField['displayName'];
                        }
                    }
                }
            }

            $this->formsModel->updateFormFields($idSite, $idForm, $form['fields']);
        }
    }

    /**
     * This method returns simple counters, for a given website ID, for visits over the last N minutes to see
     * how your forms were doing in real time.
     *
     * @param $idSite
     * @param int $lastMinutes
     * @param bool|string $segment
     * @return DataTable\Simple
     */
    public function getCounters($idSite, $lastMinutes, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $lastMinutes = (int) $lastMinutes;
        $serverTime = $this->getServerTimeForXMinutesAgo($lastMinutes);

        $counters = $this->logForm->getCounters($idSite, $serverTime, $segment);
        foreach ($counters as $index => $value) {
            if (!isset($value)) {
                $counters[$index] = 0;
            }
        }

        $table = new DataTable\Simple();
        $table->disableFilter('AddColumnsProcessedMetrics');
        $table->addRowsFromArray($counters);
        $table->setMetadata(DataTable::EXTRA_PROCESSED_METRICS_METADATA_NAME, array(
            new FormAvgTimeSpent(),
            new FormRateStarters(),
            new FormRateSubmitter(),
            new FormRateResubmitter(),
            new FormRateConversion(),
        ));

        return $table;
    }

    /**
     * This methods returns the currently most popular forms, for a given website ID, for visits over the last N minutes
     * to see which forms are performing best in real time.
     *
     * @param int $idSite
     * @param int $lastMinutes
     * @param int $filter_limit
     * @param bool|string $segment
     * @return DataTable
     */
    public function getCurrentMostPopularForms($idSite, $lastMinutes, $filter_limit = 5, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $lastMinutes = (int) $lastMinutes;
        $serverTime = $this->getServerTimeForXMinutesAgo($lastMinutes);

        $rows = $this->logForm->getCurrentMostPopularForms($idSite, $serverTime, $filter_limit, $segment);

        $table = DataTable::makeFromSimpleArray($rows);
        $table->disableFilter('AddColumnsProcessedMetrics');

        return $table;
    }

    /**
     * Returns settings about the auto creation of forms.
     *
     * @param int $idSite
     * @return array
     */
    public function getAutoCreationSettings($idSite)
    {
        $this->validator->checkWritePermission($idSite);

        $value = $this->settings->autoCreateForm->getValue();
        $numCreated = $this->formsModel->getNumFormsAutoCreated($idSite);

        $message = '';
        if ($value === SystemSettings::FORM_CREATION_UP_TO_10 || $value === SystemSettings::FORM_CREATION_UP_TO_50) {
            $limit = 10;
            if ($value === SystemSettings::FORM_CREATION_UP_TO_50) {
                $limit = 50;
            }

            $message = Piwik::translate('FormAnalytics_CreateFormsConfiguredLimited', $limit) . ' ';
            if ($numCreated >= $limit) {
                $message .= Piwik::translate('FormAnalytics_CreateFormsConfiguredLimitedReached', $numCreated);
            } else {
                $message .= Piwik::translate('FormAnalytics_CreateFormsConfiguredLimitedUnreached', $numCreated);
            }

        } elseif ($value === SystemSettings::FORM_CREATION_UNLIMITED) {
            $message = Piwik::translate('FormAnalytics_CreateFormsConfiguredUnlimited');
        } elseif ($value === SystemSettings::FORM_CREATION_DISABLED) {
            $message = Piwik::translate('FormAnalytics_CreateFormsConfiguredDisabled');
        }

        if (!empty($message)) {
            $message .= ' ' . Piwik::translate('FormAnalytics_CreateFormsHowToChange');
        }

        return array(
            'message' => $message
        );
    }

    private function getServerTimeForXMinutesAgo($lastMinutes)
    {
        // we do not use time() directly because this way we can mock time() in tests
        $time = Date::now()->getTimestampUTC();

        return Date::factory($time - ($lastMinutes * 60))->getDatetime();
    }

    private function getFormFieldsReport($idSite, $period, $date, $idForm, $segment, $reportName)
    {
        $this->formsModel->checkFormExists($idSite, $idForm);
        $this->validator->checkSiteExists($idSite);// lets not return any report for no longer existing site

        $form = $this->formsModel->getForm($idSite, $idForm);

        $recordName = Archiver::completeRecordName(Archiver::FORM_FIELDS_RECORD, $idForm);
        $table = $this->getDataTable($recordName, $idSite, $period, $date, $segment);

        $table->queueFilter('Piwik\Plugins\FormAnalytics\DataTable\Filter\ReplaceFormFieldLabel', array($form));

        $table->queueFilter(function (DataTable $table) use ($reportName) {
            $report = ReportsProvider::factory('FormAnalytics', $reportName);
            $showColumns = $report->getAllMetrics();
            $table->filter('ColumnDelete', array($hideColumns = array(), $showColumns));
        });

        return $table;
    }

    /**
     * @param $recordName
     * @param $idSite
     * @param $period
     * @param $date
     * @param $segment
     * @param $expanded
     * @param $idSubtable
     * @return DataTable
     */
    private function getDataTable($recordName, $idSite, $period, $date, $segment)
    {
        $table = Archive::createDataTableFromArchive($recordName, $idSite, $period, $date, $segment);
        $table->disableFilter('AddColumnsProcessedMetrics');

        return $table;
    }

    /**
     * Get a list of valid forms statuses (eg "running", "archived", ...)
     *
     * @return array
     */
    public function getAvailableStatuses()
    {
        Piwik::checkUserHasSomeAdminAccess();

        return $this->formsModel->getValidStatuses();
    }

    /**
     * Get a list of available form rule patterns that can be used to configure a form.
     *
     * @return array
     * @throws Exception
     */
    public function getAvailableFormRules()
    {
        Piwik::checkUserHasSomeAdminAccess();

        return RuleMatcher::getAvailableFormRules();
    }

    /**
     * Get a list of available conversion rule patterns that can be used to configure a form.
     * @return array
     * @throws Exception
     */
    public function getAvailablePageRules()
    {
        Piwik::checkUserHasSomeAdminAccess();

        return RuleMatcher::getAvailablePageRules();
    }

}
