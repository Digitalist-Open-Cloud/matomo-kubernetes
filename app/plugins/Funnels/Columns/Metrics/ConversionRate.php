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
namespace Piwik\Plugins\Funnels\Columns\Metrics;

use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugins\Funnels\Metrics as PluginMetrics;

class ConversionRate extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::RATE_CONVERSION;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('Funnels_ColumnRateFunnelConversion');
    }

    public function getDocumentation()
    {
        return Piwik::translate('Funnels_ColumnRateFunnelConversionDocumentation');
    }

    public function compute(Row $row)
    {
        $conversions = $this->getMetric($row, PluginMetrics::NUM_CONVERSIONS);
        $entries = $this->getMetric($row, PluginMetrics::SUM_FUNNEL_ENTRIES);

        return Piwik::getQuotientSafe($conversions, $entries, $precision = 3);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::NUM_CONVERSIONS, PluginMetrics::SUM_FUNNEL_ENTRIES);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }
}