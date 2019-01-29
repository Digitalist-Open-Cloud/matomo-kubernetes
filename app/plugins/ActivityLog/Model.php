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
namespace Piwik\Plugins\ActivityLog;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Network\IPUtils;

class Model
{
    private $tableName = 'activity_log';

    private $validFields = array(
        'id',
        'user_login',
        'type',
        'parameters',
        'ts_created',
        'country',
        'ip'
    );

    /**
     * Creates table
     */
    public function install()
    {
        $table = "`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_login` VARCHAR(100) NOT NULL,
                  `type` VARCHAR(255) NOT NULL,
                  `parameters` LONGTEXT NOT NULL,
                  `ts_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  `country` CHAR(3) NULL DEFAULT NULL,
                  `ip` VARBINARY(16) NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)";

        DbHelper::createTable($this->tableName, $table);
    }

    /**
     * Adds a new activity to the database table
     *
     * @param array $activity
     * @return bool|int  id of new record or false on error
     */
    public function add($activity)
    {
        try {
            $table = Common::prefixTable($this->tableName);

            $invalidKeys = array_diff(array_keys($activity), $this->validFields);

            if (!empty($invalidKeys)) {
                 // invalid fields
                return false;
            }

            if (isset($activity['ip'])) {
                $activity['ip'] = IPUtils::stringToBinaryIP($activity['ip']);
            }

            $columns = implode('`,`', array_keys($activity));
            $values = Common::getSqlStringFieldsArray($activity);
            $bind = array_values($activity);

            $query = sprintf('INSERT INTO %s (`%s`) VALUES (%s)', $table, $columns, $values);

            // we do not use $db->insert() here as this method is as well used in Tracker mode and the tracker DB does not
            // support "->insert()". We need to use the query method instead.
            $db = $this->getDatabase();
            $db->query($query, $bind);

            $insertId = $db->lastInsertId();

        } catch (\Exception $e) {
            $insertId = false;
        }

        return $insertId;
    }

    /**
     * Returns entries of activity log
     *
     * If user has no super user access only entries for the current user will be returned
     *
     * @param int $offset offset to start at
     * @param int $limit amount of entries to return
     * @param null|string $filterByUserLogin userLogin to filter by
     * @param null|string $filterByActivityType activity type to filter by
     *
     * @return array
     */
    public function getEntries($offset = 0, $limit = 25, $filterByUserLogin = null, $filterByActivityType = null)
    {
        $query = sprintf('SELECT * FROM %s ', Common::prefixTable($this->tableName));

        $where = [];
        $bind  = [];

        if ($filterByUserLogin) {
            $where[] = 'user_login = ?';
            $bind[]  = $filterByUserLogin;
        }

        if ($filterByActivityType) {
            $where[] = 'type = ?';
            $bind[]  = $filterByActivityType;
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY ts_created DESC';

        if ($limit) {
            $query .= sprintf(' LIMIT %d,%d', $offset, $limit);
        }

        $entries = $this->getDatabase()->fetchAll($query, $bind);

        foreach ($entries as &$entry) {
            if (!empty($entry['ip'])) {
                $entry['ip'] = IPUtils::binaryToStringIP($entry['ip']);
            }
        }

        return $entries;
    }

    /**
     * Returns count auf available entries
     *
     * @param null|string $filterByUserLogin
     * @param null|string $filterByActivityType
     * @return array
     */
    public function getAvailableEntryCount($filterByUserLogin = null, $filterByActivityType = null)
    {
        $query = sprintf('SELECT COUNT(id) FROM %s ', Common::prefixTable($this->tableName));

        $where = [];
        $bind  = [];

        if ($filterByUserLogin) {
            $where[] = 'user_login = ?';
            $bind[]  = $filterByUserLogin;
        }

        if ($filterByActivityType) {
            $where[] = 'type = ?';
            $bind[]  = $filterByActivityType;
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        return $this->getDatabase()->fetchOne($query, $bind);
    }

    /**
     * @return Db\Adapter\Mysqli
     */
    protected function getDatabase()
    {
        return Db::get();
    }
}
