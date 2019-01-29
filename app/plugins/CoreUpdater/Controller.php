<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreUpdater;

use Exception;
use Piwik\AssetManager;
use Piwik\Common;
use Piwik\Config;
use Piwik\DbHelper;
use Piwik\Filechecks;
use Piwik\FileIntegrity;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\LanguagesManager\LanguagesManager;
use Piwik\Plugins\Marketplace\Plugins;
use Piwik\SettingsPiwik;
use Piwik\SettingsServer;
use Piwik\Updater as DbUpdater;
use Piwik\Version;
use Piwik\View;
use Piwik\View\OneClickDone;

class Controller extends \Piwik\Plugin\Controller
{
    private $coreError = false;
    private $warningMessages = array();
    private $errorMessages = array();
    private $deactivatedPlugins = array();

    /**
     * @var Updater
     */
    private $updater;

    /**
     * @var Plugins
     */
    private $marketplacePlugins;

    public function __construct(Updater $updater, Plugins $marketplacePlugins = null)
    {
        $this->updater = $updater;
        $this->marketplacePlugins = $marketplacePlugins;

        parent::__construct();
    }

    /**
     * Return the base.less compiled to css
     *
     * @return string
     */
    public function getUpdaterCss()
    {
        Common::sendHeader('Content-Type: text/css');
        Common::sendHeader('Cache-Control: max-age=' . (60 * 60));

        $files = array(
            'plugins/Morpheus/stylesheets/base/bootstrap.css',
            'plugins/Morpheus/stylesheets/base/icons.css',
            'libs/jquery/themes/base/jquery-ui.min.css',
            'libs/bower_components/materialize/dist/css/materialize.min.css',
            'plugins/Morpheus/stylesheets/base.less',
            'plugins/Morpheus/stylesheets/general/_forms.less',
            'plugins/Morpheus/stylesheets/simple_structure.css',
            'plugins/CoreHome/stylesheets/jquery.ui.autocomplete.css',
            'plugins/CoreUpdater/stylesheets/updateLayout.css'
        );

        return AssetManager::compileCustomStylesheets($files);
    }

    /**
     * Return the base.less compiled to css
     *
     * @return string
     */
    public function getUpdaterJs()
    {
        Common::sendHeader('Content-Type: application/javascript; charset=UTF-8');
        Common::sendHeader('Cache-Control: max-age=' . (60 * 60));
    
        $files = array(
            'libs/bower_components/jquery/dist/jquery.min.js',
            'libs/bower_components/jquery-ui/ui/minified/jquery-ui.min.js',
            'libs/bower_components/materialize/dist/js/materialize.min.js',
            'plugins/Morpheus/javascripts/piwikHelper.js',
            'plugins/CoreHome/javascripts/donate.js',
            'plugins/CoreUpdater/javascripts/updateLayout.js',
            'libs/bower_components/angular/angular.min.js',
            'libs/bower_components/angular-sanitize/angular-sanitize.js',
            'libs/bower_components/angular-animate/angular-animate.js',
            'libs/bower_components/angular-cookies/angular-cookies.js',
            'libs/bower_components/ngDialog/js/ngDialog.min.js',
            'plugins/CoreHome/angularjs/common/services/service.module.js',
            'plugins/CoreHome/angularjs/common/filters/filter.module.js',
            'plugins/CoreHome/angularjs/common/filters/translate.js',
            'plugins/CoreHome/angularjs/common/directives/directive.module.js',
            'plugins/CoreHome/angularjs/common/directives/focus-anywhere-but-here.js',
            'plugins/CoreHome/angularjs/piwikApp.config.js',
            'plugins/CoreHome/angularjs/piwikApp.js',
            'plugins/Installation/javascripts/installation.js',
        );

        return AssetManager::compileCustomJs($files);
    }

