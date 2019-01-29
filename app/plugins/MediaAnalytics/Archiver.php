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

namespace Piwik\Plugins\MediaAnalytics;
use Piwik\ArchiveProcessor;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataAccess\LogAggregator;
use Piwik\Date;
use Piwik\Db;
use Piwik\DataArray as PiwikDataArray;
use Piwik\Plugins\MediaAnalytics\Archiver\DataArray;
use Piwik\Plugins\MediaAnalytics\Archiver\GroupedDataArray;
use Piwik\Plugins\MediaAnalytics\Archiver\HoursDataArray;
use Piwik\Plugins\MediaAnalytics\Archiver\ResourceDataArray;

/**
 * Class Archiver
 * @package Piwik\Plugins\MediaAnalytics
 */
class Archiver extends \Piwik\Plugin\Archiver
{
    const RECORD_VIDEO_RESOURCES = "MediaAnalytics_video_resources_record";
    const RECORD_VIDEO_GROUPEDRESOURCES = "MediaAnalytics_video_groupedresources_record";
    const RECORD_VIDEO_TITLES = "MediaAnalytics_video_title_record";
    const RECORD_VIDEO_RESOLUTIONS = "MediaAnalytics_video_resolutions_record";
    const RECORD_VIDEO_HOURS = "MediaAnalytics_video_hours_record";

    const RECORD_AUDIO_RESOURCES = "MediaAnalytics_audio_resources_record";
    const RECORD_AUDIO_GROUPEDRESOURCES = "MediaAnalytics_audio_groupedresources_record";
    const RECORD_AUDIO_TITLES = "MediaAnalytics_audio_title_record";
    const RECORD_AUDIO_HOURS= "MediaAnalytics_audio_hours_record";

    const RECORD_PLAYER_NAMES = "MediaAnalytics_playernames_record";

    const NUMERIC_RECORD_PREFIX = 'MediaAnalytics_';

    const LABEL_NOT_DEFINED = 'MEDIA_LABEL_NOT_DEFINED';

    const SECONDARY_DIMENSION_HOURS = 'hours';
    const SECONDARY_DIMENSION_RESOLUTION = 'resolution';
    const SECONDARY_DIMENSION_SPENT_TIME = 'spent_time';
    const SECONDARY_DIMENSION_MEDIA_PROGRESS = 'media_progress';

    /**
     * @var LogAggregator
     */
    private $logAggregator;
    
