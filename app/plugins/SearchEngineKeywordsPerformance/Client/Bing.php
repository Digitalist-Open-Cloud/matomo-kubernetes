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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Client;

use Piwik\Http;
use Piwik\Piwik;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Configuration\Bing as Configuration;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidCredentialsException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\UnknownAPIException;

class Bing
{
    /**
     * Bing API Error codes (see https://msdn.microsoft.com/en-us/library/hh969357.aspx)
     */
    const API_ERROR_INTERNAL = 1;
    const API_ERROR_UNKNOWN = 2;
    const API_ERROR_INVALID_API_KEY = 3;
    const API_ERROR_THROTTLE_USER = 4;
    const API_ERROR_THROTTLE_HOST = 5;
    const API_ERROR_USER_BLOCKED = 6;
    const API_ERROR_INVALID_URL = 7;
    const API_ERROR_INVALID_PARAM = 8;
    const API_ERROR_TOO_MANY_SITES = 9;
    const API_ERROR_USER_NOT_FOUND = 10;
    const API_ERROR_NOT_FOUND = 11;
    const API_ERROR_ALREADY_EXISTS = 12;
    const API_ERROR_NOT_ALLOWED = 13;
    const API_ERROR_NOT_AUTHORIZED = 14;

    /**
     * @var Configuration
     */
    protected $configuration = null;

    /**
     * Base URL of bing API
     *
     * @var string
     */
    protected $baseAPIUrl = 'https://ssl.bing.com/webmaster/api.svc/json/';

    /**
     * Bing constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Returns configured client api keys
     *
     * @return array
     */
    public function getAccounts()
    {
        return $this->configuration->getAccounts();
    }

    /**
     * Removes client api key
     *
     * @param string $apiKey
     * @return boolean
     */
    public function removeAccount($apiKey)
    {
        $this->configuration->removeAccount($apiKey);
        Piwik::postEvent('SearchEngineKeywordsPerformance.AccountRemoved', [
            [
                'provider' => \Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Bing::getInstance()->getName(),
                'account'  => substr($apiKey, 0, 5) . '*****' . substr($apiKey, -5, 5)
            ]
        ]);
        return true;
    }

    /**
     * Adds a client api key
     *
     * @param $apiKey
     * @param $username
     * @return boolean
     */
    public function addAccount($apiKey, $username)
    {
        $this->configuration->addAccount($apiKey, $username);
        Piwik::postEvent('SearchEngineKeywordsPerformance.AccountAdded', [
            [
                'provider' => \Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Bing::getInstance()->getName(),
                'account'  => substr($apiKey, 0, 5) . '*****' . substr($apiKey, -5, 5)
            ]
        ]);
        return true;
    }

    /**
     * Returns if client is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return count($this->getAccounts()) > 0;
    }

    /**
     * Checks if API key can be used to query the API
     *
     * @param $apiKey
     * @return bool
     * @throws \Exception
     */
    public function testConfiguration($apiKey)
    {
        $data = $this->sendAPIRequest($apiKey, 'GetUserSites');

        if (empty($data)) {
            throw new \Exception('Unknown error');
        }

        return true;
    }

    /**
     * Returns the urls, keyword data is available for (in connected google account)
     *
     * @param $apiKey
     *
     * @return array
     */
    public function getAvailableUrls($apiKey, $removeUrlsWithoutAccess = true)
    {
        try {
            $data = $this->sendAPIRequest($apiKey, 'GetUserSites');
        } catch (\Exception $e) {
            return [];
        }

        if (empty($data) || !is_array($data['d'])) {
            return [];
        }

        $urls = [];
        foreach ($data['d'] as $item) {
            if (!$removeUrlsWithoutAccess || $item['IsVerified']) {
                $urls[$item['Url']] = $item['IsVerified'];
            }
        }

        return $urls;
    }

    /**
     * Returns search anyalytics data from Bing API
     *
     * @param string $apiKey
     * @param string $url
     * @return array
     */
    public function getSearchAnalyticsData($apiKey, $url)
    {
        $keywordDataSets = $this->sendAPIRequest($apiKey, 'GetQueryStats', array('siteUrl' => $url));
        $keywords        = [];

        if (empty($keywordDataSets) || !is_array($keywordDataSets['d'])) {
            return [];
        }

        foreach ($keywordDataSets['d'] as $keywordDataSet) {
            $timestamp = substr($keywordDataSet['Date'], 6, 10);
            $date      = date('Y-m-d', $timestamp);

            if (!isset($keywords[$date])) {
                $keywords[$date] = [];
            }

            $keywords[$date][] = [
                'keyword'     => $keywordDataSet['Query'],
                'clicks'      => $keywordDataSet['Clicks'],
                'impressions' => $keywordDataSet['Impressions'],
                'position'    => $keywordDataSet['AvgImpressionPosition'],
            ];
        }

        return $keywords;
    }

