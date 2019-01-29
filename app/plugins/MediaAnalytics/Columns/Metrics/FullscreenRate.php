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

class FullscreenRate extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::METRIC_FULLSCREEN_RATE;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('MediaAnalytics_ColumnFullscreenPlays');
    }

    public function getDocumentation()
    {
        return Piwik::translate('MediaAnalytics_ColumnDescriptionFullscreenPlays');
    }

    public function compute(Row $row)
    {
        $fullscreenPlays = $this->getMetric($row, PluginMetrics::METRIC_SUM_FULLSCREEN_PLAYS);
        $plays = $this->getMetric($row, PluginMetrics::METRIC_NB_PLAYS);

        return Piwik::getQuotientSafe($fullscreenPlays, $plays, $precision = 2);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::METRIC_NB_PLAYS, PluginMetrics::METRIC_SUM_FULLSCREEN_PLAYS);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }
}