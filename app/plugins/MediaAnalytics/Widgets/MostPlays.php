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
namespace Piwik\Plugins\MediaAnalytics\Widgets;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\ViewDataTable\Factory;
use Piwik\Widget\WidgetConfig;

class MostPlays extends BaseLiveWidget
{
    public static function configure(WidgetConfig $config)
    {
        parent::configure($config);
        
        $idSite = self::getIdSite();
        $config->setName('MediaAnalytics_WidgetTitleMostPlaysLast30');
        $config->setParameters(array('lastMinutes' => '30'));
        $config->setOrder(101);
        $config->setSubcategoryId('MediaAnalytics_TypeRealTime');
        if (empty($idSite)) {
            $config->disable();
        } else {
            $config->setIsEnabled(Piwik::isUserHasViewAccess($idSite));
        }
    }

    /**
     * This method renders the widget. It's on you how to generate the content of the widget.
     * As long as you return a string everything is fine. You can use for instance a "Piwik\View" to render a
     * twig template. In such a case don't forget to create a twig template (eg. myViewTemplate.twig) in the
     * "templates" directory of your plugin.
     *
     * @return string
     */
    public function render($time = 30)
    {
        $lastMinutes = Common::getRequestVar('lastMinutes', $time, 'int');
        $filterLimit = Common::getRequestVar('filter_limit', 5, 'int');

        $view = Factory::build(HtmlTable::ID, 'MediaAnalytics.getCurrentMostPlays', 'MediaAnalytics.mostPlays', $force = true);
        $view->requestConfig->request_parameters_to_modify['filter_limit'] = $filterLimit;
        $view->requestConfig->request_parameters_to_modify['lastMinutes'] = $lastMinutes;
        $view->config->addTranslation('label', $this->translator->translate('MediaAnalytics_Media'));
        $view->config->addTranslation('value', $this->translator->translate('MediaAnalytics_ColumnPlays'));
        $view->config->custom_parameters['lastMinutes'] = $lastMinutes;
        $view->config->custom_parameters['updateInterval'] = $this->getLiveRefreshInterval();
        $view->config->title = 'MediaAnalytics_WidgetTitleMostPlaysLast' . (int) $lastMinutes;

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_row_evolution = true;
        }

        $view->requestConfig->filter_sort_column = 'value';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->config->columns_to_display = array('label', 'value');
        $view->config->filters[] = function () use ($view) {
            $view->config->columns_to_display = array('label', 'value');
        };
        $view->config->datatable_js_type = 'LiveMediaDataTable';
        $view->config->show_tag_cloud = false;
        $view->config->show_insights = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_search = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->enable_sort = false;

        return $view->render();
    }

}