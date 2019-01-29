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
use Piwik\Date;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetCrawlingErrorExamplesBing extends Base
{
    protected function init()
    {
        parent::init();
        $idSite                  = Common::getRequestVar('idSite', false, 'int');
        $dateOfLastImport        = (int)Option::get('BingImporterTask_LastRun_' . $idSite);
        $dateOfLastImport        = $dateOfLastImport ? Date::factory($dateOfLastImport)->getLocalized(Date::DATETIME_FORMAT_SHORT) : Piwik::translate('General_Never');
        $this->categoryId        = 'General_Actions';
        $this->subcategoryId     = 'SearchEngineKeywordsPerformance_CrawlingErrors';
        $this->name              = Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlErrors');
        $this->documentation     = Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlErrorsFromDateX', $dateOfLastImport);
        $this->defaultSortColumn = 'label';
        $this->metrics           = [];
        $this->order             = 2;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_all_views_icons   = false;
        $view->config->show_table_all_columns = false;
        $view->config->disable_row_evolution  = true;
        $view->config->addTranslations([
            'label'         => Piwik::translate('Actions_ColumnPageURL'),
            'category'      => Piwik::translate('SearchEngineKeywordsPerformance_Category'),
            'inLinks'       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlInboundLink'),
            'responseCode'  => Piwik::translate('SearchEngineKeywordsPerformance_ResponseCode')
        ]);

        $translations = [
            'Code301' => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus301'),
            'Code302' => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus302'),
            'Code4xx' => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus4xx'),
            'Code5xx' => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus5xx'),
            'BlockedByRobotsTxt' => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlBlockedByRobotsTxt'),
            'ContainsMalware' => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlMalwareInfected'),
            'ImportantUrlBlockedByRobotsTxt' => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlImportantBlockedByRobotsTxt'),
        ];

        $view->config->filters[] = array(
            'ColumnCallbackReplace',
            array(
                'category',
                function ($val) use ($translations) {
                    return array_key_exists($val, $translations) ? $translations[$val] : $val;
                }
            )
        );

        $this->configureSegmentNotSupported($view);
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widget = $factory->createWidget();
        $widget->setIsWide();
        $widgetsList->addWidgetConfig($widget);
    }

    public function isEnabled()
    {
        $idSite = Common::getRequestVar('idSite', false, 'int');

        if (empty($idSite)) {
            return false;
        }

        $setting = new MeasurableSettings($idSite);
        return !empty($setting->bingSiteUrl) && $setting->bingSiteUrl->getValue();
    }
}
