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

namespace Piwik\Plugins\Funnels\Reports;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\Funnels\Columns\Metrics\ProceededRate;
use Piwik\Plugins\Funnels\Columns\Step;
use Piwik\Plugins\Funnels\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetFunnelFlow extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('Funnels_Flow');
        $this->dimension = new Step();
        $this->documentation = '';
        $this->order = 100;
        $this->metrics = array(Metrics::NUM_STEP_VISITS, Metrics::NUM_STEP_ENTRIES,
                               Metrics::NUM_STEP_EXITS, Metrics::NUM_STEP_PROCEEDED);
        $this->processedMetrics = array(new ProceededRate());
    }

    private function getValidator()
    {
        return StaticContainer::get('Piwik\Plugins\Funnels\Input\Validator');
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        $validator = $this->getValidator();

        if (!$validator->canViewReport($idSite)) {
            return;
        }

        $model = StaticContainer::get('Piwik\Plugins\Funnels\Model\FunnelsModel');
        $funnels = $model->getAllActivatedFunnelsForSite($idSite);

        foreach ($funnels as $funnel) {
            $config = $factory->createWidget();
            $config->setName(Piwik::translate('Funnels_GoalFunnelReport'));
            $config->forceViewDataTable(Evolution::ID);
            $config->setSubcategoryId($funnel['idfunnel']);
            $config->setAction('goalFunnelReport');
            $config->setOrder(10);
            $config->setParameters(array('idGoal' => $funnel['idgoal']));
            $config->setIsNotWidgetizable();
            $widgetsList->addWidgetConfig($config);
        }
    }

    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        $view->config->addTranslation(Metrics::NUM_STEP_VISITS, Piwik::translate('General_ColumnNbVisits'));
        $view->config->addTranslation(Metrics::NUM_STEP_VISITS_ACTUAL, Piwik::translate('General_ColumnNbVisits'));

        $view->requestConfig->filter_limit = 5;

        $view->config->columns_to_display = array_merge(array('label'), $this->metrics);
        $view->requestConfig->request_parameters_to_modify['idFunnel'] = Common::getRequestVar('idFunnel', null, 'int');
        $view->config->show_goals = false;

        if ($view->isViewDataTableId(Evolution::ID)) {
            $view->config->add_total_row = false;
        }
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        if (!$this->isEnabled()) {
            return;
        }

        $idSite = $this->getIdSiteFromInfos($infos);
        $model = StaticContainer::get('Piwik\Plugins\Funnels\Model\FunnelsModel');
        $funnels = $model->getAllActivatedFunnelsForSite($idSite);

        $order = 111; // goals start at 50, we want to show them after goals
        foreach ($funnels as $funnel) {
            $order = $order + 2;

            $funnel['name'] = Common::sanitizeInputValue($funnel['name']);

            $this->name       = Piwik::translate('Funnels_FunnelXFlow', $funnel['name']);
            $this->parameters = array('idGoal' => $funnel['idgoal']);
            $this->order      = 52.2 + $funnel['idgoal'] * 3;

            $availableReports[] = $this->buildReportMetadata();
        }

        // reset name etc
        $this->init();
    }

    public function getDefaultTypeViewDataTable()
    {
        return Evolution::ID;
    }

}
