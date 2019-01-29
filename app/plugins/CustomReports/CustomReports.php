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

namespace Piwik\Plugins\CustomReports;

use Piwik\API\Request;
use Piwik\Category\Subcategory;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\CustomReports\Dao\CustomReportsDao;
use Piwik\Plugins\CoreHome\SystemSummary;
use Piwik\Plugins\CustomReports\Input\Dimensions;
use Piwik\Plugins\CustomReports\Input\Metrics;
use Piwik\Plugins\CustomReports\Input\ReportType;

class CustomReports extends \Piwik\Plugin
{
    const MENU_ICON = 'icon-business';

    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Report.addReports' => 'addCustomReports',
            'SitesManager.deleteSite.end' => 'onDeleteSite',
            'System.addSystemSummaryItems' => 'addSystemSummaryItems',
            'Category.addSubcategories' => 'addSubcategories',
            'CustomReports.buildPreviewReport' => 'buildPreviewReport',
        );
    }

    public function buildPreviewReport(&$report)
    {
        $dimensions = Common::getRequestVar('dimensions', '', 'string');
        $metrics = Common::getRequestVar('metrics', null, 'string');
        $idSite = Common::getRequestVar('idSite', 0, 'string');

        if (!empty($dimensions)) {
            $dimensions = array_unique(explode(',' , $dimensions));
            $dimensionsCheck = new Dimensions($dimensions, $idSite);
            $dimensionsCheck->check();
        } else {
            $dimensions = array();
        }

        $metrics = array_unique(explode(',' , $metrics));
        $metricsCheck = new Metrics($metrics, $idSite);
        $metricsCheck->check();

        $reportType = Common::getRequestVar('report_type', null, 'string');
        $segment = Request::getRawSegmentFromRequest();

        $type = new ReportType($reportType);
        $type->check();

        $thirdDimensionTruncated = false;
        if (count($dimensions) > 2) {
            $thirdDimensionTruncated = true;
            $dimensions = array_slice($dimensions, 0, 2);
        }

        $report = array(
            'idcustomreport' => 0,
            'report_type' => $reportType,
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'segment_filter' => $segment,
            'category' => array('id' => CustomReportsDao::DEFAULT_CATEGORY,
                                'name' => Piwik::translate(CustomReportsDao::DEFAULT_CATEGORY),
                                'order' => 999,
                                'icon' => ''),
            'subcategory' => null,
            'name' => Piwik::translate('CustomReports_Preview'),
            'description' => null,
            'dimensionsTruncated' => $thirdDimensionTruncated,
            'created_date' => Date::now()->getDatetime(),
            'updated_date' => Date::now()->getDatetime(),
        );
    }

    public function addSystemSummaryItems(&$systemSummary)
    {
        $dao = $this->getDao();
        $numForms = $dao->getNumReportsTotal();

        $systemSummary[] = new SystemSummary\Item($key = 'customreports', Piwik::translate('CustomReports_NCustomReports', $numForms), $value = null, array('module' => 'CustomReports', 'action' => 'manage'), self::MENU_ICON, $order = 8);
    }

    public function install()
    {
        $dao = new CustomReportsDao();
        $dao->install();

        $config = new Configuration();
        $config->install();
    }

    public function uninstall()
    {
        $dao = new CustomReportsDao();
        $dao->uninstall();

        $config = new Configuration();
        $config->uninstall();
    }

    private function getModel()
    {
        return StaticContainer::get('Piwik\Plugins\CustomReports\Model\CustomReportsModel');
    }

    private function getDao()
    {
        return StaticContainer::get('Piwik\Plugins\CustomReports\Dao\CustomReportsDao');
    }

    public function onDeleteSite($idSite)
    {
        $model = $this->getModel();
        $model->deactivateReportsForSite($idSite);
    }

    public function addCustomReports(&$instances)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite) || $idSite < 1) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (empty($idSite) || $idSite < 1) {
                return;
            }
        }

        $dao = $this->getModel();
        $reports = $dao->getAllCustomReportsForSite($idSite);

        if (!empty($idSite)
            && (   Common::getRequestVar('actionToWidgetize', '', 'string') === 'previewReport'
                || Common::getRequestVar('action', '', 'string') === 'previewReport')
            && (   Common::getRequestVar('moduleToWidgetize', '', 'string') === 'CustomReports'
                || Common::getRequestVar('module', '', 'string') === 'CustomReports')) {

            $instance = new GetCustomReport();
            $instances[] = $instance;
            return;
        }

        foreach ($reports as $report) {
            $instance = new GetCustomReport();
            $instance->initCustomReport($report);
            $instances[] = $instance;
        }
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

        $dao = $this->getDao();
        $reports = $dao->getCustomReports($idSite);

        usort($reports, function ($a, $b) {
           return strcasecmp($a['name'], $b['name']);
        });

        $addedNames = array();
        $addedCategories = array();

        $order = 100;

        foreach ($reports as $report) {
            if (!empty($report['category']) && $report['category'] === CustomReportsDao::DEFAULT_CATEGORY) {
                // we presume this subcategory is added by different plugin.

                if (!empty($report['subcategory'])) {
                    // will be added with another custom report entry. Happens when assigning a custom report to another custom report page
                    continue;
                }

                $subcategoryName = $report['name'];
                $subcategoryId = $report['idcustomreport'];
                $lowerName = strtolower($subcategoryName);

                if (in_array($lowerName, $addedNames)) {
                    continue; // this may happen when two custom reports exist where one has eg name "My report" and the other
                    // custom report chooses the same subcategory "My report"
                }

                if (in_array($subcategoryId, $addedCategories)) {
                    continue; // this may happen when two custom reports exist where one has eg name "My report" and the other
                    // custom report chooses the same subcategory "My report"
                }

                $addedNames[] = $lowerName;
                $addedCategories[] = $subcategoryId;

                $subcategory = new Subcategory();
                $subcategory->setName($subcategoryName);
                $subcategory->setCategoryId($report['category']);
                $subcategory->setId($subcategoryId);
                $subcategory->setOrder($order++);
                $subcategories[] = $subcategory;
            }
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/CustomReports/angularjs/manage/edit.directive.less";
        $stylesheets[] = "plugins/CustomReports/angularjs/manage/list.directive.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/CustomReports/angularjs/common/filters/truncateText2.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/edit.controller.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/edit.directive.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/list.controller.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/list.directive.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/manage.controller.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/manage.directive.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/model.js";
    }

    public function getClientSideTranslationKeys(&$result)
    {
        $result[] = 'General_Actions';
        $result[] = 'General_Name';
        $result[] = 'General_Id';
        $result[] = 'General_Yes';
        $result[] = 'General_No';
        $result[] = 'General_LoadingData';
        $result[] = 'General_Description';
        $result[] = 'General_Cancel';
        $result[] = 'General_Website';
        $result[] = 'General_Metrics';
        $result[] = 'CoreUpdater_UpdateTitle';
        $result[] = 'CustomReports_AddMetric';
        $result[] = 'CustomReports_Type';
        $result[] = 'CustomReports_Category';
        $result[] = 'CustomReports_AddDimension';
        $result[] = 'CustomReports_ReportPage';
        $result[] = 'CustomReports_ReportCategory';
        $result[] = 'CustomReports_ReportCategoryHelp';
        $result[] = 'CustomReports_ReportSubcategory';
        $result[] = 'CustomReports_ReportSubcategoryHelp';
        $result[] = 'CustomReports_ReportType';
        $result[] = 'CustomReports_Dimensions';
        $result[] = 'CustomReports_PreviewReport';
        $result[] = 'CustomReports_Preview';
        $result[] = 'CustomReports_Filter';
        $result[] = 'CustomReports_WarningRequiresUnlock';
        $result[] = 'CustomReports_Unlock';
        $result[] = 'CustomReports_ConfirmUnlockReport';
        $result[] = 'CustomReports_WarningOnUpdateReportMightGetLost';
        $result[] = 'CustomReports_InfoReportIsLocked';
        $result[] = 'CustomReports_ReportContent';
        $result[] = 'CustomReports_AvailableAllWebsites';
        $result[] = 'CustomReports_ErrorMissingMetric';
        $result[] = 'CustomReports_ErrorMissingDimension';
        $result[] = 'CustomReports_ReportEditNotAllowedAllWebsites';
        $result[] = 'CustomReports_RemoveMetric';
        $result[] = 'CustomReports_RemoveDimension';
        $result[] = 'CustomReports_ReportAvailableToAllWebsites';
        $result[] = 'CustomReports_ApplyTo';
        $result[] = 'CustomReports_ViewReportInfo';
        $result[] = 'CustomReports_CustomReportIntroduction';
        $result[] = 'CustomReports_NoCustomReportsFound';
        $result[] = 'CustomReports_ManageReports';
        $result[] = 'CustomReports_EditReport';
        $result[] = 'CustomReports_DeleteReportConfirm';
        $result[] = 'CustomReports_DeleteReportInfo';
        $result[] = 'CustomReports_CreateNewReport';
        $result[] = 'CustomReports_ErrorXNotProvided';
        $result[] = 'CustomReports_ReportCreated';
        $result[] = 'CustomReports_ReportUpdated';
        $result[] = 'CustomReports_UpdatingData';
        $result[] = 'CustomReports_FieldNamePlaceholder';
        $result[] = 'CustomReports_FieldDescriptionPlaceholder';
        $result[] = 'CustomReports_ReportNameHelp';
        $result[] = 'CustomReports_ReportDescriptionHelp';
        $result[] = 'CustomReports_ReportAllWebsitesHelp';
        $result[] = 'CustomReports_ReportDimensionsHelp';
        $result[] = 'CustomReports_ReportMetricsHelp';
        $result[] = 'CustomReports_ReportSegmentHelp';
    }
}
