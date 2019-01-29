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
namespace Piwik\Plugins\MediaAnalytics\Columns;

use Piwik\Columns\DimensionMetricFactory;
use Piwik\Columns\MetricsList;
use Piwik\Piwik;
use Piwik\Plugin\ArchivedMetric;

class IdVisit extends MediaDimension
{
    protected $nameSingular = 'MediaAnalytics_ColumnImpressions';
    protected $columnName = 'idvisit';

    public function __construct()
    {
        if (defined('self::TYPE_NUMBER')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_NUMBER;
        }
    }

    public function configureMetrics(MetricsList $metricsList, DimensionMetricFactory $dimensionMetricFactory)
    {
        $metric = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_COUNT);
        $metric->setName('nb_media_impressions');
        $metric->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnImpressions'));
        $metric->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionImpressions'));
        $metricsList->addMetric($metric);

        $metric = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_UNIQUE);
        $metric->setName('nb_media_uniq_impressions');
        $metric->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnImpressionsByUniqueVisitors'));
        $metric->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionImpressionsByUniqueVisitors'));
        $metricsList->addMetric($metric);
    }

    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }
}