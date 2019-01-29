<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomDimensions;

use Piwik\Config;
use Piwik\Metrics;
use Piwik\Plugins\Actions\Metrics as ActionsMetrics;
use Piwik\Plugins\CustomDimensions\Dao\Configuration;
use Piwik\Plugins\CustomDimensions\Dao\LogTable;
use Piwik\Tracker;
use Piwik\ArchiveProcessor;

/**
 * Archives reports for each active Custom Dimension of a website.
 */
class Archiver extends \Piwik\Plugin\Archiver
{
    const LABEL_CUSTOM_VALUE_NOT_DEFINED = "Value not defined";
    private $recordNames = array();

    /**
     * @var DataArray
     */
    protected $dataArray;
    protected $maximumRowsInDataTableLevelZero;
    protected $maximumRowsInSubDataTable;

    /**
     * @var ArchiveProcessor
     */
    private $processor;

    function __construct($processor)
    {
        parent::__construct($processor);

        $this->processor = $processor;

        $this->maximumRowsInDataTableLevelZero = Config::getInstance()->General['datatable_archiving_maximum_rows_custom_variables'];
        $this->maximumRowsInSubDataTable = Config::getInstance()->General['datatable_archiving_maximum_rows_subtable_custom_variables'];
    }

    public static function buildRecordNameForCustomDimensionId($id)
    {
        return 'CustomDimensions_Dimension' . (int) $id;
    }

    private function getRecordNames()
    {
        if (!empty($this->recordNames)) {
            return $this->recordNames;
        }

        $dimensions = $this->getActiveCustomDimensions();

        foreach ($dimensions as $dimension) {
            $this->recordNames[] = self::buildRecordNameForCustomDimensionId($dimension['idcustomdimension']);
        }

        return $this->recordNames;
    }

    private function getActiveCustomDimensions()
    {
        $idSite = $this->processor->getParams()->getSite()->getId();

        $config = new Configuration();
        $dimensions = $config->getCustomDimensionsForSite($idSite);

        $active = array();
        foreach ($dimensions as $index => $dimension) {
            if ($dimension['active']) {
                $active[] = $dimension;
            }
        }

        return $active;
    }

    public function aggregateMultipleReports()
    {
        $columnsAggregationOperation = null;

        $this->getProcessor()->aggregateDataTableRecords(
            $this->getRecordNames(),
            $this->maximumRowsInDataTableLevelZero,
            $this->maximumRowsInSubDataTable,
            $columnToSort = Metrics::INDEX_NB_VISITS,
            $columnsAggregationOperation,
            $columnsToRenameAfterAggregation = null,
            $countRowsRecursive = array());
    }

    public function aggregateDayReport()
    {
        $dimensions = $this->getActiveCustomDimensions();
        foreach ($dimensions as $dimension) {
            $this->dataArray = new DataArray();

            $valueField = LogTable::buildCustomDimensionColumnName($dimension);
            $dimensions = array($valueField);

            if ($dimension['scope'] === CustomDimensions::SCOPE_VISIT) {
                $this->aggregateFromVisits($valueField, $dimensions, " log_visit.$valueField is not null");
                $this->aggregateFromConversions($valueField, $dimensions, " log_conversion.$valueField is not null");
            } elseif ($dimension['scope'] === CustomDimensions::SCOPE_ACTION) {
                $this->aggregateFromActions($valueField);
            }

            $this->dataArray->enrichMetricsWithConversions();
            $table = $this->dataArray->asDataTable();

            $blob = $table->getSerialized(
                $this->maximumRowsInDataTableLevelZero, $this->maximumRowsInSubDataTable,
                $columnToSort = Metrics::INDEX_NB_VISITS
            );

            $recordName = self::buildRecordNameForCustomDimensionId($dimension['idcustomdimension']);
            $this->getProcessor()->insertBlobRecord($recordName, $blob);
        }
    }

