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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance;

use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

/**
 * Defines Settings for SearchEngineKeywordsPerformance.
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $roundKeywordPosition;

    protected function init()
    {
        $this->roundKeywordPosition = $this->createRoundKeywordPosition();
    }

    private function createRoundKeywordPosition()
    {
        return $this->makeSetting('roundKeywordPosition', $default = true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('SearchEngineKeywordsPerformance_RoundKeywordPosition');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }
}
