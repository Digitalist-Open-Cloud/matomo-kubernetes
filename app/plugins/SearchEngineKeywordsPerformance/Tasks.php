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

use Piwik\Option;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Importer\Google as GoogleImporter;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Importer\Bing as BingImporter;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

class Tasks extends \Piwik\Plugin\Tasks
{
    /**
     * Schedules task to run daily for each website that has configured imports
     */
    public function schedule()
    {
        $siteIds = SitesManagerAPI::getInstance()->getAllSitesId();

        foreach ($siteIds as $idSite) {
            $setting          = new MeasurableSettings($idSite);
            $searchConsoleUrl = $setting->googleSearchConsoleUrl;
            if ($searchConsoleUrl && $searchConsoleUrl->getValue()) {
                $this->daily('runImportsGoogle', $idSite);
            }
            $bingSiteUrl = $setting->bingSiteUrl;
            if ($bingSiteUrl && $bingSiteUrl->getValue()) {
                $this->daily('runImportsBing', $idSite);
            }
        }
    }

    /**
     * Run Google importer for the last X available dates
     * To calculate the amount of imported days a timestamp of the last run will be saved
     * and checked how many days it was ago. This ensures dates will be imported even if
     * the tasks doesn't run some days. And it also ensure that all available dates will be
     * imported on the first run, as no last run has been saved before
     *
     * @param int $idSite
     */
    public function runImportsGoogle($idSite)
    {
        $lastRun = Option::get('GoogleImporterTask_LastRun_' . $idSite);
        $now     = time();

        $limitDays = 0;
        if ($lastRun) {
            $difference = $now - $lastRun;
            $limitDays  = ceil($difference / (3600 * 24));
        }

        $importer = new GoogleImporter($idSite);
        $importer->importAllAvailableData($limitDays);

        Option::set('GoogleImporterTask_LastRun_' . $idSite, $now);
    }

    /**
     * Run Bing importer
     *
     * @param int $idSite
     */
    public function runImportsBing($idSite)
    {
        $importer = new BingImporter($idSite);
        $importer->importAllAvailableData();

        Option::set('BingImporterTask_LastRun_' . $idSite, time());
    }
}