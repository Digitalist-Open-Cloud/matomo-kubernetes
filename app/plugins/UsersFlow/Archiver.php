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
use Piwik\ArchiveProcessor;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Plugins\UsersFlow\Archiver\DataSources;
use Piwik\Plugins\UsersFlow\Archiver\LogAggregator;
use Piwik\Plugins\UsersFlow\Archiver\DataArray;
use Piwik\Plugins\UsersFlow\Archiver\StepDataArray;
use Piwik\Site;

class Archiver extends \Piwik\Plugin\Archiver
{
    const USERSFLOW_ARCHIVE_RECORD = 'UsersFlow_usersFlow';
    const USERSFLOW_PAGE_TITLE_ARCHIVE_RECORD = 'UsersFlow_usersFlowPageTitle';

    const LABEL_SEARCH = 'UsersFlow_LabelUsedSearch';

    /**
     * @var LogAggregator
     */
    private $logAggregator;

    /**
     * @var Configuration
     */
    private $configuration;

    private $maxRowsPerSubtable;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->configuration = StaticContainer::get('Piwik\Plugins\UsersFlow\Configuration');
        $this->logAggregator = new LogAggregator($this->getLogAggregator(), $this->configuration);
        $this->maxRowsPerSubtable = $this->configuration->getMaxRowsInActions();
    }

    public function aggregateDayReport()
    {
        $maxSteps = $this->configuration->getMaxSteps();
        $table = $this->makeDataTable($maxSteps, DataSources::DATA_SOURCE_PAGE_URL);
        $this->insertDataTable(self::USERSFLOW_ARCHIVE_RECORD, $table);

        $table = $this->makeDataTable($maxSteps, DataSources::DATA_SOURCE_PAGE_TITLE);
        $this->insertDataTable(self::USERSFLOW_PAGE_TITLE_ARCHIVE_RECORD, $table);
    }

    public function makeDataTable($numStepsToAggregate, $dataSource, $exploreStep = null, $exploreValueToMatch = null)
    {
        $allDataArray = new DataArray($numStepsToAggregate);

        $siteKeepsUrlFragments = $this->doesAnySiteKeepUrlFragments();

        $systemSettings = new SystemSettings();
        $ignoreSearchQuery = $systemSettings->ignoreUrlQuery->getValue();
        $ignoreDomain = $systemSettings->ignoreDomain->getValue();

        for ($step = 1; $step <= $numStepsToAggregate; $step++) {
            $stepDataArray = new StepDataArray();

            $query = $this->logAggregator->aggregateTopStepActions($step, $dataSource, $ignoreSearchQuery, $ignoreDomain, $siteKeepsUrlFragments, $exploreStep, $exploreValueToMatch);
            $cursor = Db::query($query['sql'], $query['bind']);
            while ($row = $cursor->fetch()) {
                $allDataArray->computeMetrics($row, $step);
                $stepDataArray->computeMetrics($row);
            }
            $stepTable = $stepDataArray->asDataTable();
            $stepTable->filter('Truncate', array($this->maxRowsPerSubtable,
                    DataTable::LABEL_SUMMARY_ROW,
                    Metrics::NB_VISITS,
                    $filterRecursive = true)
            );

            $allDataArray->setStepTable($stepTable, $step);
            $cursor->closeCursor();
        }

        return $allDataArray->asDataTable();
    }

    private function insertDataTable($recordName, DataTable $table)
    {
        $serialized = $table->getSerialized();
        $this->getProcessor()->insertBlobRecord($recordName, $serialized);

        Common::destroy($table);
        unset($table);
        unset($serialized);
    }

    public function aggregateMultipleReports()
    {
        $columnsAggregationOperation = null;

        $records = array(
            self::USERSFLOW_ARCHIVE_RECORD,
            self::USERSFLOW_PAGE_TITLE_ARCHIVE_RECORD,
        );
        $this->getProcessor()->aggregateDataTableRecords($records,
            $maximumRowsInDataTableLevelZero = null,
            $maximumRowsInSubDataTable = null,
            $columnToSortByBeforeTruncation = null,
            $columnsAggregationOperation,
            $columnsToRenameAfterAggregation = null,
            $countRowsRecursive = false);
    }

    private function doesAnySiteKeepUrlFragments()
    {
        foreach ($this->getProcessor()->getParams()->getIdSites() as $idSite) {
            $site = Site::getSite($idSite);
            if (!empty($site['keep_url_fragment'])) {
                return true;
            }
        }

        return false;
    }

}
