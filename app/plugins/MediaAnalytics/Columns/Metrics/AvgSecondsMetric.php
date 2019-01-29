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
namespace Piwik\Plugins\MediaAnalytics\Columns\Metrics;


use Piwik\DataTable\Row;

use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;

abstract class AvgSecondsMetric extends ProcessedMetric
{
    abstract protected function getTotalMetricName();
    abstract protected function getMetricName();
    
    public function compute(Row $row)
    {
        $revenue = $this->getMetric($row, $this->getMetricName());
        $conversions = $this->getMetric($row, $this->getTotalMetricName());

        return Piwik::getQuotientSafe($revenue, $conversions, $precision = 0);
    }

    public function getDependentMetrics()
    {
        return array($this->getTotalMetricName(), $this->getMetricName());
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyTimeFromSeconds($value, 1);
    }

    public function getTemporaryMetrics()
    {
        return array($this->getMetricName());
    }
}