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
namespace Piwik\Plugins\FormAnalytics\DataTable\Filter;

use Piwik\DataTable;

class ReplaceFormFieldLabel extends DataTable\BaseFilter
{
    /**
     * @var array
     */
    private $fieldsToDisplayNames = array();

    public function __construct(DataTable $table, $form)
    {
        parent::__construct($table);

        if (!empty($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (!empty($field['displayName']) && !empty($field['name'])) {
                    $this->fieldsToDisplayNames[$field['name']] = $field['displayName'];
                }
            }
        }
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $fieldName = $row->getColumn('label');

            if (!empty($this->fieldsToDisplayNames[$fieldName])) {
                $row->setColumn('label', $this->fieldsToDisplayNames[$fieldName]);
            }
        }

        $table->setLabelsHaveChanged();
    }
}