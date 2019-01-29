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

namespace Piwik\Plugins\FormAnalytics\Archiver;

use Piwik\Common;
use Piwik\DataAccess\LogAggregator as PiwikLogAggregator;
use Piwik\Db;
use Piwik\Plugins\FormAnalytics\Metrics;

class LogAggregator
{
    /**
     * @var PiwikLogAggregator
     */
    private $logAggregator;

    public function __construct(PiwikLogAggregator $logAggregator)
    {
        $this->logAggregator = $logAggregator;
    }

    public function aggregateFormMetrics()
    {
        $select = sprintf('log_form.idsiteform as label, 
                           sum(log_form.num_views) as %s,
                           count(log_form.idsiteform) as %s,
                           sum(log_form.num_starts) as %s,
                           sum(if(log_form.num_starts > 0, 1, 0)) as %s,
                           sum(log_form.time_hesitation) as %s,
                           sum(log_form.time_to_first_submission) as %s,
                           sum(log_form.num_submissions) as %s,
                           sum(if(log_form.num_submissions > 0, 1, 0)) as %s,
                           sum(if(log_form.num_submissions > 1, 1, 0)) as %s,
                           sum(if(log_form.converted = 1, log_form.time_spent, 0)) as %s,
                           sum(log_form.time_spent) as %s,
                           sum(log_form.converted) as %s',
                        Metrics::SUM_FORM_VIEWS,
                        Metrics::SUM_FORM_VIEWERS,
                        Metrics::SUM_FORM_STARTS,
                        Metrics::SUM_FORM_STARTERS,
                        Metrics::SUM_FORM_HESITATION_TIME,
                        Metrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION,
                        Metrics::SUM_FORM_SUBMISSIONS,
                        Metrics::SUM_FORM_SUBMITTERS,
                        Metrics::SUM_FORM_RESUBMITTERS,
                        Metrics::SUM_FORM_TIME_TO_CONVERSION,
                        Metrics::SUM_FORM_TIME_SPENT,
                        Metrics::SUM_FORM_CONVERSIONS);
        $where = '';
        $groupBy = 'log_form.idsiteform';

        return $this->queryForm($select, $where, $groupBy);
    }

    public function aggregatePageUrls()
    {
        $from = array(
            array('table' => 'log_form_page',
                  'joinOn' => 'log_form_page.idlogform = log_form.idlogform'),
            array(
            'table'  => 'log_action',
            'joinOn' => 'log_form_page.idaction_url = log_action.idaction'
        ));

        $select = sprintf('log_action.name as label, 
                           log_form.idsiteform,
                           sum(log_form_page.num_views) as %s,
                           count(log_form_page.idlogform) as %s,
                           sum(log_form_page.num_starts) as %s,
                           sum(if(log_form_page.num_starts > 0, 1, 0)) as %s,
                           sum(log_form_page.time_hesitation) as %s,
                           sum(log_form_page.time_to_first_submission) as %s,
                           sum(log_form_page.time_spent) as %s,
                           sum(log_form_page.num_submissions) as %s,
                           sum(if(log_form_page.num_submissions > 0, 1, 0)) as %s,
                           sum(if(log_form_page.num_submissions > 1, 1, 0)) as %s,
                           sum(log_form.converted) as %s',
                           Metrics::SUM_FORM_VIEWS,
                           Metrics::SUM_FORM_VIEWERS,
                           Metrics::SUM_FORM_STARTS,
                           Metrics::SUM_FORM_STARTERS,
                           Metrics::SUM_FORM_HESITATION_TIME,
                           Metrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION,
                           Metrics::SUM_FORM_TIME_SPENT,
                           Metrics::SUM_FORM_SUBMISSIONS,
                           Metrics::SUM_FORM_SUBMITTERS,
                           Metrics::SUM_FORM_RESUBMITTERS,
                           Metrics::SUM_FORM_CONVERSIONS);

        $where = '';
        $groupBy = 'log_form.idsiteform, log_action.name';

        return $this->queryForm($select, $where, $groupBy, $from);
    }

    public function aggregateEntryFields()
    {
        $from = array(
            array('table' => 'log_form_page',
                'joinOn' => 'log_form_page.idlogform = log_form.idlogform')
        );

        // the distinct might be slow and we may have to remove it
        $select = sprintf('log_form_page.entry_field_name as label, 
                           log_form.idsiteform,
                           count(log_form.idvisitor) as %s,
                           count(DISTINCT log_form.idvisitor) as %s',
                           Metrics::SUM_FIELD_ENTRIES,
                           Metrics::SUM_FIELD_UNIQUE_ENTRIES);
        $where = 'log_form_page.entry_field_name is not null';
        $groupBy = 'log_form.idsiteform, log_form_page.entry_field_name';

        return $this->queryForm($select, $where, $groupBy, $from);
    }

