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

use Piwik\API\Request;
use Piwik\Piwik;
use Piwik\Site;

/**
 * API for plugin RollUpReporting
 *
 * @method static \Piwik\Plugins\RollUpReporting\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * @var Model
     */
    private $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Adds a new Roll-Up.
     *
     * @param string $name
     * @param int[] $sourceIdSites if you want to automatically assign all websites, set ['all']
     * @param string $timezone
     * @param string $currency
     * @return mixed
     */
    public function addRollUp($name, $sourceIdSites, $timezone, $currency)
    {
        Piwik::checkUserHasSuperUserAccess();

        $idSite = Request::processRequest('SitesManager.addSite', array(
            'siteName' => $name,
            'type' => Type::ID,
            'timezone' => $timezone,
            'currency' => $currency,
            'settingValues' => array(
                'RollUpReporting' => array(
                    array('name' => MeasurableSettings::ROLLUP_FIELDNAME, 'value' => $sourceIdSites)
                )
            )
        ));

        return $idSite;
    }

    /**
     * Updates an existing Roll-Up.
     *
     * @param int $idSite
     * @param string $name
     * @param int[] $sourceIdSites if you want to automatically assign all websites, set ['all']
     * @param string $timezone
     * @param string $currency
     * @return mixed
     */
    public function updateRollUp($idSite, $name = null, $sourceIdSites = null, $timezone = null, $currency = null)
    {
        Piwik::checkUserHasSuperUserAccess();

        if (!$this->model->isRollUpIdSite($idSite)) {
            throw new \Exception('The given idSite is not a roll-up');
        }

        $params = array('idSite' => $idSite);

        if (isset($name)) {
            $params['siteName'] = $name;
        }

        if (isset($timezone)) {
            $params['timezone'] = $timezone;
        }

        if (isset($currency)) {
            $params['currency'] = $currency;
        }

        if (isset($sourceIdSites) && is_array($sourceIdSites)) {
            $params['settingValues'] = array(
                'RollUpReporting' => array(
                    array('name' => MeasurableSettings::ROLLUP_FIELDNAME, 'value' => $sourceIdSites)
                )
            );
        }

        Request::processRequest('SitesManager.updateSite', $params);
    }

    /**
     * Returns an array of Roll-Up properties.
     * @return array
     */
    public function getRollUps()
    {
        Piwik::checkUserHasSuperUserAccess();

        $rollUps = array();

        $parentIdSites = $this->model->getParentIdSites();

        foreach ($parentIdSites as $parentIdSite) {
            $site = Site::getSite($parentIdSite);
            $childIds = $this->model->getChildIdSites($parentIdSite);

            $rollUps[] = array(
                'idsite' => $parentIdSite,
                'name' => $site['name'],
                'timezone' => $site['timezone'],
                'currency' => $site['currency'],
                'sourceIdSites' => $childIds
            );
        }

        return $rollUps;
    }

}
