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

use Piwik\Piwik;
use Piwik\Plugins\ActivityLog\Activity\Manager;
use Piwik\Plugins\ActivityLog\Activity\PluginDeactivated;

class ActivityLog extends \Piwik\Plugin
{
    /**
     * Register all defined activities in event observer
     *
     * @return array
     */
    public function registerEvents()
    {
        $events = [
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        ];

        $activities = Manager::getInstance()->getMapOfEventToActivity();

        foreach ($activities as $event => $activityClass) {
            $events[$event] = [$activityClass, 'logEvent'];
        }

        return $events;
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    public function install()
    {
        $model = new Model();
        $model->install();
    }

    /**
     * Force logging when deactivating this plugin
     */
    public function deactivate()
    {
        $activity = new PluginDeactivated();
        $activity->logEvent('ActivityLog');
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/ActivityLog/angularjs/activitylog/activitylog.directive.js';
        $jsFiles[] = 'plugins/ActivityLog/angularjs/activitylog/activitylog.controller.js';
        $jsFiles[] = 'plugins/ActivityLog/angularjs/activitylog/activitylog-model.js';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/ActivityLog/stylesheets/activitylog.less";
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = "CorePluginsAdmin_Active";
        $translationKeys[] = "CorePluginsAdmin_Inactive";
        $translationKeys[] = "CorePluginsAdmin_Version";
        $translationKeys[] = "General_TrackingScopeAction";
        $translationKeys[] = "General_TrackingScopePage";
        $translationKeys[] = "General_TrackingScopeVisit";
        $translationKeys[] = "General_ColumnRevenue";
        $translationKeys[] = "General_Hour";
        $translationKeys[] = "General_Period";
        $translationKeys[] = "General_Report";
        $translationKeys[] = "General_Type";
        $translationKeys[] = "General_Plugin";
        $translationKeys[] = "General_Installed";
        $translationKeys[] = "General_NotInstalled";
        $translationKeys[] = "UsersManager_Email";
        $translationKeys[] = "UsersManager_PrivAdmin";
        $translationKeys[] = "UsersManager_PrivNone";
        $translationKeys[] = "UsersManager_PrivView";
        $translationKeys[] = "Live_GoalType";
        $translationKeys[] = "ScheduledReports_ReportFormat";
        $translationKeys[] = "SitesManager_Type";
        $translationKeys[] = "ActivityLog_Access";
        $translationKeys[] = "ActivityLog_FilterByUser";
        $translationKeys[] = "ActivityLog_ConsoleCommand";
        $translationKeys[] = "ActivityLog_System";
        $translationKeys[] = "ActivityLog_NoValueSet";
        $translationKeys[] = "ActivityLog_UserCountryWithIP";
        $translationKeys[] = "ActivityLog_UserCountry";
    }


    public static function checkPermission()
    {
        Piwik::checkUserIsNotAnonymous();

        $settings = new SystemSettings();
        $permissionLevel = $settings->viewPermission->getValue();

        switch ($permissionLevel) {
            case 'view':
                Piwik::checkUserHasSomeViewAccess();
                break;
            case 'admin':
                Piwik::checkUserHasSomeAdminAccess();
                break;
            case 'superuser':
                Piwik::checkUserHasSuperUserAccess();
                break;
        }
    }
}
