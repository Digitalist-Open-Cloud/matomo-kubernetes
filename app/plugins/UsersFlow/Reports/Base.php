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
 *
 */
namespace Piwik\Plugins\UsersFlow\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugins\UsersFlow\Metrics;

abstract class Base extends Report
{
    protected function init()
    {
        $this->categoryId = 'General_Visitors';
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();

        if (isset($metrics[Metrics::NB_EXITS])) {
            $metrics[Metrics::NB_EXITS] = Piwik::translate('General_ColumnExits');
        }

        return $metrics;
    }
}
