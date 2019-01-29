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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Provider;

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SitesManager\Model as SitesManagerModel;

class Bing extends ProviderAbstract
{
    /**
     * @inheritdoc
     */
    const ID = 'Bing';

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'Bing Webmaster API';
    }

    /**
     * @inheritdoc
     */
    public function getLogoUrls()
    {
        return [
            './plugins/SearchEngineKeywordsPerformance/images/Bing.png',
            './plugins/SearchEngineKeywordsPerformance/images/Yahoo.png',
        ];
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Piwik::translate('SearchEngineKeywordsPerformance_ProviderBingDescription');
    }

    /**
     * @inheritdoc
     */
    public function getNote()
    {
        return Piwik::translate('SearchEngineKeywordsPerformance_ProviderBingNote');
    }

    /**
     * @inheritdoc
     */
    public function getClient()
    {
        return StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Bing');
    }

    /**
     * @inheritdoc
     */
    public function isConfigured()
    {
        return $this->getClient()->isConfigured();
    }

    /**
     * @inheritdoc
     */
    public function getConfiguredSiteIds()
    {
        $siteManagerModel = new SitesManagerModel();
        $allSiteIds       = $siteManagerModel->getSitesId();

        $configuredSites = array();

        foreach ($allSiteIds as $siteId) {
            if (!Piwik::isUserHasAdminAccess($siteId)) {
                continue; // skip sites without access
            }

            $settings = new MeasurableSettings($siteId);

            $siteConfig = array();

            if ($settings->bingSiteUrl && $settings->bingSiteUrl->getValue()) {
                $siteConfig['bingSiteUrl'] = $settings->bingSiteUrl->getValue();
            }

            if (!empty($siteConfig)) {
                $configuredSites[$siteId] = $siteConfig;
            }
        }

        return $configuredSites;
    }

    public function getConfigurationProblems()
    {
        return [
            'sites'    => $this->getSiteErrors(),
            'accounts' => $this->getAccountErrors()
        ];
    }

    protected function getSiteErrors()
    {
        $errors            = [];
        $client            = $this->getClient();
        $accounts          = $client->getAccounts();
        $configuredSiteIds = $this->getConfiguredSiteIds();

        foreach ($configuredSiteIds as $configuredSiteId => $config) {
            $bingSiteUrl = $config['bingSiteUrl'];
            list($apiKey, $url) = explode('##', $bingSiteUrl);

            if (!key_exists($apiKey, $accounts)) {
                $errors[$configuredSiteId] = Piwik::translate('SearchEngineKeywordsPerformance_AccountDoesNotExist', [$this->obfuscateApiKey($apiKey)]);
                continue;
            }

            $urls = $client->getAvailableUrls($apiKey);

            if (!key_exists($url, $urls)) {
                $errors[$configuredSiteId] = Piwik::translate('SearchEngineKeywordsPerformance_ConfiguredUrlNotAvailable');
                continue;
            }
        }

        return $errors;
    }

    protected function getAccountErrors()
    {
        $errors   = [];
        $client   = $this->getClient();
        $accounts = $client->getAccounts();

        if (empty($accounts)) {
            return [];
        }

        foreach ($accounts as $id => $account) {
            try {
                $client->testConfiguration($account['apiKey']);
            } catch (\Exception $e) {
                $errors[$id] = Piwik::translate('SearchEngineKeywordsPerformance_BingAccountError', $e->getMessage());
            }
        }

        return $errors;
    }

    protected function obfuscateApiKey($apiKey)
    {
        return substr($apiKey, 0, 5) . '*****' . substr($apiKey, -5, 5);
    }
}
