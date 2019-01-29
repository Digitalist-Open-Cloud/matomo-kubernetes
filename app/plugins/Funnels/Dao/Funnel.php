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

class Funnel
{
    private $table = 'funnel';
    private $tablePrefixed = '';

    /**
     * @var Steps
     */
    private $steps;

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
        $this->steps = new Steps();
    }

    private function getDb()
    {
        return Db::get();
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idfunnel` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `idsite` int(11) UNSIGNED NOT NULL,
                  `idgoal` int(11) UNSIGNED NOT NULL,
                  `created_date` DATETIME NOT NULL,
                  `activated` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                  `deleted_date` DATETIME NULL DEFAULT NULL,
                  PRIMARY KEY(`idfunnel`),
                  KEY unique_idsite_idgoal (`idsite`, `idGoal`)");

        $this->steps->install();
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
        $this->steps->uninstall();
    }

    /**
     * @return array
     */
    public function getDisabledFunnelIds()
    {
        $table = $this->tablePrefixed;
        $funnels = $this->getDb()->fetchAll("SELECT idfunnel FROM $table WHERE deleted = 1", array());
        $ids = array();

        foreach ($funnels as $funnel) {
            $ids[] = $funnel['idfunnel'];
        }

        return $ids;
    }

    /**
     * @return array
     */
    public function getDisabledFunnelIdsOlderThan($olderThanDateTime)
    {
        $table = $this->tablePrefixed;
        $bind = array($olderThanDateTime);

        $funnels = $this->getDb()->fetchAll("SELECT idfunnel FROM $table WHERE deleted = 1 and deleted_date < ?", $bind);
        $ids = array();

        foreach ($funnels as $funnel) {
            $ids[] = $funnel['idfunnel'];
        }

        return $ids;
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
     * @internal
     * Does return deleted funnels. Won't "enrich" funnels. Do not use except for tests.
     * @return array
     */
    public function getAllFunnelsTestsOnly()
    {
        $table = $this->tablePrefixed;
        return $this->getDb()->fetchAll("SELECT * FROM $table");
    }

    private function enrichFunnels($funnels)
    {
        if (empty($funnels)) {
            return array();
        }

        foreach ($funnels as $index => $funnel) {
            $funnels[$index] = $this->enrichFunnel($funnel);
        }

        return $funnels;
    }

    private function enrichFunnel($funnel)
    {
        if (empty($funnel)) {
            return null;
        }

        $funnel['steps'] = $this->steps->getAllStepsForFunnel($funnel['idfunnel']);
        $funnel['activated'] = !empty($funnel['activated']);
        unset($funnel['deleted']);
        unset($funnel['deleted_date']);

        return $funnel;
    }

    /**
     * @param $idSite
     * @return bool
     */
    public function hasAnyActivatedFunnelForSite($idSite)
    {
        $table = $this->tablePrefixed;
        $funnels = $this->getDb()->fetchOne("SELECT count(*) FROM $table WHERE idsite = ? and deleted = 0 and activated = 1 LIMIT 1", array($idSite));

        return !empty($funnels);
    }

    /**
     * @param $idSite
     * @return array
     */
    public function getAllActivatedFunnelsForSite($idSite)
    {
        $table = $this->tablePrefixed;
        $funnels = $this->getDb()->fetchAll("SELECT * FROM $table WHERE idsite = ? and deleted = 0 and activated = 1", array($idSite));

        return $this->enrichFunnels($funnels);
    }

    /**
     * @param $idSite
     * @return array
     */
    public function getAllFunnelsForSite($idSite)
    {
        $table = $this->tablePrefixed;
        $funnels = $this->getDb()->fetchAll("SELECT * FROM $table WHERE idsite = ? and deleted = 0", array($idSite));

        return $this->enrichFunnels($funnels);
    }

    /**
     * @param $idGoal
     * @param $idSite
     * @return array|false
     * @throws \Exception
     */
    public function getGoalFunnel($idSite, $idGoal)
    {
        $table = $this->tablePrefixed;
        $funnel = $this->getDb()->fetchRow("SELECT * FROM $table WHERE idgoal = ? and idsite = ? and deleted = 0", array($idGoal, $idSite));

        return $this->enrichFunnel($funnel);
    }

    public function getFunnel($idFunnel)
    {
        $table = $this->tablePrefixed;
        $funnel = $this->getDb()->fetchRow("SELECT * FROM $table WHERE idfunnel = ? and deleted = 0", array($idFunnel));

        return $this->enrichFunnel($funnel);
    }

    public function createGoalFunnel($idSite, $idGoal, $isActivated, $steps, $createdDate)
    {
        $db = $this->getDb();
        $db->insert($this->tablePrefixed, array(
            'idgoal' => $idGoal,
            'idsite' => $idSite,
            'activated' => $isActivated ? 1 : 0,
            'created_date' => $createdDate,
            'deleted' => 0
        ));

        $idFunnel = $db->lastInsertId();

        $this->setSteps($idFunnel, $steps);

        return $idFunnel;
    }

    /**
     * @param int $idFunnel
     * @param array $steps
     */
    private function setSteps($idFunnel, $steps)
    {
        $this->steps->deleteStepsForFunnel($idFunnel);

        if (!empty($steps)) {
            $position = 0;
            foreach ($steps as $index => $step) {
                if (empty($step)) {
                    continue;
                }
                $position++;
                $this->steps->insertStep($idFunnel, $position, $step['name'], $step['pattern'], $step['pattern_type'], $step['required']);
            }
        }
    }

    public function updateFunnel($idFunnel, $isActivated, $steps)
    {
        $idFunnel = (int) $idFunnel;

        $db = $this->getDb();
        $db->update($this->tablePrefixed, array(
            'activated' => $isActivated ? 1 : 0
        ), "idfunnel = $idFunnel");

        $this->setSteps($idFunnel, $steps);
    }

    /**
     * @param int $idFunnel
     */
    public function deleteFunnel($idFunnel)
    {
        $table = $this->tablePrefixed;
        $stepsTable = $this->steps->getPrefixedTableName();

        $query = "DELETE $table, $stepsTable FROM $table LEFT JOIN $stepsTable ON $table.idfunnel = $stepsTable.idfunnel WHERE $table.idfunnel = ?";
        $bind = array($idFunnel);

        $this->getDb()->query($query, $bind);
    }

    /**
     * @param int $idSite
     * @param string $dateTime
     */
    public function disableFunnelsForSite($idSite, $dateTime)
    {
        $table = $this->tablePrefixed;

        $query = "UPDATE $table set deleted = 1, activated = 0, deleted_date = ? WHERE idsite = ?";
        $bind = array($dateTime, $idSite);
        $this->getDb()->query($query, $bind);
    }

    /**
     * @param int $idFunnel
     * @param string $dateTime
     */
    public function disableFunnel($idFunnel, $dateTime)
    {
        $table = $this->tablePrefixed;

        $query = "UPDATE $table set deleted = 1, activated = 0, deleted_date = ? WHERE idfunnel = ?";
        $bind = array($dateTime, $idFunnel);
        $this->getDb()->query($query, $bind);
    }

}

