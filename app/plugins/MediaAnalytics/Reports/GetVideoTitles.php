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

use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\ReportsProvider;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MediaAnalytics\Archiver;
use Piwik\Plugins\MediaAnalytics\Columns\MediaTitle;

class GetVideoTitles extends Base
{
    protected function init()
    {
        parent::init();

        $this->setDefaultMetrics();
        
        $this->name = Piwik::translate('MediaAnalytics_VideoTitles');
        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationVideoTitles');
        $this->dimension = new MediaTitle();

        // This defines in which order your report appears in the mobile app, in the menu and in the list of widgets
        $this->order = 1;
        $this->subcategoryId  = 'MediaAnalytics_TypeVideo';
    }

    public function configureView(ViewDataTable $view)
    {
        $this->setExpandableTable($view);
        $this->configureTableReport($view);
        $view->config->addTranslations(array('label' => Piwik::translate('MediaAnalytics_DimensionTitle')));

        $view->config->filters[] = function (DataTable $table) use ($view) {
            $unknown = Piwik::translate('General_Unknown');
            $a = $table->getRowFromLabel($unknown);
            $b = $table->getRowFromLabel(Archiver::LABEL_NOT_DEFINED);
            if (!empty($a) || !empty($b)) {
                $view->config->show_footer_message = Piwik::translate('MediaAnalytics_UnknownMediaTitleExplanation', array($unknown, '<a target="_blank" rel="noreferrer" href="https://developer.matomo.org/guides/media-analytics/options">', '</a>'));
            }
        };
    }

    public function getRelatedReports()
    {
        return array(
            ReportsProvider::factory('MediaAnalytics', 'GetVideoResources'),
            ReportsProvider::factory('MediaAnalytics', 'GetGroupedVideoResources'),
        );
    }

}
