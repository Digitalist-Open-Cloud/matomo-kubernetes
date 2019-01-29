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
use Piwik\Period\Range;
use Piwik\Period\Week;
use Piwik\Period\Year;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidClientConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidCredentialsException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\UnknownAPIException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Google as GoogleModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Metrics;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Version;

class Google
{
    /**
     * @var int site id
     */
    protected $idSite = null;

    /**
     * @var string url, eg http://matomo.org
     */
    protected $searchConsoleUrl = null;

    /**
     * Id if account, to use for querying data
     *
     * @var string
     */
    protected $accountId = null;

    /**
     * Force Data Import
     *
     * @var bool
     */
    protected $force = false;

    /**
     * Search types available for import
     *
     * @var array
     */
    static protected $typesToImport = ['web', 'video', 'image'];

    /**
     * @param int $idSite
     * @param bool $force  force reimport of all data
     */
    public function __construct($idSite, $force = false)
    {
        $this->idSite = $idSite;
        $this->force  = $force;

        $setting          = new MeasurableSettings($idSite);
        $searchConsoleUrl = $setting->googleSearchConsoleUrl;

        list($this->accountId,
            $this->searchConsoleUrl) = explode('##', $searchConsoleUrl->getValue());
    }

    protected static function getRowCountToImport()
    {
        return Config::getInstance()->General['datatable_archiving_maximum_rows_referrers'];
    }

    /**
     * Imports available crawl stats
     *
     * @return bool
     */
    public function importCrawlStats()
    {
        Log::debug("[SearchEngineKeywordsPerformance] Fetching crawl stats for $this->searchConsoleUrl");

        try {
            list($date, $crawlStats) = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google')
                                                      ->getCrawlStats($this->accountId, $this->searchConsoleUrl);
        } catch (InvalidCredentialsException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
            return false;
        } catch (InvalidClientConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
            return false;
        } catch (UnknownAPIException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
            return false;
        }

        if (empty($date) || empty($crawlStats)) {
            return false;
        }

        $model = new GoogleModel();

        $keywordData = $model->getCrawlStatsData($this->searchConsoleUrl, $date);

        if (!empty($keywordData)) {
            return false; // already imported
        }

        $dataTable = self::getCrawlStatsAsDataTable($crawlStats);

        if ($dataTable) {
            $crawlStatsData = $dataTable->getSerialized();
            $model->archiveCrawlStatsData($this->searchConsoleUrl, $date, $crawlStatsData[0]);
            return $date;
        }

        return false;
    }

