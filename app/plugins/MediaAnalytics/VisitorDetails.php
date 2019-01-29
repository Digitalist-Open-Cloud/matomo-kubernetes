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
namespace Piwik\Plugins\MediaAnalytics;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\View;

class VisitorDetails extends VisitorDetailsAbstract
{
    const ACTION_TYPE_MEDIA = 'media';
    const EVENT_CATEGORY_VIDEO = 'MediaVideo';
    const EVENT_CATEGORY_AUDIO = 'MediaAudio';

    public function extendActionDetails(&$action, $nextAction, $visitorDetails)
    {
        if (!empty($action['type']) && $action['type'] != 'event') {
            return;
        }

        if (!empty($action['eventValue']) &&
            !empty($action['eventCategory']) &&
            in_array($action['eventCategory'], array(self::EVENT_CATEGORY_VIDEO, self::EVENT_CATEGORY_AUDIO), true)
            && $this->shouldFormatEventValue()) {
            $formatter = new Formatter();
            $action['eventValue'] = $formatter->getPrettyTimeFromSeconds($action['eventValue'], false, true);
        }
    }

    private function shouldFormatEventValue()
    {
        if (Request::isApiRequest($request = null)) {

            if ('1' === Common::getRequestVar('format_metrics', '0', 'string')) {
                return true;
            }

            return false;
        }

        return true;
    }


    public function provideActionsForVisitIds(&$actions, $visitIds)
    {
        if (empty($visitIds)) {
            return;
        }
        $visitIds = array_map('intval', $visitIds);

        $mediaActions = LogTable::getInstance()->getRecordsForVisitIds($visitIds);

        // use while / array_shift combination instead of foreach to save memory
        while (is_array($mediaActions) && count($mediaActions)) {
            $action = array_shift($mediaActions);
            if (!empty($action['idvisitor']) && !@ctype_alnum($action['idvisitor'])) {
                $action['idvisitor'] = bin2hex($action['idvisitor']);
            }
            $idVisit = $action['idvisit'];
            $action['type'] = self::ACTION_TYPE_MEDIA;
            $action['serverTimePretty'] = $action['server_time'];
            $action['url'] = $action['resource'];
            $action['idlink_va'] = $action['idview'];

            $titleOrUrl = $action['media_title'] ? $action['media_title'] : preg_replace('|https?://|i', '', $action['url']);

            if ($action['media_type'] == MediaAnalytics::MEDIA_TYPE_AUDIO) {
                $action['icon'] = 'plugins/MediaAnalytics/images/audio.png';
                $action['media_type'] = 'audio';
                $action['title'] = Piwik::translate('MediaAnalytics_ListenedToX', $titleOrUrl);
            } else if ($action['media_type'] == MediaAnalytics::MEDIA_TYPE_VIDEO) {
                $action['icon'] = 'plugins/MediaAnalytics/images/video.png';
                $action['media_type'] = 'video';
                $action['title'] = Piwik::translate('MediaAnalytics_WatchedVideoX', $titleOrUrl);
            }

            unset($action['server_time'], $action['idview'], $action['idvisit'], $action['resource']);

            if ($action['media_type'] == 'audio') {
                unset($action['resolution'], $action['fullscreen']);
            }

            $actions[$idVisit][] = $action;
        }
    }

    public function renderAction($action, $previousAction, $visitorDetails)
    {
        if ($action['type'] != self::ACTION_TYPE_MEDIA) {
            return;
        }

        $view = new View('@MediaAnalytics/_actionMedia');
        $view->action = $action;
        $view->previousAction = $previousAction;
        $view->visitInfo = $visitorDetails;
        return $view->render();
    }

    public function renderActionTooltip($action, $visitInfo)
    {
        if ($action['type'] != self::ACTION_TYPE_MEDIA) {
            return [];
        }

        $view         = new View('@MediaAnalytics/_actionTooltip');
        $view->action = $action;
        return [[ 120, $view->render() ]];
    }


    public function initProfile($visits, &$profile)
    {
        $profile['mediaViewAudio'] = 0;
        $profile['mediaViewVideo'] = 0;
    }

    public function handleProfileAction($action, &$profile)
    {
        if ($action['type'] != self::ACTION_TYPE_MEDIA) {
            return;
        }

        if ($action['media_type'] == 'audio') {
            ++$profile['mediaViewAudio'];
        } else if ($action['media_type'] == 'video') {
            ++$profile['mediaViewVideo'];
        }
    }

}
