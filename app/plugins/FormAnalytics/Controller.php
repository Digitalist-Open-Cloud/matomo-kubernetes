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
namespace Piwik\Plugins\FormAnalytics;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\Controller as PluginController;
use Piwik\Plugin\ReportsProvider;
use Piwik\Plugins\FormAnalytics\Tracker\RuleMatcher;
use Piwik\Plugins\FormAnalytics\Input\Validator;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;

class Controller extends PluginController
{
    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var FormsModel
     */
    private $formsModel;

    public function __construct(Validator $validator, FormsModel $formsModel)
    {
        $this->validator = $validator;
        $this->formsModel = $formsModel;

        parent::__construct();
    }

    public function manage()
    {
        $idSite = Common::getRequestVar('idSite');

        if (strtolower($idSite) === 'all') {
            // prevent fatal error... redirect to a specific site as it is not possible to manage for all sites
            Piwik::checkUserHasSomeAdminAccess();
            $this->redirectToIndex('FormAnalytics', 'manage');
            exit;
        }

        $this->checkSitePermission();
        $this->validator->checkWritePermission($this->idSite);

        return $this->renderTemplate('manage');
    }

    public function formSummary()
    {
        $this->checkSitePermission();
        $this->validator->checkReportViewPermission($this->idSite);

        $idSiteForm = Common::getRequestVar('idForm', null, 'int');
        $period = Common::getRequestVar('period', null, 'string');
        $date = Common::getRequestVar('date', null, 'string');
        $segment = Request::getRawSegmentFromRequest();

        $this->formsModel->checkFormExists($this->idSite, $idSiteForm);

        $form = $this->formsModel->getForm($this->idSite, $idSiteForm);

        $canEditForm = $this->validator->canWrite($this->idSite);

        return $this->renderTemplate('formSummary', array(
            'period' => $period,
            'segment' => !empty($segment) ? $segment : '',
            'date' => $date,
            'form' => $form,
            'canEditForm' => $canEditForm,
            'patternTranslations' => RuleMatcher::getPatternTranslations(),
            'attributeTranslations' => RuleMatcher::getAttributeTranslations(),
        ));
    }

    public function getEvolutionGraph()
    {
        $this->checkSitePermission();
        $this->validator->checkReportViewPermission($this->idSite);

        $idSiteForm = Common::getRequestVar('idForm', 0, 'int');

        if (!empty($idSiteForm)) {
            $this->formsModel->checkFormExists($this->idSite, $idSiteForm);
        }

        $columns = Common::getRequestVar('columns', false);
        if (false !== $columns) {
            $columns = Piwik::getArrayFromApiParameter($columns);
        }

        $view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, 'FormAnalytics.get');

        if (!empty($columns)) {
            $view->config->columns_to_display = $columns;
        } elseif (empty($view->config->columns_to_display)) {
            $view->config->columns_to_display = array(Metrics::RATE_FORM_STARTERS, Metrics::RATE_FORM_CONVERSION);
        }

        $report = ReportsProvider::factory('FormAnalytics', 'get');
        $view->config->selectable_columns = $report->getAllMetrics();

        $translations = Metrics::getMetricsTranslations();
        foreach ($translations as $index => $translation) {
            $view->config->addTranslation($index, Piwik::translate($translation));
        }

        $view->config->documentation = Piwik::translate('General_EvolutionOverPeriod');

        return $this->renderView($view);
    }

}
