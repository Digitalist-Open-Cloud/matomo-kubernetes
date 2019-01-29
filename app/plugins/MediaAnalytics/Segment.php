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
namespace Piwik\Plugins\MediaAnalytics;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Segment\SegmentExpression;

/**
 * MediaAnalytics segment base class
 */
class Segment extends \Piwik\Plugin\Segment
{
    const NAME_MEDIA_TITLE = 'media_title';
    const NAME_RESOURCE = 'media_resource';
    const NAME_MEDIA_IMPRESSION_TYPE = 'media_impression_type';
    const NAME_MEDIA_PLAYS_TYPE = 'media_plays_type';
    const NAME_SPENT_TIME = 'media_spent_time';
    const NAME_TIME_TO_PLAY = 'media_time_initial_play';
    const NAME_MEDIA_LENGTH = 'media_length';
    const NAME_MEDIA_PLAYER = 'media_player';

    public static function getAllSegmentNames()
    {
        return array(
            self::NAME_MEDIA_TITLE,
            self::NAME_RESOURCE,
            self::NAME_MEDIA_IMPRESSION_TYPE,
            self::NAME_MEDIA_PLAYS_TYPE,
            self::NAME_SPENT_TIME,
            self::NAME_TIME_TO_PLAY,
            self::NAME_MEDIA_LENGTH,
            self::NAME_MEDIA_PLAYER,
        );
    }

    protected function init()
    {
        $this->setCategory('MediaAnalytics_Media');
    }

    public static function getMediaTypePlays($valueToMatch, $sqlField, $matchType, $segmentName)
    {
        $sql = 'SELECT idview FROM ' . Common::prefixTable('log_media') . ' WHERE watched_time > 0 AND ';

        switch ($matchType) {
            case SegmentExpression::MATCH_NOT_EQUAL:
                $where = ' media_type != ? ';
                break;
            case SegmentExpression::MATCH_EQUAL:
                $where = ' media_type = ? ';
                break;
            case SegmentExpression::MATCH_CONTAINS:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' media_type LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_DOES_NOT_CONTAIN:
                $where = ' media_type NOT LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_STARTS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' media_type LIKE CONCAT(?, \'%\') ';
                break;
            case SegmentExpression::MATCH_ENDS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' media_type LIKE CONCAT(\'%\', ?) ';
                break;
            default:
                throw new \Exception("This match type $matchType is not available for MediaAnalytics segments.");
                break;
        }

        $sql .= $where;

        return array('SQL' => $sql, 'bind' => $valueToMatch);
    }
}

