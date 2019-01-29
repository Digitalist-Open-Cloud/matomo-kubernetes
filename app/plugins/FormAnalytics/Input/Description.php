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

class Description
{
    const MAX_LENGTH = 1000;

    /**
     * @var string
     */
    private $description;

    public function __construct($description)
    {
        $this->description = $description;
    }

    public function check()
    {
        if (empty($this->description)) {
            // no description is valid
            return;
        }

        if (Common::mb_strlen($this->description) > self::MAX_LENGTH) {
            $title = Piwik::translate('General_Description');
            throw new Exception(Piwik::translate('FormAnalytics_ErrorXTooLong', array($title, static::MAX_LENGTH)));
        }

    }

}