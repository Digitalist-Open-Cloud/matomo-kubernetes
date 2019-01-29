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

use Piwik\Piwik;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Bing as BingArchiver;

/**
 * Defines Metrics used in SearchEngineKeywordsPerformance plugin
 */
class Metrics
{
    const NB_CLICKS      = 'nb_clicks';
    const NB_IMPRESSIONS = 'nb_impressions';
    const CTR            = 'ctr';
    const POSITION       = 'position';
    const NB_PAGES       = 'nb_pages';

    /**
     * Returns list of available keyword metrics
     *
     * @return array
     */
    public static function getKeywordMetrics()
    {
        return array(
            self::NB_CLICKS,
            self::NB_IMPRESSIONS,
            self::CTR,
            self::POSITION,
        );
    }

    /**
     * Returns metric translations
     *
     * @return array
     */
    public static function getMetricsTranslations()
    {
        return array(
            self::NB_CLICKS      => Piwik::translate('SearchEngineKeywordsPerformance_Clicks'),
            self::NB_IMPRESSIONS => Piwik::translate('SearchEngineKeywordsPerformance_Impressions'),
            self::CTR            => Piwik::translate('SearchEngineKeywordsPerformance_Ctr'),
            self::POSITION       => Piwik::translate('SearchEngineKeywordsPerformance_Position'),
        );
    }

    /**
     * Return metric documentations
     *
     * @return array
     */
    public static function getMetricsDocumentation()
    {
        return array(
            self::NB_CLICKS      => Piwik::translate('SearchEngineKeywordsPerformance_ClicksDocumentation'),
            self::NB_IMPRESSIONS => Piwik::translate('SearchEngineKeywordsPerformance_ImpressionsDocumentation'),
            self::CTR            => Piwik::translate('SearchEngineKeywordsPerformance_CtrDocumentation'),
            self::POSITION       => Piwik::translate('SearchEngineKeywordsPerformance_PositionDocumentation'),

            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOUND                       => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFoundDesc'),
            GoogleArchiver::CRAWLERRORS_WEB_NOT_FOLLOWED                    => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFollowedDesc'),
            GoogleArchiver::CRAWLERRORS_WEB_AUTH_PERMISSION                 => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlAuthPermissionDesc'),
            GoogleArchiver::CRAWLERRORS_WEB_SERVER_ERROR                    => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlServerErrorDesc'),
            GoogleArchiver::CRAWLERRORS_WEB_SOFT404                         => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlSoft404Desc'),
            GoogleArchiver::CRAWLERRORS_WEB_OTHER_ERROR                     => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlOtherErrorDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOUND            => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFoundDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_NOT_FOLLOWED         => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlNotFollowedDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_AUTH_PERMISSION      => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlAuthPermissionDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SERVER_ERROR         => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlServerErrorDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_SOFT404              => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlSoft404Desc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_ROBOTED              => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlRobotedDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_MANY_TO_ONE_REDIRECT => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlManyRedirectDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_FLASH_CONTENT        => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlFlashDesc'),
            GoogleArchiver::CRAWLERRORS_SMARTPHONEONLY_OTHER_ERROR          => Piwik::translate('SearchEngineKeywordsPerformance_GoogleCrawlOtherErrorDesc'),

            BingArchiver::CRAWLSTATS_OTHER_CODES_RECORD_NAME    => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlStatsOtherCodesDesc'),
            BingArchiver::CRAWLSTATS_BLOCKED_ROBOTS_RECORD_NAME => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlBlockedByRobotsTxtDesc'),
            BingArchiver::CRAWLSTATS_CODE_2XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus2xxDesc'),
            BingArchiver::CRAWLSTATS_CODE_301_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus301Desc'),
            BingArchiver::CRAWLSTATS_CODE_302_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus302Desc'),
            BingArchiver::CRAWLSTATS_CODE_4XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus4xxDesc'),
            BingArchiver::CRAWLSTATS_CODE_5XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus5xxDesc'),
            BingArchiver::CRAWLSTATS_TIMEOUT_RECORD_NAME        => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlConnectionTimeoutDesc'),
            BingArchiver::CRAWLSTATS_MALWARE_RECORD_NAME        => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlMalwareInfectedDesc'),
            BingArchiver::CRAWLSTATS_ERRORS_RECORD_NAME         => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlErrorsDesc'),
            BingArchiver::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME  => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlCrawledPagesDesc'),
            BingArchiver::CRAWLSTATS_DNS_FAILURE_RECORD_NAME    => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlDNSFailuresDesc'),
            BingArchiver::CRAWLSTATS_IN_INDEX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlPagesInIndexDesc'),
            BingArchiver::CRAWLSTATS_IN_LINKS_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlInboundLinkDesc'),
        );
    }

    public static function getMetricIdsToProcessReportTotal()
    {
        return array(
            self::NB_CLICKS,
            self::NB_IMPRESSIONS
        );
    }

    /**
     * Returns operations used to aggregate the metric columns
     *
     * @return array
     */
    public static function getColumnsAggregationOperations()
    {
        /*
         * Calculate average CTR based on summed impressions and summed clicks
         */
        $calcCtr = function ($val1, $val2, $thisRow, $rowToSum) {
            $sumImpressions = $thisRow->getColumn(Metrics::NB_IMPRESSIONS) + $rowToSum->getColumn(Metrics::NB_IMPRESSIONS);
            $sumClicks      = $thisRow->getColumn(Metrics::NB_CLICKS) + $rowToSum->getColumn(Metrics::NB_CLICKS);
            if (!$sumImpressions) {
                return 0.0;
            }
            return round($sumClicks / $sumImpressions, 2);
        };

        /*
         * Calculate average position based on impressions and positions
         */
        $calcPosition = function ($val1, $val2, $thisRow, $rowToSum) {
            return round((($thisRow->getColumn(Metrics::NB_IMPRESSIONS) * $thisRow->getColumn(Metrics::POSITION)) +
                    ($rowToSum->getColumn(Metrics::NB_IMPRESSIONS) * $rowToSum->getColumn(Metrics::POSITION))) /
                ($thisRow->getColumn(Metrics::NB_IMPRESSIONS) + $rowToSum->getColumn(Metrics::NB_IMPRESSIONS)), 2);
        };

        return array(
            Metrics::CTR      => $calcCtr,
            Metrics::POSITION => $calcPosition,
        );
    }
}