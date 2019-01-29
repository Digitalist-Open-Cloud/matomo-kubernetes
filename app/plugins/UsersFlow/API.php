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

use Piwik\Archive;
use Piwik\ArchiveProcessor;
use Piwik\DataAccess\ArchiveWriter;
use Piwik\DataTable;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\DataTable\Filter\Sort as SortFilter;
use Piwik\DataAccess\LogAggregator;
use Piwik\Plugins\UsersFlow\Archiver\DataSources;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Period\Factory as PeriodFactory;

/**
 * API for Users Flow. The API lets you explore details about how your users or visitors navigate through your
 * website.
 *
 * @method static \Piwik\Plugins\UsersFlow\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    const DATA_SOURCE_PAGE_URL = 'page_url';

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Get flow details for each available interaction step.
     *
     * The first table level will list all available interaction steps,
     * Their subtables list all pages and actions they viewed or performed within that interaction steps,
     * Their subtables list where they proceeded to afterwards as the next interaction.
     *
     * This report is polished to be more human readable and adds some processed metrics like the proceeded rate and exit rate.
     * If you are interested in integrating the data into a different system you may be interested in the "UsersFlow.getUsersFlow" API method.
     *
     * @param $idSite
     * @param $period
     * @param $date
     * @param bool $segment
     * @param bool $expanded
     * @param bool $flat
     * @param bool $idSubtable
     * @param string $dataSource Either 'page_url' or 'page_title'. For a list of all available data sources call the API method UsersFlow.getAvailableDataSources
     * @return DataTable|DataTable\Map
     */
    public function getUsersFlowPretty($idSite, $period, $date, $segment = false, $expanded = false, $flat = false, $idSubtable = false, $dataSource = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $dataSource = DataSources::getValidDataSource($dataSource);

        $table = $this->getDataTable($idSite, $period, $date, $segment, $dataSource, $expanded, $idSubtable, $flat);
        $table->queueFilter('\Piwik\Plugins\UsersFlow\DataTable\Filter\ReplaceActionLabels');

        if ($flat) {
            $table->queueFilterSubtables('\Piwik\Plugins\UsersFlow\DataTable\Filter\ReplaceActionLabels');
        }

        if (empty($idSubtable)) {
            $table->filter('ColumnCallbackReplace', array('label', function ($value) {
                if (is_numeric($value)) {
                    return Piwik::translate('UsersFlow_ColumnInteraction') . ' ' . $value;
                }

                return $value;
            }));
        }

        if ($flat) {
            $table->filterSubtables('ColumnCallbackDeleteRow', array('label', function ($value) {
                if ($value === false
                    || $value == DataTable::LABEL_SUMMARY_ROW
                    || $value === Piwik::translate('General_Others')) {
                    return true;
                }
                return false;
            }));
        }

        return $table;
    }

    /**
     * Get flow details for each available interaction step.
     *
     * The first table level will list all available interaction steps,
     * Their subtables list all pages and actions they viewed or performed within that interaction steps,
     * Their subtables list where they proceeded to afterwards as the next interaction.
     *
     * This report is "unformatted" and useful if you want to develop your own visualization on top of this API or if
     * you want to use the data for integrating it into another tool. If you are interested in requesting the report data
     * in a more human readable way you may want to have a look at "UsersFlow.getUsersFlowPretty".
     *
     * @param $idSite
     * @param $period
     * @param $date
     * @param int $limitActionsPerStep By default, only 5 rows per interaction step are returned and all other rows are merged into "Others".
     * @param bool $exploreStep
     * @param bool $exploreUrl
     * @param bool $segment
     * @param bool $expanded
     * @param string $dataSource Either 'page_url' or 'page_title'. For a list of all available data sources call the API method UsersFlow.getAvailableDataSources
     * @return DataTable|DataTable\Map
     */
    public function getUsersFlow($idSite, $period, $date, $limitActionsPerStep = 5, $exploreStep = false, $exploreUrl = false, $segment = false, $expanded = false, $dataSource = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $dataSource = DataSources::getValidDataSource($dataSource);

        $table = $this->getUsersFlowDataTable($idSite, $period, $date, $segment, $dataSource, $expanded, $exploreStep, $exploreUrl);
        $table->filter('\Piwik\Plugins\UsersFlow\DataTable\Filter\AddLabelsForMissingSteps');
        $table->filter('Sort', array('label', SortFilter::ORDER_ASC, $naturalSort = true, $recursiveSort = false));
        // we do not need to filter the subtables recursive as we will in the sub-subtable only keep rows anyway that are present in the sub-table
        $table->filterSubtables('Sort', array(Metrics::NB_VISITS, SortFilter::ORDER_DESC, $naturalSort = true, $recursiveSort = false));
        $table->filter('\Piwik\Plugins\UsersFlow\DataTable\Filter\LimitStepActions', array($limitActionsPerStep));
        $table->filter('\Piwik\Plugins\UsersFlow\DataTable\Filter\LimitProceededToActions');
        $table->filter('\Piwik\Plugins\UsersFlow\DataTable\Filter\ReplaceActionLabels');
        $table->disableFilter('Sort');

        return $table;
    }

    private function getUsersFlowDataTable($idSite, $period, $date, $segment, $dataSource, $expanded, $exploreStep, $exploreUrl)
    {
        if (empty($exploreStep) || empty($exploreUrl)) {
            $table = $this->getDataTable($idSite, $period, $date, $segment, $dataSource, $expanded);
            return $table;
        }

        $site = new Site($idSite);

        if (Period::isMultiplePeriod($date, $period)) {
            throw new \Exception('Multi period is not supported');
        } else {
            $period = PeriodFactory::makePeriodFromQueryParams($site->getTimezone(), $period, $date);
        }

        $parameters = new ArchiveProcessor\Parameters($site, $period, new Segment($segment, array($idSite)));
        $archiveWriter = new ArchiveWriter($parameters, $isTemporary = true);
        $logAggregator = new LogAggregator($parameters);

        $processor = new ArchiveProcessor($parameters, $archiveWriter, $logAggregator);
        $archiver = new Archiver($processor);

        $numMaxSteps = $exploreStep + 3;
        $numMaxStepsTotal = $this->configuration->getMaxSteps();
        if ($numMaxSteps > $numMaxStepsTotal) {
            $numMaxSteps = $numMaxStepsTotal;
        }

        $table = $archiver->makeDataTable($numMaxSteps, $dataSource, $exploreStep, $exploreUrl);
        $table->queueFilter('ReplaceSummaryRowLabel');

        return $table;
    }

    /**
     * Get all actions that were performed as part of a specific interaction step. For example "Give me all pages that
     * were viewed in the first step". Their subtables hold rows to where the users proceeded to next.
     *
     * @param $idSite
     * @param $period
     * @param $date
     * @param $interactionPosition
     * @param bool $offsetActionsPerStep
     * @param bool $segment
     * @param bool $idSubtable
     * @param string $dataSource Either 'page_url' or 'page_title'
     * @return DataTable|DataTable\Map
     */
    public function getInteractionActions($idSite, $period, $date, $interactionPosition, $offsetActionsPerStep = false, $segment = false, $idSubtable = false, $dataSource = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $requestsTargetLinks = !empty($idSubtable);

        if (!$requestsTargetLinks) {
            // in this case we are fetching first level actions and not the subtable of one of those actions
            $table = $this->getDataTable($idSite, $period, $date, $segment, $dataSource, $expanded = false);
            $stepRow = $table->getRowFromLabel($interactionPosition);

            if (!$stepRow) {
                return new DataTable();
            }
            $idSubtable = $stepRow->getIdSubDataTable();

            if (!$idSubtable) {
                return new DataTable();
            }

            unset($table); // the above table contains like only 10 rows so no need to destroy it
        }

        $stepSubtable = $this->getDataTable($idSite, $period, $date, $segment, $dataSource, $expanded = false, $idSubtable);
        $stepSubtable->filter('Sort', array(Metrics::NB_VISITS));
        if ($offsetActionsPerStep && !$requestsTargetLinks) {
            // this way we only show the actions within the others group
           $stepSubtable->filter('Limit', array($offset = $offsetActionsPerStep, $limit = -1, $keepSummaryRow = true));
        }

        $stepSubtable->filter('\Piwik\Plugins\UsersFlow\DataTable\Filter\ReplaceActionLabels');

        return $stepSubtable;
    }

    /**
     * Get a list of all available data sources
     * @return array
     */
    public function getAvailableDataSources()
    {
        Piwik::checkUserHasSomeViewAccess();

        return DataSources::getAvailableDataSources();
    }

    private function getDataTable($idSite, $period, $date, $segment, $dataSource, $expanded, $idSubtable = null, $flat = false)
    {
        if (false === $idSubtable) {
            $idSubtable = null;
        }

        if ($dataSource === DataSources::DATA_SOURCE_PAGE_TITLE) {
            $recordName = Archiver::USERSFLOW_PAGE_TITLE_ARCHIVE_RECORD;
        } else {
            $recordName = Archiver::USERSFLOW_ARCHIVE_RECORD;
        }

        return Archive::createDataTableFromArchive($recordName,
            $idSite, $period, $date, $segment, $expanded, $flat, $idSubtable);
    }

}
