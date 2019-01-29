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
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\Funnels\Columns\Entry;
use Piwik\Plugins\Funnels\Metrics;

class GetFunnelEntries extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('Funnels_Entries');
        $this->dimension = new Entry();
        $this->documentation = '';
        $this->order = 100;
        $this->metrics = array(Metrics::NUM_HITS);
        $this->defaultSortColumn = Metrics::NUM_HITS;
    }

    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        if (Common::getRequestVar('idSubtable', 0, 'int')) {
            $view->config->addTranslation('label', Piwik::translate('Referrers_Referrer'));
            $view->config->show_search = false;
        }

        $view->config->datatable_js_type = 'FunnelDataTable';
        $view->config->datatable_css_class = 'FunnelDataTable';

        $view->config->addTranslation(Metrics::NUM_HITS, Piwik::translate('Funnels_Hits'));

        $view->requestConfig->filter_limit = 4;

        $view->config->show_flatten_table = false;
        $view->config->columns_to_display = array_merge(array('label'), $this->metrics);
        $view->config->show_exclude_low_population = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_goals = false;
        $view->config->show_ecommerce = false;
        $view->config->show_all_views_icons = false;

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_row_evolution = true;
        }

        $view->requestConfig->request_parameters_to_modify['idFunnel'] = Common::getRequestVar('idFunnel', null, 'int');
        $view->requestConfig->request_parameters_to_modify['step'] = Common::getRequestVar('step', 0, 'int');
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        // we do not add it to the report metadata
    }

}
