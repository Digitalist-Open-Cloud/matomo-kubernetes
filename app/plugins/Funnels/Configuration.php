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

use Piwik\Config;

class Configuration
{
    const DEFAULT_NUM_ENTRIES_IN_ACTIONS = 100;
    const DEFAULT_NUM_ENTRIES_IN_REFERRERS = 50;
    const DEFAULT_NUM_ROWS_POPULATE_AT_ONCE = 60000;

    const KEY_MAX_ACTION_ROWS = 'funnels_num_max_rows_in_actions';
    const KEY_MAX_REFERRERS_ROWS = 'funnels_num_max_rows_in_referrers';
    const KEY_MAX_POPULATE_AT_ONCE = 'funnels_num_max_rows_populate_at_once';

    public function install()
    {
        $config = $this->getConfig();
        $config->Funnels = array(
            self::KEY_MAX_ACTION_ROWS => self::DEFAULT_NUM_ENTRIES_IN_ACTIONS,
            self::KEY_MAX_REFERRERS_ROWS => self::DEFAULT_NUM_ENTRIES_IN_REFERRERS,
            self::KEY_MAX_POPULATE_AT_ONCE => self::DEFAULT_NUM_ROWS_POPULATE_AT_ONCE
        );
        $config->forceSave();
    }

    public function uninstall()
    {
        $config = $this->getConfig();
        $config->Funnels = array();
        $config->forceSave();
    }

    /**
     * @return int
     */
    public function getMaxRowsInReferrers()
    {
        $config = $this->getConfig();
        $funnels = $config->Funnels;

        $numEntries = null;

        if (!empty($funnels[self::KEY_MAX_REFERRERS_ROWS])){
            $numEntries = (int) $funnels[self::KEY_MAX_REFERRERS_ROWS];
        }

        if (empty($numEntries)) {
            // eg when wrongly configured or not configured
            $numEntries = self::DEFAULT_NUM_ENTRIES_IN_REFERRERS;
        }

        return $numEntries;
    }

    /**
     * @return int
     */
    public function getMaxRowsInActions()
    {
        $config = $this->getConfig();
        $funnels = $config->Funnels;

        $numEntries = null;

        if (!empty($funnels[self::KEY_MAX_ACTION_ROWS])){
            $numEntries = (int) $funnels[self::KEY_MAX_ACTION_ROWS];
        }

        if (empty($numEntries)) {
            // eg when wrongly configured or not configured
            $numEntries = self::DEFAULT_NUM_ENTRIES_IN_ACTIONS;
        }

        return $numEntries;
    }

    /**
     * @return int
     */
    public function getMaxRowsToPopulateAtOnce()
    {
        $config = $this->getConfig();
        $funnels = $config->Funnels;

        $numEntries = null;

        if (!empty($funnels[self::KEY_MAX_POPULATE_AT_ONCE])){
            $numEntries = (int) $funnels[self::KEY_MAX_POPULATE_AT_ONCE];
        }

        if (empty($numEntries)) {
            // eg when wrongly configured or not configured
            $numEntries = self::DEFAULT_NUM_ROWS_POPULATE_AT_ONCE;
        }

        return $numEntries;
    }

    private function getConfig()
    {
        return Config::getInstance();
    }
}
