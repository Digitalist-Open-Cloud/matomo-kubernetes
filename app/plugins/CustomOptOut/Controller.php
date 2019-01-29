<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package CustomOptOut
 */

namespace Piwik\Plugins\CustomOptOut;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Plugins\LanguagesManager\LanguagesManager;
use Piwik\Plugins\SitesManager\API as APISiteManager;
use Piwik\Site;
use Piwik\UrlHelper;
use Piwik\View;

/**
 *
 * @package CustomOptOut
 */
class Controller extends ControllerAdmin
{

    /**
     * Main Plugin Index
     *
     * @return mixed
     * @throws \Exception
     */
    public function index()
    {

        Piwik::checkUserHasSomeAdminAccess();

        if (isset($_SERVER['REQUEST_METHOD']) && 'POST' == $_SERVER['REQUEST_METHOD']) {

            // Cannot use Common::getRequestVar, because the function remove whitespaces and newline breaks
            $postedSiteData = isset($_POST['site']) ? $_POST['site'] : null;

            if (is_array($postedSiteData) && count($postedSiteData) > 0) {

                foreach ($postedSiteData as $id => $site) {

                    if (!isset($site['css'], $site['file']) && !isset($site['js'], $site['js_file'])) {
                        continue;
                    }

                    // Check URL for CSS file
                    if (empty($site['file']) || !UrlHelper::isLookLikeUrl($site['file'])) {
                        $site['file'] = null;
                    }

                    // Check URL for JS file
                    if (empty($site['js_file']) || !UrlHelper::isLookLikeUrl($site['js_file'])) {
                        $site['js_file'] = null;
                    }

                    if (empty($site['css'])) {
                        $site['css'] = null;
                    }

                    if (empty($site['js'])) {
                        $site['js'] = null;
                    }

                    API::getInstance()->saveSite($id, $site['css'], $site['file'], $site['js'], $site['js_file']);
                }

                // Redirect to, clear POST vars
                $this->redirectToIndex('CustomOptOut', 'index');

                return;

            }
        }

        $view = new View('@CustomOptOut/index.twig');
        Site::clearCache();

        if (Piwik::hasUserSuperUserAccess()) {
            $sitesRaw = APISiteManager::getInstance()->getAllSites();
        } else {
            $sitesRaw = APISiteManager::getInstance()->getSitesWithAdminAccess();
        }

        // Gets sites after Site.setSite hook was called
        $sites = array_values(Site::getSites());

        if (count($sites) != count($sitesRaw)) {
            throw new \Exception("One or more website are missing or invalid.");
        }

        foreach ($sites as &$site) {
            $site['alias_urls'] = APISiteManager::getInstance()->getSiteUrlsFromId($site['idsite']);
        }

        $settings = new SystemSettings();

        $view->adminSites = $sites;
        $view->adminSitesCount = count($sites);
        $view->language = LanguagesManager::getLanguageCodeForCurrentUser();
        $view->isEditorEnabled = API::getInstance()->isCssEditorEnabled();
        $view->editorTheme = API::getInstance()->getEditorTheme();
        $view->showOldLinks = false;
        $view->enableJs = $settings->enableJavascriptInjection->getValue();

        $this->setBasicVariablesView($view);

        return $view->render();
    }

    /**
     * Shows the "Track Visits" checkbox.
     * @deprecated This action is introduced only to keep BC with older piwik versions <= 2.15.0
     *             The user will be redirected to CoreAdminHome:optOut
     */
    public function optOut()
    {
        // See Issue #33
        $siteId = Common::getRequestVar('idsite', 0, 'integer');

        // Is still available for BC
        if (!$siteId) {
            $siteId = Common::getRequestVar('idSite', 0, 'integer');
        }

        // Redirect to default OptOut Method if OptOut Manager available
        $params = $_GET;
        unset($params['action']);
        unset($params['module']);
        unset($params['idSite']);

        $this->redirectToIndex('CoreAdminHome', 'optOut', $siteId, null, null, $params);
    }
}
