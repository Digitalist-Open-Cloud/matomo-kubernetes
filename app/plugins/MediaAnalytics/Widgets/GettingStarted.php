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
use Piwik\Container\StaticContainer;
use Piwik\NoAccessException;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Widget\WidgetConfig;

class GettingStarted extends BaseWidget
{
    public static function configure(WidgetConfig $config)
    {
        parent::configure($config);
        
        $idSite = self::getIdSite();
        $config->setIsNotWidgetizable();
        $config->setName('MediaAnalytics_GettingStarted');
        $config->setOrder(1);
        $config->setMiddlewareParameters(array('module' => 'MediaAnalytics', 'action' => 'hasNoRecords'));
        $config->setCategoryId('MediaAnalytics_Media');
        $config->setSubcategoryId('General_Overview');

        if (empty($idSite)) {
            $config->disable();
        } else {
            $config->setIsEnabled(Piwik::isUserHasViewAccess($idSite));
        }

    }

    public function render()
    {
        try {
            if (PluginManager::getInstance()->isPluginActivated('CustomPiwikJs')) {
                $includeAutomatically = Request::processRequest('CustomPiwikJs.doesIncludePluginTrackersAutomatically');
            } else {
                $includeAutomatically = false;
            }
        } catch (NoAccessException $e) {
            $includeAutomatically = true;
        }

        $idSite = self::getIdSite();
        $siteName = Site::getNameFor($idSite);

        return $this->renderTemplate('gettingStarted', array(
            'siteName' => $siteName,
            'piwikJsWritable' => $includeAutomatically
        ));
    }

}