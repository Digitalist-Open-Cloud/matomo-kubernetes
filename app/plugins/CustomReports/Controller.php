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

namespace Piwik\Plugins\CustomReports;

use Piwik\Columns\MetricsList;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\ArchivedMetric;
use Piwik\Plugins\AbTesting\Columns\Metrics\ProcessedMetric;
use Piwik\Plugins\CustomReports\Input\Validator;
use Piwik\ArchiveProcessor;
use Piwik\DataAccess\ArchiveWriter;
use Piwik\Period;
use Piwik\DataAccess\LogAggregator;
use Piwik\Plugins\CustomReports\ReportType\ReportType;
use Piwik\Plugins\CustomReports\ReportType\Table;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\ViewDataTable\Factory;

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * @var Validator
     */
    private $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;

        parent::__construct();
    }

    /**
     * Manage custom reports in the admin area.
     * @return string
     */
    public function manage()
    {
        $idSite = Common::getRequestVar('idSite');

        if (strtolower($idSite) === 'all') {
            // prevent fatal error... redirect to a specific site as it is not possible to manage for all sites
            Piwik::checkUserHasSomeAdminAccess();
            $this->redirectToIndex('CustomReports', 'manage');
            exit;
        }

        $this->checkSitePermission();
        $this->validator->checkWritePermission($this->idSite);

        return $this->renderTemplate('manage');
    }

    /**
     * Preview a custom reports report. Only supports table visualization so far.
     * @return string
     * @throws \Exception
     */
    public function previewReport()
    {
        $this->checkSitePermission();
        $this->validator->checkWritePermission($this->idSite);

        $report = null;
        Piwik::postEvent('CustomReports.buildPreviewReport', array(&$report));

        $date = Common::getRequestVar('date', null, 'string');
        $period = 'day';

        try {
            // only for UI tests
            if (StaticContainer::get('test.vars.previewDate')) {
                $date = StaticContainer::get('test.vars.previewDate');
            }
        } catch (\Exception $e) {
            // ignore any possible error
        }
        // for now we only support day period as weekly or monthly doesn't really work and would take way too long
        // so it can also not be misused to fetch not yet archived reports

        $site = new Site($this->idSite);

        if (Period::isMultiplePeriod($date, $period)) {
            throw new \Exception('Multi period is not supported');
        } else {
            $period = PeriodFactory::makePeriodFromQueryParams($site->getTimezone(), $period, $date);
        }

        $parameters = new ArchiveProcessor\Parameters($site, $period, new Segment('', array($this->idSite)));
        $archiveWriter = new ArchiveWriter($parameters, $isTemporary = true);
        $logAggregator = new LogAggregator($parameters);

        $processor = new ArchiveProcessor($parameters, $archiveWriter, $logAggregator);
        $archiver = new Archiver($processor);

        $dataArray = $archiver->aggregateReport($report, $this->idSite);
        $dataTable = $dataArray->asDataTable();

        $idSubtable = Common::getRequestVar('idSubtable', 0, 'int');
        if (!empty($idSubtable)) {
            $row = $dataTable->getRowFromIdSubDataTable($idSubtable);
            $dataTable = $row->getSubtable();
        }

        $showFooterMessage = true;

        if ($report['report_type'] === Table::ID) {
            $factory = StaticContainer::get('Piwik\Columns\DimensionsProvider');
            if (empty($idSubtable)) {
                $dimension = $factory->factory(reset($report['dimensions']));
            } else {
                $showFooterMessage = false; // do not show message again for subtables
                $dimension = $factory->factory(end($report['dimensions']));
            }
            if (!empty($dimension)) {
                $dataTable->filter('Piwik\Plugins\CustomReports\DataTable\Filter\ReportTypeTableFilter', [$this->idSite, $dimension, array()]);
            }
        }

        $type = ReportType::factory($report['report_type']);
        $view = Factory::build($type->getDefaultViewDataTable(), 'CustomReports.getCustomReport', 'CustomReports.previewReport', true);
        $view->setDataTable($dataTable);

        // make sure any action will be correctly forwarded
        if (property_exists($view->config, 'disable_row_evolution')) {
            $view->config->disable_row_evolution = true;
        }
        $view->config->custom_parameters['module'] = 'CustomReports';
        $view->config->custom_parameters['action'] = 'previewReport';
        $view->config->subtable_controller_action = 'previewReport';

        if ($showFooterMessage) {
            $view->config->show_footer_message = Piwik::translate('CustomReports_PreviewDate', $period->getLocalizedLongString());
        }

        if ($report['dimensionsTruncated'] && empty($idSubtable)) {
            $view->config->show_footer_message .= ' ' . Piwik::translate('CustomReports_PreviewSupportsDimension', 2);
        }
        $view->config->show_flatten_table = false;
        $view->config->show_pivot_by_subtable = false;
        $view->config->show_insights = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_all_views_icons = false;
        $view->config->show_search = false;
        $view->config->show_title = false;
        $view->config->show_limit_control = false;
        $view->config->show_export_as_image_icon = false;
        $view->config->show_export_as_rss_feed = false;
        $view->config->show_export = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->requestConfig->filter_limit = 10;
        $view->requestConfig->request_parameters_to_modify['report_type'] = $report['report_type'];
        $view->requestConfig->request_parameters_to_modify['metrics'] = $report['metrics'];
        $view->requestConfig->request_parameters_to_modify['totals'] = 0;
        if (!empty($report['dimensions'])) {
            $view->requestConfig->request_parameters_to_modify['dimensions'] = $report['dimensions'];
        }

        return $view->render();
    }

    /**
     * Renders an evolution graph visualization.
     * @return string|void
     */
    public function getEvolutionGraph()
    {
        $this->checkSitePermission();
        $this->validator->checkReportViewPermission($this->idSite);

        if (empty($columns)) {
            $columns = Common::getRequestVar('columns', false);
            if (false !== $columns) {
                $columns = Piwik::getArrayFromApiParameter($columns);
            }
        }

        $report = new GetCustomReport();
        $documentation = $report->getDocumentation();

        $selectableColumns = array_keys($report->getMetrics());

        if (empty($columns) && !empty($selectableColumns) && count($selectableColumns) <= 4) {
            // pre-select all columns when there are only 4 columns
            $columns = $selectableColumns;
        } elseif (empty($columns) && !empty($selectableColumns)) {
            // pre-select only first column when there are more than 5 columns
            $columns = array(reset($selectableColumns));
        }

        $view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns,
            $selectableColumns, $documentation, 'CustomReports.getCustomReport');

        if (empty($view->config->columns_to_display) && $report->getDefaultSortColumn()) {
            $view->config->columns_to_display = array($report->getDefaultSortColumn());
        }

        return $this->renderView($view);
    }

}
