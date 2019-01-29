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
namespace Piwik\Plugins\MediaAnalytics\Tracker;

use Piwik\Common;
use Piwik\Date;
use Piwik\Plugins\MediaAnalytics\Actions\ActionMedia;
use Piwik\Plugins\MediaAnalytics\Configuration;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\MediaAnalytics;
use Piwik\Tracker\Request;
use Piwik\Tracker;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\UrlHelper;
use Piwik\Config;

class RequestProcessor extends Tracker\RequestProcessor
{
    /**
     * @var LogTable
     */
    private $logTable;

    public function __construct(LogTable $logTable)
    {
        $this->logTable = $logTable;
    }

    public function manipulateRequest(Request $request)
    {
        $params = $request->getParams();
        $idView = Common::getRequestVar(ActionMedia::PARAM_ID_VIEW, false, 'string', $params);
        $mediaType = Common::getRequestVar(ActionMedia::PARAM_MEDIA_TYPE, false, 'string', $params);
        $playerName = Common::getRequestVar(ActionMedia::PARAM_PLAYER_NAME, false, 'string', $params);
        $mediaTitle = Common::getRequestVar(ActionMedia::PARAM_MEDIA_TITLE, false, 'string', $params);
        $watchedTime = Common::getRequestVar(ActionMedia::PARAM_SPENT_TIME, false, 'int', $params);
        $resource = Common::getRequestVar(ActionMedia::PARAM_RESOURCE, false, 'string', $params);
        $mediaProgress = Common::getRequestVar(ActionMedia::PARAM_PROGRESS, false, 'int', $params);
        $mediaLength = Common::getRequestVar(ActionMedia::PARAM_MEDIA_LENGTH, false, 'int', $params);
        $timeToInitialPlay = Common::getRequestVar(ActionMedia::PARAM_TIME_TO_INITIAL_PLAY, false, 'string', $params); // string on purposes as 0 !== '' !== false
        $mediaWidth = Common::getRequestVar(ActionMedia::PARAM_MEDIA_WIDTH, false, 'int', $params);
        $mediaHeight = Common::getRequestVar(ActionMedia::PARAM_MEDIA_HEIGHT, false, 'int', $params);
        $isFullscreen = Common::getRequestVar(ActionMedia::PARAM_FULLSCREEN, false, 'int', $params);

        if (!empty($idView) && !empty($resource)) {
            $mediaType = strtolower($mediaType);
            if ($mediaType === 'video') {
                $mediaType = MediaAnalytics::MEDIA_TYPE_VIDEO;
            } elseif ($mediaType === 'audio') {
                $mediaType = MediaAnalytics::MEDIA_TYPE_AUDIO;
            } else {
                $mediaType = 0;
            }

            $this->setIsMediaRequest($request, array(
                'idview' => $idView,
                'media_type' => $mediaType,
                'player_name' => $playerName,
                'media_title' => $mediaTitle,
                'resource' => $resource,
                'watched_time' => $watchedTime,
                'media_progress' => $mediaProgress,
                'media_length' => $mediaLength,
                'time_to_initial_play' => $timeToInitialPlay,
                'media_width' => $mediaWidth,
                'media_height' => $mediaHeight,
                'fullscreen' => $isFullscreen,
            ));
        }
    }

    public function afterRequestProcessed(VisitProperties $visitProperties, Request $request)
    {
        if ($this->getMediaRequest($request)) {
            $request->setMetadata('Actions', 'action', null);
            $request->setMetadata('Goals', 'goalsConverted', array());
        }
    }

    // Actions and Goals metadata might be set after this plugin's afterRequestProcessed was called, make sure to unset it
    public function onNewVisit(VisitProperties $visitProperties, Request $request)
    {
        $this->afterRequestProcessed($visitProperties, $request);
    }

    public function onExistingVisit(&$valuesToUpdate, VisitProperties $visitProperties, Request $request)
    {
        if ($this->getMediaRequest($request)) {
            foreach ($valuesToUpdate as $index => $val) {
                if (!in_array($index, array('visit_last_action_time', 'visit_total_time'))) {
                    // we do not want to update  visitor info for such requests apart to keep the users session alive
                    unset($valuesToUpdate[$index]);
                }
            }
        }

        $this->afterRequestProcessed($visitProperties, $request);
    }

    public function recordLogs(VisitProperties $visitProperties, Request $request)
    {
        $media = $this->getMediaRequest($request);

        if (!empty($media['idview'])) {
            $idVisitor = $visitProperties->getProperty('idvisitor');
            $idVisit = $visitProperties->getProperty('idvisit');
            $idSite = $request->getIdSite();
            $idView = $media['idview'];
            $mediaType = $media['media_type'];
            $playerName = $media['player_name'];
            $mediaTitle = $media['media_title'];
            $watchedTime = $media['watched_time'];
            $seekProgress = $media['media_progress'];
            $mediaLength = $media['media_length'];
            $timeToInitialPlay = $media['time_to_initial_play'];
            $mediaWidth = $media['media_width'];
            $mediaHeight = $media['media_height'];
            $isFullscreen = $media['fullscreen'];

            $visitStandardLength = $this->getVisitStandardLength();
            if (!empty($visitStandardLength) && $timeToInitialPlay > $visitStandardLength) {
                // limit time to inital play
                $timeToInitialPlay = $visitStandardLength;
            }

            $resource = Tracker\PageUrl::excludeQueryParametersFromUrl($media['resource'], $idSite);
            $parsedResource = @parse_url($resource);

            if (!empty($parsedResource['query'])) {
                $config = new Configuration();
                $parametersToExclude = $config->getMediaParametersToExclude();
                if (!empty($parametersToExclude)) {
                    $queryParameters = UrlHelper::getArrayFromQueryString($parsedResource['query']);
                    $parsedResource['query'] = UrlHelper::getQueryStringWithExcludedParameters($queryParameters, $parametersToExclude);
                    $resource = UrlHelper::getParseUrlReverse($parsedResource);
                }
            }

            $serverTime = Date::getDatetimeFromTimestamp($request->getCurrentTimestamp());

            $this->logTable->record($idVisitor, $idVisit, $idSite, $idView, $mediaType, $playerName, $mediaTitle, $resource, $watchedTime, $seekProgress, $mediaLength, $timeToInitialPlay, $mediaWidth, $mediaHeight, $isFullscreen, $serverTime);
        }
    }

    private function getVisitStandardLength()
    {
        return Config::getInstance()->Tracker['visit_standard_length'];
    }

    protected function setIsMediaRequest(Request $request, $data)
    {
        $request->setMetadata('MediaAnalytics', 'media', $data);
    }

    protected function getMediaRequest(Request $request)
    {
        return $request->getMetadata('MediaAnalytics', 'media');
    }


}
