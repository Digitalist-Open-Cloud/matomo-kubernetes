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

use Piwik\Piwik;
use Piwik\Plugins\MediaAnalytics\Metrics as PluginMetrics;

class AvgTimeWatched extends AvgSecondsMetric
{
    protected function getMetricName()
    {
        return PluginMetrics::METRIC_SUM_TIME_WATCHED;
    }

    protected function getTotalMetricName()
    {
        return PluginMetrics::METRIC_NB_PLAYS;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('MediaAnalytics_ColumnAvgTimeWatched');
    }

    public function getDocumentation()
    {
        return Piwik::translate('MediaAnalytics_ColumnDescriptionAvgTimeWatched');
    }

    public function getName()
    {
        return PluginMetrics::METRIC_AVG_TIME_WATCHED;
    }

}