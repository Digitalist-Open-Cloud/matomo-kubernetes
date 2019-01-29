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

use Piwik\ArchiveProcessor;
use Piwik\Columns\Dimension;
use Piwik\Columns\DimensionsProvider;
use Piwik\Columns\MetricsList;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataArray as PiwikDataArray;
use Piwik\Db;
use Piwik\Log;
use Piwik\Plugin;
use Piwik\Plugin\ArchivedMetric;
use Piwik\Plugin\LogTablesProvider;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugins\CustomDimensions\CustomDimension;
use Piwik\Plugins\CustomReports\Archiver\DataArray;
use Piwik\Plugins\CustomReports\Archiver\ExecutionPlan;
use Piwik\Plugins\CustomReports\Archiver\NotJoinableException;
use Piwik\Plugins\CustomReports\Archiver\QueryBuilder;
use Piwik\Plugins\CustomReports\Model\CustomReportsModel;
use Piwik\Plugins\CustomReports\ReportType\Evolution;
use Piwik\Plugins\CustomReports\ReportType\Table;

class Archiver extends \Piwik\Plugin\Archiver
{
    const RECORDNAME_PREFIX = "CustomReports_customreport_";
    const RECORDNAME_NUMERICPREFIX = "CustomReports_";

    const LABEL_NOT_DEFINED = 'CustomReports_LabelNotDefined';

    /**
     * @var LogAggregator
     */
    private $logAggregator;

    /**
     * @var DimensionsProvider
     */
    private $dimension;

    /**
     * @var MetricsList
     */
    private $metricsList;

    /**
     * @var CustomReportsModel
     */
    private $customReportsModel;

