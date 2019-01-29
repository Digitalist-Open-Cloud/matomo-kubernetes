<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugin;

use Piwik\Application\Kernel\PluginList;
use Piwik\Cache;
use Piwik\Columns\Dimension;
use Piwik\Config;
use Piwik\Config as PiwikConfig;
use Piwik\Container\StaticContainer;
use Piwik\Development;
use Piwik\EventDispatcher;
use Piwik\Exception\PluginDeactivatedException;
use Piwik\Filesystem;
use Piwik\Log;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugin\Dimension\ActionDimension;
use Piwik\Plugin\Dimension\ConversionDimension;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\Marketplace\Api\Client;
use Piwik\Settings\Storage as SettingsStorage;
use Piwik\SettingsPiwik;
use Piwik\Theme;
use Piwik\Translation\Translator;
use Piwik\Updater;

require_once PIWIK_INCLUDE_PATH . '/core/EventDispatcher.php';

/**
 * The singleton that manages plugin loading/unloading and installation/uninstallation.
 */
class Manager
{
    /**
     * @return self
     */
    public static function getInstance()
    {
        return StaticContainer::get('Piwik\Plugin\Manager');
    }

    protected $pluginsToLoad = array();

    protected $doLoadPlugins = true;

    private $pluginsLoadedAndActivated;

    /**
     * @var Plugin[]
     */
    protected $loadedPlugins = array();
    /**
     * Default theme used in Piwik.
     */
    const DEFAULT_THEME = "Morpheus";

    protected $doLoadAlwaysActivatedPlugins = true;

    // These are always activated and cannot be deactivated
    protected $pluginToAlwaysActivate = array(
        'CoreHome',
        'Diagnostics',
        'CoreUpdater',
        'CoreAdminHome',
        'CoreConsole',
        'CorePluginsAdmin',
        'CoreVisualizations',
        'Installation',
        'SitesManager',
        'UsersManager',
        'Intl',
        'API',
        'Proxy',
        'LanguagesManager',
        'WebsiteMeasurable',

        // default Piwik theme, always enabled
        self::DEFAULT_THEME,
    );

    private $trackerPluginsNotToLoad = array();

    /**
     * @var PluginList
     */
    private $pluginList;

    public function __construct(PluginList $pluginList)
    {
        $this->pluginList = $pluginList;
    }

    /**
     * Loads plugin that are enabled
     */
    public function loadActivatedPlugins()
    {
        $pluginsToLoad = $this->getActivatedPluginsFromConfig();
        if (!SettingsPiwik::isInternetEnabled()) {
            $pluginsToLoad = array_filter($pluginsToLoad, function($name) {
                $plugin = Manager::makePluginClass($name);
                return !$plugin->requiresInternetConnection();
            });
        }
        $this->loadPlugins($pluginsToLoad);
    }

    /**
     * Called during Tracker
     */
    public function loadCorePluginsDuringTracker()
    {
        $pluginsToLoad = $this->pluginList->getActivatedPlugins();
        $pluginsToLoad = array_diff($pluginsToLoad, $this->getTrackerPluginsNotToLoad());
        $this->loadPlugins($pluginsToLoad);
    }

    /**
     * @return array names of plugins that have been loaded
     */
    public function loadTrackerPlugins()
    {
        $cacheId = 'PluginsTracker';
        $cache = Cache::getEagerCache();

        if ($cache->contains($cacheId)) {
            $pluginsTracker = $cache->fetch($cacheId);
        } else {
            $this->unloadPlugins();
            $this->loadActivatedPlugins();

            $pluginsTracker = array();

            foreach ($this->loadedPlugins as $pluginName => $plugin) {
                if ($this->isTrackerPlugin($plugin)) {
                    $pluginsTracker[] = $pluginName;
                }
            }

            if (!empty($pluginsTracker)) {
                $cache->save($cacheId, $pluginsTracker);
            }
        }

        if (empty($pluginsTracker)) {
            $this->unloadPlugins();
            return array();
        }

        $pluginsTracker = array_diff($pluginsTracker, $this->getTrackerPluginsNotToLoad());
        $this->doNotLoadAlwaysActivatedPlugins();
        $this->loadPlugins($pluginsTracker);

        // we could simply unload all plugins first before loading plugins but this way it is much faster
        // since we won't have to create each plugin again and we won't have to parse each plugin metadata file
        // again etc
        $this->makeSureOnlyActivatedPluginsAreLoaded();

        return $pluginsTracker;
    }

    /**
     * Do not load the specified plugins (used during testing, to disable Provider plugin)
     * @param array $plugins
     */
    public function setTrackerPluginsNotToLoad($plugins)
    {
        $this->trackerPluginsNotToLoad = $plugins;
    }

    /**
     * Get list of plugins to not load
     *
     * @return array
     */
    public function getTrackerPluginsNotToLoad()
    {
        return $this->trackerPluginsNotToLoad;
    }

    // If a plugin hooks onto at least an event starting with "Tracker.", we load the plugin during tracker
    const TRACKER_EVENT_PREFIX = 'Tracker.';

    /**
     * @param $pluginName
     * @return bool
     */
    public function isPluginOfficialAndNotBundledWithCore($pluginName)
    {
        static $gitModules;
        if (empty($gitModules)) {
            $gitModules = file_get_contents(PIWIK_INCLUDE_PATH . '/.gitmodules');
        }
        // All submodules are officially maintained plugins
        $isSubmodule = false !== strpos($gitModules, "plugins/" . $pluginName . "\n");
        return $isSubmodule;
    }

