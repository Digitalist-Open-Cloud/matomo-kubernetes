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

namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Diagnostic;

use Piwik\Plugins\Diagnostics\Diagnostic\Diagnostic;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Bing;
use Piwik\Site;
use Piwik\Translation\Translator;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Bing as ProviderBing;

/**
 * Check the used bing accounts.
 */
class BingAccountDiagnostic implements Diagnostic
{
    /**
     * @var Translator
     */
    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function execute()
    {
        $client   = ProviderBing::getInstance()->getClient();
        $accounts = $client->getAccounts();

        if (empty($accounts)) {
            return []; // skip if no accounts configured
        }

        $errors = ProviderBing::getInstance()->getConfigurationProblems();

        $resultAccounts = new DiagnosticResult(
            Bing::getInstance()->getName() . ' - ' . $this->translator->translate('SearchEngineKeywordsPerformance_ConfiguredAccounts')
        );

        foreach ($accounts as $account) {
            if (array_key_exists($account['apiKey'], $errors['accounts'])) {
                $item = new DiagnosticResultItem(
                    DiagnosticResult::STATUS_ERROR,
                    $this->obfuscateApiKey($account['apiKey']) . ': ' .
                    $errors['accounts'][$account['apiKey']]

                );
            } else {
                $item = new DiagnosticResultItem(
                    DiagnosticResult::STATUS_OK,
                    $this->obfuscateApiKey($account['apiKey']) . ': ' .
                    $this->translator->translate('SearchEngineKeywordsPerformance_BingAccountOk')
                );
            }
            $resultAccounts->addItem($item);
        }

        $resultMeasurables = new DiagnosticResult(
            Bing::getInstance()->getName() . ' - ' . $this->translator->translate('SearchEngineKeywordsPerformance_MeasurableConfig')
        );

        $configuredSiteIds = ProviderBing::getInstance()->getConfiguredSiteIds();

        foreach ($configuredSiteIds as $configuredSiteId => $config) {

            if (array_key_exists($configuredSiteId, $errors['sites'])) {
                $item = new DiagnosticResultItem(
                    DiagnosticResult::STATUS_ERROR,
                    Site::getNameFor($configuredSiteId) . ' (' . Site::getMainUrlFor($configuredSiteId) . ')' . ': ' . $errors['sites'][$configuredSiteId]
                );
            } else {
                $item = new DiagnosticResultItem(
                    DiagnosticResult::STATUS_OK,
                    Site::getNameFor($configuredSiteId) . ' (' . Site::getMainUrlFor($configuredSiteId) . ')'
                );
            }
            $resultMeasurables->addItem($item);
        }

        return [$resultAccounts, $resultMeasurables];
    }

    protected function obfuscateApiKey($apiKey)
    {
        return substr($apiKey, 0, 5) . '*****' . substr($apiKey, -5, 5);
    }
}
