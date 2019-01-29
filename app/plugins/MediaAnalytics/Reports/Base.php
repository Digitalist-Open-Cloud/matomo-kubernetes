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
namespace Piwik\Plugins\MediaAnalytics\Reports;

use Piwik\Common;

use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Bar;
use Piwik\Plugins\MediaAnalytics\Archiver;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\AvgMediaLength;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\AvgMediaCompletion;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\AvgTimeToPlay;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\AvgTimeWatched;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\FinishRate;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\FullscreenRate;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\PlayRate;
use Piwik\Plugins\MediaAnalytics\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

abstract class Base extends Report
{
    protected function init()
    {
        $this->categoryId = 'MediaAnalytics_Media';
        $this->defaultSortColumn = Metrics::METRIC_NB_PLAYS;
    }
    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        if ($this->categoryId && $this->subcategoryId) {
            $widgetsList->addWidgetConfig($factory->createWidget()->setIsWide());
        }
    }

    protected function setDefaultMetrics()
    {
        $hasRequestedSecondaryDimension = Common::getRequestVar('isDetailPage', 0, 'int');
        if (!$hasRequestedSecondaryDimension) {
            $hasRequestedSecondaryDimension = Common::getRequestVar('secondaryDimension', '', 'string');
        }

        if ($hasRequestedSecondaryDimension) {
            $this->metrics = array(Metrics::METRIC_NB_PLAYS, Metrics::METRIC_NB_FINISHES);
            $this->processedMetrics = array(
                new AvgTimeWatched(),
                new FinishRate()
            );
        } else {
            $this->metrics = array(
                Metrics::METRIC_NB_PLAYS,
                Metrics::METRIC_NB_IMPRESSIONS,
                Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS,
                Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS,
                Metrics::METRIC_NB_FINISHES,
            );

            $this->processedMetrics = array(
                new PlayRate(),
                new FinishRate(),
            );
            if ($this->isVideoReport()) {
                $this->processedMetrics[] = new FullscreenRate();
            }
            $this->processedMetrics[] = new AvgTimeWatched();
            $this->processedMetrics[] = new AvgMediaCompletion();
            $this->processedMetrics[] = new AvgTimeToPlay();
            $this->processedMetrics[] = new AvgMediaLength();
        }
    }

    public function setExpandableTable(ViewDataTable $view)
    {
        $view->config->show_flatten_table = false;

        $isDetailPage = Common::getRequestVar('isDetailPage', 0, 'int');

        if (!$isDetailPage && $view->isViewDataTableId(HtmlTable::ID)) {
            if (Common::getRequestVar('idSubtable', 0, 'int')) {
                // we always show all subtables under a certain path for request URLs
                $view->requestConfig->filter_limit = 1000;
            }

            $view->config->show_embedded_subtable = true;
            $view->config->datatable_js_type = 'MediaDataTable';
            $view->config->datatable_css_class = 'dataTableActions';
            $view->config->filters[] = array(function () use ($view) {
                $view->config->datatable_js_type = 'MediaDataTable';
                $view->config->datatable_css_class = 'dataTableActions';
            }, $params = array(), $priority = true);
        } elseif ($isDetailPage && $view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->show_embedded_subtable = false;
            $view->config->disable_row_evolution = true;
        } elseif ($isDetailPage && $view->isViewDataTableId(Bar::ID)) {
            $view->config->columns_to_display = array(Metrics::METRIC_NB_PLAYS);
            $view->config->filters[] = array(function () use ($view) {
                $view->config->datatable_js_type = 'MediaBarGraph';
                $view->config->columns_to_display = array(Metrics::METRIC_NB_PLAYS);
            }, $params = array(), $priority = true);
        }
    }

    public function configureTableReport(ViewDataTable $view)
    {
        $view->config->show_table_all_columns = true;
        $view->config->show_all_views_icons = false;
        $view->config->show_insights = false;
        $view->config->show_exclude_low_population = false;

        $isHtmlTable = $view->isViewDataTableId(HtmlTable::ID);
        $isDetailPage = Common::getRequestVar('isDetailPage', 0, 'int');


        if (!$isDetailPage && $isHtmlTable) {
            $view->config->columns_to_display = array(
                'label',
                Metrics::METRIC_NB_PLAYS,
                Metrics::METRIC_NB_IMPRESSIONS,
                Metrics::METRIC_PLAY_RATE,
                Metrics::METRIC_NB_FINISHES,
                Metrics::METRIC_AVG_TIME_WATCHED,
                Metrics::METRIC_AVG_MEDIA_LENGTH, Metrics::METRIC_AVG_COMPLETION
            );

            if ($view->isViewDataTableId(HtmlTable\AllColumns::ID)) {
                $self = $this;

                $view->config->filters[] = array(function () use ($view, $self) {

                    $view->config->columns_to_display = array(
                        'label',
                        Metrics::METRIC_NB_PLAYS,
                        Metrics::METRIC_NB_IMPRESSIONS,
                        Metrics::METRIC_PLAY_RATE,
                    );

                    $currentPeriod = Common::getRequestVar('period', '', 'string');
                    $displayUniqueVisitors = Archiver::isUniqueVisitorsEnabled($currentPeriod);

                    if ($displayUniqueVisitors) {
                        $view->config->columns_to_display[] = Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS;
                        $view->config->columns_to_display[] = Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS;
                    }

                    $view->config->columns_to_display[] = Metrics::METRIC_NB_FINISHES;
                    $view->config->columns_to_display[] = Metrics::METRIC_FINISH_RATE;
                    $view->config->columns_to_display[] = Metrics::METRIC_AVG_TIME_TO_PLAY;
                    $view->config->columns_to_display[] = Metrics::METRIC_AVG_TIME_WATCHED;
                    $view->config->columns_to_display[] = Metrics::METRIC_AVG_MEDIA_LENGTH;
                    $view->config->columns_to_display[] = Metrics::METRIC_AVG_COMPLETION;

                    if ($self->isVideoReport()) {
                        $view->config->columns_to_display[] = Metrics::METRIC_FULLSCREEN_RATE;
                    }

                }, $params = array(), $priority = false);
            }
        } elseif ($isDetailPage && $isHtmlTable) {
            $view->config->disable_row_evolution = true;
            $view->config->columns_to_display = array_merge(array('label'), $this->metrics);
            foreach ($this->processedMetrics as $metric) {
                if ($metric instanceof ProcessedMetric) {
                    $view->config->columns_to_display[] = $metric->getName();
                } elseif (is_string($metric)) {
                    $view->config->columns_to_display[] = $metric;
                }
            }
        }

        $view->requestConfig->filter_sort_column = Metrics::METRIC_NB_PLAYS;
    }

    public function isVideoReport()
    {
        return strpos(strtolower($this->action), 'video') !== false;
    }

    public function isAudioReport()
    {
        return strpos(strtolower($this->action), 'audio') !== false;
    }

}
