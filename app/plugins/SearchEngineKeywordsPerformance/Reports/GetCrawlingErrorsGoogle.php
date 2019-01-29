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
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetCrawlingErrorsGoogle extends Base
{
    protected function init()
    {
        parent::init();
        $this->subcategoryId = 'SearchEngineKeywordsPerformance_CrawlingStats';
        $this->name          = Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlingErrors');
        $this->documentation = Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlingErrorsDocumentation');
        $this->defaultSortColumn = null;
        $this->metrics           = [];
        $this->order             = 11;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_limit_control     = false;
        $view->config->show_all_views_icons   = false;
        $view->config->show_table_all_columns = false;
        $view->config->setDefaultColumnsToDisplay([
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOUND,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOUND
        ], false, false);
        $view->config->addTranslations([
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOUND                       => Piwik::translate('SearchEngineKeywordsPerformance_PrefixDesktop') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFound'),
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOLLOWED                    => Piwik::translate('SearchEngineKeywordsPerformance_PrefixDesktop') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFollowed'),
            GoogleArchiver::CRAWLERRORS_WEB_AUTH_PERMISSION                 => Piwik::translate('SearchEngineKeywordsPerformance_PrefixDesktop') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlAuthPermission'),
            GoogleArchiver::CRAWLERRORS_WEB_SERVER_ERROR                    => Piwik::translate('SearchEngineKeywordsPerformance_PrefixDesktop') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlServerError'),
            GoogleArchiver::CRAWLERRORS_WEB_SOFT404                         => Piwik::translate('SearchEngineKeywordsPerformance_PrefixDesktop') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlSoft404'),
            GoogleArchiver::CRAWLERRORS_WEB_OTHER_ERROR                     => Piwik::translate('SearchEngineKeywordsPerformance_PrefixDesktop') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlOtherError'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOUND            => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFound'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOLLOWED         => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFollowed'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_AUTH_PERMISSION      => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlAuthPermission'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SERVER_ERROR         => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlServerError'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SOFT404              => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlSoft404'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_ROBOTED              => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlRoboted'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_MANY_TO_ONE_REDIRECT => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlManyRedirect'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_FLASH_CONTENT        => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlFlash'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_OTHER_ERROR          => Piwik::translate('SearchEngineKeywordsPerformance_PrefixSmartphone') . Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlOtherError'),
        ]);
        $view->config->selectable_columns = [
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOUND,
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOLLOWED,
            GoogleArchiver::CRAWLERRORS_WEB_AUTH_PERMISSION,
            GoogleArchiver::CRAWLERRORS_WEB_SERVER_ERROR,
            GoogleArchiver::CRAWLERRORS_WEB_SOFT404,
            GoogleArchiver::CRAWLERRORS_WEB_OTHER_ERROR,
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

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite)) {
            return;
        }

        $subcategory = 'SearchEngineKeywordsPerformance_CrawlingStats';

        $widgets = [];

        $config = $factory->createWidget();
        $config->forceViewDataTable(Evolution::ID);
        $config->setSubcategoryId($subcategory);
        $config->setIsNotWidgetizable();
        $widgets[] = $config;

        $config = $factory->createWidget();
        $config->setAction('getCrawlingErrorsGoogleDesktop');
        $config->forceViewDataTable(Sparklines::ID);
        $config->setSubcategoryId($subcategory);
        $config->setName('');
        $config->setIsNotWidgetizable();
        $widgets[] = $config;

        $config = $factory->createWidget();
        $config->setAction('getCrawlingErrorsGoogleSmartphone');
        $config->forceViewDataTable(Sparklines::ID);
        $config->setSubcategoryId($subcategory);
        $config->setName('');
        $config->setIsNotWidgetizable();
        $widgets[] = $config;


        $config = $factory->createContainerWidget('CrawlingStatsGoogle');
        $config->setCategoryId($widgets[0]->getCategoryId());
        $config->setSubcategoryId($subcategory);
        $config->setIsWidgetizable();

        foreach ($widgets as $widget) {
            $config->addWidgetConfig($widget);
        }

        $widgetsList->addWidgetConfigs([$config]);
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
