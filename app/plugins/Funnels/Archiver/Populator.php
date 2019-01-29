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
use Piwik\Db;
use Piwik\Plugins\Funnels\Configuration;
use Piwik\Plugins\Funnels\Dao\LogTable;
use Piwik\Plugins\Funnels\Db\Pattern;
use Piwik\Plugins\Funnels\Model\FunnelsModel;
use Piwik\Tracker\Action;

class Populator
{

    /**
     * @var LogTable
     */
    private $logTable;

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(LogTable $logTable, Configuration $configuration)
    {
        $this->logTable = $logTable;
        $this->configuration = $configuration;
    }

    public function populateLogFunnel($funnel, $startDateTime, $endDateTime)
    {
        if (empty($funnel['steps'])) {
            // there is no funnel when there are no steps
            return;
        }

        $idFunnel = (int) $funnel['idfunnel'];
        $idSite = (int) $funnel['idsite'];
        $limitToProcessAtOnce = $this->configuration->getMaxRowsToPopulateAtOnce();

        $lastRequiredStepPosition = null;

        foreach ($funnel['steps'] as $step) {
            $loop = 0;
            do {
                $loop++; // prevent endless loop
                $hasMoreVisits = $this->populateStep($idSite, $idFunnel, $step['position'], $step['pattern_type'], $step['pattern'], $lastRequiredStepPosition, $startDateTime, $endDateTime, $limitToProcessAtOnce);
            } while ($hasMoreVisits && $loop < 20000);

            if ($step['required']) {
                $lastRequiredStepPosition = (int) $step['position'];
            }
        }

        // NOW we find the ones that actually converted this goal and add a final step
        $lastStep = $funnel[FunnelsModel::KEY_FINAL_STEP_POSITION];

        $loop = 0;
        do {
            $loop++; // prevent endless loop
            $hasMoreVisits = $this->populateConversion($idSite, $idFunnel, $funnel['idgoal'], $lastStep, $lastRequiredStepPosition, $startDateTime, $endDateTime, $limitToProcessAtOnce);
        } while ($hasMoreVisits && $loop < 20000);
    }

    public function populateConversion($idSite, $idFunnel, $idGoal, $stepPosition, $lastRequiredStepPosition, $startDateTime, $endDateTime, $limitToInsertAtOnce)
    {
        $idSite = (int) $idSite;
        $idFunnel = (int) $idFunnel;
        $idGoal = (int) $idGoal;

        $visitTable = Common::prefixTable('log_visit');
        $conversionTable = Common::prefixTable('log_conversion');
        $visitActionTable = Common::prefixTable('log_link_visit_action');
        $funnelTable = $this->logTable->getPrefixedTableName();
        $limitToInsertAtOnce = (int) $limitToInsertAtOnce;

        // in theory we could start joining from log_conversion and possibly not need a join at all. Problem:
        // we need to take the same visitors into consideration as in top, because a conversion might have happened
        // on the previous day but the visit ended just after midnight.

        if (!empty($lastRequiredStepPosition)) {
            //  If a previous step was required, we make sure to only take into consideration those users
            $visitorHadEnteredFunnelQuery = $this->getSubqueryToRequireStep($idFunnel, $lastRequiredStepPosition);
        } else {
            // otherwise we take into consideration any user.
            $visitorHadEnteredFunnelQuery = '';
        }

        $sql = "SELECT lc.idvisit, 
                        lc.idlink_va, 
                        lc.idaction_url as idaction, 
                        (select lvaprev.idaction_url_ref 
                            from $visitActionTable lvaprev 
                            where lc.idlink_va = lvaprev.idlink_va 
                            limit 1) as idaction_prev,
                        null as idaction_next
                        from $visitTable lv
                        left join $conversionTable lc on lv.idvisit = lc.idvisit 
                        WHERE $visitorHadEnteredFunnelQuery
                            lv.idsite = ? 
                            AND lv.visit_last_action_time >= ? 
                            AND lv.visit_last_action_time <= ?
                            AND lc.idgoal = ?
                            AND NOT EXISTS (SELECT 1 FROM $funnelTable lf WHERE lf.idfunnel = ? AND lf.idvisit = lv.idvisit AND lf.step_position = ?)
                        GROUP BY idvisit";

        $shouldApplyLimit = $limitToInsertAtOnce > 0;
        if ($shouldApplyLimit) {
            $sql .= " LIMIT $limitToInsertAtOnce";
        }

        $bind = array($idSite, $startDateTime, $endDateTime, $idGoal, $idFunnel, $stepPosition);

        $rows = Db::fetchAll($sql, $bind);

        $this->logTable->bulkInsert($idSite, $idFunnel, $stepPosition, $rows);

        $hasMore = $shouldApplyLimit && !empty($rows) && count($rows) >= $limitToInsertAtOnce;

        unset($rows);
        return $hasMore;
    }

    private function getSubqueryToRequireStep($idFunnel, $lastRequiredStepPosition)
    {
        $subQueryRequiredStep = '';

        if (!empty($lastRequiredStepPosition)) {
            $funnelTable = $this->logTable->getPrefixedTableName();
            $subQueryRequiredStep = 'lv.idvisit IN(SELECT idvisit FROM ' . $funnelTable .' WHERE idfunnel = ' .  (int) $idFunnel . ' AND step_position = ' . (int) $lastRequiredStepPosition . ') AND';
        }

        return $subQueryRequiredStep;
    }

