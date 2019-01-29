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
namespace Piwik\Plugins\CustomReports\Dao;

use Piwik\Common;
use Piwik\Date;
use Piwik\DbHelper;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\CustomReports\Input\Category;
use Piwik\Plugins\CustomReports\Input\Description;
use Piwik\Plugins\CustomReports\Input\Name;
use Piwik\Plugins\CustomReports\Input\Subcategory;
use Piwik\Plugins\CustomReports\ReportType\Table;
use Piwik\Plugins\CustomReports\Model\CustomReportsModel;
use Exception;

class CustomReportsDao
{
    private $table = 'custom_reports';
    private $tablePrefixed = '';

    const DEFAULT_CATEGORY = 'CustomReports_CustomReports';

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
    }

    private function getDb()
    {
        return Db::get();
    }

    public function install()
    {
        // revision is there to indirectly "invalidate" an existing archive for that custom report.
        // revision is appended to the archive record name and when revision changes, it will notice that no archive
        // exists yet and need to re-archive reports for it
        DbHelper::createTable($this->table, "
                  `idcustomreport` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `idsite` int(11) UNSIGNED NOT NULL,
                  `revision` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
                  `report_type` VARCHAR(10) NOT NULL DEFAULT '" . Table::ID . "',
                  `name` VARCHAR(" . Name::MAX_LENGTH . ") NOT NULL,
                  `description` VARCHAR(" . Description::MAX_LENGTH . ") NOT NULL DEFAULT '',
                  `category` VARCHAR(" . Category::MAX_LENGTH . ") NOT NULL DEFAULT '". self::DEFAULT_CATEGORY."',
                  `subcategory` VARCHAR(" . Subcategory::MAX_LENGTH . ") NOT NULL DEFAULT '',
                  `dimensions` TEXT NOT NULL,
                  `metrics` TEXT NOT NULL,
                  `segment_filter` TEXT NOT NULL DEFAULT '',
                  `created_date` DATETIME NOT NULL,
                  `updated_date` DATETIME NOT NULL,
                  `status` VARCHAR(10) NOT NULL DEFAULT '" . CustomReportsModel::STATUS_ACTIVE . "',
                  PRIMARY KEY (`idcustomreport`),
                  UNIQUE unique_site_name (`idsite`, `name`)");
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    public function addCustomReport($idSite, $name, $description, $reportType, $dimensions, $metrics, $segmentFilter, $categoryId, $subcategoryId, $status, $createdDate)
    {
        $columns = array(
            'idsite' => $idSite,
            'name' => $name,
            'description' => $description,
            'report_type' => $reportType,
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'segment_filter' => $segmentFilter,
            'subcategory' => $subcategoryId,
            'category' => $categoryId,
            'status' => $status,
            'created_date' => $createdDate,
            'updated_date' => $createdDate
        );
        $columns = $this->encodeFieldsWhereNeeded($columns);

        $bind = array_values($columns);
        $placeholder = Common::getSqlStringFieldsArray($columns);

        $sql = sprintf('INSERT INTO %s (`%s`) VALUES(%s)',
            $this->tablePrefixed, implode('`,`', array_keys($columns)), $placeholder);

        $db = $this->getDb();

        try {
            $db->query($sql, $bind);

        } catch (Exception $e) {
            if ($e->getCode() == 23000
                || strpos($e->getMessage(), 'Duplicate entry') !== false
                || strpos($e->getMessage(), ' 1062 ') !== false) {
                throw new Exception(Piwik::translate('CustomReports_ErrorReportNameDuplicate'));
            }
            throw $e;
        }

        $idCustomReport = $db->lastInsertId();

        return (int) $idCustomReport;
    }

    public function updateColumns($idSite, $idCustomReport, $columns)
    {
        $columns = $this->encodeFieldsWhereNeeded($columns);

        if (!empty($columns)) {
            if (!isset($columns['updated_date'])) {
                $columns['updated_date'] = $this->getCurrentTime();
            }

            $fields = array();
            $bind = array();
            foreach ($columns as $key => $value) {
                $fields[] = ' ' . $key . ' = ?';
                $bind[] = $value;
            }
            $fields = implode(',', $fields);

            $query = sprintf('UPDATE %s SET %s WHERE idcustomreport = ? AND idsite = ?', $this->tablePrefixed, $fields);
            $bind[] = (int) $idCustomReport;
            $bind[] = (int) $idSite;

            // we do not use $db->update() here as this method is as well used in Tracker mode and the tracker DB does not
            // support "->update()". Therefore we use the query method where we know it works with tracker and regular DB
            $this->getDb()->query($query, $bind);
        }
    }

    /**
     * @return int
     */
    public function getNumReportsTotal()
    {
        $sql = sprintf("SELECT COUNT(*) as numreports FROM %s WHERE `status` = ?", $this->tablePrefixed);
        return $this->getDb()->fetchOne($sql, array(CustomReportsModel::STATUS_ACTIVE));
    }

    /**
     * @param int $idSite
     * @param int $idCustomReport
     */
    public function deleteCustomReport($idSite, $idCustomReport)
    {
        $query = sprintf('DELETE FROM %s WHERE (idsite = ? or idsite = 0) and idcustomreport = ?', $this->tablePrefixed);
        $db = $this->getDb();
        $db->query($query, array($idSite, $idCustomReport));
    }

    /**
     * @param int $idSite
     */
    public function deleteCustomReports($idSite)
    {
        $query = sprintf('DELETE FROM %s WHERE (idsite = ? or idsite = 0) and status = ?', $this->tablePrefixed);
        $db = $this->getDb();
        $db->query($query, array($idSite, CustomReportsModel::STATUS_ACTIVE));
    }

    /**
     * @param int $idSite
     * @return array
     */
    public function getCustomReports($idSite)
    {
        $query = sprintf('SELECT * FROM %s WHERE (idsite = ? or idsite = 0) and status = ?', $this->tablePrefixed);
        $db = $this->getDb();
        $rows = $db->fetchAll($query, array($idSite, CustomReportsModel::STATUS_ACTIVE));

        return $this->enrichRecords($rows);
    }

    /**
     * @return array
     */
    public function getAllReports()
    {
        $table = $this->tablePrefixed;
        $reports = $this->getDb()->fetchAll("SELECT * FROM $table");
        return $this->enrichRecords($reports);
    }

    /**
     * @param int $idSite
     * @param int $idCustomReport
     * @return array|bool
     */
    public function getCustomReport($idSite, $idCustomReport)
    {
        $query = sprintf('SELECT * FROM %s WHERE (idsite = ? or idsite = 0) and idcustomreport = ? and status = ? LIMIT 1', $this->tablePrefixed);
        $db = $this->getDb();
        $row = $db->fetchRow($query, array($idSite, $idCustomReport, CustomReportsModel::STATUS_ACTIVE));

        return $this->enrichRecord($row);
    }

    /**
     * @param int $idCustomReport
     * @return array|bool
     */
    public function getCustomReportById($idCustomReport)
    {
        $query = sprintf('SELECT * FROM %s WHERE idcustomreport = ? and status = ? LIMIT 1', $this->tablePrefixed);
        $db = $this->getDb();
        $row = $db->fetchRow($query, array($idCustomReport, CustomReportsModel::STATUS_ACTIVE));

        return $this->enrichRecord($row);
    }

    private function enrichRecords($records)
    {
        if (empty($records)) {
            return $records;
        }

        foreach ($records as $index => $record) {
            $records[$index] = $this->enrichRecord($record);
        }

        return $records;
    }

    private function enrichRecord($record)
    {
        if (empty($record)) {
            return $record;
        }

        $record['idcustomreport'] = (int) $record['idcustomreport'];
        $record['idsite'] = (int) $record['idsite'];
        $record['revision'] = (int) $record['revision'];
        $record['dimensions'] = $this->decodeField($record['dimensions']);
        $record['metrics'] = $this->decodeField($record['metrics']);

        if (!empty($record['created_date']) && strpos($record['created_date'], '0000') !== false) {
            $record['created_date'] = null;
        }

        if (!empty($record['updated_date']) && strpos($record['updated_date'], '0000') !== false) {
            $record['updated_date'] = null;
        }

        if (empty($record['category'])) {
            $record['category'] = self::DEFAULT_CATEGORY;
        }

        return $record;
    }

    private function encodeFieldsWhereNeeded($columns)
    {
        foreach ($columns as $column => $value) {
            if (in_array($column, array('dimensions', 'metrics'))) {
                $columns[$column] = $this->encodeField($value);
            }
        }

        return $columns;
    }

    private function encodeField($field)
    {
        if (empty($field) || !is_array($field)) {
            $field = array();
        }

        return json_encode($field);
    }

    private function decodeField($field)
    {
        if (!empty($field)) {
            $field = @json_decode($field, true);
        }

        if (empty($field) || !is_array($field)) {
            $field = array();
        }

        return $field;
    }

    protected function getCurrentTime()
    {
        return Date::now()->getDatetime();
    }
}

