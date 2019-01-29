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

namespace Piwik\Plugins\CustomReports\Input;

use \Exception;
use Piwik\Columns\MetricsList;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;

class Metrics
{
    /**
     * @var array
     */
    private $metrics;

    /**
     * @var int|string
     */
    private $idSite;

    public function __construct($metrics, $idSite)
    {
        $this->metrics = $metrics;
        $this->idSite = $idSite;
    }

    public function check()
    {
        if (empty($this->metrics)) {
            return; // it may be empty
        }

        $title = Piwik::translate('General_Metrics');

        if (!is_array($this->metrics)) {
            throw new Exception(Piwik::translate('CustomReports_ErrorNotAnArray', $title));
        }

        $validateMetricsExist = true;
        if (empty($this->idSite) || $this->idSite === 'all') {
            $configuration = StaticContainer::get('Piwik\Plugins\CustomReports\Configuration');
            $validateMetricsExist = $configuration->shouldValidateReportContentWhenAllSites();
        }

        $metricsList = MetricsList::get();

        foreach ($this->metrics as $index => $metric) {
            if (empty($metric)) {
                throw new Exception(Piwik::translate('CustomReports_ErrorArrayMissingItem', array($title, $index)));
            }
            if ($validateMetricsExist && !$metricsList->getMetric($metric)) {
                throw new Exception(Piwik::translate('CustomReports_ErrorInvalidValueInArray', array($title, $index)));
            }
        }

        if (count($this->metrics) !== count(array_unique($this->metrics))) {
            $title = Piwik::translate('General_Metric');
            throw new Exception(Piwik::translate('CustomReports_ErrorDuplicateItem', $title));
        }
    }

}