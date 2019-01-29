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
use Piwik\Db;
use Piwik\Piwik;

/**
 * API for plugin CustomOptOut
 *
 * @package CustomOptOut
 * @method static \Piwik\Plugins\CustomOptOut\API getInstance()
 */
class API extends \Piwik\Plugin\API
{

    /**
     * Save the custom css and custom css file
     *
     * @param $siteId
     * @param null $customCss
     * @param null $customFile
     */
    public function saveSite($siteId, $customCss = null, $customFile = null, $customJs = null, $customJsFile = null)
    {

        Piwik::checkUserHasAdminAccess($siteId);

        $params = array($customCss, $customFile);
        $query = "UPDATE ".Common::prefixTable("site") . " SET custom_css = ?, custom_css_file = ?";

        // If js is activate, update its too.
        $settings = new SystemSettings();
        if ($settings->enableJavascriptInjection->getValue()) {
                $query .= ", custom_js = ?, custom_js_file = ?";
                $params[] = $customJs;
                $params[] = $customJsFile;
        }

        $query .= " WHERE idsite = ?";
        $params[] = $siteId;

        Db::query($query, $params);
    }

    /**
     * Returns the website information : id, custom css, custom css file
     *
     * @throws Exception If the site ID doesn't exist or the user doesn't have access to it
     * @param int $idSite
     * @return array
     */
    public function getSiteDataId($idSite)
    {

        $query = "SELECT idsite, custom_css, custom_css_file";

        $settings = new SystemSettings();
        if ($settings->enableJavascriptInjection->getValue()) {
            $query .= ", custom_js, custom_js_file";
        } else {
            $query .= ", null as custom_js, null as custom_js_file";
        }

        $query .= " FROM ".Common::prefixTable("site") . " WHERE idsite = ?";

        $site = Db::get()->fetchRow($query, array($idSite));

        return $site;
    }

    /**
     * Returns true if the css editor is enabled
     *
     * @return bool
     */
    public function isCssEditorEnabled()
    {

        $settings = new SystemSettings('CustomOptOut');
        $value = (bool)$settings->enableEditor->getValue();

        if ($value === false) {
            return false;
        }

        return true;

    }

    /**
     * Return the current css editor theme
     *
     * @return string
     */
    public function getEditorTheme()
    {

        $settings = new SystemSettings('CustomOptOut');
        $value = $settings->editorTheme->getValue();

        if ($value == 'default') {
            return 'default';
        }

        return 'blackboard';

    }
}