    private $maximumRowsInDataTable;
    private $maximumRowsInSubTable;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->maximumRowsInDataTable = null;
        $this->maximumRowsInSubTable = null;
        $this->logAggregator = $this->getLogAggregator();
    }

    public static function isUniqueVisitorsEnabled($periodLabel)
    {
        return $periodLabel === 'day';
    }

    public function aggregateDayReport()
    {
        // RECORD VIDEO RESOURCES
        $groupBy = 'log_media.resource';
        $where = ' AND log_media.media_type = ' . MediaAnalytics::MEDIA_TYPE_VIDEO;
        $this->makeRegularReport(array(
            self::RECORD_VIDEO_RESOURCES => new ResourceDataArray(),
            self::RECORD_VIDEO_GROUPEDRESOURCES => new GroupedDataArray(),
        ), $where, $groupBy, $withSubtableReport = true);

        // RECORD AUDIO RESOURCES
        $groupBy = 'log_media.resource';
        $where = ' AND log_media.media_type = ' . MediaAnalytics::MEDIA_TYPE_AUDIO;
        $this->makeRegularReport(array(
            self::RECORD_AUDIO_RESOURCES => new ResourceDataArray(),
            self::RECORD_AUDIO_GROUPEDRESOURCES => new GroupedDataArray(),
        ), $where, $groupBy, $withSubtableReport = true);
        
        // RECORD VIDEO MEDIA TITLES
        $groupBy = 'log_media.media_title';
        $where = ' AND log_media.media_type = ' . MediaAnalytics::MEDIA_TYPE_VIDEO;
        $this->makeRegularReport(array(self::RECORD_VIDEO_TITLES => new DataArray()), $where, $groupBy, $withSubtableReport = true);

        // RECORD AUDIO MEDIA TITLES
        $groupBy = 'log_media.media_title';
        $where = ' AND log_media.media_type = ' . MediaAnalytics::MEDIA_TYPE_AUDIO;
        $this->makeRegularReport(array(self::RECORD_AUDIO_TITLES => new DataArray()), $where, $groupBy, $withSubtableReport = true);

        // RECORD MEDIA PLAYERS
        $groupBy = 'log_media.player_name';
        $where = '';
        $this->makeRegularReport(array(self::RECORD_PLAYER_NAMES => new DataArray()), $where, $groupBy);
        
        // RECORD RESOLUTION
        $groupBy = 'log_media.resolution';
        $where = ' AND char_length(log_media.resolution) > 5 AND log_media.media_type = ' . MediaAnalytics::MEDIA_TYPE_VIDEO;
        $this->makeRegularReport(array(self::RECORD_VIDEO_RESOLUTIONS => new DataArray()), $where, $groupBy);

        // RECORD HOURS
        $date = Date::factory($this->getParams()->getDateStart()->getDateStartUTC())->toString();
        $timezone = $this->getParams()->getSite()->getTimezone();

        $dataArray = new HoursDataArray($date, $timezone);
        $groupBy = 'hour(log_media.server_time)';
        $where = ' AND log_media.media_type = ' . MediaAnalytics::MEDIA_TYPE_VIDEO;
        $this->makeRegularReport(array(self::RECORD_VIDEO_HOURS => $dataArray), $where, $groupBy);

        $dataArray = new HoursDataArray($date, $timezone);
        $groupBy = 'hour(log_media.server_time)';
        $where = ' AND log_media.media_type = ' . MediaAnalytics::MEDIA_TYPE_AUDIO;
        $this->makeRegularReport(array(self::RECORD_AUDIO_HOURS => $dataArray), $where, $groupBy);

        $this->archiveGlobalMetrics();
    }

    private function getParams()
    {
        return $this->getProcessor()->getParams();
    }

    /**
     * @param DataArray[] $dataArrays array('recordName' => DataArray)
     * @param $where
     * @param $groupByColumn
     * @param bool $withSubtableReport
     * @internal param $select
     */
    private function makeRegularReport($dataArrays, $where, $groupByColumn, $withSubtableReport = false)
    {
        $baseSelect1 = sprintf(
            '%s as label,
             count(log_media.idvisit) as %s,
             count(distinct log_media.idvisitor) as %s',
            $groupByColumn,
            Metrics::METRIC_NB_IMPRESSIONS,
            Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS
        );

        $baseSelect2 = sprintf(
            '%s as label,
             count(log_media.idvisit) as %s,
             count(distinct log_media.idvisitor) as %s,
             %s as %s,
             sum(log_media.time_to_initial_play) as %s,
             sum(if(log_media.time_to_initial_play is null, 0, 1)) as %s,
             sum(log_media.watched_time) as %s,
             sum(log_media.media_progress) as %s,
             sum(log_media.media_length) as %s,
             sum(if(log_media.media_length > 0, 1, 0)) as %s,
             sum(log_media.fullscreen) as %s',
            $groupByColumn,
            Metrics::METRIC_NB_PLAYS,
            Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS,
            $this->getSelectFinishes(),
            Metrics::METRIC_NB_FINISHES,
            Metrics::METRIC_SUM_TIME_TO_PLAY,
            Metrics::METRIC_NB_PLAYS_WITH_TIME_TO_INITIAL_PLAY,
            Metrics::METRIC_SUM_TIME_WATCHED,
            Metrics::METRIC_SUM_TIME_PROGRESS,
            Metrics::METRIC_SUM_MEDIA_LENGTH,
            Metrics::METRIC_NB_PLAYS_WITH_MEDIA_LENGTH,
            Metrics::METRIC_SUM_FULLSCREEN_PLAYS
        );

        $baseSelects = array(
            'impressions' => array(
                'select' => $baseSelect1,
                'where' => '' . $where,
                'orderBy' => Metrics::METRIC_NB_IMPRESSIONS,
            ),
            'plays' => array(
                'select' => $baseSelect2,
                'where' => ' AND watched_time > 1 ' . $where,
                'orderBy' => Metrics::METRIC_NB_PLAYS,
            )
        );

        foreach ($baseSelects as $baseSelect) {
            $cursor = $this->query($baseSelect['select'], $baseSelect['where'], 'label', $baseSelect['orderBy']);

            while ($row = $cursor->fetch()) {
                foreach ($dataArrays as $recordName => $dataArray) {
                    $dataArray->computeMetrics($row);
                }
            }

            $cursor->closeCursor();
            unset($cursor);
        }

        if ($withSubtableReport) {
            $this->archiveSubtables($baseSelects['plays'], $dataArrays, $groupByColumn);
        }

        foreach ($dataArrays as $recordName => $dataArray) {
            $this->insertDataArray($recordName, $dataArray);
        }

        unset($dataArrays);
    }

    public static function putValueIntoSecondsBucket($value)
    {
        if ($value <= 10) {
            return $value;
        }

        $rest = 0;

        if ($value >= 21600) {
            $rest = $value % 1800; // after 6 hours we group watched time into buckets of 30 minutes
        } elseif ($value >= 10800) {
            $rest = $value % 900; // after 3 hours we group watched time into buckets of 15 minutes
        } elseif ($value >= 7201) {
            $rest = $value % 600; // after 2 hours we group watched time into buckets of 10 minutes
        } elseif ($value >= 3601) {
            $rest = $value % 300; // after 1 hour we group watched time into buckets of 5 minutes
        } elseif ($value >= 1201) {
            $rest = $value % 60; // after 10 minutes we group watched time into buckets of 1 minute
        } elseif ($value >= 301) {
            $rest = $value % 30; // after 5 minutes we group watched time into buckets of 30 seconds
        } elseif ($value >= 121) {
            $rest = $value % 20; // after 2 minutes we group watched time into buckets of 20 seconds
        } elseif ($value >= 61) {
            $rest = $value % 10; // after 1 minutes we group watched time into buckets of 10 seconds
        } elseif ($value >= 31) {
            $rest = $value % 5; // after 30 seconds we group watched time into buckets of 5 seconds
        } elseif ($value >= 11) {
            $rest = $value % 2; // after 10 seconds we group watched time into buckets of 2 seconds
        }

        return $value - $rest;
    }

    private function archiveSubtables($baseSelect, $dataArrays, $groupByColumn)
    {
        $select = $groupByColumn . ' as parentLabel, 
                  log_media.watched_time as label, 
                  count(log_media.watched_time) as ' . Metrics::METRIC_NB_PLAYS;
        $groupBy = $groupByColumn . ', log_media.watched_time';
        $cursor = $this->query($select, $baseSelect['where'], $groupBy, '');

        while ($row = $cursor->fetch()) {

            $parentLabel = $row['parentLabel'];
            unset($row['parentLabel']);

            $label = $this->putValueIntoSecondsBucket($row['label']);

            foreach ($dataArrays as $dataArray) {

                /** @var DataArray $dataArray */
                $dataArray->computeMetricsSubtable(self::SECONDARY_DIMENSION_SPENT_TIME, $parentLabel, $label, $row);
            }
        }
        $cursor->closeCursor();

        $select = $groupByColumn . ' as parentLabel, 
                  round((log_media.media_progress / log_media.media_length) * 100) as label, 
                  count(log_media.media_length) as ' . Metrics::METRIC_NB_PLAYS;

        $groupBy = $groupByColumn . ', label';
        $cursor = $this->query($select, $baseSelect['where'] . ' AND log_media.media_length > 0', $groupBy, '');
        while ($row = $cursor->fetch()) {
            $parentLabel = $row['parentLabel'];
            unset($row['parentLabel']);

            foreach ($dataArrays as $dataArray) {
                /** @var DataArray $dataArray */
                $dataArray->computeMetricsSubtable(self::SECONDARY_DIMENSION_MEDIA_PROGRESS, $parentLabel, $row['label'], $row);
            }
        }
        $cursor->closeCursor();

        $select = sprintf('%s as parentLabel, 
                          log_media.resolution as label, 
                          count(log_media.idvisit) as %s,
                          %s as %s,
                          sum(log_media.watched_time) as %s',
                          $groupByColumn,
                          Metrics::METRIC_NB_PLAYS,
                          $this->getSelectFinishes(),
                          Metrics::METRIC_NB_FINISHES,
                          Metrics::METRIC_SUM_TIME_WATCHED);
        $groupBy = $groupByColumn . ', log_media.resolution';
        $cursor = $this->query($select, $baseSelect['where'], $groupBy, $orderBy = Metrics::METRIC_NB_PLAYS);

        $resource = array();

        while ($row = $cursor->fetch()) {
            $parentLabel = $row['parentLabel']; // ==> resource or name
            unset($row['parentLabel']);

            if (!isset($resource[$parentLabel])) {
                $resource[$parentLabel] = 1;
            } elseif ($resource[$parentLabel] > 10) {
                // we only save the top 10 resolutions per resource for each day. This means the aggregated sums
                // might not be 100% correct but that should be fine as usually there are not too many resolutions.
                // this works because they are ordered by plays, won't be possible to sort by something else
                continue;
            } else {
                $resource[$parentLabel]++;
            }

            foreach ($dataArrays as $dataArray) {
                /** @var DataArray $dataArray */
                $dataArray->computeMetricsSubtable(self::SECONDARY_DIMENSION_RESOLUTION, $parentLabel, $row['label'], $row);
            }
        }
        $cursor->closeCursor();

        $select = sprintf('%s as parentLabel, 
                          hour(log_media.server_time) as label, 
                          count(log_media.idvisit) as %s,
                          %s as %s, 
                          sum(log_media.watched_time) as %s',
                          $groupByColumn,
                          Metrics::METRIC_NB_PLAYS,
                          $this->getSelectFinishes(),
                          Metrics::METRIC_NB_FINISHES,
                          Metrics::METRIC_SUM_TIME_WATCHED);
        $groupBy = $groupByColumn . ', label';
        $cursor = $this->query($select, $baseSelect['where'], $groupBy, $orderBy = Metrics::METRIC_NB_PLAYS);

        while ($row = $cursor->fetch()) {
            // todo: should it be 5?
            if ($row[Metrics::METRIC_NB_PLAYS] < 1) {
                // ignore any resolution that had less than 5 plays, just to not save too many of them
                continue;
            }

            $parentLabel = $row['parentLabel'];
            unset($row['parentLabel']);

            foreach ($dataArrays as $dataArray) {
                /** @var DataArray $dataArray */
                $dataArray->computeMetricsSubtable(self::SECONDARY_DIMENSION_HOURS, $parentLabel, $row['label'], $row);
            }
        }
        $cursor->closeCursor();
    }

    private function archiveGlobalMetrics()
    {
        $records = array();

        // IMPRESSIONS
        $select = sprintf('count(log_media.idvisit) as %s, count(distinct log_media.idvisitor) as %s',
            Metrics::METRIC_NB_IMPRESSIONS, Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS);
        $cursor = $this->query($select, $where = '', $groupBy = '', $orderBy = '');
        $row = $cursor->fetch();
        $records = array_merge($records, $row);

        $select = sprintf('count(log_media.idvisit) as %s', Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS);
        $cursor = $this->query($select, $where = ' AND media_type = ' . MediaAnalytics::MEDIA_TYPE_AUDIO, $groupBy = '', $orderBy = '');
        $row = $cursor->fetch();
        $records = array_merge($records, $row);

        $select = sprintf('count(log_media.idvisit) as %s', Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS);
        $cursor = $this->query($select, $where = ' AND media_type = ' . MediaAnalytics::MEDIA_TYPE_VIDEO, $groupBy = '', $orderBy = '');
        $row = $cursor->fetch();
        $records = array_merge($records, $row);

        // PLAYS
        $select = sprintf('count(log_media.idvisit) as %s, 
                          count(distinct log_media.idvisitor) as %s,
                          sum(log_media.watched_time) as %s,
                          %s as %s',
            Metrics::METRIC_NB_PLAYS,
            Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS,
            Metrics::METRIC_TOTAL_TIME_WATCHED,
            $this->getSelectFinishes(),
            Metrics::METRIC_NB_FINISHES);
        $cursor = $this->query($select, $where = ' AND watched_time > 0', $groupBy = '', $orderBy = '');
        $row = $cursor->fetch();
        $records = array_merge($records, $row);

        $select = sprintf('count(log_media.idvisit) as %s', Metrics::METRIC_TOTAL_AUDIO_PLAYS);
        $cursor = $this->query($select, $where = 'AND watched_time > 0 AND media_type = ' . MediaAnalytics::MEDIA_TYPE_AUDIO, $groupBy = '', $orderBy = '');
        $row = $cursor->fetch();
        $records = array_merge($records, $row);

        $select = sprintf('count(log_media.idvisit) as %s', Metrics::METRIC_TOTAL_VIDEO_PLAYS);
        $cursor = $this->query($select, $where = ' AND watched_time > 0 AND media_type = ' . MediaAnalytics::MEDIA_TYPE_VIDEO, $groupBy = '', $orderBy = '');
        $row = $cursor->fetch();
        $records = array_merge($records, $row);

        $recordNames = array();
        foreach ($records as $record => $value) {
            $recordNames[self::NUMERIC_RECORD_PREFIX . $record] = $value;
        }

        $this->getProcessor()->insertNumericRecords($recordNames);
    }

    // for finishes we have a slight tolerance if someone watches close to 2 seconds to the end we count it as finished
    // as there could be some race conditions or for some reasons the player might not report the correct progress etc
    private function getSelectFinishes()
    {
        return 'sum(if(log_media.media_length > 2 AND log_media.media_progress >= (log_media.media_length - 2), 1, 0))';
    }

    private function insertDataArray($recordName, PiwikDataArray $dataArray)
    {
        $table = $dataArray->asDataTable();

        $serialized = $table->getSerialized($this->maximumRowsInDataTable, $this->maximumRowsInSubTable, Metrics::METRIC_NB_PLAYS);
        $this->getProcessor()->insertBlobRecord($recordName, $serialized);

        Common::destroy($table);
        unset($table);
        unset($serialized);
    }
    
    public function aggregateMultipleReports()
    {
        $recordNames = array(
            self::RECORD_VIDEO_RESOURCES,
            self::RECORD_VIDEO_GROUPEDRESOURCES,
            self::RECORD_VIDEO_TITLES,
            self::RECORD_VIDEO_RESOLUTIONS,
            self::RECORD_VIDEO_HOURS,
            self::RECORD_AUDIO_RESOURCES,
            self::RECORD_AUDIO_GROUPEDRESOURCES,
            self::RECORD_AUDIO_TITLES,
            self::RECORD_AUDIO_HOURS,
            self::RECORD_PLAYER_NAMES
        );

        $columnsAggregationOperation = array('url' => function ($thisColumnValue, $columnToSumValue) {
            if (!empty($thisColumnValue)) {
                return $thisColumnValue;
            }
            if (!empty($columnToSumValue)){
                return $columnToSumValue;
            }
        });

        $this->getProcessor()->aggregateDataTableRecords(
            $recordNames,
            $this->maximumRowsInDataTable,
            $this->maximumRowsInSubTable,
            $columnToSortByBeforeTruncation = Metrics::METRIC_NB_PLAYS,
            $columnsAggregationOperation,
            $columnsToRenameAfterAggregation = array()
        );

        $metrics = array(
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_NB_PLAYS,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_NB_IMPRESSIONS,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_NB_FINISHES,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_TOTAL_TIME_WATCHED,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_TOTAL_AUDIO_PLAYS,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_TOTAL_VIDEO_PLAYS,
            self::NUMERIC_RECORD_PREFIX . Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS,
        );
        $this->getProcessor()->aggregateNumericMetrics($metrics);
    }

    private function query($select, $where, $groupBy, $orderBy)
    {
        $from = array('log_media');

        $condition = $this->logAggregator->getWhereStatement('log_media', 'server_time');
        if (!empty($where)) {
            $condition .= ' ' . $where . ' ';
        }

        $logQueryBuilder = StaticContainer::get('Piwik\DataAccess\LogQueryBuilder');
        // only available from piwik 3.2.0 or 3.1.0
        $segment = $this->getParams()->getSegment();
        $shouldForceInnerGroupBy = $segment && $segment->getString();
        $shouldForceInnerGroupBy = $shouldForceInnerGroupBy && $logQueryBuilder && method_exists($logQueryBuilder, 'forceInnerGroupBySubselect');

        if ($shouldForceInnerGroupBy) {
            $logQueryBuilder->forceInnerGroupBySubselect( 'log_media.idview');
        }

        try {
            // just fyi: we cannot add any bind as any argument as it would otherwise break segmentation
            $query = $this->logAggregator->generateQuery($select, $from, $condition, $groupBy, $orderBy);

        } catch (\Exception $e) {
            if ($shouldForceInnerGroupBy) {
                // important to unset it, otherwise could be applied to other archiver queries of other plugins etc.
                $logQueryBuilder->forceInnerGroupBySubselect('');
            }

            throw $e;
        }

        if ($shouldForceInnerGroupBy) {
            // important to unset it, otherwise could be applied to other archiver queries of other plugins etc.
            $logQueryBuilder->forceInnerGroupBySubselect('');
        }

        return Db::query($query['sql'], $query['bind']);
    }
}
