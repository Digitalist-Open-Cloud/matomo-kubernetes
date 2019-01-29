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

class Name
{
    const MAX_LENGTH = 50;

    /**
     * @var string
     */
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function check()
    {
        $title = 'FormAnalytics_FormName';

        if (empty($this->name)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('FormAnalytics_ErrorXNotProvided', $title));
        }

        if (Common::mb_strlen($this->name) > static::MAX_LENGTH) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('FormAnalytics_ErrorXTooLong', array($title, static::MAX_LENGTH)));
        }
    }

}