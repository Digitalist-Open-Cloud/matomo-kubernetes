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

namespace Piwik\Plugins\UsersFlow;

use Piwik\Common;
use Piwik\Piwik;

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        parent::__construct();
    }

    public function getUsersFlow()
    {
        $this->checkSitePermission();

        $login  = Piwik::getCurrentUserLogin();
        $params = $this->configuration->getUsersFlowReportParams($login);

        $showTitle = Common::getRequestVar('showtitle', 0, 'int');

        return $this->renderTemplate('getUsersFlow', array(
            'showTitle' => $showTitle,
            'numActionsPerStep' => $params['numActionsPerStep'],
            'levelOfDetail' => $params['levelOfDetail'],
            'userFlowSource' => $params['userFlowSource'],
            'maxLinksPerInteractions' => $this->configuration->getMaxLinksPerInteractions()
        ));
    }

}
