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
use Piwik\Piwik;
use Piwik\Widget\WidgetConfig;

class CurrentPlays extends BaseLiveWidget
{
    public static function configure(WidgetConfig $config)
    {
        parent::configure($config);

        $idSite = self::getIdSite();
        $config->setName('MediaAnalytics_WidgetTitleMediaPlays');
        $config->setOrder(99);
        $config->setSubcategoryId('MediaAnalytics_TypeRealTime');
        if (empty($idSite)) {
            $config->disable();
        } else {
            $config->setIsEnabled(Piwik::isUserHasViewAccess($idSite));
        }
    }

    /**
     * This method renders the widget. It's on you how to generate the content of the widget.
     * As long as you return a string everything is fine. You can use for instance a "Piwik\View" to render a
     * twig template. In such a case don't forget to create a twig template (eg. myViewTemplate.twig) in the
     * "templates" directory of your plugin.
     *
     * @return string
     */
    public function render()
    {
        $idSite = self::getIdSite();

        $last30 = Request::processRequest('MediaAnalytics.getCurrentNumPlays', array('idSite' => $idSite, 'lastMinutes' => 30));
        $last3600 = Request::processRequest('MediaAnalytics.getCurrentNumPlays', array('idSite' => $idSite, 'lastMinutes' => 3600));

        return $this->renderLiveMetrics('currentPlays', $last30, $last3600, 'MediaAnalytics_NbPlays');
    }

}