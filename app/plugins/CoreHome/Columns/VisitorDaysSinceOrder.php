<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreHome\Columns;

use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class VisitorDaysSinceOrder extends VisitDimension
{
    protected $columnName = 'visitor_days_since_order';
    protected $columnType = 'SMALLINT(5) UNSIGNED NULL';
    protected $segmentName = 'daysSinceLastEcommerceOrder';
    protected $nameSingular = 'General_DaysSinceLastEcommerceOrder';
    protected $category = 'General_Visitors'; // todo put into ecommerce category?
    protected $type = self::TYPE_NUMBER;

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        $daysSinceLastOrder = $request->getDaysSinceLastOrder();

        if ($daysSinceLastOrder === false) {
            $daysSinceLastOrder = 0;
        }

        return $daysSinceLastOrder;
    }

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onAnyGoalConversion(Request $request, Visitor $visitor, $action)
    {
        return $visitor->getVisitorColumn($this->columnName);
    }
}