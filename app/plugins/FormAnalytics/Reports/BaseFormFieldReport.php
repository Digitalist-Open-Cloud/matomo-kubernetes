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
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\FormAnalytics\Columns\FormField;
use Piwik\Plugins\FormAnalytics\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

abstract class BaseFormFieldReport extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension = new FormField();
        $this->processedMetrics = array();
    }

    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        $metrics = $this->getAllMetrics();

        $view->config->columns_to_display = array_merge(array('label'), $metrics);
        $this->setMetricsForGraphBasedOnColumnsToDisplay($view);
        $view->config->show_insights = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_flatten_table = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_exclude_low_population = false;

        // we have to set manually since we do not generate report metadata for it
        $metricDocs = Metrics::getMetricsDocumentationTranslations();
        foreach ($metricDocs as $index => $doc) {
            $metricDocs[$index] = Piwik::translate($doc);
        }

        if (empty($view->config->metrics_documentation)) {
            $view->config->metrics_documentation = array();
        }
        $view->config->metrics_documentation = array_merge($view->config->metrics_documentation, $metricDocs);

        if (empty($view->config->documentation)) {
            // we have to set manually since we do not generate report metadata for it
            $view->config->documentation = $this->documentation;
        }

        $view->requestConfig->filter_sort_column = $this->defaultSortColumn;
        $view->requestConfig->request_parameters_to_modify['idForm'] = Common::getRequestVar('idForm', 0, 'int');
    }

    protected function setMetricsForGraphBasedOnColumnsToDisplay(ViewDataTable $view)
    {
        if (property_exists($view->config, 'selectable_columns')) {
            // we are rendering a graph
            $metrics = $view->config->columns_to_display;

            $index = array_search('label', $metrics);
            if ($index !== false) {
                array_splice($metrics, $index, 1);
            }

            $view->config->selectable_columns = $metrics;
            $view->config->columns_to_display = array($this->defaultSortColumn);
        }
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $this->addDimensionWidgetsForEachForm($widgetsList, $factory);
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        // we do not want to expose this currently
        if ($this->isRequestingRowEvolutionPopover()) {
            parent::configureReportMetadata($availableReports, $infos);
        }
    }


}
