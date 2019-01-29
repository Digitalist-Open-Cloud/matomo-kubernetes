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
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\SearchEngineKeywordsPerformance;

use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Bing as BingArchiver;

/**
 * Archiver for SearchEngineKeywordsPerformance Plugin
 *
 * @see PluginsArchiver
 */
class Archiver extends \Piwik\Plugin\Archiver
{
    /**
     * Force archiver to run even if there were no visits in the archived period
     *
     * @return bool
     */
    public static function shouldRunEvenWhenNoVisits()
    {
        return true;
    }

    /**
     * Aggregate data for day reports
     */
    public function aggregateDayReport()
    {
        $googleArchiver = new GoogleArchiver($this->getProcessor());
        $googleArchiver->aggregateDayReport();
        $bingArchiver = new BingArchiver($this->getProcessor());
        $bingArchiver->aggregateDayReport();
    }

    /**
     * Period archiving: combine daily archives
     */
    public function aggregateMultipleReports()
    {
        $googleArchiver = new GoogleArchiver($this->getProcessor());
        $googleArchiver->aggregateMultipleReports();
        $bingArchiver = new BingArchiver($this->getProcessor());
        $bingArchiver->aggregateMultipleReports();
    }
}

