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
 *
 */

namespace Piwik\Plugins\RollUpReporting\Settings\Storage;

use Piwik\Container\StaticContainer;
use Exception;
use Piwik\Option;
use Piwik\Plugins\RollUpReporting\Model;
use Piwik\Plugins\SitesManager\Model as SitesModel;
use Piwik\Settings\Storage\Backend\BackendInterface;

/**
 * Roll Up Backend stores everything in a site_rollup table instead of in the site settings table to have data
 * better and especially much faster fetchable.
 */
class RollUpBackend implements BackendInterface
{
    /**
     * @var int
     */
    private $idSite;

    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var Model
     */
    private $model;

    /**
     * @var SitesModel
     */
    private $siteModel;

    public function __construct($idSite, $fieldName)
    {
        if (empty($idSite)) {
            throw new Exception('No idSite given for RollUpBackend backend');
        }

        if (empty($fieldName)) {
            throw new Exception('No fieldName given for RollUpBackend backend');
        }

        $this->fieldName = $fieldName;
        $this->idSite = (int) $idSite;
        $this->model = StaticContainer::get('Piwik\Plugins\RollUpReporting\Model');
        $this->siteModel = StaticContainer::get('Piwik\Plugins\SitesManager\Model');
    }

    /**
     * @inheritdoc
     */
    public function getStorageId()
    {
        return 'RollUpSetting_' . $this->idSite;
    }

    /**
     * Saves (persists) the current setting values in the database.
     *
     * @param  array $values
     */
    public function save($values)
    {
        $beforeUpdate = $this->getSourceIdSites();

        $this->delete();

        if (!empty($values[$this->fieldName]) && is_array($values[$this->fieldName])) {
            $sourceIdSites = array_unique($values[$this->fieldName]);

            $hadAllWebsitesAlreadyBefore = false;
            if (in_array(Model::KEY_ALL_WEBSITES, $sourceIdSites, true)) {
                // roll up is configured for all websites, and had already all sites before, then we do not want to
                // invalidate any reports. But we do want to invalidate if it did not have all websites before and is
                // now assigned to all websites
                $hadAllWebsitesAlreadyBefore = self::hasAssignedAllSites($this->idSite);

                self::setAssignAllSites($this->idSite);
                $sourceIdSites = $this->siteModel->getSitesId();
                $parentIdSites = $this->model->getParentIdSites();
                $sourceIdSites = array_diff($sourceIdSites, $parentIdSites); // we do not want to add any roll-ups
                $sourceIdSites = array_diff($sourceIdSites, array($this->idSite)); // we do not want to assign it to itself
                $sourceIdSites = array_unique($sourceIdSites);

            } elseif (self::hasAssignedAllSites($this->idSite)) {
                self::unsetAssignAllSites($this->idSite);
            }

            foreach ($sourceIdSites as $value) {
                if (is_numeric($value)) {
                    // we make sure to add only numeric idsite values
                    $this->model->addChildToParentSite($this->idSite, (int) $value);
                }
            }

            sort($beforeUpdate);
            sort($sourceIdSites);

            if ($beforeUpdate != $sourceIdSites && !$hadAllWebsitesAlreadyBefore) {
                // we cannot invalidate for all websites since we would invalidate each time a new site is created
                // and it would be not needed to invalidate in this case. However, we want to invalidate if the user
                // changes from few sites to all websites for the first time
                $sync = StaticContainer::get('Piwik\Plugins\RollUpReporting\RollUp\Sync');
                $sync->invalidateReportsSinceCreation($this->idSite);
            }
        }
    }

    public static function setAssignAllSites($idSite)
    {
        Option::set('rollup_all_sites_idsite_' . (int) $idSite, (int) $idSite);
    }

    public static function unsetAssignAllSites($idSite)
    {
        Option::delete('rollup_all_sites_idsite_' . (int) $idSite);
    }

    public static function hasAssignedAllSites($idSite)
    {
        $sites = Option::get('rollup_all_sites_idsite_' . (int) $idSite);
        return !empty($sites);
    }

    public static function getAllRollUpsWithAllWebsites()
    {
        $siteIds = Option::getLike('rollup_all_sites_idsite_%');
        $siteIds = array_unique(array_values($siteIds));

        if (empty($siteIds) || !is_array($siteIds)) {
            return array();
        }

        return $siteIds;
    }

    private function getSourceIdSites()
    {
        return $this->model->getChildIdSites($this->idSite);
    }

    public function load()
    {
        $sites = array(Model::KEY_ALL_WEBSITES);

        if (!self::hasAssignedAllSites($this->idSite)) {
            $sites = $this->getSourceIdSites();
        }

        return array(
            $this->fieldName => $sites
        );
    }

    public function delete()
    {
        $this->model->removeParentSite($this->idSite);
    }

}
