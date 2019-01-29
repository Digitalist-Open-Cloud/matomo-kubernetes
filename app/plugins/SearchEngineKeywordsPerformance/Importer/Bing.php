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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Importer;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Parameters;
use Piwik\Container\StaticContainer;
use Piwik\Config;
use Piwik\DataAccess\ArchiveSelector;
use Piwik\DataAccess\ArchiveTableCreator;
use Piwik\DataAccess\ArchiveWriter;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\DataTable\Manager AS DataTableManager;
use Piwik\Date;
use Piwik\Db;
use Piwik\Log;
use Piwik\Period\Day;
use Piwik\Period\Month;
use Piwik\Period\Week;
use Piwik\Period\Year;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidCredentialsException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\UnknownAPIException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Bing as BingModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Bing AS BingArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Metrics;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Version;

class Bing
{
    /**
     * Site id
     *
     * @var int
     */
    protected $idSite = null;

    /**
     * Site url to query data for, eg http://matomo.org
     *
     * @var string
     */
    protected $bingSiteUrl = null;

    /**
     * API-Key used for Bing-API
     *
     * @var string
     */
    protected $apiKey = null;

    /**
     * Force Data Import
     *
     * @var bool
     */
    protected $force = false;

    /**
     * Indicates if importer already ran
     *
     * @var boolean
     */
    static public $dataImported = false;

    /**
     * @param int $idSite
     * @param bool $force  force reimport of all data
     */
    public function __construct($idSite, $force = false)
    {
        $this->idSite = $idSite;
        $this->force  = $force;

        $setting          = new MeasurableSettings($idSite);
        $searchConsoleUrl = $setting->bingSiteUrl;
        $siteConfig       = $searchConsoleUrl->getValue();
        list($this->apiKey, $this->bingSiteUrl) = explode('##', $siteConfig);
    }

    protected static function getRowCountToImport()
    {
        return Config::getInstance()->General['datatable_archiving_maximum_rows_referrers'];
    }

    /**
     * Run importer for all available data
     */
    public function importAllAvailableData()
    {
        $dates = self::importAvailablePeriods($this->apiKey, $this->bingSiteUrl, $this->force);

        if (empty($dates)) {
            return;
        }

        $days = $weeks = $months = $years = [];

        foreach ($dates as $date) {
            $date                             = Date::factory($date);
            $day                              = new Day($date);
            $days[$day->toString()]           = $day;
            $week                             = new Week($date);
            $weeks[$week->getRangeString()]   = $week;
            $month                            = new Month($date);
            $months[$month->getRangeString()] = $month;
            $year                             = new Year($date);
            $years[$year->getRangeString()]   = $year;
        }

        $periods = $days + $weeks + $months + $years;

        foreach ($periods as $period) {
            $this->completeExistingArchiveIfAny($period);
        }
    }

