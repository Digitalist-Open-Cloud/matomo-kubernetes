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

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable\AllColumns;
use Piwik\Plugins\UsersFlow\Archiver\DataSources;
use Piwik\Plugins\UsersFlow\Columns\Interactions;
use Piwik\Plugins\UsersFlow\Columns\Metrics\ExitRate;
use Piwik\Plugins\UsersFlow\Columns\Metrics\Proceeded;
use Piwik\Plugins\UsersFlow\Columns\Metrics\ProceededRate;
use Piwik\Plugins\UsersFlow\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetUsersFlowPretty extends Base
{

    protected $recursiveLabelSeparator = ' → ';

    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('UsersFlow_UsersFlow');
        $this->dimension     = new Interactions();
        $this->documentation = Piwik::translate('');
        $this->metrics = array(Metrics::NB_VISITS, Metrics::NB_EXITS);
        $this->processedMetrics = array(new Proceeded(), new ProceededRate(), new ExitRate());
        $this->actionToLoadSubTables = 'getUsersFlowPretty';

        $this->order = 2000;
    }

    public function getMetricsDocumentation()
    {
        $docs = parent::getMetricsDocumentation();
        $docs[Metrics::NB_EXITS] = Piwik::translate('UsersFlow_ColumnExitsDocumentation');
        return $docs;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_flatten_table = false;

        if (Common::getRequestVar('topPaths', 0, 'int') === 1) {
            $_GET['flat'] = '1';
            $view->requestConfig->flat = 1;
            $view->config->show_bar_chart = false;
            $view->config->show_pie_chart = false;
            $view->config->show_tag_cloud = false;
            $view->config->show_table_all_columns = false;
            $view->config->addTranslations(array('label' => Piwik::translate('UsersFlow_Path')));

            $view->config->columns_to_display = array('label', Metrics::NB_VISITS);

        } else {

            $idSubtable = Common::getRequestVar('idSubtable', 0, 'int');
            if (empty($idSubtable)) {
                $view->config->addTranslations(array('label' => Piwik::translate('UsersFlow_ColumnInteractionPosition')));
            } else {
                $view->config->addTranslations(array('label' => Piwik::translate('General_Action')));
            }

            if ($view->isViewDataTableId(AllColumns::ID)) {
                $view->config->columns_to_display = array('label', Metrics::NB_VISITS, Metrics::NB_PROCEEDED, Metrics::RATE_PROCEEDED, Metrics::NB_EXITS, Metrics::RATE_EXIT);
                $view->config->filters[] = function () use ($view) {
                    $view->config->columns_to_display = array('label', Metrics::NB_VISITS, Metrics::NB_PROCEEDED, Metrics::NB_EXITS, Metrics::RATE_PROCEEDED, Metrics::RATE_EXIT);
                };
            }

            $view->config->filters[] = function (DataTable $table) use ($view) {
                if ($table->getRowsCount() && $table->getFirstRow() && !$table->getFirstRow()->hasColumn(Metrics::NB_EXITS)) {
                    // we only have visits metric for 3rd level data table
                    $view->config->columns_to_display = array('label', Metrics::NB_VISITS);
                    $view->config->addTranslations(array('label' => Piwik::translate('UsersFlow_DimensionProceededTo')));
                }
            };

            $view->config->columns_to_display = array('label', Metrics::NB_VISITS, Metrics::RATE_PROCEEDED);
        }

        $dataSource = Common::getRequestVar('dataSource', '', 'string');
        $dataSource = DataSources::getValidDataSource($dataSource);
        $view->requestConfig->request_parameters_to_modify['dataSource'] = $dataSource;

        $view->config->show_exclude_low_population = false;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $name = $this->name . ' - ' . Piwik::translate('UsersFlow_TopPaths') . ' - ' . Piwik::translate('Actions_PageUrls');
        $config = $factory->createWidget()
                           ->setCategoryId('General_Actions')
                           ->setSubcategoryId('UsersFlow_TopPaths')
                            ->setIsWide()
                          ->setName($name)->setParameters(array('topPaths' => '1'))->setOrder(2000);
        $widgetsList->addWidgetConfig($config);

        $name = $this->name . ' - ' . Piwik::translate('General_Overview') . ' - ' . Piwik::translate('Actions_PageUrls');
        $config = $factory->createWidget()
            ->setIsWide()
            ->setCategoryId('General_Actions')
            ->setSubcategoryId('UsersFlow_TopPaths')->setName($name)->setOrder(2001);
        $widgetsList->addWidgetConfig($config);

        $name = $this->name . ' - ' . Piwik::translate('UsersFlow_TopPaths') . ' - ' . Piwik::translate('Actions_WidgetPageTitles');
        $config = $factory->createWidget()
            ->setCategoryId('General_Actions')
            ->setSubcategoryId('UsersFlow_TopPaths')
            ->setIsWide()
            ->setName($name)->setParameters(array('topPaths' => '1', 'dataSource' => DataSources::DATA_SOURCE_PAGE_TITLE))->setOrder(2002);
        $widgetsList->addWidgetConfig($config);

        $name = $this->name . ' - ' . Piwik::translate('General_Overview') . ' - ' . Piwik::translate('Actions_WidgetPageTitles');
        $config = $factory->createWidget()
            ->setIsWide()
            ->setCategoryId('General_Actions')
            ->setSubcategoryId('UsersFlow_TopPaths')
            ->setName($name)
            ->setParameters(array('dataSource' => DataSources::DATA_SOURCE_PAGE_TITLE))->setOrder(2003);
        $widgetsList->addWidgetConfig($config);

    }
}
