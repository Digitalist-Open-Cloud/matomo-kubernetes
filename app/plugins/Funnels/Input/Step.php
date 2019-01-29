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

namespace Piwik\Plugins\Funnels\Input;

use Exception;
use Piwik\Piwik;
use Piwik\Plugins\Funnels\Db\Pattern;

class Step
{
    const NAME_MAX_LENGTH = 150;
    const PATTERN_MAX_LENGTH = 1000;

    /**
     * @var array
     */
    private $step;

    /**
     * @var int
     */
    private $index;
    
    public function __construct($step, $index)
    {
        $this->step = $step;
        $this->index = $index;
    }

    public function check()
    {
        if (!is_array($this->step)) {
            $titleSingular = Piwik::translate('Funnels_Step');
            $titlePlural = Piwik::translate('Funnels_Steps');

            throw new Exception(Piwik::translate('Funnels_ErrorInnerIsNotAnArray', array($titleSingular, $titlePlural)));
        }

        $this->checkName();
        $this->checkPatternType();
        $this->checkPattern();
        $this->checkRequired();
    }

    private function checkName()
    {
        $title = Piwik::translate('Funnels_StepName');

        if (!array_key_exists('name', $this->step)) {
            $title = Piwik::translate('Funnels_Steps');
            throw new Exception(Piwik::translate('Funnels_ErrorArrayMissingKey', array('name', $title, $this->index)));
        }

        if (empty($this->step['name'])) {
            throw new Exception(Piwik::translate('Funnels_ErrorXNotProvided', $title));
        }

        $name = $this->step['name'];

        if (strlen($name) > static::NAME_MAX_LENGTH) {
            throw new Exception(Piwik::translate('Funnels_ErrorXTooLong', array($title, static::NAME_MAX_LENGTH)));
        }
    }

    public function checkPatternType()
    {
        if (!array_key_exists('pattern_type', $this->step)) {
            $title = Piwik::translate('Funnels_Steps');
            throw new Exception(Piwik::translate('Funnels_ErrorArrayMissingKey', array('pattern_type', $title, $this->index)));
        }

        $isAllowed = Pattern::isSupportedPatternType($this->step['pattern_type']);

        if (!$isAllowed) {
            $allowed = Pattern::getSupportedPatternTypes();
            $message = Piwik::translate('Funnels_ErrorXNotWhitelisted', array('pattern_type', implode(', ', $allowed)));
            throw new Exception($message);
        }
    }

    public function checkPattern()
    {
        $title = Piwik::translate('Funnels_StepPattern');

        if (!array_key_exists('pattern', $this->step)) {
            $title = Piwik::translate('Funnels_Steps');
            throw new Exception(Piwik::translate('Funnels_ErrorArrayMissingKey', array('pattern', $title, $this->index)));
        }

        if (empty($this->step['pattern'])) {
            throw new Exception(Piwik::translate('Funnels_ErrorXNotProvided', $title));
        }

        $pattern = $this->step['pattern'];

        if (strlen($pattern) > static::PATTERN_MAX_LENGTH) {
            throw new Exception(Piwik::translate('Funnels_ErrorXTooLong', array($title, static::PATTERN_MAX_LENGTH)));
        }
    }

    private function checkRequired()
    {
        if (!array_key_exists('required', $this->step)) {
            $title = Piwik::translate('Funnels_Steps');
            throw new Exception(Piwik::translate('Funnels_ErrorArrayMissingKey', array('required', $title, $this->index)));
        }

        $allowedValues = array('0', '1', 0, 1, true, false);

        if (!in_array($this->step['required'], $allowedValues, true)) {
            $message = Piwik::translate('Funnels_ErrorXNotWhitelisted', array('required', '"0", "1"'));

            throw new Exception($message);
        }
    }
}