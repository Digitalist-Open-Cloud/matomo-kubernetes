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

use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    const FORM_CREATION_DISABLED = 'disabled';
    const FORM_CREATION_UP_TO_10 = 'up_to_10';
    const FORM_CREATION_UP_TO_50 = 'up_to_50';
    const FORM_CREATION_UNLIMITED = 'unlimited';

    /** @var Setting */
    public $autoCreateForm;

    protected function init()
    {
        $this->autoCreateForm = $this->createAutoCreateFormSetting();
    }

    private function createAutoCreateFormSetting()
    {
        return $this->makeSetting('autoCreateForm', self::FORM_CREATION_UP_TO_10, FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('FormAnalytics_SettingCreateFormsAutomatically');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->description = Piwik::translate('FormAnalytics_SettingCreateFormsAutomaticallyDescription');
            $field->availableValues = array(
                self::FORM_CREATION_DISABLED => Piwik::translate('FormAnalytics_Disabled'),
                self::FORM_CREATION_UP_TO_10 => Piwik::translate('FormAnalytics_UpToXForms', 10),
                self::FORM_CREATION_UP_TO_50 => Piwik::translate('FormAnalytics_UpToXForms', 50),
                self::FORM_CREATION_UNLIMITED => Piwik::translate('FormAnalytics_UnlimitedForms'),
            );
        });
    }

}
