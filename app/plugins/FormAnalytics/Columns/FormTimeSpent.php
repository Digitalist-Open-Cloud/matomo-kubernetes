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
namespace Piwik\Plugins\FormAnalytics\Columns;

use Piwik\Columns\DimensionMetricFactory;
use Piwik\Columns\MetricsList;
use Piwik\Piwik;
use Piwik\Plugin\ArchivedMetric;
use Piwik\Plugin\ComputedMetric;
use Piwik\Plugins\FormAnalytics\Metrics;

class FormTimeSpent extends BaseDimension
{
    protected $nameSingular = 'FormAnalytics_ColumnTimeSpent';
    protected $columnName = 'time_spent';

    public function __construct()
    {
        if (defined('self::TYPE_DURATION_MS')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_DURATION_MS;
        }
    }

    public function configureMetrics(MetricsList $metricsList, DimensionMetricFactory $dimensionMetricFactory)
    {
        $metric = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_SUM);
        $metric->setName(Metrics::SUM_FORM_TIME_SPENT);
        $metric->setTranslatedName(Piwik::translate('FormAnalytics_ColumnTimeSpent'));
        $metric->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionNbFormTimeSpent'));
        $metricsList->addMetric($metric);

        $metric = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_MAX);
        $metric->setName('max_form_time_spent');
        $metricsList->addMetric($metric);

        $metric = $dimensionMetricFactory->createComputedMetric(Metrics::SUM_FORM_TIME_SPENT, Metrics::SUM_FORM_STARTERS,ComputedMetric::AGGREGATION_AVG);
        $metric->setName(Metrics::AVG_FORM_TIME_SPENT);
        $metric->setTranslatedName(Piwik::translate('FormAnalytics_ColumnFormAvgTimeSpent'));
        $metric->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionAvgFormTimeSpent'));
        $metricsList->addMetric($metric);
    }

    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }
}