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
use Piwik\Plugins\UserCountry\LocationProvider;

class UserCountrySetLocationProvider extends Activity
{
    protected $eventName = 'API.UserCountry.setLocationProvider.end';

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
        // $finalAPIParameters[parameters] = [ providerId ]

        $params = [
            'provider_id' => $finalAPIParameters['parameters']['providerId'],
        ];

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
        $providerName = $activityData['provider_id'];

        $provider = LocationProvider::getProviderById($providerName);

        if ($provider) {
            $providerName = Piwik::translate($provider::TITLE);
        }

        return Piwik::translate('ActivityLog_UserCountrySetLocationProvider', array($providerName));
    }
}