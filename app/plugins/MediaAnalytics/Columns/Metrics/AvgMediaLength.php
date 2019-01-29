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

class AvgMediaLength extends AvgSecondsMetric
{
    protected function getMetricName()
    {
        return PluginMetrics::METRIC_SUM_MEDIA_LENGTH;
    }

    protected function getTotalMetricName()
    {
        return PluginMetrics::METRIC_NB_PLAYS_WITH_MEDIA_LENGTH;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('MediaAnalytics_ColumnAvgMediaLength');
    }

    public function getDocumentation()
    {
        return Piwik::translate('MediaAnalytics_ColumnDescriptionAvgMediaLength');
    }

    public function getName()
    {
        return PluginMetrics::METRIC_AVG_MEDIA_LENGTH;
    }

}