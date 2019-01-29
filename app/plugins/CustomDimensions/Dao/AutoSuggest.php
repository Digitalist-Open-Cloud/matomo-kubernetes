<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\CustomDimensions\Dao;

use Piwik\Common;
use Piwik\Date;
use Piwik\Db;

class AutoSuggest
{

    /**
     * @param array $dimension
     * @param int $idSite
     * @param int $maxValuesToReturn
     * @return array
     */
    public function getMostUsedActionDimensionValues($dimension, $idSite, $maxValuesToReturn)
    {
        $maxValuesToReturn = (int) $maxValuesToReturn;
        $idSite = (int) $idSite;
        $startDate = Date::now()->subDay(60)->toString();
        $name = LogTable::buildCustomDimensionColumnName($dimension);

        $table = Common::prefixTable('log_link_visit_action');
        $query = "SELECT $name, count($name) as countName FROM $table
                  WHERE idsite = ? and server_time > $startDate and $name is not null
                  GROUP by $name
                  ORDER BY countName DESC, $name ASC LIMIT $maxValuesToReturn";
        $rows = Db::get()->fetchAll($query, array($idSite));

        $values = array();
        foreach ($rows as $row) {
            $values[] = $row[$name];
        }

        return $values;
    }

}