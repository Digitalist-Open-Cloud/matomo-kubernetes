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
namespace Piwik\Plugins\Funnels\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;

class LogTable
{
    const BULK_INSERT_LIMIT = 1000;

    private $table = 'log_funnel';
    private $tablePrefixed = '';

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idfunnel` int(11) UNSIGNED NOT NULL,
                  `step_position` smallint(5) UNSIGNED NOT NULL,
                  `idsite` int(11) UNSIGNED NOT NULL,
                  `idvisit` bigint(10) UNSIGNED NOT NULL,
                  `idlink_va` bigint(10) UNSIGNED NOT NULL,
                  `idaction` int(11) UNSIGNED NULL DEFAULT NULL,
                  `idaction_prev` int(11) UNSIGNED NULL DEFAULT NULL,
                  `idaction_next` int(11) UNSIGNED NULL DEFAULT NULL,
                  `min_step` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
                  `max_step` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
                  INDEX index_id_step_idvisit(`idfunnel`, `step_position`, `idvisit`),
                  INDEX index_idvisit_id(`idvisit`, `idfunnel`)");
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    /**
     * @return string
     */
    public function getUnprefixedTableName()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getPrefixedTableName()
    {
        return $this->tablePrefixed;
    }

    /**
     * @internal only used for tests so far
     * @return array
     */
    public function getAllEntries()
    {
        $table = $this->tablePrefixed;
        return Db::fetchAll("SELECT * FROM $table");
    }

    /**
     * @internal only for tests
     * @return array
     */
    public function deleteAllEntriesTestsOnly()
    {
        $table = $this->tablePrefixed;
        Db::query("DELETE FROM $table");
    }

    /**
     * @param int $idFunnel
     * @return array
     */
    public function getStepPositionsUsedForFunnel($idFunnel)
    {
        $table = $this->tablePrefixed;
        $entries = Db::fetchAll("SELECT DISTINCT `step_position` FROM $table WHERE idfunnel = ? ORDER BY step_position ASC", array($idFunnel));

        $positions = array();
        foreach ($entries as $step) {
            $positions[] = (int) $step['step_position'];
        }

        return $positions;
    }

    public function deleteFunnelEntries($idFunnel)
    {
        Db::deleteAllRows($this->tablePrefixed, 'WHERE idfunnel = ?', 'idvisit', 100000, array((int) $idFunnel));
    }

    /**
     * Insert several funnel log entries at once.
     *
     * @param int $idSite
     * @param int $idFunnel
     * @param int $stepPosition
     * @param array $logEntries An array of log funnel entries
     *
     * @throws \Exception
     */
    public function bulkInsert($idSite, $idFunnel, $stepPosition, $logEntries)
    {
        if (empty($logEntries)) {
            return;
        }

        $columnsToCopy = array(
            'idvisit',
            'idlink_va',
            'idaction',
            'idaction_prev',
            'idaction_next'
        );

        $fields = $columnsToCopy;
        array_unshift($fields, 'idsite');
        array_unshift($fields, 'step_position');
        array_unshift($fields, 'idfunnel');

        $chunks = array_chunk($logEntries, LogTable::BULK_INSERT_LIMIT);

        foreach ($chunks as $chunk) {
            $values = array();
            foreach ($chunk as $logEntry) {
                $value = array($idFunnel, $stepPosition, $idSite);
                foreach ($columnsToCopy as $column) {
                    $value[] = $logEntry[$column];
                }
                $values[] = $value;
            }
            Db\BatchInsert::tableInsertBatch($this->tablePrefixed, $fields, $values, $throwException = false, $charset = 'latin1');
            unset($values);
        }

        unset($chunks);
    }

}