    /**
     * Update Plugins config
     *
     * @param array $pluginsToLoad Plugins
     */
    private function updatePluginsConfig($pluginsToLoad)
    {
        $pluginsToLoad = $this->pluginList->sortPluginsAndRespectDependencies($pluginsToLoad);
        $section = PiwikConfig::getInstance()->Plugins;
        $section['Plugins'] = $pluginsToLoad;
        PiwikConfig::getInstance()->Plugins = $section;
    }

    /**
     * Update PluginsInstalled config
     *
     * @param array $plugins Plugins
     */
    private function updatePluginsInstalledConfig($plugins)
    {
        $section = PiwikConfig::getInstance()->PluginsInstalled;
        $section['PluginsInstalled'] = $plugins;
        PiwikConfig::getInstance()->PluginsInstalled = $section;
    }

    public function clearPluginsInstalledConfig()
    {
        $this->updatePluginsInstalledConfig(array());
        PiwikConfig::getInstance()->forceSave();
    }

    /**
     * Returns true if plugin is always activated
     *
     * @param string $name Name of plugin
     * @return bool
     */
    private function isPluginAlwaysActivated($name)
    {
        return in_array($name, $this->pluginToAlwaysActivate);
    }

    /**
     * Returns true if the plugin can be uninstalled. Any non-core plugin can be uninstalled.
     *
     * @param $name
     * @return bool
     */
    private function isPluginUninstallable($name)
    {
        return !$this->isPluginBundledWithCore($name);
    }

    /**
     * Returns `true` if a plugin has been activated.
     *
     * @param string $name Name of plugin, eg, `'Actions'`.
     * @return bool
     * @api
     */
    public function isPluginActivated($name)
    {
        return in_array($name, $this->pluginsToLoad)
        || ($this->doLoadAlwaysActivatedPlugins && $this->isPluginAlwaysActivated($name));
    }

    /**
     * Returns `true` if a plugin requires an working internet connection
     *
     * @param string $name Name of plugin, eg, `'Actions'`.
     * @return bool
     * @throws \Exception
     */
    public function doesPluginRequireInternetConnection($name)
    {
        $plugin = $this->makePluginClass($name);
        return $plugin->requiresInternetConnection();
    }

    /**
     * Checks whether the given plugin is activated, if not triggers an exception.
     *
     * @param  string $pluginName
     * @throws PluginDeactivatedException
     */
    public function checkIsPluginActivated($pluginName)
    {
        if (!$this->isPluginActivated($pluginName)) {
            throw new PluginDeactivatedException($pluginName);
        }
    }

    /**
     * Returns `true` if plugin is loaded (in memory).
     *
     * @param string $name Name of plugin, eg, `'Acions'`.
     * @return bool
     * @api
     */
    public function isPluginLoaded($name)
    {
        return isset($this->loadedPlugins[$name]);
    }

    /**
     * Reads the directories inside the plugins/ directory and returns their names in an array
     *
     * @return array
     */
    public function readPluginsDirectory()
    {
        $pluginsName = _glob(self::getPluginsDirectory() . '*', GLOB_ONLYDIR);
        $result = array();
        if ($pluginsName != false) {
            foreach ($pluginsName as $path) {
                if (self::pluginStructureLooksValid($path)) {
                    $result[] = basename($path);
                }
            }
        }
        return $result;
    }

    public static function getPluginsDirectory()
    {
        return PIWIK_INCLUDE_PATH . '/plugins/';
    }

    /**
     * Deactivate plugin
     *
     * @param string $pluginName Name of plugin
     */
    public function deactivatePlugin($pluginName)
    {
        $this->clearCache($pluginName);

        // execute deactivate() to let the plugin do cleanups
        $this->executePluginDeactivate($pluginName);

        $this->unloadPluginFromMemory($pluginName);

        $this->removePluginFromConfig($pluginName);

        /**
         * Event triggered after a plugin has been deactivated.
         *
         * @param string $pluginName The plugin that has been deactivated.
         */
        Piwik::postEvent('PluginManager.pluginDeactivated', array($pluginName));
    }

    /**
     * Tries to find the given components such as a Menu or Tasks implemented by plugins.
     * This method won't cache the found components. If you need to find the same component multiple times you might
     * want to cache the result to save a tiny bit of time.
     *
     * @param string $componentName     The name of the component you want to look for. In case you request a
     *                                  component named 'Menu' it'll look for a file named 'Menu.php' within the
     *                                  root of all plugin folders that implement a class named
     *                                  Piwik\Plugin\$PluginName\Menu.
     * @param string $expectedSubclass  If not empty, a check will be performed whether a found file extends the
     *                                  given subclass. If the requested file exists but does not extend this class
     *                                  a warning will be shown to advice a developer to extend this certain class.
     *
     * @return \stdClass[]
     */
    public function findComponents($componentName, $expectedSubclass)
    {
        $plugins    = $this->getPluginsLoadedAndActivated();
        $components = array();

        foreach ($plugins as $plugin) {
            $component = $plugin->findComponent($componentName, $expectedSubclass);

            if (!empty($component)) {
                $components[] = $component;
            }
        }

        return $components;
    }

