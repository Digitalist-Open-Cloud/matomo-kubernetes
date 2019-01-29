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

use Piwik\ArchiveProcessor;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataArray as PiwikDataArray;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Plugins\Funnels\Archiver\ActionsDataArray;
use Piwik\Plugins\Funnels\Archiver\LogAggregator;
use Piwik\Plugins\Funnels\Archiver\Populator;
use Piwik\Plugins\Funnels\Archiver\FlowDataArray;
use Piwik\Plugins\Funnels\Model\FunnelsModel;

class Archiver extends \Piwik\Plugin\Archiver
{
    const FUNNELS_ENTRIES_RECORD = 'Funnels_entries_';
    const FUNNELS_EXITS_RECORD = 'Funnels_exits_';
    const FUNNELS_FLOW_RECORD = 'Funnels_flow_';

    const FUNNELS_NUM_ENTRIES_RECORD = 'Funnels_funnel_sum_entries_';
    const FUNNELS_NUM_EXITS_RECORD = 'Funnels_funnel_sum_exits_';
    const FUNNELS_NUM_CONVERSIONS_RECORD = 'Funnels_funnel_nb_conversions_';

    const LABEL_NOT_DEFINED = 'Funnels_ValueNotSet';
    const LABEL_VISIT_ENTRY = 'Funnels_VisitEntry';
    const LABEL_VISIT_EXIT = 'Funnels_VisitExit';
    const LABEL_DIRECT_ENTRY = 'Funnels_DirectEntry';

    /**
     * @var FunnelsModel
     */
    private $funnels;

    /**
     * @var Populator
     */
    private $populator;

    /**
     * @var LogAggregator
     */
    private $aggregator;

    /**
     * @var int
     */
    private $maximumRowsInActions;

    /**
     * @var int
     */
    private $maximumRowsInReferrers;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->funnels = StaticContainer::get('Piwik\Plugins\Funnels\Model\FunnelsModel');
        $this->populator = StaticContainer::get('Piwik\Plugins\Funnels\Archiver\Populator');
        $this->aggregator = new LogAggregator($this->getLogAggregator());

