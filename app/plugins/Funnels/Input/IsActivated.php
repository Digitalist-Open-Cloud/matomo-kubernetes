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

class IsActivated
{
    private $isActivated;

    public function __construct($value)
    {
        $this->isActivated = $value;
    }

    public function check()
    {
        $allowedValues = array('0', '1', 0, 1, true, false);

        if (!in_array($this->isActivated, $allowedValues, $strict = true)) {
            $message = Piwik::translate('Funnels_ErrorXNotWhitelisted', array('activated', '"1", "0"'));
            throw new Exception($message);
        }
    }


}