    public function findMultipleComponents($directoryWithinPlugin, $expectedSubclass)
    {
        $plugins = $this->getPluginsLoadedAndActivated();
        $found   = array();

        foreach ($plugins as $plugin) {
            $components = $plugin->findMultipleComponents($directoryWithinPlugin, $expectedSubclass);

            if (!empty($components)) {
                $found = array_merge($found, $components);
            }
        }

        return $found;
    }

    /**
     * Uninstalls a Plugin (deletes plugin files from the disk)
     * Only deactivated plugins can be uninstalled
     *
     * @param $pluginName
     * @throws \Exception
     * @return bool
     */
    public function uninstallPlugin($pluginName)
    {
        if ($this->isPluginLoaded($pluginName)) {
            throw new \Exception("To uninstall the plugin $pluginName, first disable it in Matomo > Settings > Plugins");
        }
        $this->loadAllPluginsAndGetTheirInfo();

        SettingsStorage\Backend\PluginSettingsTable::removeAllSettingsForPlugin($pluginName);
        SettingsStorage\Backend\MeasurableSettingsTable::removeAllSettingsForPlugin($pluginName);

        $this->executePluginDeactivate($pluginName);
        $this->executePluginUninstall($pluginName);

        $this->removePluginFromPluginsInstalledConfig($pluginName);

        $this->unloadPluginFromMemory($pluginName);

        $this->removePluginFromConfig($pluginName);
        $this->removeInstalledVersionFromOptionTable($pluginName);
        $this->clearCache($pluginName);

        self::deletePluginFromFilesystem($pluginName);
        if ($this->isPluginInFilesystem($pluginName)) {
            return false;
        }

        /**
         * Event triggered after a plugin has been uninstalled.
         *
         * @param string $pluginName The plugin that has been uninstalled.
         */
        Piwik::postEvent('PluginManager.pluginUninstalled', array($pluginName));

        return true;
    }

    /**
     * @param string $pluginName
     */
    private function clearCache($pluginName)
    {
        $this->resetTransientCache();
        Filesystem::deleteAllCacheOnUpdate($pluginName);
    }

    public static function deletePluginFromFilesystem($plugin)
    {
        Filesystem::unlinkRecursive(PIWIK_INCLUDE_PATH . '/plugins/' . $plugin, $deleteRootToo = true);
    }

    /**
     * Install loaded plugins
     *
     * @throws
     * @return array Error messages of plugin install fails
     */
    public function installLoadedPlugins()
    {
        Log::debug("Loaded plugins: " . implode(", ", array_keys($this->getLoadedPlugins())));
        
        foreach ($this->getLoadedPlugins() as $plugin) {
            $this->installPluginIfNecessary($plugin);
        }
    }

    /**
     * Activate the specified plugin and install (if needed)
     *
     * @param string $pluginName Name of plugin
     * @throws \Exception
     */
    public function activatePlugin($pluginName)
    {
        $plugins = $this->pluginList->getActivatedPlugins();
        if (in_array($pluginName, $plugins)) {
            // plugin is already activated
            return;
        }

        if (!$this->isPluginInFilesystem($pluginName)) {
            throw new \Exception("Plugin '$pluginName' cannot be found in the filesystem in plugins/ directory.");
        }
        $this->deactivateThemeIfTheme($pluginName);

        // Load plugin
        $plugin = $this->loadPlugin($pluginName);
        if ($plugin === null) {
            throw new \Exception("The plugin '$pluginName' was found in the filesystem, but could not be loaded.'");
        }
        $this->installPluginIfNecessary($plugin);
        $plugin->activate();

        EventDispatcher::getInstance()->postPendingEventsTo($plugin);

        $this->pluginsToLoad[] = $pluginName;

        $this->updatePluginsConfig($this->pluginsToLoad);
        PiwikConfig::getInstance()->forceSave();

        $this->clearCache($pluginName);

        /**
         * Event triggered after a plugin has been activated.
         *
         * @param string $pluginName The plugin that has been activated.
         */
        Piwik::postEvent('PluginManager.pluginActivated', array($pluginName));
    }

    public function isPluginInFilesystem($pluginName)
    {
        $existingPlugins = $this->readPluginsDirectory();
        $isPluginInFilesystem = array_search($pluginName, $existingPlugins) !== false;
        return $this->isValidPluginName($pluginName)
        && $isPluginInFilesystem;
    }

    /**
     * Returns the currently enabled theme.
     *
     * If no theme is enabled, the **Morpheus** plugin is returned (this is the base and default theme).
     *
     * @return Plugin
     * @api
     */
    public function getThemeEnabled()
    {
        $plugins = $this->getLoadedPlugins();

        $theme = false;
        foreach ($plugins as $plugin) {
            /* @var $plugin Plugin */
            if ($plugin->isTheme()
                && $this->isPluginActivated($plugin->getPluginName())
            ) {
                if ($plugin->getPluginName() != self::DEFAULT_THEME) {
                    return $plugin; // enabled theme (not default)
                }
                $theme = $plugin; // default theme
            }
        }
        return $theme;
    }

    /**
     * @param string $themeName
     * @throws \Exception
     * @return Theme
     */
    public function getTheme($themeName)
    {
        $plugins = $this->getLoadedPlugins();

        foreach ($plugins as $plugin) {
            if ($plugin->isTheme() && $plugin->getPluginName() == $themeName) {
                return new Theme($plugin);
            }
        }
        throw new \Exception('Theme not found : ' . $themeName);
    }

