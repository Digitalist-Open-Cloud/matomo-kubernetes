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

namespace Piwik\Plugins\FormAnalytics\Archiver;

use Piwik\DataArray;

class SimpleDataArray extends DataArray
{
    /**
     * Returns an empty row containing default metrics
     *
     * @return array
     */
    public function createEmptyRow()
    {
        return array();
    }

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        $label = $row['label'];
        unset($row['label']);

        if (!isset($this->data[$label])) {
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