    /**
     * Returns crawl statistics from Bing API
     *
     * @param string $apiKey
     * @param string $url
     * @return array
     */
    public function getCrawlStats($apiKey, $url)
    {
        $crawlStatsDataSets = $this->sendAPIRequest($apiKey, 'GetCrawlStats', array('siteUrl' => $url));
        $crawlStats         = [];

        if (empty($crawlStatsDataSets) || !is_array($crawlStatsDataSets['d'])) {
            return [];
        }

        foreach ($crawlStatsDataSets['d'] as $crawlStatsDataSet) {
            $timestamp = substr($crawlStatsDataSet['Date'], 6, 10);
            $date      = date('Y-m-d', $timestamp);
            unset($crawlStatsDataSet['Date']);

            $crawlStats[$date] = $crawlStatsDataSet;
        }

        return $crawlStats;
    }


    /**
     * Returns urls with crawl issues from Bing API
     *
     * @param string $apiKey
     * @param string $url
     * @return array
     */
    public function getUrlWithCrawlIssues($apiKey, $url)
    {
        $crawlErrorsDataSets = $this->sendAPIRequest($apiKey, 'GetCrawlIssues', array('siteUrl' => $url));
        $crawlErrors         = [];

        if (empty($crawlErrorsDataSets) || !is_array($crawlErrorsDataSets['d'])) {
            return [];
        }

        $crawlIssueMapping = [
            1 => 'Code301',
            2 => 'Code302',
            4 => 'Code4xx',
            8 => 'Code5xx',
            16 => 'BlockedByRobotsTxt',
            32 => 'ContainsMalware',
            64 => 'ImportantUrlBlockedByRobotsTxt'
        ];

        foreach ($crawlErrorsDataSets['d'] as $crawlStatsDataSet) {
            $crawlStatsDataSet['Issues'] = $crawlIssueMapping[$crawlStatsDataSet['Issues']];

            $crawlErrors[] = $crawlStatsDataSet;
        }

        return $crawlErrors;
    }

    /**
     * Executes a API-Request to Bing with the given parameters
     *
     * @param string $apiKey
     * @param string $method
     * @param array  $params
     * @return mixed
     * @throws InvalidCredentialsException
     * @throws \Exception
     */
    protected function sendAPIRequest($apiKey, $method, $params = [])
    {
        $params['apikey'] = $apiKey;

        $url = $this->baseAPIUrl . $method . '?' . Http::buildQuery($params);

        $retries = 0;
        while ($retries < 5) {

            $response = Http::sendHttpRequest($url, 2000);

            $response = json_decode($response, true);

            if (!empty($response['ErrorCode'])) {

                $isThrottled = (strpos($response['Message'], 'ThrottleUser') !== false ||
                                strpos($response['Message'], 'ThrottleHost') !== false ||
                                in_array($response['ErrorCode'], [self::API_ERROR_THROTTLE_HOST, self::API_ERROR_THROTTLE_USER]));

                $isUnknownError = $response['ErrorCode'] === self::API_ERROR_UNKNOWN;

                if (($isThrottled || $isUnknownError) && $retries < 4) {
                    $retries++;
                    usleep($retries*500);
                    continue;
                }

                if ($isUnknownError) {
                    throw new UnknownAPIException($response['Message'], $response['ErrorCode']);
                }

                $authenticationError = (strpos($response['Message'], 'NotAuthorized') !== false ||
                                        strpos($response['Message'], 'InvalidApiKey') !== false ||
                                        in_array($response['ErrorCode'], [self::API_ERROR_NOT_AUTHORIZED, self::API_ERROR_INVALID_API_KEY]));

                if ($authenticationError) {
                    throw new InvalidCredentialsException($response['Message'], $response['ErrorCode']);
                }


                throw new \Exception($response['Message'], $response['ErrorCode']);
            }

            return $response;
        }

        return null;
    }
}