    public function getNumberOfActivatedPluginsExcludingAlwaysActivated()
    {
        $counter = 0;

        $pluginNames = $this->getLoadedPluginsName();
        foreach ($pluginNames as $pluginName) {
            if ($this->isPluginActivated($pluginName)
                && !$this->isPluginAlwaysActivated($pluginName)) {
                $counter++;
            }
        }

        return $counter;
    }

    /**
     * Returns info regarding all plugins. Loads plugins that can be loaded.
     *
     * @return array An array that maps plugin names with arrays of plugin information. Plugin
     *               information consists of the following entries:
     *
     *               - **activated**: Whether the plugin is activated.
     *               - **alwaysActivated**: Whether the plugin should always be activated,
     *                                      or not.
     *               - **uninstallable**: Whether the plugin is uninstallable or not.
     *               - **invalid**: If the plugin is invalid, this property will be set to true.
     *                              If the plugin is not invalid, this property will not exist.
     *               - **info**: If the plugin was loaded, will hold the plugin information.
     *                           See {@link Piwik\Plugin::getInformation()}.
     * @api
     */
    public function loadAllPluginsAndGetTheirInfo()
    {
        /** @var Translator $translator */
        $translator = StaticContainer::get('Piwik\Translation\Translator');

        $plugins = array();

        $listPlugins = array_merge(
            $this->readPluginsDirectory(),
            $this->pluginList->getActivatedPlugins()
        );
        $listPlugins = array_unique($listPlugins);
        $internetFeaturesEnabled = SettingsPiwik::isInternetEnabled();
        foreach ($listPlugins as $pluginName) {
            // Hide plugins that are never going to be used
            if ($this->isPluginBogus($pluginName)) {
                continue;
            }

            // If the plugin is not core and looks bogus, do not load
            if ($this->isPluginThirdPartyAndBogus($pluginName)) {
                $info = array(
                    'invalid'         => true,
                    'activated'       => false,
                    'alwaysActivated' => false,
                    'uninstallable'   => true,
                );
            } else {
                $translator->addDirectory(self::getPluginsDirectory() . $pluginName . '/lang');
                $this->loadPlugin($pluginName);
                $info = array(
                    'activated'       => $this->isPluginActivated($pluginName),
                    'alwaysActivated' => $this->isPluginAlwaysActivated($pluginName),
                    'uninstallable'   => $this->isPluginUninstallable($pluginName),
                );
            }

            $plugins[$pluginName] = $info;
        }

        $loadedPlugins = $this->getLoadedPlugins();
        foreach ($loadedPlugins as $oPlugin) {
            $pluginName = $oPlugin->getPluginName();

            $info = array(
                'info' => $oPlugin->getInformation(),
                'activated'       => $this->isPluginActivated($pluginName),
                'alwaysActivated' => $this->isPluginAlwaysActivated($pluginName),
                'missingRequirements' => $oPlugin->getMissingDependenciesAsString(),
                'uninstallable' => $this->isPluginUninstallable($pluginName),
            );
            $plugins[$pluginName] = $info;
        }

        return $plugins;
    }

    protected static function isManifestFileFound($path)
    {
        return file_exists($path . "/" . MetadataLoader::PLUGIN_JSON_FILENAME);
    }

    /**
     * Returns `true` if the plugin is bundled with core or `false` if it is third party.
     *
     * @param string $name The name of the plugin, eg, `'Actions'`.
     * @return bool
     */
    public function isPluginBundledWithCore($name)
    {
        return $this->isPluginEnabledByDefault($name)
        || in_array($name, $this->pluginList->getCorePluginsDisabledByDefault())
        || $name == self::DEFAULT_THEME;
    }

    /**
     * @param $pluginName
     * @return bool
     * @ignore
     */
    public function isPluginThirdPartyAndBogus($pluginName)
    {
        if ($this->isPluginBundledWithCore($pluginName)) {
            return false;
        }
        if ($this->isPluginBogus($pluginName)) {
            return true;
        }

        $path = $this->getPluginsDirectory() . $pluginName;
        if (!$this->isManifestFileFound($path)) {
            return true;
        }
        return false;
    }

    /**
     * Load AND activates the specified plugins. It will also overwrite all previously loaded plugins, so it acts
     * as a setter.
     *
     * @param array $pluginsToLoad Array of plugins to load.
     */
    public function loadPlugins(array $pluginsToLoad)
    {
        $this->resetTransientCache();
        $this->pluginsToLoad = $this->makePluginsToLoad($pluginsToLoad);
        $this->reloadActivatedPlugins();
    }

    /**
     * Disable plugin loading.
     */
    public function doNotLoadPlugins()
    {
        $this->doLoadPlugins = false;
    }

    /**
     * Disable loading of "always activated" plugins.
     */
    public function doNotLoadAlwaysActivatedPlugins()
    {
        $this->doLoadAlwaysActivatedPlugins = false;
    }

    /**
     * Execute postLoad() hook for loaded plugins
     */
    public function postLoadPlugins()
    {
        $plugins = $this->getLoadedPlugins();
        foreach ($plugins as $plugin) {
            $plugin->postLoad();
        }
    }

    /**
     * Returns an array containing the plugins class names (eg. 'UserCountry' and NOT 'UserCountry')
     *
     * @return array
     */
    public function getLoadedPluginsName()
    {
        return array_keys($this->getLoadedPlugins());
    }

