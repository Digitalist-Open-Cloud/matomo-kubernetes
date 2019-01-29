<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreHome\Columns;

use Piwik\Cache;
use Piwik\DataTable;
use Piwik\DataTable\Map;
use Piwik\Metrics;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\VisitsSummary\API as VisitsSummaryApi;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;
use Piwik\Tracker\Action;

/**
 * UserId dimension.
 */
class UserId extends VisitDimension
{
    /**
     * @var string
     */
    protected $columnName = 'user_id';
    protected $type = self::TYPE_TEXT;
    protected $allowAnonymous = false;
    protected $segmentName = 'userId';
    protected $nameSingular = 'General_UserId';
    protected $namePlural = 'General_UserIds';
    protected $acceptValues = 'any non empty unique string identifying the user (such as an email address or a username).';

    /**
     * @var string
     */
    protected $columnType = 'VARCHAR(200) NULL';

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed|false
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return $request->getForcedUserId();
    }

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     *
     * @return mixed|false
     */
    public function onExistingVisit(Request $request, Visitor $visitor, $action)
    {
        return $request->getForcedUserId();
    }

    public function isUsedInAtLeastOneSite($idSites, $period, $date)
    {
        if ($period === 'day' || $period === 'week') {
            $period = 'month';
        }

        if ($period === 'range') {
            $period = 'day';
        }

        if (!empty($idSites)) {
            foreach ($idSites as $idSite) {
                if ($this->isUsedInSiteCached($idSite, $period, $date)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isUsedInSiteCached($idSite, $period, $date)
    {
        $cache = Cache::getTransientCache();
        $key   = sprintf('%d.%s.%s', $idSite, $period, $date);

        if (!$cache->contains($key)) {
            $result = $this->isUsedInSite($idSite, $period, $date);
            $cache->save($key, $result);
        }

        return $cache->fetch($key);
    }

    private function isUsedInSite($idSite, $period, $date)
    {
        $result = VisitsSummaryApi::getInstance()->get($idSite, $period, $date, false, 'nb_users');

        return $this->hasDataTableUsers($result);
    }

    public function hasDataTableUsers(DataTable\DataTableInterface $result)
    {
        if ($result instanceof Map) {
            foreach ($result->getDataTables() as $table) {
                if ($this->hasDataTableUsers($table)) {
                    return true;
                }
            }
        }

        if (!$result->getRowsCount()) {
            return false;
        }

        $firstRow = $result->getFirstRow();
        if ($firstRow instanceof DataTable\Row && $firstRow->hasColumn(Metrics::INDEX_NB_USERS)) {
            $metric = Metrics::INDEX_NB_USERS;
        } else {
            $metric = 'nb_users';
        }

        $numUsers = $result->getColumn($metric);
        $numUsers = array_sum($numUsers);

        return !empty($numUsers);
    }

}