    protected function aggregateFromVisits($valueField, $dimensions, $where)
    {
        $query = $this->getLogAggregator()->queryVisitsByDimension($dimensions, $where);

        while ($row = $query->fetch()) {
            $value = $this->cleanCustomDimensionValue($row[$valueField]);

            $this->dataArray->sumMetricsVisits($value, $row);
        }
    }

    protected function aggregateFromConversions($valueField, $dimensions, $where)
    {
        $query = $this->getLogAggregator()->queryConversionsByDimension($dimensions, $where);

        while ($row = $query->fetch()) {
            $value = $this->cleanCustomDimensionValue($row[$valueField]);

            $this->dataArray->sumMetricsGoals($value, $row);
        }
    }

    public function queryCustomDimensionActions(DataArray $dataArray, $valueField, $additionalWhere = '')
    {
        $metricsConfig = ActionsMetrics::getActionMetrics();

        $metricIds   = array_keys($metricsConfig);
        $metricIds[] = Metrics::INDEX_PAGE_SUM_TIME_SPENT;
        $metricIds[] = Metrics::INDEX_BOUNCE_COUNT;
        $metricIds[] = Metrics::INDEX_PAGE_EXIT_NB_VISITS;
        $dataArray->setActionMetricsIds($metricIds);

        $select = "log_link_visit_action.$valueField,
                  log_action.name as url,
                  sum(log_link_visit_action.time_spent) as `" . Metrics::INDEX_PAGE_SUM_TIME_SPENT . "`,
                  sum(case log_visit.visit_total_actions when 1 then 1 when 0 then 1 else 0 end) as `" . Metrics::INDEX_BOUNCE_COUNT . "`,
                  sum(IF(log_visit.last_idlink_va = log_link_visit_action.idlink_va, 1, 0)) as `" . Metrics::INDEX_PAGE_EXIT_NB_VISITS . "`";

        $select = $this->addMetricsToSelect($select, $metricsConfig);

        $from = array(
            "log_link_visit_action",
            array(
                "table"  => "log_visit",
                "joinOn" => "log_visit.idvisit = log_link_visit_action.idvisit"
            ),
            array(
                "table"  => "log_action",
                "joinOn" => "log_link_visit_action.idaction_url = log_action.idaction"
            )
        );

        $where = "log_link_visit_action.server_time >= ?
                  AND log_link_visit_action.server_time <= ?
                  AND log_link_visit_action.idsite = ?
                  AND log_link_visit_action.$valueField is not null";

        if (!empty($additionalWhere)) {
            $where .= ' AND ' . $additionalWhere;
        }

        $groupBy = "log_link_visit_action.$valueField, url";
        $orderBy = "`" . Metrics::INDEX_PAGE_NB_HITS . "` DESC";

        // get query with segmentation
        $logAggregator = $this->getLogAggregator();
        $query     = $logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);
        $db        = $logAggregator->getDb();
        $resultSet = $db->query($query['sql'], $query['bind']);

        return $resultSet;
    }

    protected function aggregateFromActions($valueField)
    {
        $resultSet = $this->queryCustomDimensionActions($this->dataArray, $valueField);

        while ($row = $resultSet->fetch()) {
            $label = $row[$valueField];
            $label = $this->cleanCustomDimensionValue($label);

            $this->dataArray->sumMetricsActions($label, $row);

            // make sure we always work with normalized URL no matter how the individual action stores it
            $normalized = Tracker\PageUrl::normalizeUrl($row['url']);
            $row['url'] = $normalized['url'];

            $subLabel = $row['url'];

            if (empty($subLabel)) {
                continue;
            }

            $this->dataArray->sumMetricsActionCustomDimensionsPivot($label, $subLabel, $row);
        }
    }

    private function addMetricsToSelect($select, $metricsConfig)
    {
        if (!empty($metricsConfig)) {
            foreach ($metricsConfig as $metric => $config) {
                $select .= ', ' . $config['query'] . " as `" . $metric . "`";
            }
        }

        return $select;
    }

    protected function cleanCustomDimensionValue($value)
    {
        if (isset($value) && strlen($value)) {
            return $value;
        }

        return self::LABEL_CUSTOM_VALUE_NOT_DEFINED;
    }

}
