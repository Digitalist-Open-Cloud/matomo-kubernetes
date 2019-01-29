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
use Piwik\Piwik;

class ConversionRules
{
    /**
     * @var array
     */
    private $rules;

    public function __construct($rules)
    {
        $this->rules = $rules;
    }

    public function check()
    {
        if (empty($this->rules)) {
            return; // it may be empty
        }

        $title = Piwik::translate('FormAnalytics_ConversionRules');

        if (!is_array($this->rules)) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorNotAnArray', $title));
        }

        foreach ($this->rules as $index => $rule) {
            $rule = new PageRule($title, $rule, $index);
            $rule->check();
        }
    }

}