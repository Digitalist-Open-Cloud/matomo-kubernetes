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

namespace Piwik\Plugins\FormAnalytics\Archiver;

use Piwik\Plugins\FormAnalytics\Metrics;

class FieldDataArray extends SimpleDataArray
{

    /**
     * Returns an empty row containing default metrics
     *
     * @return array
     */
    public function createEmptyRow()
    {
        return array(
            Metrics::SUM_FIELD_TIME_SPENT => 0,
            Metrics::SUM_FIELD_HESITATION_TIME => 0,
            Metrics::SUM_FIELD_FIELDSIZE => 0,
            Metrics::SUM_FIELD_FIELDSIZE_UNSUBMITTED => 0,
            Metrics::SUM_FIELD_FIELDSIZE_SUBMITTED => 0,
            Metrics::SUM_FIELD_FIELDSIZE_CONVERTED => 0,
            Metrics::SUM_FIELD_UNIQUE_AMENDMENTS => 0,
            Metrics::SUM_FIELD_UNIQUE_REFOCUS => 0,
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS => 0,
            Metrics::SUM_FIELD_CONVERTED => 0,
            Metrics::SUM_FIELD_SUBMITTED => 0,
            Metrics::SUM_FIELD_LEFTBLANK_SUBMITTED => 0,
            Metrics::SUM_FIELD_LEFTBLANK_CONVERTED => 0,
        );
    }

}
