<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomDimensions;

use Piwik\Category\Subcategory;
use Piwik\Common;
use Piwik\Plugins\CustomDimensions\Dao\Configuration;
use Piwik\Plugins\CustomDimensions\Dao\LogTable;
use Piwik\Plugins\CustomDimensions\Tracker\CustomDimensionsRequestProcessor;
use Piwik\Tracker\Cache;
use Piwik\Tracker;
use Piwik\Plugin;

class CustomDimensions extends Plugin
{
    const SCOPE_ACTION = 'action';
    const SCOPE_VISIT = 'visit';
    const SCOPE_CONVERSION = 'conversion';

    /**
     * @var Configuration
     */
    private $configuration;

    private $isInstalled;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->configuration = new Configuration();
    }

    public function getReportsWithGoalMetrics(&$reportsWithGoals)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if ($idSite < 1) {
            return;
        }

        $dimensions = $this->configuration->getCustomDimensionsForSite($idSite);

        foreach ($dimensions as $dimension) {
            if (!$dimension['active']) {
                continue;
            }

            if ($dimension['scope'] !== self::SCOPE_VISIT) {
                continue;
            }

            $reportsWithGoals[] = array(
                'category' => 'VisitsSummary_VisitsSummary',
                'name'     => $dimension['name'],
                'module'   => $this->pluginName,
                'action'   => 'getCustomDimension',
                'parameters' => array('idDimension' => $dimension['idcustomdimension'])
            );
        }
    }

    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        if (!$this->isInstalled()) {
            return null;
        }

        return array(
            'Tracker.Cache.getSiteAttributes'  => 'addCustomDimensionsAttributes',
            'SitesManager.deleteSite.end'      => 'deleteCustomDimensionDefinitionsForSite',
            'AssetManager.getJavaScriptFiles'  => 'getJsFiles',
            'AssetManager.getStylesheetFiles'  => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Tracker.newConversionInformation' => 'addConversionInformation',
            'Tracker.getVisitFieldsToPersist'  => 'addVisitFieldsToPersist',
            'Tracker.setTrackerCacheGeneral'   => 'setTrackerCacheGeneral',
            'Category.addSubcategories' => 'addSubcategories',
            'Goals.getReportsWithGoalMetrics'  => 'getReportsWithGoalMetrics',
            'Dimension.addDimensions' => 'addDimensions',
            'Actions.getCustomActionDimensionFieldsAndJoins' => 'provideActionDimensionFields'
        );
    }

    public function addDimensions(&$instances)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        $dimensions = $this->configuration->getCustomDimensionsForSite($idSite);
        foreach ($dimensions as $dimension) {
            if (!$dimension['active']) {
                continue;
            }

            $custom = new CustomDimension();
            $custom->initCustomDimension($dimension);
            $instances[] = $custom;
        }
    }

    public function addSubcategories(&$subcategories)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$idSite) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (!$idSite) {
                return;
            }
        }

        $dimensions = $this->configuration->getCustomDimensionsForSite($idSite);
        $order = 70;

        foreach ($dimensions as $dimension) {
            if (!$dimension['active']) {
                continue;
            }

            $category = new Subcategory();
            $category->setName($dimension['name']);

            if ($dimension['scope'] === CustomDimensions::SCOPE_ACTION) {
                $category->setCategoryId('General_Actions');
            } elseif ($dimension['scope'] === CustomDimensions::SCOPE_VISIT) {
                $category->setCategoryId('General_Visitors');
            }

            $category->setId('customdimension' . $dimension['idcustomdimension']);
            $category->setOrder($order++);
            $subcategories[] = $category;
        }
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/CustomDimensions/angularjs/manage/model.js";
        $jsFiles[] = "plugins/CustomDimensions/angularjs/manage/list.controller.js";
        $jsFiles[] = "plugins/CustomDimensions/angularjs/manage/list.directive.js";
        $jsFiles[] = "plugins/CustomDimensions/angularjs/manage/edit.controller.js";
        $jsFiles[] = "plugins/CustomDimensions/angularjs/manage/edit.directive.js";
        $jsFiles[] = "plugins/CustomDimensions/angularjs/manage/manage.controller.js";
        $jsFiles[] = "plugins/CustomDimensions/angularjs/manage/manage.directive.js";
        $jsFiles[] = "plugins/CustomDimensions/javascripts/rowactions.js";
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/CustomDimensions/angularjs/manage/edit.directive.less";
        $stylesheets[] = "plugins/CustomDimensions/angularjs/manage/list.directive.less";
        $stylesheets[] = "plugins/CustomDimensions/stylesheets/reports.less";
    }

    public function install()
    {
        $this->configuration->install();

        foreach (self::getScopes() as $scope) {
            $tracking = new Dao\LogTable($scope);
            $tracking->install();
        }

        Cache::clearCacheGeneral();
        $this->isInstalled = true;
    }

    public function uninstall()
    {
        $this->configuration->uninstall();

        foreach (self::getScopes() as $scope) {
            $tracking = new Dao\LogTable($scope);
            $tracking->uninstall();
        }

        Cache::clearCacheGeneral();
        $this->isInstalled = false;
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    public function addCustomDimensionsAttributes(&$content, $idSite)
    {
        $dimensions = $this->configuration->getCustomDimensionsForSite($idSite);
        $active = array();

        foreach ($dimensions as $dimension) {
            if (!$dimension['active']) {
                continue;
            }

            $active[] = $dimension;
        }

        $content['custom_dimensions'] = $active;
    }

    public function deleteCustomDimensionDefinitionsForSite($idSite)
    {
        $this->configuration->deleteConfigurationsForSite($idSite);
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'General_Loading';
        $translationKeys[] = 'General_Id';
        $translationKeys[] = 'General_Name';
        $translationKeys[] = 'General_Action';
        $translationKeys[] = 'General_Cancel';
        $translationKeys[] = 'CorePluginsAdmin_Active';
        $translationKeys[] = 'Actions_ColumnPageURL';
        $translationKeys[] = 'Goals_PageTitle';
        $translationKeys[] = 'Goals_CaseSensitive';
        $translationKeys[] = 'CustomDimensions_CustomDimensions';
        $translationKeys[] = 'CustomDimensions_CustomDimensionsIntro';
        $translationKeys[] = 'CustomDimensions_CustomDimensionsIntroNext';
        $translationKeys[] = 'CustomDimensions_ScopeDescriptionVisit';
        $translationKeys[] = 'CustomDimensions_ScopeDescriptionVisitMoreInfo';
        $translationKeys[] = 'CustomDimensions_ScopeDescriptionAction';
        $translationKeys[] = 'CustomDimensions_ScopeDescriptionActionMoreInfo';
        $translationKeys[] = 'CustomDimensions_IncreaseAvailableCustomDimensionsTitle';
        $translationKeys[] = 'CustomDimensions_IncreaseAvailableCustomDimensionsTakesLong';
        $translationKeys[] = 'CustomDimensions_HowToCreateCustomDimension';
        $translationKeys[] = 'CustomDimensions_HowToManyCreateCustomDimensions';
        $translationKeys[] = 'CustomDimensions_ExampleCreateCustomDimensions';
        $translationKeys[] = 'CustomDimensions_HowToTrackManuallyTitle';
        $translationKeys[] = 'CustomDimensions_HowToTrackManuallyViaJs';
        $translationKeys[] = 'CustomDimensions_HowToTrackManuallyViaJsDetails';
        $translationKeys[] = 'CustomDimensions_HowToTrackManuallyViaPhp';
        $translationKeys[] = 'CustomDimensions_HowToTrackManuallyViaHttp';
        $translationKeys[] = 'CustomDimensions_Extractions';
        $translationKeys[] = 'CustomDimensions_ExtractionsHelp';
        $translationKeys[] = 'CustomDimensions_ExtractValue';
        $translationKeys[] = 'CustomDimensions_ExampleValue';
        $translationKeys[] = 'CustomDimensions_NoCustomDimensionConfigured';
        $translationKeys[] = 'CustomDimensions_ConfigureNewDimension';
        $translationKeys[] = 'CustomDimensions_ConfigureDimension';
        $translationKeys[] = 'CustomDimensions_XofYLeft';
        $translationKeys[] = 'CustomDimensions_CannotBeDeleted';
        $translationKeys[] = 'CustomDimensions_PageUrlParam';
        $translationKeys[] = 'CustomDimensions_NameAllowedCharacters';
        $translationKeys[] = 'CustomDimensions_NameIsRequired';
        $translationKeys[] = 'CustomDimensions_NameIsTooLong';
        $translationKeys[] = 'CustomDimensions_ExceptionDimensionDoesNotExist';
        $translationKeys[] = 'CustomDimensions_ExceptionDimensionIsNotActive';
        $translationKeys[] = 'CustomDimensions_DimensionCreated';
        $translationKeys[] = 'CustomDimensions_DimensionUpdated';
        $translationKeys[] = 'CustomDimensions_ColumnUniqueActions';
        $translationKeys[] = 'CustomDimensions_ColumnAvgTimeOnDimension';
        $translationKeys[] = 'CustomDimensions_CustomDimensionId';
    }

    public function addConversionInformation(&$conversion, $visitInformation, Tracker\Request $request)
    {
        $dimensions = CustomDimensionsRequestProcessor::getCachedCustomDimensions($request);

        // we copy all visit custom dimensions, but only if the index also exists in the conversion table
        // to not fail while conversion custom dimensions are added
        $conversionIndexes = $this->getCachedInstalledIndexesForScope(self::SCOPE_CONVERSION);
        $conversionIndexes = array_map(function ($index) {
            return (int) $index; // make sure we work with integers
        }, $conversionIndexes);

        foreach ($dimensions as $dimension) {
            $index = (int) $dimension['index'];
            if ($dimension['scope'] === self::SCOPE_VISIT && in_array($index, $conversionIndexes)) {
                $field = LogTable::buildCustomDimensionColumnName($dimension);

                if (array_key_exists($field, $visitInformation)) {
                    $conversion[$field] = $visitInformation[$field];
                }
            }
        }
    }

    private function isInstalled()
    {
        if (!isset($this->isInstalled)) {
            $names = Plugin\Manager::getInstance()->getInstalledPluginsName();
            // installed plugins are not yet loaded properly

            if (empty($names)) {
                return false;
            }

            $this->isInstalled = Plugin\Manager::getInstance()->isPluginInstalled($this->pluginName);
        }

        return $this->isInstalled;
    }

    public function addVisitFieldsToPersist(&$fields)
    {
        if (!$this->isInstalled()) {
            return;
        }

        $indexes = $this->getCachedInstalledIndexesForScope(self::SCOPE_VISIT);

        $fields[] = 'last_idlink_va';

        foreach ($indexes as $index) {
            $fields[] = LogTable::buildCustomDimensionColumnName($index);
        }
    }

    public function provideActionDimensionFields(&$fields, &$joins)
    {
        $logTable = new Dao\LogTable(CustomDimensions::SCOPE_ACTION);
        $indices = $logTable->getInstalledIndexes();

        foreach ($indices as $index) {
            $field    = Dao\LogTable::buildCustomDimensionColumnName($index);
            $fields[] = $field;
        }
    }

    public function getCachedInstalledIndexesForScope($scope)
    {
        $cache = Cache::getCacheGeneral();
        $key = 'custom_dimension_indexes_installed_' . $scope;

        if (empty($cache[$key])) {
            return array();
        }

        return $cache[$key];
    }

    public function setTrackerCacheGeneral(&$cacheContent)
    {
        foreach (self::getScopes() as $scope) {
            $tracking = new LogTable($scope);
            $cacheContent['custom_dimension_indexes_installed_' . $scope] = $tracking->getInstalledIndexes();
        }
    }

    public static function getScopes()
    {
        return array(self::SCOPE_VISIT, self::SCOPE_ACTION, self::SCOPE_CONVERSION);
    }

    /**
     * These are public scopes that are actually visible to the user, scope Conversion
     * is not really directly visible to the user and a user cannot manage/configure dimensions in scope conversion.
     */
    public static function getPublicScopes()
    {
        return array(self::SCOPE_VISIT, self::SCOPE_ACTION);
    }

    /**
     * These are public scopes that are actually visible to the user, scope Conversion
     * is not really directly visible to the user and a user cannot manage/configure dimensions in scope conversion.
     */
    public static function doesScopeSupportExtractions($scope)
    {
        return $scope === self::SCOPE_ACTION;
    }
}
