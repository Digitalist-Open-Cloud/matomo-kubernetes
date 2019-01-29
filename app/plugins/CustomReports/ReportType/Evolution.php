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
namespace Piwik\Plugins\CustomReports\ReportType;

use Piwik\Archive;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph;
use Piwik\Plugins\CustomReports\Archiver;
use Piwik\Plugins\CustomReports\GetCustomReport;

class Evolution extends ReportType
{
    const ID = 'evolution';

    public function getName()
    {
        return Piwik::translate('MultiSites_Evolution');
    }

    public function needsDimensions()
    {
        return false;
    }

    public function getRenderAction()
    {
        return 'getEvolutionGraph';
    }

    public function getDefaultViewDataTable()
    {
        return JqplotGraph\Evolution::ID;
    }

    public function alwaysUseDefaultViewDataTable()
    {
        return true;
    }

    public function fetchApi($idSite, $idCustomReport, $period, $date, $segment, $expanded, $flat, $idSubtable, $columns)
    {
        $archive = Archive::build($idSite, $period, $date, $segment);

        $requestedColumns = Piwik::getArrayFromApiParameter($columns);

        $report = new GetCustomReport();
        $columns = $report->getMetricsRequiredForReport(null, $requestedColumns);

        $customReport = StaticContainer::get('\Piwik\Plugins\CustomReports\Model\CustomReportsModel');
        $reportData = $customReport->getCustomReport($idSite, $idCustomReport);

        $recordNamePrefix = Archiver::makeEvolutionRecordNamePrefix($idCustomReport, $reportData['revision']);

        $recordNames = array_map(function ($metricName) use ($idCustomReport, $reportData) {
            return Archiver::makeEvolutionRecordName($idCustomReport, $reportData['revision'], $metricName);
        }, $columns);

        $dataTable = $archive->getDataTableFromNumeric($recordNames);
        $dataTable->filter(function (DataTable $table) use ($recordNamePrefix) {
            foreach ($table->getRows() as $row) {
                $columns = $row->getColumns();
                foreach ($columns as $column => $value) {
                    if (strpos($column, $recordNamePrefix) === 0) {
                        $row->setColumn(substr($column, strlen($recordNamePrefix)), $value);
                        $row->deleteColumn($column);
                    }
                }
            }
        });

        if (!empty($requestedColumns)) {
            $dataTable->queueFilter('ColumnDelete', array($columnsToRemove = array(), $requestedColumns));
        }

        return $dataTable;
    }


}
