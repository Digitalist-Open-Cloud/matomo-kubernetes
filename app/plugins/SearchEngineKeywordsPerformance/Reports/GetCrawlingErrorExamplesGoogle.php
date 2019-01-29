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

class GetCrawlingErrorExamplesGoogle extends Base
{
    protected function init()
    {
        parent::init();
        $idSite                  = Common::getRequestVar('idSite', false, 'int');
        $dateOfLastImport        = (int)Option::get('GoogleImporterTask_LastRun_' . $idSite);
        $dateOfLastImport        = $dateOfLastImport ? Date::factory($dateOfLastImport)->getLocalized(Date::DATETIME_FORMAT_SHORT) : Piwik::translate('General_Never');
        $this->categoryId        = 'General_Actions';
        $this->subcategoryId     = 'SearchEngineKeywordsPerformance_CrawlingErrors';
        $this->name              = Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlingErrors');
        $this->documentation     = Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlErrorsFromDateX', $dateOfLastImport);
        $this->defaultSortColumn = 'label';
        $this->metrics           = [];
        $this->order             = 1;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->datatable_js_type = 'GoogleCrawlIssuesDataTable';
        $view->config->show_all_views_icons   = false;
        $view->config->show_table_all_columns = false;
        $view->config->disable_row_evolution  = true;
        $view->config->addTranslations([
            'label'         => Piwik::translate('Actions_ColumnPageURL'),
            'category'      => Piwik::translate('SearchEngineKeywordsPerformance_Category'),
            'platform'      => Piwik::translate('SearchEngineKeywordsPerformance_Platform'),
            'lastCrawled'   => Piwik::translate('SearchEngineKeywordsPerformance_LastCrawled'),
            'firstDetected' => Piwik::translate('SearchEngineKeywordsPerformance_FirstDetected'),
            'inLinks'       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlInboundLink'),
            'inSitemaps'    => Piwik::translate('SearchEngineKeywordsPerformance_ContainingSitemaps'),
            'responseCode'  => Piwik::translate('SearchEngineKeywordsPerformance_ResponseCode')
        ]);

        $translations = [
            "authPermissions"   => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlAuthPermission'),
            "flashContent"      => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlFlash'),
            "manyToOneRedirect" => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlManyRedirect'),
            "notFollowed"       => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFollowed'),
            "notFound"          => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFound'),
            "other"             => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlOtherError'),
            "roboted"           => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlRoboted'),
            "serverError"       => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlServerError'),
            "soft404"           => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlSoft404')
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

        $view->config->filters[] = array(
            'ColumnCallbackReplace',
            array(
                'lastCrawled',
                function ($val) {
                    return Date::factory($val)->getLocalized(Date::DATETIME_FORMAT_SHORT);
                }
            )
        );

        $view->config->filters[] = array(
            'ColumnCallbackReplace',
            array(
                'firstDetected',
                function ($val) {
                    return Date::factory($val)->getLocalized(Date::DATETIME_FORMAT_SHORT);
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
        return !empty($setting->googleSearchConsoleUrl) && $setting->googleSearchConsoleUrl->getValue();
    }
}