    public function newVersionAvailable()
    {
        Piwik::checkUserHasSuperUserAccess();
        $this->checkNewVersionIsAvailableOrDie();

        $newVersion = $this->updater->getLatestVersion();

        $view = new View('@CoreUpdater/newVersionAvailable');
        $this->addCustomLogoInfo($view);
        $this->setBasicVariablesView($view);

        $view->piwik_version = Version::VERSION;
        $view->piwik_new_version = $newVersion;

        $incompatiblePlugins = $this->getIncompatiblePlugins($newVersion);

        $marketplacePlugins = array();
        try {
            if (!empty($incompatiblePlugins) && $this->marketplacePlugins) {
                $marketplacePlugins = $this->marketplacePlugins->getAllAvailablePluginNames();
            }
        } catch (\Exception $e) {}

        $view->marketplacePlugins = $marketplacePlugins;
        $view->incompatiblePlugins = $incompatiblePlugins;
        $view->piwik_latest_version_url = $this->updater->getArchiveUrl($newVersion);
        $view->can_auto_update  = Filechecks::canAutoUpdate();
        $view->makeWritableCommands = Filechecks::getAutoUpdateMakeWritableMessage();

        return $view->render();
    }

    public function oneClickUpdate()
    {
        Piwik::checkUserHasSuperUserAccess();

        $view = new OneClickDone(Piwik::getCurrentUserTokenAuth());

        $useHttps = Common::getRequestVar('https', 1, 'int');

        try {
            $messages = $this->updater->updatePiwik($useHttps);
        } catch (ArchiveDownloadException $e) {
            $view->httpsFail = $useHttps;
            $view->error = $e->getMessage();
            $messages = $e->getUpdateLogMessages();
        } catch (UpdaterException $e) {
            $view->error = $e->getMessage();
            $messages = $e->getUpdateLogMessages();
        }

        Filesystem::deleteAllCacheOnUpdate();

        $view->feedbackMessages = $messages;
        $this->addCustomLogoInfo($view);
        return $view->render();
    }

    public function oneClickResults()
    {
        $httpsFail = (bool) Common::getRequestVar('httpsFail', 0, 'int', $_POST);
        $error = Common::getRequestVar('error', '', 'string', $_POST);

        if ($httpsFail) {
            $view = new View('@CoreUpdater/updateHttpsError');
            $view->error = $error;
        } elseif ($error) {
            $view = new View('@CoreUpdater/updateHttpError');
            $view->error = $error;
            $view->feedbackMessages = safe_unserialize(Common::unsanitizeInputValue(Common::getRequestVar('messages', '', 'string', $_POST)));
        } else {
            $view = new View('@CoreUpdater/updateSuccess');
        }

        $this->addCustomLogoInfo($view);
        $this->setBasicVariablesView($view);
        return $view->render();
    }

    protected function redirectToDashboardWhenNoError(DbUpdater $updater)
    {
        if (count($updater->getSqlQueriesToExecute()) == 1
            && !$this->coreError
            && empty($this->warningMessages)
            && empty($this->errorMessages)
            && empty($this->deactivatedPlugins)
        ) {
            Piwik::redirectToModule('CoreHome');
        }
    }

    private function checkNewVersionIsAvailableOrDie()
    {
        if (!$this->updater->isNewVersionAvailable()) {
            throw new Exception(Piwik::translate('CoreUpdater_ExceptionAlreadyLatestVersion', Version::VERSION));
        }
    }

    public function index()
    {
        $language = Common::getRequestVar('language', '');
        if (!empty($language)) {
            LanguagesManager::setLanguageForSession($language);
        }

        try {
            return $this->runUpdaterAndExit();
        } catch(NoUpdatesFoundException $e) {
            Piwik::redirectToModule('CoreHome');
        }
    }

