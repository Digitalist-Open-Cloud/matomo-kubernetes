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

namespace Piwik\Plugins\UsersFlow;

class UsersFlow extends \Piwik\Plugin
{
    public function install()
    {
        $configuration = new Configuration();
        $configuration->install();
    }

    public function uninstall()
    {
        $configuration = new Configuration();
        $configuration->uninstall();
    }

    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys'
        );
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/UsersFlow/libs/d3/d3.min.js';
        $jsFiles[] = 'plugins/UsersFlow/libs/d3/sankey/sankey.js';
        $jsFiles[] = 'plugins/UsersFlow/libs/d3/tip/index.js';
        $jsFiles[] = 'plugins/UsersFlow/angularjs/visualization/sankey.controller.js';
        $jsFiles[] = 'plugins/UsersFlow/angularjs/visualization/sankey.directive.js';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/UsersFlow/angularjs/visualization/sankey.less";
        $stylesheets[] = "plugins/UsersFlow/libs/d3/tip/example-styles.css";
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'CoreHome_ThereIsNoDataForThisReport';
        $translationKeys[] = 'UsersFlow_Interactions';
        $translationKeys[] = 'UsersFlow_ColumnInteraction';
        $translationKeys[] = 'Transitions_ExitsInline';
        $translationKeys[] = 'General_NVisits';
        $translationKeys[] = 'General_Others';
        $translationKeys[] = 'General_Search';
        $translationKeys[] = 'General_ColumnNbVisits';
        $translationKeys[] = 'General_ColumnExits';
        $translationKeys[] = 'General_Source';
        $translationKeys[] = 'Installation_SystemCheckOpenURL';
        $translationKeys[] = 'VisitorInterest_NPages';
        $translationKeys[] = 'UsersFlow_ExploringInfo';
        $translationKeys[] = 'UsersFlow_ColumnProceeded';
        $translationKeys[] = 'UsersFlow_ActionShowDetails';
        $translationKeys[] = 'UsersFlow_ActionClearHighlight';
        $translationKeys[] = 'UsersFlow_ActionHighlightTraffic';
        $translationKeys[] = 'UsersFlow_ActionRemoveStep';
        $translationKeys[] = 'UsersFlow_ActionAddStep';
        $translationKeys[] = 'UsersFlow_NProceededInline';
        $translationKeys[] = 'UsersFlow_InteractionXToY';
        $translationKeys[] = 'UsersFlow_OptionLevelOfDetail';
        $translationKeys[] = 'UsersFlow_OptionLevelOfDetail1';
        $translationKeys[] = 'UsersFlow_OptionLevelOfDetail2';
        $translationKeys[] = 'UsersFlow_OptionLevelOfDetail3';
        $translationKeys[] = 'UsersFlow_OptionLevelOfDetail4';
        $translationKeys[] = 'UsersFlow_OptionLevelOfDetail5';
        $translationKeys[] = 'UsersFlow_OptionLevelOfDetail6';
        $translationKeys[] = 'UsersFlow_OptionNumActionsPerStep';
        $translationKeys[] = 'UsersFlow_ExploreTraffic';
        $translationKeys[] = 'UsersFlow_UnexploreTraffic';
    }
}
