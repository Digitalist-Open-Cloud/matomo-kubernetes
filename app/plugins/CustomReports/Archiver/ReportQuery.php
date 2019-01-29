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

namespace Piwik\Plugins\CustomReports\Archiver;

use Piwik\Plugin\LogTablesProvider;

class ReportQuery
{
    /**
     * @var string[]
     */
    private $selects = array();

    /**
     * @var string[]|array[]
     */
    private $from = array();

    /**
     * @var string[]
     */
    private $where = array();

    /**
     * @var string[]
     */
    private $groupBy = array();

    private $extraWhere = null;
    private $extraBind = array();

    private $sortBy = false;

    /**
     * @var LogTablesProvider
     */
    private $logTablesProvider;

    public function __construct(LogTablesProvider $logTablesProvider)
    {
        $this->logTablesProvider = $logTablesProvider;
    }

    public function addSelect($select)
    {
        $this->selects[] = $select;
    }

    public function hasFrom($table)
    {
        $has = in_array($table, $this->from, $strict = true);

        if ($has) {
            return true;
        }

        foreach ($this->from as $from) {
            if (is_array($from)) {
                if (!isset($from['tableAlias']) && $from['table'] === $table) {
                    return true;
                } elseif (isset($from['tableAlias']) && $from['tableAlias'] === $table) {
                    return true;
                }
            }
        }

        if (is_array($table) && isset($table['table']) && !isset($table['tableAlias'])) {
            return $this->hasFrom($table['table']);
        }

        if (is_array($table) && isset($table['table']) && isset($table['tableAlias'])) {
            return $this->hasFrom($table['tableAlias']);
        }

        return false;
    }

    public function getFromAlias($table)
    {
        if (is_array($table) && isset($table['table']) && !isset($table['tableAlias'])) {
            return $this->getFromAlias($table['table']);
        }

        if (is_array($table) && isset($table['table']) && isset($table['tableAlias'])) {
            return $this->getFromAlias($table['tableAlias']);
        }

        if (is_string($table) && in_array($table, $this->from, $strict = true)) {
            return $table;
        }

        foreach ($this->from as $from) {
            if (is_array($from)) {
                if (!isset($from['tableAlias']) && isset($from['table']) && $from['table'] === $table) {
                    return $table;
                } elseif (isset($from['tableAlias']) && ($from['tableAlias'] === $table || $from['table'] === $table)) {
                    return $from['tableAlias'];
                }
            }
        }
    }

    public function addFrom($table)
    {
        if (!$this->hasFrom($table)) {
            if (is_string($table) && !$this->isTableJoinable($table)) {
                throw new NotJoinableException("Table $table cannot be joined");
            }

            if (is_array($table) && isset($table['table']) && !isset($table['tableAlias']) && !isset($table['joinOn']) && !$this->isTableJoinable($table['table'])) {
                throw new NotJoinableException("Table " . $table['table'] . " cannot be joined");
            }

            $this->from[] = $table;
        }
    }

    public function addWhere($where)
    {
        if (!in_array($where, $this->where, $strict = true)) {
            // make sure we do not add it twice, happens eg if two metrics have same discriminator
            $this->where[] = $where;
        }
    }

    public function addGroupBy($groupBy)
    {
        if (!in_array($groupBy, $this->groupBy)) {
            $this->groupBy[] = $groupBy;
        }
    }

    public function setSortBy($sortBy)
    {
        if (empty($this->sortBy)) {
            $this->sortBy = $sortBy;
        }
    }

    public function addExtraWhere($where)
    {
        if (empty($this->extraWhere)) {
            $this->extraWhere = $where;
        } else {
            $this->extraWhere = sprintf("( %s ) AND (%s)", $this->extraWhere, $where);
        }
    }

    public function getExtraBind()
    {
        return $this->extraBind;
    }

    public function addExtraBind($bind)
    {
        if (empty($this->extraBind)) {
            $this->extraBind = $bind;
        } else {
            foreach ($bind as $val) {
                $this->extraBind[] = $val;
            }
        }
    }

    public function getTableColumnId($tableName)
    {
        $logTable = $this->logTablesProvider->getLogTable($tableName);

        $primaryKey = $logTable->getPrimaryKey();

        if (count($primaryKey) === 1) {
            return '`' . $tableName . '`.`' . array_shift($primaryKey) . '`';
        }

        $glue = "`, '_', `" . $tableName . '`.`';
        return sprintf("CONCAT(`%s`.`%s`)", $tableName, implode($glue, $primaryKey));
    }

    public function isTableJoinable($tableName)
    {
        $logTable = $this->logTablesProvider->getLogTable($tableName);
        if ($logTable && ($logTable->getColumnToJoinOnIdAction() || $logTable->getColumnToJoinOnIdVisit())) {
            if ($logTable->getPrimaryKey()) {
                // without primary key we would not group the data correctly
                return true;
            }
        }

        return false;
    }

    public function getSelect()
    {
        return implode(', ', $this->selects);
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function setFrom($from)
    {
        $this->from = $from;
    }

    public function getGroupBy()
    {
        if (empty($this->groupBy)) {
            return false;
        }

        return implode(', ', $this->groupBy);
    }

    public function getWhere()
    {
        $where = implode(' AND ', $this->where);

        if (!empty($this->extraWhere) && !empty($where)) {
            $where = sprintf("( %s ) AND (%s)", $where, $this->extraWhere);
        } elseif (!empty($this->extraWhere)){
            $where = $this->extraWhere;
        }

        return $where;
    }

    /**
     * @return false|string
     */
    public function getSortBy()
    {
        return $this->sortBy;
    }

}
