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
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Configuration;

use Piwik\Option;

class Bing
{
    /**
     * Key used to store accounts in options table
     */
    const CLIENT_CONFIG_OPTION_NAME = 'SearchEngineKeywordsPerformance_Bing_Accounts';

    protected $accounts = [];

    /**
     * Returns stored accounts
     *
     * @return array
     */
    public function getAccounts()
    {
        if (empty($this->accounts)) {
            $accounts = Option::get(self::CLIENT_CONFIG_OPTION_NAME);
            $accounts = @json_decode($accounts, true);
            if (is_array($accounts)) {
                $this->accounts = $accounts;
            }
        }
        return $this->accounts;
    }

    /**
     * Adds new account
     *
     * @param $apiKey
     * @param $username
     */
    public function addAccount($apiKey, $username)
    {
        $currentAccounts = (array)$this->getAccounts();

        if (array_key_exists($apiKey, $currentAccounts)) {
            return;
        }

        $currentAccounts[$apiKey] = [
            'apiKey'   => $apiKey,
            'username' => $username,
            'created'  => time()
        ];

        $this->setAccounts($currentAccounts);
    }

    /**
     * Removes account with given API-Key
     *
     * @param $apiKey
     */
    public function removeAccount($apiKey)
    {
        $currentAccounts = (array)$this->getAccounts();

        unset($currentAccounts[$apiKey]);

        $this->setAccounts($currentAccounts);
    }

    protected function setAccounts($newAccounts)
    {
        $accounts = json_encode($newAccounts);

        Option::set(self::CLIENT_CONFIG_OPTION_NAME, $accounts);

        $this->accounts = [];
    }
}
