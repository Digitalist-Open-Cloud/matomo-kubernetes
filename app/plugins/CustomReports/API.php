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
use Piwik\Cache;
use Piwik\Category\CategoryList;
use Piwik\Columns\DimensionsProvider;
use Piwik\Columns\MetricsList;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugin\ArchivedMetric;
use Piwik\Plugin\LogTablesProvider;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugins\CustomReports\Dao\CustomReportsDao;
use Piwik\Plugins\CustomReports\Input\Validator;
use Piwik\Plugins\CustomReports\ReportType\ReportType;
use Piwik\Plugins\CustomReports\Model\CustomReportsModel;

/**
 * The <a href='http://plugins.matomo.org/CustomReports' target='_blank'>Custom Reports</a> API lets you 1) create custom
 * reports within Matomo and 2) view the created reports in the Matomo Reporting UI or consume them via the API.
 * <br/><br/>
 * You can choose between different visualizations (eg table or evolution graph) and combine hundreds of dimensions
 * and metrics to get the data you need.
 *
 * @method static \Piwik\Plugins\CustomReports\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * @var MetricsList
     */
    private $metricsList;

    /**
     * @var DimensionsProvider
     */
    private $columnsProvider;

    /**
     * @var CustomReportsModel
     */
    private $model;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var LogTablesProvider
     */
    private $logTablesProvider;

    public function __construct(CustomReportsModel $model, Validator $validator, DimensionsProvider $columnsProvider, LogTablesProvider $logTablesProvider)
    {
        $this->metricsList = MetricsList::get();
        $this->columnsProvider = $columnsProvider;
        $this->model = $model;
        $this->validator = $validator;
        $this->logTablesProvider = $logTablesProvider;
    }

    /**
     * Adds a new custom report
     * @param int $idSite
     * @param string $name  The name of the report.
     * @param string $reportType    The type of report you want to create, for example 'table' or 'evolution'.
     *                              For a list of available reports call 'CustomReports.getAvailableReportTypes'
     * @param string[] $metricIds   A list of metric IDs. For a list of available metrics call 'CustomReports.getAvailableMetrics'
     * @param string $categoryId  By default, the report will be put into a custom report category unless a specific
     *                            categoryId is provided. For a list of available categories call 'CustomReports.getAvailableCategories'.
     * @param string[] $dimensionIds A list of dimension IDs.  For a list of available metrics call 'CustomReports.getAvailableDimensions'
     * @param bool|string $subcategoryId By default, a new reporting page will be created for this report unless you
     *                                   specifiy a specific name or subcategoryID. For a list of available subcategories
     *                                   call 'CustomReports.getAvailableCategories'.
     * @param string $description  An optional description for the report, will be shown in the title help icon of the report.
     * @param string $segmentFilter   An optional segment to filter the report data. Needs to be sent urlencoded.
     * @return int
     */
    public function addCustomReport($idSite, $name, $reportType, $metricIds, $categoryId = false, $dimensionIds = array(), $subcategoryId = false, $description = '', $segmentFilter = '')
    {
        $this->validator->checkWritePermission($idSite);

        // prevent creating reports for sites that do not yet exist but might in the future
        $this->validator->checkSiteExists($idSite);

        if (empty($categoryId)) {
            $categoryId = CustomReportsDao::DEFAULT_CATEGORY;
        }

        $createdDate = Date::now()->getDatetime();
        if (!empty($segmentFilter)) {
            $segmentFilter = urldecode($segmentFilter);
        }

        $idReport = $this->model->createCustomReport($idSite, $name, $description, $reportType, $dimensionIds, $metricIds, $segmentFilter, $categoryId, $subcategoryId, $createdDate);

        $this->clearCache();
        return $idReport;
    }

    private function clearCache()
    {
        // we need to delete possibly cached values. especially ReportsProvider
        try {
            Cache::getLazyCache()->flushAll();
        } catch (\Exception $e) {

        }
    }

    /**
     * Updates an existing custom report. Be aware that if you change metrics, dimensions, the report type or the segment filter,
     * previously processed/archived reports may become unavailable and would need to be re-processed.
     *
     * @param int $idSite
     * @param int $idCustomReport
     * @param string $name  The name of the report.
     * @param string $reportType    The type of report you want to create, for example 'table' or 'evolution'.
     *                              For a list of available reports call 'CustomReports.getAvailableReportTypes'
     * @param string[] $metricIds   A list of metric IDs. For a list of available metrics call 'CustomReports.getAvailableMetrics'
     * @param string $categoryId  By default, the report will be put into a custom report category unless a specific
     *                            categoryId is provided. For a list of available categories call 'CustomReports.getAvailableCategories'.
     * @param string[] $dimensionIds A list of dimension IDs.  For a list of available metrics call 'CustomReports.getAvailableDimensions'
     * @param bool|string $subcategoryId By default, a new reporting page will be created for this report unless you
     *                                   specifiy a specific name or subcategoryID. For a list of available subcategories
     *                                   call 'CustomReports.getAvailableCategories'.
     * @param string $description  An optional description for the report, will be shown in the title help icon of the report.
     * @param string $segmentFilter   An optional segment to filter the report data. Needs to be sent urlencoded.
     */
    public function updateCustomReport($idSite, $idCustomReport, $name, $reportType, $metricIds, $categoryId = false, $dimensionIds = array(), $subcategoryId = false, $description = '', $segmentFilter = '')
    {
        $this->validator->checkWritePermission($idSite);

        // prevent creating reports for sites that do not yet exist but might in the future
        $this->validator->checkSiteExists($idSite);

        // we cannot get report by idSite, idCustomReport since the idSite may change!
        $report = $this->model->getCustomReportById($idCustomReport);

        if (empty($report)) {
            throw new \Exception(Piwik::translate('CustomReports_ErrorReportDoesNotExist'));
        }

        if ($report['idsite'] != $idSite) {
            // if the site changes for a report, make sure the user write permission for the old and the new site
            $this->validator->checkWritePermission($report['idsite']);
        }

        if (empty($categoryId)) {
            $categoryId = CustomReportsDao::DEFAULT_CATEGORY;
        }

        if (!empty($segmentFilter)) {
            $segmentFilter = urldecode($segmentFilter);
        }

        $updatedDate = Date::now()->getDatetime();

        $this->model->updateCustomReport($idSite, $idCustomReport, $name, $description, $reportType, $dimensionIds, $metricIds, $segmentFilter, $categoryId, $subcategoryId, $updatedDate);
    }

    /**
     * Get all custom report configurations for a specific site.
     *
     * @param int $idSite
     * @return array
     */
    public function getConfiguredReports($idSite)
    {
        $this->validator->checkWritePermission($idSite);
        $this->validator->checkSiteExists($idSite);

        if ($idSite === 'all') {
            $idSite = 0;
        }

        $reports = $this->model->getAllCustomReportsForSite($idSite);
        usort($reports, function ($a, $b) {
            if ($a['idcustomreport'] > $b['idcustomreport']) {
                return 1; // no need to check for === because two reports won't have same ID
            }
            return -1;
        });
        return $reports;
    }

    /**
     * Get a specific custom report configuration.
     *
     * @param int $idSite
     * @param int $idCustomReport
     * @return array
     */
    public function getConfiguredReport($idSite, $idCustomReport)
    {
        $this->validator->checkWritePermission($idSite);
        $this->validator->checkSiteExists($idSite);

        if ($idSite === 'all') {
            $idSite = 0;
        }

        $this->model->checkReportExists($idSite, $idCustomReport);

        return $this->model->getCustomReport($idSite, $idCustomReport);
    }

    /**
     * Deletes the given custom report.
     *
     * When a custom report is deleted, its report will be no longer available in the API and tracked data for this
     * report might be removed at some point by the system.
     *
     * @param int $idSite
     * @param int $idForm
     */
    public function deleteCustomReport($idSite, $idCustomReport)
    {
        $this->validator->checkWritePermission($idSite);

        if ($idSite === 'all') {
            $idSite = 0;
        }

        $report = $this->model->getCustomReport($idSite, $idCustomReport);

        if (!empty($report) && $report['idsite'] != $idSite) {
            // prevent a possible hack that someone passes a different site than the report has and then we accidentally
            // still delete the report because we match with `idsite = 0 or idsite = ?`. We don't do this here right now
            // and wouldn't need this code but it is to prevent any possible future security bugs.
            throw new \Exception('Cannot delete report, site does not match');
        } elseif (!empty($report)) {
            $this->model->deactivateReport($idSite, $idCustomReport);
            Piwik::postEvent('CustomReports.deleteCustomReport.end', array($idSite, $idCustomReport));
        }
    }

    /**
     * Get a list of available categories that can be used in custom reports.
     *
     * @param int $idSite
     * @return array
     */
    public function getAvailableCategories($idSite)
    {
        $this->validator->checkReportViewPermission($idSite);

        $reportPages = Request::processRequest('API.getReportPagesMetadata', array('idSite' => $idSite, 'filter_limit' => -1));

        $categories = array();
        foreach ($reportPages as $reportPage) {
            if (!empty($reportPage['category']['id'])) {
                $categoryId = $reportPage['category']['id'];

                if ($categoryId === 'Dashboard_Dashboard') {
                    continue;
                }

                $subcategoryId = $reportPage['subcategory']['id'];
                if (strpos($subcategoryId, '_Manage') !== false) {
                    continue; // we do not want to be able to add reports to manage pages
                }

                if (isset($categories[$categoryId])) {
                    $categories[$categoryId]['subcategories'][] = array(
                        'uniqueId' => $reportPage['subcategory']['id'],
                        'name' => $reportPage['subcategory']['name']
                    );
                } else {
                    $categories[$categoryId] = array(
                        'uniqueId' => $categoryId,
                        'name' => $reportPage['category']['name'],
                        'subcategories' => array(
                            array(
                                'uniqueId' => $reportPage['subcategory']['id'],
                                'name' => $reportPage['subcategory']['name']
                            )
                        ),
                    );
                }
            }
        }

        if (!isset($categories['CustomReports_CustomReports'])) {
            $categories['CustomReports_CustomReports'] = array(
                'uniqueId' => 'CustomReports_CustomReports',
                'name' => Piwik::translate('CustomReports_CustomReports'),
                'subcategories' => array()
            );
        }

        return array_values($categories);
    }

    /**
     * Get a list of available reporty types that can be used in custom reports.
     *
     * @param int $idSite
     * @return array
     */
    public function getAvailableReportTypes()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $rows = array();
        foreach (ReportType::getAll() as $reportType) {
            $rows[] = array('key' => $reportType::ID, 'value' => $reportType->getName());
        }

        return $rows;
    }

    private function isTableJoinable($tableName)
    {
        $logTable = $this->logTablesProvider->getLogTable($tableName);
        if ($logTable && ($logTable->getColumnToJoinOnIdAction() || $logTable->getColumnToJoinOnIdVisit())) {
            if ($logTable->getPrimaryKey()) {
                // without primary key we would not group the data correctly
                return true;
            }
        }

        return false;
    }

    /**
     * Get a list of available dimensions that can be used in custom reports.
     *
     * @param int $idSite
     * @return array
     */
    public function getAvailableDimensions($idSite)
    {
        Piwik::checkUserHasAdminAccess($idSite);

        $dimensions = $this->columnsProvider->getAllDimensions();

        $rows = array();

        $dimensionsToIgnore = array(
            'Actions.IdPageview', 'Actions.ActionType', 'CoreHome.VisitId', 'UserCountry.Latitude', 'UserCountry.Longitude',
            'UserCountry.Region', // not working on it's own as it needs country in combination in order to format title and have unique values
            'DevicesDetection.OsVersion', // only makes sense in combination with Os Family
            'Ecommerce.Order',
            'CoreHome.VisitorId',
            'CoreHome.LinkVisitActionId'
        );

        /**
         * Adds the possibility to other plugins to ignore more dimensions
         */
        Piwik::postEvent('CustomReports.addDimensionsToIgnore', array(&$dimensionsToIgnore));

        $categoryList = CategoryList::get();

        foreach ($dimensions as $dimension) {
            $categoryId = $dimension->getCategoryId();
            $dimensionName = $dimension->getName();
            $table = $dimension->getDbTableName();
            $dimensionId = $dimension->getId();

            if (!$table) {
                // without table we cannot join it
                continue;
            }

            if (!$this->isTableJoinable($table)) {
                // archiving this dimension would not work
                continue;
            }

            if (in_array($dimensionId, $dimensionsToIgnore)) {
                continue;
            }

            if ($dimension->getColumnName() && $dimensionName) {
                if (!isset($rows[$categoryId])) {
                    $category = $categoryList->getCategory($categoryId);
                    $orderId = 999;
                    if (!empty($category)) {
                        $orderId = $category->getOrder();
                    }
                    $rows[$categoryId] = array(
                        'category' => Piwik::translate($categoryId),
                        'dimensions' => array(),
                        'orderId' => $orderId
                    );
                }
                $rows[$categoryId]['dimensions'][] = array(
                    'uniqueId' => $dimension->getId(),
                    'name' => ucwords($dimensionName),
                    'sqlSegment' => $dimension->getSqlSegment(),
                );
            }
        }

        usort($rows, function ($rowA, $rowB) {
            if ((int)$rowA['orderId'] > (int)$rowB['orderId']) {
                return 1;
            }
            if ((int)$rowA['orderId'] === (int)$rowB['orderId']) {
                return 0;
            }
            return -1;
        });

        foreach ($rows as $categoryId => $row) {
            $dimensions = $row['dimensions'];
            usort($dimensions, function ($dimA, $dimB) {
                return strcmp($dimA['name'], $dimB['name']);
            });
            $rows[$categoryId]['dimensions'] = $dimensions;
        }

        return array_values($rows);
    }

    /**
     * Get a list of available metrics that can be used in custom reports.
     *
     * @param int $idSite
     * @return array
     */
    public function getAvailableMetrics($idSite)
    {
        Piwik::checkUserHasAdminAccess($idSite);

        $metrics = MetricsList::get();
        $categoryList = CategoryList::get();

        $rows = array();
        foreach ($metrics->getMetrics() as $metric) {
            if (!$metric) {
                continue;
            }
            if ($metric instanceof ProcessedMetric && !$this->canGenerateMetricAutomatically($metric)) {
                // we do not have all the dependent metrics to generate this processed metric automatically
                continue;
            }

            $categoryId = $metric->getCategoryId();
            $name = $metric->getName();
            $translatedName = $metric->getTranslatedName();

            if (($metric instanceof ProcessedMetric || $metric instanceof ArchivedMetric) && $name && $translatedName) {

                if (method_exists($metric, 'getQuery') && !$metric->getQuery()) {
                    // archiving this metric would not work!
                    continue;
                }

                if (method_exists($metric, 'getDbTableName') && $metric->getDbTableName() && !$this->isTableJoinable($metric->getDbTableName())) {
                    // archiving this metric would not work!
                    continue;
                }

                if (method_exists($metric, 'getDimension') && $metric->getDimension()) {
                    $dimension = $metric->getDimension();
                    $dbDiscriminator = $dimension->getDbDiscriminator();
                    if ($dbDiscriminator) {
                        $dbDiscriminatorValue = $dbDiscriminator->getValue();
                        if (!isset($dbDiscriminatorValue) || !is_numeric($dbDiscriminatorValue)) {
                            continue;
                        }
                    }
                }

                if (!isset($rows[$categoryId])) {
                    $category = $categoryList->getCategory($categoryId);
                    $orderId = 999;
                    if (!empty($category)) {
                        $orderId = $category->getOrder();
                    }

                    $rows[$categoryId] = array(
                        'category' => Piwik::translate($categoryId),
                        'metrics' => array(),
                        'orderId' => $orderId
                    );
                }
                $rows[$categoryId]['metrics'][] = array(
                    'uniqueId' => $name, 'name' => ucwords($translatedName), 'description' => $metric->getDocumentation()
                );
            }
        }

        usort($rows, function ($rowA, $rowB) {
            if ((int)$rowA['orderId'] > (int)$rowB['orderId']) {
                return 1;
            }
            if ((int)$rowA['orderId'] === (int)$rowB['orderId']) {
                return 0;
            }
            return -1;
        });

        foreach ($rows as $category => $row) {
            $dimensions = $row['metrics'];
            usort($dimensions, function ($dimA, $dimB) {
                return strcasecmp($dimA['name'], $dimB['name']);
            });
            $rows[$category]['metrics'] = $dimensions;
        }

        return array_values($rows);
    }

    private function canGenerateMetricAutomatically(ProcessedMetric $metric)
    {
        foreach ($metric->getDependentMetrics() as $dependentMetric) {
            $depMetric = $this->metricsList->getMetric($dependentMetric);
            if (!$depMetric) {
                // we cannot generate this metric directly
                return false;
            }

            if ($depMetric instanceof ProcessedMetric && !$this->canGenerateMetricAutomatically($depMetric)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get report data for a previously created custom report.
     *
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param int $idCustomReport
     * @param bool|string $segment
     * @param bool $expanded
     * @param bool $flat
     * @param int|bool $idSubtable
     * @param string|bool $columns
     * @return DataTable\DataTableInterface
     */
    public function getCustomReport($idSite, $period, $date, $idCustomReport, $segment = false, $expanded = false, $flat = false, $idSubtable = false, $columns = false)
    {
        $this->validator->checkReportViewPermission($idSite);
        $this->validator->checkSiteExists($idSite); // lets not return any reports from eg deleted sites if for some reason report still exists
        $this->model->checkReportExists($idSite, $idCustomReport);

        $report = $this->model->getCustomReport($idSite, $idCustomReport);

        $reportType = ReportType::factory($report['report_type']);

        $table = $reportType->fetchApi($idSite, $idCustomReport, $period, $date, $segment, $expanded, $flat, $idSubtable, $columns);

        return $table;
    }
}
