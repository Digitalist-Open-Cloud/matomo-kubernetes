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

namespace Piwik\Plugins\Funnels\Reports;

use Piwik\Piwik;
use Piwik\Plugins\Funnels\Columns\FunnelExit;

/**
 * This class defines a new report.
 *
 * See {@link http://developer.matomo.org/api-reference/Piwik/Plugin/Report} for more information.
 */
class GetFunnelExits extends GetFunnelEntries
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('Funnels_Exits');
        $this->dimension = new FunnelExit();
        $this->documentation = '';
        $this->order = 101;
    }

}
