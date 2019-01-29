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
use Piwik\Plugins\FormAnalytics\Metrics;

class GetDropOffFields extends BaseFormFieldReport
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_DropOffFields');
        $this->documentation = Piwik::translate('FormAnalytics_ReportDropOffFieldsDescription');

        $this->defaultSortColumn = Metrics::SUM_FIELD_UNIQUE_DROPOFFS;

        $this->metrics = array(
            Metrics::SUM_FIELD_UNIQUE_DROPOFFS,
            Metrics::SUM_FIELD_DROPOFFS,
        );

        $this->order = 105;
    }

    public function getMetricNamesToProcessReportTotals()
    {
        return array_combine($this->metrics, $this->metrics);
    }

}
