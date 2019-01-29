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

namespace Piwik\Plugins\MediaAnalytics\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ReportsProvider;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MediaAnalytics\Columns\MediaResource;

class GetVideoResources extends Base
{
    protected function init()
    {
        parent::init();

        $this->setDefaultMetrics();
        
        $this->name          = Piwik::translate('MediaAnalytics_VideoResources');
        $this->dimension     = new MediaResource();
        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationVideoResources');

        $this->order = 3;
        $this->subcategoryId  = 'MediaAnalytics_TypeVideo';
    }

    public function configureView(ViewDataTable $view)
    {
        $this->setExpandableTable($view);
        $this->configureTableReport($view);
        $view->config->addTranslations(array('label' => Piwik::translate('MediaAnalytics_Resolution')));
    }

    public function getRelatedReports()
    {
        return array(
            ReportsProvider::factory('MediaAnalytics', 'GetGroupedVideoResources'),
            ReportsProvider::factory('MediaAnalytics', 'GetVideoTitles'),
        );
    }
}
