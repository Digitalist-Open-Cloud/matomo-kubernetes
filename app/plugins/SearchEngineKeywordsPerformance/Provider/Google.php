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

class Google extends ProviderAbstract
{
    /**
     * @inheritdoc
     */
    const ID = 'Google';

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'Google Search Console';
    }

    /**
     * @inheritdoc
     */
    public function getLogoUrls()
    {
        return [
            './plugins/SearchEngineKeywordsPerformance/images/Google.png'
        ];
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Piwik::translate('SearchEngineKeywordsPerformance_ProviderGoogleDescription');
    }

    /**
     * @inheritdoc
     */
    public function getNote()
    {
        return Piwik::translate('SearchEngineKeywordsPerformance_ProviderGoogleNote');
    }

    /**
     * @inheritdoc
     */
    public function getClient()
    {
        return StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google');
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

            if ($settings->googleSearchConsoleUrl && $settings->googleSearchConsoleUrl->getValue()) {
                $siteConfig['googleSearchConsoleUrl'] = $settings->googleSearchConsoleUrl->getValue();
                $siteConfig['googleWebKeywords']      = $settings->googleWebKeywords->getValue();
                $siteConfig['googleImageKeywords']    = $settings->googleImageKeywords->getValue();
                $siteConfig['googleVideoKeywords']    = $settings->googleVideoKeywords->getValue();
            }

            if (!empty($siteConfig)) {
                $configuredSites[$siteId] = $siteConfig;
            }
        }

        return $configuredSites;
    }

    public function getConfigurationProblems()
    {
        $errors = [
            'sites'    => $this->getSiteErrors(),
            'accounts' => $this->getAccountErrors()
        ];

        return $errors;
    }

    protected function getSiteErrors()
    {
        $errors            = [];
        $client            = $this->getClient();
        $accounts          = $client->getAccounts();
        $configuredSiteIds = $this->getConfiguredSiteIds();

        foreach ($configuredSiteIds as $configuredSiteId => $config) {
            $googleSiteUrl = $config['googleSearchConsoleUrl'];
            list($accountId, $url) = explode('##', $googleSiteUrl);

            if (!key_exists($accountId, $accounts)) {
                $errors[$configuredSiteId] = Piwik::translate('SearchEngineKeywordsPerformance_AccountDoesNotExist', ['']);
                continue;
            }

            $urls = $client->getAvailableUrls($accountId);

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
                $client->testConfiguration($id);
            } catch (\Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        return $errors;
    }
}
