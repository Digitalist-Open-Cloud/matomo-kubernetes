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
namespace Piwik\Plugins\MediaAnalytics\DataTable\Filter;

use Piwik\DataTable;

class AddMissingSpentTime extends DataTable\BaseFilter
{
    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $existingSeconds = $table->getColumn('label');

        if (!empty($existingSeconds) && count($existingSeconds) < 80) {
            // we had a few more labels if there are less than 80 labels in the existing dataTable
            $max = max($existingSeconds);
            $max = min(3601, $max);
            // we never add more values for rows after 3600s (1 hour) it's +1 for comparing "<" in loop

            foreach (self::getAllBucketValues() as $bucketValue) {
                if ($max > $bucketValue && !in_array($bucketValue, $existingSeconds)) {
                    // we add new labels if they don't exist yet but only for our actual "buckets"
                    $table->addRowFromSimpleArray(array('label' => $bucketValue));
                }
            }
        }

        // we make sure they are sorted by label
        $table->filter('Sort', array('label', 'asc'));
        $table->disableFilter('Sort');
    }

    public static function getAllBucketValues()
    {
        // we could loop over all values and generate them here "dynamically" but it is faster this way
        return array (
            1,
            2,
            3,
            4,
            5,
            6,
            7,
            8,
            9,
            10,
            12,
            14,
            16,
            18,
            20,
            22,
            24,
            26,
            28,
            30,
            35,
            40,
            45,
            50,
            55,
            60,
            70,
            80,
            90,
            100,
            110,
            120,
            140,
            160,
            180,
            200,
            220,
            240,
            260,
            280,
            300,
            330,
            360,
            390,
            420,
            450,
            480,
            510,
            540,
            570,
            600,
            630,
            660,
            690,
            720,
            750,
            780,
            810,
            840,
            870,
            900,
            930,
            960,
            990,
            1020,
            1050,
            1080,
            1110,
            1140,
            1170,
            1200,
            1260,
            1320,
            1380,
            1440,
            1500,
            1560,
            1620,
            1680,
            1740,
            1800,
            1860,
            1920,
            1980,
            2040,
            2100,
            2160,
            2220,
            2280,
            2340,
            2400,
            2460,
            2520,
            2580,
            2640,
            2700,
            2760,
            2820,
            2880,
            2940,
            3000,
            3060,
            3120,
            3180,
            3240,
            3300,
            3360,
            3420,
            3480,
            3540,
            3600,
        );
    }

}