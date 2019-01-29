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
namespace Piwik\Plugins\CustomReports\DataTable\Filter;

use Piwik\Columns\Dimension;
use Piwik\DataTable\BaseFilter;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\CustomReports\Archiver;
use Piwik\Metrics\Formatter;

class ReportTypeTableFilter extends BaseFilter
{
    /** @var  Dimension[] */
    private $dimensions;
    /** @var  Dimension */
    private $dimension;
    private $idSite;

    public function __construct($table, $idSite, $dimension, $nestedDimensions)
    {
        parent::__construct($table);
        $this->dimension = $dimension;
        $this->dimensions = $nestedDimensions;
        $this->idSite = $idSite;
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $this->renameRowDimension($table, $this->dimension, $this->dimensions);
        $table->setLabelsHaveChanged();
    }

    /**
     * @param DataTable $table
     * @param Dimension $dimension
     * @param Dimension[] $dimension
     */
    private function renameRowDimension($table, $dimension, $dimensions)
    {
        if (!$dimension) {
            return;
        }

        $formatter = new Formatter();

        $nextDimension = null;
        if (!empty($dimensions)) {
            $nextDimension = array_shift($dimensions);
        }

        foreach ($table->getRowsWithoutSummaryRow() as $row) {

            $label = $row->getColumn('label');
            if ($label === Archiver::LABEL_NOT_DEFINED) {
                $label = Piwik::translate('General_NotDefined', $dimension->getName());
            } else {
                $label = $dimension->formatValue($label, $this->idSite, $formatter);
            }

            $row->setColumn('label', $label);

            $subtable = $row->getSubtable();
            if ($subtable) {
                $this->renameRowDimension($subtable, $nextDimension, $dimensions);
            }
        }
    }
}