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
use Piwik\DataTable\BaseFilter;
use Piwik\Piwik;
use Piwik\Plugins\UsersFlow\Archiver;

class ReplaceActionLabels extends BaseFilter
{
    public function __construct(DataTable $table)
    {
        parent::__construct($table);
        $this->enableRecursive = true;
    }

    /**
     * See {@link Limit}.
     *
     * @param DataTable $table
     */
    public function filter($table)
    {
        // for some reason this might be unset when being queued
        $this->enableRecursive = true;
        $row = $table->getRowFromLabel(Archiver::LABEL_SEARCH);
        if (!empty($row)) {
            $row->setColumn('label', Piwik::translate('General_Search'));
        }

        foreach ($table->getRowsWithoutSummaryRow() as $row1) {
            $this->filterSubTable($row1);
        }

        $summaryRow = $table->getRowFromId(DataTable::ID_SUMMARY_ROW);
        if (!empty($summaryRow)) {
            $this->filterSubTable($summaryRow);
        }
    }

}
