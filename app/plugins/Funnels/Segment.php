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

namespace Piwik\Plugins\Funnels;

use Piwik\Common;
use Piwik\Segment\SegmentExpression;

/**
 * AbTesting segment base class
 */
class Segment extends \Piwik\Plugin\Segment
{
    const NAME_FUNNEL_SEGMENT = 'funnels_name';
    const NAME_FUNNEL_STEP_POSITION = 'funnels_step_position';

    protected function init()
    {
        $this->setCategory('Funnels_Funnels');
    }

    public static function getIdByName($valueToMatch, $sqlField, $matchType, $segmentName)
    {
        if (is_numeric($valueToMatch) && $matchType === SegmentExpression::MATCH_EQUAL && $segmentName === self::NAME_FUNNEL_SEGMENT) {
            // someone is applying segment by idfunnel
            return (int) $valueToMatch;
        }

        if ($segmentName === self::NAME_FUNNEL_SEGMENT) {
            $funnelTable = Common::prefixTable('funnel');
            $goalTable = Common::prefixTable('goal');
            $sql = "SELECT idfunnel FROM $funnelTable lf LEFT JOIN $goalTable lg ON lf.idgoal = lg.idgoal WHERE lf.activated = 1 AND lf.deleted = 0 AND ";
        } else {
            throw new \Exception("Invalid use of segment filter method");
        }

        switch ($matchType) {
            case SegmentExpression::MATCH_NOT_EQUAL:
                $where = ' lg.name != ? ';
                break;
            case SegmentExpression::MATCH_EQUAL:
                $where = ' lg.name = ? ';
                break;
            case SegmentExpression::MATCH_CONTAINS:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' lg.name LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_DOES_NOT_CONTAIN:
                $where = ' lg.name NOT LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_STARTS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' lg.name LIKE CONCAT(?, \'%\') ';
                break;
            case SegmentExpression::MATCH_ENDS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' lg.name LIKE CONCAT(\'%\', ?) ';
                break;
            default:
                throw new \Exception("This match type $matchType is not available for Funnels segments.");
                break;
        }

        $sql .= $where;

        return array('SQL' => $sql, 'bind' => $valueToMatch);
    }
}

