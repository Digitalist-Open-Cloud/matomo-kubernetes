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
 *
 */
namespace Piwik\Plugins\UsersFlow\DataTable\Filter;

use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\DataTable\BaseFilter;
use Piwik\Plugins\UsersFlow\Metrics;

class AddLabelsForMissingSteps extends BaseFilter
{

    /**
     * See {@link Limit}.
     *
     * @param DataTable $table
     */
    public function filter($table)
    {
        $numSteps = 0;
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $step = $row->getColumn('label');
            if ($step > $numSteps) {
                $numSteps = $step;
            }
        }

        for ($i = 1; $i < $numSteps; $i++) {
            if (!$table->getRowFromLabel($i . '')) {
               $table->addRow(new Row(array(Row::COLUMNS => array(
                   'label' => $i,
                   Metrics::NB_VISITS => 0,
                   Metrics::NB_EXITS => 0,
               ))));
            }
        }

    }

}
