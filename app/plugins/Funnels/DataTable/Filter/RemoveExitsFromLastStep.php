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
use Piwik\Plugins\Funnels\Model\FunnelsModel;

class RemoveExitsFromLastStep extends DataTable\BaseFilter
{
    /**
     * @var null|int
     */
    private $lastStepPosition;

    public function __construct(DataTable $table, $funnel)
    {
        parent::__construct($table);

        if (!empty($funnel[FunnelsModel::KEY_FINAL_STEP_POSITION])) {
            $this->lastStepPosition = $funnel[FunnelsModel::KEY_FINAL_STEP_POSITION];
        }
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $row = $table->getRowFromLabel($this->lastStepPosition);
        if (!empty($row)) {
            $row->setColumn(Metrics::NUM_STEP_EXITS, '-');
        }
    }
}