        $configuration = StaticContainer::get('Piwik\Plugins\Funnels\Configuration');
        $this->maximumRowsInActions = $configuration->getMaxRowsInActions();
        $this->maximumRowsInReferrers = $configuration->getMaxRowsInReferrers();
    }

    public function aggregateDayReport()
    {
        $idSite = $this->getIdSite();
        $funnels = $this->getActivatedFunnelsWithSteps($idSite);

        // $startDateTime eg '2020-01-01 23:00:00';
        // $endDateTime eg '2020-01-02 22:59:59';
        $startDateTime = $this->getProcessor()->getParams()->getDateStart()->getDateStartUTC();
        $endDateTime = $this->getProcessor()->getParams()->getDateEnd()->getDateEndUTC();
        $segment = $this->getProcessor()->getParams()->getSegment();

        if (empty($segment) || $segment->isEmpty()) {
            // we only populate when no segment is applied, lowers needed resources and speeds up requesting reports
            // when using segments. Also lowers concurrency when multiple processes archive this report at the
            // same time

            // prevent running a bug where archiving without segment starts at the same time several times eg when loading
            // dashboard
            $lock = 'funnels_populate_log_site_' . $idSite;
            if (Db::getDbLock($lock)) {
                try {
                    // pre-populate log_funnel
                    foreach ($funnels as $funnel) {
                        $this->populator->populateLogFunnel($funnel, $startDateTime, $endDateTime);

                        // this might need to go into segmentation and always done before archiving, but depends on how we interpret
                        // action segments
                        $this->populator->updateEntryAndExitStep($funnel['idsite'], $funnel['idfunnel'], $startDateTime, $endDateTime);
                    }

                } catch (\Exception $e) {
                    Db::releaseDbLock($lock);

                    throw $e;
                }

                Db::releaseDbLock($lock);
            }
        }

        $processor = $this->getProcessor();

        // archive reports
        foreach ($funnels as $funnel) {
            $idFunnel = (int) $funnel['idfunnel'];

            $recordFlow = self::completeRecordName(self::FUNNELS_FLOW_RECORD, $idFunnel);
            $recordEntry = self::completeRecordName(self::FUNNELS_ENTRIES_RECORD, $idFunnel);
            $recordExit = self::completeRecordName(self::FUNNELS_EXITS_RECORD, $idFunnel);

            $recordNumEntries = self::completeRecordName(self::FUNNELS_NUM_ENTRIES_RECORD, $idFunnel);
            $recordNumExits = self::completeRecordName(self::FUNNELS_NUM_EXITS_RECORD, $idFunnel);
            $recordNumConversions = self::completeRecordName(self::FUNNELS_NUM_CONVERSIONS_RECORD, $idFunnel);

            $flowDataArray = new FlowDataArray($funnel);

            $cursor = $this->aggregator->aggregateNumHitsPerStep($idFunnel);
            $this->addRowsToDataArray($flowDataArray, $cursor);

            $cursor = $this->aggregator->aggregateNumEntriesPerStep($idFunnel);
            $this->addRowsToDataArray($flowDataArray, $cursor);

            $cursor = $this->aggregator->aggregateNumExitsPerStep($idFunnel);
            $this->addRowsToDataArray($flowDataArray, $cursor);

            $processor->insertNumericRecord($recordNumEntries, $flowDataArray->getNumEntries());
            $processor->insertNumericRecord($recordNumExits, $flowDataArray->getNumExits());
            $processor->insertNumericRecord($recordNumConversions, $flowDataArray->getNumConversions());

            $this->insertDataArray($recordFlow, $flowDataArray);
            unset($flowDataArray);
            unset($cursor);


            $cursor = $this->aggregator->aggregateActionReferrers($idFunnel);
            $referers = new ActionsDataArray($funnel);
            $this->addRowsToDataArray($referers, $cursor);
            $referers = $referers->asDataTable();
            $referers->filterSubtables('Truncate', array($this->maximumRowsInReferrers,
                                        DataTable::LABEL_SUMMARY_ROW,
                                        Metrics::NUM_HITS));

            $cursor = $this->aggregator->aggregateEntriesActions($idFunnel);
            $dataArray = new ActionsDataArray($funnel);
            $this->addRowsToDataArray($dataArray, $cursor);
            $dataArray->setReferersTable($referers);

            $this->insertDataArray($recordEntry, $dataArray, Metrics::NUM_HITS);
            unset($dataArray);
            unset($cursor);

            Common::destroy($referers);
            unset($referers);

            $cursor = $this->aggregator->aggregateExitActions($idFunnel);
            $dataArray = new ActionsDataArray($funnel);
            $this->addRowsToDataArray($dataArray, $cursor);
            $this->insertDataArray($recordExit, $dataArray, Metrics::NUM_HITS);
            unset($dataArray);
            unset($cursor);
        }
    }

    private function insertDataArray($recordName, PiwikDataArray $dataArray, $sortBy = null)
    {
        $table = $dataArray->asDataTable();

        $serialized = $table->getSerialized($maxRows = null, $this->maximumRowsInActions, $sortBy);
        $this->getProcessor()->insertBlobRecord($recordName, $serialized);

        Common::destroy($table);
        unset($table);
        unset($serialized);
    }

    /**
     * @param FlowDataArray|ActionsDataArray $dataArray
     * @param $cursor
     */
    private function addRowsToDataArray($dataArray, $cursor)
    {
        while ($row = $cursor->fetch()) {
            $dataArray->computeMetrics($row);
        }
        $cursor->closeCursor();
    }

    public static function completeRecordName($recordName, $idFunnel)
    {
        return $recordName . (int) $idFunnel;
    }

    public static function getNumericRecordNames($idFunnel)
    {
        return array(
            self::completeRecordName(self::FUNNELS_NUM_ENTRIES_RECORD, $idFunnel),
            self::completeRecordName(self::FUNNELS_NUM_EXITS_RECORD, $idFunnel),
            self::completeRecordName(self::FUNNELS_NUM_CONVERSIONS_RECORD, $idFunnel),
        );
    }

    public static function getNumericColumnNameFromRecordName($recordName, $idFunnel)
    {
        // eg Funnels_nb_conversions_6 => nb_conversions
        return str_replace(array('Funnels_', '_' . $idFunnel), '', $recordName);
    }

    protected function getActivatedFunnelsWithSteps($idSite)
    {
        if (!isset($idSite) || false === $idSite) {
            return array();
        }

        $funnels = $this->funnels->getAllActivatedFunnelsForSite($idSite);

        if (empty($funnels)) {
            return array();
        }

        $withSteps = array();

        foreach ($funnels as $funnel) {
            // in theory the system should prevent from being able to activate a funnel without step, but we make sure to
            // just ignore such funnels
            if (!empty($funnel['steps'])) {
                $withSteps[] = $funnel;
            }
        }

        return $withSteps;
    }

    public function aggregateMultipleReports()
    {
        $idSite = $this->getIdSite();
        $funnels = $this->getActivatedFunnelsWithSteps($idSite);

        $blobRecordNames = array();
        $numericRecordNames = array();
        foreach ($funnels as $funnel) {
            $blobRecordNames[] = self::completeRecordName(self::FUNNELS_ENTRIES_RECORD, $funnel['idfunnel']);
            $blobRecordNames[] = self::completeRecordName(self::FUNNELS_EXITS_RECORD, $funnel['idfunnel']);
            $blobRecordNames[] = self::completeRecordName(self::FUNNELS_FLOW_RECORD, $funnel['idfunnel']);

            foreach (self::getNumericRecordNames($funnel['idfunnel']) as $numericRecord) {
                $numericRecordNames[] = $numericRecord;
            }
        }

        $columnsAggregationOperation = array(
            'referer_type' => function ($thisValue, $otherValue ) {
                // edge case. when two different referrer types have the same label, instead of aggregating we unset the
                // referer_type as it cannot be clearly assigned to either. Same logic is implemented in ActionsDataArray
                if ($thisValue != $otherValue) { return ''; }
                return $thisValue;
            }
        );

        $this->getProcessor()->aggregateDataTableRecords(
            $blobRecordNames, $maxRows = null, $this->maximumRowsInActions, Metrics::NUM_HITS,
            $columnsAggregationOperation, $columnsToRenameAfterAggregation = null, $countRecursive = false
        );
        $this->getProcessor()->aggregateNumericMetrics($numericRecordNames, $operation = 'sum');
    }

    protected function getIdSite()
    {
        $idSites = $this->getProcessor()->getParams()->getIdSites();

        if (count($idSites) > 1) {
            return null;
        }

        return reset($idSites);
    }
}
