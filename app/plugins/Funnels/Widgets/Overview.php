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
 *
 */
namespace Piwik\Plugins\Funnels\Widgets;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\Funnels\Funnels;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class Overview extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId(Funnels::MENU_CATEGORY);
        $config->setSubcategoryId('General_Overview');
        $config->setName('Funnels_FunnelsOverview');
        $config->setOrder(99);

        $idSite = Common::getRequestVar('idSite', 0, 'int');

        $model = StaticContainer::get('Piwik\Plugins\Funnels\Model\FunnelsModel');
        if ($model->hasAnyActivatedFunnelForSite($idSite)) {
            $config->disable();
        }
    }

}