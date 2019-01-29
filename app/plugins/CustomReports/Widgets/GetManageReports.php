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

namespace Piwik\Plugins\CustomReports\Widgets;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\CustomReports\Dao\CustomReportsDao;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class GetManageReports extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId(CustomReportsDao::DEFAULT_CATEGORY);
        $config->setSubcategoryId('CustomReports_ManageReports');
        $config->setName('CustomReports_ManageReports');
        $config->setParameters(array('showtitle' => 0));
        $config->setOrder(99);
        $config->setIsNotWidgetizable();

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (Piwik::isUserHasAdminAccess($idSite)) {
            $config->enable();
        } else {
            $config->disable();
        }
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasAdminAccess($idSite);

        return '<div piwik-reports-manage></div>';
    }

}