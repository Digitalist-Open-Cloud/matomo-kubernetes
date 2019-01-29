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
 *
 */
namespace Piwik\Plugins\UsersFlow\DataTable\Filter;

use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\DataTable\BaseFilter;
use Piwik\Plugins\UsersFlow\Metrics;

class LimitStepActions extends BaseFilter
{
    private $limitToXactionsPerStep;

    public function __construct($table, $limitToXactionsPerStep)
    {
        parent::__construct($table);

        $this->limitToXactionsPerStep  = $limitToXactionsPerStep;
    }

    /**
     * See {@link Limit}.
     *
     * @param DataTable $table
     */
    public function filter($table)
    {
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            // first we limit all steps to show eg only 5 actions and we merge all removed ones into summary row
            $subtable = $row->getSubtable();
            if ($subtable) {
                $this->limitActions($subtable);
            }
        }
    }

    private function limitActions(DataTable $table)
    {
        $table->setMetadata(DataTable::TOTAL_ROWS_BEFORE_LIMIT_METADATA_NAME, $table->getRowsCount());

        $subtableSummaryRow = $table->getEmptyClone($keepFilters = true);
        // we add all rows that we remove to the summaryRow table so we can still draw connections from the
        // others row and are still able to access these rows

        $summaryRow = $table->getRowFromId(DataTable::ID_SUMMARY_ROW);
        if (!$summaryRow) {
            $summaryRow = new DataTable\Row(array(DataTable\Row::COLUMNS => array(
                'label' => DataTable::LABEL_SUMMARY_ROW,
                Metrics::NB_VISITS => 0,
                Metrics::NB_EXITS => 0,
                Metrics::NB_PAGES_IN_GROUP => 0
            )));
            $table->addSummaryRow($summaryRow);
        }

        if ($table->getRowsCount() > $this->limitToXactionsPerStep) {

            $rows = $table->getRowsWithoutSummaryRow();

            $keepRows = array_splice($rows, $offset = 0, $this->limitToXactionsPerStep);
            $table->setRows($keepRows);
            // we keep only the top X rows, and then add all removed rows to the summary row

            $summaryRow->setColumn(Metrics::NB_PAGES_IN_GROUP, count($rows));

            foreach ($rows as $row) {
                $summaryRow->sumRow($row, $copyMetadata = false);

                $subtable = $row->getSubtable();
                if ($subtable) {
                    $subtableSummaryRow->addDataTable($subtable);
                }
            }

            unset($rows);

            $summaryVisits = $summaryRow->getColumn('nb_visits');

            if ($summaryVisits && $subtableSummaryRow->getRowsCount()) {
                // the other tables were already sorted before that filter, so we need to make sure to sort this one now as well
                // otherwise we might keep wrong rows
                $subtableSummaryRow->filter('Sort', array(Metrics::NB_VISITS));
                $summaryRow->setSubtable($subtableSummaryRow);
            }

        } else {
            $summaryVisits = $summaryRow->getColumn('nb_visits');

            $summaryRow->setColumn(Metrics::NB_PAGES_IN_GROUP, 0);
        }

        if (empty($summaryVisits)) {
            // if there were no visits, we can safely delete the row so it won't be shown in the visualization
            // with zero visits
            $table->deleteRow(DataTable::ID_SUMMARY_ROW);
        }
    }
}
