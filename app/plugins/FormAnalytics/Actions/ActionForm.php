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

namespace Piwik\Plugins\FormAnalytics\Actions;

use Piwik\Common;
use Piwik\Plugins\FormAnalytics\Tracker\RequestProcessor;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;

class ActionForm extends Action
{
    const TYPE_FORM = 95;

    public function __construct(Request $request)
    {
        parent::__construct(static::TYPE_FORM, $request);

        $url = $request->getParam('url');

        $this->setActionUrl($url);
    }

    public static function shouldHandle(Request $request)
    {
        $params = $request->getParams();
        // defines whether it was sent along a pageview request or whether it was sent standalone
        $wasSentWithPageView = Common::getRequestVar(RequestProcessor::PARAM_FORM_WITH_PAGEVIEW_REQUEST, 0, 'int', $params);

        if (!empty($wasSentWithPageView)) {
            return false;
        }

        $idView = Common::getRequestVar(RequestProcessor::PARAM_FORM_ID_VIEW, '', 'string', $params);

        return !empty($idView);
    }

    protected function getActionsToLookup()
    {
        return array(
            'idaction_url' => $this->getUrlAndType()
        );
    }

    // Do not track this Event URL as Entry/Exit Page URL (leave the existing entry/exit)
    public function getIdActionUrlForEntryAndExitIds()
    {
        return false;
    }

    // Do not track this Event Name as Entry/Exit Page Title (leave the existing entry/exit)
    public function getIdActionNameForEntryAndExitIds()
    {
        return false;
    }
}