    /**
     * Returns an array mapping loaded plugin names with their plugin objects, eg,
     *
     *     array(
     *         'UserCountry' => Plugin $pluginObject,
     *         'UserLanguage' => Plugin $pluginObject,
     *     );
     *
     * @return Plugin[]
     */
    public function getLoadedPlugins()
    {
        return $this->loadedPlugins;
    }

    /**
     * @param  string $piwikVersion
     * @return Plugin[]
     */
    public function getIncompatiblePlugins($piwikVersion)
    {
        $plugins = $this->getLoadedPlugins();

        $incompatible = array();
        foreach ($plugins as $plugin) {
            if ($plugin->hasMissingDependencies($piwikVersion)) {
                $incompatible[] = $plugin;
            }
        }

        return $incompatible;
    }

    /**
     * Returns an array of plugins that are currently loaded and activated,
     * mapping loaded plugin names with their plugin objects, eg,
     *
     *     array(
     *         'UserCountry' => Plugin $pluginObject,
     *         'UserLanguage' => Plugin $pluginObject,
     *     );
     *
     * @return Plugin[]
     */
    public function getPluginsLoadedAndActivated()
    {
        if (is_null($this->pluginsLoadedAndActivated)) {
            $enabled = $this->getActivatedPlugins();

            if (empty($enabled)) {
                return array();
            }

            $plugins = $this->getLoadedPlugins();
            $enabled = array_combine($enabled, $enabled);
            $plugins = array_intersect_key($plugins, $enabled);

            $this->pluginsLoadedAndActivated = $plugins;
        }

        return $this->pluginsLoadedAndActivated;
    }

    /**
     * Returns a list of all names of currently activated plugin eg,
     *
     *     array(
     *         'UserCountry'
     *         'UserLanguage'
     *     );
     *
     * @return string[]
     */
    public function getActivatedPlugins()
    {
        return $this->pluginsToLoad;
    }

    public function getActivatedPluginsFromConfig()
    {
        $plugins = $this->pluginList->getActivatedPlugins();

        return $this->makePluginsToLoad($plugins);
    }

    /**
     * Returns a Plugin object by name.
     *
     * @param string $name The name of the plugin, eg, `'Actions'`.
     * @throws \Exception If the plugin has not been loaded.
     * @return Plugin
     */
    public function getLoadedPlugin($name)
    {
        if (!isset($this->loadedPlugins[$name]) || is_null($this->loadedPlugins[$name])) {
            throw new \Exception("The plugin '$name' has not been loaded.");
        }
        return $this->loadedPlugins[$name];
    }

    /**
     * Load the plugins classes installed.
     * Register the observers for every plugin.
     */
    private function reloadActivatedPlugins()
    {
        $pluginsToPostPendingEventsTo = array();
        foreach ($this->pluginsToLoad as $pluginName) {
            $pluginsToPostPendingEventsTo = $this->reloadActivatedPlugin($pluginName, $pluginsToPostPendingEventsTo);
        }

        // post pending events after all plugins are successfully loaded
        foreach ($pluginsToPostPendingEventsTo as $plugin) {
            EventDispatcher::getInstance()->postPendingEventsTo($plugin);
        }
    }

    private function reloadActivatedPlugin($pluginName, $pluginsToPostPendingEventsTo)
    {
        if ($this->isPluginLoaded($pluginName) || $this->isPluginThirdPartyAndBogus($pluginName)) {
            return $pluginsToPostPendingEventsTo;
        }

        $newPlugin = $this->loadPlugin($pluginName);

        if ($newPlugin === null) {
            return $pluginsToPostPendingEventsTo;
        }

        $requirements = $newPlugin->getMissingDependencies();

        if (!empty($requirements)) {
            foreach ($requirements as $requirement) {
                $possiblePluginName = $requirement['requirement'];
                if (in_array($possiblePluginName, $this->pluginsToLoad, $strict = true)) {
                    $pluginsToPostPendingEventsTo = $this->reloadActivatedPlugin($possiblePluginName, $pluginsToPostPendingEventsTo);
                }
            }
        }

        if ($newPlugin->hasMissingDependencies()) {
            $this->unloadPluginFromMemory($pluginName);

            // at this state we do not know yet whether current user has super user access. We do not even know
            // if someone is actually logged in.
            $message  = Piwik::translate('CorePluginsAdmin_WeCouldNotLoadThePluginAsItHasMissingDependencies', array($pluginName, $newPlugin->getMissingDependenciesAsString()));
            $message .= ' ';
            $message .= Piwik::translate('General_PleaseContactYourPiwikAdministrator');

            $notification = new Notification($message);
            $notification->context = Notification::CONTEXT_ERROR;
            Notification\Manager::notify('PluginManager_PluginUnloaded', $notification);
            return $pluginsToPostPendingEventsTo;
        }

        if ($newPlugin->isPremiumFeature()
            && SettingsPiwik::isInternetEnabled()
            && !Development::isEnabled()
            && $this->isPluginActivated('Marketplace')
            && $this->isPluginActivated($pluginName)) {

            $cacheKey = 'MarketplacePluginMissingLicense' . $pluginName;
            $cache = self::getLicenseCache();

            if ($cache->contains($cacheKey)) {
                $pluginLicenseInfo = $cache->fetch($cacheKey);
            } else {
                try {
                    $plugins = StaticContainer::get('Piwik\Plugins\Marketplace\Plugins');
                    $licenseInfo = $plugins->getLicenseValidInfo($pluginName);
                } catch (\Exception $e) {
                    $licenseInfo = array();
                }

                $pluginLicenseInfo = array('missing' => !empty($licenseInfo['isMissingLicense']));
                $sixHours = 3600 * 6;
                $cache->save($cacheKey, $pluginLicenseInfo, $sixHours);
            }

            if (!empty($pluginLicenseInfo['missing']) && (!defined('PIWIK_TEST_MODE') || !PIWIK_TEST_MODE)) {
                $this->unloadPluginFromMemory($pluginName);
                return $pluginsToPostPendingEventsTo;
            }
        }

        $pluginsToPostPendingEventsTo[] = $newPlugin;

        return $pluginsToPostPendingEventsTo;
    }

