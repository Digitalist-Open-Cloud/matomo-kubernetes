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

use Piwik\Columns\Dimension;
use Piwik\Piwik;

class ComponentUpdated extends Activity
{
    protected $eventName = 'Updater.componentUpdated';

    /**
     * Returns data to be used for logging the event
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        list($component, $version) = $eventData;

        // do not track updates of bundled plugins, as they are always updated with Matomo Core
        if($component !== 'core' && \Piwik\Plugin\Manager::getInstance()->isPluginBundledWithCore($component)) {
            return false;
        }

        // ignore dimension and other component updates other than core and plugins
        if ($component !== 'core' && !\Piwik\Plugin\Manager::getInstance()->isPluginInstalled($component)) {
            return false;
        }

        $params = [
            'component' => $component,
            'version'   => $version,
        ];

        if ($component !== 'core') {
            $params['items'] = [
                [
                    'type' => 'plugin',
                    'data' => [
                        'name' => $component,
                        'version' => $version
                    ]
                ]
            ];
        }

        return $params;
    }

    public function getPerformingUser($eventData = null)
    {
        $login = Piwik::getCurrentUserLogin();

        if ($login === self::USER_ANONYMOUS) {
            // anonymous cannot update a component. The system may update plugins before access is initialized
            return self::USER_SYSTEM;
        }

        return $login;
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
        if ('core' == $activityData['component']) {
            return Piwik::translate('ActivityLog_ComponentUpdatedPiwik', [$activityData['version']]);
        }
        return Piwik::translate('ActivityLog_ComponentUpdatedPlugin');
    }
}
