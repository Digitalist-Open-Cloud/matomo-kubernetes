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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Google as GoogleModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Bing as BingModel;
use Piwik\View;

class SearchEngineKeywordsPerformance extends \Piwik\Plugin
{
    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles'                   => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles'                   => 'getJSFiles',
            'Metrics.getDefaultMetricDocumentationTranslations' => 'addMetricDocumentationTranslations',
            'Metrics.getDefaultMetricTranslations'              => 'addMetricTranslations',
            'ViewDataTable.configure'                           => 'configureViewDataTable',
            'Translate.getClientSideTranslationKeys'            => 'getClientSideTranslationKeys',
        );
    }

    public function configureViewDataTable(ViewDataTable $viewDataTable)
    {
        if ($viewDataTable->requestConfig->apiMethodToRequestDataTable == 'Referrers.getKeywords') {
            if (Common::getRequestVar('widget', 0, 'int')) {
                return;
            }

            $view = new View('@SearchEngineKeywordsPerformance/messageReferrerKeywordsReport');
            $view->hasAdminPriviliges = Piwik::isUserHasSomeAdminAccess();
            $view->reportEnabled = false;

            $report = new \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetCrawlingErrorsGoogle();
            if ($report->isBingEnabled() || $report->isEnabled()) {
                $view->reportEnabled = true;
            }

            $message = $view->render();

            if (property_exists($viewDataTable->config, 'show_header_message')) {
                $viewDataTable->config->show_header_message = $message;
            } else {
                $viewDataTable->config->show_footer_message .= $message;
            }
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/SearchEngineKeywordsPerformance/stylesheets/styles.less";
    }

    public function getJSFiles(&$javascripts)
    {
        $javascripts[] = "plugins/SearchEngineKeywordsPerformance/javascripts/GoogleCrawlIssuesDataTable.js";
    }

    public function addMetricTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsTranslations());
    }

    public function addMetricDocumentationTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsDocumentation());
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = "SearchEngineKeywordsPerformance_LinksToUrl";
        $translationKeys[] = "SearchEngineKeywordsPerformance_SitemapsContainingUrl";
    }

    /**
     * Installation
     */
    public function install()
    {
        GoogleModel::install();
        BingModel::install();
    }
}
