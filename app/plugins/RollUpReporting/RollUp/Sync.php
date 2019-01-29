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

namespace Piwik\Plugins\RollUpReporting\RollUp;

use Piwik\API\Request;
use Piwik\Date;
use Piwik\Plugins\RollUpReporting\Model;
use Piwik\Plugins\RollUpReporting\SystemSettings;
use Piwik\Plugins\SitesManager\API;
use Piwik\Site;

class Sync
{
    /**
     * @var Model
     */
    private $model;

    /**
     * @var SystemSettings
     */
    private $settings;

    /**
     * Cache for parentidsites
     * @var null|array
     */
    private $parentIdSites = null;

    public function __construct(Model $model, SystemSettings $settings)
    {
        $this->model = $model;
        $this->settings = $settings;
    }

    private function getParentIdSites()
    {
        if (!isset($this->parentIdSites)) {
            $this->parentIdSites = $this->model->getParentIdSites();
        }

        return $this->parentIdSites;
    }

    public function updateRollUpSiteIfNeeded($idSiteRollUp)
    {
        // we do not call getChildrenSites for roll up because then we would need to make sure to first update
        // all rollups within this site before looking at this roll up and it gets quite hard to maintain and test etc
        // better to instead look at all sites that belong to this site
        $childIdSites = $this->model->getSitesBelongingToRollUp($idSiteRollUp);

        // we only want to look at regular websites, not add other roll up sites
        $parentSites = $this->getParentIdSites();
        $childIdSites = array_values(array_unique(array_diff($childIdSites, $parentSites)));

        if (!empty($childIdSites)) {
            $hasEcommerce = 0;
            $hasSiteSearch = 0;
            $creationDateString = Site::getCreationDateFor($idSiteRollUp);
            $earliestCreationDate = Date::factory($creationDateString);

            foreach ($childIdSites as $childIdSite) {
                if (!$hasEcommerce && Site::isEcommerceEnabledFor($childIdSite)) {
                    $hasEcommerce = 1;
                }
                if (!$hasSiteSearch && Site::isSiteSearchEnabledFor($childIdSite)) {
                    $hasSiteSearch = 1;
                }

                if ($this->settings->syncCreationDate->getValue()) {
                    $siteCreationDate = Date::factory(Site::getCreationDateFor($childIdSite));
                    if ($siteCreationDate->isEarlier($earliestCreationDate)) {
                        $earliestCreationDate = $siteCreationDate;
                        $creationDateString = $siteCreationDate->getDatetime();
                    }
                }
            }

            // we do not use Request::processRequest since this would trigger an API.SitesManager.updateSite.end
            // event and we might end up in this method again causing an endless loop
            API::getInstance()->updateSite($idSiteRollUp, $siteName = null, $urls = null, $hasEcommerce, $hasSiteSearch,
                $searchKeywordParameters = null,
                $searchCategoryParameters = null,
                $excludedIps = null,
                $excludedQueryParameters = null,
                $timezone = null,
                $currency = null,
                $group = null,
                $creationDateString);
        }
    }

    public function invalidateReportsSinceCreation($idSite)
    {
        $creationDate = Site::getCreationDateFor($idSite);
        $yearStart = Date::factory($creationDate)->toString('Y');
        $yearEnd = Date::now()->toString('Y');

        $range = range($yearStart, $yearEnd);
        $range = array_map(function ($date) {
            return $date . '-01-02';
        }, $range);

        Request::processRequest('CoreAdminHome.invalidateArchivedReports', array(
            'idSites' => $idSite,
            'dates' =>   implode(',', $range),
            'period' => 'year',
            'cascadeDown' => '1'
        ));
    }
}
