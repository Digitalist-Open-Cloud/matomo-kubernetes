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

use Exception;
use Piwik\DataTable\DataTableInterface;
use Piwik\Piwik;

abstract class ReportType
{
    const ID = '';

    abstract public function needsDimensions();
    abstract public function getName();
    abstract public function getDefaultViewDataTable();
    abstract public function alwaysUseDefaultViewDataTable();

    /** @return DataTableInterface */
    abstract public function fetchApi($idSite, $idCustomReport, $period, $date, $segment, $expanded, $flat, $idSubtable, $columns);

    public function getRenderAction()
    {
        return 'getCustomReport';
    }

    /**
     * @return ReportType[]
     */
    public static function getAll()
    {
        return array(new Table(), new Evolution());
    }

    public static function factory($reportType)
    {
        $ids = array();
        foreach (self::getAll() as $report) {
            if ($report::ID == $reportType) {
                return $report;
            }
            $ids[] = $report::ID;
        }

        $title = Piwik::translate('CustomReports_ReportType');
        $message = Piwik::translate('CustomReports_ErrorXNotWhitelisted', array($title, implode(', ', $ids)));
        throw new Exception($message);
    }

}
