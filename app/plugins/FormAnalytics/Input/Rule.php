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

namespace Piwik\Plugins\FormAnalytics\Input;

use \Exception;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\FormAnalytics\Tracker\RuleMatcher;

abstract class Rule
{
    const VALUE_MAX_LENGTH = 1000;

    /**
     * @var array
     */
    private $rule;

    /**
     * @var string
     */
    private $titlePlural;

    /**
     * @var int
     */
    private $index;

    /**
     * @var array
     */
    protected $allowedAttributes = array();

    public function __construct($titlePlural, $rule, $index)
    {
        $this->titlePlural = $titlePlural;
        $this->rule = $rule;
        $this->index = $index;
    }

    public function check()
    {
        if (!is_array($this->rule)) {
            $titleSingular = Piwik::translate('FormAnalytics_Rule');

            throw new Exception(Piwik::translate('FormAnalytics_ErrorInnerIsNotAnArray', array($titleSingular, $this->titlePlural)));
        }

        $this->checkAttribute();
        $this->checkPattern();
        $this->checkValue();
    }

    private function checkAttribute()
    {
        if (!array_key_exists('attribute', $this->rule)) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorArrayMissingKey', array('attribute', $this->titlePlural, $this->index)));
        }

        $attribute = $this->rule['attribute'];

        if (!in_array($attribute, $this->allowedAttributes, $strict = true)) {
            $message = $this->titlePlural . ': ' . Piwik::translate('FormAnalytics_ErrorXNotWhitelisted', array('attribute', implode(', ', $this->allowedAttributes)));
            throw new Exception($message);
        }
    }

    private function checkPattern()
    {
        if (!array_key_exists('pattern', $this->rule)) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorArrayMissingKey', array('pattern', $this->titlePlural, $this->index)));
        }

        $pattern = $this->rule['pattern'];
        $patterns = array_keys(RuleMatcher::getPatternTranslations());

        if (!in_array($pattern, $patterns, $strict = true)) {
            $message = $this->titlePlural . ': ' . Piwik::translate('FormAnalytics_ErrorXNotWhitelisted', array('pattern', implode(', ', $patterns)));
            throw new Exception($message);
        }
    }

    private function checkValue()
    {
        if (!array_key_exists('value', $this->rule)) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorArrayMissingKey', array('value', $this->titlePlural, $this->index)));
        }

        $value = $this->rule['value'];
        $title = 'value' . ' at index ' . (int) $this->index;

        if (empty($value)) {
            throw new Exception($this->titlePlural . ': ' . Piwik::translate('FormAnalytics_ErrorXNotProvided', $title));
        }

        if (Common::mb_strlen($value) > static::VALUE_MAX_LENGTH) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorXTooLong', array($title, static::VALUE_MAX_LENGTH)));
        }

    }
}