    public function aggregateDropOffs()
    {
        $from = array(
            array('table' => 'log_form_page',
                'joinOn' => 'log_form_page.idlogform = log_form.idlogform')
        );

        // the distinct might be slow and we may have to remove it
        $select = sprintf('log_form_page.exit_field_name as label, 
                           log_form.idsiteform,
                           count(log_form.idvisitor) as %s,
                           count(DISTINCT log_form.idvisitor) as %s',
                           Metrics::SUM_FIELD_DROPOFFS,
                           Metrics::SUM_FIELD_UNIQUE_DROPOFFS);

        $where = 'log_form_page.num_submissions = 0 and log_form_page.exit_field_name is not null';
        $groupBy = 'log_form.idsiteform, log_form_page.exit_field_name';

        return $this->queryForm($select, $where, $groupBy, $from);
    }

    public function aggregateFields()
    {
        $select = sprintf('outerform.field_name as label, 
                           outerform.idsiteform,
                           sum(if(num_interactions > 0, 1, 0)) as %s,
                           sum(num_interactions) as %s,
                           sum(outerform.time_spent) as %s,
                           sum(outerform.time_hesitation) as %s',
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS,
            Metrics::SUM_FIELD_INTERACTIONS,
            Metrics::SUM_FIELD_TIME_SPENT,
            Metrics::SUM_FIELD_HESITATION_TIME);

        $select .= ',';
        $select .= sprintf('sum(with_field_size) as %s,
                           sum(field_size) as %s',
            Metrics::SUM_FIELD_WITH_FIELDSIZE,
            Metrics::SUM_FIELD_FIELDSIZE);

        $select .= ',';
        $select .= sprintf('sum(if(outerform.num_changes > 1, 1, 0)) as %s,
                           sum(if(outerform.num_changes > 1, outerform.num_changes - 1, 0)) as %s,
                           sum(if(outerform.num_focus > 1, 1, 0)) as %s,
                           sum(if(outerform.num_focus > 1, outerform.num_focus - 1, 0)) as %s,
                           sum(if(outerform.num_changes > 0, 1, 0)) as %s,
                           sum(outerform.num_changes) as %s,
                           sum(if(outerform.num_deletes > 0, 1, 0)) as %s,
                           sum(outerform.num_deletes) as %s,
                           sum(if(outerform.num_cursor > 0, 1, 0)) as %s,
                           sum(outerform.num_cursor) as %s',
            Metrics::SUM_FIELD_UNIQUE_AMENDMENTS,
            Metrics::SUM_FIELD_AMENDMENTS,
            Metrics::SUM_FIELD_UNIQUE_REFOCUS,
            Metrics::SUM_FIELD_REFOCUSES,
            Metrics::SUM_FIELD_UNIQUE_CHANGES,
            Metrics::SUM_FIELD_TOTAL_CHANGES,
            Metrics::SUM_FIELD_UNIQUE_DELETES,
            Metrics::SUM_FIELD_DELETES,
            Metrics::SUM_FIELD_UNIQUE_CURSOR,
            Metrics::SUM_FIELD_CURSOR);

        // we do not calculate the number of corrections 100% correctly since there might be eg following behaviour:
        // user submits form, page reloads, user submits form again. We would have two different outerform entries
        // each with num_changes = 1 meaning even though basically a correction happened the second time the user submits it,
        // it was not detected as correction cause the second requests creates a new outerform entry because of
        // different idformview (expected). we can calculate correct numbers but then we would need to first group by
        // idvisit, idsiteform and then in outer query by idsiteform, action_name
        // in some ways it is actually correct behaviour, in some ways it might be preferable to have different way

        $where = 'log_form_field.field_name is not null';

        return $this->queryField($select, $where);
    }

