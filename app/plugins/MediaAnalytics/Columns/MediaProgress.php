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
use Piwik\Plugin\ComputedMetric;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Segment;

class MediaProgress extends MediaDimension
{
    protected $nameSingular = 'MediaAnalytics_MediaProgress';
    protected $columnName = 'media_progress';

    public function __construct()
    {
        if (defined('self::TYPE_DURATION_S')) {
            // only defined in > 3.1
            $this->type = self::TYPE_DURATION_S;
        }
    }

    public function configureMetrics(MetricsList $metricsList, DimensionMetricFactory $dimensionMetricFactory)
    {
        $metric4 = $dimensionMetricFactory->createMetric('sum(if(log_media.media_length > 2 AND %s >= (log_media.media_length - 2), 1, 0))');
        $metric4->setName('nb_media_finishes');
        $metric4->setTranslatedName('Finishes');
        $metric4->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnFinishes'));
        $metric4->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionFinishes'));
        $metric4->setType(Dimension::TYPE_NUMBER);
        $metricsList->addMetric($metric4);

        $metric = $dimensionMetricFactory->createComputedMetric($metric4->getName(), 'nb_media_plays', ComputedMetric::AGGREGATION_RATE);
        $metric->setName('media_finish_rate');
        $metric->setTranslatedName(Piwik::translate('MediaAnalytics_ColumnFinishRate'));
        $metric->setDocumentation(Piwik::translate('MediaAnalytics_ColumnDescriptionFinishRate'));
        $metricsList->addMetric($metric);
    }

    /**
     * The name of the dimension which will be visible for instance in the UI of a related report and in the mobile app.
     * @return string
     */
    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }
}