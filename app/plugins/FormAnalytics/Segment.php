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
namespace Piwik\Plugins\FormAnalytics;
use Piwik\Common;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Piwik\Segment\SegmentExpression;

/**
 * FormAnalytics segment base class
 */
class Segment extends \Piwik\Plugin\Segment
{
    const FORM_NAME_SEGMENT = 'form_name';
    const FORM_CONVERTED_SEGMENT = 'form_converted';
    const FORM_SPENT_TIME_SEGMENT = 'form_timespent';
    const FORM_NUM_SUBMISSIONS_SEGMENT = 'form_num_submissions';
    const FORM_NUM_STARTS_SEGMENT = 'form_num_starts';

    public static function getAllSegments()
    {
        return array(
            self::FORM_NAME_SEGMENT, self::FORM_CONVERTED_SEGMENT, self::FORM_SPENT_TIME_SEGMENT,
            self::FORM_NUM_SUBMISSIONS_SEGMENT, self::FORM_NUM_STARTS_SEGMENT
        );
    }

    protected function init()
    {
        $this->setCategory('FormAnalytics_Forms');
    }

    public static function getIdByName($valueToMatch, $sqlField, $matchType, $segmentName)
    {
        if ($segmentName === self::FORM_NAME_SEGMENT) {
            $sql = 'SELECT idsiteform FROM ' . Common::prefixTable('site_form') . " WHERE site_form.status = '" . FormsModel::STATUS_RUNNING . "' AND " ;
        } else {
            throw new \Exception("Invalid use of form segment filter method");
        }

        if (is_numeric($valueToMatch) && in_array($matchType, array(SegmentExpression::MATCH_EQUAL, SegmentExpression::MATCH_NOT_EQUAL))) {
            // we assume matching by idSiteForm
            return $valueToMatch;
        }

        switch ($matchType) {
            case SegmentExpression::MATCH_NOT_EQUAL:
                $where = ' name != ? ';
                break;
            case SegmentExpression::MATCH_EQUAL:
                $where = ' name = ? ';
                break;
            case SegmentExpression::MATCH_CONTAINS:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' name LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_DOES_NOT_CONTAIN:
                $where = ' name NOT LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_STARTS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' name LIKE CONCAT(?, \'%\') ';
                break;
            case SegmentExpression::MATCH_ENDS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' name LIKE CONCAT(\'%\', ?) ';
                break;
            default:
                throw new \Exception("This match type $matchType is not available for Form Analytics segments.");
                break;
        }

        $sql .= $where;

        return array('SQL' => $sql, 'bind' => $valueToMatch);
    }
}

