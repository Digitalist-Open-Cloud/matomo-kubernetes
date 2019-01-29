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

use Piwik\Archive;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Piwik;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Bing as BingArchiver;
use Piwik\Site;

/**
 * The <a href='https://plugins.matomo.org/SearchEngineKeywordsPerformance' target='_blank'>SearchEngineKeywordsPerformance</a> API lets you download all your SEO search keywords from Google, Bing and Yahoo,
 * as well as getting a detailed overview of how search robots crawl your websites and any error they may encounter.
 * <br/><br/>
 * 1) download all your search keywords as they were searched on Google, Bing and Yahoo. This includes Google Images and Google Videos.
 * This lets you view all keywords normally hidden from view behind "keyword not defined". With this plugin you can view them all!
 *<br/><br/>
 * 2) download all crawling overview stats and metrics from Bring and Yahoo and Google. Many metrics are available such as: Crawled pages,
 * Crawl errors, Connection timeouts, HTTP-Status Code 301 (Permanently moved), HTTP-Status Code 400-499 (Request errors), All other HTTP-Status Codes,
 * Total pages in index, Robots.txt exclusion, DNS failures, HTTP-Status Code 200-299, HTTP-Status Code 301 (Temporarily moved),
 * HTTP-Status Code 500-599 (Internal server errors), Malware infected sites, Total inbound links.
 *
 * @package Piwik\Plugins\SearchEngineKeywordsPerformance
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Returns Keyword data used on any search
     *
     * @param string|int|array $idSite  A single ID (eg, `'1'`), multiple IDs (eg, `'1,2,3'` or `array(1, 2, 3)`),
     *                                  or `'all'`.
     * @param string           $period  'day', `'week'`, `'month'`, `'year'` or `'range'`
     * @param Date|string      $date    'YYYY-MM-DD', magic keywords (ie, 'today'; {@link Date::factory()}
     *                                  or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD').
     * @return DataTable|DataTable\Map
     */
    public function getKeywords($idSite, $period, $date)
    {
        $googleWebKwds = $this->getDataTable(GoogleArchiver::KEYWORDS_GOOGLE_WEB_RECORD_NAME, $idSite, $period, $date);
        $googleImgKwds = $this->getDataTable(GoogleArchiver::KEYWORDS_GOOGLE_IMAGE_RECORD_NAME, $idSite, $period, $date);
        $googleVidKwds = $this->getDataTable(GoogleArchiver::KEYWORDS_GOOGLE_VIDEO_RECORD_NAME, $idSite, $period, $date);
        $bingKeywords  = $this->getDataTable(BingArchiver::KEYWORDS_BING_RECORD_NAME, $idSite, $period, $date);

        if ($googleWebKwds instanceof DataTable\Map) {
            $dataTable = new DataTable\Map();

            $dataTables = [];

            foreach ([$googleWebKwds, $googleImgKwds, $googleVidKwds, $bingKeywords] as $dataTableMap) {
                /** @var DataTable\Map $dataTableMap */
                $tables = $dataTableMap->getDataTables();
                foreach ($tables as $label => $table) {

                    if (empty($dataTables[$label])) {
                        $dataTables[$label] = new DataTable();
                        $dataTables[$label]->setAllTableMetadata($table->getAllTableMetadata());
                        $dataTables[$label]->setMetadata(DataTable::COLUMN_AGGREGATION_OPS_METADATA_NAME, Metrics::getColumnsAggregationOperations());
                    }

                    $dataTables[$label]->addDataTable($table);
                }
            }

            foreach ($dataTables as $label => $table) {
                $dataTable->addTable($table, $label);
            }

        } else {
            $dataTable = new DataTable();
            $dataTable->setMetadata(DataTable::COLUMN_AGGREGATION_OPS_METADATA_NAME, Metrics::getColumnsAggregationOperations());
            $dataTable->addDataTable($googleWebKwds);
            $dataTable->addDataTable($googleImgKwds);
            $dataTable->addDataTable($googleVidKwds);
            $dataTable->addDataTable($bingKeywords);
        }

        return $dataTable;
    }

    /**
     * Returns Bing keyword data used on search
     *
     * @param string|int|array $idSite  A single ID (eg, `'1'`), multiple IDs (eg, `'1,2,3'` or `array(1, 2, 3)`),
     *                                  or `'all'`.
     * @param string           $period  'day', `'week'`, `'month'`, `'year'` or `'range'`
     * @param Date|string      $date    'YYYY-MM-DD', magic keywords (ie, 'today'; {@link Date::factory()}
     *                                  or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD').
     * @return DataTable|DataTable\Map
     */
    public function getKeywordsBing($idSite, $period, $date)
    {
        // disabled for date ranges that need days for processing
        if ($period == 'range') {
            $periodObject = new Range($period, $date, Site::getTimezoneFor($idSite));

            $subPeriods = $periodObject->getSubperiods();

            foreach ($subPeriods as $subPeriod) {
                if ($subPeriod->getLabel() == 'day') {
                    return new DataTable();
                }
            }
        }

        // bing statistics are not available for single days
        if ($period == 'day') {
            if (Period::isMultiplePeriod($date, $period)) {
                return new DataTable\Map();
            }
            return new DataTable();
        }

        $dataTable = $this->getDataTable(BingArchiver::KEYWORDS_BING_RECORD_NAME, $idSite, $period, $date);
        return $dataTable;
    }

    /**
     * Returns Google keyword data used on Web search
     *
     * @param string|int|array $idSite  A single ID (eg, `'1'`), multiple IDs (eg, `'1,2,3'` or `array(1, 2, 3)`),
     *                                  or `'all'`.
     * @param string           $period  'day', `'week'`, `'month'`, `'year'` or `'range'`
     * @param Date|string      $date    'YYYY-MM-DD', magic keywords (ie, 'today'; {@link Date::factory()}
     *                                  or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD').
     * @return DataTable|DataTable\Map
     */
    public function getKeywordsGoogleWeb($idSite, $period, $date)
    {
        $dataTable = $this->getDataTable(GoogleArchiver::KEYWORDS_GOOGLE_WEB_RECORD_NAME, $idSite, $period, $date);
        return $dataTable;
    }

    /**
     * Returns Google keyword data used on Image search
     *
     * @param string|int|array $idSite  A single ID (eg, `'1'`), multiple IDs (eg, `'1,2,3'` or `array(1, 2, 3)`),
     *                                  or `'all'`.
     * @param string           $period  'day', `'week'`, `'month'`, `'year'` or `'range'`
     * @param Date|string      $date    'YYYY-MM-DD', magic keywords (ie, 'today'; {@link Date::factory()}
     *                                  or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD').
     * @return DataTable|DataTable\Map
     */
    public function getKeywordsGoogleImage($idSite, $period, $date)
    {
        $dataTable = $this->getDataTable(GoogleArchiver::KEYWORDS_GOOGLE_IMAGE_RECORD_NAME, $idSite, $period, $date);
        return $dataTable;
    }

    /**
     * Returns Google keyword data used on Video search
     *
     * @param string|int|array $idSite  A single ID (eg, `'1'`), multiple IDs (eg, `'1,2,3'` or `array(1, 2, 3)`),
     *                                  or `'all'`.
     * @param string           $period  'day', `'week'`, `'month'`, `'year'` or `'range'`
     * @param Date|string      $date    'YYYY-MM-DD', magic keywords (ie, 'today'; {@link Date::factory()}
     *                                  or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD').
     * @return DataTable|DataTable\Map
     */
    public function getKeywordsGoogleVideo($idSite, $period, $date)
    {
        $dataTable = $this->getDataTable(GoogleArchiver::KEYWORDS_GOOGLE_VIDEO_RECORD_NAME, $idSite, $period, $date);
        return $dataTable;
    }


    public function getCrawlingOverviewBing($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $archive = Archive::build($idSite, $period, $date);

        $dataTable = $archive->getDataTableFromNumeric([
            BingArchiver::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME,
            BingArchiver::CRAWLSTATS_IN_INDEX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_IN_LINKS_RECORD_NAME,
            BingArchiver::CRAWLSTATS_MALWARE_RECORD_NAME,
            BingArchiver::CRAWLSTATS_BLOCKED_ROBOTS_RECORD_NAME,
            BingArchiver::CRAWLSTATS_ERRORS_RECORD_NAME,
            BingArchiver::CRAWLSTATS_DNS_FAILURE_RECORD_NAME,
            BingArchiver::CRAWLSTATS_TIMEOUT_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_2XX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_301_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_302_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_4XX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_5XX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_OTHER_CODES_RECORD_NAME,
        ]);

        return $dataTable;
    }

    public function getCrawlingErrorsGoogle($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $archive = Archive::build($idSite, $period, $date);

        $dataTable = $archive->getDataTableFromNumeric([
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOUND,
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOLLOWED,
            GoogleArchiver::CRAWLERRORS_WEB_AUTH_PERMISSION,
            GoogleArchiver::CRAWLERRORS_WEB_SERVER_ERROR,
            GoogleArchiver::CRAWLERRORS_WEB_SOFT404,
            GoogleArchiver::CRAWLERRORS_WEB_OTHER_ERROR,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOUND,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOLLOWED,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_AUTH_PERMISSION,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SERVER_ERROR,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SOFT404,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_ROBOTED,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_MANY_TO_ONE_REDIRECT,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_FLASH_CONTENT,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_OTHER_ERROR,
        ]);

        return $dataTable;
    }

    public function getCrawlingErrorsGoogleSmartphone($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $archive = Archive::build($idSite, $period, $date);

        $dataTable = $archive->getDataTableFromNumeric([
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOUND,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOLLOWED,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_AUTH_PERMISSION,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SERVER_ERROR,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SOFT404,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_ROBOTED,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_MANY_TO_ONE_REDIRECT,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_FLASH_CONTENT,
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_OTHER_ERROR,
        ]);

        return $dataTable;
    }

    public function getCrawlingErrorsGoogleDesktop($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $archive = Archive::build($idSite, $period, $date);

        $dataTable = $archive->getDataTableFromNumeric([
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOUND,
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOLLOWED,
            GoogleArchiver::CRAWLERRORS_WEB_AUTH_PERMISSION,
            GoogleArchiver::CRAWLERRORS_WEB_SERVER_ERROR,
            GoogleArchiver::CRAWLERRORS_WEB_SOFT404,
            GoogleArchiver::CRAWLERRORS_WEB_OTHER_ERROR,
        ]);

        return $dataTable;
    }

    /**
     * Returns list of pages that had an error while crawling for Google
     *
     * Note: This methods returns data imported lately. It does not support any historical reports
     *
     * @param $idSite
     *
     * @return DataTable
     */
    public function getCrawlingErrorExamplesGoogle($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $dataTable = new DataTable();
        $settings = new MeasurableSettings($idSite);
        $searchConsoleSetting = $settings->googleSearchConsoleUrl->getValue();

        if (empty($searchConsoleSetting)) {
            return $dataTable;
        }

        list($accountId, $searchConsoleUrl) = explode('##', $searchConsoleSetting);

        $model = new Model\Google();

        $data = $model->getCrawlErrors($searchConsoleUrl);

        if (empty($data)) {
            return $dataTable;
        }

        $dataTable->addRowsFromSerializedArray($data);

        $dataTable->filter(
            'ColumnCallbackAddMetadata',
            array(
                'label',
                'url',
                function ($url) use ($searchConsoleUrl) {
                    return $searchConsoleUrl . $url;
                }
            )
        );

        return $dataTable;
    }

    /**
     * Returns list of pages that had an error while crawling for Bing
     *
     * Note: This methods returns data imported lately. It does not support any historical reports
     *
     * @param $idSite
     *
     * @return DataTable
     */
    public function getCrawlingErrorExamplesBing($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $dataTable = new DataTable();
        $settings = new MeasurableSettings($idSite);
        $searchConsoleSetting = $settings->bingSiteUrl->getValue();

        if (empty($searchConsoleSetting)) {
            return $dataTable;
        }

        list($apiKey, $searchConsoleUrl) = explode('##', $searchConsoleSetting);

        $model = new Model\Bing();

        $data = $model->getCrawlErrors($searchConsoleUrl);

        if (empty($data)) {
            return $dataTable;
        }

        $dataTable->addRowsFromSerializedArray($data);

        $dataTable->filter(
            'ColumnCallbackAddMetadata',
            array( 'label', 'url' )
        );

        $dataTable->filter(
            'ColumnCallbackReplace',
            array( 'label', function ($val) use ($searchConsoleUrl) {
                return preg_replace('|https?://[^/]*/|i', '', $val);
            } )
        );

        return $dataTable;
    }

    /**
     * Returns datatable for the requested archive
     *
     * @param string           $name    name of the archive to use
     * @param string|int|array $idSite  A single ID (eg, `'1'`), multiple IDs (eg, `'1,2,3'` or `array(1, 2, 3)`),
     *                                  or `'all'`.
     * @param string           $period  'day', `'week'`, `'month'`, `'year'` or `'range'`
     * @param Date|string      $date    'YYYY-MM-DD', magic keywords (ie, 'today'; {@link Date::factory()}
     *                                  or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD').
     * @return DataTable|DataTable\Map
     */
    private function getDataTable($name, $idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $archive   = Archive::build($idSite, $period, $date, $segment = false);
        $dataTable = $archive->getDataTable($name);
        $dataTable->queueFilter('ReplaceSummaryRowLabel');
        return $dataTable;
    }
}
