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

use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\Graph;
use Piwik\Plugins\MediaAnalytics\Columns\Hour;

class GetVideoHours extends Base
{
    protected function init()
    {
        parent::init();

        $this->setDefaultMetrics();
        
        $this->name = Piwik::translate('MediaAnalytics_VideoHours');
        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationVideoHours');
        $this->dimension = new Hour();

        // This defines in which order your report appears in the mobile app, in the menu and in the list of widgets
        $this->order = 7;
        $this->constantRowsCount = true;

        $this->subcategoryId  = 'MediaAnalytics_TypeVideo';
    }

    public function configureView(ViewDataTable $view)
    {
        $this->configureTableReport($view);
        $view->config->addTranslations(array('label' => 'Hour'));
        $view->requestConfig->filter_limit = 24;
        $view->config->show_search = false;
        $view->config->show_limit_control = false;
        $view->config->show_offset_information = false;
        $view->config->show_exclude_low_population = false;

        if ($view->isViewDataTableId(Graph::ID)) {
            $view->config->max_graph_elements = false;
        }
    }

    public function getRelatedReports()
    {
        return array(); // eg return array(new XyzReport());
    }

}
