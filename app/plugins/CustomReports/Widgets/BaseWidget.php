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
use Piwik\Plugins\CustomReports\Dao\CustomReportsDao;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

abstract class BaseWidget extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setOrder(99);
        $config->setCategoryId(CustomReportsDao::DEFAULT_CATEGORY);
    }

    protected static function getIdSite()
    {
        return Common::getRequestVar('idSite', false, 'int');
    }

}