    /**
     * Imports available data to model storage if not already done
     *
     * @param string $apiKey API key to use
     * @param string $url    url, eg http://matomo.org
     * @return array
     */
    public static function importAvailablePeriods($apiKey, $url, $force = false)
    {
        if (self::$dataImported) {
            return [];
        }

        $datesImported = [];

        $model = new BingModel();
        Log::debug("[SearchEngineKeywordsPerformance] Fetching Bing keywords for $url");

        try {
            $keywordData = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Bing')
                                          ->getSearchAnalyticsData($apiKey, $url);

            foreach ($keywordData as $date => $keywords) {
                $availableKeywords = $model->getKeywordData($url, $date);

                $datesImported[] = $date;

                if (!empty($availableKeywords) && !$force) {
                    continue; // skip as data was already imported before
                }

                $dataTable = self::getKeywordsAsDataTable($keywords);

                if ($dataTable) {
                    $keywordData = $dataTable->getSerialized(self::getRowCountToImport(), null, Metrics::NB_CLICKS);

                    Log::debug("[SearchEngineKeywordsPerformance] Importing Bing keywords for $url / $date");

                    $model->archiveKeywordData($url, $date, $keywordData[0]);
                }
            }
        } catch (InvalidCredentialsException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Bing keywords for ' . $url . ': ' . $e->getMessage());
        } catch (UnknownAPIException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Bing keywords for ' . $url . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('[SearchEngineKeywordsPerformance] Error while importing Bing keywords for ' . $url . ': ' . $e->getMessage());
        }

        Log::debug("[SearchEngineKeywordsPerformance] Fetching Bing crawl stats for $url");

        try {
            $crawlStatsDataSets = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Bing')
                                                 ->getCrawlStats($apiKey, $url);

            foreach ($crawlStatsDataSets as $date => $crawlStats) {
                $availableCrawlStats = $model->getCrawlStatsData($url, $date);

                $datesImported[] = $date;

                if (!empty($availableCrawlStats)) {
                    continue; // skip as data was already imported before
                }

                $dataTable = self::getCrawlStatsAsDataTable($crawlStats);

                if ($dataTable) {
                    $keywordData = $dataTable->getSerialized();

                    Log::debug("[SearchEngineKeywordsPerformance] Importing Bing crawl stats for $url / $date");

                    $model->archiveCrawlStatsData($url, $date, $keywordData[0]);
                }
            }
        } catch (InvalidCredentialsException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Bing crawl stats for ' . $url . ': ' . $e->getMessage());
        } catch (UnknownAPIException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Bing crawl stats for ' . $url . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('[SearchEngineKeywordsPerformance] Error while importing Bing crawl stats for ' . $url . ': ' . $e->getMessage());
        }

        try {
            $crawlErrorsDataSets = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Bing')
                                                 ->getUrlWithCrawlIssues($apiKey, $url);

            Log::debug("[SearchEngineKeywordsPerformance] Importing Bing crawl errors for $url");

            $dataTable = self::getCrawlErrorsAsDataTable($crawlErrorsDataSets);

            if ($dataTable->getRowsCount()) {
                $crawlErrorsData = $dataTable->getSerialized();
                $model->archiveCrawlErrors($url, $crawlErrorsData[0]);
            }
        } catch (InvalidCredentialsException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Bing crawl errors for ' . $url . ': ' . $e->getMessage());
        } catch (UnknownAPIException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Bing crawl errors for ' . $url . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('[SearchEngineKeywordsPerformance] Error while importing Bing crawl errors for ' . $url . ': ' . $e->getMessage());
        }

        $datesImported = array_unique($datesImported);
        sort($datesImported);

        self::$dataImported = true;

        return $datesImported;
    }

    protected static function getKeywordsAsDataTable($keywords)
    {
        $dataTable = new DataTable();
        foreach ($keywords as $keywordDataSet) {
            $rowData = [
                DataTable\Row::COLUMNS => [
                    'label'                 => $keywordDataSet['keyword'],
                    Metrics::NB_CLICKS      => (int)$keywordDataSet['clicks'],
                    Metrics::NB_IMPRESSIONS => (int)$keywordDataSet['impressions'],
                    Metrics::CTR            => (float)round($keywordDataSet['clicks'] / $keywordDataSet['impressions'],
                        2),
                    Metrics::POSITION       => (float)$keywordDataSet['position'],
                ]
            ];
            $row     = new DataTable\Row($rowData);
            $dataTable->addRow($row);
        }

        return $dataTable;
    }

    protected static function getCrawlStatsAsDataTable($crawlStats)
    {
        $dataTable = new DataTable();

        foreach ($crawlStats as $label => $pagesCount) {
            $rowData = [
                DataTable\Row::COLUMNS => [
                    'label'           => $label,
                    Metrics::NB_PAGES => (int)$pagesCount
                ]
            ];
            $row     = new DataTable\Row($rowData);
            $dataTable->addRow($row);
        }

        return $dataTable;
    }

