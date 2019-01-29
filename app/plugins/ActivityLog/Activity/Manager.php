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

use Piwik\Cache;
use Piwik\CacheId;
use Piwik\Plugin;
use Piwik\Singleton;

class Manager extends Singleton
{
    /**
     * Returns instance of activity of given type
     *
     * @param string $classNameOrId  class name or id of an activity
     * @return Activity
     */
    public function factory($classNameOrId)
    {
        $activities = $this->getMapOfIdToActivity();
        if (array_key_exists($classNameOrId, $activities)) {
            return new $activities[$classNameOrId];
        }

        if (in_array($classNameOrId, $activities)) {
            return new $classNameOrId;
        }

        return new Activity();
    }

    /**
     * Returns a mapping of events to activity class names
     *
     * @return array
     * @throws \Exception
     */
    public function getMapOfEventToActivity()
    {
        $cacheId = CacheId::pluginAware('UserActivityEventMap');

        $cache = Cache::getEagerCache();
        if ($cache->contains($cacheId)) {
            $mapEventToActivity = $cache->fetch($cacheId);
        } else {
            $activities = $this->getAllActivities();

            $mapEventToActivity = array();
            foreach ($activities as $activity) {
                $event = $activity->getEvent();
                if (array_key_exists($event, $mapEventToActivity)) {
                    throw new \Exception(sprintf('Activity for event already registered', $event));
                }
                $mapEventToActivity[$event] = get_class($activity);
            }

            $cache->save($cacheId, $mapEventToActivity);
        }

        return $mapEventToActivity;
    }

    /**
     * Returns a mapping of internal ids to activity class names
     *
     * @return array
     * @throws \Exception
     */
    public function getMapOfIdToActivity()
    {
        $cacheId = CacheId::pluginAware('UserActivityIdMap');

        $cache = Cache::getEagerCache();
        if ($cache->contains($cacheId)) {
            $mapIdToActivity = $cache->fetch($cacheId);
        } else {
            $activities = $this->getAllActivities();

            $mapIdToActivity = array();
            foreach ($activities as $activity) {
                $id = $activity->getId();
                if (array_key_exists($id, $mapIdToActivity)) {
                    throw new \Exception(sprintf('Activity with id %s already registered', $id));
                }
                $mapIdToActivity[$id] = get_class($activity);
            }

            $cache->save($cacheId, $mapIdToActivity);
        }

        return $mapIdToActivity;
    }

    /**
     * Returns all available activities
     *
     * @return Activity[]
     * @throws \Exception
     */
    public function getAllActivities()
    {
        $cacheId = CacheId::pluginAware('UserActivities');
        $cache   = Cache::getTransientCache();

        if (!$cache->contains($cacheId)) {
            $instances = [];

            foreach ($this->getAllActivityClasses() as $className) {
                $instance = new $className();
                $instances[] = $instance;
            }

            $cache->save($cacheId, $instances);
        }

        return $cache->fetch($cacheId);
    }

    /**
     * Returns class names of all Activity classes.
     *
     * @return string[]
     * @api
     */
    public function getAllActivityClasses()
    {
        return Plugin\Manager::getInstance()->findMultipleComponents('Activity', 'Piwik\Plugins\ActivityLog\Activity\Activity');
    }
}