    public static function getLicenseCache()
    {
        return Cache::getLazyCache();
    }

    public function getIgnoredBogusPlugins()
    {
        $ignored = array();
        foreach ($this->pluginsToLoad as $pluginName) {
            if ($this->isPluginThirdPartyAndBogus($pluginName)) {
                $ignored[] = $pluginName;
            }
        }
        return $ignored;
    }

    /**
     * Returns the name of all plugins found in this Piwik instance
     * (including those not enabled and themes)
     *
     * @return array
     */
    public static function getAllPluginsNames()
    {
        $pluginList = StaticContainer::get('Piwik\Application\Kernel\PluginList');

        $pluginsToLoad = array_merge(
            self::getInstance()->readPluginsDirectory(),
            $pluginList->getCorePluginsDisabledByDefault()
        );
        $pluginsToLoad = array_values(array_unique($pluginsToLoad));
        return $pluginsToLoad;
    }

    /**
     * Loads the plugin filename and instantiates the plugin with the given name, eg. UserCountry.
     * Contrary to loadPlugins() it does not activate the plugin, it only loads it.
     *
     * @param string $pluginName
     * @throws \Exception
     * @return Plugin|null
     */
    public function loadPlugin($pluginName)
    {
        if (isset($this->loadedPlugins[$pluginName])) {
            return $this->loadedPlugins[$pluginName];
        }
        $newPlugin = $this->makePluginClass($pluginName);

        $this->addLoadedPlugin($pluginName, $newPlugin);
        return $newPlugin;
    }

    public function isValidPluginName($pluginName)
    {
        return (bool) preg_match('/^[a-zA-Z]([a-zA-Z0-9_]*)$/D', $pluginName);
    }

    /**
     * @param $pluginName
     * @return Plugin
     * @throws \Exception
     */
    protected function makePluginClass($pluginName)
    {
        $pluginFileName = sprintf("%s/%s.php", $pluginName, $pluginName);
        $pluginClassName = $pluginName;

        if (!$this->isValidPluginName($pluginName)) {
            throw new \Exception(sprintf("The plugin filename '%s' is not a valid plugin name", $pluginFileName));
        }

        $path = self::getPluginsDirectory() . $pluginFileName;

        if (!file_exists($path)) {
            // Create the smallest minimal Piwik Plugin
            // Eg. Used for Morpheus default theme which does not have a Morpheus.php file
            return new Plugin($pluginName);
        }

        require_once $path;

        $namespacedClass = $this->getClassNamePlugin($pluginName);
        if (!class_exists($namespacedClass, false)) {
            throw new \Exception("The class $pluginClassName couldn't be found in the file '$path'");
        }
        $newPlugin = new $namespacedClass;

        if (!($newPlugin instanceof Plugin)) {
            throw new \Exception("The plugin $pluginClassName in the file $path must inherit from Plugin.");
        }
        return $newPlugin;
    }

    protected function getClassNamePlugin($pluginName)
    {
        $className = $pluginName;
        if ($pluginName == 'API') {
            $className = 'Plugin';
        }
        return "\\Piwik\\Plugins\\$pluginName\\$className";
    }

    private function resetTransientCache()
    {
        $this->pluginsLoadedAndActivated = null;
    }

    /**
     * Unload plugin
     *
     * @param Plugin|string $plugin
     * @throws \Exception
     */
    public function unloadPlugin($plugin)
    {
        $this->resetTransientCache();

        if (!($plugin instanceof Plugin)) {
            $oPlugin = $this->loadPlugin($plugin);
            if ($oPlugin === null) {
                unset($this->loadedPlugins[$plugin]);
                return;
            }

            $plugin = $oPlugin;
        }

        unset($this->loadedPlugins[$plugin->getPluginName()]);
    }

    /**
     * Unload all loaded plugins
     */
    public function unloadPlugins()
    {
        $this->resetTransientCache();

        $pluginsLoaded = $this->getLoadedPlugins();
        foreach ($pluginsLoaded as $plugin) {
            $this->unloadPlugin($plugin);
        }
    }

    /**
     * Install a specific plugin
     *
     * @param Plugin $plugin
     * @throws \Piwik\Plugin\PluginException if installation fails
     */
    private function executePluginInstall(Plugin $plugin)
    {
        try {
            $plugin->install();
        } catch (\Exception $e) {
            throw new \Piwik\Plugin\PluginException($plugin->getPluginName(), $e->getMessage());
        }
    }

