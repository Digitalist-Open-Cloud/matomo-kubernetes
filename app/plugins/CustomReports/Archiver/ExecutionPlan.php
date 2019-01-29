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

namespace Piwik\Plugins\CustomReports\Archiver;

use Piwik\Columns\Dimension;
use Piwik\Plugin\ArchivedMetric;

class ExecutionPlan
{
    /** @var Dimension[] */
    private $dimensions;

    /** @var ArchivedMetric[] */
    private $metrics;

    public function __construct($dimensions, $metrics)
    {
        $this->dimensions = array_values($dimensions);
        $this->metrics = $metrics;
    }

    /**
     * @return Dimension[][]
     */
    public function getDimensionsPlan()
    {
        $numDimensions = count($this->dimensions);

        $plan = array();

        for ($i = 0; $i < $numDimensions; $i++) {
            $dimension = $this->dimensions[$i];

            $currentTable = $dimension->getDbTableName();

            $group = array('left' => array(), 'right' => array());

            if ($i >= 1) {
                for ($j = 0; $j < $i; $j++) {
                    $group['left'][] = $this->dimensions[$j];
                }
            }

            $group['left'][] = $dimension;

            while (isset($this->dimensions[$i + 1]) && $this->dimensions[$i + 1]->getDbTableName() === $currentTable) {
                $i++;
                $group['left'][] = $this->dimensions[$i];
            }

            if (isset($this->dimensions[$i + 1])) {
                $group['right'][] = $this->dimensions[$i + 1];
            }

            $plan[] = $group;
        }

        if (empty($plan)) {
            // for evolution graph... we need to make sure to still iterate at least over one "fake group"
            $plan[] = array('left' => array(), 'right' => array());
        }

        return $plan;
    }

    /**
     * @return ArchivedMetric[][]
     */
    public function getMetricsPlanForGroup()
    {
        $metricsToResolve = $this->metrics;

        $plan = array();

        foreach ($metricsToResolve as $index => $metric) {
            if (!$metric->getDimension()) {
                continue;
            }

            $discriminator = $metric->getDimension()->getDbDiscriminator();
            // create a separate run for each metric that has a discriminator
            if (!empty($discriminator)) {
                $id = $metric->getDbTableName() . '_' . $discriminator->getTable() . '_' . $discriminator->getColumn() . '_' . $discriminator->getValue();
                if (!isset($plan[$id])) {
                    // improve performance by grouping metrics with the very same discriminator together!!
                    $plan[$id] = array();
                }
                $plan[$id][] = $metric;
                unset($metricsToResolve[$index]);
            } else {
                if (!isset($plan[$metric->getDbTableName()])) {
                    $plan[$metric->getDbTableName()] = array();
                }

                // we try to put all metrics that go on same table in one run
                $plan[$metric->getDbTableName()][] = $metric;
            }
        }

        return $plan;
    }

}
