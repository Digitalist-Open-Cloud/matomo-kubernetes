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
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Plugins\CustomOptOut\SystemSettings as Settings;

/**
 * @package CustomOptOut
 */
class CustomOptOut extends \Piwik\Plugin
{

    /**
     * {@inheritdoc}
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Controller.CoreAdminHome.optOut' => 'addOptOutStyles',
            'Settings.CustomOptOut.settingsUpdated' => 'onSettingsUpdate',
            'SystemSettings.updated' => 'onSystemSettingsUpdate',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function registerEvents()
    {
        return $this->getListHooksRegistered();
    }

    /**
     * @param $jsFiles
     */
    public function getJsFiles(&$jsFiles)
    {

        // CodeMirror
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/codemirror.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/mode/css/css.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/mode/javascript/javascript.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/addon/hint/show-hint.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/addon/hint/css-hint.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/addon/hint/javascript-hint.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/addon/lint/lint.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/addon/lint/css-lint.js";
        $jsFiles[] = "plugins/CustomOptOut/javascripts/codemirror/addon/lint/javascript-lint.js";

        // CSS Lint for CodeMirror
        $jsFiles[] = "plugins/CustomOptOut/javascripts/csslint/csslint.js";

        // CSS Lint for CodeMirror
        $jsFiles[] = "plugins/CustomOptOut/javascripts/jshint/jshint.js";

        // Plugin
        $jsFiles[] = "plugins/CustomOptOut/javascripts/plugin.js";

    }

    /**
     * @param $stylesheets
     */
    public function getStylesheetFiles(&$stylesheets)
    {

        // CodeMirror CSS
        $stylesheets[] = "plugins/CustomOptOut/stylesheets/codemirror/codemirror.css";
        $stylesheets[] = "plugins/CustomOptOut/stylesheets/codemirror/theme/blackboard.css";
        $stylesheets[] = "plugins/CustomOptOut/stylesheets/codemirror/lint.css";
        $stylesheets[] = "plugins/CustomOptOut/stylesheets/codemirror/show-hint.css";

    }

    public function onSettingsUpdate(Settings $settings)
    {
        $this->install();
        return;
    }

    public function onSystemSettingsUpdate(\Piwik\Settings\Plugin\SystemSettings $settings) {
        if ($settings->getPluginName() == 'CustomOptOut') {
            $this->install();
        }
    }

    /**
     * @throws \Exception
     */
    public function addOptOutStyles()
    {
        /** @var \Piwik\Plugins\CoreAdminHome\OptOutManager $manager */
        $manager = StaticContainer::get('Piwik\Plugins\CoreAdminHome\OptOutManager');

        $settings = new Settings();

        // See Issue #33
        $siteId = Common::getRequestVar('idsite', 0, 'integer');

        // Is still available for BC
        if (!$siteId) {
            $siteId = Common::getRequestVar('idSite', 0, 'integer');
        }

        // Try to find siteId in Session
        if (!$siteId) {
            if ($settings->defaultCssStyles->getValue()) {
                $manager->addStylesheet($settings->defaultCssStyles->getValue());
            }

            if ($settings->defaultCssFile->getValue()) {
                $manager->addStylesheet($settings->defaultCssFile->getValue(), false);
            }

            return;
        }

        $site = API::getInstance()->getSiteDataId($siteId);

        if (!$site) {
            if ($settings->defaultCssStyles->getValue()) {
                $manager->addStylesheet($settings->defaultCssStyles->getValue());
            }

            if ($settings->defaultCssFile->getValue()) {
                $manager->addStylesheet($settings->defaultCssFile->getValue(), false);
            }

            return;
        }

        $manager->addQueryParameter('idsite', $siteId);

        // Add CSS file if set
        if (!empty($site['custom_css_file'])) {
            $manager->addStylesheet($site['custom_css_file'], false);
        }

        // Add CSS Inline Styles if set
        if (!empty($site['custom_css'])) {
            $manager->addStylesheet($site['custom_css'], true);
        }


        $jsEnabled = $settings->enableJavascriptInjection->getValue();

        if ($jsEnabled && !empty($site['custom_js_file'])) {
            $manager->addJavascript($site['custom_js_file'], false);
        }

        if ($jsEnabled && !empty($site['custom_js'])) {
            $manager->addJavascript($site['custom_js'], true);
        }
    }

    /**
     * Plugin install hook
     *
     * @throws \Exception
     */
    public function install()
    {

        try {

            $sql = sprintf(
                "ALTER TABLE %s" .
                " ADD COLUMN `custom_css` TEXT NULL AFTER `keep_url_fragment`," .
                " ADD COLUMN `custom_css_file` VARCHAR(255) NULL AFTER `custom_css`;",
                Common::prefixTable('site')
            );

            Db::exec($sql);

        } catch (\Exception $exp) {

            if (!Db::get()->isErrNo($exp, '1060')) {
                throw $exp;
            }

        }

        try {

            $sql = sprintf(
                "ALTER TABLE %s" .
                " ADD COLUMN `custom_js` TEXT NULL AFTER `custom_css`," .
                " ADD COLUMN `custom_js_file` VARCHAR(255) NULL AFTER `custom_js`;",
                Common::prefixTable('site')
            );

            Db::exec($sql);

        } catch (\Exception $exp) {

            if (!Db::get()->isErrNo($exp, '1060')) {
                throw $exp;
            }

        }

    }

    /**
     * Plugin uninstall hook
     *
     * @throws \Exception
     */
    public function uninstall()
    {

        try {

            $sql = sprintf(
                "ALTER TABLE %s" .
                " DROP COLUMN `custom_js`," .
                " DROP COLUMN `custom_js_file`," .
                " DROP COLUMN `custom_css`," .
                " DROP COLUMN `custom_css_file`;",
                Common::prefixTable('site')
            );

            Db::exec($sql);

        } catch (\Exception $exp) {

            if (!Db::get()->isErrNo($exp, '1091')) {
                throw $exp;
            }

        }

    }
}

