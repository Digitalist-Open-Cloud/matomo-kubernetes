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

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Config;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateConversion;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateResubmitter;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateStarters;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateSubmitter;
use Piwik\Plugins\FormAnalytics\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\View;
use Piwik\Widget\WidgetsList;

class GetCounters extends Base
{
    protected function init()
    {
        parent::init();

        $title = 'FormAnalytics_WidgetTitleLiveFormOverviewLast30';

        $lastMinutes = $this->getLastMinutes();
        if (in_array($lastMinutes, array(30, 60, 3600))) {
            $title = 'FormAnalytics_WidgetTitleLiveFormOverviewLast' . $lastMinutes;
        }

        $this->name          = Piwik::translate($title);
        $this->dimension     = null;
        $this->documentation = Piwik::translate('FormAnalytics_ReportGetCountersDescription');
        $this->subcategoryId = 'FormAnalytics_TypeRealTime';

        $this->order = 140;
        $this->metrics = array(
            Metrics::SUM_FORM_VIEWERS,
            Metrics::SUM_FORM_STARTERS,
            Metrics::SUM_FORM_TIME_SPENT,
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

    private function getLastMinutes()
    {
        return Common::getRequestVar('lastMinutes', 30, 'int');
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $lastMinutes = $this->getLastMinutes();

        $counters = Request::processRequest('FormAnalytics.getCounters', array(
            'idSite' => $idSite, 'lastMinutes' => $lastMinutes, 'format_metrics' => '1'
        ));

        $view = new View('@FormAnalytics/liveFormOverview');

        if (empty($counters)) {
            $counters = array();
        }

        $view->is_auto_refresh = Common::getRequestVar('is_auto_refresh', 0, 'int');
        $view->lastMinutes = $lastMinutes;
        $view->counters = $counters;
        $view->liveRefreshAfterMs = (int) Config::getInstance()->General['live_widget_refresh_after_seconds'] * 1000;
        $report = $view->render();

        $view = new View('@CoreHome/_singleWidget');
        $view->title = $this->name;
        $view->content = $report;
        return $view->render();
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $config = $factory->createWidget();
        $config->setOrder($this->order);
        $widgetsList->addWidgetConfig($config);

        $widgetsToAdd = array(60, 3600);
        foreach ($widgetsToAdd as $index => $timeToAdd) {
            $config = $factory->createWidget();
            $config->setName('FormAnalytics_WidgetTitleLiveFormOverviewLast' . $timeToAdd);
            $config->setParameters(array('lastMinutes' => $timeToAdd));
            $config->setOrder($this->order + $index + 1);
            if (60 == $timeToAdd && Common::getRequestVar('method', '', 'string') !== 'API.getWidgetMetadata') {
                // prevent from showing it in reporting ui
                $config->setSubcategoryId(null);
            }
            $widgetsList->addWidgetConfig($config);
        }

    }

}
