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
use Piwik\Archive;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\Funnels\Db\Pattern;
use Piwik\Plugins\Funnels\Input\Step;
use Exception;
use Piwik\Plugins\Funnels\Input\Validator;
use Piwik\Plugins\Funnels\Model\FunnelsModel;
use Piwik\Plugin\API as PluginApi;

/**
 * API for plugin Funnels
 *
 * @method static \Piwik\Plugins\Funnels\API getInstance()
 */
class API extends PluginApi
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

    public function __construct(FunnelsModel $funnel, Validator $validator, Pattern $pattern)
    {
        $this->funnels = $funnel;
        $this->validator = $validator;
        $this->pattern = $pattern;
    }

    /**
     * Get summary metrics for a specific funnel like the number of conversions, the conversion rate, the number of
     * entries etc.
     *
     * @param $idSite
     * @param $period
     * @param $date
     * @param bool|int $idFunnel  Either idFunnel or idGoal has to be set
     * @param bool|int $idGoal    Either idFunnel or idGoal has to be set. If goal given, will return the latest funnel for that goal
     * @param bool $segment
     * @return DataTable|DataTable\Map
     */
    public function getMetrics($idSite, $period, $date, $idFunnel = false, $idGoal = false, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $idFunnel = $this->getIdFunnelForReport($idSite, $idFunnel, $idGoal);

        $recordNames = Archiver::getNumericRecordNames($idFunnel);

        $archive = Archive::build($idSite, $period, $date, $segment);
        $table = $archive->getDataTableFromNumeric($recordNames);

        $columnMapping = array();
        foreach ($recordNames as $recordName) {
            $columnMapping[$recordName] = Archiver::getNumericColumnNameFromRecordName($recordName, $idFunnel);
        }

        $table->filter('ReplaceColumnNames', array($columnMapping));

        return $table;
    }

    private function getIdFunnelForReport($idSite, $idFunnel, $idGoal)
    {
        $isEcommerceOrder = $idGoal === 0 || $idGoal === '0';

        if (empty($idFunnel) && FunnelsModel::isValidGoalId($idGoal)) {
            // fetching by idGoal is needed for email reports
            $this->funnels->checkGoalFunnelExists($idSite, $idGoal);
            $funnel = $this->funnels->getGoalFunnel($idSite, $idGoal);
            $idFunnel = $funnel['idfunnel'];
        } elseif (empty($idFunnel) && empty($idGoal) && !$isEcommerceOrder) {
            throw new Exception('No idFunnel or idGoal given');
        } else {
            $this->funnels->checkFunnelExists($idSite, $idFunnel);
        }

        return $idFunnel;
    }

    /**
     * Get funnel flow information. The returned datatable will include a row for each step within the funnel
     * showing information like how many visits have entered or left the funnel at a certain position, how many
     * have completed a certain step etc.
     *
     * @param $idSite
     * @param $period
     * @param $date
     * @param bool|int $idFunnel  Either idFunnel or idGoal has to be set
     * @param bool|int $idGoal    Either idFunnel or idGoal has to be set. If goal given, will return the latest funnel for that goal
     * @param bool $segment
     * @return DataTable
     * @throws Exception
     */
    public function getFunnelFlow($idSite, $period, $date, $idFunnel = false, $idGoal = false, $segment = false)
    {
        $this->validator->checkReportViewPermission($idSite);

        $idFunnel = $this->getIdFunnelForReport($idSite, $idFunnel, $idGoal);

        $funnel = $this->funnels->getFunnel($idFunnel);

        $record = Archiver::completeRecordName(Archiver::FUNNELS_FLOW_RECORD, $idFunnel);

        $table = $this->getDataTable($record, $idSite, $period, $date, $segment, $expanded = false, $idSubtable = false);
        $table->filter('Piwik\Plugins\Funnels\DataTable\Filter\ForceSortByStepPosition');
        $table->filter('Piwik\Plugins\Funnels\DataTable\Filter\ComputeBackfills');
        $table->filter('Piwik\Plugins\Funnels\DataTable\Filter\RemoveExitsFromLastStep', array($funnel));
        $table->queueFilter('Piwik\Plugins\Funnels\DataTable\Filter\AddStepDefinitionMetadata', array($funnel));
        $table->queueFilter('Piwik\Plugins\Funnels\DataTable\Filter\ReplaceFunnelStepLabel', array($funnel));

        return $table;
    }

    /**
     * Get all entry actions for the given funnel at the given step.
     *
     * @param $idSite
     * @param $period
     * @param $date
     * @param $idFunnel
     * @param bool $segment
     * @param bool $step
     * @param bool $expanded
     * @param bool $idSubtable
     * @return DataTable
     */
    public function getFunnelEntries($idSite, $period, $date, $idFunnel, $segment = false, $step = false, $expanded = false, $idSubtable = false)
    {
        $record = Archiver::FUNNELS_ENTRIES_RECORD;

        $table = $this->getActionReport($record, $idSite, $period, $date, $idFunnel, $segment, $step, $expanded, $idSubtable);
        $table->filter('Piwik\Plugins\Funnels\DataTable\Filter\ReplaceEntryLabel');

        return $table;
    }

    /**
     * Get all exit actions for the given funnel at the given step.
     *
     * @param $idSite
     * @param $period
     * @param $date
     * @param $idFunnel
     * @param bool $segment
     * @param bool $step
     * @return DataTable
     */
    public function getFunnelExits($idSite, $period, $date, $idFunnel, $segment = false, $step = false)
    {
        $record = Archiver::FUNNELS_EXITS_RECORD;

        $table = $this->getActionReport($record, $idSite, $period, $date, $idFunnel, $segment, $step);
        $table->filter('Piwik\Plugins\Funnels\DataTable\Filter\ReplaceExitLabel');

        return $table;
    }

    private function getActionReport($record, $idSite, $period, $date, $idFunnel, $segment = false, $step = false, $expanded = false, $idSubtable = false)
    {
        $this->validator->checkReportViewPermission($idSite);
        $this->funnels->checkFunnelExists($idSite, $idFunnel);

        $record = Archiver::completeRecordName($record, $idFunnel);

        $root = $this->getDataTable($record, $idSite, $period, $date, $segment, $expanded, $idSubtable);

        if (!empty($idSubtable)) {
            // a subtable was requested specifically. This is usually the case when fetching the referrers for entries

            return $root;
        }

        if (!empty($step)) {
            $stepRow = $root->getRowFromLabel($step);

            if (!empty($stepRow)) {
                $idSubtable = $stepRow->getIdSubDataTable();
            }

            if (empty($idSubtable)) {
                return new DataTable();
            }

            $stepTable = $this->getDataTable($record, $idSite, $period, $date, $segment, $expanded, $idSubtable);

            $stepTable->filter('ColumnCallbackAddMetadata', array('label', 'url', function ($label) {
                if ($label === Archiver::LABEL_NOT_DEFINED
                    || $label === Archiver::LABEL_VISIT_ENTRY
                    || $label === Archiver::LABEL_VISIT_EXIT) {
                    return false;
                }
                return $label;
            }, $functionParams = null, $applyToSummary = false));

            return $stepTable;
        }

        $funnel = $this->funnels->getFunnel($idFunnel);

        $root->filter('Piwik\Plugins\Funnels\DataTable\Filter\ForceSortByStepPosition');
        $root->queueFilter('Piwik\Plugins\Funnels\DataTable\Filter\ReplaceFunnelStepLabel', array($funnel));

        return $root;
    }

    /**
     * @param $recordName
     * @param $idSite
     * @param $period
     * @param $date
     * @param $segment
     * @param $expanded
     * @param $idSubtable
     * @return DataTable
     */
    private function getDataTable($recordName, $idSite, $period, $date, $segment, $expanded, $idSubtable)
    {
        $table = Archive::createDataTableFromArchive($recordName, $idSite, $period, $date, $segment, $expanded, $flat = false, $idSubtable);

        return $table;
    }

    /**
     * Get funnel information for this goal.
     *
     * @param int $idSite
     * @param int $idGoal
     * @return array|null   Null when no funnel has been configured yet, the funnel otherwise.
     * @throws Exception
     */
    public function getGoalFunnel($idSite, $idGoal)
    {
        // it is important to not throw an exception if a goal does not exist yet. Otherwise we would see a notification
        // in the Manage Goals UI when a user is editing a goal and has not configured a funnel yet for that goal.

        // while view users can view funnel information in the report, we do not expose all the details via the API
        // therefore admin / write permission is required
        $this->validator->checkWritePermission($idSite);

        $this->funnels->checkGoalExists($idSite, $idGoal);

        return $this->funnels->getGoalFunnel($idSite, $idGoal);
    }

    /**
     * Deletes the given goal funnel.
     *
     * @param int $idSite
     * @param int $idGoal
     * @throws Exception
     */
    public function deleteGoalFunnel($idSite, $idGoal)
    {
        $this->validator->checkWritePermission($idSite);

        $this->funnels->deleteGoalFunnel($idSite, $idGoal);
    }

    /**
     * Sets (overwrites) a funnel for this goal.
     *
     * @param int $idSite
     * @param int $idGoal
     * @param int $isActivated   "0" or "1". As soon as a funnel is activated, a report will be generated for this funnel
     * @param array $steps   If $isActivated = true, there has to be at least one step
     * @return int   The id of the created or updated funnel
     * @throws Exception
     */
    public function setGoalFunnel($idSite, $idGoal, $isActivated, $steps)
    {
        $this->validator->checkWritePermission($idSite);
        $steps = $this->unsanitizeSteps($steps);
        $this->validator->validateFunnelConfiguration($isActivated, $steps);
        $this->funnels->checkGoalExists($idSite, $idGoal);

        $now = Date::now()->getDatetime();
        $isActivated = !empty($isActivated);

        if (empty($steps)) {
            $steps = array();
        }

        return $this->funnels->setGoalFunnel($idSite, $idGoal, $isActivated, $steps, $now);
    }

    private function unsanitizeSteps($steps)
    {
        if (!empty($steps) && is_array($steps)) {
            foreach ($steps as $index => $step) {
                if (!empty($step['pattern']) && is_string($step['pattern'])) {
                    $steps[$index]['pattern'] = Common::unsanitizeInputValue($step['pattern']);
                }
            }
        }

        return $steps;
    }

    /**
     * Get a list of available pattern types that can be used to configure a funnel step.
     * @return array
     * @throws Exception
     */
    public function getAvailablePatternMatches()
    {
        Piwik::checkUserHasSomeAdminAccess();

        return Pattern::getSupportedPatterns();
    }

    /**
     * Tests whether a URL matches any of the step patterns.
     *
     * @param string $url eg 'http://www.example.com/path/dir' or a value for event category, event name, page title, ...
     * @param array $steps eg array(array('pattern_type' => 'path_contains', 'pattern' => 'path/dir'))
     * @return array
     * @throws Exception
     */
    public function testUrlMatchesSteps($url, $steps)
    {
        Piwik::checkUserHasSomeAdminAccess();

        if ($url === '' || $url === false || $url === null) {
            return array('url' => '', 'tests' => array());
        }

        if (!is_array($steps)) {
            throw new Exception(Piwik::translate('Funnels_ErrorNotAnArray', 'steps'));
        }

        $url = Common::unsanitizeInputValue($url);
        $steps = $this->unsanitizeSteps($steps);

        $results = array();

        foreach ($steps as $index => $step) {
            $stepInput = new Step($step, $index);
            $stepInput->checkPatternType();
            $stepInput->checkPattern();

            $matching = $this->pattern->matchesUrl($url, $step['pattern_type'], $step['pattern']);

            $results[] = array(
                'matches' => $matching,
                'pattern_type' => $step['pattern_type'],
                'pattern' => $step['pattern'],
            );
        }

        return array('url' => $url, 'tests' => $results);
    }
}
