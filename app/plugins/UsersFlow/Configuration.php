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

namespace Piwik\Plugins\UsersFlow;

use Piwik\Config;
use Piwik\Plugins\UsersFlow\Archiver\DataSources;
use Piwik\ViewDataTable\Manager;

class Configuration
{
    const DEFAULT_MAX_STEPS = 10;
    const DEFAULT_MAX_ACTIONS_PER_TABLE = 100;
    const DEFAULT_MAX_LINKS_PER_INTERACTION = 5000;
    const DEFAULT_LEVEL_OF_DETAIL = 4;
    const DEFAULT_NUM_ACTIONS_PER_STEP = 5;

    const KEY_MAX_STEPS = 'UsersFlow_num_max_steps';
    const KEY_MAX_ACTIONS_PER_TABLE = 'UsersFlow_num_max_rows_in_actions';
    const KEY_MAX_LINKS_PER_INTERACTION = 'UsersFlow_num_max_links_per_interaction';

    public function install()
    {
        $config = $this->getConfig();
        $config->UsersFlow = array(
            self::KEY_MAX_STEPS => self::DEFAULT_MAX_STEPS,
            self::KEY_MAX_ACTIONS_PER_TABLE => self::DEFAULT_MAX_ACTIONS_PER_TABLE,
            self::KEY_MAX_LINKS_PER_INTERACTION => self::DEFAULT_MAX_LINKS_PER_INTERACTION,
        );
        $config->forceSave();
    }

    public function uninstall()
    {
        $config = $this->getConfig();
        $config->UsersFlow = array();
        $config->forceSave();
    }

    /**
     * @return int
     */
    public function getMaxRowsInActions()
    {
        return $this->getIntegerConfigSetting(self::KEY_MAX_ACTIONS_PER_TABLE, self::DEFAULT_MAX_ACTIONS_PER_TABLE);
    }

    /**
     * @return int
     */
    public function getMaxLinksPerInteractions()
    {
        return $this->getIntegerConfigSetting(self::KEY_MAX_LINKS_PER_INTERACTION, self::DEFAULT_MAX_LINKS_PER_INTERACTION);
    }

    /**
     * @return int
     */
    public function getMaxSteps()
    {
        $maxSteps = $this->getIntegerConfigSetting(self::KEY_MAX_STEPS, self::DEFAULT_MAX_STEPS);
        if ($maxSteps < 3) {
            $maxSteps = 3;
        }
        return $maxSteps;
    }

    public function getUsersFlowReportParams($login)
    {
        $params = Manager::getViewDataTableParameters($login, 'UsersFlow.getUsersFlow');

        if (!empty($params['levelOfDetail'])) {
            $levelOfDetail = (int) $params['levelOfDetail'];
        } else {
            $levelOfDetail = self::DEFAULT_LEVEL_OF_DETAIL;
        }
        if ($levelOfDetail < 1 || $levelOfDetail > 6) {
            $levelOfDetail = self::DEFAULT_LEVEL_OF_DETAIL;
        }

        if (isset($params['userFlowSource'])) {
            $userFlowSource = DataSources::getValidDataSource($params['userFlowSource']);
        } else {
            $userFlowSource = DataSources::getValidDataSource('');
        }

        if (!empty($params['numActionsPerStep'])) {
            $numActionsPerStep = (int) $params['numActionsPerStep'];
        } else {
            $numActionsPerStep = self::DEFAULT_NUM_ACTIONS_PER_STEP;
        }

        if ($numActionsPerStep < 4) {
            $numActionsPerStep = self::DEFAULT_NUM_ACTIONS_PER_STEP;
        }

        return array(
            'levelOfDetail' => $levelOfDetail,
            'numActionsPerStep' => $numActionsPerStep,
            'userFlowSource' => $userFlowSource
        );
    }

    /**
     * @return int
     */
    private function getIntegerConfigSetting($key, $default)
    {
        $config = $this->getConfig();
        $usersFlow = $config->UsersFlow;

        $value = null;

        if (!empty($usersFlow[$key])){
            $value = (int) $usersFlow[$key];
        }

        if (empty($value)) {
            // eg when wrongly configured or not configured
            $value = $default;
        }

        return $value;
    }

    private function getConfig()
    {
        return Config::getInstance();
    }
}
