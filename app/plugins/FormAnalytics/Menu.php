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
namespace Piwik\Plugins\FormAnalytics;

use Piwik\Common;
use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!empty($idSite) && Piwik::isUserHasAdminAccess($idSite)) {
            $menu->addMeasurableItem('FormAnalytics_Forms', $this->urlForAction('manage'), $orderId = 30);
        }
    }

}
