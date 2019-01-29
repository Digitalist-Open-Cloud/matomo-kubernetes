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
namespace Piwik\Plugins\MediaAnalytics\DataTable\Filter;

use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Date;

class RemoveHoursInFuture extends DataTable\BaseFilter
{
    /**
     * @var string
     */
    private $timezone;

    /**
     * @var string
     */
    private $period;

    /**
     * @var string
     */
    private $date;

    /**
     * Constructor.
     *
     * @param DataTable $table
     * @param int $idSite
     * @param string $period
     * @param string $date
     */
    public function __construct(DataTable $table, $timezone, $period, $date)
    {
        parent::__construct($table);
        $this->timezone = $timezone;
        $this->period = $period;
        $this->date = $date;
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        try {
            if (StaticContainer::get('test.vars.doNotRemoveHoursInFuture')) {
                return;
            }
        } catch (\Exception $e) {}

        if ($this->period !== 'day') {
            return;
        }

        $now = Date::factory('now', $this->timezone);

        if ($this->date !== 'today' && $this->date !== $now->toString()) {
            return;
        }

        $currentHour = $now->toString('G');

        $idsToDelete = array();
        foreach ($table->getRowsWithoutSummaryRow() as $id => $row) {
            $hour = $row->getColumn('label');
            if ($hour > $currentHour) {
                $idsToDelete[] = $id;
            }
        }

        $table->deleteRows($idsToDelete);
    }
}