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

use Piwik\Columns\Dimension;
use Piwik\Columns\DimensionMetricFactory;
use Piwik\Columns\MetricsList;
use Piwik\Piwik;
use Piwik\Plugin\ArchivedMetric;
use Piwik\Plugin\ComputedMetric;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Segment;

class SpentTime extends MediaDimension
{
    protected $nameSingular = 'MediaAnalytics_SegmentNameSpentTime';
    protected $columnName = 'watched_time';

    public function __construct()
    {
        if (defined('self::TYPE_DURATION_S')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_DURATION_S;
        }
    }

    public function configureMetrics(MetricsList $metricsList, DimensionMetricFactory $dimensionMetricFactory)
    {
        $metric1 = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_SUM);
        $metricsList->addMetric($metric1);

        $metric2 = $dimensionMetricFactory->createMetric(ArchivedMetric::AGGREGATION_MAX);
        $metricsList->addMetric($metric2);

        $metric3 = $dimensionMetricFactory->createMetric('sum(if(%s > 0, 1, 0))');
        $metric3->setName('nb_media_plays');
        $metric3->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnPlays'));
        $metric3->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionPlays'));
        $metric3->setType(Dimension::TYPE_NUMBER);
        $metricsList->addMetric($metric3);

        $metric = $dimensionMetricFactory->createComputedMetric('nb_media_plays', 'nb_media_impressions', ComputedMetric::AGGREGATION_RATE);
        $metric->setName('media_play_rate');
        $metric->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnPlayRate'));
        $metric->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionPlayRate'));
        $metricsList->addMetric($metric);

        $metric = $dimensionMetricFactory->createComputedMetric($metric1->getName(), $metric3->getName(), ComputedMetric::AGGREGATION_AVG);
        $metric->setName('avg_spent_time');
        $metric->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnAvgTimeWatched'));
        $metric->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionAvgTimeWatched'));
        $metricsList->addMetric($metric);

        $metric = $dimensionMetricFactory->createComputedMetric($metric1->getName(), 'sum_media_length', ComputedMetric::AGGREGATION_RATE);
        $metric->setName('avg_media_completion');
        $metric->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnCompletion'));
        $metric->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionCompletion'));
        $metricsList->addMetric($metric);
    }

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment(Segment::NAME_SPENT_TIME);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameSpentTime'));
        $segment->setSqlSegment('log_media.watched_time');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionSpentTime'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('watched_time', $idSite, $maxValuesToReturn);
        });
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }
}