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

class GetEntryFields extends BaseFormFieldReport
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('FormAnalytics_EntryFields');
        $this->documentation = Piwik::translate('FormAnalytics_ReportEntryFieldsDescription');

        $this->defaultSortColumn = Metrics::SUM_FIELD_UNIQUE_ENTRIES;
        $this->order = 110;

        $this->metrics = array(
            Metrics::SUM_FIELD_UNIQUE_ENTRIES,
            Metrics::SUM_FIELD_ENTRIES,
        );
    }

    public function getMetricNamesToProcessReportTotals()
    {
        return array_combine($this->metrics, $this->metrics);
    }

}
