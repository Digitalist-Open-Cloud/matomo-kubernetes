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

namespace Piwik\Plugins\MediaAnalytics\Archiver;

use Piwik\DataTable;
use Piwik\Plugins\MediaAnalytics\Archiver;
use Piwik\Plugins\MediaAnalytics\Metrics;

class DataArray extends \Piwik\DataArray
{
    protected $dataThreeLevel = array();

    /**
     * Returns an empty row containing default metrics
     *
     * @return array
     */
    public static function createEmptyRow()
    {
        return array(
            Metrics::METRIC_NB_PLAYS => 0,
            Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS => 0,
            Metrics::METRIC_NB_IMPRESSIONS => 0,
            Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS => 0,
            Metrics::METRIC_NB_FINISHES => 0,
            Metrics::METRIC_SUM_MEDIA_LENGTH => 0,
            Metrics::METRIC_SUM_TIME_WATCHED => 0,
            Metrics::METRIC_SUM_TIME_TO_PLAY => 0,
            Metrics::METRIC_SUM_TIME_PROGRESS => 0,
            Metrics::METRIC_NB_PLAYS_WITH_TIME_TO_INITIAL_PLAY => 0,
            Metrics::METRIC_NB_PLAYS_WITH_MEDIA_LENGTH => 0,
            Metrics::METRIC_SUM_FULLSCREEN_PLAYS => 0,
        );
    }

    protected function isEmptyLabel($label)
    {
        return !isset($label) || $label === '' || $label === false;
    }

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        $label = $row['label'];

        if ($this->isEmptyLabel($label)) {
            $label = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = self::createEmptyRow();
        }

        foreach ($row as $column => $value) {
            if ($column !== 'label') {
                if (isset($this->data[$label][$column])) {
                    $this->data[$label][$column] += $value;
                } else {
                    $this->data[$label][$column] = $value;
                }
            }
        }
    }

    public function computeMetricsSubtable($secondaryDimension, $parentLabel, $label, $row)
    {
        if ($this->isEmptyLabel($parentLabel)) {
            $parentLabel = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->dataThreeLevel[$parentLabel])) {
            $this->dataThreeLevel[$parentLabel] = array();
        }

        if (!isset($this->dataThreeLevel[$parentLabel][$secondaryDimension])) {
            $this->dataThreeLevel[$parentLabel][$secondaryDimension] = array();
        }

        if (!isset($this->dataThreeLevel[$parentLabel][$secondaryDimension][$label])) {
            $this->dataThreeLevel[$parentLabel][$secondaryDimension][$label] = array();
        }

        foreach ($row as $column => $value) {
            if ($column !== 'label') {
                if (isset($this->dataThreeLevel[$parentLabel][$secondaryDimension][$label][$column])) {
                    $this->dataThreeLevel[$parentLabel][$secondaryDimension][$label][$column] += $value;
                } else {
                    $this->dataThreeLevel[$parentLabel][$secondaryDimension][$label][$column] = $value;
                }
            }
        }
    }

    public function getThirdLevelData()
    {
        return $this->dataThreeLevel;
    }

    /**
     * Converts array to a datatable
     *
     * @return \Piwik\DataTable
     */
    public function asDataTable()
    {
        $subTableByParentLabel = null;

        if (!empty($this->dataThreeLevel)) {
            $subTableByParentLabel = array();

            foreach ($this->dataThreeLevel as $label => $keyTables) {

                // => Eg array('Hours' => array(), 'Resolution' => array(), 'WatchedTime' => array(), ...). As these
                // rows are only there to reference the actual subtable we don't need to old an actual value for it
                // it will be later only array('label' => 'Hours', 'idSubtable' => 22), array('label' => 'Resolution', idSubtable => 23, ..)
                $keysOnlyTable = array_fill_keys(array_keys($keyTables), array());

                if (!empty($keyTables)) {
                    $subTablesByKey = array();
                    foreach ($keyTables as $key => $labelPerKey) {
                        $subTablesByKey[$key] = DataTable::makeFromIndexedArray($labelPerKey);
                    }

                    $subTableByParentLabel[$label] = DataTable::makeFromIndexedArray($keysOnlyTable, $subTablesByKey);
                } else {
                    // there is no subtable to write because they are only references
                    // $subTableByParentLabel[$label] = DataTable::makeFromIndexedArray($keysOnlyTable);
                }
            }
        }
        return DataTable::makeFromIndexedArray($this->data, $subTableByParentLabel);
    }

}
