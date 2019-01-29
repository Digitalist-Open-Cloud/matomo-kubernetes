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
namespace Piwik\Plugins\Funnels\Input;

use Exception;
use Piwik\Piwik;
use Piwik\Site;

class Validator
{
    public function checkWritePermission($idSite)
    {
        Piwik::checkUserHasAdminAccess($idSite);
        $this->checkSiteExists($idSite);
    }

    public function checkReportViewPermission($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $this->checkSiteExists($idSite);
    }

    private function checkSiteExists($idSite)
    {
        new Site($idSite);
    }

    public function canViewReport($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        return Piwik::isUserHasViewAccess($idSite);
    }

    public function canWrite($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        return Piwik::isUserHasAdminAccess($idSite);
    }

    public function validateFunnelConfiguration($activated, $steps)
    {
        $isActivated = new IsActivated($activated);
        $isActivated->check();

        if (!empty($activated) && empty($steps)) {
            throw new Exception(Piwik::translate('Funnels_ErrorActivatedFunnelWithNoSteps'));
        }

        $steps = new Steps($steps);
        $steps->check();
    }

}

