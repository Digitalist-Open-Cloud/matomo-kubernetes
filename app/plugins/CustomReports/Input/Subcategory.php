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

namespace Piwik\Plugins\CustomReports\Input;

use Exception;
use Piwik\Common;
use Piwik\Piwik;

class Subcategory
{
    const MAX_LENGTH = 150;

    /**
     * @var string
     */
    private $subcategory;

    public function __construct($subcategoryId)
    {
        $this->subcategory = $subcategoryId;
    }

    public function check()
    {
        if (empty($this->subcategory)) {
            return; // may be empty
        }
        $title = 'CustomReports_ReportSubcategory';

        if (!empty($this->subcategory) && Common::mb_strlen($this->subcategory) > static::MAX_LENGTH) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('CustomReports_ErrorXTooLong', array($title, static::MAX_LENGTH)));
        }
    }

}