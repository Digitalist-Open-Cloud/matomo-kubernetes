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

use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\MediaAnalytics\Archiver;

class RenameUnknownLabel extends DataTable\BaseFilter
{

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $row = $table->getRowFromLabel(Archiver::LABEL_NOT_DEFINED);

        if (!empty($row)) {
            $row->setColumn('label', Piwik::translate('General_Unknown'));
        }
    }
}