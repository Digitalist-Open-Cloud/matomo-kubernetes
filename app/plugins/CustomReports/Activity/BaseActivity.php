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
namespace Piwik\Plugins\CustomReports\Activity;

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\ActivityLog\Activity\Activity;
use Piwik\Site;

abstract class BaseActivity extends Activity
{
    protected function getReportNameFromActivityData($activityData)
    {
        if (!empty($activityData['report']['name'])) {
            return $activityData['report']['name'];
        }

        if (!empty($activityData['report']['id'])) {
            return $activityData['report']['id'];
        }

        return '';
    }

    protected function getSiteNameFromActivityData($activityData)
    {
        if (!empty($activityData['site']['site_name'])) {
            return $activityData['site']['site_name'];
        }

        if (!empty($activityData['site']['site_id'])) {
            return $activityData['site']['site_id'];
        }

        return '';
    }

    protected function formatActivityData($idSite, $idCustomReport)
    {
        if (!is_numeric($idSite) || !is_numeric($idCustomReport)) {
            return;
        }

        return array(
            'site' => $this->getSiteData($idSite),
            'version' => 'v1',
            'report' => $this->getReportData($idSite, $idCustomReport),
        );
    }

    private function getSiteData($idSite)
    {
        return array(
            'site_id'   => $idSite,
            'site_name' => Site::getNameFor($idSite)
        );
    }

    private function getReportData($idSite, $idCustomReport)
    {
        $report = $this->getDao()->getCustomReport($idSite, $idCustomReport);

        if (!empty($report['name'])) {
            $reportName = $report['name'];
        } else {
            // report name might not be set when we are handling ReportDeleted activity
            $reportName = 'ID: ' . (int) $idCustomReport;
        }

        return array(
            'id' => $idCustomReport,
            'name' => $reportName
        );
    }

    public function getPerformingUser($eventData = null)
    {
        $login = Piwik::getCurrentUserLogin();

        if ($login === self::USER_ANONYMOUS || empty($login)) {
            // anonymous cannot change a report, in this case the system changed it
            return self::USER_SYSTEM;
        }

        return $login;
    }

    private function getDao()
    {
        return StaticContainer::get('Piwik\Plugins\CustomReports\Dao\CustomReportsDao');
    }
}
