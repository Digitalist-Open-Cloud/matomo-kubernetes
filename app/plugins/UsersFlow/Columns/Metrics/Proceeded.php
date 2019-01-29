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
namespace Piwik\Plugins\UsersFlow\Columns\Metrics;

use Piwik\DataTable\Row;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugins\UsersFlow\Metrics as PluginMetrics;

class Proceeded extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::NB_PROCEEDED;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('UsersFlow_ColumnProceeded');
    }

    public function getDocumentation()
    {
        return Piwik::translate('UsersFlow_ColumnProceededDocumentation');
    }

    public function compute(Row $row)
    {
        $visits = $this->getMetric($row, PluginMetrics::NB_VISITS);
        $exits = $this->getMetric($row, PluginMetrics::NB_EXITS);

        $proceeded = $visits - $exits;

        if ($proceeded < 0) {
            $proceeded = 0;
        }

        return (int) $proceeded;
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::NB_VISITS, PluginMetrics::NB_EXITS);
    }
}