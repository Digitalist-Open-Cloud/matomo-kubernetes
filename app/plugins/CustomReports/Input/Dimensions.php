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
use Piwik\Container\StaticContainer;
use Piwik\Piwik;

class Dimensions
{
    /**
     * @var array
     */
    private $dimensions;

    /**
     * @var int|string
     */
    private $idSite;

    public function __construct($dimensions, $idSite)
    {
        $this->dimensions = $dimensions;
        $this->idSite = $idSite;
    }

    public function check()
    {
        if (empty($this->dimensions)) {
            return; // it may be empty
        }

        $title = Piwik::translate('CustomReports_Dimensions');

        if (!is_array($this->dimensions)) {
            throw new Exception(Piwik::translate('CustomReports_ErrorNotAnArray', $title));
        }

        $validateDimensionsExist = true;
        if (empty($this->idSite) || $this->idSite === 'all') {
            $configuration = StaticContainer::get('Piwik\Plugins\CustomReports\Configuration');
            $validateDimensionsExist = $configuration->shouldValidateReportContentWhenAllSites();
        }

        $dimensionProvider = StaticContainer::get('Piwik\Columns\DimensionsProvider');
        foreach ($this->dimensions as $index => $dimension) {
            if (empty($dimension)) {
                throw new Exception(Piwik::translate('CustomReports_ErrorArrayMissingItem', array($title, $index)));
            }

            if ($validateDimensionsExist && !$dimensionProvider->factory($dimension)) {
                throw new Exception(Piwik::translate('CustomReports_ErrorInvalidValueInArray', array($title, $index)));
            }
        }

        if (count($this->dimensions) !== count(array_unique($this->dimensions))) {
            $title = Piwik::translate('CustomReports_Dimension');
            throw new Exception(Piwik::translate('CustomReports_ErrorDuplicateItem', $title));
        }
    }

}