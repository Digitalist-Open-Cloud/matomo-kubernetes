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

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable\AllColumns;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldAvgHesitationTime;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldAvgTimeSpent;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldSumHesitationTime;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldSumTimeSpent;
use Piwik\Plugins\FormAnalytics\Metrics;

class GetFieldTimings extends BaseFormFieldReport
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_FieldTimings');
        $this->documentation = Piwik::translate('FormAnalytics_ReportFieldTimingsDescription');
        $this->defaultSortColumn = Metrics::AVG_FIELD_TIME_SPENT;

        $this->order = 120;
        $this->metrics = array(
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS
        );
        $this->processedMetrics = array(
            new FieldAvgTimeSpent(),
            new FieldSumTimeSpent(),
            new FieldAvgHesitationTime(),
            new FieldSumHesitationTime(),
        );
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $view->config->show_table_all_columns = true;
        if ($view->isViewDataTableId(AllColumns::ID)) {
            $columns = array(
                'label',
                Metrics::AVG_FIELD_TIME_SPENT,
                Metrics::AVG_FIELD_HESITATION_TIME,
                Metrics::SUM_FIELD_UNIQUE_INTERACTIONS,
                Metrics::SUM_FIELD_TIME_SPENT,
                Metrics::SUM_FIELD_HESITATION_TIME,
            );
            $view->config->columns_to_display = $columns;
            $view->config->filters[] = function () use ($view, $columns) {
                $view->config->columns_to_display = $columns;
            };
        } else {
            $view->config->columns_to_display = array(
                'label',
                Metrics::AVG_FIELD_TIME_SPENT,
                Metrics::AVG_FIELD_HESITATION_TIME,
                Metrics::SUM_FIELD_UNIQUE_INTERACTIONS
            );
        }
        $this->setMetricsForGraphBasedOnColumnsToDisplay($view);
    }

}
