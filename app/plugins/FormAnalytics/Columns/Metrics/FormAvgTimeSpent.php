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
namespace Piwik\Plugins\FormAnalytics\Columns\Metrics;

use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugins\FormAnalytics\Metrics as PluginMetrics;

class FormAvgTimeSpent extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::AVG_FORM_TIME_SPENT;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('FormAnalytics_ColumnFormAvgTimeSpent');
    }

    public function compute(Row $row)
    {
        $spentTime = $this->getMetric($row, PluginMetrics::SUM_FORM_TIME_SPENT);

        if (!empty($spentTime)) {
            $spentTime = $spentTime / 1000; // convert ms to seconds
        }

        $numInteractions = $this->getMetric($row, PluginMetrics::SUM_FORM_STARTERS);

        return Piwik::getQuotientSafe($spentTime, $numInteractions, $precision = 3);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::SUM_FORM_TIME_SPENT, PluginMetrics::SUM_FORM_STARTERS);
    }

    public function getTemporaryMetrics()
    {
        return array(PluginMetrics::SUM_FORM_TIME_SPENT);
    }

    public function format($value, Formatter $formatter)
    {
        if ($value >= 30) {
            $value = (int) $value;
        } elseif ($value >= 2) {
            $value = round($value, 1);
        } else {
            $value = round($value, 2);
        }

        return $formatter->getPrettyTimeFromSeconds($value, $asSentence = true);
    }
}