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

namespace Piwik\Plugins\CustomReports\Archiver;

use Piwik\DataTable;
use Piwik\Plugins\CustomReports\Archiver;

class DataArray extends \Piwik\DataArray
{
    private $emptyRow = array();

    protected $dataThreeLevel = array();
    private $aggregations = array();

    public $doneFirstLevel = false;
    public $doneSecondLevel = false;

    /**
     * @param string[] $metrics
     */
    public function __construct($metrics)
    {
        parent::__construct($data = array(), $dataArrayByLabel = array());

        $this->aggregations = $metrics;

        foreach ($metrics as $metric => $aggregation) {
            if ($aggregation === 'min') {
                $this->emptyRow[$metric] = null;
            } else {
                $this->emptyRow[$metric] = 0;
            }
        }
    }

    /**
     * Returns an empty row containing default metrics
     *
     * @return array
     */
    public function createEmptyRow($level)
    {
        $row = $this->emptyRow;
        $row['level'] = $level;
        return $row;
    }

    protected function isEmptyLabel($label)
    {
        return !isset($label) || $label === '' || $label === false;
    }

    /**
     * @param $row
     */
    public function computeMetrics($row, $label)
    {
        if ($this->isEmptyLabel($label)) {
            $label = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = $this->createEmptyRow(1);
        }

        foreach ($row as $column => $value) {
            if (!isset($this->aggregations[$column])) {
                continue;
            }

            if (isset($this->data[$label][$column])) {
                if ($this->aggregations[$column] === 'max') {
                    $this->data[$label][$column] = max($value, $this->data[$label][$column]);
                } elseif ($this->aggregations[$column] === 'min') {
                    $this->data[$label][$column] = min($value, $this->data[$label][$column]);
                } else {
                    $this->data[$label][$column] += $value;
                }
            } else {
                $this->data[$label][$column] = $value;
            }
        }
    }

    /**
     * @param $row
     */
    public function computeMetricsLevel2($row, $label, $sublabel)
    {
        if (!isset($sublabel)) {
            return;
        }

        if ($this->isEmptyLabel($label)) {
            $label = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = $this->createEmptyRow(1);
        }

        if (!isset($this->dataTwoLevels[$label])) {
            $this->dataTwoLevels[$label] = array();
        }

        if (!isset($this->dataTwoLevels[$label][$sublabel])) {
            $this->dataTwoLevels[$label][$sublabel] = $this->createEmptyRow(2);
        }

        foreach ($row as $column => $value) {
            if (!isset($this->aggregations[$column])) {
                continue;
            }

            if (isset($this->dataTwoLevels[$label][$sublabel][$column])) {
                if ($this->aggregations[$column] === 'max') {
                    $this->dataTwoLevels[$label][$sublabel][$column] = max($value, $this->dataTwoLevels[$label][$sublabel][$column]);
                } elseif ($this->aggregations[$column] === 'min') {
                    $this->dataTwoLevels[$label][$sublabel][$column] = min($value, $this->dataTwoLevels[$label][$sublabel][$column]);
                } else {
                    $this->dataTwoLevels[$label][$sublabel][$column] += $value;
                }
            } else {
                $this->dataTwoLevels[$label][$sublabel][$column] = $value;
            }
        }
    }

    public function computeMetricsLevel3($row, $label, $sublabel, $subsublabel)
    {
        if (!isset($subsublabel)) {
            return;
        }

        if ($this->isEmptyLabel($label)) {
            $label = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = $this->createEmptyRow(1);
        }

        if (!isset($this->dataTwoLevels[$label])) {
            $this->dataTwoLevels[$label] = array();
        }

        if (!isset($this->dataTwoLevels[$label][$sublabel])) {
            $this->dataTwoLevels[$label][$sublabel] = $this->createEmptyRow(2);
        }

        if (!isset($this->dataThreeLevel[$label])) {
            $this->dataThreeLevel[$label] = array();
        }

        if (!isset($this->dataThreeLevel[$label][$sublabel])) {
            $this->dataThreeLevel[$label][$sublabel] = array();
        }

        if (!isset($this->dataThreeLevel[$label][$sublabel][$subsublabel])) {
            $this->dataThreeLevel[$label][$sublabel][$subsublabel] = $this->createEmptyRow(3);
        }

        foreach ($row as $column => $value) {
            if (!isset($this->aggregations[$column])) {
                continue;
            }

            if (isset($this->dataThreeLevel[$label][$sublabel][$subsublabel][$column])) {
                if ($this->aggregations[$column] === 'max') {
                    $this->dataThreeLevel[$label][$sublabel][$subsublabel][$column] = max($value, $this->dataThreeLevel[$label][$sublabel][$subsublabel][$column]);
                } elseif ($this->aggregations[$column] === 'min') {
                    $this->dataThreeLevel[$label][$sublabel][$subsublabel][$column] = min($value, $this->dataThreeLevel[$label][$sublabel][$subsublabel][$column]);
                } else {
                    $this->dataThreeLevel[$label][$sublabel][$subsublabel][$column] += $value;
                }

            } else {
                $this->dataThreeLevel[$label][$sublabel][$subsublabel][$column] = $value;
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
        if (!empty($this->dataThreeLevel)) {
            $subTableByParentLabel = array();

            foreach ($this->dataThreeLevel as $label => $keyTables) {
                $subTablesByKey = array();
                foreach ($keyTables as $key => $labelPerKey) {
                    $subTablesByKey[$key] = DataTable::makeFromIndexedArray($labelPerKey);
                }

                $subTableByParentLabel[$label] = DataTable::makeFromIndexedArray($this->dataTwoLevels[$label], $subTablesByKey);
            }

            return DataTable::makeFromIndexedArray($this->data, $subTableByParentLabel);
        }

        return parent::asDataTable();
    }
}
