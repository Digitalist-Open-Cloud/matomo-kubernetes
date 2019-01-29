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

namespace Piwik\Plugins\Funnels\Archiver;

use Piwik\Common;
use Piwik\DataAccess\LogAggregator as PiwikLogAggregator;
use Piwik\Db;
use Piwik\Plugins\Funnels\Archiver;
use Piwik\Plugins\Funnels\Metrics;

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

    public function aggregateNumEntriesPerStep($idFunnel)
    {
        $select = sprintf('log_funnel.step_position as label, count(log_funnel.idfunnel) as %s', Metrics::NUM_STEP_ENTRIES);
        $where = 'log_funnel.min_step = log_funnel.step_position';
        $groupBy = 'log_funnel.step_position';

        return $this->query($idFunnel, $select, $where, $groupBy);
    }

    public function aggregateNumExitsPerStep($idFunnel)
    {
        $select = sprintf('log_funnel.step_position as label, count(log_funnel.idfunnel) as %s', Metrics::NUM_STEP_EXITS);
        $where = 'log_funnel.max_step = log_funnel.step_position';
        $groupBy = 'log_funnel.step_position';

        return $this->query($idFunnel, $select, $where, $groupBy);
    }

    public function aggregateNumHitsPerStep($idFunnel)
    {
        $select = sprintf('log_funnel.step_position as label, count(log_funnel.idfunnel) as %s', Metrics::NUM_STEP_VISITS_ACTUAL);
        $where = '';
        $groupBy = 'log_funnel.step_position';

        return $this->query($idFunnel, $select, $where, $groupBy);
    }

    public function aggregateEntriesActions($idFunnel)
    {
        $select = sprintf('log_funnel.step_position as label, 
                           ifnull(log_action.name, if(log_funnel.idaction_prev = 0, \'%s\',\'%s\')) as sublabel, 
                           count(log_funnel.idfunnel) as %s',
                           Archiver::LABEL_VISIT_ENTRY,
                           Archiver::LABEL_NOT_DEFINED,
                           Metrics::NUM_HITS);

        return $this->aggregateActions($select, 'min_step', 'idaction_prev', $idFunnel);
    }

    public function aggregateExitActions($idFunnel)
    {
        $select = sprintf('log_funnel.step_position as label, 
                           ifnull(log_action.name, if(log_funnel.idaction_next is null, \'%s\',\'%s\')) as sublabel, 
                           count(log_funnel.idfunnel) as %s',
                           Archiver::LABEL_VISIT_EXIT,
                           Archiver::LABEL_NOT_DEFINED,
                           Metrics::NUM_HITS);

        return $this->aggregateActions($select, 'max_step', 'idaction_next', $idFunnel);
    }

    private function aggregateActions($select, $column, $joinColumn, $idFunnel)
    {
        // to further improve performance, if we knew there are eg 6 steps, then we could order by "hits desc"
        // and then fetch only a limit of 6 steps * $maxRowsInActions, eg only max 600 entries.

        $where = sprintf('log_funnel.%s = log_funnel.step_position', $column);
        $groupBy = 'log_funnel.step_position, log_action.name';

        $from = array(array(
            'table'  => 'log_action',
            'joinOn' => sprintf('log_funnel.%s = log_action.idaction', $joinColumn)
        ));

        return $this->query($idFunnel, $select, $where, $groupBy, $from);
    }

    public function aggregateActionReferrers($idFunnel)
    {
        $switchRef = sprintf('case when log_visit.referer_type = %s then log_visit.referer_keyword 
                                   when log_visit.referer_type = %s then log_visit.referer_name
                                   when log_visit.referer_type = %s then log_visit.referer_name
                                   else \'%s\'
                                   end',
                                Common::REFERRER_TYPE_SEARCH_ENGINE,
                                Common::REFERRER_TYPE_WEBSITE,
                                Common::REFERRER_TYPE_CAMPAIGN,
                                Archiver::LABEL_DIRECT_ENTRY);

        $select = sprintf('log_funnel.step_position as label, 
                           log_visit.referer_type as referer_type, 
                           %s as sublabel, 
                           count(log_funnel.idfunnel) as %s',
                          $switchRef,
                          Metrics::NUM_HITS);

        $where = sprintf('log_funnel.min_step = log_funnel.step_position and log_funnel.idaction_prev = 0');
        $groupBy = 'log_funnel.step_position, referer_type, sublabel';

        return $this->query($idFunnel, $select, $where, $groupBy);
    }

    private function query($idFunnel, $select, $where = '', $groupBy, $from = array())
    {
        // we cannot add any bind as any argument as it would otherwise break segmentation

        $idFunnel = (int) $idFunnel;
        $baseFrom = array('log_funnel', 'log_visit');
        $baseWhere = "log_visit.visit_last_action_time >= ? 
                AND log_visit.visit_last_action_time <= ? 
                AND log_visit.idsite = ? 
                AND log_funnel.idfunnel = $idFunnel";

        if (!empty($from)) {
            foreach ($from as $join) {
                $baseFrom[] = $join;
            }
        }

        if (!empty($where)) {
            $baseWhere .= ' AND ' . $where;
        }

        $orderBy = '';

        $query = $this->logAggregator->generateQuery($select, $baseFrom, $baseWhere, $groupBy, $orderBy);

        return Db::query($query['sql'], $query['bind']);
    }

}
