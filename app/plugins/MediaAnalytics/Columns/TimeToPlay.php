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

use Piwik\Piwik;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Segment;

class TimeToPlay extends MediaDimension
{
    protected $nameSingular = 'MediaAnalytics_SegmentNameTimeToInitialPlay';
    protected $columnName = 'time_to_initial_play';

    public function __construct()
    {
        if (defined('self::TYPE_DURATION_S')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_DURATION_S;
        }
    }

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment(Segment::NAME_TIME_TO_PLAY);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameTimeToInitialPlay'));
        $segment->setSqlSegment('log_media.time_to_initial_play');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionTimeToInitialPlay'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('time_to_initial_play', $idSite, $maxValuesToReturn);
        });
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate($this->nameSingular);
    }
}