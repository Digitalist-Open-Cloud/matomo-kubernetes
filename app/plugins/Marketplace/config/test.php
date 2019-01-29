<?php

use Interop\Container\ContainerInterface;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Consumer as MockConsumer;
use Piwik\Plugins\Marketplace\LicenseKey;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Service as MockService;
use Piwik\Plugins\Marketplace\Input\PurchaseType;

return array(
    'MarketplaceEndpoint' => function (ContainerInterface $c) {
        // if you wonder why this here is configured here again, and the same as in `config.php`,
        // it is because someone might have overwritten MarketplaceEndpoit in local config.php and we want
        // to make sure system tests of marketplace are ran against plugins.piwik.org
        $domain = 'http://plugins.piwik.org';
        $updater = $c->get('Piwik\Plugins\CoreUpdater\Updater');

        if ($updater->isUpdatingOverHttps()) {
            $domain = str_replace('http://', 'https://', $domain);
        }

        return $domain;
    },
    'Piwik\Plugins\Marketplace\Consumer' => function (ContainerInterface $c) {
        $consumerTest = $c->get('test.vars.consumer');
        $licenseKey = new LicenseKey();

        if ($consumerTest == 'validLicense') {
            $consumer = MockConsumer::buildValidLicense();
            $licenseKey->set('123456789');
        } elseif ($consumerTest == 'exceededLicense') {
            $consumer = MockConsumer::buildExceededLicense();
            $licenseKey->set('1234567891');
        } elseif ($consumerTest == 'expiredLicense') {
            $consumer = MockConsumer::buildExpiredLicense();
            $licenseKey->set('1234567892');
        } else {
            $consumer = MockConsumer::buildNoLicense();
            $licenseKey->set(null);
        }

        return $consumer;
    },
    'Piwik\Plugins\Marketplace\Plugins' => DI\decorate(function ($previous, ContainerInterface $c) {
        /** @var \Piwik\Plugins\Marketplace\Plugins $previous */
        $previous->setPluginsHavingUpdateCache(null);

        $pluginNames = $c->get('test.vars.mockMarketplaceAssumePluginNamesActivated');

        if (!empty($pluginNames)) {
            /** @var \Piwik\Plugins\Marketplace\Plugins $previous */
            $previous->setActivatedPluginNames($pluginNames);
        }

        return $previous;
    }),
    'Piwik\Plugins\Marketplace\Api\Client' => DI\decorate(function ($previous) {
        /** @var \Piwik\Plugins\Marketplace\Api\Client $previous */
        $previous->clearAllCacheEntries();

        return $previous;
    }),
    'Piwik\Plugins\Marketplace\Plugins\InvalidLicenses' => DI\decorate(function ($previous, ContainerInterface $c) {

        $pluginNames = $c->get('test.vars.mockMarketplaceAssumePluginNamesActivated');

        if (!empty($pluginNames)) {
            /** @var \Piwik\Plugins\Marketplace\Plugins\InvalidLicenses $previous */
            $previous->setActivatedPluginNames($pluginNames);
            $previous->clearCache();
        }

        return $previous;

    }),
    'Piwik\Plugins\Marketplace\Api\Service' => DI\decorate(function ($previous, ContainerInterface $c) {
        if (!$c->get('test.vars.mockMarketplaceApiService')) {
            return $previous;
        }

        // for ui tests
        $service = new MockService();

        $key = new LicenseKey();
        $accessToken = $key->get();

        $service->authenticate($accessToken);

        function removeReviewsUrl($content)
        {
            $content = json_decode($content, true);
            if (!empty($content['shop']['reviews']['embedUrl'])) {
                $content['shop']['reviews']['embedUrl'] = '';
            }
            return json_encode($content);
        }

        $isExceededUser = $c->get('test.vars.consumer') === 'exceededLicense';
        $isExpiredUser = $c->get('test.vars.consumer') === 'expiredLicense';
        $isValidUser = $c->get('test.vars.consumer') === 'validLicense';

        $service->setOnDownloadCallback(function ($action, $params) use ($service, $isExceededUser, $isValidUser, $isExpiredUser) {
            if ($action === 'info') {
                return $service->getFixtureContent('v2.0_info.json');
            } elseif ($action === 'consumer' && $service->getAccessToken() === 'valid') {
                return $service->getFixtureContent('v2.0_consumer-access_token-consumer2_paid1.json');
            } elseif ($action === 'consumer/validate' && $service->getAccessToken() === 'valid') {
                return $service->getFixtureContent('v2.0_consumer_validate-access_token-consumer2_paid1.json');
            } elseif ($action === 'consumer' && $service->getAccessToken() === 'invalid') {
                return $service->getFixtureContent('v2.0_consumer-access_token-notexistingtoken.json');
            } elseif ($action === 'consumer/validate' && $service->getAccessToken() === 'invalid') {
                return $service->getFixtureContent('v2.0_consumer_validate-access_token-notexistingtoken.json');
            } elseif ($action === 'plugins' && empty($params['purchase_type']) && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins.json');
            } elseif ($action === 'plugins' && $isExceededUser && !empty($params['purchase_type']) && $params['purchase_type'] === PurchaseType::TYPE_PAID && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-paid-num_users-201-access_token-consumer2_paid1.json');
            } elseif ($action === 'plugins' && $isExpiredUser && !empty($params['purchase_type']) && $params['purchase_type'] === PurchaseType::TYPE_PAID && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-paid-access_token-consumer1_paid2_custom1.json');
            } elseif ($action === 'plugins' && ($service->hasAccessToken() || $isValidUser) && !empty($params['purchase_type']) && $params['purchase_type'] === PurchaseType::TYPE_PAID && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-paid-access_token-consumer2_paid1.json');
            } elseif ($action === 'plugins' && !$service->hasAccessToken() && !empty($params['purchase_type']) && $params['purchase_type'] === PurchaseType::TYPE_PAID && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-paid-access_token-notexistingtoken.json');
            } elseif ($action === 'themes' && empty($params['purchase_type']) && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_themes.json');
            } elseif ($action === 'plugins/Barometer/info') {
                return $service->getFixtureContent('v2.0_plugins_Barometer_info.json');
            } elseif ($action === 'plugins/TreemapVisualization/info') {
                return $service->getFixtureContent('v2.0_plugins_TreemapVisualization_info.json');
            } elseif ($action === 'plugins/PaidPlugin1/info' && $service->hasAccessToken() && $isExceededUser) {
                $content = $service->getFixtureContent('v2.0_plugins_PaidPlugin1_info-purchase_type-paid-num_users-201-access_token-consumer2_paid1.json');
                return removeReviewsUrl($content);
            } elseif ($action === 'plugins/PaidPlugin1/info' && $service->hasAccessToken()) {
                $content = $service->getFixtureContent('v2.0_plugins_PaidPlugin1_info-access_token-consumer3_paid1_custom2.json');
                return removeReviewsUrl($content);
            } elseif ($action === 'plugins/PaidPlugin1/info' && !$service->hasAccessToken()) {
                $content = $service->getFixtureContent('v2.0_plugins_PaidPlugin1_info.json');
                return removeReviewsUrl($content);
            } elseif ($action === 'plugins/checkUpdates') {
                return $service->getFixtureContent('v2.0_plugins_checkUpdates-pluginspluginsnameAnonymousPi.json');
            }
        });

        return $service;
    })
);