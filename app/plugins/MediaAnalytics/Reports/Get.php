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
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MediaAnalytics\Archiver;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\FinishRate;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\ImpressionRate;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\PlayRate;
use Piwik\Plugins\MediaAnalytics\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\SettingsPiwik;
use Piwik\Widget\WidgetsList;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;

class Get extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('MediaAnalytics_Summary');
        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationMediaSummary');
        $this->metrics = array(
            Metrics::METRIC_NB_PLAYS,
            Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS,
            Metrics::METRIC_NB_IMPRESSIONS,
            Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS,
            Metrics::METRIC_IMPRESSION_RATE,
            Metrics::METRIC_NB_FINISHES,
            Metrics::METRIC_TOTAL_TIME_WATCHED,
            Metrics::METRIC_TOTAL_AUDIO_PLAYS,
            Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS,
            Metrics::METRIC_TOTAL_VIDEO_PLAYS,
            Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS,
        );
        $this->order = 0;
        $this->subcategoryId = 'General_Overview';

        $this->processedMetrics = array(
            new PlayRate(),
            new FinishRate(),
            new ImpressionRate()
        );
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setName('General_EvolutionOverPeriod')
                ->forceViewDataTable(Evolution::ID)
                ->setAction('getEvolutionGraph')
                ->setMiddlewareParameters(array('module' => 'MediaAnalytics', 'action' => 'hasRecords'))
                ->setParameters(array('columns' => array(Metrics::METRIC_NB_PLAYS)))
                ->setOrder(5)
        );

        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setName('General_Report')
                ->setMiddlewareParameters(array('module' => 'MediaAnalytics', 'action' => 'hasRecords'))
                ->forceViewDataTable(Sparklines::ID)
                ->setOrder(10)
        );
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view->isViewDataTableId(Sparklines::ID)) {
            /** @var Sparklines $view */
            $view->requestConfig->apiMethodToRequestDataTable = 'MediaAnalytics.get';
            $this->addSparklineColumns($view);
            $view->config->addTranslations($this->getSparklineTranslations());

            $view->config->filters[] = function (DataTable $table) use ($view) {
                $firstRow = $table->getFirstRow();
                $nbUsers = $firstRow->getColumn(Metrics::METRIC_TOTAL_TIME_WATCHED);
                if (is_numeric($nbUsers)) {
                    $formatter = StaticContainer::get('Piwik\Metrics\Formatter');
                    $nbUsers = $formatter->getPrettyTimeFromSeconds($nbUsers, true);
                    $firstRow->setColumn(Metrics::METRIC_TOTAL_TIME_WATCHED, $nbUsers);
                }
            };
        }
    }

    private function addSparklineColumns(Sparklines $view)
    {
        $currentPeriod = Common::getRequestVar('period');
        $displayUniqueVisitors = Archiver::isUniqueVisitorsEnabled($currentPeriod);

        if ($displayUniqueVisitors) {
            $view->config->addSparklineMetric(array(Metrics::METRIC_NB_PLAYS, Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS), 1);
        } else {
            $view->config->addSparklineMetric(array(Metrics::METRIC_NB_PLAYS), 1);
        }

        $view->config->addSparklineMetric(array(Metrics::METRIC_TOTAL_VIDEO_PLAYS, Metrics::METRIC_TOTAL_AUDIO_PLAYS), 3);

        if ($displayUniqueVisitors) {
            $view->config->addSparklineMetric(array(Metrics::METRIC_NB_IMPRESSIONS, Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS), 3);
        } else {
            $view->config->addSparklineMetric(array(Metrics::METRIC_NB_IMPRESSIONS), 2);
        }

        $view->config->addSparklineMetric(array(Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS, Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS), 4);

        $view->config->addSparklineMetric(array(Metrics::METRIC_TOTAL_TIME_WATCHED), 5);
        $view->config->addSparklineMetric(array(Metrics::METRIC_NB_FINISHES), 6);
        $view->config->addSparklineMetric(array(Metrics::METRIC_PLAY_RATE), 7);
        $view->config->addSparklineMetric(array(Metrics::METRIC_FINISH_RATE), 8);

        $displayImpressionRate = SettingsPiwik::isUniqueVisitorsEnabled($currentPeriod) && Archiver::isUniqueVisitorsEnabled($currentPeriod);
        if ($displayImpressionRate) {
            $view->config->addSparklineMetric(array(Metrics::METRIC_IMPRESSION_RATE), 9);
        };
    }

    private function getSparklineTranslations()
    {
        $translations = array(
            Metrics::METRIC_NB_PLAYS => 'NbTotalPlays',
            Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS => 'NbTotalUniquePlays',
            Metrics::METRIC_NB_IMPRESSIONS => 'NbTotalImpressions',
            Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS => 'NbTotalUniqueImpressions',
            Metrics::METRIC_TOTAL_VIDEO_PLAYS => 'NbVideoPlays',
            Metrics::METRIC_TOTAL_AUDIO_PLAYS => 'NbAudioPlays',
            Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS => 'NbVideoImpressions',
            Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS => 'NbAudioImpressions',
            Metrics::METRIC_TOTAL_TIME_WATCHED => 'NbTotalTimeSpent',
            Metrics::METRIC_NB_FINISHES => 'NbFinishes',
            Metrics::METRIC_PLAY_RATE => 'NbPlayRate',
            Metrics::METRIC_FINISH_RATE => 'NbFinishRate',
            Metrics::METRIC_IMPRESSION_RATE => 'NbImpressionRate',
        );

        foreach ($translations as $metric => $key) {
            $translations[$metric] = Piwik::translate('MediaAnalytics_' . $key);
        }

        return $translations;
    }

    public function getRelatedReports()
    {
        return array();
    }

}
