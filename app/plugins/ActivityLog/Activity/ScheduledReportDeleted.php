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
use Piwik\Site;
use Piwik\Plugins\ScheduledReports\API as ScheduledReportsAPI;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

class ScheduledReportDeleted extends Activity
{
    protected $eventName = 'API.ScheduledReports.deleteReport';

    /**
     * Returns data to be used for logging the event
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        list($finalAPIParameters) = $eventData;

        $idReport = $finalAPIParameters['idReport'];

        $APIScheduledReports = ScheduledReportsAPI::getInstance()->getReports($idSite = false, $periodSearch = false,
            $idReport);
        $report              = reset($APIScheduledReports);
        $idSite              = $report['idsite'];

        return [
            'items'     => [
                [
                    'type' => 'scheduledreport',
                    'data' => [
                        'id'          => $idReport,
                        'description' => $report['description'],
                        'period'      => $report['period'],
                        'hour'        => $report['hour'],
                        'type'        => $report['type'],
                        'format'      => $report['format'],
                    ]
                ],
                [
                    'type' => 'measurable',
                    'data' => [
                        'id'   => $idSite,
                        'type' => Site::getTypeFor($idSite),
                        'name' => Site::getNameFor($idSite),
                        'urls' => SitesManagerAPI::getInstance()->getSiteUrlsFromId($idSite)
                    ]
                ]
            ],
        ];
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
        return Piwik::translate('ActivityLog_ScheduledReportDeleted');
    }
}