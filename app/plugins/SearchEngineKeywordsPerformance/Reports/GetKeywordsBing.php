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
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Columns\Keyword;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Bing as ModelBing;
use Piwik\Site;
use Piwik\Version;

class GetKeywordsBing extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension     = new Keyword();
        $this->name          = Piwik::translate('SearchEngineKeywordsPerformance_BingKeywords');
        $this->documentation = Piwik::translate('SearchEngineKeywordsPerformance_BingKeywordsDocumentation');
        $this->order         = 5;
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $period = Common::getRequestVar('period', false, 'string');
        $idSite = Common::getRequestVar('idSite', false, 'string');

        $model               = new ModelBing();
        $measurableSetting   = new MeasurableSettings($idSite);
        list($apiKey, $url) = explode('##', $measurableSetting->bingSiteUrl->getValue());

        $dateLastData = $model->getLatestDateKeywordDataIsAvailableFor($url);
        $lastDateMessage = '';
        if ($dateLastData && $period != 'range') {
            $reportPeriod = $period != 'day' ? $period : 'week';
            $periodObj = Period\Factory::build($reportPeriod, Date::factory($dateLastData));
            $lastDateMessage =
                Piwik::translate(
                    'SearchEngineKeywordsPerformance_LatestAvailableDate',
                    '<a href="javascript:broadcast.propagateNewPage(\'date='.$dateLastData.'&period='.$reportPeriod.'\')">' .
                    $periodObj->getLocalizedShortString() .
                    '</a>'
                );
        }

        if ($period == 'day') {
            $message =
                '<p style="margin-bottom:2em" class=" alert-info alert">' .
                Piwik::translate('CoreHome_ThereIsNoDataForThisReport') . '<br />' .
                Piwik::translate('SearchEngineKeywordsPerformance_BingKeywordsNotDaily') . '<br />' .
                $lastDateMessage .
                '</p>';
        } else {
            $message =
                '<p style="margin-bottom:2em" class=" alert-info alert">' .
                Piwik::translate('CoreHome_ThereIsNoDataForThisReport') . '<br />' .
                $lastDateMessage .
                '</p>';
        }

        if ($period == 'range') {
            $date         = Common::getRequestVar('date', false, 'string');
            $idSite       = Common::getRequestVar('idSite', false, 'string');
            $periodObject = new Range($period, $date, Site::getTimezoneFor($idSite));
            $subPeriods   = $periodObject->getSubperiods();

            foreach ($subPeriods as $subPeriod) {
                if ($subPeriod->getLabel() == 'day') {
                    $message =
                        '<p style="margin-top:2em;margin-bottom:2em" class=" alert-info alert">' .
                        Piwik::translate('SearchEngineKeywordsPerformance_BingKeywordsNoRangeReports') .
                        '</p>';
                    break;
                }
            }
        }

        if (!empty($message)) {
            if (version_compare(Version::VERSION, '3.0.3-b4', '>=')) {
                $view->config->no_data_message = $message;
            } else {
                $view->config->show_footer_message = $message;
            }
        }

        $this->formatCtrAndPositionColumns($view);
    }

    public function isEnabled()
    {
        return parent::isBingEnabled();
    }
}
