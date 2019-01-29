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
namespace Piwik\Plugins\MediaAnalytics\Widgets;

use Piwik\API\Request;
use Piwik\ArchiveProcessor\Rules;
use Piwik\Common;
use Piwik\FrontController;
use Piwik\Piwik;
use Piwik\Segment;
use Piwik\View;
use Piwik\Widget\WidgetConfig;

class AudienceMap extends BaseWidget
{
    public static function configure(WidgetConfig $config)
    {
        parent::configure($config);
        
        $idSite = self::getIdSite();
        $config->setName('MediaAnalytics_WidgetTitleAudienceMap');
        $config->setSubcategoryId('MediaAnalytics_TypeAudienceMap');

        if (empty($idSite)) {
            $config->disable();
        } else {
            $config->setIsEnabled(Piwik::isUserHasViewAccess($idSite));
        }
    }

    public function render()
    {
        $idSite = self::getIdSite();
        Piwik::checkUserHasViewAccess($idSite);

        $segmentString = self::getMediaSegment();
        $segment = new Segment($segmentString, array($idSite));

        $params = array($fetch = false, $segmentString);
        $content = FrontController::getInstance()->dispatch('UserCountryMap', 'visitorMap', $params);

        if (self::isUsingDefaultSegment() && !$segment->willBeArchived()) {

            $period = Common::getRequestVar('period', null, 'string');

            // there might be data when it has been archived in the past
            $summary = Request::processRequest('VisitsSummary.get', array(
                'idSite' => $idSite,
                'period' => $period,
                'date' => Common::getRequestVar('date', null, 'string'),
                'segment' => $segmentString,
                'filter_limit' => '-1',
                'format' => 'PHP'
            ));

            $hasNoData = empty($summary['nb_visits']);

            if ($hasNoData) {
                $view = new View('@MediaAnalytics/archiveDisabled.twig');
                $view->canAddSegment = Request::processRequest('SegmentEditor.isUserCanAddNewSegment', array('idSite' => $idSite));
                $content .= $view->render();
            }
        }

        return $content;
    }

}