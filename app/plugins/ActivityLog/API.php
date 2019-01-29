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
namespace Piwik\Plugins\ActivityLog;

use Piwik\Date;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\ActivityLog\Activity\Manager;
use Piwik\Plugins\UsersManager\Model AS UsersManagerModel;

require_once PIWIK_DOCUMENT_ROOT . '/plugins/UserCountry/functions.php';

/**
 * The Activity Log API is used to get the activity logs for users in your Matomo instance.
 * <br/><br/>
 * The method `ActivityLog.getEntries` returns a list of the activities done by users in your Matomo instance.

 * <br/>The list of activities returned depends on which user is calling the API:
 * <br/> - if you authenticate with a Super User access, the API will return activity logs for all users
 * <br/> - if you authenticate as anonymous (no authentication), or a user with view or admin access, only this user's activity will be returned.
 * <br/><br/>
 * Each activity includes an activity ID, the user who initiated the activity, a list of parameters/metadata specific to this activity, the datetime (and pretty datetime),
 * the activity description, and the URL to the colored avatar image for this user.
 *
 * <br/><br/>The activity log includes over 80 different types of Matomo activities, for example:
 * <br/> - See when a user logged in, failed to log in, or logged out
 * <br/> - See when a user was created, updated or deleted by who
 * <br/> - See when a website was created, updated or deleted by who
 * <br/> - See when a Matomo setting, an <a href="https://www.ab-tests.net">A/B Test</a>, a <a href="https://piwik.org/docs/email-reports/">Scheduled Report</a>,
 * or a <a href="https://piwik.org/docs/segmentation/">Segment</a> was changed and by who
 *
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Returns entries of activity log
     *
     * If user has no super user access only entries for the current user will be returned
     *
     * @param int $offset offset to start at
     * @param int $limit amount of entries to return
     * @param null|string $filterByUserLogin userLogin to filter by
     * @param null|string $filterByActivityType activity type to filter by
     *
     * @return array
     */
    public function getEntries($offset = 0, $limit = 25, $filterByUserLogin = null, $filterByActivityType = null)
    {
        ActivityLog::checkPermission();

        $filterByUserLogin = $this->getFilterByUserLogin($filterByUserLogin);

        $model   = $this->getModel();
        $entries = $model->getEntries($offset, $limit, $filterByUserLogin, $filterByActivityType);

        $settings = new SystemSettings();
        $formatter = new Formatter();
        $currentTimestamp = Date::now()->getTimestampUTC();

        // unserialize parameters field of entries
        foreach ($entries as &$entry) {
            $type = Manager::getInstance()->factory($entry['type']);
            $entry['type'] = $type->getId();
            $tsCreated = $entry['ts_created'];
            unset($entry['ts_created']);

            $dateCreated = Date::factory($tsCreated);
            $diffSeconds = $currentTimestamp - $dateCreated->getTimestampUTC();

            $entry['parameters']      = unserialize($entry['parameters']);
            $entry['datetime']        = $tsCreated;
            $entry['datetime_pretty'] = $dateCreated->getLocalized(Date::DATETIME_FORMAT_SHORT);
            if ($diffSeconds > 0) {
                $entry['time_relative_pretty'] = $formatter->getPrettyTimeFromSeconds($diffSeconds, true) . ' ago';
            } else {
                $entry['time_relative_pretty'] = '';
            }
            $entry['description'] = $type->getTranslatedDescription($entry['parameters'], $entry['user_login']);
            $entry['avatar']      = '';

            $entry['country_flag'] = \Piwik\Plugins\UserCountry\getFlagFromCode(strtolower($entry['country']));
            $entry['country_name'] = \Piwik\Plugins\UserCountry\countryTranslate($entry['country']);

            $usersManagerModel = new UsersManagerModel();

            if ($settings->enableGravatar->getValue()) {
                $user = $usersManagerModel->getUser($entry['user_login']);
                if (isset($user['email'])) {
                    $hash = $user['email'];
                } else {
                    $hash = $entry['user_login'];
                }

                $entry['avatar'] = sprintf('https://www.gravatar.com/avatar/%s?d=identicon&s=40', md5($hash));
            } elseif (!Piwik::hasUserSuperUserAccess()) {
                $entry['avatar'] = 'plugins/ActivityLog/images/avatar_g1.png';
            } else {
                $crc = abs(crc32($entry['user_login']));
                $numAvailableAvatarImages = 24;
                $avatarIndex = $crc % $numAvailableAvatarImages;
                $entry['avatar'] = 'plugins/ActivityLog/images/avatar' . ($avatarIndex + 1) . '.png';
            }
        }

        return $entries;
    }

    /**
     * Returns count auf available entries
     *
     * @param null|string $filterByUserLogin
     * @param null|string $filterByActivityType
     * @return array
     */
    public function getEntryCount($filterByUserLogin = null, $filterByActivityType = null)
    {
        ActivityLog::checkPermission();
        $filterByUserLogin = $this->getFilterByUserLogin($filterByUserLogin);

        $model = $this->getModel();
        return $model->getAvailableEntryCount($filterByUserLogin, $filterByActivityType);
    }

    private function getFilterByUserLogin($filterByUserLogin)
    {
        if (!Piwik::hasUserSuperUserAccess()) {
            $filterByUserLogin = Piwik::getCurrentUserLogin();
        }

        return $filterByUserLogin;
    }

    private function getModel()
    {
        return new Model();
    }

}
