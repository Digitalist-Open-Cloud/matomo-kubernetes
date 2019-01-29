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
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

/**
 * Defines Settings for ActivityLog.
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $enableGravatar;

    /** @var Setting */
    public $viewPermission;

    protected function init()
    {
        $this->enableGravatar = $this->createEnableGravatarSetting();
        $this->viewPermission = $this->createViewPermissionSetting();
    }

    private function createEnableGravatarSetting()
    {
        return $this->makeSetting('enableGravatar', $default = false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('ActivityLog_EnableGravatar');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function createViewPermissionSetting()
    {
        return $this->makeSetting('viewPermission', $default = 'view', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('ActivityLog_PermissionRequired');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = array(
                'view' => Piwik::translate('ActivityLog_PermissionViewAccess'),
                'admin' => Piwik::translate('ActivityLog_PermissionAdminAccess'),
                'superuser' => Piwik::translate('ActivityLog_PermissionSuperUserAccess')
            );
            $field->description = Piwik::translate('ActivityLog_PermissionDescription');
        });
    }
}
