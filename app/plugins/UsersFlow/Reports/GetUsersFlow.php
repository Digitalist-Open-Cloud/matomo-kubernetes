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

namespace Piwik\Plugins\UsersFlow\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\UsersFlow\Columns\Interactions;
use Piwik\Plugins\UsersFlow\Columns\Metrics\Proceeded;
use Piwik\Plugins\UsersFlow\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetUsersFlow extends Base
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('UsersFlow_UsersFlow');
        $this->dimension     = new Interactions();
        $this->documentation = Piwik::translate('');
        $this->subcategoryId = 'UsersFlow_UsersFlow';
        $this->metrics = array(Metrics::NB_VISITS, Metrics::NB_EXITS);
        $this->processedMetrics = array(new Proceeded());

        $this->order = 1999;
    }

    public function getMetricsDocumentation()
    {
        $docs = parent::getMetricsDocumentation();
        $docs[Metrics::NB_EXITS] = Piwik::translate('UsersFlow_ColumnExitsDocumentation');
        return $docs;
    }

    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        $view->config->columns_to_display = array_merge(array('label'), $this->metrics);
        $view->config->overridableProperties[] = 'levelOfDetail';
        $view->config->overridableProperties[] = 'numActionsPerStep';
        $view->config->overridableProperties[] = 'userFlowSource';
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $name = $this->name . ' - ' . Piwik::translate('UsersFlow_Visualization');
        $config = $factory->createWidget()->setName($name)->setOrder(2000);
        $widgetsList->addWidgetConfig($config);
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        // not visible in report metadata
    }
}
