<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitsSummary\Reports;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Metrics\Formatter;
use Piwik\NumberFormatter;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreHome\Columns\Metrics\ActionsPerVisit;
use Piwik\Plugins\CoreHome\Columns\Metrics\AverageTimeOnSite;
use Piwik\Plugins\CoreHome\Columns\Metrics\BounceRate;
use Piwik\Plugins\CoreHome\Columns\UserId;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Report\ReportWidgetFactory;
use Piwik\SettingsPiwik;
use Piwik\Site;
use Piwik\Widget\WidgetsList;

class Get extends \Piwik\Plugin\Report
{
    private $usersColumn = 'nb_users';

    protected function init()
    {
        parent::init();
        $this->categoryId    = 'General_Visitors';
        $this->name          = Piwik::translate('VisitsSummary_VisitsSummary');
        $this->documentation = ''; // TODO
        $this->processedMetrics = array(
            new BounceRate(),
            new ActionsPerVisit(),
            new AverageTimeOnSite()
        );
        $this->metrics = array(
            'nb_uniq_visitors',
            'nb_visits',
            $this->usersColumn,
            'nb_actions',
            'max_actions'
        );
        $this->subcategoryId = 'General_Overview';
        // Used to process metrics, not displayed/used directly
//								'sum_visit_length',
//								'nb_visits_converted',
        $this->order = 1;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setName('VisitsSummary_WidgetLastVisits')
                ->forceViewDataTable(Evolution::ID)
                ->setAction('getEvolutionGraph')
                ->setOrder(5)
        );

        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setName('VisitsSummary_WidgetVisits')
                ->forceViewDataTable(Sparklines::ID)
                ->setOrder(10)
        );
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view->isViewDataTableId(Sparklines::ID)) {
            /** @var Sparklines $view */
            $view->requestConfig->apiMethodToRequestDataTable = 'API.get';
            $this->addSparklineColumns($view);
            $view->config->addTranslations($this->getSparklineTranslations());

            $numberFormatter = NumberFormatter::getInstance();
            $view->config->filters[] = function (DataTable $table) use ($view, $numberFormatter) {
                $firstRow = $table->getFirstRow();

                if (($firstRow->getColumn('nb_pageviews')
                    + $firstRow->getColumn('nb_downloads')
                    + $firstRow->getColumn('nb_outlinks')) == 0
                    && $firstRow->getColumn('nb_actions') > 0) {
                    $view->config->removeSparklineMetric(array('nb_downloads', 'nb_uniq_downloads'));
                    $view->config->removeSparklineMetric(array('nb_outlinks', 'nb_uniq_outlinks'));
                    $view->config->removeSparklineMetric(array('nb_pageviews', 'nb_uniq_pageviews'));
                    $view->config->removeSparklineMetric(array('nb_searches', 'nb_keywords'));
                } else {
                    $view->config->removeSparklineMetric(array('nb_actions'));
                }

                $nbUsers = $firstRow->getColumn('nb_users');
                if (!is_numeric($nbUsers) || 0 >= $nbUsers) {
                    $view->config->replaceSparklineMetric(array('nb_users'), '');
                }

                $avgGenerationTime = $firstRow->getColumn('avg_time_generation');
                if (false === $avgGenerationTime) {
                    // fix avgGenerationTime is not formatted if value is false
                    /** @var Formatter $formatter */
                    $formatter = StaticContainer::get('Piwik\Metrics\Formatter');
                    $avgGenerationTime = $formatter->getPrettyTimeFromSeconds($avgGenerationTime, true);
                    $firstRow->setColumn('avg_time_generation', $avgGenerationTime);
                }

                $numberMetrics = array('nb_visits', 'nb_uniq_visitors', 'nb_users', 'nb_actions',
                                       'nb_pageviews', 'nb_uniq_pageviews', 'nb_searches', 'nb_keywords', 'nb_downloads',
                                       'nb_uniq_downloads', 'nb_outlinks', 'nb_uniq_outlinks', 'max_actions');
                foreach ($numberMetrics as $metric) {
                    $value = $firstRow->getColumn($metric);
                    if (false !== $value) {
                        $firstRow->setColumn($metric, $numberFormatter->formatNumber($value));
                    }
                }
                $value = $firstRow->getColumn('bounce_rate');
                if (false !== $value) {
                    $firstRow->setColumn('bounce_rate', $numberFormatter->formatPercent($value, $precision = 1));
                }
                $value = $firstRow->getColumn('nb_actions_per_visit');
                if (false !== $value) {
                    $firstRow->setColumn('nb_actions_per_visit', $numberFormatter->formatNumber($value, $maxFraction = 1));
                }
            };
        }
    }

    private function getSparklineTranslations()
    {
        $translations = array(
            'nb_actions' => 'NbActionsDescription',
            'nb_visits' => 'NbVisitsDescription',
            'nb_users' => 'NbUsersDescription',
            'nb_uniq_visitors' => 'NbUniqueVisitors',
            'avg_time_generation' => 'AverageGenerationTime',
            'avg_time_on_site' => 'AverageVisitDuration',
            'max_actions' => 'MaxNbActions',
            'nb_actions_per_visit' => 'NbActionsPerVisit',
            'nb_downloads' => 'NbDownloadsDescription',
            'nb_uniq_downloads' => 'NbUniqueDownloadsDescription',
            'nb_outlinks' => 'NbOutlinksDescription',
            'nb_uniq_outlinks' => 'NbUniqueOutlinksDescription',
            'nb_keywords' => 'NbKeywordsDescription',
            'nb_searches' => 'NbSearchesDescription',
            'nb_pageviews' => 'NbPageviewsDescription',
            'nb_uniq_pageviews' => 'NbUniquePageviewsDescription',
            'bounce_rate' => 'NbVisitsBounced',
        );

        foreach ($translations as $metric => $key) {
            $translations[$metric] = Piwik::translate('VisitsSummary_' . $key);
        }

        return $translations;
    }

    private function addSparklineColumns(Sparklines $view)
    {
        $currentPeriod = Common::getRequestVar('period');
        $currentIdSite = Common::getRequestVar('idSite');
        $currentDate   = Common::getRequestVar('date');
        $displayUniqueVisitors = SettingsPiwik::isUniqueVisitorsEnabled($currentPeriod);

        $isActionPluginEnabled = Common::isActionsPluginEnabled();

        $view->config->addSparklineMetric($displayUniqueVisitors ? array('nb_visits', 'nb_uniq_visitors') : array('nb_visits'), 5);

        if ($isActionPluginEnabled) {
            $view->config->addSparklineMetric(array('nb_actions'), 10); // either actions or pageviews will be displayed
            $view->config->addSparklineMetric(array('nb_pageviews', 'nb_uniq_pageviews'), 20);
        } else {
            // make sure to still create a div on the right side for this, just leave it empty
            $view->config->addPlaceholder(10);
        }

        $userId = new UserId();
        if ($userId->isUsedInAtLeastOneSite(array($currentIdSite), $currentPeriod, $currentDate)) {
            $view->config->addSparklineMetric(array('nb_users'), 30);
            $view->config->addPlaceholder(31);
        }

        $view->config->addSparklineMetric(array('avg_time_on_site'), 40);

        $idSite = Common::getRequestVar('idSite');
        if ($isActionPluginEnabled && Site::isSiteSearchEnabledFor($idSite)) {
            $view->config->addSparklineMetric(array('nb_searches', 'nb_keywords'), 50);
        } else {
            // make sure to still create a div on the right side for this, just leave it empty
            $view->config->addPlaceholder(50);
        }

        $view->config->addSparklineMetric(array('bounce_rate'), 60);

        if ($isActionPluginEnabled) {
            $view->config->addSparklineMetric(array('nb_downloads', 'nb_uniq_downloads'), 70);
            $view->config->addSparklineMetric(array('nb_actions_per_visit'), 71);
            $view->config->addSparklineMetric(array('nb_outlinks', 'nb_uniq_outlinks'), 72);
            $view->config->addSparklineMetric(array('avg_time_generation'), 73);
            $view->config->addSparklineMetric(array('max_actions'), 74);
        }
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();

        $metrics['max_actions'] = Piwik::translate('General_ColumnMaxActions');

        return $metrics;
    }

    public function getProcessedMetrics()
    {
        $metrics = parent::getProcessedMetrics();

        $metrics['avg_time_on_site'] = Piwik::translate('General_VisitDuration');

        return $metrics;
    }

    public function removeUsersFromProcessedReport(&$response)
    {
        if (!empty($response['metadata']['metrics'][$this->usersColumn])) {
            unset($response['metadata']['metrics'][$this->usersColumn]);
        }

        if (!empty($response['metadata']['metricsDocumentation'][$this->usersColumn])) {
            unset($response['metadata']['metricsDocumentation'][$this->usersColumn]);
        }

        if (!empty($response['columns'][$this->usersColumn])) {
            unset($response['columns'][$this->usersColumn]);
        }

        if (!empty($response['reportData'])) {
            $dataTable = $response['reportData'];
            $dataTable->deleteColumn($this->usersColumn, true);
        }
    }

}