    public function populateStep($idSite, $idFunnel, $stepPosition, $patternType, $pattern, $lastRequiredStepPosition, $startDateTime, $endDateTime, $limitToInsertAtOnce)
    {
        $idSite = (int) $idSite;
        $idFunnel = (int) $idFunnel;
        $stepPosition = (int) $stepPosition;

        $visitTable = Common::prefixTable('log_visit');
        $actionTable = Common::prefixTable('log_action');
        $visitActionTable = Common::prefixTable('log_link_visit_action');
        $funnelTable = $this->logTable->getPrefixedTableName();
        $limitToInsertAtOnce = (int) $limitToInsertAtOnce;

        $subQueryRequiredStep = $this->getSubqueryToRequireStep($idFunnel, $lastRequiredStepPosition);

        $dbPattern = new Pattern();
        $pattern = $dbPattern->getMysqlQuery('la.name', $patternType, $pattern);

        $dbColInfo = $dbPattern->getActionTypeAndColumnName($patternType);
        $actionType = $dbColInfo['actionType'];
        $actionColumn = $dbColInfo['actionColumn'];
        $actionPageUrlType = (int) Action::TYPE_PAGE_URL;

        // idaction_url_ref = 0 when new visit
        // idaction_url_ref = null eg when there is a site search and shouldn't apply to action type url
        // lva.idaction_url_ref seems to be pretty much always a pageview because it depends on exit url
        // lvanext.idaction_url_ref seems to be always a pageview as well

        // we need to make sure it finds the first matching action for that funnel, not the last matching funnel.
        // otherwise `lva.idlink_va < lvanext.idlink_va` may result in non matching result even though there could be one
        // if we had used the first matching idaction
        $sql = "SELECT lv.idvisit, 
                        lva.idlink_va, 
                        lva.idaction_url_ref as idaction_prev, 
                        lva.".$actionColumn." as idaction, 
                        (select lvanext.idaction_url 
                            FROM $visitActionTable lvanext 
                            LEFT JOIN $actionTable lanext ON lvanext.idaction_url = lanext.idaction
                            WHERE lv.idvisit = lvanext.idvisit 
                                AND lvanext.idsite = $idSite
                                AND lanext.`type` = $actionPageUrlType
                                AND lva.interaction_position < lvanext.interaction_position
                            order by lvanext.interaction_position ASC 
                            limit 1) as idaction_next
                        from $visitTable lv
                        left join $visitActionTable lva on 
                        lv.idvisit = lva.idvisit
                        left join $actionTable la on 
                        lva." . $actionColumn ." = la.idaction
                        WHERE $subQueryRequiredStep
                            lv.idsite = ? 
                            AND la.type = " . $actionType . "
                            AND lv.visit_last_action_time >= ?
                            AND lv.visit_last_action_time <= ?
                            AND NOT EXISTS (SELECT 1 FROM $funnelTable lf WHERE lf.idfunnel = ? AND lf.idvisit = lv.idvisit AND lf.step_position = ?)
                            AND " . $pattern['query'] . "
                    GROUP BY idvisit";

        $shouldApplyLimit = $limitToInsertAtOnce > 0;
        if ($shouldApplyLimit) {
            $sql .= " LIMIT $limitToInsertAtOnce";
        }

        $bind = array($idSite, $startDateTime, $endDateTime, $idFunnel, $stepPosition);
        $bind[] = $pattern['bind'];

        $rows = Db::fetchAll($sql, $bind);

        $this->logTable->bulkInsert($idSite, $idFunnel, $stepPosition, $rows);

        $hasMore = $shouldApplyLimit && !empty($rows) && count($rows) >= $limitToInsertAtOnce;
        unset($rows);
        return $hasMore;
    }

    public function updateEntryAndExitStep($idSite, $idFunnel, $startDateTime, $endDateTime)
    {
        $idSite = (int) $idSite;
        $idFunnel = (int) $idFunnel;

        $table = $this->logTable->getPrefixedTableName();
        $tableVisit = Common::prefixTable('log_visit');

        $sql = "UPDATE $table AS log_funnel
                 INNER JOIN (SELECT inner_funnel.idvisit, min(step_position) as minstep, max(step_position) as maxstep 
                        FROM $table inner_funnel
                        LEFT JOIN $tableVisit log_visit on inner_funnel.idvisit = log_visit.idvisit
                        WHERE inner_funnel.idfunnel = ? 
                              AND log_visit.idsite = ? 
                              AND log_visit.visit_last_action_time >= ?
                              AND log_visit.visit_last_action_time <= ?
                        GROUP BY inner_funnel.idvisit) lf
                  SET log_funnel.min_step = lf.minstep, log_funnel.max_step = lf.maxstep 
                  WHERE log_funnel.idvisit = lf.idvisit
                        AND log_funnel.idfunnel = ? 
                        AND log_funnel.min_step != lf.minstep
                        AND log_funnel.max_step != lf.maxstep";

        $bind = array($idFunnel, $idSite, $startDateTime, $endDateTime, $idFunnel);
        Db::query($sql, $bind);
    }


}

