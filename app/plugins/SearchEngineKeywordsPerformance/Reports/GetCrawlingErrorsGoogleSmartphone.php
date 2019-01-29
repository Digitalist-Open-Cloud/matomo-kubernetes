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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Reports;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;

class GetCrawlingErrorsGoogleSmartphone extends Base
{
    protected function init()
    {
        parent::init();
        $this->subcategoryId = '';
        $this->name          = Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlingErrorsSmartphone');
        $this->documentation = Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlingErrorsSmartphoneDocumentation');
        $this->defaultSortColumn = null;
        $this->metrics           = [];
        $this->order             = 11;
    }

    public function configureView(ViewDataTable $view)
    {
        $period = Common::getRequestVar('period', false, 'string');
        if ($period != 'day') {
            $view->config->show_footer_message .=
                '<p style="margin-top:2em;margin-bottom:2em" class=" alert-info alert">' .
                Piwik::translate('SearchEngineKeywordsPerformance_ReportShowMaximumValues') .
                '</p>';
        }

        $view->config->show_limit_control     = false;
        $view->config->show_all_views_icons   = false;
        $view->config->show_table_all_columns = false;
        $view->config->setDefaultColumnsToDisplay([
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOUND,
        ], false, false);
        $view->config->addTranslations([
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOUND            => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFound'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOLLOWED         => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFollowed'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_AUTH_PERMISSION      => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlAuthPermission'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SERVER_ERROR         => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlServerError'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SOFT404              => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlSoft404'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_ROBOTED              => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlRoboted'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_MANY_TO_ONE_REDIRECT => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlManyRedirect'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_FLASH_CONTENT        => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlFlash'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_OTHER_ERROR          => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlOtherError'),
        ]);
        $view->config->selectable_columns = [
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOUND,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOLLOWED,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_AUTH_PERMISSION,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SERVER_ERROR,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SOFT404,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_ROBOTED,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_MANY_TO_ONE_REDIRECT,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_FLASH_CONTENT,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_OTHER_ERROR,
        ];

        $this->configureSegmentNotSupported($view);
        $this->formatColumnsAsNumbers($view, $view->config->selectable_columns);
    }

    public function isEnabled()
    {
        $idSite = Common::getRequestVar('idSite', false, 'int');

        if (empty($idSite)) {
            return false;
        }

        $setting = new MeasurableSettings($idSite);
        return !empty($setting->googleSearchConsoleUrl) && $setting->googleSearchConsoleUrl->getValue();
    }
}
