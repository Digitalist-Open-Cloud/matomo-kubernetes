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

use Piwik\Common;
use Piwik\Date;
use Piwik\Development;
use Piwik\IP;
use Piwik\Piwik;
use Piwik\Plugins\ActivityLog\Model;
use Piwik\Plugins\UserCountry\LocationProvider;
use Piwik\Plugins\UserCountry\VisitorGeolocator;

class Activity
{
    const USER_SYSTEM = 'Matomo System';
    const USER_CLI = 'Console Command';
    const USER_ANONYMOUS = 'anonymous';

    public static $fakeTime;

    /**
     * @var string  Name of event to log
     */
    protected $eventName;

    /**
     * Returns name of the event to bind to
     *
     * @return string
     */
    public function getEvent()
    {
        return $this->eventName;
    }

    /**
     * Returns data to be used for logging the event.
     * If you want to ignore a log and don't want to have it logged, return boolean "false".
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        return [];
    }

    /**
     * Returns the translated description of the logged event
     *
     * Example: "has created a new website 'NewSite'"
     * Where objects like users or websites might be linked to view/edit them
     *
     * @param array $activityData
     * @param string $performingUser
     * @return string
     */
    public function getTranslatedDescription($activityData, $performingUser)
    {
        unset($activityData['items']);
        return Piwik::translate('ActivityLog_GenericActivity', var_export($activityData, true));
    }

    /**
     * Returns the Login of the user performing the activity
     *
     * Might be 'null' for activities performed by the system, like sending reports
     *
     * @param array $eventData
     *
     * @return string
     */
    public function getPerformingUser($eventData = null)
    {
        return Piwik::getCurrentUserLogin();
    }

    /**
     * Returns the internal (unique) ID of the Activity
     *
     * The ID is built out of the plugin name the activity is located in
     * and the name of the activity class itself (eg ActivityLog/GoalAdded)
     *
     * @return string
     */
    public function getId()
    {
        $className = explode('\\', get_class($this));

        $name = array_pop($className);
        $plugin = $className[2];

        return $plugin . '/' . $name;
    }

    /**
     * Used to log the event as activity when triggered by the event observer
     */
    public static function logEvent()
    {
        $object = new static();

        try {
            $activityParameters = $object->extractParams(func_get_args());
        } catch (\Exception $e) {
            if (Development::isEnabled()) {
                throw $e;
            } else {
                return;
            }
        }

        if ($activityParameters === false) {
            // developers can ignore an event by returning false
            return;
        }

        $userLogin = $object->getPerformingUser(func_get_args());

        if ($userLogin == 'super user was set' || $userLogin == '') {
            $userLogin = self::USER_SYSTEM;
        }

        if (Common::isRunningConsoleCommand()) {
            $userLogin = self::USER_CLI;
        }

        $model = new Model();

        $ip = IP::getIpFromHeader();

        try {
            $geoLocator       = new VisitorGeolocator();
            $locationProvider = $geoLocator->getProvider();
            $location         = $locationProvider->getLocation([
                'ip'   => $ip,
                'lang' => Common::getBrowserLanguage(),
            ]);

            $country = !empty($location[LocationProvider::COUNTRY_CODE_KEY]) ? $location[LocationProvider::COUNTRY_CODE_KEY] : null;
        } catch (\Exception $e) {
            $country = null;
        }

        $activity = array(
            'user_login' => $userLogin,
            'type'       => $object->getId(),
            'parameters' => serialize($activityParameters),
            'ts_created' => self::$fakeTime ? self::$fakeTime : Date::now()->getDatetime(),
            'country'    => $country,
            'ip'         => $ip
        );

        Piwik::postEvent('ActivityLog.logEvent', array(&$activity, $activityParameters));

        if (!empty($activity)) {
            $model->add($activity);
        }
    }
}
