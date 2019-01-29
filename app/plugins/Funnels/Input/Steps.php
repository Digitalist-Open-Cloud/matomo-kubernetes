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

use \Exception;
use Piwik\Piwik;

class Steps
{
    /**
     * @var array
     */
    private $steps;

    public function __construct($steps)
    {
        if (empty($steps)) {
            $steps = array();
        }
        $this->steps = $steps;
    }

    /**
     * @return Step[]
     */
    public function getSteps()
    {
        $steps = array();
        foreach ($this->steps as $index => $step) {
            $steps[] = new Step($step, $index);
        }

        return $steps;
    }

    public function check()
    {
        $title = 'Funnels_Steps';

        if (!is_array($this->steps)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('Funnels_ErrorNotAnArray', $title));
        }

        foreach ($this->getSteps() as $index => $step) {
            $step->check();
        }
    }

}