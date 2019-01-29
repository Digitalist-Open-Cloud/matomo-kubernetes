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
namespace Piwik\Plugins\Funnels\Model;

use Exception;
use Piwik\API\Request;
use Piwik\Date;
use Piwik\Exception\UnexpectedWebsiteFoundException;
use Piwik\Piwik;
use Piwik\Tracker\GoalManager;
use Piwik\Plugins\Funnels\Dao\Funnel as FunnelDao;
use Piwik\Plugins\Funnels\Dao\LogTable;
use Piwik\Site;
use Piwik\Translate;

class FunnelsModel
{
    const KEY_FINAL_STEP_POSITION = 'final_step_position';

    /**
     * @var FunnelDao
     */
    private $funnelDao;

    /**
     * @var LogTable
     */
    private $logTable;

    private $goalsCache = array();

    public function __construct(FunnelDao $funnelDao, LogTable $logTable)
    {
        $this->funnelDao = $funnelDao;
        $this->logTable = $logTable;
    }

    public static function isValidGoalId($idGoal)
    {
        // 0 for ecommerce order
        return !empty($idGoal) || $idGoal === '0' || $idGoal === 0;
    }

    public function checkFunnelExists($idSite, $idFunnel)
    {
        $funnel = $this->funnelDao->getFunnel($idFunnel);

        if (empty($funnel) || $funnel['idsite'] != $idSite) {
            throw new Exception(Piwik::translate('Funnels_ErrorFunnelDoesNotExist'));
        }
    }

    public function checkGoalFunnelExists($idSite, $idGoal)
    {
        $funnel = $this->funnelDao->getGoalFunnel($idSite, $idGoal);

        if (empty($funnel)) {
            throw new Exception(Piwik::translate('Funnels_ErrorGoalFunnelDoesNotExist'));
        }
    }

    public function checkGoalExists($idSite, $idGoal)
    {
        $goal = $this->getGoal($idSite, $idGoal);

        if (empty($goal)) {
            throw new Exception(Piwik::translate('Funnels_ErrorGoalDoesNotExist'));
        }
    }

    public function getFunnel($idFunnel)
    {
        $funnel = $this->funnelDao->getFunnel($idFunnel);

        return $this->enrichFunnel($funnel);
    }

    public function getGoalFunnel($idSite, $idGoal)
    {
        $funnel = $this->funnelDao->getGoalFunnel($idSite, $idGoal);

        return $this->enrichFunnel($funnel);
    }

    public function deleteGoalFunnel($idSite, $idGoal)
    {
        // do not use $this->getGoalFunnel() because that method checks if the goal exists and the goal might have been
        // deleted meanwhile.
        $funnel = $this->funnelDao->getGoalFunnel($idSite, $idGoal);

        if (!empty($funnel['idfunnel'])) {
            $now = Date::now()->getDatetime();
            $this->funnelDao->disableFunnel($funnel['idfunnel'], $now);
        }
    }

    public function getAllActivatedFunnelsForSite($idSite)
    {
        $funnels = $this->funnelDao->getAllActivatedFunnelsForSite($idSite);

        return $this->enrichFunnels($funnels);
    }

    public function hasAnyActivatedFunnelForSite($idSite)
    {
        // we could reuse getAllActivatedFunnelsForSite() and do !empty(->getAllActivatedFunnelsForSite()) but this is
        // much faster
        return $this->funnelDao->hasAnyActivatedFunnelForSite($idSite);
    }

    public function setGoalFunnel($idSite, $idGoal, $isActivated, $steps, $now)
    {
        $this->checkGoalExists($idSite, $idGoal);

        if (!empty($isActivated) && empty($steps)) {
            throw new Exception(Piwik::translate('Funnels_ErrorActivatedFunnelWithNoSteps'));
        }

        // while a funnel is not activated we can reuse the same funnel and update the existing funnel instead of
        // disabling it and creating a new one
        $funnel = $this->getGoalFunnel($idSite, $idGoal);

        if (!empty($funnel['activated'])) {
            // when there is already an activated funnel, we need to disable the current activated funnel and then
            // create a new one to make sure to give it a new funnelId as we would otherwise mix existing reports
            // with differently configured funnels
            $this->funnelDao->disableFunnel($funnel['idfunnel'], $now);

        } else if (!empty($funnel['idfunnel'])) {
            $this->funnelDao->updateFunnel($funnel['idfunnel'], $isActivated, $steps);
            return $funnel['idfunnel'];
        }

       return $this->funnelDao->createGoalFunnel($idSite, $idGoal, $isActivated, $steps, $now);
    }

    private function enrichFunnel($funnel)
    {
        if (empty($funnel)) {
            return null;
        }

        $goal = $this->getGoal($funnel['idsite'], $funnel['idgoal']);

        if (empty($goal['name'])) {
            return null;
        }

        $funnel['name'] = str_replace('%', '%%', Translate::clean($goal['name']));
        $funnel[self::KEY_FINAL_STEP_POSITION] = count($funnel['steps']) + 1;

        return $funnel;
    }

    private function enrichFunnels($funnels)
    {
        $all = array();

        if (!empty($funnels)) {
            foreach ($funnels as $funnel) {
                $funnel = $this->enrichFunnel($funnel);
                if (!empty($funnel)) {
                    $all[] = $funnel;
                }
            }
        }

        return $all;
    }

    public function clearGoalsCache()
    {
        $this->goalsCache = array();
    }

    public function getNumGoals($idSite)
    {
        $goals = $this->getAllGoals($idSite);
        return count($goals);
    }

    public function getAllGoals($idSite)
    {
        if (!isset($this->goalsCache[$idSite])) {
            $this->goalsCache[$idSite] = Request::processRequest('Goals.getGoals', array(
                'idSite' => $idSite,
                'filter_limit' => '-1', // when requesting a report it might eg set filter_limit=5, we need to overwrite this
                'filter_offset' => 0,
                'filter_truncate' => '-1',
                'filter_pattern' => '',
                'hideColumns' => '',
                'showColumns' => '',
                'filter_pattern_recursive' => ''
            ));

            if ($this->hasEcommerce($idSite)) {
                $this->goalsCache[$idSite][GoalManager::IDGOAL_ORDER] = array(
                    'idgoal' => GoalManager::IDGOAL_ORDER,
                    'name' => Piwik::translate('Ecommerce_Sales')
                );
            }
        }

        return $this->goalsCache[$idSite];
    }

    private function hasEcommerce($idSite)
    {
        try {
            if (Site::isEcommerceEnabledFor($idSite)) {
                return true;
            }
        } catch (UnexpectedWebsiteFoundException $e) {
            // ignore this error, site was just deleted
        }

        return false;
    }

    private function getGoal($idSite, $idGoal)
    {
        $goals = $this->getAllGoals($idSite);

        if (isset($goals[$idGoal])) {
            return $goals[$idGoal];
        }
    }
}

