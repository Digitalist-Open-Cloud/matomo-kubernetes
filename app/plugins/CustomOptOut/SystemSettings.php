<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomOptOut;

use Piwik\Piwik;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

/**
 * Defines Settings for CustomOptOut.
 *
 * Usage like this:
 * $settings = new Settings('CustomOptOut');
 * $settings->autoRefresh->getValue();
 * $settings->metric->getValue();
 *
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /**
     * @var Setting
     */
    public $enableEditor;

    /**
     * @var Setting
     */
    public $editorTheme;

    /**
     * @var Setting
     */
    public $defaultCssStyles;

    /**
     * @var Setting
     */
    public $defaultCssFile;

    /**
     * @var Setting
     */
    public $enableJavascriptInjection;

    protected function init()
    {
        $this->enableEditor = $this->createEnableEditorSetting();
        $this->editorTheme = $this->createThemeSetting();
        $this->defaultCssStyles = $this->createDefaultCssStylesSetting();
        $this->defaultCssFile = $this->createDefaultCssFileSetting();
        $this->enableJavascriptInjection = $this->createEnableJavascriptInjectionSetting();
    }

    private function createEnableEditorSetting()
    {
        return $this->makeSetting('enableEditor', true, FieldConfig::TYPE_BOOL, function(FieldConfig $field) {
            $field->title = Piwik::translate('CustomOptOut_ShowEditorOptionName');
            $field->description = Piwik::translate('CustomOptOut_ShowEditorDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });

    }

    private function createThemeSetting()
    {
        return $this->makeSetting('editorTheme', 'default', FieldConfig::TYPE_STRING, function(FieldConfig $field) {
            $field->title = Piwik::translate('CustomOptOut_EditorThemeOptionName');
            $field->description = Piwik::translate('CustomOptOut_EditorThemeDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = array(
                'default' => 'Bright Theme',
                'blackboard' => 'Dark Theme',
            );
        });
    }

    private function createDefaultCssStylesSetting()
    {
        return $this->makeSetting('defaultCssStyles', 'body { font-family: Arial; }', FieldConfig::TYPE_STRING, function(FieldConfig $field) {
            $field->title = Piwik::translate('CustomOptOut_DefaultCssStyles');
            $field->description = Piwik::translate('CustomOptOut_DefaultCssStylesDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
        });
    }

    private function createDefaultCssFileSetting()
    {
        return $this->makeSetting('defaultCssFile', null, FieldConfig::TYPE_STRING, function(FieldConfig $field) {
            $field->title = Piwik::translate('CustomOptOut_DefaultCssFile');
            $field->description = Piwik::translate('CustomOptOut_DefaultCssFileDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });

    }

    private function createEnableJavascriptInjectionSetting()
    {
        return $this->makeSetting('enableJavascriptInjection', false, FieldConfig::TYPE_BOOL, function(FieldConfig $field) {
            $field->title = Piwik::translate('CustomOptOut_EnableJavascriptInjection');
            $field->description = Piwik::translate('CustomOptOut_EnableJavascriptInjectionDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }
}
