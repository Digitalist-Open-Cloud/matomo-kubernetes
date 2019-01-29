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
namespace Piwik\Plugins\ActivityLog\Activity;

use Piwik\Piwik;

class PrivacySetDeleteReportsSettings extends Activity
{
    protected $eventName = 'API.PrivacyManager.setDeleteReportsSettings.end';

    /**
     * Returns data to be used for logging the event
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        list($true, $finalAPIParameters) = $eventData;

        // $finalAPIParameters = [ className, module, action, parameters ]
        // $finalAPIParameters[parameters] = [ enableDeleteReports, deleteReportsOlderThan, keepBasic, keepDay,
        //                                     keepWeek, keepMonth, keepYear, keepRange, keepSegments ]

        $params = [
            'enable' => !empty($finalAPIParameters['parameters']['enableDeleteReports']),
        ];

        if (!empty($finalAPIParameters['parameters']['enableDeleteReports'])) {
            $params['items'] = [
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'delete_reports_older_than',
                        'value' => $finalAPIParameters['parameters']['deleteReportsOlderThan']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'keep_basic_metrics',
                        'value' => !!$finalAPIParameters['parameters']['keepBasic'],
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'keep_day_reports',
                        'value' => !!$finalAPIParameters['parameters']['keepDay']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'keep_week_reports',
                        'value' => !!$finalAPIParameters['parameters']['keepWeek']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'keep_month_reports',
                        'value' => !!$finalAPIParameters['parameters']['keepMonth']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'keep_year_reports',
                        'value' => !!$finalAPIParameters['parameters']['keepYear']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'keep_range_reports',
                        'value' => !!$finalAPIParameters['parameters']['keepRange']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'keep_segment_reports',
                        'value' => !!$finalAPIParameters['parameters']['keepSegments']
                    ]
                ]
            ];
        }

        return $params;
    }

    /**
     * Returns the translated description of the logged event
     *
     * @param array $activityData
     * @param string $performingUser
     * @return string
     */
    public function getTranslatedDescription($activityData, $performingUser)
    {
        if (empty($activityData['enable'])) {
            return Piwik::translate('ActivityLog_PrivacyDisabledDeleteReportsSettings');
        }

        return Piwik::translate('ActivityLog_PrivacyChangedDeleteReportsSettings');
    }
}