    /**
     * Add a plugin in the loaded plugins array
     *
     * @param string $pluginName plugin name without prefix (eg. 'UserCountry')
     * @param Plugin $newPlugin
     * @internal
     */
    public function addLoadedPlugin($pluginName, Plugin $newPlugin)
    {
        $this->resetTransientCache();

        $this->loadedPlugins[$pluginName] = $newPlugin;
    }

    /**
     * Return names of all installed plugins.
     *
     * @return array
     * @api
     */
    public function getInstalledPluginsName()
    {
        $pluginNames = Config::getInstance()->PluginsInstalled['PluginsInstalled'];
        return $pluginNames;
    }

    /**
     * Returns names of plugins that should be loaded, but cannot be since their
     * files cannot be found.
     *
     * @return array
     * @api
     */
    public function getMissingPlugins()
    {
        $missingPlugins = array();

        $plugins = $this->pluginList->getActivatedPlugins();

        foreach ($plugins as $pluginName) {
            // if a plugin is listed in the config, but is not loaded, it does not exist in the folder
            if (!$this->isPluginLoaded($pluginName) && !$this->isPluginBogus($pluginName) &&
                !($this->doesPluginRequireInternetConnection($pluginName) && !SettingsPiwik::isInternetEnabled())) {
                $missingPlugins[] = $pluginName;
            }
        }

        return $missingPlugins;
    }

    /**
     * Install a plugin, if necessary
     *
     * @param Plugin $plugin
     */
    private function installPluginIfNecessary(Plugin $plugin)
    {
        $pluginName = $plugin->getPluginName();
        $saveConfig = false;

        // is the plugin already installed or is it the first time we activate it?
        $pluginsInstalled = $this->getInstalledPluginsName();

        if (!$this->isPluginInstalled($pluginName)) {
            $this->executePluginInstall($plugin);
            $pluginsInstalled[] = $pluginName;
            $this->updatePluginsInstalledConfig($pluginsInstalled);
            $updater = new Updater();
            $updater->markComponentSuccessfullyUpdated($plugin->getPluginName(), $plugin->getVersion(), $isNew = true);
            $saveConfig = true;

            /**
             * Event triggered after a new plugin has been installed.
             *
             * Note: Might be triggered more than once if the config file is not writable
             *
             * @param string $pluginName The plugin that has been installed.
             */
            Piwik::postEvent('PluginManager.pluginInstalled', array($pluginName));
        }

        if ($saveConfig) {
            PiwikConfig::getInstance()->forceSave();
            $this->clearCache($pluginName);
        }
    }

    public function isTrackerPlugin(Plugin $plugin)
    {
        if (!$this->isPluginInstalled($plugin->getPluginName())) {
            return false;
        }
        
        if ($plugin->isTrackerPlugin()) {
            return true;
        }

        $dimensions = VisitDimension::getDimensions($plugin);
        if (!empty($dimensions)) {
            return true;
        }

        $dimensions = ActionDimension::getDimensions($plugin);
        if (!empty($dimensions)) {
            return true;
        }

        $hooks = $plugin->getListHooksRegistered();
        $hookNames = array_keys($hooks);
        foreach ($hookNames as $name) {
            if (strpos($name, self::TRACKER_EVENT_PREFIX) === 0) {
                return true;
            }
            if ($name === 'Request.initAuthenticationObject') {
                return true;
            }
        }

        $dimensions = ConversionDimension::getDimensions($plugin);
        if (!empty($dimensions)) {
            return true;
        }

        return false;
    }

    private static function pluginStructureLooksValid($path)
    {
        $name = basename($path);
        return file_exists($path . "/" . $name . ".php")
        || self::isManifestFileFound($path);
    }

    /**
     * @param $pluginName
     */
    private function removePluginFromPluginsInstalledConfig($pluginName)
    {
        $pluginsInstalled = Config::getInstance()->PluginsInstalled['PluginsInstalled'];
        $key = array_search($pluginName, $pluginsInstalled);
        if ($key !== false) {
            unset($pluginsInstalled[$key]);
        }

        $this->updatePluginsInstalledConfig($pluginsInstalled);
    }

    /**
     * @param $pluginName
     */
    private function removePluginFromPluginsConfig($pluginName)
    {
        $pluginsEnabled = $this->pluginList->getActivatedPlugins();
        $key = array_search($pluginName, $pluginsEnabled);
        if ($key !== false) {
            unset($pluginsEnabled[$key]);
        }
        $this->updatePluginsConfig($pluginsEnabled);
    }

    /**
     * @param $pluginName
     * @return bool
     */
    private function isPluginBogus($pluginName)
    {
        $bogusPlugins = array(
            'PluginMarketplace', //defines a plugin.json but 1.x Piwik plugin
            'DoNotTrack', // Removed in 2.0.3
            'AnonymizeIP', // Removed in 2.0.3
        );
        return in_array($pluginName, $bogusPlugins);
    }

    private function deactivateThemeIfTheme($pluginName)
    {
        // Only one theme enabled at a time
        $themeEnabled = $this->getThemeEnabled();
        if ($themeEnabled
            && $themeEnabled->getPluginName() != self::DEFAULT_THEME) {
            $themeAlreadyEnabled = $themeEnabled->getPluginName();

            $plugin = $this->loadPlugin($pluginName);
            if ($plugin->isTheme()) {
                $this->deactivatePlugin($themeAlreadyEnabled);
            }
        }
    }

