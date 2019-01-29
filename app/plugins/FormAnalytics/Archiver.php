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

use Piwik\ArchiveProcessor;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\FormAnalytics\Archiver\FieldDataArray;
use Piwik\Plugins\FormAnalytics\Archiver\LogAggregator;
use Piwik\Plugins\FormAnalytics\Archiver\SimpleDataArray;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Piwik\DataArray as PiwikDataArray;

class Archiver extends \Piwik\Plugin\Archiver
{
    const FORM_NUMERIC_RECORD_PREFIX = 'FormAnalytics';

    const FORM_FIELDS_RECORD = 'FormAnalytics_fields';
    const FORM_PAGE_URLS_RECORD = 'FormAnalytics_form_pageurls';
    const FORM_DROP_OFF_RECORD = 'FormAnalytics_dropoff_fields';
    const FORM_ENTRY_FIELDS_RECORD = 'FormAnalytics_entry_fields';

    /**
     * @var FormsModel
     */
    private $forms;

    /**
     * @var LogAggregator
     */
    private $aggregator;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->forms = StaticContainer::get('Piwik\Plugins\FormAnalytics\Model\FormsModel');
        $this->aggregator = new LogAggregator($this->getLogAggregator());
    }

    public static function completeRecordName($recordName, $idSiteForm)
    {
        return $recordName . '_' . (int) $idSiteForm;
    }

    public static function getMetricNameFromNumericRecordName($recordName, $idSiteForm = false)
    {
        $metricName = str_replace(self::FORM_NUMERIC_RECORD_PREFIX . '_', '', $recordName);

        if (!empty($idSiteForm)) {
            $metricName = str_replace('_' . $idSiteForm, '', $metricName);
        }

        // eg $metricName => nb_conversions
        return $metricName;
    }

    public static function buildNumericFormRecordName($metric, $idSiteForm = false)
    {
        $record = self::FORM_NUMERIC_RECORD_PREFIX . '_' . $metric;

        if (!empty($idSiteForm)) {
            $record .= '_' . (int) $idSiteForm;
        }

        // eg FormAnalytics_Form_nb_conversions_6 => nb_conversions
        return $record;
    }

    public static function getNumericFormRecordNames($metrics, $idSiteForm = false)
    {
        $recordNames = array();
        foreach ($metrics as $metric) {
            $recordNames[] = self::buildNumericFormRecordName($metric, $idSiteForm);
        }
        return $recordNames;
    }

    public function aggregateDayReport()
    {
        $idSite = $this->getIdSite();

        if (empty($idSite)) {
            return;
        }

        $forms = $this->getActivatedForms($idSite);

        $cursor = $this->aggregator->aggregateFormMetrics();
        $this->insertFormNumerics($cursor);
        unset($cursor);

        $dropOffArrays = array();
        $entryFieldArrays = array();
        $fieldArrays = array();
        $pageUrlArrays = array();

        foreach ($forms as $form) {
            $idSiteForm = (int) $form['idsiteform'];

            $dropOffArrays[$idSiteForm] = new SimpleDataArray();
            $entryFieldArrays[$idSiteForm] = new SimpleDataArray();
            $fieldArrays[$idSiteForm] = new FieldDataArray();
            $pageUrlArrays[$idSiteForm] = new SimpleDataArray();
        }

        $cursor = $this->aggregator->aggregateDropOffs();
        $this->addRowsToDataArray($dropOffArrays, $cursor);
        $this->insertDataArray(self::FORM_DROP_OFF_RECORD, $dropOffArrays);
        unset($dropOffArrays);
        unset($cursor);

        $cursor = $this->aggregator->aggregateEntryFields();
        $this->addRowsToDataArray($entryFieldArrays, $cursor);
        $this->insertDataArray(self::FORM_ENTRY_FIELDS_RECORD, $entryFieldArrays);
        unset($entryFieldArrays);
        unset($cursor);

        $cursor = $this->aggregator->aggregateFields();
        $this->addRowsToDataArray($fieldArrays, $cursor);
        unset($cursor);

        $cursor = $this->aggregator->aggregateSubmittedFields();
        $this->addRowsToDataArray($fieldArrays, $cursor);
        unset($cursor);

        $cursor = $this->aggregator->aggregateConvertedFields();
        $this->addRowsToDataArray($fieldArrays, $cursor);
        unset($cursor);

        $this->insertDataArray(self::FORM_FIELDS_RECORD, $fieldArrays);
        unset($fieldArrays);

        $cursor = $this->aggregator->aggregatePageUrls();
        $this->addRowsToDataArray($pageUrlArrays, $cursor);
        $this->insertDataArray(self::FORM_PAGE_URLS_RECORD, $pageUrlArrays);
        unset($pageUrlArrays);
    }

    private function insertFormNumerics($cursor)
    {
        $metrics = Metrics::getNumericFormMetrics();

        $numericEntries = array();

        while ($row = $cursor->fetch()) {
            $idSiteForm = $row['label'];
            foreach ($metrics as $metric) {
                if (isset($row[$metric])) {
                    $column = self::buildNumericFormRecordName($metric, $idSiteForm);
                    $totalColumn = self::buildNumericFormRecordName($metric, false);

                    if (isset($numericEntries[$column])) {
                        $numericEntries[$column] += $row[$metric];
                    } else {
                        $numericEntries[$column] = $row[$metric];
                    }

                    if (isset($numericEntries[$totalColumn])) {
                        $numericEntries[$totalColumn] += $row[$metric];
                    } else {
                        $numericEntries[$totalColumn] = $row[$metric];
                    }
                }
            }
        }

        $this->getProcessor()->insertNumericRecords($numericEntries);

        $cursor->closeCursor();
        unset($cursor);
    }

    /**
     * @param string $recordName
     * @param PiwikDataArray[] $dataArrays  indexed by IdsiteForm
     */
    private function insertDataArray($recordName, $dataArrays)
    {
        $maxRowsInTable = null;

        if (strpos($recordName, self::FORM_PAGE_URLS_RECORD) > -1) {
            $maxRowsInTable = 500;
        }

        foreach ($dataArrays as $idSiteForm => $dataArray) {
            $theRecordName = self::completeRecordName($recordName, $idSiteForm);
            $table = $dataArray->asDataTable();

            $serialized = $table->getSerialized($maxRowsInTable, $maxRowsInTable);
            $this->getProcessor()->insertBlobRecord($theRecordName, $serialized);

            Common::destroy($table);
            unset($table);
            unset($serialized);
        }
    }

    /**
     * @param SimpleDataArray[]|FieldDataArray[] $dataArrays
     * @param $cursor
     */
    private function addRowsToDataArray($dataArrays, $cursor)
    {
        while ($row = $cursor->fetch()) {
            $idSiteForm = $row['idsiteform'];
            unset($row['idsiteform']);
            if (isset($dataArrays[$idSiteForm])) {
                $dataArrays[$idSiteForm]->computeMetrics($row);
            }
        }
        $cursor->closeCursor();
    }

    protected function getActivatedForms($idSite)
    {
        if (!isset($idSite) || false === $idSite) {
            return array();
        }

        $forms = $this->forms->getFormsByStatuses($idSite, FormsModel::STATUS_RUNNING);

        return $forms;
    }

    public function aggregateMultipleReports()
    {
        $blobRecords = array();
        $numericRecords = array();
        $idSite = $this->getIdSite();

        if (empty($idSite)) {
            return;
        }

        $forms = $this->getActivatedForms($idSite);

        $metrics = Metrics::getNumericFormMetrics();
        foreach ($metrics as $metric) {
            $numericRecords[] = self::buildNumericFormRecordName($metric, false);
        }

        foreach ($forms as $form) {
            $idSiteForm = (int) $form['idsiteform'];

            $blobRecords[] = self::completeRecordName(self::FORM_DROP_OFF_RECORD, $idSiteForm);
            $blobRecords[] = self::completeRecordName(self::FORM_ENTRY_FIELDS_RECORD, $idSiteForm);
            $blobRecords[] = self::completeRecordName(self::FORM_FIELDS_RECORD, $idSiteForm);
            $blobRecords[] = self::completeRecordName(self::FORM_PAGE_URLS_RECORD, $idSiteForm);

            foreach ($metrics as $metric) {
                $numericRecords[] = self::buildNumericFormRecordName($metric, $idSiteForm);
            }
        }

        $columnsAggregationOperation = null;
        $this->getProcessor()->aggregateNumericMetrics($numericRecords);
        $this->getProcessor()->aggregateDataTableRecords($blobRecords, $maximumRowsInDataTableLevelZero = null,
                                              $maximumRowsInSubDataTable = null,
                                              $columnToSortByBeforeTruncation = null,
                                              $columnsAggregationOperation,
                                              $columnsToRenameAfterAggregation = array());
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
