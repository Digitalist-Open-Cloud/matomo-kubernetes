<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreHome\Columns;

use Piwik\Date;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;
use Piwik\Metrics\Formatter;

require_once PIWIK_INCLUDE_PATH . '/plugins/VisitTime/functions.php';

/**
 * This dimension holds the best guess for a visit's end time. It is set the last action
 * time for each visit. `ping=1` requests can be sent to update the dimension value so
 * it can be a more accurate guess of the time the visitor spent on the site.
 *
 * Note: though it is named 'visit last action time' it actually refers to the visit's last action's
 * end time.
 */
class VisitLastActionTime extends VisitDimension
{
    protected $columnName = 'visit_last_action_time';
    protected $type = self::TYPE_DATETIME;
    protected $nameSingular = 'VisitTime_ColumnVisitEndServerHour';
    protected $sqlSegment = 'HOUR(log_visit.visit_last_action_time)';
    protected $segmentName = 'visitServerHour';
    protected $acceptValues = '0, 1, 2, 3, ..., 20, 21, 22, 23';

    public function formatValue($value, $idSite, Formatter $formatter)
    {
        return \Piwik\Plugins\VisitTime\getTimeLabel($value);
    }

    // we do not install or define column definition here as we need to create this column when installing as there is
    // an index on it. Currently we do not define the index here... although we could overwrite the install() method
    // and add column 'visit_last_action_time' and add index. Problem is there is also an index
    // INDEX(idsite, config_id, visit_last_action_time) and we maybe not be sure whether idsite already exists at
    // installing point (we do not know whether idsite column will be added first).

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return Date::getDatetimeFromTimestamp($request->getCurrentTimestamp());
    }

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onExistingVisit(Request $request, Visitor $visitor, $action)
    {
        if ($request->getParam('ping') == 1) {
            return false;
        }
        
        return $this->onNewVisit($request, $visitor, $action);
    }
}