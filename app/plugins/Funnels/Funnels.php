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

namespace Piwik\Plugins\Funnels;

use Piwik\API\Request;
use Piwik\Category\Subcategory;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\FrontController;
use Piwik\Plugins\Funnels\Dao\Funnel;
use Piwik\Plugins\Funnels\Dao\LogTable;
use Piwik\Piwik;
use Piwik\Plugin;

class Funnels extends Plugin
{
    const MENU_CATEGORY = 'Funnels_Funnels';

    public function install()
    {
        $dao = new Funnel();
        $dao->install();

        $dao = new LogTable();
        $dao->install();

        $configuration = new Configuration();
        $configuration->install();
    }

    public function uninstall()
    {
        $dao = new Funnel();
        $dao->uninstall();

        $dao = new LogTable();
        $dao->uninstall();

        $configuration = new Configuration();
        $configuration->uninstall();
    }

    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        $hooks = array(
            'Template.beforeGoalListActionsHead' => 'printGoalListHead',
            'Template.beforeGoalListActionsBody' => 'printGoalListBody',
            'Template.endGoalEditTable' => 'printGoalEdit',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'API.Goals.addGoal' => 'validateFunnelParams',
            'API.Goals.updateGoal' => 'validateFunnelParams',
            'API.Goals.addGoal.end' => 'setFunnelFromNew',
            'API.Goals.updateGoal.end' => 'setFunnelFromUpdate',
            'API.Goals.deleteGoal.end' => 'deleteFunnel',
            'SitesManager.deleteSite.end' => 'onDeleteSite',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Segment.addSegments' => 'addSegments',
            'Metrics.getDefaultMetricTranslations' => 'getDefaultMetricTranslations',
            'Metrics.getDefaultMetricDocumentationTranslations' => 'getDefaultMetricDocumentationTranslations',
            'Category.addSubcategories' => 'addSubcategories',
        );
        return $hooks;
    }

    public function addSubcategories(&$subcategories)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$this->getValidator()->canViewReport($idSite)) {
            return;
        }

        $model = $this->getFunnelsModel();
        $funnels = $model->getAllActivatedFunnelsForSite($idSite);
        $order = 5;

        $category = new Subcategory();
        $category->setId('General_Overview');
        $category->setCategoryId(self::MENU_CATEGORY);
        $category->setOrder($order);
        $subcategories[] = $category;

        foreach ($funnels as $funnel) {
            $order++;

            $category = new Subcategory();
            $category->setName($funnel['name']);
            $category->setCategoryId(self::MENU_CATEGORY);
            $category->setId($funnel['idfunnel']);
            $category->setOrder($order);
            $subcategories[] = $category;
        }
    }

    public function getDefaultMetricTranslations(&$translations)
    {
        $translations[Metrics::NUM_STEP_VISITS] = Piwik::translate('Funnels_ColumnNbStepVisits');
        $translations[Metrics::NUM_STEP_ENTRIES] = Piwik::translate('Funnels_ColumnNbStepEntries');
        $translations[Metrics::NUM_STEP_EXITS] = Piwik::translate('Funnels_ColumnNbStepExits');
        $translations[Metrics::NUM_STEP_PROCEEDED] = Piwik::translate('Funnels_ColumnNbProceeded');
        $translations[Metrics::RATE_ABANDONED] = Piwik::translate('Funnels_ColumnAbandonedRate');
        $translations[Metrics::SUM_FUNNEL_ENTRIES] = Piwik::translate('Funnels_ColumnSumEntries');
        $translations[Metrics::SUM_FUNNEL_EXITS] = Piwik::translate('Funnels_ColumnSumExits');
        $translations[Metrics::NUM_CONVERSIONS] = Piwik::translate('Funnels_ColumnNumFunnelConversions');
        $translations[Metrics::RATE_CONVERSION] = Piwik::translate('Funnels_ColumnRateFunnelConversion');
    }

    public function getDefaultMetricDocumentationTranslations(&$translations)
    {
        $translations[Metrics::NUM_STEP_VISITS] = Piwik::translate('Funnels_ColumnNbStepVisitsDocumentation');
        $translations[Metrics::NUM_STEP_ENTRIES] = Piwik::translate('Funnels_ColumnNbStepEntriesDocumentation');
        $translations[Metrics::NUM_STEP_EXITS] = Piwik::translate('Funnels_ColumnNbStepExitsDocumentation');
        $translations[Metrics::NUM_STEP_PROCEEDED] = Piwik::translate('Funnels_ColumnNbStepProceededDocumentation');
        $translations[Metrics::SUM_FUNNEL_ENTRIES] = Piwik::translate('Funnels_ColumnSumEntriesDocumentation');
        $translations[Metrics::SUM_FUNNEL_EXITS] = Piwik::translate('Funnels_ColumnSumExitsDocumentation');
        $translations[Metrics::NUM_CONVERSIONS] = Piwik::translate('Funnels_ColumnNumFunnelConversionsDocumentation');
        $translations[Metrics::RATE_CONVERSION] = Piwik::translate('Funnels_ColumnRateFunnelConversionDocumentation');
        $translations[Metrics::RATE_ABANDONED] = Piwik::translate('Funnels_ColumnAbandonedRateDocumentation');
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'General_Yes';
        $translationKeys[] = 'General_No';
        $translationKeys[] = 'General_Ok';
        $translationKeys[] = 'General_Name';
        $translationKeys[] = 'General_Help';
        $translationKeys[] = 'General_Remove';
        $translationKeys[] = 'General_Cancel';
        $translationKeys[] = 'Goals_Pattern';
        $translationKeys[] = 'Funnels_Unlock';
        $translationKeys[] = 'Funnels_RemoveStepTooltip';
        $translationKeys[] = 'Funnels_HelpStepTooltip';
        $translationKeys[] = 'Funnels_InfoCannotActivateFunnelIncomplete';
        $translationKeys[] = 'Funnels_InfoFunnelIsLocked';
        $translationKeys[] = 'Funnels_ConfirmUnlockFunnel';
        $translationKeys[] = 'Funnels_ConfirmDeactivateFunnel';
        $translationKeys[] = 'Funnels_AddStep';
        $translationKeys[] = 'Funnels_ValidateFunnelSteps';
        $translationKeys[] = 'Funnels_ValidateUrlMatchesDescription';
        $translationKeys[] = 'Funnels_EnterURLToValidate';
        $translationKeys[] = 'Funnels_Step';
        $translationKeys[] = 'Funnels_ActivateFunnel';
        $translationKeys[] = 'Funnels_ActivateFunnelDescription';
        $translationKeys[] = 'Funnels_ConfigureFunnelSteps';
        $translationKeys[] = 'Funnels_ConfigureFunnelStepsDescription1';
        $translationKeys[] = 'Funnels_ConfigureFunnelStepsDescription2';
        $translationKeys[] = 'Funnels_ConfigureFunnelStepsDescription3';
        $translationKeys[] = 'Funnels_WarningOnUpdateReportMightGetLost';
        $translationKeys[] = 'Funnels_WarningFunnelIsActivatedRequiredUnlock';
        $translationKeys[] = 'Funnels_RequiredColumnTitle';
        $translationKeys[] = 'Funnels_ComparisonColumnTitle';
        $translationKeys[] = 'Funnels_Introduction';
        $translationKeys[] = 'Funnels_IntroductionListItem1';
        $translationKeys[] = 'Funnels_IntroductionListItem2';
        $translationKeys[] = 'Funnels_IntroductionListItem3';
        $translationKeys[] = 'Funnels_IntroductionListItem4';
        $translationKeys[] = 'Funnels_IntroductionFollowSteps';
    }

    public function onDeleteSite($idSite)
    {
        $funnel = new Funnel();
        $now = Date::now()->getDatetime();
        $funnel->disableFunnelsForSite($idSite, $now);
    }

    public function deleteFunnel($returnedValue, $info)
    {
        if (empty($info['parameters'])) {
            return;
        }

        $finalParameters = $info['parameters'];

        $idSite = $finalParameters['idSite'];
        $idGoal = $finalParameters['idGoal'];

        $goal = Request::processRequest('Goals.getGoal', array('idSite' => $idSite, 'idGoal' => $idGoal));

        if (empty($goal['idgoal'])) {
            // we only delete funnel if that goal was actually deleted
            // we check for idgoal because API might return true even though goal does not exist
            Request::processRequest('Funnels.deleteGoalFunnel', array('idSite' => $idSite, 'idGoal' => $idGoal));
        }
    }

    public function validateFunnelParams($finalParameters)
    {
        // important! We validate before the goal is created or updated. Otherwise we would only save partial data
        // eg it would be otherwise possible that a goal is first updated, then an error occurs when validating steps
        // meaning the user would end up having some data saved and some data not. Instead we validate the sent data
        // first before the actual goal api gets the request and throw exceptions as early as possible
        if (isset($_POST['funnelActivated']) && isset($_POST['funnelSteps']) && !empty($finalParameters)) {
            // we only validate when a value was actually sent.
            $isActivated = Common::getRequestVar('funnelActivated', 0, 'int');
            $steps = Common::getRequestVar('funnelSteps', array(), 'array');
            $idSite = $finalParameters['idSite'];

            $validator = $this->getValidator();
            $validator->checkWritePermission($idSite);
            $validator->validateFunnelConfiguration($isActivated, $steps);
        }
    }

    private function getValidator()
    {
        return StaticContainer::get('Piwik\Plugins\Funnels\Input\Validator');
    }

    public function setFunnelFromNew($returnedValue, $info)
    {
        if ($returnedValue) {
            $idGoal = $returnedValue;
            $finalParameters = $info['parameters'];
            $idSite = $finalParameters['idSite'];

            $this->setFunnel($idSite, $idGoal);
        }
    }

    public function setFunnelFromUpdate($returnedValue, $info)
    {
        if (empty($info['parameters'])) {
            return;
        }

        $finalParameters = $info['parameters'];
        $idSite = $finalParameters['idSite'];
        $idGoal = $finalParameters['idGoal'];

        $this->setFunnel($idSite, $idGoal);
    }

    private function setFunnel($idSite, $idGoal)
    {
        if (!isset($_POST['funnelActivated']) || !isset($_POST['funnelSteps'])) {
            // no value was set, we should not set funnel as it would generate a new funnel ID causing all existing
            // reports to be gone. We are allowed to only set a funnel, when the UI sent funnel data along the
            // goals request
            return;
        }

        $isActivated = Common::getRequestVar('funnelActivated', 0, 'int');
        $steps = Common::getRequestVar('funnelSteps', array(), 'array');

        $this->getFunnelsModel()->clearGoalsCache();

        Request::processRequest('Funnels.setGoalFunnel', array(
            'idSite' => $idSite,
            'idGoal' => $idGoal,
            'isActivated' => $isActivated,
            'steps' => $steps
        ));
    }

    private function getFunnelsModel()
    {
        return StaticContainer::get('Piwik\Plugins\Funnels\Model\FunnelsModel');
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/Funnels/angularjs/common/directives/funnel-page-link.js";
        $jsFiles[] = "plugins/Funnels/angularjs/sales-funnel/sales-funnel.controller.js";
        $jsFiles[] = "plugins/Funnels/angularjs/manage-funnel/manage-funnel.directive.js";
        $jsFiles[] = "plugins/Funnels/javascripts/funnelDataTable.js";
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/Funnels/angularjs/manage-funnel/manage-funnel.directive.less";
        $stylesheets[] = "plugins/Funnels/stylesheets/report.less";
        $stylesheets[] = "plugins/Funnels/stylesheets/manage.less";
    }

    public function printGoalListHead(&$out)
    {
        $out .= '<th>' . Piwik::translate('Funnels_Funnel') . '</th>';
    }

    public function printGoalListBody(&$out, $goal)
    {
        $funnel = $this->getFunnel($goal['idsite'], $goal['idgoal']);

        $out .= '<td>';

        if (!empty($funnel['activated'])) {
            $message = Piwik::translate('Funnels_ActivatedFunnelExists');
            $out .= '<span title="' . $message . '" class="icon-ok funnelActivated"></span>';
        } elseif (!empty($funnel['steps'])) {
            $message = Piwik::translate('Funnels_FunnelConfiguredButNotActivated');
            $out .= '<span title="' . $message .'" class="icon-ok funnelExists"></span>';
        } else {
            $out .= '-';
        }

        $out .= '</td>';
    }

    public function printGoalEdit(&$out)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite) || !$this->getValidator()->canWrite($idSite)) {
            return;
        }

        $out .= '<hr />
                 <div class="row"><div class="col s12">
                 <h3>' . Piwik::translate('Funnels_Funnel') . '</h3>
                 <div piwik-manage-funnel></div></div></div>
                 <hr />';
    }

    public function addSegments(&$segments)
    {
        $funnels = $this->getFunnelsModel();

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_FUNNEL_SEGMENT);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName('Funnels_SegmentNameFunnelName');
        $segment->setSqlSegment('log_funnel.idfunnel');
        $segment->setAcceptedValues(Piwik::translate('Funnels_SegmentNameFunnelNameDescription'));
        $segment->setSqlFilter('\\Piwik\\Plugins\\Funnels\\Segment::getIdByName');
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) use ($funnels) {
            $funnels = $funnels->getAllActivatedFunnelsForSite($idSite);
            $names = array();

            foreach ($funnels as $funnel) {
                $names[] = $funnel['name'];
            }

            $names = array_unique($names);

            return array_slice($names, 0, $maxValuesToReturn);
        });

        $segments[] = $segment;

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_FUNNEL_STEP_POSITION);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName('Funnels_SegmentNameStepPosition');
        $segment->setSqlSegment('log_funnel.step_position');
        $segment->setAcceptedValues(Piwik::translate('Funnels_SegmentNameStepPositionDescription'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $steps = range(1,10);

            return array_slice($steps, 0, $maxValuesToReturn);
        });

        $segments[] = $segment;
    }

    private function getFunnel($idSite, $idGoal)
    {
        // we use model instead of Request::processRequest as only admins can get this data. However, when showing
        // goal reporting page or manage goals page we need to know whether a funnel exists for view users
        $funnels = $this->getFunnelsModel();
        return $funnels->getGoalFunnel($idSite, $idGoal);
    }
}
