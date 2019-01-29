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
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\Funnels\Db\Pattern;
use Piwik\Plugins\Funnels\Input\Validator;
use Piwik\Plugins\Funnels\Model\FunnelsModel;
use Piwik\Plugins\PrivacyManager\PrivacyManager;
use Piwik\Tracker\PageUrl;
use Piwik\Plugin\Controller as PluginController;

class Controller extends PluginController
{
    /**
     * @var FunnelsModel
     */
    private $funnels;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var Pattern
     */
    private $pattern;

    public function __construct(FunnelsModel $funnels, Validator $validator, Pattern $pattern)
    {
        $this->funnels = $funnels;
        $this->validator = $validator;
        $this->pattern = $pattern;

        parent::__construct();
    }

    /**
     * Shows which URLs match a certain pattern and help text for how to configure a step.
     * @return string
     */
    public function stepHelp()
    {
        $this->checkSitePermission();
        $this->validator->checkWritePermission($this->idSite);

        $pattern = Common::getRequestVar('pattern', '', 'string');
        $patternType = Common::getRequestVar('pattern_type', '', 'string');
        $limit = 20;

        $hasMoreUrls = false;
        $urls = array();
        if (!empty($pattern) && !empty($patternType)) {
            $urls = $this->pattern->findMatchingUrls($patternType, $pattern, $this->idSite, $limit);
            $hasMoreUrls = count($urls) >= $limit;
        }

        return $this->renderTemplate('stepHelp', array(
            'urls' => $urls,
            'hasMoreUrls' => $hasMoreUrls,
            'urlLimit' => $limit,
            'pattern' => $pattern,
            'patternType' => $patternType,
            'patternTranslations' => Pattern::getTranslationsForPatternTypes(),
            'urlPrefixes' => array_keys(PageUrl::$urlPrefixMap)
        ));
    }

    /**
     * Shows a message if there are no funnels configured
     * @return string
     */
    public function overview()
    {
        $this->checkSitePermission();
        $this->validator->checkReportViewPermission($this->idSite);

        $hasActivatedFunnels = $this->funnels->hasAnyActivatedFunnelForSite($this->idSite);

        if ($hasActivatedFunnels) {
            return '';
        }

        return $this->renderTemplate('overview', array(
            'canEditFunnels' => $this->validator->canWrite($this->idSite),
        ));
    }

