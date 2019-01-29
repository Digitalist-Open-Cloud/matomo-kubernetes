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

namespace Piwik\Plugins\UsersFlow\Archiver;

use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\UsersFlow\API;
use Piwik\Plugins\UsersFlow\Archiver;
use Piwik\Plugins\UsersFlow\Configuration;
use Piwik\Plugins\UsersFlow\Metrics;
use Piwik\DataAccess\LogAggregator as PiwikLogAggregator;
use Piwik\Tracker\Action;

class LogAggregator
{
    /**
     * @var PiwikLogAggregator
     */
    private $logAggregator;

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(PiwikLogAggregator $logAggregator, Configuration $configuration)
    {
        $this->logAggregator = $logAggregator;
        $this->configuration = $configuration;
    }

    public function makeUrlColumn($column, $dataSource, $ignoreSearchQuery, $ignoreDomain, $siteKeepsUrlFragments)
    {
        if ($dataSource === DataSources::DATA_SOURCE_PAGE_TITLE) {
            return $column;
        }

        $theColumn = $column;

        if ($ignoreSearchQuery) {
            if ($siteKeepsUrlFragments) {
                $removeHash = "SUBSTRING_INDEX(%s, '#', 1)"; // if the site keeps url hashes, we remove them as well
            } else {
                $removeHash = '%s';
            }

            $pathName = "TRIM(TRAILING '/' FROM SUBSTRING_INDEX($removeHash, '?', 1))";

            $column = sprintf($pathName, $column);
        }

        if ($ignoreDomain) {
            $pathName = "SUBSTR(%s, LOCATE('/', $theColumn))";
            $column = sprintf($pathName, $column);
        }

        return $column;
    }

