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
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldAvgSizeConverted;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldAvgSizeOverall;
use Piwik\Plugins\FormAnalytics\Columns\Metrics\FieldAvgSizeSubmitted;
use Piwik\Plugins\FormAnalytics\Metrics;

class GetFieldSize extends BaseFormFieldReport
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_FieldSize');
        $this->documentation = Piwik::translate('FormAnalytics_ReportFieldSizeDescription');

        $this->defaultSortColumn = Metrics::AVG_FIELD_FIELDSIZE_CONVERTED;
        $this->order = 125;

        $this->metrics = array();
        $this->processedMetrics = array(
            new FieldAvgSizeConverted(),
            new FieldAvgSizeSubmitted(),
            new FieldAvgSizeOverall(),
        );
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);
        $view->config->show_footer_message = Piwik::translate('FormAnalytics_ReportFieldSizeFooterMessage');
    }

}
