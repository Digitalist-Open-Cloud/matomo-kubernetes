<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Referrers\Columns;

use Piwik\Common;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;
use Piwik\Tracker\Action;

class ReferrerName extends Base
{
    protected $columnName = 'referer_name';
    protected $columnType = 'VARCHAR(70) NULL';
    protected $type = self::TYPE_TEXT;

    protected $nameSingular = 'Referrers_ReferrerName';
    protected $namePlural = 'Referrers_ReferrerNames';
    protected $segmentName = 'referrerName';
    protected $acceptValues = 'twitter.com, www.facebook.com, Bing, Google, Yahoo, CampaignName';
    protected $category = 'Referrers_Referrers';

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        $information = $this->getReferrerInformationFromRequest($request, $visitor);

        if (!empty($information['referer_name'])) {
            return Common::mb_substr($information['referer_name'], 0, 70);
        }
        return $information['referer_name'];
    }

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onAnyGoalConversion(Request $request, Visitor $visitor, $action)
    {
        return $this->getValueForRecordGoal($request, $visitor);
    }
}
