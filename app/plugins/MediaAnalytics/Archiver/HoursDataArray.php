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

use Piwik\Date;

class HoursDataArray extends DataArray
{
    private $startDate;
    private $timezone;

    private $labelCache = array();

    /**
     * @param string $startDateString "Y-m-d"
     * @param string $timezone
     */
    public function __construct($startDateString, $timezone)
    {
        $this->startDate = $startDateString;
        $this->timezone = $timezone;
        parent::__construct();
    }

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        if (!$this->isEmptyLabel($row['label'])) {
            $row['label'] = $this->convertTimeToLocalTimezone($row['label']);
        }

        parent::computeMetrics($row);
    }
    
    public function computeMetricsSubtable($secondaryDimension, $parentLabel, $label, $row)
    {
        if (!$this->isEmptyLabel($parentLabel)) {
            $parentLabel = $this->convertTimeToLocalTimezone($parentLabel);
        }

        parent::computeMetricsSubtable($secondaryDimension, $parentLabel, $label, $row);
    }

    protected function convertTimeToLocalTimezone($label)
    {
        if (!isset($this->labelCache[$label])) {
            $datetime = $this->startDate . ' ' . $label . ':00:00';
            $this->labelCache[$label] = (int) Date::factory($datetime, $this->timezone)->toString('H');
        }

        return $this->labelCache[$label];
    }

}
