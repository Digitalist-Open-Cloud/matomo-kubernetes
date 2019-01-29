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
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Columns\Keyword;

class GetKeywordsGoogleVideo extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension     = new Keyword();
        $this->name          = Piwik::translate('SearchEngineKeywordsPerformance_VideoKeywords');
        $this->documentation = Piwik::translate('SearchEngineKeywordsPerformance_VideoKeywordsDocumentation');
        $this->order         = 20;
    }

    public function isEnabled()
    {
        return parent::isGoogleEnabledForType('video');
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $this->configureViewNoDataMessageGoogle($view, 'video');
        $this->formatCtrAndPositionColumns($view);

        $view->requestConfig->filter_limit = 5;
    }
}
