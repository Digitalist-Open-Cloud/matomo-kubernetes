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
use Piwik\Plugins\MediaAnalytics\Columns\PlayerName;

class GetPlayers extends Base
{
    protected function init()
    {
        parent::init();

        $this->setDefaultMetrics();
        $this->name = Piwik::translate('MediaAnalytics_MediaPlayers');
        $this->dimension = new PlayerName();
        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationMediaPlayers');

        $this->order = 36;

        $this->subcategoryId = 'MediaAnalytics_MediaPlayers';
    }

    public function configureView(ViewDataTable $view)
    {
        $this->configureTableReport($view);
        $view->config->addTranslations(array('label' => $this->dimension->getName()));
        $view->config->show_pagination_control = false;
        $view->config->show_flatten_table = false;
        $view->config->show_offset_information = false;
        $view->config->show_limit_control = false;
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_footer_message = Piwik::translate('MediaAnalytics_MediaPlayersFooterMessage', array('<a rel="noreferrer" target="_blank" href="http://developer.matomo.org/guides/media-analytics/custom-player">', '</a>'));
    }

}