    protected static function getCrawlErrorsAsDataTable($crawlErrors)
    {
        $dataTable = new DataTable();

        foreach ($crawlErrors as $crawlError) {
            $rowData = [
                DataTable\Row::COLUMNS => [
                    'label'        => $crawlError['Url'],
                    'category'     => $crawlError['Issues'],
                    'inLinks'      => $crawlError['InLinks'],
                    'responseCode' => $crawlError['HttpCode'],
                ]
            ];
            $row     = new DataTable\Row($rowData);
            $dataTable->addRow($row);
        }

        return $dataTable;
    }

    /**
     * Runs the Archiving for SearchEngineKeywordsPerformance plugin if an archive for the given period already exists
     *
     * @param \Piwik\Period $period
     *
     * @return boolean
     */
    protected function completeExistingArchiveIfAny($period)
    {
        $parameters = new Parameters(new Site($this->idSite), $period, new Segment('', ''));
        $parameters->setRequestedPlugin('SearchEngineKeywordsPerformance');

        $result    = ArchiveSelector::getArchiveIdAndVisits($parameters, $period->getDateStart()->getDateStartUTC());
        $idArchive = $result ? array_shift($result) : null;

        if (empty($idArchive)) {
            return false; // ignore periods that weren't archived before
        }

        $archiveWriter            = new ArchiveWriter($parameters, !!$idArchive);
        $archiveWriter->idArchive = $idArchive;

        $archiveProcessor = new ArchiveProcessor($parameters, $archiveWriter,
            new LogAggregator($parameters));

        $archiveProcessor->setNumberOfVisits(1, 1);

        $archiver = new BingArchiver($archiveProcessor);

        $this->removeExistingArchiveRecordsIfNecessary($period, $idArchive);

        if ($period instanceof Day) {
            $archiver->aggregateDayReport();
        } else {
            $archiver->aggregateMultipleReports();
        }

        DataTableManager::getInstance()->deleteAll();

        return true;
    }

    /**
     * Removes old archives records, to be sure new ones can be inserted
     *
     * @param \Piwik\Period $period
     */
    protected function removeExistingArchiveRecordsIfNecessary($period, $idArchive)
    {
        if (version_compare(Version::VERSION, '3.0.3', '>=')) {
            return; // skip for newer Matomo versions, as records are replaced there automatically
        }

        $blobTable    = ArchiveTableCreator::getBlobTable($period->getDateStart());
        $numericTable = ArchiveTableCreator::getNumericTable($period->getDateStart());

        $sql = 'DELETE FROM %1$s WHERE idarchive = ? AND `name` = \'%2$s\' AND idsite = ? AND date1= ? AND date2 = ? AND period = ?';

        $queryParams = [
            $idArchive,
            $this->idSite,
            $period->getDateStart()->toString('Y-m-d'),
            $period->getDateEnd()->toString('Y-m-d'),
            $period->getId(),
        ];

        $dataSetsToRemoveFromBlob = [
            BingArchiver::KEYWORDS_BING_RECORD_NAME
        ];

        $dataSetsToRemoveFromNumeric = [
            BingArchiver::CRAWLSTATS_BLOCKED_ROBOTS_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_2XX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_301_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_302_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_4XX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CODE_5XX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_TIMEOUT_RECORD_NAME,
            BingArchiver::CRAWLSTATS_MALWARE_RECORD_NAME,
            BingArchiver::CRAWLSTATS_ERRORS_RECORD_NAME,
            BingArchiver::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME,
            BingArchiver::CRAWLSTATS_DNS_FAILURE_RECORD_NAME,
            BingArchiver::CRAWLSTATS_IN_INDEX_RECORD_NAME,
            BingArchiver::CRAWLSTATS_IN_LINKS_RECORD_NAME,
        ];

        foreach ($dataSetsToRemoveFromBlob AS $archiveName) {
            $query = sprintf($sql, $blobTable, $archiveName);
            Db::query($query, $queryParams);
        }

        foreach ($dataSetsToRemoveFromNumeric AS $archiveName) {
            $query = sprintf($sql, $numericTable, $archiveName);
            Db::query($query, $queryParams);
        }
    }
}