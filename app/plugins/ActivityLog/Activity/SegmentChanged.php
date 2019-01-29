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
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

class SegmentChanged extends Activity
{
    protected $eventName = 'SegmentEditor.update';

    /**
     * Returns data to be used for logging the event
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        list($idSegment, $segmentData) = $eventData;

        // $segmentData = [ name, definition, enable_all_users, enable_only_idsite, auto_archive, ts_last_edit ]

        $idSite = $segmentData['enable_only_idsite'];

        $return = [
            'items'   => [
                [
                    'type' => 'segment',
                    'data' => [
                        'id'         => $idSegment,
                        'name'       => $segmentData['name'],
                        'definition' => $segmentData['definition'],
                    ]
                ]
            ],

        ];

        if (!empty($idSite)) {
            $return['items'][] = [
                'type' => 'measurable',
                'data' => [
                    'id'   => $idSite,
                    'type' => Site::getTypeFor($idSite),
                    'name' => Site::getNameFor($idSite),
                    'urls' => SitesManagerAPI::getInstance()->getSiteUrlsFromId($idSite)
                ]
            ];
        }

        return $return;
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
        return Piwik::translate('ActivityLog_SegmentUpdated');
    }
}