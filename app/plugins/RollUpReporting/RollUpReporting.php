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

use Piwik\ArchiveProcessor\Rules;
use Piwik\Cache;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugins\RollUpReporting\Settings\Storage\RollUpBackend;
use Piwik\Site;
use Piwik\Widget\WidgetContainerConfig;
use Piwik\Widget\WidgetsList;
use Exception;
use Piwik\Plugin;

class RollUpReporting extends Plugin
{

    public function install()
    {
        $model = new Model();
        $model->install();
    }

    public function uninstall()
    {
        $model = new Model();
        $model->uninstall();
    }

    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Request.dispatch' => 'doNotRedirectIfRollUpSiteHasNoData',
            'Site.setSites' => 'addRollUpPrefixToSiteNames',
            'API.Goals.addGoal' => 'preventGoalCreationForRollUp',
            'API.SitesManager.getPatternMatchSites.end' => 'addRollUpPrefixToSiteNames',
            'API.SitesManager.getAllSites.end' => 'addRollUpPrefixToSiteNames',
            'API.SitesManager.getSitesWithAtLeastViewAccess.end' => 'addRollUpPrefixToSiteNames',
            'SitesManager.addSite.end' => 'updateParentSiteIfNeeded',
            'API.SitesManager.updateSite.end' => 'updateParentSitesIfNeeded',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'SitesManager.deleteSite.end' => 'onSiteDeleted',
            'Live.API.getIdSitesString' => 'addIdSitesToLiveQueries',
            'Live.visitorLogViewBeforeActionsInfo' => 'renderVisitorLogViewBeforeActionsInfo',
            'Live.visitorLogWidgetViewBeforeVisitInfo'  => 'renderVisitorLogWidgetViewBeforeVisitInfo',
            'Widget.filterWidgets' => 'removeNotCompatibleWidgets',
            'CronArchive.getIdSitesNotUsingTracker' => 'addIdSitesNotUsingTracker',
            'CronArchive.filterWebsiteIds' => 'makeSureToArchiveRollUpSiteAfterChildren',
            'API.Request.dispatch.end' => 'removeUrlsFromMetadataAttributeBecauseTheyWontWork',
            'ArchiveProcessor.Parameters.getIdSites' => 'addIdSitesToBeArchivedAtOnce',
            'MultiSites.filterRowsForTotalsCalculation' => 'filterRowsForMultiSitesTotalsCalculation'
        );
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'General_Remove';
        $translationKeys[] = 'General_Name';
        $translationKeys[] = 'General_Id';
        $translationKeys[] = 'General_Search';
        $translationKeys[] = 'RollUpReporting_NoMeasurableAssignedYet';
        $translationKeys[] = 'RollUpReporting_AllMeasurablesAssigned';
        $translationKeys[] = 'RollUpReporting_MatchingSearchNotFound';
        $translationKeys[] = 'RollUpReporting_MatchingSearchConfirmTitle';
        $translationKeys[] = 'RollUpReporting_MatchingSearchConfirmTitleAlreadyAdded';
        $translationKeys[] = 'RollUpReporting_MatchingSearchMatchedAdd';
        $translationKeys[] = 'RollUpReporting_MatchingSearchMatchedAlreadyAdded';
        $translationKeys[] = 'RollUpReporting_FindMeasurables';
        $translationKeys[] = 'RollUpReporting_SelectMeasurable';
        $translationKeys[] = 'RollUpReporting_SelectMeasurablesMatchingSearch';
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/RollUpReporting/angularjs/manage-roll-up/manage-roll-up.directive.js';
        $jsFiles[] = 'plugins/RollUpReporting/angularjs/manage-roll-up/manage-roll-up.controller.js';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/RollUpReporting/angularjs/manage-roll-up/manage-roll-up.directive.less';
    }

    /**
     * @param DataTable\Row[] $rows
     */
    public function filterRowsForMultiSitesTotalsCalculation(&$rows)
    {
        foreach ($rows as $index => $row) {
            $idSite = $row->getColumn('label');
            if (is_numeric($idSite) && Site::getTypeFor($idSite) === Type::ID) {
                unset($rows[$index]);
            }
        }
    }

    /**
     * @param WidgetsList $list
     */
    public function removeNotCompatibleWidgets($list)
    {
        $idSite = $this->getIdSite();

        if (!$this->isRollUpIdSite($idSite)) {
            return;
        }

        $widgets = $list->getWidgetConfigs();

        foreach ($widgets as $widget) {
            if ($widget->getCategoryId() === 'Goals_Goals') {
                if ($widget->getSubcategoryId() === 'General_Overview' && $widget instanceof WidgetContainerConfig) {
                    if ($widget->getId() === 'GoalsOverview') {
                        $configs = $widget->getWidgetConfigs();
                        // we keep evolution overview on home page
                        foreach ($configs as $index => $config) {
                            $params = $config->getParameters();
                            if (!empty($params['idGoal'])) {
                                unset($configs[$index]);
                            }
                        }
                        $widget->setWidgetConfigs(array_values($configs));
                    } else {
                        $list->remove('Goals_Goals', $widget->getName());
                    }
                } else {
                    $list->remove('Goals_Goals', $widget->getName());
                }
            }
        }

        $list->remove('Funnels_Funnels');
        $list->remove('AbTesting_Experiments');
        $list->remove('HeatmapSessionRecording_Heatmaps');
        $list->remove('HeatmapSessionRecording_SessionRecordings');
    }

    public function preventGoalCreationForRollUp($parameters)
    {
        if (empty($parameters['idSite'])) {
            return;
        }

        if ($this->isRollUpIdSite($parameters['idSite'])) {
            throw new Exception(Piwik::translate('RollUpReporting_GoalCreationNotPossible'));
        }
    }

    private function getModel()
    {
        return StaticContainer::get('Piwik\Plugins\RollUpReporting\Model');
    }

    private function getSync()
    {
        return StaticContainer::get('Piwik\Plugins\RollUpReporting\RollUp\Sync');
    }

    private function getIdSite()
    {
        return Common::getRequestVar('idSite', 0, 'int');
    }

    public function onSiteDeleted($idSite)
    {
        $model = $this->getModel();
        $model->removeParentSite($idSite);

        $parentIdSites = $model->getParentSitesOfChild($idSite);
        $model->removeChildrenSites($idSite);

        if (!empty($parentIdSites)) {
            $sync = $this->getSync();

            foreach ($parentIdSites as $parentIdSite) {
                $sync->updateRollUpSiteIfNeeded($parentIdSite);
            }
        }

        RollUpBackend::unsetAssignAllSites($idSite);
    }

    public function updateParentSiteIfNeeded($idSite)
    {
        // here we make sure to update all existing roll-ups so they will have the newly created website assigned
        foreach (RollUpBackend::getAllRollUpsWithAllWebsites() as $websiteToUpdate) {
            if ($this->isRollUpIdSite($websiteToUpdate)) {
                // we need to check if it still is a roll-up and whether it has not been manually deleted yet etc.
                $backend = new RollUpBackend($websiteToUpdate, MeasurableSettings::ROLLUP_FIELDNAME);
                $backend->save(array(MeasurableSettings::ROLLUP_FIELDNAME => array(Model::KEY_ALL_WEBSITES)));
            }
        }

        if ($this->isRollUpIdSite($idSite)) {
            $sync = $this->getSync();
            $sync->updateRollUpSiteIfNeeded($idSite);
        }

        foreach (RollUpBackend::getAllRollUpsWithAllWebsites() as $websiteToUpdate) {
            if ($this->isRollUpIdSite($websiteToUpdate)) {
                // we need to check if it still is a roll-up and whether it has not been manually deleted yet etc.
                $sync = $this->getSync();
                $sync->updateRollUpSiteIfNeeded($websiteToUpdate);
            }
        }
    }

    public function updateParentSitesIfNeeded($returnedValue, $finalParameters)
    {
        if (empty($finalParameters['parameters']['idSite'])) {
            return;
        }

        $idSite = $finalParameters['parameters']['idSite'];
        $model = $this->getModel();

        $parentIdSites = $model->getParentSitesOfChild($idSite);

        if (!empty($parentIdSites)) {
            $sync = $this->getSync();

            foreach ($parentIdSites as $parentIdSite) {
                $sync->updateRollUpSiteIfNeeded($parentIdSite);
            }
        }
    }

    /**
     * We need to make sure to include the idSites of the roll-up children for the live queries, and not
     * the parent idsite.
     *
     * @param array $idSites
     */
    public function addIdSitesToLiveQueries(&$idSites)
    {
        if (empty($idSites)) {
            return;
        }

        if (is_array($idSites)) {
            $idSite = reset($idSites);
        } else {
            $idSite = $idSites;
        }

        if ($this->isRollUpIdSite($idSite)) {
            $model = $this->getModel();
            $childIdSites = $model->getSitesBelongingToRollUp($idSite);
            $parentSites = $this->getParentIdSites();

            // we do not add parent Roll-Up IDSite to childIdSites because we cannot archive any data for that site
            // so for consistency we also cannot show any data directly tracked into roll-up site in Live API
            $childIdSites = array_diff($childIdSites, $parentSites);

            if (!empty($childIdSites)) {
                $idSites = array_values(array_unique($childIdSites));
            }
        }
    }

    private function getParentIdSites()
    {
        $cache = Cache::getLazyCache();
        $cacheKey = 'RollUp_ParentSites';

        if ($cache->contains($cacheKey)) {
            $parentSites = $cache->fetch($cacheKey);
        } else {
            $model = $this->getModel();
            $parentSites = array_values(array_unique($model->getParentIdSites()));

            $cache->save($cacheKey, $parentSites, $ttl = 7200);
        }

        return $parentSites;
    }

    /**
     * @param array $idSites
     * @param Period $period
     */
    public function addIdSitesToBeArchivedAtOnce(&$idSites, $period)
    {
        if (empty($idSites)) {
            return;
        }

        // this is usually always just one idSite sent from core that this site needs to be archived unless another
        // plugin would listen to this event as well
        $idSite = reset($idSites);

        $shouldExecuteArchiveQueries = $period->getLabel() == 'day' || !Rules::shouldSkipUniqueVisitorsCalculationForMultipleSites();

        if (!$shouldExecuteArchiveQueries || !$this->isRollUpIdSite($idSite)) {
            // when period is not day, we do not generate the archives based on the mysql queries or based on
            // the aggregated archives of children sites, we always aggregate the day, week, month, ... archives
            // of the roll-up site.
            return;
        }

        $cache = Cache::getLazyCache();
        $cacheKey = 'RollUp_SitesToBeArchivedAtOnce_' . (int) $idSite;

        if ($cache->contains($cacheKey)) {
            $idSitesOfRollUpSite = $cache->fetch($cacheKey);
        } else {
            $model = $this->getModel();
            $idSitesOfRollUpSite = $model->getSitesBelongingToRollUp($idSite);

            $parentSites = $this->getParentIdSites();

            // we cannot add the rollUpIdSite, otherwise the archiver runs in an endless loop.
            $idSitesOfRollUpSite = array_values(array_unique(array_diff($idSitesOfRollUpSite, $parentSites)));

            $cache->save($cacheKey, $idSitesOfRollUpSite, $ttl = 7200);
        }

        if (!empty($idSitesOfRollUpSite)) {
            $idSites = $idSitesOfRollUpSite;
        }
    }

    public function doNotRedirectIfRollUpSiteHasNoData(&$module, &$action)
    {
        if ($module === 'SitesManager' && $action === 'siteWithoutData') {
            // usually, a roll-up site never has any logs written so it would redirect to the sites without data
            // page. If it actually is a roll-up site, we should undo that redirect and forward to regular
            // page CoreHome.index
            $idSite = $this->getIdSite();

            if ($this->isRollUpIdSite($idSite)) {
                $module = 'CoreHome';
                $action = 'index';
            }
        }
    }

    /**
     * for example we merge all urls of all measurables, which means if 2 measurables define a path like /cart
     * or /checkout, we cannot link to one specific site anymore. Therefore we rather remove all urls instead of
     * linking to "wrongly" attributed urls
     */
    public function removeUrlsFromMetadataAttributeBecauseTheyWontWork(&$returnedValue, $params)
    {
        $idSite = $this->getIdSite();

        $pluginsThatDefineUrlMetadata = array('Actions', 'Funnels', 'UsersFlow', 'CustomDimensions');

        if (!empty($returnedValue)
            && $this->isRollUpIdSite($idSite)
            && !empty($params['module'])
            && in_array($params['module'], $pluginsThatDefineUrlMetadata, $strict = true)
            && $returnedValue instanceof DataTable\DataTableInterface) {

            $returnedValue->filter('Piwik\Plugins\RollUpReporting\DataTable\Filter\RemoveUrlMetadata');
        }
    }

    public function addRollUpPrefixToSiteNames(&$sites)
    {
        $model = $this->getModel();

        $sitePrefix = '[' . Piwik::translate('RollUpReporting_RollUpSitePrefixName') . '] ';
        foreach ($sites as &$site) {
            if ($model->hasSiteTypeRollUp($site)) {
                if (strpos($site['name'], $sitePrefix) !== 0) {
                    // only add if not already added
                    $site['name'] = $sitePrefix . $site['name'];
                }
            }
        }
    }

    private function isRollUpIdSite($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        $model = $this->getModel();
        return $model->isRollUpIdSite($idSite);
    }

    /**
     * @param string $out
     * @param DataTable\Row $visitor
     */
    public function renderVisitorLogViewBeforeActionsInfo(&$out, $visitor)
    {
        $idSite = $visitor->getColumn('idSite');

        $out  = $this->addSiteVisitorLogInfo($out, $idSite, true);
    }

    public function renderVisitorLogWidgetViewBeforeVisitInfo(&$out, $visitor)
    {
        $idSite = $visitor['idSite'];

        $out = $this->addSiteVisitorLogInfo($out, $idSite, false);
    }

    private function addSiteVisitorLogInfo($out, $idSite, $addNewLine)
    {
        $parentSite = $this->getIdSite();

        if (!empty($idSite) && $this->isRollUpIdSite($parentSite)) {
            $siteName = Site::getNameFor($idSite);
            $out .= sprintf('<div>%1$s "%2$s" (ID %3$s)</div>', Piwik::translate('General_Website'), $siteName, (int)$idSite);
            if ($addNewLine) {
                $out .= '<br />';
            }
        }

        return $out;
    }

    public function makeSureToArchiveRollUpSiteAfterChildren(&$idSites)
    {
        $rollUpIdSites = $this->getParentIdSites();

        $rollUpIdSitesToBeArchived = array_intersect($idSites, $rollUpIdSites);

        if (empty($rollUpIdSitesToBeArchived)) {
            // when no roll-up sites need to be archived, we do not have to move them to the end
            return;
        }

        $idSitesToBeArchivedWithoutRollUpIdSites = array_diff($idSites, $rollUpIdSitesToBeArchived);

        // idsites have now the roll up idsites at the end
        $idSites = array_values(array_merge($idSitesToBeArchivedWithoutRollUpIdSites, $rollUpIdSitesToBeArchived));
    }

    public function addIdSitesNotUsingTracker(&$idSites)
    {
        $rollUpSites = $this->getParentIdSites();

        if (!empty($rollUpSites)) {
            $idSites = array_values(array_merge($idSites, $rollUpSites));
        }
    }

}
