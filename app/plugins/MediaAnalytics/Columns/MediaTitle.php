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
use Piwik\Piwik;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Segment;

class MediaTitle extends MediaDimension
{
    protected $nameSingular = 'MediaAnalytics_MediaTitle';
    protected $namePlural = 'MediaAnalytics_MediaTitles';
    protected $columnName = 'media_title';

    public function __construct()
    {
        if (defined('self::TYPE_TEXT')) {
            // only defined in Matomo 3.0.5 or 3.1
            $this->type = self::TYPE_TEXT;
        }
    }

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment(Segment::NAME_MEDIA_TITLE);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaTitle'));
        $segment->setSqlSegment('log_media.media_title');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaTitle'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('media_title', $idSite, $maxValuesToReturn);
        });
        $segment->setSqlFilterValue(function ($value) {
            if ($value === 'Unknown' || $value === Piwik::translate('General_Unknown')) {
                $value = '';
            }

            return $value;
        });
        $this->addSegment($segment);
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