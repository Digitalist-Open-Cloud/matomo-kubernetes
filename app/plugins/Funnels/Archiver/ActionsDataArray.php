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

namespace Piwik\Plugins\Funnels\Archiver;

use Piwik\DataTable;
use Piwik\Plugins\Funnels\Archiver;
use Piwik\Plugins\Funnels\Metrics;
use Piwik\Plugins\Funnels\Model\FunnelsModel;
use Piwik\DataArray;

class ActionsDataArray extends DataArray
{
    /**
     * @var null|DataTable
     */
    private $referersTable;

    public function __construct($funnel)
    {
        parent::__construct();
        $this->setFunnelSteps($funnel);
    }

    /**
     * We need to make sure to always have a row for all steps, otherwise backfilling does not work correctly
     * @param $funnel
     */
    private function setFunnelSteps($funnel)
    {
        if (!empty($funnel['steps'])) {
            foreach ($funnel['steps'] as $step) {
                $label = $step['position'];
                $this->data[$label] = array();
            }

            // one more step for the actual conversion
            $this->data[$funnel[FunnelsModel::KEY_FINAL_STEP_POSITION]] = array();
        }
    }

    /**
     * Returns an empty row containing default metrics
     *
     * @return array
     */
    public function createEmptyRow()
    {
        return array(
            Metrics::NUM_HITS => 0
        );
    }

    /**
     * @param $table
     */
    public function setReferersTable(DataTable $table)
    {
        $this->referersTable = $table;
    }

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        $stepPosition = $row['label'];
        $sublabel = $row['sublabel'];
        unset($row['label']);
        unset($row['sublabel']);

        if (!empty($sublabel)) {
            $sublabel = rtrim($sublabel, '/');
        }

        if (!isset($this->data[$stepPosition])) {
            // label === step_position
            $this->data[$stepPosition] = array();
        }

        if (!isset($this->dataTwoLevels[$stepPosition])) {
            // label === step_position
            $this->dataTwoLevels[$stepPosition] = array();
        }

        if (!isset($this->dataTwoLevels[$stepPosition][$sublabel])) {
            // label === step_position
            $this->dataTwoLevels[$stepPosition][$sublabel] = $this->createEmptyRow();
        }

        foreach ($row as $column => $value) {
            $hasColumn = isset($this->dataTwoLevels[$stepPosition][$sublabel][$column]);

            if ($hasColumn && $column == 'referer_type') {
                if ($this->dataTwoLevels[$stepPosition][$sublabel][$column] != $value) {
                    // edge case. when two different referrer types have the same label, instead of aggregating we unset the
                    // referer_type as it cannot be clearly assigned to either.
                    $this->dataTwoLevels[$stepPosition][$sublabel][$column] = '';
                }
            } elseif ($hasColumn) {
                $this->dataTwoLevels[$stepPosition][$sublabel][$column] += $value;
            } else {
                $this->dataTwoLevels[$stepPosition][$sublabel][$column] = $value;
            }
        }
    }

    public function asDataTable()
    {
        $table = parent::asDataTable();

        if (!empty($this->referersTable)) {
            foreach ($table->getRowsWithoutSummaryRow() as $row) {
                $step = $row->getColumn('label');

                if ($subtable = $row->getSubtable()) {
                    $rowVisitEntry = $subtable->getRowFromLabel(Archiver::LABEL_VISIT_ENTRY);
                    // for visit entries, we want to set a subtable listing all referrers

                    if (!empty($rowVisitEntry)) {
                        // find matching subtable for that step from referrers table
                        $refererStepRow = $this->referersTable->getRowFromLabel($step);

                        if (!empty($rowVisitEntry) && !empty($refererStepRow) && $refererStepRow->getSubtable()) {
                            $rowVisitEntry->setSubtable($refererStepRow->getSubtable());
                        }
                    }

                }
            }

            unset($this->referersTable);
        }

        return $table;
    }

}
