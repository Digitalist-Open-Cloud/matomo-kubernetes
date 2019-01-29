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

namespace Piwik\Plugins\UsersFlow\Archiver;

use Piwik\Plugins\UsersFlow\Metrics;

class DataArray extends \Piwik\DataArray
{
    private $stepTables = array();

    /**
     * DataArray constructor.
     * @param int $numMaxSteps
     */
    public function __construct($numMaxSteps)
    {
        parent::__construct();

        for ($step = 1; $step <= $numMaxSteps; $step++) {
            $this->data[$step] = $this->createEmptyRow();
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
            Metrics::NB_VISITS => 0,
            Metrics::NB_EXITS => 0,
        );
    }

    public function computeMetrics($row, $step)
    {
        $this->data[$step][Metrics::NB_VISITS] += $row[Metrics::NB_VISITS];
        $this->data[$step][Metrics::NB_EXITS] += $row[Metrics::NB_EXITS];
    }

    public function setStepTable($table, $step)
    {
        $this->stepTables[$step] = $table;
    }

    public function asDataTable()
    {
        $table = parent::asDataTable();

        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            // here we merge a step table that contains all actions for that step with a step row
            $step = $row->getColumn('label');
            if (!empty($this->stepTables[$step])) {
                $row->setSubtable($this->stepTables[$step]);
            }
        }

        $this->stepTables = array();

        return $table;
    }
}