    public function aggregateSubmittedFields()
    {
        $from = array(array(
            'table'  => 'log_form_field',
            'joinOn' => 'log_form_field.idlogform = log_form.idlogform'
        ));

        $select = sprintf('log_form_field.field_name as label, 
                           log_form.idsiteform,
                           count(log_form_field.idlogform) as %s,
                           sum(log_form_field.left_blank) as %s,
                           sum(if(log_form_field.field_size > 0, 1, 0)) as %s,
                           sum(if(log_form_field.field_size > 0, log_form_field.field_size, 0)) as %s,
                           sum(if(log_form_field.time_spent > 0, 1, 0)) as %s',
                           Metrics::SUM_FIELD_SUBMITTED,
                           Metrics::SUM_FIELD_LEFTBLANK_SUBMITTED,
                           Metrics::SUM_FIELD_SUBMITTED_WITH_FIELDSIZE,
                           Metrics::SUM_FIELD_FIELDSIZE_SUBMITTED,
                           Metrics::SUM_FIELD_INTERACTIONS_SUBMIT);

        $where = 'log_form_field.submitted = 1';
        $groupBy = 'log_form.idsiteform, log_form_field.field_name';

        return $this->queryForm($select, $where, $groupBy, $from);
    }

    public function aggregateConvertedFields()
    {
        $from = array(array(
            'table'  => 'log_form_field',
            'joinOn' => 'log_form_field.idlogform = log_form.idlogform'
        ));

        $select = sprintf('log_form_field.field_name as label, 
                           log_form.idsiteform,
                           count(log_form_field.idlogform) as %s,
                           sum(log_form_field.left_blank) as %s,
                           sum(if(log_form_field.field_size > 0, 1, 0)) as %s,
                           sum(log_form_field.field_size) as %s',
                           Metrics::SUM_FIELD_CONVERTED,
                           Metrics::SUM_FIELD_LEFTBLANK_CONVERTED,
                           Metrics::SUM_FIELD_CONVERTED_WITH_FIELDSIZE,
                           Metrics::SUM_FIELD_FIELDSIZE_CONVERTED);

        $where = 'log_form.converted = 1 and log_form_field.idformview = log_form.last_idformview';
        $groupBy = 'log_form.idsiteform, log_form_field.field_name';

        return $this->queryForm($select, $where, $groupBy, $from);
    }

    private function queryField($select, $where = '')
    {
        // important: we cannot add any bind as an argument here as it would otherwise break segmentation
        $baseFrom = array('log_form', array(
            'table'  => 'log_form_field',
            'joinOn' => 'log_form_field.idlogform = log_form.idlogform'
        ));

        $baseWhere = $this->logAggregator->getWhereStatement('log_form', 'form_last_action_time');

        if (!empty($where)) {
            $baseWhere .= ' AND ' . $where;
        }

        // we do log_form_field.field_size > 0 as there could be a record with "-1"
        $innerSelect = 'log_form.idlogform,
                   log_form.idsiteform,
                   log_form_field.field_name as field_name,
                   sum(if(log_form_field.time_spent > 0, 1, 0)) as num_interactions,
                   sum(log_form_field.time_spent) as time_spent,
                   sum(log_form_field.time_hesitation) as time_hesitation,
                   sum(if(log_form_field.field_size > 0, 1, 0)) as with_field_size,
                   sum(if(log_form_field.field_size > 0, log_form_field.field_size, 0)) as field_size,
                   sum(log_form_field.num_changes) as num_changes,
                   sum(log_form_field.num_deletes) as num_deletes,
                   sum(log_form_field.num_cursor) as num_cursor,
                   sum(log_form_field.num_focus) as num_focus';
        $groupBy = 'log_form.idlogform, log_form_field.field_name';
        $query = $this->logAggregator->generateQuery($innerSelect, $baseFrom, $baseWhere, $groupBy, $orderBy = false);

        $query['sql'] = sprintf('SELECT %s FROM (SELECT * FROM (%s) AS abc) as outerform 
                                 GROUP BY outerform.idsiteform, outerform.field_name', $select, $query['sql']);

        return Db::query($query['sql'], $query['bind']);
    }

    private function queryForm($select, $where = '', $groupBy, $from = array())
    {
        // important: we cannot add any bind as an argument here as it would otherwise break segmentation

        $baseFrom = array('log_form');
        $baseWhere = $this->logAggregator->getWhereStatement('log_form', 'form_last_action_time');

        if (!empty($from)) {
            foreach ($from as $join) {
                $baseFrom[] = $join;
            }
        }

        if (!empty($where)) {
            $baseWhere .= ' AND ' . $where;
        }

        $orderBy = '';
        $limit = 30000;

        $query = $this->logAggregator->generateQuery($select, $baseFrom, $baseWhere, $groupBy, $orderBy, $limit);

        return Db::query($query['sql'], $query['bind']);
    }

}
