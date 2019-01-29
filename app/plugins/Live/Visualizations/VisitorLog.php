<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Live\Visualizations;

use Piwik\Common;
use Piwik\Config;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugin\Visualization;
use Piwik\Plugins\PrivacyManager\PrivacyManager;
use Piwik\View;

/**
 * A special DataTable visualization for the Live.getLastVisitsDetails API method.
 *
 * @property VisitorLog\Config $config
 */
class VisitorLog extends Visualization
{
    const ID = 'VisitorLog';
    const TEMPLATE_FILE = "@Live/_dataTableViz_visitorLog.twig";
    const FOOTER_ICON_TITLE = '';
    const FOOTER_ICON = '';

    public static function getDefaultConfig()
    {
        return new VisitorLog\Config();
    }

    public function beforeLoadDataTable()
    {
        $this->requestConfig->addPropertiesThatShouldBeAvailableClientSide(array(
            'filter_limit',
            'filter_offset',
            'filter_sort_column',
            'filter_sort_order',
        ));

        if (!is_numeric($this->requestConfig->filter_limit)
            || $this->requestConfig->filter_limit == -1 // 'all' is not supported for this visualization
        ) {
            $defaultLimit = Config::getInstance()->General['datatable_default_limit'];
            $this->requestConfig->filter_limit = $defaultLimit;
        }

        $this->requestConfig->disable_generic_filters = true;
        $this->requestConfig->filter_sort_column      = false;

        $view = $this;
        $this->config->filters[] = function (DataTable $table) use ($view) {
            if (Plugin\Manager::getInstance()->isPluginActivated('PrivacyManager') && PrivacyManager::haveLogsBeenPurged($table)) {
                $settings = PrivacyManager::getPurgeDataSettings();
                if (!empty($settings['delete_logs_older_than'])) {
                    $numDaysDelete = $settings['delete_logs_older_than'];
                    $view->config->no_data_message = Piwik::translate('CoreHome_ThereIsNoDataForThisReport') .  ' ' . Piwik::translate('Live_VisitorLogNoDataMessagePurged', $numDaysDelete);
                }
            }
        };
    }

    public function afterGenericFiltersAreAppliedToLoadedDataTable()
    {
        $this->requestConfig->filter_sort_column = false;
    }

    /**
     * Configure visualization.
     */
    public function beforeRender()
    {
        $this->config->show_as_content_block = false;
        $this->config->title = Piwik::translate('Live_VisitorLog');
        $this->config->disable_row_actions = true;
        $this->config->datatable_js_type = 'VisitorLog';
        $this->config->enable_sort       = false;
        $this->config->show_search       = false;
        $this->config->show_exclude_low_population = false;
        $this->config->show_offset_information     = false;
        $this->config->show_all_views_icons        = false;
        $this->config->show_table_all_columns      = false;
        $this->config->show_export_as_rss_feed     = false;
        $this->config->disable_all_rows_filter_limit = true;

        $this->config->documentation = Piwik::translate('Live_VisitorLogDocumentation', array('<br />', '<br />'));

        if (!is_array($this->config->custom_parameters)) {
            $this->config->custom_parameters = array();
        }

        // set a very high row count so that the next link in the footer of the data table is always shown
        $this->config->custom_parameters['totalRows'] = 10000000;
        $this->config->custom_parameters['smallWidth'] = (1 == Common::getRequestVar('small', 0, 'int'));
        $this->config->custom_parameters['hideProfileLink'] = (1 == Common::getRequestVar('hideProfileLink', 0, 'int'));
        $this->config->custom_parameters['pageUrlNotDefined'] = Piwik::translate('General_NotDefined', Piwik::translate('Actions_ColumnPageURL'));

        $this->config->footer_icons = array(
            array(
                'class'   => 'tableAllColumnsSwitch',
                'buttons' => array(
                    array(
                        'id'    => static::ID,
                        'title' => Piwik::translate('Live_LinkVisitorLog'),
                        'icon'  => 'plugins/Morpheus/images/table.png'
                    )
                )
            )
        );
    }

    public static function canDisplayViewDataTable(ViewDataTable $view)
    {
        return ($view->requestConfig->getApiModuleToRequest() === 'Live');
    }
}
