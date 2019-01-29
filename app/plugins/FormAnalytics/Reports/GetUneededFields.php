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
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldRateLeftBlankConversion;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldRateLeftBlankSubmit;
use Piwik\Plugins\FormAnalytics\Metrics;

class GetUneededFields extends BaseFormFieldReport
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_UnneededFields');
        $this->documentation = Piwik::translate('FormAnalytics_ReportUnneededFieldsDescription');

        $this->order = 140;
        $this->defaultSortColumn = Metrics::RATE_FIELD_LEFTBLANK_SUBMITTED;

        $this->metrics = array(
            Metrics::SUM_FIELD_SUBMITTED,
            Metrics::SUM_FIELD_LEFTBLANK_SUBMITTED,
            Metrics::SUM_FIELD_CONVERTED,
            Metrics::SUM_FIELD_LEFTBLANK_CONVERTED,
        );

        $this->processedMetrics = array(
            new FieldRateLeftBlankSubmit(),
            new FieldRateLeftBlankConversion()
        );
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $view->config->show_tag_cloud = false;

        $metrics = array(
            Metrics::RATE_FIELD_LEFTBLANK_SUBMITTED,
            Metrics::SUM_FIELD_LEFTBLANK_SUBMITTED,
            Metrics::SUM_FIELD_SUBMITTED,
            Metrics::RATE_FIELD_LEFTBLANK_CONVERTED,
            Metrics::SUM_FIELD_LEFTBLANK_CONVERTED,
            Metrics::SUM_FIELD_CONVERTED
        );

        $columns = array_merge(array('label'), $metrics);
        $view->config->columns_to_display = $columns;

        $this->setMetricsForGraphBasedOnColumnsToDisplay($view);
    }

}
