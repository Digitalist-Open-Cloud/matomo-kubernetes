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
use Piwik\Plugins\FormAnalytics\Metrics;

class FormViews extends BaseDimension
{
    protected $nameSingular = 'FormAnalytics_ColumnFormViews';
    protected $columnName = 'num_views';

    public function __construct()
    {
        if (defined('self::TYPE_NUMBER')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_NUMBER;
        }
    }

    public function configureMetrics(MetricsList $metricsList, DimensionMetricFactory $dimensionMetricFactory)
    {
        $metric1 = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_COUNT);
        $metric1->setName(Metrics::SUM_FORM_VIEWERS);
        $metric1->setTranslatedName(Piwik::translate('FormAnalytics_ColumnFormViewers'));
        $metric1->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionNbFormViewers'));
        $metricsList->addMetric($metric1);

        $metric2 = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_SUM);
        $metric2->setTranslatedName(Piwik::translate('FormAnalytics_ColumnFormViews'));
        $metric2->setDocumentation(Piwik::translate('FormAnalytics_ColumnDescriptionNbFormViews'));
        $metric2->setName(Metrics::SUM_FORM_VIEWS);
        $metricsList->addMetric($metric2);

        $metric3 = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_MAX);
        $metric3->setName('max_form_views');
        $metricsList->addMetric($metric3);
    }
    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }
}