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
use Piwik\Plugins\MediaAnalytics\Columns\Resolution;

class GetVideoResolutions extends Base
{
    protected function init()
    {
        parent::init();

        $this->setDefaultMetrics();
        
        $this->name = Piwik::translate('MediaAnalytics_VideoResolutions');
        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationVideoResolutions');
        $this->dimension = new Resolution();

        // This defines in which order your report appears in the mobile app, in the menu and in the list of widgets
        $this->order = 9;

        $this->subcategoryId  = 'MediaAnalytics_TypeVideo';
    }

    public function configureView(ViewDataTable $view)
    {
        $this->configureTableReport($view);
        $view->config->addTranslations(array('label' => 'Resolution'));
        $view->config->show_flatten_table = false;
    }

    public function getRelatedReports()
    {
        return array(); // eg return array(new XyzReport());
    }

}
