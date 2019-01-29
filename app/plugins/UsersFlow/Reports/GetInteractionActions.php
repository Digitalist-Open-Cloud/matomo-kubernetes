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
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\UsersFlow\API;
use Piwik\Plugins\UsersFlow\Archiver\DataSources;
use Piwik\Plugins\UsersFlow\Columns\Interactions;
use Piwik\Plugins\UsersFlow\Columns\Metrics\Proceeded;
use Piwik\Plugins\UsersFlow\Metrics;

class GetInteractionActions extends Base
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('UsersFlow_UsersFlow');
        $this->dimension     = new Interactions();
        $this->documentation = Piwik::translate('');
        $this->metrics = array(Metrics::NB_VISITS, Metrics::NB_EXITS);
        $this->processedMetrics = array(new Proceeded());

        $this->order = 52;
    }

    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_row_evolution = true;
        }

        $offset = Common::getRequestVar('offsetActionsPerStep', 0, 'int');
        $position = Common::getRequestVar('interactionPosition', 0, 'int');
        $dataSource = Common::getRequestVar('dataSource', '', 'string');

        $dataSource = DataSources::getValidDataSource($dataSource);

        $idSubtable = Common::getRequestVar('idSubtable', 0, 'int');

        $view->requestConfig->request_parameters_to_modify['offsetActionsPerStep'] = $offset;
        $view->requestConfig->request_parameters_to_modify['interactionPosition'] = $position;
        $view->requestConfig->request_parameters_to_modify['dataSource'] = $dataSource;

        if (empty($idSubtable)) {
            $view->config->show_footer_message = Piwik::translate('UsersFlow_ActionsReportFooterMessage');
            $view->config->columns_to_display = array('label', Metrics::NB_VISITS, Metrics::NB_PROCEEDED, Metrics::NB_EXITS);
        } else {
            $view->config->columns_to_display = array('label', Metrics::NB_VISITS);
            $view->config->addTranslations(array('label' => Piwik::translate('UsersFlow_DimensionProceededTo')));
            $view->requestConfig->filter_limit = '-1';
            $view->config->show_limit_control = false;
            $view->config->show_pagination_control = false;
        }

        $view->config->title = $this->name;

        if ($position) {
            $view->config->title .= ' - ' . $this->dimension->getName() . ' ' . $position;
        }

        if ($rowLabel = Common::getRequestVar('rowLabel', '', 'string')) {
            $view->config->title .= ' - ' . $rowLabel;
        }

        $view->config->show_flatten_table = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_goals = false;
        $view->config->show_ecommerce = false;
        $view->config->show_all_views_icons = false;
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        // not visible in report metadata
    }
}
