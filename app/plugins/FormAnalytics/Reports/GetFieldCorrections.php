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
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldRateAmendment;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldRateCursors;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldRateDeletes;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldRateRefocus;
use Piwik\Plugins\FormAnalytics\Metrics;

class GetFieldCorrections extends BaseFormFieldReport
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_ReportMostCorrectedFieldsName');
        $this->documentation = Piwik::translate('FormAnalytics_ReportMostCorrectedFieldsDescription');

        $this->order = 135;
        $this->defaultSortColumn = Metrics::SUM_FIELD_UNIQUE_AMENDMENTS;

        $this->metrics = array(
            Metrics::SUM_FIELD_UNIQUE_CHANGES,
            Metrics::SUM_FIELD_UNIQUE_AMENDMENTS,
            Metrics::SUM_FIELD_AMENDMENTS,
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS,
            Metrics::SUM_FIELD_UNIQUE_REFOCUS,
            Metrics::SUM_FIELD_REFOCUSES,
            Metrics::SUM_FIELD_UNIQUE_DELETES,
            Metrics::SUM_FIELD_DELETES,
            Metrics::SUM_FIELD_UNIQUE_CURSOR,
            Metrics::SUM_FIELD_CURSOR,
        );

        $this->processedMetrics = array(
            new FieldRateAmendment(),
            new FieldRateRefocus(),
            new FieldRateDeletes(),
            new FieldRateCursors(),
        );
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $view->config->show_tag_cloud = false;

        if ($view->isViewDataTableId(AllColumns::ID)) {
            $columns = array(
                'label',
                Metrics::SUM_FIELD_UNIQUE_CHANGES,
                Metrics::SUM_FIELD_UNIQUE_AMENDMENTS,
                Metrics::RATE_FIELD_AMENDMENTS,
                Metrics::SUM_FIELD_AMENDMENTS,
                Metrics::SUM_FIELD_UNIQUE_INTERACTIONS,
                Metrics::SUM_FIELD_UNIQUE_REFOCUS,
                Metrics::RATE_FIELD_REFOCUS,
                Metrics::SUM_FIELD_REFOCUSES,
                Metrics::SUM_FIELD_UNIQUE_DELETES,
                Metrics::SUM_FIELD_DELETES,
                Metrics::RATE_FIELD_DELETES,
                Metrics::SUM_FIELD_UNIQUE_CURSOR,
                Metrics::SUM_FIELD_CURSOR,
                Metrics::RATE_FIELD_CURSORS,
            );
            $view->config->columns_to_display = $columns;
            $view->config->filters[] = function () use ($view, $columns) {
                $view->config->columns_to_display = $columns;
            };
        } else {
            $view->config->columns_to_display = array(
                'label',
                Metrics::SUM_FIELD_UNIQUE_AMENDMENTS,
                Metrics::SUM_FIELD_UNIQUE_REFOCUS,
                Metrics::SUM_FIELD_UNIQUE_DELETES,
                Metrics::SUM_FIELD_UNIQUE_CURSOR,
                Metrics::RATE_FIELD_AMENDMENTS,
                Metrics::RATE_FIELD_REFOCUS,
            );
        }

        $this->setMetricsForGraphBasedOnColumnsToDisplay($view);

        $view->config->show_table_all_columns = true;
    }


}
