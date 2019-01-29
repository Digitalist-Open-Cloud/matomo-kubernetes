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
namespace Piwik\Plugins\CustomReports\Model;

use Piwik\API\Request;
use Piwik\Category\CategoryList;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\CustomReports\Dao\CustomReportsDao;
use Piwik\Plugins\CustomReports\Input\Category;
use Piwik\Plugins\CustomReports\Input\Dimensions;
use Piwik\Plugins\CustomReports\Input\Metrics;
use Piwik\Plugins\CustomReports\Input\Name;
use Exception;
use Piwik\Plugins\CustomReports\Input\Description;
use Piwik\Plugins\CustomReports\Input\ReportType;
use Piwik\Plugins\CustomReports\Input\SegmentFilter;
use Piwik\Plugins\CustomReports\Input\Subcategory;
use Piwik\Plugins\CustomReports\ReportType\Evolution;
use Piwik\Plugins\CustomReports\ReportType\Table;
use Piwik\Site;

class CustomReportsModel
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DELETED = 'deleted';

    /**
     * @var CustomReportsDao
     */
    private $dao;

    /**
     * @var CategoryList
     */
    private $categoryList;

    public function __construct(CustomReportsDao $dao)
    {
        $this->dao = $dao;
    }

    public function createCustomReport($idSite, $name, $description, $reportType, $dimensions, $metrics, $segmentFilter, $categoryId, $subcategoryId, $createdDate)
    {
        if ($reportType === Evolution::ID) {
            $dimensions = array();
        }

        $this->validateReportValues($idSite, $name, $description, $reportType, $dimensions, $metrics, $segmentFilter, $categoryId, $subcategoryId);

        $status = self::STATUS_ACTIVE;

        $idCustomReport = $this->dao->addCustomReport($idSite, $name, $description, $reportType, $dimensions, $metrics, $segmentFilter, $categoryId, $subcategoryId, $status, $createdDate);

        return $idCustomReport;
    }

    private function areMetricsEqual($metricNew, $metricOld)
    {
        if (!is_array($metricNew) || !is_array($metricOld)) {
            return false;
        }

        if (count($metricNew) > count($metricOld)) {
            // there are now more metrics in the new version...
            return false;
        }

        if (array_diff($metricNew, $metricOld) === array_diff($metricOld, $metricNew)) {
            return true; // they are still the same metrics
        }

        if (array_diff($metricNew, $metricOld) === array()) {
            return true; // the new metric contains still all of the old metrics so we do not need to invalidate reports with a new revision
        }

        return false;
    }

    public function updateCustomReport($idSite, $idCustomReport, $name, $description, $reportType, $dimensions, $metrics, $segmentFilter, $categoryId, $subcategoryId, $updatedDate)
    {
        if ($reportType === Evolution::ID) {
            $dimensions = array();
        }

        $this->validateReportValues($idSite, $name, $description, $reportType, $dimensions, $metrics, $segmentFilter, $categoryId, $subcategoryId);

        $report = $this->getCustomReportById($idCustomReport);

        $revision = $report['revision'];

        if ($report['dimensions'] !== $dimensions
            || $reportType !== $report['report_type']
            || $segmentFilter !== $report['segment_filter']
            || !$this->areMetricsEqual($metrics, $report['metrics'])) {
            // we do not need to create a new revision if metrics only has a different order as we would still have all the data
            $revision++;
        }

        $columns = array(
            'idsite' => $idSite,
            'name' => $name,
            'description' => $description,
            'report_type' => $reportType,
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'segment_filter' => $segmentFilter,
            'subcategory' => $subcategoryId,
            'category' => $categoryId,
            'updated_date' => $updatedDate,
            'revision' => $revision
        );
        // idsite might change when configuring a report so we cannot use $idSite but need to use the currently stored
        // idsite in order to update the report!

        $this->updateReportColumns($report['idsite'], $idCustomReport, $columns);
    }

    /**
     * @param $idSite
     * @param $idCustomReport
     * @return array|false
     * @throws \Exception
     */
    public function getCustomReport($idSite, $idCustomReport)
    {
        $report = $this->dao->getCustomReport($idSite, $idCustomReport);
        return $this->enrichReport($report);
    }

    /**
     * @param $idCustomReport
     * @return array|false
     * @throws \Exception
     */
    public function getCustomReportById($idCustomReport)
    {
        $report = $this->dao->getCustomReportById($idCustomReport);
        return $this->enrichReport($report);
    }

    /**
     * @return array
     */
    public function getAllCustomReportsForSite($idSite)
    {
        $reports = $this->dao->getCustomReports($idSite);
        return $this->enrichReports($reports);
    }

    private function enrichReports($reports)
    {
        if (empty($reports)) {
            return array();
        }

        foreach ($reports as $index => $report) {
            $reports[$index] = $this->enrichReport($report);
        }

        return $reports;
    }

    private function enrichReport($report)
    {
        if (empty($report)) {
            return $report;
        }

        if (empty($report['idsite'])) {
            $report['site'] = array('id' => $report['idsite'], 'name' => Piwik::translate('General_MultiSitesSummary'));
        } else {
            $report['site'] = array('id' => $report['idsite'], 'name' => Site::getNameFor($report['idsite']));
        }

        $category = $report['category'];
        $report['category'] = $this->buildCategoryMetadata($category);
        $report['subcategory'] = $this->buildSubcategoryMetadata($category, $report['subcategory']);

        return $report;
    }

    private function getCategoryList()
    {
        if (!$this->categoryList) {
            $this->categoryList = CategoryList::get();
        }
        return $this->categoryList;
    }

    /**
     * Consist API return with API.getWidgetMetadata and API.getReportingPages...
     * @param string $categoryId
     * @return array
     */
    private function buildCategoryMetadata($categoryId)
    {
        if (empty($categoryId)) {
            return array(
                'id'    => CustomReportsDao::DEFAULT_CATEGORY,
                'name'  => Piwik::translate(CustomReportsDao::DEFAULT_CATEGORY),
                'order' => 999,
                'icon' => '',
            );
        }

        $category = $this->getCategoryList()->getCategory($categoryId);

        if (!empty($category)) {
            return array(
                'id'    => (string) $category->getId(),
                'name'  => Piwik::translate($category->getId()),
                'order' => $category->getOrder(),
                'icon' => $category->getIcon(),
            );
        }

        return array(
            'id'    => (string) $categoryId,
            'name'  => Piwik::translate($categoryId),
            'order' => 999,
            'icon' => '',
        );
    }

    /**
     * Consist API return with API.getWidgetMetadata and API.getReportingPages...
     * @param Subcategory|null $subcategory
     * @return array
     */
    private function buildSubcategoryMetadata($categoryId, $subcategoryId)
    {
        if (empty($subcategoryId)) {
            return null;
        }

        if (!empty($categoryId)) {
            $category = $this->getCategoryList()->getCategory($categoryId);
        } else {
            $category = null;
        }

        if (!empty($category)) {
            $subcategory = $category->getSubcategory($subcategoryId);

            if (!empty($subcategory)) {
                return array(
                    'id'    => (string) $subcategory->getId(),
                    'name'  => Piwik::translate($subcategory->getName()),
                    'order' => $subcategory->getOrder(),
                );
            }
        }

        return array(
            'id'    => (string) $subcategoryId,
            'name'  => Piwik::translate((string) $subcategoryId),
            'order' => 999,
        );
    }

    public function checkReportExists($idSite, $idCustomReport)
    {
        $report = $this->dao->getCustomReport($idSite, $idCustomReport);

        if (empty($report)) {
            throw new Exception(Piwik::translate('CustomReports_ErrorReportDoesNotExist'));
        }
    }

    public function deactivateReport($idSite, $idCustomReport)
    {
        $columns = array('status' => self::STATUS_DELETED);
        $this->updateReportColumns($idSite, $idCustomReport, $columns);
    }

    protected function getCurrentDateTime()
    {
        return Date::now()->getDatetime();
    }

    private function validateReportValues($idSite, $name, $description, $reportType, $dimensions, $metrics, $segmentFilter, $categoryId, $subcategoryId)
    {
        $nameObj = new Name($name);
        $nameObj->check();

        $descriptionObj = new Description($description);
        $descriptionObj->check();

        $typeObj = new ReportType($reportType);
        $typeObj->check();

        $categoryObj = new Category($categoryId);
        $categoryObj->check();

        $subcategoryObj = new Subcategory($subcategoryId);
        $subcategoryObj->check();

        $dimensionsObj = new Dimensions($dimensions, $idSite);
        $dimensionsObj->check();

        $metricsObj = new Metrics($metrics, $idSite);
        $metricsObj->check();

        if (!empty($idSite)) {
            $idSite = array($idSite);
        } elseif ($idSite === '0' || $idSite === 0 || $idSite === 'all') {
            // just fetching some sites as we have to pass them to the segment selector
            $idSite = Request::processRequest('SitesManager.getSitesIdWithAtLeastViewAccess');
        }

        $segment = new SegmentFilter($segmentFilter, $idSite);
        $segment->check();

        $type = \Piwik\Plugins\CustomReports\ReportType\ReportType::factory($reportType);

        if ($type->needsDimensions() && empty($dimensions)) {
            throw new Exception(Piwik::translate('CustomReports_ErrorMissingDimension'));
        }

        if (!empty($dimensions) && is_array($dimensions) && count($dimensions) > Table::NUM_MAX_DIMENSIONS) {
            throw new Exception(Piwik::translate('CustomReports_ErrorTooManyDimension', Table::NUM_MAX_DIMENSIONS));
        }
    }

    public function deactivateReportsForSite($idSite)
    {
        foreach ($this->dao->getCustomReports($idSite) as $report) {
            // getCustomReports also returns sites for "all websites"... we need to make sure to not delete those.
            if (!empty($report['idsite']) && $report['idsite'] == $idSite) {
                $this->deactivateReport($idSite, $report['idcustomreport']);
            }
        }
    }

    private function updateReportColumns($idSite, $idCustomReport, $columns)
    {
        if (!isset($columns['updated_date'])) {
            $columns['updated_date'] = $this->getCurrentDateTime();
        }
        $this->dao->updateColumns($idSite, $idCustomReport, $columns);
    }

}

