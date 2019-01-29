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

class FormAvgTimeToFirstSubmission extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::AVG_FORM_TIME_TO_FIRST_SUBMISSION;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('FormAnalytics_ColumnAvgTimeToFirstSubmit');
    }

    public function compute(Row $row)
    {
        $timeToSubmission = $this->getMetric($row, PluginMetrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION);

        if (!empty($timeToSubmission)) {
            $timeToSubmission = $timeToSubmission / 1000; // => convert from ms to seconds
        }

        $numSubmissions = $this->getMetric($row, PluginMetrics::SUM_FORM_SUBMITTERS);

        return Piwik::getQuotientSafe($timeToSubmission, $numSubmissions, $precision = 3);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION, PluginMetrics::SUM_FORM_SUBMITTERS);
    }

    public function getTemporaryMetrics()
    {
        return array(PluginMetrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION);
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