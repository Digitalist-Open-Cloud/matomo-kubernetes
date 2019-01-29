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
use Piwik\NumberFormatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreHome\CoreHome;
use Piwik\Plugins\CoreVisualizations\Visualizations\Graph;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgHesitationTime;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgTimeSpent;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgTimeToConversion;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormAvgTimeToFirstSubmission;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateConversion;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateResubmitter;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateStarters;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FormRateSubmitter;
use Piwik\Plugins\FormAnalytics\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class Get extends Base
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_ReportFormOverviewName');
        $this->documentation = Piwik::translate('FormAnalytics_ReportFormOverviewDescription');
        $this->dimension     = null;

        $this->order = 101;
        $this->metrics = Metrics::getNumericFormMetrics();
        $this->subcategoryId = 'General_Overview';
        $this->processedMetrics = [
            new FormRateStarters(),
            new FormRateSubmitter(),
            new FormRateConversion(),
            new FormRateResubmitter(),
            new FormAvgHesitationTime(),
            new FormAvgTimeSpent(),
            new FormAvgTimeToFirstSubmission(),
            new FormAvgTimeToConversion(),
        ];

        /** @var ProcessedMetric $processedMetric */
        foreach ($this->processedMetrics as $processedMetric) {
            foreach ($processedMetric->getTemporaryMetrics() as $temporaryMetric) {
                $this->removeMetricIfSet($temporaryMetric);
            }
        }
    }

    /**
     * Here you can configure how your report should be displayed. For instance whether your report supports a search
     * etc. You can also change the default request config. For instance change how many rows are displayed by default.
     *
     * @param ViewDataTable $view
     */
    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        if (!$view->isViewDataTableId(Graph::ID)) {
            $view->config->columns_to_display = array_merge(array('label'), $this->metrics);
        }

        $idForm = Common::getRequestVar('idForm', 0, 'int');

        if (!empty($idForm)) {
            $view->requestConfig->request_parameters_to_modify['idForm'] = $idForm;
        }

        if ($view->isViewDataTableId(Sparklines::ID)) {
            /** @var Sparklines $view */
            if (Common::getRequestVar('summary', 0, 'int')) {
                $view->config->setNotLinkableWithAnyEvolutionGraph();
                $view->config->title_attributes = array('piwik-form-page-link' => $idForm);

                $view->config->addSparklineMetric(array(Metrics::RATE_FORM_CONVERSION), $order = 20);
                $view->config->addSparklineMetric(array(Metrics::RATE_FORM_STARTERS), $order = 19);

                $numberFormatter = NumberFormatter::getInstance();
                $view->config->filters[] = function (DataTable $table) use ($numberFormatter) {
                    $firstRow = $table->getFirstRow();
                    if ($firstRow) {
                        $engagementRate = $firstRow->getColumn(Metrics::RATE_FORM_STARTERS);
                        if (false !== $engagementRate) {
                            $firstRow->setColumn(Metrics::RATE_FORM_STARTERS, $numberFormatter->formatPercent($engagementRate, $precision = 1));
                        }

                        $conversionRate = $firstRow->getColumn(Metrics::RATE_FORM_CONVERSION);
                        if (false !== $conversionRate) {
                            $firstRow->setColumn(Metrics::RATE_FORM_STARTERS, $numberFormatter->formatPercent($engagementRate, $precision = 1));
                        }
                    }
                };
            } elseif (Common::getRequestVar('timings', 0, 'int')) {
                /** @var Sparklines $view */
                $view->config->addSparklineMetric(array(Metrics::AVG_FORM_HESITATION_TIME), $order = 15);
                $view->config->addSparklineMetric(array(Metrics::AVG_FORM_TIME_SPENT), $order = 16);
                $view->config->addSparklineMetric(array(Metrics::AVG_FORM_TIME_TO_FIRST_SUBMISSION), $order = 17);
                $view->config->addSparklineMetric(array(Metrics::AVG_FORM_TIME_TO_CONVERSION), $order = 18);
            } else {
                $view->config->addSparklineMetric(array(Metrics::RATE_FORM_STARTERS), $order = 10);
                $view->config->addSparklineMetric(array(Metrics::RATE_FORM_SUBMITTER), $order = 11);
                $view->config->addSparklineMetric(array(Metrics::RATE_FORM_CONVERSION), $order = 12);
                $view->config->addSparklineMetric(array(Metrics::RATE_FORM_RESUBMITTERS), $order = 13);
                $view->config->addSparklineMetric(array(Metrics::SUM_FORM_VIEWS), $order = 14);
                $view->config->addSparklineMetric(array(Metrics::SUM_FORM_VIEWERS), $order = 15);
                $view->config->addSparklineMetric(array(Metrics::SUM_FORM_STARTERS), $order = 16);
                $view->config->addSparklineMetric(array(Metrics::SUM_FORM_SUBMITTERS), $order = 17);
                $view->config->addSparklineMetric(array(Metrics::SUM_FORM_RESUBMITTERS), $order = 19);
                $view->config->addSparklineMetric(array(Metrics::SUM_FORM_CONVERSIONS), $order = 20);

                $numberFormatter = NumberFormatter::getInstance();
                $view->config->filters[] = function (DataTable $table) use ($numberFormatter) {
                    $firstRow = $table->getFirstRow();
                    if ($firstRow) {
                        $metrics = [
                            Metrics::SUM_FORM_VIEWS,
                            Metrics::SUM_FORM_VIEWERS,
                            Metrics::SUM_FORM_STARTERS,
                            Metrics::SUM_FORM_SUBMITTERS,
                            Metrics::SUM_FORM_RESUBMITTERS,
                            Metrics::SUM_FORM_CONVERSIONS
                        ];
                        foreach ($metrics as $metric) {
                            $metricValue = $firstRow->getColumn($metric);
                            if (false !== $metricValue) {
                                $firstRow->setColumn($metric, $numberFormatter->formatNumber($metricValue, $precision = 1));
                            }
                        }
                    }
                };
            }

            $view->config->addTranslations(array(
                Metrics::SUM_FORM_VIEWS => Piwik::translate('FormAnalytics_ColumnNbViews'),
                Metrics::SUM_FORM_VIEWERS => Piwik::translate('FormAnalytics_ColumnNbViewers'),
                Metrics::SUM_FORM_STARTERS => Piwik::translate('FormAnalytics_ColumnNbStarters'),
                Metrics::SUM_FORM_SUBMITTERS => Piwik::translate('FormAnalytics_ColumnNbSubmitters'),
                Metrics::SUM_FORM_RESUBMITTERS => Piwik::translate('FormAnalytics_ColumnNbResubmitters'),
                Metrics::SUM_FORM_CONVERSIONS => Piwik::translate('FormAnalytics_ColumnNbConversions'),
                Metrics::RATE_FORM_CONVERSION => Piwik::translate('FormAnalytics_ColumnNbConversionRate'),
                Metrics::RATE_FORM_STARTERS => Piwik::translate('FormAnalytics_ColumnNbStarterRate'),
                Metrics::RATE_FORM_SUBMITTER => Piwik::translate('FormAnalytics_ColumnNbSubmitterRate'),
                Metrics::RATE_FORM_RESUBMITTERS => Piwik::translate('FormAnalytics_ColumnNbResubmitterRate'),
                Metrics::AVG_FORM_TIME_SPENT => Piwik::translate('FormAnalytics_ColumnNbAvgTimeSpent'),
                Metrics::AVG_FORM_HESITATION_TIME => Piwik::translate('FormAnalytics_ColumnNbAvgHesitationTime'),
                Metrics::AVG_FORM_TIME_TO_FIRST_SUBMISSION => Piwik::translate('FormAnalytics_ColumnNbAvgTimeToFirstSubmit'),
                Metrics::AVG_FORM_TIME_TO_CONVERSION => Piwik::translate('FormAnalytics_ColumnNbAvgTimeToConversion'),
            ));
        }
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $timingsTitle = Piwik::translate('FormAnalytics_FormTimings');

        $config = $factory->createWidget();
        $config->forceViewDataTable(Evolution::ID);
        $config->setAction('getEvolutionGraph');
        $config->setOrder(5);
        $config->setName('General_EvolutionOverPeriod');
        $widgetsList->addWidgetConfig($config);

        $config = $factory->createWidget();
        $config->forceViewDataTable(Sparklines::ID);
        $config->setName('');
        $config->setIsNotWidgetizable();
        $config->setOrder(15);
        $widgetsList->addWidgetConfig($config);

        $config = $factory->createWidget();
        $config->forceViewDataTable(Sparklines::ID);
        $config->setName($timingsTitle);
        $config->setParameters(array('timings' => '1'));
        $config->setOrder(15);
        $config->setIsNotWidgetizable();
        $widgetsList->addWidgetConfig($config);

        $idSite = Common::getRequestVar('idSite', $default = 0, 'int');
        $forms = $this->getCachedFormsForSite($idSite);

        foreach ($forms as $form) {

            if (!empty($form['in_overview'])) {
                $config = $factory->createWidget();
                $config->forceViewDataTable(Sparklines::ID);
                $config->setName($form['name']);
                $config->setOrder(20 + $form['idsiteform']);
                $config->setIsNotWidgetizable();
                $config->setParameters(array('idForm' => $form['idsiteform'], 'summary' => '1'));
                $widgetsList->addWidgetConfig($config);
            }

            $config = $factory->createWidget();
            $config->setName(Piwik::translate('FormAnalytics_FormX', '"' . $form['name'] . '"'));
            $config->setSubcategoryId($form['idsiteform']);
            $config->setAction('formSummary');
            $config->setOrder(1);
            $config->setParameters(array('idForm' => $form['idsiteform']));
            $config->setIsNotWidgetizable();
            $widgetsList->addWidgetConfig($config);

            $config = $factory->createWidget();
            $config->forceViewDataTable(Evolution::ID);
            $config->setSubcategoryId($form['idsiteform']);
            $config->setAction('getEvolutionGraph');
            $config->setName(Piwik::translate('General_EvolutionOverPeriod'));
            $config->setOrder(5);
            $config->setParameters(array('idForm' => $form['idsiteform']));
            $config->setIsNotWidgetizable();
            $widgetsList->addWidgetConfig($config);

            $config = $factory->createWidget();
            $config->forceViewDataTable(Sparklines::ID);
            $config->setName('');
            $config->setSubcategoryId($form['idsiteform']);
            $config->setOrder(10);
            $config->setParameters(array('idForm' => $form['idsiteform']));
            $config->setIsNotWidgetizable();
            $widgetsList->addWidgetConfig($config);

            $config = $factory->createWidget();
            $config->forceViewDataTable(Sparklines::ID);
            $config->setName($timingsTitle);
            $config->setSubcategoryId($form['idsiteform']);
            $config->setOrder(15);
            $config->setParameters(array('idForm' => $form['idsiteform'], 'timings' => '1'));
            $config->setIsNotWidgetizable();
            $widgetsList->addWidgetConfig($config);

            $widget = $factory->createContainerWidget('forms_' . $form['idsiteform']);
            $widget->setLayout(CoreHome::WIDGET_CONTAINER_LAYOUT_BY_DIMENSION);
            $widget->setSubcategoryId($form['idsiteform']);
            $widget->setName($form['name']);
            $widget->setIsNotWidgetizable();
            $widget->setParameters(array('idForm' => $form['idsiteform']));
            $widget->setOrder(25);
            $widgetsList->addWidgetConfig($widget);
        }
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        if (!$this->isEnabled()) {
            return;
        }

        $availableReports[] = $this->buildReportMetadata();

        $this->configureReportMetadataForAllForms($availableReports, $infos);
    }

}
