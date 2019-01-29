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
use Piwik\Plugin\ComputedMetric;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Segment;

class MediaLength extends MediaDimension
{
    protected $nameSingular = 'MediaAnalytics_SegmentNameMediaLength';
    protected $columnName = 'media_length';
    protected $acceptValues = 'MediaAnalytics_SegmentDescriptionMediaLength';
    protected $segmentName = Segment::NAME_MEDIA_LENGTH;

    public function __construct()
    {
        if (defined('self::TYPE_DURATION_S')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_DURATION_S;
        }
    }

    public function configureMetrics(MetricsList $metricsList, DimensionMetricFactory $dimensionMetricFactory)
    {
        $metric1 = $dimensionMetricFactory->createMetric('sum(if(log_media.watched_time > 0, %s, 0))');
        $metric1->setName('sum_media_length');
        $metric1->setTranslatedName(Piwik::translate('General_ComputedMetricSum', Piwik::translate('MediaAnalytics_SegmentNameMediaLength')));
        $metricsList->addMetric($metric1);

        $metric2 = $dimensionMetricFactory->createMetric('max(if(log_media.watched_time > 0, %s, 0))');
        $metric2->setName('max_media_length');
        $metric2->setTranslatedName(Piwik::translate('General_ComputedMetricMax', Piwik::translate('MediaAnalytics_SegmentNameMediaLength')));
        $metricsList->addMetric($metric2);

        $metric = $dimensionMetricFactory->createComputedMetric($metric1->getName(), 'nb_media_plays',ComputedMetric::AGGREGATION_AVG);
        $metric->setName('avg_media_length');
        $metric->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnAvgMediaLength'));
        $metric->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionAvgMediaLength'));
        $metricsList->addMetric($metric);
    }

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment(Segment::NAME_MEDIA_LENGTH);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaLength'));
        $segment->setSqlSegment('log_media.media_length');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaLength'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('media_length', $idSite, $maxValuesToReturn);
        });
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }
}