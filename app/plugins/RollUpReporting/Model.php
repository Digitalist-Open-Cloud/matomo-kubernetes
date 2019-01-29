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
namespace Piwik\Plugins\RollUpReporting;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Site;

class Model
{

    private $table = 'site_rollup';
    private $tablePrefixed = '';

    const KEY_ALL_WEBSITES = 'all';
    const TYPE_ROLLUP_SITE = 'rollup';

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
                  `parent_idsite` int(11) UNSIGNED NOT NULL,
                  `idsite` int(11) UNSIGNED NOT NULL,
                  UNIQUE KEY unique_parentidsite_idsite (`parent_idsite`, `idsite`)");
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

    public function isRollUpIdSite($idSite)
    {
        try {
            $type = Site::getTypeFor($idSite);
        } catch (\Exception $e) {
            return false;
        }

        return !empty($type) && $type === self::TYPE_ROLLUP_SITE;
    }

    public function hasSiteTypeRollUp($site)
    {
        return !empty($site['type']) && $site['type'] === self::TYPE_ROLLUP_SITE;
    }

    /**
     * @return array
     */
    public function getParentIdSites()
    {
        $table = $this->tablePrefixed;

        $sites = $this->getDb()->fetchAll("SELECT DISTINCT parent_idsite FROM $table");
        return $this->makeSimpleArrayFromField($sites, 'parent_idsite');
    }

    /**
     * This method only returns the sites that are actually attached to a roll-up. If a roll-up contains another
     * roll-up, those sites won't be returned. Otherwise the selection in the websites manager would be wrong.
     * @param $parentIdSite
     * @return array
     */
    public function getChildIdSites($parentIdSite)
    {
        $table = $this->tablePrefixed;
        $sites = $this->getDb()->fetchAll("SELECT idsite FROM $table WHERE parent_idsite = ?", array($parentIdSite));
        return $this->makeSimpleArrayFromField($sites, 'idsite');
    }

    public function getSitesBelongingToRollUp($parentIdSite, $idSites = array())
    {
        $table = $this->tablePrefixed;
        $siteTable = Common::prefixTable('site');

        $sites = $this->getDb()->fetchAll("SELECT site.idsite, site.`type` FROM $table LEFT JOIN $siteTable site ON site.idsite = $table.idsite WHERE parent_idsite = ? and site.idsite is not null", array($parentIdSite));

        if (!empty($sites)) {
            foreach ($sites as $site) {
                if (in_array($site['idsite'], $idSites)) {
                    continue;
                }

                if ($site['idsite'] == $parentIdSite) {
                    // when it somehow references itself
                    continue;
                }

                $idSites[] = $site['idsite'];

                if ($site['type'] === Type::ID) {
                    $idSites = $this->getSitesBelongingToRollUp($site['idsite'], $idSites);
                }
            }
        }

        return $idSites;
    }

    public function getParentSitesOfChild($childIdSite, $parentIdSites = array())
    {
        $table = $this->tablePrefixed;

        $sites = $this->getDb()->fetchAll("SELECT parent_idsite FROM $table WHERE idsite = ?", array($childIdSite));

        if (!empty($sites)) {
            foreach ($sites as $site) {
                if (!in_array($site['parent_idsite'], $parentIdSites) && $site['parent_idsite'] != $childIdSite) {
                    $parentIdSites[] = $site['parent_idsite'];
                    $parentIdSites = $this->getParentSitesOfChild($site['parent_idsite'], $parentIdSites);
                }
            }
        }

        return $parentIdSites;
    }

    private function makeSimpleArrayFromField($rows, $field)
    {
        $fields = array();

        if (!empty($rows)) {
            foreach ($rows as $site) {
                $fields[] = $site[$field];
            }
        }

        return $fields;
    }

    public function addChildToParentSite($parentIdSite, $idSite)
    {
        $db = $this->getDb();
        $db->insert($this->tablePrefixed, array(
            'parent_idsite' => $parentIdSite,
            'idsite' => $idSite,
        ));
    }

    public function removeParentSite($parentIdSite)
    {
        $table = $this->tablePrefixed;

        $query = "DELETE FROM $table WHERE parent_idsite = ?";
        $bind = array($parentIdSite);

        $this->getDb()->query($query, $bind);
    }

    public function removeChildrenSites($childrenIdSite)
    {
        $table = $this->tablePrefixed;

        $query = "DELETE FROM $table WHERE idsite = ?";
        $bind = array($childrenIdSite);

        $this->getDb()->query($query, $bind);
    }
}

