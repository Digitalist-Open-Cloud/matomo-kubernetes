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

class StepDataArray extends \Piwik\DataArray
{
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

    public function computeMetrics($row)
    {
        $label = $row['label'];
        unset($row['label']);

        $nextLabel = $row['nextLabel'];
        unset($row['nextLabel']);

        if (empty($label)) {
            return;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = $this->createEmptyRow();
        }

        $this->data[$label][Metrics::NB_VISITS] += $row[Metrics::NB_VISITS];
        $this->data[$label][Metrics::NB_EXITS] += $row[Metrics::NB_EXITS];

        if (!empty($nextLabel)) {
            if (!isset($this->dataTwoLevels[$label][$nextLabel])) {
                $this->dataTwoLevels[$label][$nextLabel] = array(Metrics::NB_VISITS => 0);
            }

            $this->dataTwoLevels[$label][$nextLabel][Metrics::NB_VISITS] += $row[Metrics::NB_VISITS];
        }
    }

}
