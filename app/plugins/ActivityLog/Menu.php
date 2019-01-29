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
namespace Piwik\Plugins\ActivityLog;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        try {
            ActivityLog::checkPermission();
        } catch (\Exception $e) {
            return;
        }
        if (Piwik::hasUserSuperUserAccess()) {
            $menu->addDiagnosticItem('ActivityLog_ActivityLog', $this->urlForAction('index'), $orderId = 15);
        } else if (!Piwik::isUserIsAnonymous() && Piwik::isUserHasSomeViewAccess()) {
            $menu->addPersonalItem('ActivityLog_ActivityLog', $this->urlForAction('index'), $orderId = 15);
        }
    }

}
