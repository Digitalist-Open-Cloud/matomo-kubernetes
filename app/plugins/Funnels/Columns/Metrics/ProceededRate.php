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

class ProceededRate extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::RATE_PROCEEDED;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('Funnels_ColumnProceededRate');
    }

    public function getDocumentation()
    {
        return Piwik::translate('Funnels_ColumnProceededRateDocumentation');
    }

    public function compute(Row $row)
    {
        $proceeded = $this->getMetric($row, PluginMetrics::NUM_STEP_PROCEEDED);
        $hits = $this->getMetric($row, PluginMetrics::NUM_STEP_VISITS);

        return Piwik::getQuotientSafe($proceeded, $hits, $precision = 2);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::NUM_STEP_PROCEEDED, PluginMetrics::NUM_STEP_VISITS);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }
}