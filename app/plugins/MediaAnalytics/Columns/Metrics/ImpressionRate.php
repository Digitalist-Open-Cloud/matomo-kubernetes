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
use Piwik\Plugins\MediaAnalytics\Metrics as PluginMetrics;

class ImpressionRate extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::METRIC_IMPRESSION_RATE;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('MediaAnalytics_ColumnImpressionRate');
    }

    public function getDocumentation()
    {
        return Piwik::translate('MediaAnalytics_ColumnDescriptionImpressionRate');
    }

    public function compute(Row $row)
    {
        $impressions = $this->getMetric($row, PluginMetrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS);
        $visitors = $this->getMetric($row, PluginMetrics::METRIC_NB_UNIQUE_VISITORS);

        return Piwik::getQuotientSafe($impressions, $visitors, $precision = 2);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::METRIC_NB_UNIQUE_VISITORS, PluginMetrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS);
    }

    public function getTemporaryMetrics()
    {
        return array();
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }
}