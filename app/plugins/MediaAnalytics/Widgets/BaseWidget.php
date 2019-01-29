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
namespace Piwik\Plugins\MediaAnalytics\Widgets;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Plugins\MediaAnalytics\Segment;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

abstract class BaseWidget extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setOrder(99);
        $config->setCategoryId('MediaAnalytics_Media');
    }

    protected static function getIdSite()
    {
        return Common::getRequestVar('idSite', false, 'int');
    }

    private static function getRawSegment()
    {
        return Request::getRawSegmentFromRequest();
    }
    public static function isUsingDefaultSegment()
    {
        $segment = self::getRawSegment();
        return empty($segment);
    }

    public static function getMediaSegment()
    {
        $segment = self::getRawSegment();

        if (!empty($segment) && strpos($segment, Segment::NAME_SPENT_TIME) !== false) {
            // do not modify segment in case it already contains this segment
            return $segment;
        }

        $mediaSegment = Segment::NAME_SPENT_TIME . '>0';

        if (!empty($segment)) {
            $mediaSegment .= ';' . urldecode($segment);
        }

        return urlencode($mediaSegment);
    }
}