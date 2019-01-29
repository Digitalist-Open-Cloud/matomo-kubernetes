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

use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\View;

class Controller extends ControllerAdmin
{

    public function index()
    {
        ActivityLog::checkPermission();

        $view = new View('@ActivityLog/index');
        $view->showPagingBottom = true;

        $this->setBasicVariablesView($view);

        return $view->render();
    }

    public function getActivityLog()
    {
        ActivityLog::checkPermission();

        $view = new View('@ActivityLog/activitylog');
        $view->showPagingBottom = false;

        $this->setBasicVariablesView($view);

        return '<div class="activityLogWidget">' .$view->render() . '</div>';
    }
}