    private function addMetricsToFunnel($funnel)
    {
        /** @var DataTable $metrics */
        $metrics = Request::processRequest('Funnels.getMetrics', array(
            'idFunnel' => $funnel['idfunnel'],
            'format_metrics' => '1',
            'filter_limit' => '-1'
        ));
        $metrics = $metrics->getFirstRow();

        if (!empty($metrics)) {
            $funnel['numEntries'] = $metrics->getColumn(Metrics::SUM_FUNNEL_ENTRIES);
            $funnel['numExits'] = $metrics->getColumn(Metrics::SUM_FUNNEL_EXITS);
            $funnel['numConversions'] = $metrics->getColumn(Metrics::NUM_CONVERSIONS);
            $funnel['conversionRate'] = $metrics->getColumn(Metrics::RATE_CONVERSION);
            $funnel['abandonedRate'] = $metrics->getColumn(Metrics::RATE_ABANDONED);
        } else {
            $funnel['numEntries'] = 0;
            $funnel['numExits'] = 0;
            $funnel['numConversions'] = 0;
            $funnel['conversionRate'] = 0;
            $funnel['abandonedRate'] = 0;
        }

        $funnel['urlSparklineConversions'] = $this->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::NUM_CONVERSIONS), 'getMetrics' => '1', 'idFunnel' => $funnel['idfunnel'], 'idGoal' => $funnel['idgoal']));
        $funnel['urlSparklineConversionRate'] = $this->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::RATE_CONVERSION), 'getMetrics' => '1', 'idFunnel' => $funnel['idfunnel'], 'idGoal' => $funnel['idgoal']));

        return $funnel;
    }

    public function getEvolutionGraph(array $columns = array(), array $defaultColumns = array())
    {
        $this->checkSitePermission();
        $this->validator->checkReportViewPermission($this->idSite);

        $idGoal = Common::getRequestVar('idGoal', null, 'int');
        $this->funnels->checkGoalFunnelExists($this->idSite, $idGoal);

        if (empty($columns)) {
            $columns = Common::getRequestVar('columns', false);
            if (false !== $columns) {
                $columns = Piwik::getArrayFromApiParameter($columns);
            }
        }

        $view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, 'Funnels.getMetrics');

        // configure displayed columns
        if (empty($columns)) {
            $columns = Common::getRequestVar('columns', false);
            if (false !== $columns) {
                $columns = Piwik::getArrayFromApiParameter($columns);
            }
        }
        if (false !== $columns) {
            $columns = !is_array($columns) ? array($columns) : $columns;
        }

        if (!empty($columns)) {
            $view->config->columns_to_display = $columns;
        } elseif (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
            $view->config->columns_to_display = $defaultColumns;
        }

        $view->config->selectable_columns = array(
            Metrics::RATE_CONVERSION,
            Metrics::NUM_CONVERSIONS,
            Metrics::RATE_ABANDONED,
            Metrics::SUM_FUNNEL_ENTRIES,
            Metrics::SUM_FUNNEL_EXITS,
        );

        $view->config->row_picker_match_rows_by = 'label';
        $view->config->documentation = Piwik::translate('General_EvolutionOverPeriod');

        return $this->renderView($view);
    }

    public function goalFunnelReport()
    {
        $this->checkSitePermission();
        $this->validator->checkReportViewPermission($this->idSite);

        $idGoal = Common::getRequestVar('idGoal', null, 'int');
        $period = Common::getRequestVar('period', null, 'string');
        $date = Common::getRequestVar('date', null, 'string');
        $segment = Request::getRawSegmentFromRequest();

        $this->funnels->checkGoalFunnelExists($this->idSite, $idGoal);

        $funnel = $this->funnels->getGoalFunnel($this->idSite, $idGoal);
        $funnel = $this->addMetricsToFunnel($funnel);

        // needed for when requesting evolution graph
        $_GET['idFunnel'] = (int) $funnel['idfunnel'];

        $evolution = $this->getEvolutionGraph(array(Metrics::RATE_CONVERSION));

        /** @var DataTable $funnelFlow */
        $funnelFlow = Request::processRequest('Funnels.getFunnelFlow', array(
            'idFunnel' => $funnel['idfunnel'],
            'format_metrics' => '1',
            'filter_limit' => '-1'
        ));

        $hasBeenPurged = false;
        $deleteReportsOlderThan = false;
        if ($funnelFlow->getRowsCount() === 0) {
            $hasBeenPurged = $this->hasReportBeenPurged($funnelFlow);
            $deleteReportsOlderThan = $this->getDeleteReportsOlderThan();
        }

        /** @var DataTable $goalsReport */
        $goalsReport = Request::processRequest('Goals.get', array(
            'idGoal' => $idGoal,
            'idSite' => $this->idSite,
            'columns' => array('nb_conversions', 'conversion_rate', 'revenue')
        ));
        $goalsSummary = $goalsReport->getFirstRow();

        return $this->renderTemplate('goalFunnelReport', array(
            'idsite' => $this->idSite,
            'evolution' => $evolution,
            'idSite' => $this->idSite,
            'period' => $period,
            'segment' => !empty($segment) ? $segment : '',
            'date' => $date,
            'funnel' => $funnel,
            'funnelFlow' => $funnelFlow,
            'randomNumber' => mt_rand(1, 9900000), // forces reloading of ng-includes
            'goalsSummary' => $goalsSummary,
            'canEditFunnels' => $this->validator->canWrite($this->idSite),
            'patternTranslations' => Pattern::getTranslationsForPatternTypes(),
            'hasBeenPurged' => $hasBeenPurged,
            'deleteReportsOlderThan' => $deleteReportsOlderThan
        ));
    }

    public function funnelSummary()
    {
        $this->checkSitePermission();
        $this->validator->checkReportViewPermission($this->idSite);

        $idGoal = Common::getRequestVar('idGoal', null, 'int');
        $period = Common::getRequestVar('period', null, 'string');
        $date = Common::getRequestVar('date', null, 'string');
        $segment = Request::getRawSegmentFromRequest();

        $this->funnels->checkGoalFunnelExists($this->idSite, $idGoal);

        $funnel = $this->funnels->getGoalFunnel($this->idSite, $idGoal);
        $funnel = $this->addMetricsToFunnel($funnel);

        // needed for when requesting evolution graph
        $_GET['idFunnel'] = (int) $funnel['idfunnel'];

        /** @var DataTable $funnelFlow */
        $funnelFlow = Request::processRequest('Funnels.getFunnelFlow', array(
            'idFunnel' => $funnel['idfunnel'],
            'format_metrics' => '1',
            'filter_limit' => '-1'
        ));

        /** @var DataTable $goalsReport */
        $goalsReport = Request::processRequest('Goals.get', array(
            'idGoal' => $idGoal,
            'idSite' => $this->idSite,
            'columns' => array('nb_conversions', 'conversion_rate', 'revenue')
        ));
        $goalsSummary = $goalsReport->getFirstRow();

        return $this->renderTemplate('funnelSummary', array(
            'idsite' => $this->idSite,
            'idSite' => $this->idSite,
            'period' => $period,
            'segment' => !empty($segment) ? $segment : '',
            'date' => $date,
            'funnel' => $funnel,
            'goalsSummary' => $goalsSummary,
            'funnelFlow' => $funnelFlow,
            'patternTranslations' => Pattern::getTranslationsForPatternTypes(),
        ));
    }

    private function getDeleteReportsOlderThan()
    {
        $settings = PrivacyManager::getPurgeDataSettings();

        if (!empty($settings['delete_reports_older_than'])) {
            return $settings['delete_reports_older_than'];
        }

        return '';
    }

    private function hasReportBeenPurged($dataTable)
    {
        if (!Plugin\Manager::getInstance()->isPluginActivated('PrivacyManager')) {
            return false;
        }

        return PrivacyManager::hasReportBeenPurged($dataTable);
    }
}
