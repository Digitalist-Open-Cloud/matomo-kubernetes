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
use Piwik\Settings\Plugin\SystemSettings;

class SystemSettingsUpdated extends Activity
{
    protected $eventName = 'SystemSettings.updated';

    /**
     * Returns data to be used for logging the event
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        /** @var SystemSettings $systemSettings */
        list($systemSettings) = $eventData;

        $settings = $systemSettings->getSettingsWritableByCurrentUser();

        $result = [
            'items'  => [
                [
                    'type' => 'plugin',
                    'data' => [
                        'name' => $systemSettings->getPluginName(),
                    ]
                ]
            ]
        ];


        foreach ($settings as $setting) {
            $value = $setting->getValue();

            if (is_array($value)) {
                $value = array_filter($value, "strlen");
            }

            $result['items'][] = [
                'type' => 'setting',
                'data' => [
                    'name'  => $setting->getName(),
                    'value' => is_array($value) && !count($value) ? null : $value
                ]
            ];
        }

        return $result;
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
        return Piwik::translate('ActivityLog_UserUpdatedSystemSettings');
    }
}