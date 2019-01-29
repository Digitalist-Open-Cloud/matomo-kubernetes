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

use Piwik\Plugins\Funnels\Metrics;
use Piwik\Plugins\Funnels\Model\FunnelsModel;
use Piwik\DataArray;

class FlowDataArray extends DataArray
{
    private $lastStepPosition = null;

    public function __construct($funnel)
    {
        parent::__construct();
        if (!empty($funnel[FunnelsModel::KEY_FINAL_STEP_POSITION])) {
            $this->lastStepPosition = $funnel[FunnelsModel::KEY_FINAL_STEP_POSITION];
        }

        $this->setFunnelSteps($funnel);
    }

    public function getNumEntries()
    {
        $entries = 0;
        foreach ($this->data as $row) {
            if (!empty($row[Metrics::NUM_STEP_ENTRIES])) {
                $entries += $row[Metrics::NUM_STEP_ENTRIES];
            }
        }
        return $entries;
    }

    public function getNumExits()
    {
        $exits = 0;
        foreach ($this->data as $label => $row) {
            if ($label == $this->lastStepPosition) {
                // we need to ignore exits from last step
                continue;
            }

            if (!empty($row[Metrics::NUM_STEP_EXITS])) {
                $exits += $row[Metrics::NUM_STEP_EXITS];
            }
        }
        return $exits;
    }

    public function getNumConversions()
    {
        $conversions = 0;

        if (!empty($this->data[$this->lastStepPosition][Metrics::NUM_STEP_VISITS_ACTUAL])) {
            $conversions = $this->data[$this->lastStepPosition][Metrics::NUM_STEP_VISITS_ACTUAL];
        }

        return $conversions;
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
                $this->data[$label] = $this->createEmptyRow();
            }

            // one more step for the actual conversion
            $this->data[$this->lastStepPosition] = $this->createEmptyRow();
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
            Metrics::NUM_STEP_VISITS_ACTUAL => 0,
            Metrics::NUM_STEP_ENTRIES => 0,
            Metrics::NUM_STEP_EXITS => 0,
        );
    }

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        $label = $row['label'];
        unset($row['label']);

        if (!isset($this->data[$label])) {
            // label === step_position
            $this->data[$label] = $this->createEmptyRow();
        }

        foreach ($row as $column => $value) {
            if (isset($this->data[$label][$column])) {
                $this->data[$label][$column] += $value;
            } else {
                $this->data[$label][$column] = $value;
            }
        }
    }

}
