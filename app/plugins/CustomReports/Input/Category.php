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

use \Exception;
use Piwik\Common;
use Piwik\Piwik;

class Category
{
    const MAX_LENGTH = 150;

    /**
     * @var string
     */
    private $category;

    public function __construct($categoryId)
    {
        $this->category = $categoryId;
    }

    public function check()
    {
        $title = 'CustomReports_ReportCategory';

        if (empty($this->category)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('CustomReports_ErrorXNotProvided', $title));
        }

        if (Common::mb_strlen($this->category) > static::MAX_LENGTH) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('CustomReports_ErrorXTooLong', array($title, static::MAX_LENGTH)));
        }
    }

}