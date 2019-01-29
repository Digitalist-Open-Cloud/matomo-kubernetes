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
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldSumInteractionUnsubmitted;
use Piwik\Plugins\FormAnalytics\Metrics;

class GetMostUsedFields extends BaseFormFieldReport
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_MostUsedFields');
        $this->documentation = Piwik::translate('FormAnalytics_ReportMostUsedFieldsDescription');

        $this->order = 130;
        $this->defaultSortColumn = Metrics::SUM_FIELD_UNIQUE_INTERACTIONS;

        $this->metrics = array(
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS,
            Metrics::SUM_FIELD_INTERACTIONS,
            Metrics::SUM_FIELD_INTERACTIONS_SUBMIT,
            Metrics::SUM_FIELD_UNIQUE_CHANGES,
            Metrics::SUM_FIELD_TOTAL_CHANGES,
        );

        $this->processedMetrics = array(
            new FieldSumInteractionUnsubmitted()
        );
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $view->config->show_tag_cloud = false;

        $view->config->columns_to_display = array(
            'label',
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS,
            Metrics::SUM_FIELD_INTERACTIONS,
            Metrics::SUM_FIELD_INTERACTIONS_SUBMIT,
            Metrics::SUM_FIELD_INTERACTIONS_UNSUBMIT,
            Metrics::SUM_FIELD_UNIQUE_CHANGES,
            Metrics::SUM_FIELD_TOTAL_CHANGES,
        );
        $this->setMetricsForGraphBasedOnColumnsToDisplay($view);
    }


}
