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
namespace Piwik\Plugins\MediaAnalytics\Dao;

use Piwik\Common;

use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Segment;

class LogTable
{
    private $table = 'log_media';
    private $tablePrefixed = '';

    /**
     * @var Db|Db\AdapterInterface|\Piwik\Tracker\Db
     */
    private $db;

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
        $this->db = Db::get();
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idvisitor` binary(8) NOT NULL,
                  `idvisit` BIGINT unsigned NOT NULL,
                  `idsite` INT(11) unsigned NOT NULL,
                  `idview` VARCHAR(16) NOT NULL,
                  `player_name` VARCHAR(20) NOT NULL,
                  `media_type` TINYINT(1) NOT NULL,
                  `resolution` VARCHAR(20) DEFAULT '',
                  `fullscreen` TINYINT(1) UNSIGNED NOT NULL,
                  `media_title` VARCHAR(150) DEFAULT '',
                  `resource` VARCHAR(300) NOT NULL,
                  `server_time` DATETIME NOT NULL,
                  `time_to_initial_play` INT(11) UNSIGNED DEFAULT NULL,
                  `watched_time` BIGINT UNSIGNED DEFAULT 0,
                  `media_progress` INT(11) UNSIGNED DEFAULT 0,
                  `media_length` INT(11) UNSIGNED DEFAULT 0,
                  PRIMARY KEY(`idvisit`,`idview`),
                  KEY(`idsite`,`media_type`,`server_time`)");
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    public function record($idVisitor, $idVisit, $idSite, $idView, $mediaType, $playerName, $mediaTitle, $resource, $watchedTime, $mediaProgress, $mediaLength, $timeToInitialPlay, $mediaWidth, $mediaHeight, $isFullscreen, $serverTime)
    {
        $fullscreen = empty($isFullscreen) ? 0 : 1;
        $resolution = empty($mediaHeight) || empty($mediaWidth) || !is_numeric($mediaHeight) || !is_numeric($mediaWidth) ? '' : ($mediaWidth . 'x' . $mediaHeight);

        if ($timeToInitialPlay === '') {
            $timeToInitialPlay = null;
        }

        $values = array(
            'idvisitor' => $idVisitor,
            'idvisit' => $idVisit,
            'idsite' => $idSite,
            'idview' => $idView,
            'media_type' => $mediaType,
            'player_name' => $playerName,
            'media_title' => $mediaTitle,
            'resource' => $resource,
            'watched_time' => $watchedTime,
            'media_progress' => $mediaProgress,
            'media_length' => $mediaLength,
            'time_to_initial_play' => $timeToInitialPlay,
            'resolution' => $resolution,
            'fullscreen' => $fullscreen,
            'server_time' => $serverTime
        );

        $columns = implode('`,`', array_keys($values));
        $vals = Common::getSqlStringFieldsArray($values);

        $sql = sprintf('INSERT INTO %s (`%s`) VALUES(%s) 
                        ON DUPLICATE KEY UPDATE 
                        watched_time = IF(watched_time < ?, ?, watched_time), 
                        media_progress = IF(media_progress < ?, ?, media_progress), 
                        media_length = IF(media_length < ?, ?, media_length),
                        time_to_initial_play = IFNULL(time_to_initial_play, ?),
                        media_title = ?,
                        resolution = ?,
                        fullscreen = IF(fullscreen >= 1, 1, ' . $fullscreen . ')',
                       $this->tablePrefixed, $columns, $vals);

        $bind = array_values($values);
        $bind[] = $watchedTime;
        $bind[] = $watchedTime;
        $bind[] = $mediaProgress;
        $bind[] = $mediaProgress;
        $bind[] = $mediaLength;
        $bind[] = $mediaLength;
        $bind[] = $timeToInitialPlay;
        $bind[] = !empty($mediaTitle) ? substr($mediaTitle, 0, 150) : '';
        $bind[] = $resolution;

        $this->db->query($sql, $bind);
    }

    public function getAllRecords()
    {
        return $this->db->fetchAll('SELECT * FROM ' . $this->tablePrefixed);
    }

    public function getRecordsForVisitIds($visitIds)
    {
        if (empty($visitIds)) {
            return [];
        }

        $visitIds = array_map('intval', $visitIds);

        return $this->db->fetchAll("SELECT * FROM " . $this->tablePrefixed . " WHERE idvisit IN ('" . implode("','", $visitIds) ."') AND watched_time != 0");
    }

    public function hasRecords($idSite)
    {
        return (bool) $this->db->fetchOne('SELECT count(idsite) FROM ' . $this->tablePrefixed . ' WHERE idsite = ? LIMIT 1', array($idSite));
    }

    public function getNumPlays($idSite, $fromServerTime, $segment)
    {
        $where = sprintf('%1$s.idsite = ? and %1$s.server_time > ? and %1$s.watched_time > 0', $this->table);
        $segment = new Segment($segment, $idSite);
        $query = $segment->getSelectQuery('count(log_media.idview)', $this->table, $where, array($idSite, $fromServerTime));

        return $this->db->fetchOne($query['sql'], $query['bind']);
    }

    public function getSumWatchedTime($idSite, $fromServerTime, $segment)
    {
        $where = sprintf('%1$s.idsite = ? and %1$s.server_time > ?', $this->table);
        $segment = new Segment($segment, $idSite);
        $query = $segment->getSelectQuery('sum(log_media.watched_time)', $this->table, $where, array($idSite, $fromServerTime));

        return $this->db->fetchOne($query['sql'], $query['bind']);
    }

    public function getMostPlays($idSite, $fromServerTime, $limit, $segment)
    {
        $where = sprintf('%1$s.idsite = ? and %1$s.server_time > ? and %1$s.watched_time > 0', $this->table);
        $segment = new Segment($segment, $idSite);
        $query = $segment->getSelectQuery("count(log_media.idvisit) as value, IF(log_media.media_title='', log_media.resource, log_media.media_title) as label", $this->table, $where, array($idSite, $fromServerTime), $orderBy = 'value DESC, label ASC', $groupBy = 'label', (int) $limit);

        return $this->db->fetchAll($query['sql'], $query['bind']);
    }

    public function getMostUsedValuesForDimension($dimension, $idSite, $limit)
    {
        $startDate = Date::now()->subDay(60)->toString();

        $query = sprintf('SELECT %s, count(%s) as value FROM %s WHERE idsite = ? and server_time > ? and %s is not null GROUP BY %s ORDER BY value DESC, %s ASC LIMIT %d',
            $dimension, $dimension, $this->tablePrefixed, $dimension, $dimension, $dimension, (int) $limit);
        $rows = Db::get()->fetchAll($query, array($idSite, $startDate));

        $values = array();
        foreach ($rows as $row) {
            $values[] = $row[$dimension];
        }

        return $values;
    }

    public static function getInstance()
    {
        return StaticContainer::get('Piwik\Plugins\MediaAnalytics\Dao\LogTable');
    }
}

