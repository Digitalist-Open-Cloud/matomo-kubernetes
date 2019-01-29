<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ProfessionalServices;

use Piwik\Common;
use Piwik\View;
use Piwik\Plugin;

class ProfessionalServices extends \Piwik\Plugin
{
    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Request.getRenamedModuleAndAction' => 'renameProfessionalServicesModule',
            'Template.afterGoalConversionOverviewReport' => array('function' => 'getGoalOverviewPromo', 'after' => true),
            'Template.afterGoalCannotAddNewGoal' => array('function' => 'getGoalOverviewPromo', 'after' => true),
            'Template.endGoalEditTable' => array('function' => 'getGoalFunnelOverviewPromo', 'after' => true),
            'Template.afterEventsReport' => 'getEventsPromo',
            'Template.afterCampaignsReport' => 'getCampaignsPromo',
            'Template.afterReferrerTypeReport' => 'getReferrerTypePromo',
            'Template.afterReferrersKeywordsReport' => 'getSearchKeywordsPerformancePromo',
            'Template.afterCustomVariablesReport' => 'getCustomVariablesPromo',
            'Template.afterOverlaySidebar' => 'getHeatmapPromo',
            'Template.afterVisitorProfileOverview' => 'getSessionRecordingPromo',
        );
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/ProfessionalServices/stylesheets/widget.less';
    }

    /**
     * @deprecated Can be removed in Piwik 3.0
     * @param $module
     * @param $action
     */
    public function renameProfessionalServicesModule(&$module, &$action)
    {
        if ($module == 'ProfessionalServices') {
            $module = 'ProfessionalServices';

            if($action == 'promoPiwikPro') {
                $action = 'promoServices';
            }

            if ($action == 'rssPiwikPro') {
                $action = 'rss';
            }
        }
    }

    public function isRequestForDashboardWidget()
    {
        $isWidget = Common::getRequestVar('widget', 0, 'int');
        return $isWidget;
    }

    public function getHeatmapPromo(&$out)
    {
        if (!$this->shouldShowPromoForPlugin('HeatmapSessionRecording')) {
            return;
        }

        $view = new View('@ProfessionalServices/promoHeatmaps');
        $out .= $view->render();
    }

    public function getSessionRecordingPromo(&$out)
    {
        if (!$this->shouldShowPromoForPlugin('HeatmapSessionRecording')) {
            return;
        }

        $view = new View('@ProfessionalServices/promoSessionRecordings');
        $out .= $view->render();
    }

    public function getSearchKeywordsPerformancePromo(&$out)
    {
        if (!$this->shouldShowPromoForPlugin('SearchEngineKeywordsPerformance')) {
            return;
        }

        $view = new View('@ProfessionalServices/promoSearchKeywords');
        $out .= $view->render();
    }

    public function getGoalFunnelOverviewPromo(&$out)
    {
        if ($this->shouldShowPromoForPlugin('Funnels')) {
            $view = new View('@ProfessionalServices/promoFunnel');
            $out .= $view->render();
        }
    }

    public function getGoalOverviewPromo(&$out)
    {
        if ($this->shouldShowPromoForPlugin('AbTesting')) {
            $view = new View('@ProfessionalServices/promoExperiments.twig');
            $out .= $view->render();
        }
    }

    public function getCustomVariablesPromo(&$out)
    {
        if ($this->shouldShowPromoForPlugin('CustomReports')) {
            $view = new View('@ProfessionalServices/promoCustomVariables.twig');
            $out .= $view->render();
        }
    }

    public function getEventsPromo(&$out)
    {
        if ($this->isRequestForDashboardWidget()) {
            return;
        }

        $view = new View('@ProfessionalServices/promoBelowEvents');
        $view->displayMediaAnalyticsAd = !$this->isPluginActivated('MediaAnalytics');
        $out .= $view->render();
    }

    public function getCampaignsPromo(&$out)
    {
        if ($this->isRequestForDashboardWidget()) {
            return;
        }

        $view = new View('@ProfessionalServices/promoBelowCampaigns');
        $view->displayMarketingCampaignsReportingAd = !$this->isPluginActivated('MarketingCampaignsReporting');
        $view->multiChannelConversionAttributionAd = !$this->isPluginActivated('MultiChannelConversionAttribution') && !empty($_REQUEST['idGoal']);
        $out .= $view->render();
    }

    public function getReferrerTypePromo(&$out)
    {
        if ($this->shouldShowPromoForPlugin('MultiChannelConversionAttribution') && !empty($_REQUEST['idGoal'])) {
            $view = new View('@ProfessionalServices/promoBelowReferrerTypes');
            $out .= $view->render();
        }
    }

    private function shouldShowPromoForPlugin($pluginName)
    {
        return !$this->isPluginActivated($pluginName) && !$this->isRequestForDashboardWidget();
    }

    private function isPluginActivated($pluginName)
    {
        return Plugin\Manager::getInstance()->isPluginActivated($pluginName);
    }

}