    public function aggregateTopStepActions($step, $dataSource, $ignoreSearchQuery, $ignoreDomain, $siteKeepsUrlFragments, $exploreStep, $exploreValueToMatch)
    {
        if ($dataSource === DataSources::DATA_SOURCE_PAGE_TITLE) {
            $columnToFetch = 'idaction_name';
            $idAction = Action::TYPE_PAGE_TITLE;
        } else {
            $columnToFetch = 'idaction_url';
            $idAction = Action::TYPE_PAGE_URL;
        }

        $step = (int) $step;
        $nextStep = $step + 1;

        $doExploreTraffic = !empty($exploreStep) && !empty($exploreValueToMatch);

        $from = array('log_link_visit_action', array(
            'table'  => 'log_action',
            'joinOn' => "log_link_visit_action.$columnToFetch = log_action.idaction"
        ));

        $extraWhere = '';
        $keyToBeReplacedWithBind = "'TO_BE_REPLACED_WITH_BIND'";

        if ($doExploreTraffic) {
            $isExploringSearch = $exploreValueToMatch === 'Search' || $exploreValueToMatch === Piwik::translate('General_Search');

            $from[] = array(
                'table' => 'log_link_visit_action',
                'tableAlias' => 'log_link_visit_action_match',
                'joinOn' => "log_link_visit_action.idvisit = log_link_visit_action_match.idvisit"
            );
            if ($isExploringSearch) {
                $extraWhere .= ' 
                    AND log_link_visit_action_match.interaction_position = ' . (int) $exploreStep . ' AND log_link_visit_action_match.' . $columnToFetch . ' is null';
            } else {
                $columnToExplore = $this->makeUrlColumn('`log_action_match`.`name`', $dataSource, $ignoreSearchQuery, $ignoreDomain, $siteKeepsUrlFragments);
                $extraWhere .= ' 
                    AND log_link_visit_action_match.interaction_position = ' . (int) $exploreStep . " AND $columnToExplore = $keyToBeReplacedWithBind";
                $from[] = array(
                    'table'  => 'log_action',
                    'tableAlias' => 'log_action_match',
                    'joinOn' => "log_link_visit_action_match.$columnToFetch = log_action_match.idaction AND log_action_match.type = $idAction"
                );
            }

            $pluginManager = Plugin\Manager::getInstance();
            if ($pluginManager->isPluginActivated('Events')) {
                $extraWhere .= ' AND log_link_visit_action_match.idaction_event_category is null';
            }
            if ($pluginManager->isPluginActivated('Contents')) {
                $extraWhere .= ' AND log_link_visit_action_match.idaction_content_name is null';
            }
        }

        $urlLabel = $this->makeUrlColumn('`log_action`.`name`', $dataSource, $ignoreSearchQuery, $ignoreDomain, $siteKeepsUrlFragments);

        $from[] = array(
            'table' => 'log_link_visit_action',
            'tableAlias' => 'log_link_visit_action_next',
            'joinOn' => "log_link_visit_action.idvisit = log_link_visit_action_next.idvisit 
                            AND log_link_visit_action_next.interaction_position = " . $nextStep
        );

        if ($dataSource === DataSources::DATA_SOURCE_PAGE_TITLE) {
            $extraWhere .= " AND (log_action.type = $idAction or log_action.type = " . Action::TYPE_SITE_SEARCH . ")";
            $extraWhere .= " AND (log_action_url_next.type = $idAction or log_link_visit_action_next.idlink_va is null or log_action_url_next.type = " . Action::TYPE_SITE_SEARCH . ")";
        } else {
            $extraWhere .= " AND (log_action.type = $idAction or log_link_visit_action.idaction_url is null)";
            $extraWhere .= " AND (log_action_url_next.type = $idAction or log_link_visit_action_next.idaction_url is null)";
        };

        $from[] = array(
            'table' => 'log_action',
            'tableAlias' => 'log_action_url_next',
            'joinOn' => "log_link_visit_action_next.$columnToFetch = log_action_url_next.idaction"
        );

        $nextUrlLabel = $this->makeUrlColumn('`log_action_url_next`.`name`', $dataSource, $ignoreSearchQuery, $ignoreDomain, $siteKeepsUrlFragments);
        $searchLabel = Archiver::LABEL_SEARCH;

        $where = $this->logAggregator->getWhereStatement('log_link_visit_action', 'server_time');
        $where .= " AND log_link_visit_action.interaction_position = $step " . $extraWhere;


        if ($dataSource === DataSources::DATA_SOURCE_PAGE_TITLE) {
            $select = "if(log_action.type = " . Action::TYPE_SITE_SEARCH . ", '$searchLabel', $urlLabel) as label,
                   CASE WHEN log_link_visit_action_next.idlink_va is null THEN null WHEN log_action_url_next.type = " . Action::TYPE_SITE_SEARCH . " THEN '$searchLabel' ELSE $nextUrlLabel END as nextLabel,";
        } else {
            $select = "if(log_link_visit_action.idaction_url is null, '$searchLabel', $urlLabel) as label,
                   CASE WHEN log_link_visit_action_next.idlink_va is null THEN null WHEN log_link_visit_action_next.idaction_url is null THEN '$searchLabel' ELSE $nextUrlLabel END as nextLabel,
                   ";
        }

        $select .= " count(*) as " . Metrics::NB_VISITS . ", 
                   sum(if(log_link_visit_action_next.idlink_va is null, 1, 0)) as " . Metrics::NB_EXITS;
        $groupBy = "label, nextLabel";
        $orderBy = Metrics::NB_VISITS . ' DESC';

        $limit = $this->configuration->getMaxLinksPerInteractions();

        $query = $this->logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy, $limit);
        $sql = $query['sql'];
        $bind = $query['bind'];

        if ($doExploreTraffic) {
            $position = strpos($sql, $keyToBeReplacedWithBind);
            if ($position !== false) {
                $countBindsBeforeReplace = substr_count($sql, '?', 0, $position);

                array_splice($bind, $countBindsBeforeReplace, 0, $exploreValueToMatch);
                $sql = str_replace($keyToBeReplacedWithBind, '?', $sql);
            }
        }

        return array('sql' => trim($sql), 'bind' => $bind);
    }

}