    /**
     * @param $pluginName
     */
    private function executePluginDeactivate($pluginName)
    {
        if (!$this->isPluginBogus($pluginName)) {
            $plugin = $this->loadPlugin($pluginName);
            if ($plugin !== null) {
                $plugin->deactivate();
            }
        }
    }

    /**
     * @param $pluginName
     */
    private function unloadPluginFromMemory($pluginName)
    {
        $this->unloadPlugin($pluginName);

        $key = array_search($pluginName, $this->pluginsToLoad);
        if ($key !== false) {
            unset($this->pluginsToLoad[$key]);
        }
    }

    /**
     * @param $pluginName
     */
    private function removePluginFromConfig($pluginName)
    {
        $this->removePluginFromPluginsConfig($pluginName);
        PiwikConfig::getInstance()->forceSave();
    }

    /**
     * @param $pluginName
     */
    private function executePluginUninstall($pluginName)
    {
        try {
            $plugin = $this->getLoadedPlugin($pluginName);
            $plugin->uninstall();
        } catch (\Exception $e) {
        }

        if (empty($plugin)) {
            return;
        }

        try {
            $visitDimensions = VisitDimension::getAllDimensions();

            foreach (VisitDimension::getDimensions($plugin) as $dimension) {
                $this->uninstallDimension(VisitDimension::INSTALLER_PREFIX, $dimension, $visitDimensions);
            }
        } catch (\Exception $e) {
        }

        try {
            $actionDimensions = ActionDimension::getAllDimensions();

            foreach (ActionDimension::getDimensions($plugin) as $dimension) {
                $this->uninstallDimension(ActionDimension::INSTALLER_PREFIX, $dimension, $actionDimensions);
            }
        } catch (\Exception $e) {
        }

        try {
            $conversionDimensions = ConversionDimension::getAllDimensions();

            foreach (ConversionDimension::getDimensions($plugin) as $dimension) {
                $this->uninstallDimension(ConversionDimension::INSTALLER_PREFIX, $dimension, $conversionDimensions);
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param VisitDimension|ActionDimension|ConversionDimension $dimension
     * @param VisitDimension[]|ActionDimension[]|ConversionDimension[] $allDimensions
     * @return bool
     */
    private function doesAnotherPluginDefineSameColumnWithDbEntry($dimension, $allDimensions)
    {
        $module = $dimension->getModule();
        $columnName = $dimension->getColumnName();

        foreach ($allDimensions as $dim) {
            if ($dim->getColumnName() === $columnName &&
                $dim->hasColumnType() &&
                $dim->getModule() !== $module) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $prefix column installer prefix
     * @param ConversionDimension|VisitDimension|ActionDimension $dimension
     * @param VisitDimension[]|ActionDimension[]|ConversionDimension[] $allDimensions
     */
    private function uninstallDimension($prefix, Dimension $dimension, $allDimensions)
    {
        if (!$this->doesAnotherPluginDefineSameColumnWithDbEntry($dimension, $allDimensions)) {
            $dimension->uninstall();

            $this->removeInstalledVersionFromOptionTable($prefix . $dimension->getColumnName());
        }
    }

    /**
     * @param $pluginName
     * @return bool
     */
    public function isPluginInstalled($pluginName)
    {
        $pluginsInstalled = $this->getInstalledPluginsName();
        return in_array($pluginName, $pluginsInstalled);
    }

    private function removeInstalledVersionFromOptionTable($name)
    {
        $updater = new Updater();
        $updater->markComponentSuccessfullyUninstalled($name);
    }

    private function makeSureOnlyActivatedPluginsAreLoaded()
    {
        foreach ($this->getLoadedPlugins() as $pluginName => $plugin) {
            if (!in_array($pluginName, $this->pluginsToLoad)) {
                $this->unloadPlugin($plugin);
            }
        }
    }

    /**
     * Reading the plugins from the global.ini.php config file
     *
     * @return array
     */
    protected function getPluginsFromGlobalIniConfigFile()
    {
        return $this->pluginList->getPluginsBundledWithPiwik();
    }

    /**
     * @param $name
     * @return bool
     */
    protected function isPluginEnabledByDefault($name)
    {
        $pluginsBundledWithPiwik = $this->getPluginsFromGlobalIniConfigFile();
        if (empty($pluginsBundledWithPiwik)) {
            return false;
        }
        return in_array($name, $pluginsBundledWithPiwik);
    }

    /**
     * @param array $pluginsToLoad
     * @return array
     */
    private function makePluginsToLoad(array $pluginsToLoad)
    {
        $pluginsToLoad = array_unique($pluginsToLoad);
        if ($this->doLoadAlwaysActivatedPlugins) {
            $pluginsToLoad = array_merge($pluginsToLoad, $this->pluginToAlwaysActivate);
        }
        $pluginsToLoad = array_unique($pluginsToLoad);
        $pluginsToLoad = $this->pluginList->sortPlugins($pluginsToLoad);
        return $pluginsToLoad;
    }

    public function loadPluginTranslations()
    {
        /** @var Translator $translator */
        $translator = StaticContainer::get('Piwik\Translation\Translator');
        foreach ($this->getAllPluginsNames() as $pluginName) {
            $translator->addDirectory(self::getPluginsDirectory() . $pluginName . '/lang');
        }
    }
}