    /**
     * Imports available crawl stats
     *
     * @return bool
     */
    public function importCrawlErrors()
    {
        $platforms = [
            "mobile",
            "smartphoneOnly",
            "web"
        ];

        $categories = [
            "authPermissions",
            "flashContent",
            "manyToOneRedirect",
            "notFollowed",
            "notFound",
            "other",
            "roboted",
            "serverError",
            "soft404"
        ];

        $dataTable = new DataTable();

        foreach ($platforms as $platform) {
            foreach ($categories as $category) {
                try {
                    Log::debug("[SearchEngineKeywordsPerformance] Fetching crawl errors $category/$platform for $this->searchConsoleUrl");

                    $crawlErrors = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google')
                        ->getCrawlErrors($this->accountId, $this->searchConsoleUrl, $category, $platform);
                } catch (InvalidCredentialsException $e) {
                    Log::info('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                    return false; // skip when invalid credentials
                } catch (InvalidClientConfigException $e) {
                    Log::info('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                    return false; // skip when invalid config
                } catch (UnknownAPIException $e) {
                    Log::info('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                    continue; // ignore backend error as this occurs when no data is available
                } catch (\Exception $e) {
                    Log::error('[SearchEngineKeywordsPerformance] Error while importing Google crawl stats for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                    continue; // ignore any other errors and
                }

                foreach ($crawlErrors as $crawlError) {

                    /** @var \Google_Service_Webmasters_UrlCrawlErrorsSample $crawlError */
                    $urlDetails = $crawlError->getUrlDetails();
                    $inLinks    = $urlDetails ? $urlDetails->getLinkedFromUrls() : [];
                    $inSitemaps = $urlDetails ? $urlDetails->getContainingSitemaps() : [];
                    $rowData = array(
                        DataTable\Row::COLUMNS => array(
                            'label'         => $crawlError->getPageUrl(),
                            'category'      => $category,
                            'platform'      => $platform,
                            'lastCrawled'   => date('Y-m-d H:i:s', strtotime($crawlError->getLastCrawled())),
                            'firstDetected' => date('Y-m-d H:i:s', strtotime($crawlError->getFirstDetected())),
                            'inLinks'       => count($inLinks),
                            'inSitemaps'    => count($inSitemaps),
                            'responseCode'  => $crawlError->getResponseCode(),
                        )
                    );
                    $row     = new DataTable\Row($rowData);
                    $row->setMetadata('links', $inLinks);
                    $row->setMetadata('sitemaps', $inSitemaps);
                    $dataTable->addRow($row);
                }
            }
        }

        $model = new GoogleModel();

        if ($dataTable->getRowsCount()) {
            $crawlErrorsData = $dataTable->getSerialized();
            $model->archiveCrawlErrors($this->searchConsoleUrl, $crawlErrorsData[0]);
            return true;
        }

        return false;
    }

    /**
     * Triggers keyword import and plugin archiving for all dates search console has data for
     *
     * @param string|int|null $limitKeywordDates if integer given: limits the amount of imported dates to the last available X
     *                                           if string given: only imports keywords for the given string date
     * @return null
     */
    public function importAllAvailableData($limitKeywordDates = null)
    {
        // if specific date given
        if (is_string($limitKeywordDates) && strlen($limitKeywordDates) == 10) {
            $availableDates = [$limitKeywordDates];
        } else {
            try {
                $availableDates = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google')
                                                 ->getDatesWithSearchAnalyticsData($this->accountId,
                                                     $this->searchConsoleUrl);
            } catch (InvalidCredentialsException $e) {
                Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                return null;
            } catch (InvalidClientConfigException $e) {
                Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                return null;
            } catch (UnknownAPIException $e) {
                Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                return null;
            } catch (\Exception $e) {
                Log::error('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $this->searchConsoleUrl . ': ' . $e->getMessage());
                return null;
            }

            sort($availableDates);

            if ($limitKeywordDates > 0) {
                $availableDates = array_slice($availableDates, -$limitKeywordDates, $limitKeywordDates);
            }
        }
        $this->importKeywordsForListOfDates($availableDates);

        $crawlStatsDate = $this->importCrawlStats();

        if ($crawlStatsDate) {
            $availableDates[] = $crawlStatsDate;
        }

        $this->importCrawlErrors();

        $this->completeExistingArchivesForListOfDates($availableDates);
    }

    protected function importKeywordsForListOfDates($datesToImport)
    {
        foreach ($datesToImport as $date) {
            foreach (self::$typesToImport as $type) {
                self::importKeywordsIfNecessary(
                    $this->accountId,
                    $this->searchConsoleUrl,
                    $date,
                    $type,
                    $this->force
                );
            }
        }
    }

    protected function completeExistingArchivesForListOfDates($datesToComplete)
    {
        $days = $weeks = $months = $years = [];

        sort($datesToComplete);

        foreach ($datesToComplete as $date) {
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
     * Imports keyword to model storage if not already done
     *
     * @param string $accountId google account id
     * @param string $url       url, eg http://matomo.org
     * @param string $date      date string, eg 2016-12-24
     * @param string $type      'web', 'image' or 'video'
     * @param bool   $force     force reimport
     * @return boolean
     */
    public static function importKeywordsIfNecessary($accountId, $url, $date, $type, $force = false)
    {
        $model = new GoogleModel();

        $keywordData = $model->getKeywordData($url, $date, $type);

        if ($keywordData && !$force) {
            return false; // skip if data already available and no reimport forced
        }

        $dataTable = self::getKeywordsFromConsoleAsDataTable($accountId, $url, $date, $type);

        if ($dataTable) {
            $keywordData = $dataTable->getSerialized(self::getRowCountToImport(), null, Metrics::NB_CLICKS);
            $model->archiveKeywordData($url, $date, $type, $keywordData[0]);
            return true;
        }

        return false;
    }

    /**
     * Fetches data from google search console and migrates it to a Matomo Datatable
     *
     * @param string $accountId google account id
     * @param string $url       url, eg http://matomo.org
     * @param string $date      date string, eg 2016-12-24
     * @param string $type      'web', 'image' or 'video'
     * @return null|DataTable
     */
    protected static function getKeywordsFromConsoleAsDataTable($accountId, $url, $date, $type)
    {
        $dataTable = new DataTable();

        Log::debug("[SearchEngineKeywordsPerformance] Fetching $type keywords for $date and $url");

        try {
            $keywordData = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google')
                                          ->getSearchAnalyticsData($accountId, $url, $date, $type,
                                              self::getRowCountToImport());
        } catch (InvalidCredentialsException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (InvalidClientConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (UnknownAPIException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        }

        if (empty($keywordData) || !($rows = $keywordData->getRows())) {
            return null; // do not create archive as no data is available
        }

        foreach ($rows as $keywordDataSet) {
            /** @var \Google_Service_Webmasters_ApiDataRow $keywordDataSet */
            $keys    = $keywordDataSet->getKeys();
            $rowData = array(
                DataTable\Row::COLUMNS => array(
                    'label'                 => reset($keys),
                    Metrics::NB_CLICKS      => (int)$keywordDataSet->getClicks(),
                    Metrics::NB_IMPRESSIONS => (int)$keywordDataSet->getImpressions(),
                    Metrics::CTR            => (float)$keywordDataSet->getCtr(),
                    Metrics::POSITION       => (float)$keywordDataSet->getPosition(),
                )
            );
            $row     = new DataTable\Row($rowData);
            $dataTable->addRow($row);
        }

        unset($keywordData);

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

    /**
     * Runs the Archiving for SearchEngineKeywordsPerformance plugin if an archive for the given period already exists
     *
     * @param \Piwik\Period $period
     */
    protected function completeExistingArchiveIfAny($period)
    {
        $parameters = new Parameters(new Site($this->idSite), $period, new Segment('', ''));
        $parameters->setRequestedPlugin('SearchEngineKeywordsPerformance');

        $result    = ArchiveSelector::getArchiveIdAndVisits($parameters, $period->getDateStart()->getDateStartUTC());
        $idArchive = $result ? array_shift($result) : null;

        if (empty($idArchive)) {
            return; // ignore periods that weren't archived before
        }

        $archiveWriter            = new ArchiveWriter($parameters, !!$idArchive);
        $archiveWriter->idArchive = $idArchive;

        $archiveProcessor = new ArchiveProcessor($parameters, $archiveWriter,
            new LogAggregator($parameters));

        $archiveProcessor->setNumberOfVisits(1, 1);

        $archiver = new GoogleArchiver($archiveProcessor);

        $this->removeExistingArchiveRecordsIfNecessary($period, $idArchive);

        if ($period instanceof Day) {
            $archiver->aggregateDayReport();
        } else {
            $archiver->aggregateMultipleReports();
        }

        DataTableManager::getInstance()->deleteAll();
    }

    /**
     * Runs the Archiving for SearchEngineKeywordsPerformance plugin if an archive for the given period already exists
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
            GoogleArchiver::KEYWORDS_GOOGLE_IMAGE_RECORD_NAME,
            GoogleArchiver::KEYWORDS_GOOGLE_VIDEO_RECORD_NAME,
            GoogleArchiver::KEYWORDS_GOOGLE_WEB_RECORD_NAME,
        ];

        $dataSetsToRemoveFromNumeric = [
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