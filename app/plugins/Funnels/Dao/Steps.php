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

class Steps
{
    private $table = 'funnel_steps';
    private $tablePrefixed = '';

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
        DbHelper::createTable($this->table, "
                  `idfunnel` int(11) UNSIGNED NOT NULL,
                  `position` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
                  `name` VARCHAR(150) NOT NULL DEFAULT '',
                  `pattern_type` VARCHAR(30) NOT NULL,
                  `pattern` VARCHAR(1000) NOT NULL,
                  `required` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                  PRIMARY KEY(`idfunnel`, `position`)");

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
     * @param int $idFunnel
     */
    public function deleteStepsForFunnel($idFunnel)
    {
        $table = $this->tablePrefixed;
        $this->getDb()->query("DELETE FROM $table where idfunnel = ?", array($idFunnel));
    }

    /**
     * @param int $idFunnel
     * @return array
     */
    public function getAllStepsForFunnel($idFunnel)
    {
        $table = $this->tablePrefixed;
        $steps = $this->getDb()->fetchAll("SELECT `position`, `name`, `pattern_type`, `pattern`, `required` FROM $table WHERE idfunnel = ? ORDER BY position ASC", array($idFunnel));

        if (!empty($steps)) {
            foreach ($steps as &$step) {
                $step['required'] = !empty($step['required']);
                $step['position'] = (int) $step['position'];
            }
        } else {
            $steps = array();
        }

        return $steps;
    }

    public function insertStep($idFunnel, $position, $name, $pattern, $patternType, $isRequired)
    {
        $values = array(
            'idfunnel' => $idFunnel,
            'position' => $position,
            'name' => $name,
            'pattern' => $pattern,
            'pattern_type' => $patternType,
            'required' => !empty($isRequired) ? '1' : '0',
        );

        $db = $this->getDb();
        $db->insert($this->tablePrefixed, $values);
    }

}

