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

use Piwik\Category\Subcategory;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\CoreHome\SystemSummary;
use Piwik\Plugins\FormAnalytics\Actions\ActionForm;
use Piwik\Plugins\FormAnalytics\Dao\LogFormPage;
use Piwik\Plugins\FormAnalytics\Dao\SiteForm;
use Piwik\Plugins\FormAnalytics\Dao\LogForm;
use Piwik\Plugins\FormAnalytics\Dao\LogFormField;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;

class FormAnalytics extends Plugin
{
    const TRACKER_CACHE_RUNNING_FORMS_KEY = 'forms';
    const TRACKER_CACHE_ALL_FORMS_KEY = 'forms_all';
    const TRACKER_CACHE_NUM_AUTO_CREATED = 'num_forms_auto_created';

    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Category.addSubcategories' => 'addSubcategories',
            'Tracker.Cache.getSiteAttributes'  => 'addSiteForms',
            'SitesManager.deleteSite.end' => 'onDeleteSite',
            'Segment.addSegments' => 'addSegments',
            'Metrics.getDefaultMetricTranslations' => 'getDefaultMetricTranslations',
            'System.addSystemSummaryItems' => 'addSystemSummaryItems',
            'Metrics.getDefaultMetricDocumentationTranslations' => 'getDefaultMetricDocumentationTranslations',
            'Actions.addActionTypes' => 'addActionTypes'
        );
    }

    public function addSystemSummaryItems(&$systemSummary)
    {
        $dao = $this->getSiteFormsDao();
        $numForms = $dao->getNumFormsTotal();

        $systemSummary[] = new SystemSummary\Item($key = 'forms', Piwik::translate('FormAnalytics_NForms', $numForms), $value = null, array('module' => 'FormAnalytics', 'action' => 'manage'), $icon = 'icon-form', $order = 8);
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    public function install()
    {
        $form = new SiteForm();
        $form->install();

        $logForm = new LogForm();
        $logForm->install();

        $logForm = new LogFormPage();
        $logForm->install();

        $logFormField = new LogFormField();
        $logFormField->install();
    }

    public function uninstall()
    {
        $form = new SiteForm();
        $form->uninstall();

        $logForm = new LogForm();
        $logForm->uninstall();

        $logForm = new LogFormPage();
        $logForm->uninstall();

        $logFormField = new LogFormField();
        $logFormField->uninstall();
    }

    public function onDeleteSite($idSite)
    {
        $formsModel = $this->getFormsModel();
        $formsModel->deactivateFormsForSite($idSite);
    }

    public function getDefaultMetricTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsTranslations());
    }

    public function getDefaultMetricDocumentationTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsDocumentationTranslations());
    }

    public function addSiteForms(&$content, $idSite)
    {
        // we cache running and created forms as a created one can become running while being cached
        $formsModel = $this->getFormsModel();
        $content[self::TRACKER_CACHE_RUNNING_FORMS_KEY] = $formsModel->getFormsByStatuses($idSite, FormsModel::STATUS_RUNNING);
        $content[self::TRACKER_CACHE_ALL_FORMS_KEY] = $formsModel->getFormsByStatuses($idSite, array(FormsModel::STATUS_RUNNING, FormsModel::STATUS_ARCHIVED));
        $content[self::TRACKER_CACHE_NUM_AUTO_CREATED] = $formsModel->getNumFormsAutoCreated($idSite);
    }

    private function getSiteFormsDao()
    {
        return StaticContainer::get('Piwik\Plugins\FormAnalytics\Dao\SiteForm');
    }

    private function getFormsModel()
    {
        return StaticContainer::get('Piwik\Plugins\FormAnalytics\Model\FormsModel');
    }

    public function addSubcategories(&$subcategories)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite)) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (empty($idSite)) {
                return;
            }
        }

        $forms = $this->getFormsModel()->getFormsByStatuses($idSite, FormsModel::STATUS_RUNNING);

        $order = 20;
        foreach ($forms as $form) {
            $subcategory = new Subcategory();
            $subcategory->setName($form['name']);
            $subcategory->setCategoryId('FormAnalytics_Forms');
            $subcategory->setId($form['idsiteform']);
            $subcategory->setOrder($order++);
            $subcategories[] = $subcategory;
        }
    }

    public function getClientSideTranslationKeys(&$result)
    {
        $result[] = 'General_Done';
        $result[] = 'General_Yes';
        $result[] = 'General_No';
        $result[] = 'General_Add';
        $result[] = 'General_Remove';
        $result[] = 'General_Description';
        $result[] = 'General_Cancel';
        $result[] = 'General_Name';
        $result[] = 'General_Ok';
        $result[] = 'General_Id';
        $result[] = 'FormAnalytics_ManageForms';
        $result[] = 'FormAnalytics_ErrorFormRuleRequired';
        $result[] = 'General_LoadingData';
        $result[] = 'FormAnalytics_FieldDescriptionPlaceholder';
        $result[] = 'FormAnalytics_FieldNamePlaceholder';
        $result[] = 'CoreUpdater_UpdateTitle';
        $result[] = 'FormAnalytics_Filter';
        $result[] = 'FormAnalytics_ConversionCriteria';
        $result[] = 'FormAnalytics_UpdatingData';
        $result[] = 'FormAnalytics_FormSetupFormRules';
        $result[] = 'FormAnalytics_FormSetupFormRulesHelp';
        $result[] = 'FormAnalytics_ComparisonsCaseInsensitive';
        $result[] = 'FormAnalytics_ComparisonsIgnoredValues';
        $result[] = 'FormAnalytics_SettingAllowCreationByPageOnlyDescription';
        $result[] = 'FormAnalytics_SettingAllowCreationByPageOnly';
        $result[] = 'FormAnalytics_FormSetupPageRules';
        $result[] = 'FormAnalytics_FormSetupPageRulesHelp';
        $result[] = 'FormAnalytics_FormSetupConversionRules';
        $result[] = 'FormAnalytics_FormSetupConversionRulesHelp1';
        $result[] = 'FormAnalytics_FormSetupConversionRulesHelp2';
        $result[] = 'FormAnalytics_ArchiveReportInfo';
        $result[] = 'FormAnalytics_Status';
        $result[] = 'FormAnalytics_NoFormsFound';
        $result[] = 'FormAnalytics_DeleteFormInfo';
        $result[] = 'FormAnalytics_ViewReportInfo';
        $result[] = 'FormAnalytics_FormNameHelp';
        $result[] = 'FormAnalytics_FormDescriptionHelp';
        $result[] = 'FormAnalytics_CreateNewForm';
        $result[] = 'FormAnalytics_ErrorXNotProvided';
        $result[] = 'FormAnalytics_EditForm';
        $result[] = 'FormAnalytics_FormCreated';
        $result[] = 'FormAnalytics_FormUpdated';
        $result[] = 'FormAnalytics_ManageFormsIntroduction';
        $result[] = 'FormAnalytics_DeleteFormConfirm';
        $result[] = 'FormAnalytics_ArchiveReportConfirm';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/FormAnalytics/angularjs/manage/edit.directive.less";
        $stylesheets[] = "plugins/FormAnalytics/angularjs/manage/list.directive.less";
        $stylesheets[] = "plugins/FormAnalytics/stylesheets/report.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/FormAnalytics/angularjs/common/filters/truncateText.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/manage/edit.controller.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/manage/edit.directive.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/manage/list.controller.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/manage/list.directive.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/manage/manage.controller.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/manage/manage.directive.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/manage/model.js";
        $jsFiles[] = "plugins/FormAnalytics/javascripts/liveFormDataTable.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/common/directives/form-page-link.js";
        $jsFiles[] = "plugins/FormAnalytics/angularjs/formfields/formfields.controller.js";
    }

    public function addSegments(&$segments)
    {
        $forms = $this->getFormsModel();

        $segment = new Segment();
        $segment->setSegment(Segment::FORM_NAME_SEGMENT);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName('FormAnalytics_SegmentFormName');
        $segment->setAcceptedValues(Piwik::translate('FormAnalytics_SegmentFormNameDescription'));
        $segment->setSqlSegment('log_form.idsiteform');
        $segment->setSqlFilter('\\Piwik\\Plugins\\FormAnalytics\\Segment::getIdByName');
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) use ($forms) {
            $forms = $forms->getFormsByStatuses($idSite, FormsModel::STATUS_RUNNING);
            $names = array();

            foreach ($forms as $form) {
                $names[] = $form['name'];
            }

            return array_slice($names, 0, $maxValuesToReturn);
        });

        $segments[] = $segment;

        $segment = new Segment();
        $segment->setSegment(Segment::FORM_NUM_STARTS_SEGMENT);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName('FormAnalytics_SegmentFormStarts');
        $segment->setAcceptedValues(Piwik::translate('FormAnalytics_SegmentFormStartsDescription'));
        $segment->setSqlSegment('log_form.num_starts');
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) use ($forms) {

            return range(1, $maxValuesToReturn);
        });

        $segments[] = $segment;

        $segment = new Segment();
        $segment->setSegment(Segment::FORM_NUM_SUBMISSIONS_SEGMENT);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName('FormAnalytics_SegmentFormSubmissions');
        $segment->setAcceptedValues(Piwik::translate('FormAnalytics_SegmentFormSubmissionsDescription'));
        $segment->setSqlSegment('log_form.num_submissions');
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) use ($forms) {

            return range(1, $maxValuesToReturn);
        });

        $segments[] = $segment;

        $segment = new Segment();
        $segment->setSegment(Segment::FORM_CONVERTED_SEGMENT);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName('FormAnalytics_SegmentFormConverted');
        $segment->setAcceptedValues(Piwik::translate('FormAnalytics_SegmentFormConvertedDescription'));
        $segment->setSqlSegment('log_form.converted');
        $segment->setSqlFilterValue(function ($value) {
            if (in_array($value, array('0', '1'))) {
                return (int) $value;
            }

            $value = strtolower($value);

            if ($value === 'yes') {
                return 1;
            }

            if ($value === 'no') {
                return 0;
            }

            return $value;
        });
        $segment->setSuggestedValuesCallback(function () use ($forms) {
            // we cannot use translation for yes / no otherwise it would fail depending on the users langauge
            return array('0', '1', 'Yes', 'No');
        });

        $segments[] = $segment;

        $segment = new Segment();
        $segment->setSegment(Segment::FORM_SPENT_TIME_SEGMENT);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName('FormAnalytics_SegmentFormSpentTime');
        $segment->setAcceptedValues(Piwik::translate('FormAnalytics_SegmentFormSpentTimeDescription'));
        $segment->setSqlSegment('log_form.time_spent');
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) use ($forms) {
            $values = range(0, 3000, 100);

            return array_slice($values, 0, $maxValuesToReturn);
        });

        $segments[] = $segment;
    }

    public function addActionTypes(&$types)
     {
         $types[] = [
             'id' => ActionForm::TYPE_FORM,
             'name' => 'form'
         ];
     }
}
