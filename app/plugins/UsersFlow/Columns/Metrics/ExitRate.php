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
namespace Piwik\Plugins\UsersFlow\Columns\Metrics;

use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugins\UsersFlow\Metrics as PluginMetrics;

class ExitRate extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::RATE_EXIT;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('General_ColumnExitRate');
    }

    public function getDocumentation()
    {
        return Piwik::translate('UsersFlow_ColumnExitRateDocumentation');
    }

    public function compute(Row $row)
    {
        $proceeded = $this->getMetric($row, PluginMetrics::NB_EXITS);
        $hits = $this->getMetric($row, PluginMetrics::NB_VISITS);

        return Piwik::getQuotientSafe($proceeded, $hits, $precision = 2);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::NB_EXITS, PluginMetrics::NB_VISITS);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }
}