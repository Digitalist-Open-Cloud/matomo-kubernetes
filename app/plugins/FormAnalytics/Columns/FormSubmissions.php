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

class FormSubmissions extends BaseDimension
{
    protected $nameSingular = 'FormAnalytics_ColumnFormSubmissions';
    protected $columnName = 'num_submissions';

    public function __construct()
    {
        if (defined('self::TYPE_NUMBER')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_NUMBER;
        }
    }

    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }

    public function configureMetrics(MetricsList $metricsList, DimensionMetricFactory $dimensionMetricFactory)
    {
        $metric1 = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_SUM);
        $metric1->setName(Metrics::SUM_FORM_SUBMISSIONS);
        $metric1->setTranslatedName(Piwik::translate('FormAnalytics_ColumnFormSubmissions'));
        $metric1->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionNbFormSubmissions'));
        $metricsList->addMetric($metric1);

        $metric4 = $dimensionMetricFactory->createMetric('sum(if(%s > 0, 1, 0))');
        $metric4->setName(Metrics::SUM_FORM_SUBMITTERS);
        $metric4->setTranslatedName(Piwik::translate('FormAnalytics_ColumnFormSubmitters'));
        $metric4->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionNbFormSubmitters'));
        $metricsList->addMetric($metric4);

        $metric3 = $dimensionMetricFactory->createMetric('sum(if(%s > 1, 1, 0))');
        $metric3->setName(Metrics::SUM_FORM_RESUBMITTERS);
        $metric3->setTranslatedName(Piwik::translate('FormAnalytics_ColumnFormResubmitters'));
        $metric3->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionNbFormResubmitters'));
        $metricsList->addMetric($metric3);

        $metric = $dimensionMetricFactory->createComputedMetric(Metrics::SUM_FORM_RESUBMITTERS, Metrics::SUM_FORM_SUBMITTERS,ComputedMetric::AGGREGATION_RATE);
        $metric->setName(Metrics::RATE_FORM_RESUBMITTERS);
        $metric->setTranslatedName(Piwik::translate('FormAnalytics_ColumnRateResubmitter'));
        $metric->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionNbFormResubmitters'));
        $metricsList->addMetric($metric);
    }
}