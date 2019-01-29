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
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\Actions\Columns\PageUrl;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgTimeSpent;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateSubmitter;
use Piwik\Plugins\FormAnalytics\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgHesitationTime;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgTimeToFirstSubmission;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateConversion;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateStarters;

class GetPageUrls extends Base
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_PageURLs');
        $this->dimension     = new PageUrl();
        $this->documentation = Piwik::translate('FormAnalytics_ReportPageUrlsDescription');

        $this->metrics = Metrics::getNumericFormMetrics();

        $this->processedMetrics = [
            new FormRateStarters(),
            new FormRateConversion(),
            new FormRateSubmitter(),
            new FormAvgHesitationTime(),
            new FormAvgTimeToFirstSubmission(),
            new FormAvgTimeSpent()
        ];

        /** @var ProcessedMetric $processedMetric */
        foreach ($this->processedMetrics as $processedMetric) {
            foreach ($processedMetric->getTemporaryMetrics() as $temporaryMetric) {
                $this->removeMetricIfSet($temporaryMetric);
            }
        }

        $this->removeMetricIfSet(Metrics::SUM_FORM_TIME_TO_CONVERSION);

        $this->order = 102;
    }

    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        $metricDocs = Metrics::getMetricsDocumentationTranslations();
        foreach ($metricDocs as $index => $doc) {
            $metricDocs[$index] = Piwik::translate($doc);
        }

        if (empty($view->config->metrics_documentation)) {
            $view->config->metrics_documentation = array();
        }
        $view->config->metrics_documentation = array_merge($view->config->metrics_documentation, $metricDocs);

        if ($view->isViewDataTableId(HtmlTable\AllColumns::ID)) {
            $metrics = $this->getAllMetrics();
            $columns = array_merge(array('label'), $metrics);
            $view->config->columns_to_display = $columns;
            $view->config->filters[] = function () use ($view, $columns) {
                $view->config->columns_to_display = $columns;
            };
        } else {
            $view->config->columns_to_display = array(
                'label',
                Metrics::SUM_FORM_VIEWERS,
                Metrics::SUM_FORM_STARTERS,
                Metrics::RATE_FORM_STARTERS,
                Metrics::SUM_FORM_SUBMITTERS,
                Metrics::SUM_FORM_CONVERSIONS,
                Metrics::RATE_FORM_CONVERSION,
                Metrics::AVG_FORM_HESITATION_TIME,
                Metrics::AVG_FORM_TIME_SPENT
            );
        }

        $view->requestConfig->filter_limit = 5;
        $view->config->show_exclude_low_population = false;
        $view->config->show_flatten_table = false;
        $view->config->show_insights = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;

        $idForm = Common::getRequestVar('idForm', 0, 'int');

        if (!empty($idForm)) {
            $view->requestConfig->request_parameters_to_modify['idForm'] = $idForm;
        }
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite = Common::getRequestVar('idSite', $default = 0, 'int');
        $forms = $this->getCachedFormsForSite($idSite);

        foreach ($forms as $form) {
            $widget = $factory->createWidget();
            $widget->setSubcategoryId($form['idsiteform']);
            $widget->setIsNotWidgetizable();
            $widget->setOrder(20);
            $widget->setIsWide();
            $widget->setParameters(array('idForm' => $form['idsiteform']));
            $widgetsList->addWidgetConfig($widget);
        }
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        if ($this->isRequestingRowEvolutionPopover()) {
            parent::configureReportMetadata($availableReports, $infos);
        } else {
            $this->configureReportMetadataForAllForms($availableReports, $infos);
        }
    }
}
