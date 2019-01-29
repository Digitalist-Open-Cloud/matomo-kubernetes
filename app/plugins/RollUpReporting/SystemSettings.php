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

namespace Piwik\Plugins\RollUpReporting;

use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $syncCreationDate;

    protected function init()
    {
        $this->syncCreationDate = $this->createSyncCreationDateSetting();
    }

    private function createSyncCreationDateSetting()
    {
        return $this->makeSetting('metric', $default = true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('RollUpReporting_SystemSettingCreationDateSyncTitle');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            $field->description = Piwik::translate('RollUpReporting_SystemSettingCreationDateSyncHelp');
        });
    }
}