    public function runUpdaterAndExit($doDryRun = null)
    {
        $updater = new DbUpdater();
        $componentsWithUpdateFile = $updater->getComponentUpdates();
        if (empty($componentsWithUpdateFile)) {
            throw new NoUpdatesFoundException("Everything is already up to date.");
        }

        SettingsServer::setMaxExecutionTime(0);

        $welcomeTemplate = '@CoreUpdater/runUpdaterAndExit_welcome';
        $doneTemplate = '@CoreUpdater/runUpdaterAndExit_done';

        $viewWelcome = new View($welcomeTemplate);
        $this->addCustomLogoInfo($viewWelcome);
        $this->setBasicVariablesView($viewWelcome);

        $viewDone = new View($doneTemplate);
        $this->addCustomLogoInfo($viewDone);
        $this->setBasicVariablesView($viewDone);

        $doExecuteUpdates = Common::getRequestVar('updateCorePlugins', 0, 'integer') == 1;

        if (is_null($doDryRun)) {
            $doDryRun = !$doExecuteUpdates;
        }

        if ($doDryRun) {
            $viewWelcome->queries = $updater->getSqlQueriesToExecute();
            $viewWelcome->isMajor = $updater->hasMajorDbUpdate();
            $this->doWelcomeUpdates($viewWelcome, $componentsWithUpdateFile);
            return $viewWelcome->render();
        }

        // Web
        if ($doExecuteUpdates) {
            $this->warningMessages = array();
            $this->doExecuteUpdates($viewDone, $updater, $componentsWithUpdateFile);

            $this->redirectToDashboardWhenNoError($updater);

            return $viewDone->render();
        }

        exit;
    }

    private function doWelcomeUpdates($view, $componentsWithUpdateFile)
    {
        $view->new_piwik_version = Version::VERSION;
        $view->commandUpgradePiwik = "php " . Filesystem::getPathToPiwikRoot() . "/console core:update";

        $instanceId = SettingsPiwik::getPiwikInstanceId();

        if ($instanceId) {
            $view->commandUpgradePiwik .= ' --matomo-domain="' . $instanceId . '"';
        }

        $pluginNamesToUpdate = array();
        $dimensionsToUpdate = array();
        $coreToUpdate = false;

        // handle case of existing database with no tables
        if (!DbHelper::isInstalled()) {
            $this->errorMessages[] = Piwik::translate('CoreUpdater_EmptyDatabaseError', Config::getInstance()->database['dbname']);
            $this->coreError = true;
            $currentVersion = 'N/A';
        } else {
            $this->errorMessages = array();
            try {
                $currentVersion = Option::get('version_core');
            } catch (Exception $e) {
                $currentVersion = '<= 0.2.9';
            }

            foreach ($componentsWithUpdateFile as $name => $filenames) {
                if ($name == 'core') {
                    $coreToUpdate = true;
                } elseif (0 === strpos($name, 'log_')) {
                    $dimensionsToUpdate[] = $name;
                } else {
                    $pluginNamesToUpdate[] = $name;
                }
            }
        }

        // check file integrity
        list($success, $messages) = FileIntegrity::getFileIntegrityInformation();

        if (!$success) {
            $this->warningMessages[] = Piwik::translate('General_FileIntegrityWarning');
        }
        if (count($messages) > 0) {
            $this->warningMessages = array_merge($this->warningMessages, $messages);
        }
        Filesystem::deleteAllCacheOnUpdate();

        sort($dimensionsToUpdate);

        $view->coreError = $this->coreError;
        $view->warningMessages = $this->warningMessages;
        $view->errorMessages = $this->errorMessages;
        $view->current_piwik_version = $currentVersion;
        $view->pluginNamesToUpdate = $pluginNamesToUpdate;
        $view->dimensionsToUpdate = $dimensionsToUpdate;
        $view->coreToUpdate = $coreToUpdate;
    }

    private function doExecuteUpdates($view, DbUpdater $updater, $componentsWithUpdateFile)
    {
        $result = $updater->updateComponents($componentsWithUpdateFile);

        $this->coreError       = $result['coreError'];
        $this->warningMessages = $result['warnings'];
        $this->errorMessages   = $result['errors'];
        $this->deactivatedPlugins = $result['deactivatedPlugins'];
        $view->coreError = $this->coreError;
        $view->warningMessages = $this->warningMessages;
        $view->errorMessages = $this->errorMessages;
        $view->deactivatedPlugins = $this->deactivatedPlugins;
    }

    private function getIncompatiblePlugins($piwikVersion)
    {
        return PluginManager::getInstance()->getIncompatiblePlugins($piwikVersion);
    }

    public static function isUpdatingOverHttps()
    {
        $openSslEnabled = extension_loaded('openssl');
        $usingMethodSupportingHttps = (Http::getTransportMethod() !== 'socket');

        return $openSslEnabled && $usingMethodSupportingHttps;
    }
}
