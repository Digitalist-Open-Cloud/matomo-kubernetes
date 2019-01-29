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
use Piwik\DataTable;
use Piwik\NumberFormatter;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Metrics;
use Piwik\Date;
use Piwik\Period;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Google AS ModelGoogle;
use Piwik\Plugins\SearchEngineKeywordsPerformance\SystemSettings;
use Piwik\Version;

abstract class Base extends \Piwik\Plugin\Report
{
    protected $idSite = false;

    protected function init()
    {
        $this->categoryId        = 'Referrers_Referrers';
        $this->subcategoryId     = 'SearchEngineKeywordsPerformance_KeywordStatistics';
        $this->defaultSortColumn = Metrics::NB_CLICKS;
        $this->metrics           = Metrics::getKeywordMetrics();
        $this->processedMetrics  = [];
    }

    public function getMetricsDocumentation()
    {
        return Metrics::getMetricsDocumentation();
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        $this->idSite = $infos['idSite'];

        parent::configureReportMetadata($availableReports, $infos);
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->addTranslations(array(
            'label' => $this->dimension->getName(),
        ));

        $view->config->show_limit_control     = true;
        $view->config->show_all_views_icons   = false;
        $view->config->show_table_all_columns = false;
        $view->config->columns_to_display     = array(
            'label',
            Metrics::NB_CLICKS,
            Metrics::NB_IMPRESSIONS,
            Metrics::CTR,
            Metrics::POSITION
        );

        $view->requestConfig->filter_sort_column = Metrics::NB_CLICKS;
        $view->requestConfig->filter_sort_order  = 'desc';
        $view->requestConfig->filter_limit       = 10;

        $this->configureSegmentNotSupported($view);
    }

    protected function configureSegmentNotSupported(ViewDataTable $view)
    {
        // show 'not supported' message if segment is chosen
        if (Common::getRequestVar('segment', '')) {
            $view->config->show_footer_message .=
                '<p style="margin-top:2em;margin-bottom:2em" class=" alert-info alert">' .
                Piwik::translate('SearchEngineKeywordsPerformance_NoSegmentation') .
                '</p>';
        }
    }

    public function isGoogleEnabledForType($type)
    {
        $idSite = Common::getRequestVar('idSite', $this->idSite, 'int');

        if (empty($idSite)) {
            return false;
        }

        $setting = new MeasurableSettings($idSite);
        $searchConsoleSetting = $setting->googleSearchConsoleUrl;
        $typeSetting = $setting->getSetting('google' . $type . 'keywords');
        return ($searchConsoleSetting && $searchConsoleSetting->getValue() &&
            $typeSetting && $typeSetting->getValue() &&
            (strpos($searchConsoleSetting->getValue(), 'android-app') === false ||
                $type == 'web'));
    }

    public function isBingEnabled()
    {
        $idSite = Common::getRequestVar('idSite', $this->idSite, 'int');

        if (empty($idSite)) {
            return false;
        }

        $setting = new MeasurableSettings($idSite);
        return !empty($setting->bingSiteUrl) && $setting->bingSiteUrl->getValue();
    }

    public function getMetricNamesToProcessReportTotals()
    {
        return Metrics::getMetricIdsToProcessReportTotal();
    }

    public function configureViewNoDataMessageGoogle($view, $type)
    {
        $period = Common::getRequestVar('period', false, 'string');
        $date   = Common::getRequestVar('date', false, 'string');
        $idSite = Common::getRequestVar('idSite', false, 'string');

        $noDataMessageSupported = version_compare(Version::VERSION, '3.0.4-b3', '>=');

        $measurableSetting   = new MeasurableSettings($idSite);
        list($account, $url) = explode('##', $measurableSetting->googleSearchConsoleUrl->getValue());
        $model               = new ModelGoogle();
        $message             = '';

        $periodObj       = Period\Factory::build($period, $date);
        $lastDate        = $model->getLatestDateKeywordDataIsAvailableFor($url);
        $lastDateForType = $model->getLatestDateKeywordDataIsAvailableFor($url, $type);

        if ($lastDate && !Date::factory($lastDate)->isEarlier($periodObj->getDateStart())) {
            return;
        }

        $lastDateMessage = '';
        if ($lastDateForType && $noDataMessageSupported && $period != 'range') {
            $periodObjType   = Period\Factory::build($period, Date::factory($lastDateForType));
            $lastDateMessage =
                Piwik::translate(
                    'SearchEngineKeywordsPerformance_LatestAvailableDate',
                    '<a href="javascript:broadcast.propagateNewPage(\'date=' . $lastDateForType . '\')">' .
                    $periodObjType->getLocalizedShortString() .
                    '</a>'
                );
        }

        if ($periodObj->getDateEnd()->isLater(Date::now()->subDay(5))) {

            $message .=
                '<p style="margin-bottom:2em" class=" alert-info alert">' .
                Piwik::translate('CoreHome_ThereIsNoDataForThisReport') . '<br />' .
                Piwik::translate('SearchEngineKeywordsPerformance_GoogleDataProvidedWithDelay') . '<br />' .
                $lastDateMessage .
                '</p>';

            if ($noDataMessageSupported) {
                $view->config->no_data_message = $message;
            } else {
                $view->config->show_footer_message = $message;
            }
        }

        if (empty($message) && $lastDateMessage) {
            $view->config->show_footer_message .=
                '<p style="margin-bottom:2em" class=" alert-info alert">' .
                $lastDateMessage .
                '</p>';
        }
    }

    protected function formatColumnsAsNumbers($view, $columns)
    {
        $numberFormatter         = NumberFormatter::getInstance();
        $view->config->filters[] = function (DataTable $table) use ($columns, $numberFormatter) {
            $firstRow = $table->getFirstRow();

            if (empty($firstRow)) {
                return;
            }

            foreach ($columns as $metric) {
                $value = $firstRow->getColumn($metric);
                if (false !== $value) {
                    $firstRow->setColumn($metric, $numberFormatter->formatNumber($value));
                }
            }
        };
    }

    /**
     * @param ViewDataTable $view
     */
    protected function formatCtrAndPositionColumns($view)
    {
        $settings = new SystemSettings();
        $numberFormatter = NumberFormatter::getInstance();

        $view->config->filters[] = array(
            'ColumnCallbackReplace',
            array(
                Metrics::CTR,
                function ($value) use ($numberFormatter) {
                    return $numberFormatter->formatPercent($value * 100, 0, 0);
                }
            )
        );

        $precision = $settings->roundKeywordPosition->getValue() ? 0 : 1;

        $view->config->filters[] = array(
            'ColumnCallbackReplace',
            array(
                Metrics::POSITION,
                function ($value) use ($precision, $numberFormatter) {
                    if ($precision) {
                        return $numberFormatter->formatNumber($value, $precision, $precision);
                    }
                    return round($value, $precision);
                }
            )
        );
    }
}
