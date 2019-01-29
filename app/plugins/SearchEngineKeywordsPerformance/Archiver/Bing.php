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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver;

use Piwik\ArchiveProcessor;
use Piwik\Config as PiwikConfig;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Metrics;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Bing as BingModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Importer\Bing as BingImporter;
use Piwik\DataTable;
use Piwik\Log;

/**
 * Archiver for Google Keywords
 *
 * @see PluginsArchiver
 */
class Bing extends \Piwik\Plugin\Archiver
{
    /**
     * Key used for archives
     */
    const KEYWORDS_BING_RECORD_NAME = 'SearchEngineKeywordsPerformance_bing_keywords';

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->maximumRows = PiwikConfig::getInstance()->General['datatable_archiving_maximum_rows_referrers'];
    }

    /**
     * Keys used for crawl stats archives / metrics
     */
    const CRAWLSTATS_OTHER_CODES_RECORD_NAME    = 'SearchEngineKeywordsPerformance_bing_crawlstats_other_codes';
    const CRAWLSTATS_BLOCKED_ROBOTS_RECORD_NAME = 'SearchEngineKeywordsPerformance_bing_crawlstats_blocked_robots';
    const CRAWLSTATS_CODE_2XX_RECORD_NAME       = 'SearchEngineKeywordsPerformance_bing_crawlstats_code_2xx';
    const CRAWLSTATS_CODE_301_RECORD_NAME       = 'SearchEngineKeywordsPerformance_bing_crawlstats_code_301';
    const CRAWLSTATS_CODE_302_RECORD_NAME       = 'SearchEngineKeywordsPerformance_bing_crawlstats_code_303';
    const CRAWLSTATS_CODE_4XX_RECORD_NAME       = 'SearchEngineKeywordsPerformance_bing_crawlstats_code_4xx';
    const CRAWLSTATS_CODE_5XX_RECORD_NAME       = 'SearchEngineKeywordsPerformance_bing_crawlstats_code_5xx';
    const CRAWLSTATS_TIMEOUT_RECORD_NAME        = 'SearchEngineKeywordsPerformance_bing_crawlstats_timeout';
    const CRAWLSTATS_MALWARE_RECORD_NAME        = 'SearchEngineKeywordsPerformance_bing_crawlstats_malware';
    const CRAWLSTATS_ERRORS_RECORD_NAME         = 'SearchEngineKeywordsPerformance_bing_crawlstats_errors';
    const CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME  = 'SearchEngineKeywordsPerformance_bing_crawlstats_crawledpages';
    const CRAWLSTATS_DNS_FAILURE_RECORD_NAME    = 'SearchEngineKeywordsPerformance_bing_crawlstats_dnsfail';
    const CRAWLSTATS_IN_INDEX_RECORD_NAME       = 'SearchEngineKeywordsPerformance_bing_crawlstats_inindex';
    const CRAWLSTATS_IN_LINKS_RECORD_NAME       = 'SearchEngineKeywordsPerformance_bing_crawlstats_inlinks';

    /**
     * Aggregate data for day reports
     */
    public function aggregateDayReport()
    {
        $parameters = $this->getProcessor()->getParams();
        $site       = $parameters->getSite();
        $date       = $parameters->getDateStart()->setTimezone('UTC')->toString('Y-m-d');

        $setting     = new MeasurableSettings($site->getId(), $site->getType());
        $bingSiteUrl = $setting->bingSiteUrl;

        if (empty($bingSiteUrl) || !$bingSiteUrl->getValue()) {
            return; // bing api not activated for that site
        }

        list($apiKey, $url) = explode('##', $bingSiteUrl->getValue());

        Log::debug("[SearchEngineKeywordsPerformance] Archiving bing records for $date and $url");

        $dataTable = $this->getKeywordsAsDataTable($url, $date);

        if (empty($dataTable)) {
            // ensure data is present (if available)
            BingImporter::importAvailablePeriods($apiKey, $url);
            $dataTable = $this->getKeywordsAsDataTable($url, $date);
        }

        if (!empty($dataTable)) {
            Log::debug("[SearchEngineKeywordsPerformance] Archiving bing keywords for $date and $url");

            $report = $dataTable->getSerialized($this->maximumRows, null, Metrics::NB_CLICKS);
            unset($dataTable);
            $this->getProcessor()->insertBlobRecord(self::KEYWORDS_BING_RECORD_NAME, $report);
        }

        $this->archiveDayCrawlStatNumerics($url, $date);
    }

    /**
     * Inserts various numeric records for crawl stats
     *
     * @param $url
     * @param $date
     */
    protected function archiveDayCrawlStatNumerics($url, $date)
    {
        $dataTable = $this->getCrawlStatsAsDataTable($url, $date);

        if (!empty($dataTable)) {

            Log::debug("[SearchEngineKeywordsPerformance] Archiving bing crawl stats for $date and $url");

            $getValue = function ($label) use ($dataTable) {
                $row = $dataTable->getRowFromLabel($label);
                if ($row) {
                    return (int)$row->getColumn(Metrics::NB_PAGES);
                }

                return 0;
            };

            $numericRecords = [
                self::CRAWLSTATS_OTHER_CODES_RECORD_NAME    => $getValue('AllOtherCodes'),
                self::CRAWLSTATS_BLOCKED_ROBOTS_RECORD_NAME => $getValue('BlockedByRobotsTxt'),
                self::CRAWLSTATS_CODE_2XX_RECORD_NAME       => $getValue('Code2xx'),
                self::CRAWLSTATS_CODE_301_RECORD_NAME       => $getValue('Code301'),
                self::CRAWLSTATS_CODE_302_RECORD_NAME       => $getValue('Code302'),
                self::CRAWLSTATS_CODE_4XX_RECORD_NAME       => $getValue('Code4xx'),
                self::CRAWLSTATS_CODE_5XX_RECORD_NAME       => $getValue('Code5xx'),
                self::CRAWLSTATS_TIMEOUT_RECORD_NAME        => $getValue('ConnectionTimeout'),
                self::CRAWLSTATS_MALWARE_RECORD_NAME        => $getValue('ContainsMalware'),
                self::CRAWLSTATS_ERRORS_RECORD_NAME         => $getValue('CrawlErrors'),
                self::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME  => $getValue('CrawledPages'),
                self::CRAWLSTATS_DNS_FAILURE_RECORD_NAME    => $getValue('DnsFailures'),
                self::CRAWLSTATS_IN_INDEX_RECORD_NAME       => $getValue('InIndex'),
                self::CRAWLSTATS_IN_LINKS_RECORD_NAME       => $getValue('InLinks'),
            ];

            unset($dataTable);

            $this->getProcessor()->insertNumericRecords($numericRecords);
        }
    }

    /**
     * Returns keyword data for given parameters as DataTable
     *
     * @param string $url  url, eg http://matomo.org
     * @param string $date date string, eg 2016-12-24
     * @return null|DataTable
     */
    protected function getKeywordsAsDataTable($url, $date)
    {
        $model       = new BingModel();
        $keywordData = $model->getKeywordData($url, $date);

        if (!empty($keywordData)) {
            $dataTable = new DataTable();
            $dataTable->addRowsFromSerializedArray($keywordData);
            return $dataTable;
        }

        return null;
    }

    /**
     * Returns crawl stats for given parameters as DataTable
     *
     * @param string $url  url, eg http://matomo.org
     * @param string $date date string, eg 2016-12-24
     * @return null|DataTable
     */
    protected function getCrawlStatsAsDataTable($url, $date)
    {
        $model       = new BingModel();
        $keywordData = $model->getCrawlStatsData($url, $date);

        if (!empty($keywordData)) {
            $dataTable = new DataTable();
            $dataTable->addRowsFromSerializedArray($keywordData);
            return $dataTable;
        }

        return null;
    }

    /**
     * Period archiving: combine daily archives
     */
    public function aggregateMultipleReports()
    {
        $parameters = $this->getProcessor()->getParams();
        $site       = $parameters->getSite();

        $setting     = new MeasurableSettings($site->getId(), $site->getType());
        $bingSiteUrl = $setting->bingSiteUrl;

        if (empty($bingSiteUrl) || !$bingSiteUrl->getValue()) {
            return; // bing api not activated for that site
        }

        Log::debug("[SearchEngineKeywordsPerformance] Archiving bing records for " . $this->getProcessor()->getParams()->getPeriod()->getRangeString());

        $aggregationOperations = Metrics::getColumnsAggregationOperations();

        $this->getProcessor()->aggregateDataTableRecords(
            [self::KEYWORDS_BING_RECORD_NAME],
            $this->maximumRows,
            $maximumRowsInSubDataTable = null,
            $columnToSortByBeforeTruncation = Metrics::NB_CLICKS,
            $aggregationOperations,
            $columnsToRenameAfterAggregation = null,
            $countRowsRecursive = array()
        );

        $this->getProcessor()->aggregateNumericMetrics([
            self::CRAWLSTATS_OTHER_CODES_RECORD_NAME,
            self::CRAWLSTATS_BLOCKED_ROBOTS_RECORD_NAME,
            self::CRAWLSTATS_CODE_2XX_RECORD_NAME,
            self::CRAWLSTATS_CODE_301_RECORD_NAME,
            self::CRAWLSTATS_CODE_302_RECORD_NAME,
            self::CRAWLSTATS_CODE_4XX_RECORD_NAME,
            self::CRAWLSTATS_CODE_5XX_RECORD_NAME,
            self::CRAWLSTATS_TIMEOUT_RECORD_NAME,
            self::CRAWLSTATS_MALWARE_RECORD_NAME,
            self::CRAWLSTATS_ERRORS_RECORD_NAME,
            self::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME,
            self::CRAWLSTATS_DNS_FAILURE_RECORD_NAME,
            self::CRAWLSTATS_IN_INDEX_RECORD_NAME,
            self::CRAWLSTATS_IN_LINKS_RECORD_NAME,
        ], 'max');
    }
}

