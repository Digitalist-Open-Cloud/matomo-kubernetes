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
namespace Piwik\Plugins\Funnels\DataTable\Filter;

use Piwik\DataTable;
use Piwik\Plugins\Funnels\Metrics;

class ComputeBackfills extends DataTable\BaseFilter
{
    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $steps = $table->getColumn('label');

        if (empty($steps)) {
            return;
        }

        $lastNumProceeded = 0;

        $numSteps = count($steps);

        foreach ($steps as $index => $label) {
            $row = $table->getRowFromLabel($label);

            if (empty($row)) {
                $lastNumProceeded = 0;
                continue; // should never happen
            }

            $isLastStep = $numSteps === $index + 1;

            $numHits = (int) $row->getColumn(Metrics::NUM_STEP_VISITS_ACTUAL);
            $numExits = (int) $row->getColumn(Metrics::NUM_STEP_EXITS);

            $numStepsBackfilled = $numHits;
            if ($lastNumProceeded > $numHits) {
                $numStepsBackfilled = $lastNumProceeded;
            }

            $row->setColumn(Metrics::NUM_STEP_VISITS, $numStepsBackfilled);

            $numProceededBackfilled = (int) $numStepsBackfilled - $numExits;

            if ($isLastStep) {
                $row->setColumn(Metrics::NUM_STEP_PROCEEDED, 0);
            } else {
                $row->setColumn(Metrics::NUM_STEP_PROCEEDED, $numProceededBackfilled);
            }

            $lastNumProceeded = $numProceededBackfilled;
        }

    }
}