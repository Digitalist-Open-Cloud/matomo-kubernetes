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
use Piwik\Plugins\Funnels\Model\FunnelsModel;

class ReplaceFunnelStepLabel extends DataTable\BaseFilter
{
    /**
     * @var array
     */
    private $stepsToNames = array();

    public function __construct(DataTable $table, $funnel)
    {
        parent::__construct($table);

        if (!empty($funnel['steps'])) {
            foreach ($funnel['steps'] as $step) {
                $this->stepsToNames[$step['position']] = $step['name'];
            }
        }

        if (!empty($funnel[FunnelsModel::KEY_FINAL_STEP_POSITION])) {
            // final step is the funnel conversion for the goal conversion
            $this->stepsToNames[$funnel[FunnelsModel::KEY_FINAL_STEP_POSITION]] = $funnel['name'];
        }
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $stepPosition = $row->getColumn('label');
            $row->setMetadata('step_position', $stepPosition);

            if (!empty($this->stepsToNames[$stepPosition])) {
                $row->setColumn('label', $this->stepsToNames[$stepPosition]);
            }
        }

        $table->setLabelsHaveChanged();
    }
}