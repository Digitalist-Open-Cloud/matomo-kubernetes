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
use Piwik\DataTable\Row;
use Piwik\Plugins\MediaAnalytics\Archiver;

class ResourceDataArray extends \Piwik\DataArray
{
    private $computedDataTable = null;

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        $fullLabel = $row['label'];
        unset($row['label']);

        if (empty($fullLabel)) {
            $label = Archiver::LABEL_NOT_DEFINED;
            $subLabel = null;
        } else {
            $parts = $this->getResourceParts($fullLabel);
            $label = $parts['host'];
            $subLabel = $parts['resource'];
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = DataArray::createEmptyRow();
        }

        if ($subLabel) {
            if (!isset($this->dataTwoLevels[$label])) {
                $this->dataTwoLevels[$label] = array();
            }

            if (!isset($this->dataTwoLevels[$label][$subLabel])) {
                $this->dataTwoLevels[$label][$subLabel] = DataArray::createEmptyRow();
            }

            $this->dataTwoLevels[$label][$subLabel]['url'] = $fullLabel;
        }

        foreach ($row as $column => $value) {
            if (isset($this->data[$label][$column])) {
                $this->data[$label][$column] += $value;
            } else {
                $this->data[$label][$column] = $value;
            }

            if ($subLabel) {
                if (isset($this->dataTwoLevels[$label][$subLabel][$column])) {
                    $this->dataTwoLevels[$label][$subLabel][$column] += $value;
                } else {
                    $this->dataTwoLevels[$label][$subLabel][$column] = $value;
                }
            }
        }
    }

    public function computeMetricsSubtable($secondaryDimension, $parentLabel, $label, $row)
    {
        if (empty($parentLabel)) {
            return; // we do not compute subtable metrics for unknown labels
        }

        // this works only if for all rows first computeMetrics is called!
        $dataTable = $this->asDataTable();

        $parts = $this->getResourceParts($parentLabel);
        $parentLabel = $parts['host'];
        $subLabel = $parts['resource'];

        $firstLevelRow = $dataTable->getRowFromLabel($parentLabel);

        if (!$firstLevelRow) {
            return;
        }

        $parentSubtable = $firstLevelRow->getSubtable();

        if (!$parentSubtable) {
            return;
        }

        $subLabelRow = $parentSubtable->getRowFromLabel($subLabel);

        if (!$subLabelRow) {
            return;
        }

        $subTable = $subLabelRow->getSubtable();
        if (!$subTable) {
            $subTable = new DataTable();
            $subLabelRow->setSubtable($subTable);
        }

        $secondaryRow = $subTable->getRowFromLabel($secondaryDimension);
        if (!$secondaryRow) {
            $secondaryRow = new Row(array(Row::COLUMNS => array('label' => $secondaryDimension)));
            $subTable->addRow($secondaryRow);
        }

        $secondarySubtable = $secondaryRow->getSubtable();

        if (!$secondarySubtable) {
            $secondarySubtable = new DataTable();
            $secondaryRow->setSubtable($secondarySubtable);
        }

        $rowToSum = $secondarySubtable->getRowFromLabel($label);

        if (!$rowToSum) {
            $rowToSum = new Row(array(Row::COLUMNS => array('label' => $label)));
            $secondarySubtable->addRow($rowToSum);
        }

        $rowToSumColumns = $rowToSum->getColumns();

        foreach ($row as $column => $value) {
            if ($column !== 'label') {
                if (isset($rowToSum[$column])) {
                    $rowToSumColumns[$column] += $value;
                } else {
                    $rowToSumColumns[$column] = $value;
                }
            }
        }

        $rowToSum->setColumns($rowToSumColumns);
    }

    /**
     * Converts array to a datatable
     *
     * @return \Piwik\DataTable
     */
    public function asDataTable()
    {
        if (!isset($this->computedDataTable)) {
            $this->computedDataTable = parent::asDataTable();
        }
        return $this->computedDataTable;
    }

    protected function getResourceParts($resource)
    {
        if (empty($resource)) {
            return $resource;
        }

        $resource = strtolower($resource);
        $parsed = parse_url($resource);

        $resource = '/';

        if (isset($parsed['path'])) {
            $resource .= trim($parsed['path'], '/');
        }

        if (isset($parsed['query'])) {
            $resource .= '?' . $parsed['query'];
        }

        return array(
            'host' => isset($parsed['host']) ? $parsed['host'] : '',
            'resource' => $resource
        );
    }

}