    /**
     * @var LogTablesProvider
     */
    private $logTablesProvider;

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->dimension = StaticContainer::get('Piwik\Columns\DimensionsProvider');
        $this->metricsList = MetricsList::get();
        $this->customReportsModel = StaticContainer::get('Piwik\Plugins\CustomReports\Model\CustomReportsModel');
        $this->logAggregator = $this->getLogAggregator();
        $this->logTablesProvider = StaticContainer::get('Piwik\Plugin\LogTablesProvider');
        $this->configuration = StaticContainer::get('Piwik\Plugins\CustomReports\Configuration');
    }

    public function aggregateDayReport()
    {
        $idSite = $this->getIdSite();
        $reports = $this->customReportsModel->getAllCustomReportsForSite($idSite);

        foreach ($reports as $report) {
            $dataArray = $this->aggregateReport($report, $idSite);

            if (!empty($dataArray)) {
                $metrics = $this->getArchivableMetricsInReport($report);

                $orderBy = null;
                if (!empty($metrics)) {
                    $metricOrderBy = reset($metrics);
                    if (!empty($metricOrderBy) && is_object($metricOrderBy)) {
                        $orderBy = $metricOrderBy->getName();
                    }
                }
                $recordName = self::makeRecordName($report['idcustomreport'], $report['revision']);
                $this->insertDataArray($recordName, $dataArray, $orderBy); // we currently order in the query itself I think
            }

            unset($dataArray);
        }
    }

    public function aggregateReport($report, $idSite)
    {
        /** @var Dimension[] $allDimensions */
        $allDimensions = array();

        if (!empty($report['dimensions'])) {
            foreach ($report['dimensions'] as $dimension) {
                $columnInstance = $this->dimension->factory($dimension);
                if (!empty($columnInstance)) {
                    $allDimensions[] = $columnInstance;
                } else {
                    $columnInstance = $this->findNotFoundCustomDimensionManually($dimension, $idSite);

                    if (!empty($columnInstance)) {
                        $allDimensions[] = $columnInstance;
                    }
                }
            }
        }

        $metrics = $this->getArchivableMetricsInReport($report);

        if (empty($metrics)) {
            return;
        }

        if ($report['report_type'] === Table::ID && empty($allDimensions)) {
            // none of the orignally assigned dimensions exist anymore. no need to do anything
            return;
        }

        $allMetricNames = array();
        foreach ($metrics as $metric) {
            $query = $metric->getQuery();
            $metricName = $metric->getName();

            $columnsAggregationOperation = 'sum';
            if ($this->usesSqlFunction('sum', $query)) {
                $columnsAggregationOperation = 'sum'; // we check for sum again in case there is like sum(min(0,1))
            } elseif ($this->usesSqlFunction('min', $query)) {
                $columnsAggregationOperation = 'min';
            } elseif ($this->usesSqlFunction('max', $query)) {
                $columnsAggregationOperation = 'max';
            }

            $allMetricNames[$metricName] = $columnsAggregationOperation;
        }

        $dataArray = new DataArray($allMetricNames);

        $executionPlan = new ExecutionPlan($allDimensions, $metrics);
        $dimensionsPlan = $executionPlan->getDimensionsPlan();

        foreach ($dimensionsPlan as $dimensionsGroup) {
            $metricsPlan = $executionPlan->getMetricsPlanForGroup();

            // for each group of dimensions, we need to resolve all the metrics in several queries
            foreach ($metricsPlan as $metricsToFetch) {

                $queryBuilder = new QueryBuilder($this->logAggregator);

                foreach ($dimensionsGroup['left'] as $index => $dimension) {
                    try {
                        /** @var Dimension $dimension */
                        $queryBuilder->addDimension($dimension, false);
                    } catch (NotJoinableException $e) {
                        Log::info(sprintf('Ignored dimension %s in report %d as it is not joinable', $dimension->getId(), $report['idcustomreport']));
                    }
                }

                foreach ($dimensionsGroup['right'] as $index => $dimension) {
                    try {
                        /** @var Dimension $dimension */
                        $queryBuilder->addDimension($dimension, true);
                    } catch (NotJoinableException $e) {
                        Log::info(sprintf('Ignored dimension %s in report %d as it is not joinable', $dimension->getId(), $report['idcustomreport']));
                    }
                }

                foreach ($metricsToFetch as $metric) {
                    try {
                        /** @var ArchivedMetric $metric */
                        $queryBuilder->addMetric($metric);
                    } catch (NotJoinableException $e) {
                        Log::info(sprintf('Ignored metric %s in report %d as it is not joinable', $metric->getName(), $report['idcustomreport']));
                    }
                }

                if (!$queryBuilder->isValid()) {
                    continue;
                }

                if (!empty($report['segment_filter'])) {
                    $queryBuilder->addSegmentFilter($report['segment_filter'], $idSite);
                }

                $query = $queryBuilder->buildQuery();

                try {
                    $cursor = Db::query($query['sql'], $query['bind']);
                } catch (\Exception $e) {
                    // var_export($queryBuilder->getReportQuery()->getFrom());
                    // var_export($queryBuilder->getReportQuery()->getSelect());
                    // var_export($queryBuilder->getReportQuery()->getWhere());
                    // echo $query['sql'];
                    throw $e;
                }

                // we need to bring them in correct order again in case they were reversed
                $this->makeRegularReport($dataArray, $report, $dimensionsGroup['left'], $cursor, $idSite);
            }

            if (count($dimensionsGroup['left']) === 1) {
                /** @var DataArray $dataArray */
                $dataArray->doneFirstLevel = true;
            } elseif (count($dimensionsGroup['left']) === 2) {
                $dataArray->doneFirstLevel = true;
                $dataArray->doneSecondLevel = true;
            }
        }

        return $dataArray;
    }

    private function findNotFoundCustomDimensionManually($dimensionId, $idSite)
    {
        $manager = Plugin\Manager::getInstance();

        if (strpos($dimensionId, 'CustomDimension.') === 0
            && $manager->isPluginActivated('CustomDimensions')) {

            try {
                $configuration = StaticContainer::get('Piwik\Plugins\CustomDimensions\Dao\Configuration');
            } catch (\Exception $e) {
                // plugin does not have the class
                return;
            }

            if (!$configuration || !method_exists($configuration, 'getCustomDimensionsForSite')) {
                return;
            }

            $dimensions = $configuration->getCustomDimensionsForSite($idSite);
            foreach ($dimensions as $dimension) {
                if (!$dimension['active']) {
                    continue;
                }

                $custom = new CustomDimension();
                $custom->initCustomDimension($dimension);

                if ($custom->getId() === $dimensionId) {
                    return $custom;
                }
            }
        }
    }

    private function getMetrics($metricInstances, $metricName)
    {
        $metricInstance = $this->metricsList->getMetric($metricName);

        if ($metricInstance && $metricInstance instanceof ArchivedMetric) {
            if (!in_array($metricInstance, $metricInstances, $strict = true)) {
                $metricInstances[] = $metricInstance;
            }
        } elseif ($metricInstance && $metricInstance instanceof ProcessedMetric) {
            $depMetrics = $metricInstance->getDependentMetrics();
            foreach ($depMetrics as $depMetric) {
                $metricInstances = $this->getMetrics($metricInstances, $depMetric);
            }
        }

        return $metricInstances;
    }

    public static function makeRecordName($idCustomReport, $revision)
    {
        return self::RECORDNAME_PREFIX . (int) $idCustomReport . '_' . (int) $revision;
    }

    public static function makeEvolutionRecordNamePrefix($idCustomReport, $revision)
    {
        return self::makeRecordName($idCustomReport, $revision) . '_';
    }

    public static function makeEvolutionRecordName($idCustomReport, $revision, $metricName)
    {
        return self::makeEvolutionRecordNamePrefix($idCustomReport, $revision) . $metricName;
    }

    /**
     * @param DataArray $dataArray
     * @param array $report
     * @param Dimension[]  $dimensionsInThisRun
     * @param \Zend_Db_Statement  $cursor
     * @param string $orderBy
     * @param int $idSite
     */
    private function makeRegularReport($dataArray, $report, $dimensionsInThisRun, $cursor, $idSite)
    {
        switch ($report['report_type']) {
            case Evolution::ID:
                $row = $cursor->fetch();

                if (!empty($row)) {
                    /** @var DataArray $dataArray */
                    $records = array();
                    foreach ($row as $metric => $value) {
                        $records[self::makeEvolutionRecordName($report['idcustomreport'], $report['revision'], $metric)] = $value;
                    }
                    $this->getProcessor()->insertNumericRecords($records);
                }
                return;
        }

        $isDim1Binary = isset($dimensionsInThisRun[0]) && $dimensionsInThisRun[0]->getType() === Dimension::TYPE_BINARY;
        $isDim2Binary = isset($dimensionsInThisRun[1]) && $dimensionsInThisRun[1]->getType() === Dimension::TYPE_BINARY;
        $isDim3Binary = isset($dimensionsInThisRun[2]) && $dimensionsInThisRun[2]->getType() === Dimension::TYPE_BINARY;

        $firstLevelColumn  = isset($dimensionsInThisRun[0]) ? $dimensionsInThisRun[0]->getId() : null;
        $secondLevelColumn = isset($dimensionsInThisRun[1]) ? $dimensionsInThisRun[1]->getId() : null;
        $thirdLevelColumn  = isset($dimensionsInThisRun[2]) ? $dimensionsInThisRun[2]->getId() : null;

        $numDimensions = count($dimensionsInThisRun);

        while ($row = $cursor->fetch()) {

            if ($isDim1Binary) {
                $row[$dimensionsInThisRun[0]->getId()] = bin2hex($row[$dimensionsInThisRun[0]->getId()]);
            }
            if ($isDim2Binary) {
                $row[$dimensionsInThisRun[1]->getId()] = bin2hex($row[$dimensionsInThisRun[1]->getId()]);
            }
            if ($isDim3Binary) {
                $row[$dimensionsInThisRun[2]->getId()] = bin2hex($row[$dimensionsInThisRun[2]->getId()]);
            }

            foreach ($dimensionsInThisRun as $dimension) {
                if (isset($row[$dimension->getId()])) {
                    $row[$dimension->getId()] = $dimension->groupValue($row[$dimension->getId()], $idSite);
                }
            }

            $firstLevelLabel = Archiver::LABEL_NOT_DEFINED;
            $secondLevelLabel = null;
            $thirdLevelLabel = null;

            if (isset($row[$firstLevelColumn])) {
                $firstLevelLabel = $row[$firstLevelColumn];
                unset($row[$firstLevelColumn]);
            }

            if (isset($secondLevelColumn) && isset($secondLevelColumn) && isset($row[$secondLevelColumn])) {
                $secondLevelLabel = $row[$secondLevelColumn];
                unset($row[$secondLevelColumn]);
            }

            if (isset($thirdLevelColumn) && isset($thirdLevelColumn) && isset($row[$thirdLevelColumn])) {
                $thirdLevelLabel = $row[$thirdLevelColumn];
                unset($row[$thirdLevelColumn]);
            }

            if ($numDimensions === 1 || !$dataArray->doneFirstLevel) {
                $dataArray->computeMetrics($row, $firstLevelLabel);
            }

            if ($numDimensions === 2 || (!$dataArray->doneSecondLevel && $numDimensions > 2)) {
                $dataArray->computeMetricsLevel2($row, $firstLevelLabel, $secondLevelLabel);
            }

            if ($numDimensions === 3) {
                $dataArray->computeMetricsLevel3($row, $firstLevelLabel, $secondLevelLabel, $thirdLevelLabel);
            }
        }

        $cursor->closeCursor();
    }

    /**
     * @param array $report
     * @return ArchivedMetric[]
     */
    private function getArchivableMetricsInReport($report)
    {
        $metrics = array();

        if (!empty($report['metrics'])) {
            foreach ($report['metrics'] as $metric) {
                $metrics = $this->getMetrics($metrics, $metric);
            }
        }

        return $metrics;
    }

    private function insertDataArray($recordName, PiwikDataArray $dataArray, $orderByMetric)
    {
        $table = $dataArray->asDataTable();

        $maxRows = $this->configuration->getArchiveMaxRows();
        $maxRowsSub = $this->configuration->getArchiveMaxRowsSubtable();

        $serialized = $table->getSerialized($maxRows, $maxRowsSub, $orderByMetric);
        $this->getProcessor()->insertBlobRecord($recordName, $serialized);

        Common::destroy($table);
        unset($table);
        unset($serialized);
    }

    private function usesSqlFunction($function, $select)
    {
        return preg_match('/(' . $function . ')\s*\(/', $select);
    }

    public function aggregateMultipleReports()
    {
        if ($this->getPeriodLabel() === 'day') {
            $this->aggregateDayReport();
            return;
        }

        $idSite = $this->getIdSite();
        $reports = $this->customReportsModel->getAllCustomReportsForSite($idSite);

        $maxRows = $this->configuration->getArchiveMaxRows();
        $maxRowsSub = $this->configuration->getArchiveMaxRowsSubtable();

        foreach ($reports as $report) {

            $columnsAggregationOperation = array('level' => function ($thisColumnValue, $columnToSumValue) {
                // we do not want to sum the level or so. always keep either value
                if (!empty($thisColumnValue)) {
                    return $thisColumnValue;
                }
                if (!empty($columnToSumValue)){
                    return $columnToSumValue;
                }
            });

            $metrics = $this->getArchivableMetricsInReport($report);

            foreach ($metrics as $metric) {
                $query = $metric->getQuery();
                $metricName = $metric->getName();

                if ($this->usesSqlFunction('sum', $query)) {
                    $columnsAggregationOperation[$metricName] = 'sum'; // we check for sum again in case there is like sum(min(0,1))
                } elseif ($this->usesSqlFunction('min', $query)) {
                    $columnsAggregationOperation[$metricName] = 'min';
                } elseif ($this->usesSqlFunction('max', $query)) {
                    $columnsAggregationOperation[$metricName] = 'max';
                }
                // TODO add possibility for metric to add custom callback aggregation method and / or operation keyword
            }

            switch ($report['report_type']) {
                case Evolution::ID:
                    $metrics = $this->getArchivableMetricsInReport($report);
                    $numericRecordNames = array();
                    foreach ($metrics as $metric) {
                        $numericRecordNames[] = self::makeEvolutionRecordName($report['idcustomreport'], $report['revision'], $metric->getName());
                    }

                    $this->getProcessor()->aggregateNumericMetrics($numericRecordNames, $columnsAggregationOperation);
                    break;
                default:
                    $blobRecordName = self::makeRecordName($report['idcustomreport'], $report['revision']);

                    $this->getProcessor()->aggregateDataTableRecords(
                        array($blobRecordName), $maxRows, $maxRowsSub,
                       $columnToSort = null,  $columnsAggregationOperation
                    );
            }
        }
    }

    private function getPeriodLabel()
    {
        $params = $this->getParams();
        if ($params && $params->getPeriod()) {
            return $params->getPeriod()->getLabel();
        }
    }

    private function getParams()
    {
        $params = $this->getProcessor()->getParams();
        return $params;
    }

    protected function getIdSite()
    {
        $params = $this->getParams();
        $site = $params->getSite();

        if ($site && $site->getId()) {
            return $site->getId();
        }

        $idSites = $params->getIdSites();
        return reset($idSites);
    }
}
