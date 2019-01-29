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
namespace Piwik\Plugins\Funnels\DataTable\Filter;

use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\Funnels\Archiver;
use Piwik\Plugins\Referrers\Referrers;

class ReplaceEntryLabel extends DataTable\BaseFilter
{
    private $referrers;

    /**
     * Constructor.
     *
     * @param DataTable $table
     */
    public function __construct(DataTable $table)
    {
        $this->enableRecursive = true;
        parent::__construct($table);
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        // here we replace labels for Entry Actions + subtables that shows referrers

        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $label = $row->getColumn('label');
            if ($label === Archiver::LABEL_NOT_DEFINED) {
                $row->setColumn('label', Piwik::translate('General_NotDefined', Piwik::translate('Actions_ColumnPageURL')));
            } elseif ($label === Archiver::LABEL_VISIT_ENTRY) {
                $row->setColumn('label', Piwik::translate('Referrers_Referrers'));
            } elseif ($label === Archiver::LABEL_DIRECT_ENTRY) {
                $row->setColumn('label', Piwik::translate('Referrers_DirectEntry'));
            }

            if ($row->getColumn('referer_type')) {
                if ($label !== Archiver::LABEL_DIRECT_ENTRY) {
                    $row->setMetadata('html_label_prefix', $this->getRefererTypePrefix($row));
                }
            }

            $row->deleteColumn('referer_type');
        }
        $table->setLabelsHaveChanged();

        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $this->filterSubTable($row);
        }
    }

    private function getRefererTypePrefix(DataTable\Row $row)
    {
        if (!isset($this->referrers)) {
            $this->referrers = new Referrers();
        }

        return $this->referrers->setGetAllHtmlPrefix($row->getColumn('referer_type'));
    }
}