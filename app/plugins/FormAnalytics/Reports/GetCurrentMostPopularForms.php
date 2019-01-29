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
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\FormAnalytics\Reports;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Filter\Sort;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugin\ViewDataTable;
use Piwik\Config;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\FormAnalytics\Columns\FormName;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateConversion;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateResubmitter;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateStarters;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateSubmitter;
use Piwik\Plugins\FormAnalytics\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetCurrentMostPopularForms extends Base
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_WidgetTitleMostPopularFormsLast30');
        $this->dimension     = new FormName();
        $this->documentation = Piwik::translate('FormAnalytics_ReportGetCurrentMostPopularFormsDescription');
        $this->subcategoryId = 'FormAnalytics_TypeRealTime';

        $this->order = 150;
        $this->metrics = array(
            Metrics::SUM_FORM_VIEWERS,
            Metrics::SUM_FORM_STARTERS,
            Metrics::SUM_FORM_SUBMITTERS,
            Metrics::SUM_FORM_RESUBMITTERS,
            Metrics::SUM_FORM_CONVERSIONS
        );
        $this->processedMetrics = [
            new FormRateStarters(),
            new FormRateSubmitter(),
            new FormRateConversion(),
            new FormRateResubmitter(),
        ];
    }

    /**
     * Here you can configure how your report should be displayed. For instance whether your report supports a search
     * etc. You can also change the default request config. For instance change how many rows are displayed by default.
     *
     * @param ViewDataTable $view
     */
    public function configureView(ViewDataTable $view)
    {
        $view->config->addTranslations(array('label' => $this->dimension->getName()));

        $lastMinutes = Common::getRequestVar('lastMinutes', 30, 'int');
        $filterLimit = Common::getRequestVar('filter_limit', 5, 'int');

        $view->requestConfig->request_parameters_to_modify['lastMinutes'] = $lastMinutes;
        $view->requestConfig->filter_limit = $filterLimit;
        $view->config->custom_parameters['lastMinutes'] = $lastMinutes;
        $view->config->custom_parameters['updateInterval'] = (int) Config::getInstance()->General['live_widget_refresh_after_seconds'] * 1000;
        if ($view->config->custom_parameters['updateInterval'] < 2000) {
            $view->config->custom_parameters['updateInterval'] = 2000; // we want at least 2 seconds interval
        }
        $view->config->title = 'FormAnalytics_WidgetTitleMostPopularFormsLast' . (int) $lastMinutes;

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_row_evolution = true;
        }

        $view->config->columns_to_display = array(
            'label',
            Metrics::SUM_FORM_VIEWERS,
            Metrics::SUM_FORM_STARTERS,
            Metrics::SUM_FORM_SUBMITTERS,
            Metrics::SUM_FORM_RESUBMITTERS,
            Metrics::SUM_FORM_CONVERSIONS
        );

        if ($view->isViewDataTableId(HtmlTable\AllColumns::ID)) {
            $columns = $this->getAllMetrics();
            array_unshift($columns, 'label');
            $view->config->columns_to_display = $columns;
            $view->config->filters[] = function () use ($view, $columns) {
                $view->config->columns_to_display = $columns;
            };
        }

        $view->requestConfig->filter_sort_column = Metrics::SUM_FORM_STARTERS;
        $view->requestConfig->filter_sort_order = 'desc';

        $view->config->filters[] = array(function (DataTable $dataTable) {
            // we have to disable the filter as it otherwise seems to not sort correctly
            $dataTable->disableFilter('Sort');
        }, $parameters = array(), $priority = true);

        if (empty($_GET['module']) || $_GET['module'] !== 'Widgetize') {
            // do not show this link in exported widget as the link would not work
            $view->config->filters[] = array(function (DataTable $dataTable) {
                // we have to disable the filter as it otherwise seems to not sort correctly
                $title = str_replace('"', '&quot;', Piwik::translate('FormAnalytics_ViewReportInfo'));

                foreach ($dataTable->getRowsWithoutSummaryRow() as $row) {
                    $idSiteForm = $row->getColumn('idsiteform');

                    if (!empty($idSiteForm)) {
                        $row->setMetadata('html_label_prefix', '<a title="' . $title .'" href="javascript:void 0;" piwik-form-page-link="' . (int) $idSiteForm . '"><span class="icon-show"></span></a>');
                    }
                }
            }, $parameters = array(), $priority = true);
        }

        $view->config->datatable_js_type = 'LiveFormDataTable';
        $view->config->show_tag_cloud = false;
        $view->config->show_insights = false;
        $view->config->show_bar_chart = false;
        $view->config->show_pie_chart = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_search = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->enable_sort = false;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $config = $factory->createWidget();
        $config->setIsWide();
        $config->setOrder($this->order);
        $widgetsList->addWidgetConfig($config);

        $widgetsToAdd = array(60, 3600);

        foreach ($widgetsToAdd as $index => $timeToAdd) {
            $config = $factory->createWidget();
            $config->setName('FormAnalytics_WidgetTitleMostPopularFormsLast' . $timeToAdd);
            $config->setParameters(array('lastMinutes' => $timeToAdd));
            $config->setOrder($this->order + $index + 1);
            $config->setIsWide();
            if (60 == $timeToAdd && Common::getRequestVar('method', '', 'string') !== 'API.getWidgetMetadata') {
                // prevent from showing it in reporting UI
                $config->setSubcategoryId(null);
            }
            $widgetsList->addWidgetConfig($config);
        }

    }

}
