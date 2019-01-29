<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitFrequency\Reports;

use Piwik\DataTable;
use Piwik\NumberFormatter;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreHome\Columns\Metrics\ActionsPerVisit;
use Piwik\Plugins\CoreHome\Columns\Metrics\AverageTimeOnSite;
use Piwik\Plugins\CoreHome\Columns\Metrics\BounceRate;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Plugins\VisitFrequency\Columns\Metrics\ReturningMetric;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class Get extends \Piwik\Plugin\Report
{
    protected function init()
    {
        parent::init();
        $this->categoryId      = 'General_Visitors';
        $this->name          = Piwik::translate('VisitFrequency_ColumnReturningVisits');
        $this->documentation = ''; // TODO
        $this->processedMetrics = array(
            new ReturningMetric(new AverageTimeOnSite()),
            new ReturningMetric(new ActionsPerVisit()),
            new ReturningMetric(new BounceRate())
        );
        $this->metrics       = array(
            'nb_visits_returning',
            'nb_actions_returning',
            'nb_uniq_visitors_returning',
            'nb_users_returning',
            'max_actions_returning'
        );
        $this->order = 40;
        $this->subcategoryId = 'VisitorInterest_Engagement';
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setName('VisitFrequency_WidgetGraphReturning')
                ->forceViewDataTable(Evolution::ID)
                ->setAction('getEvolutionGraph')
                ->setOrder(1)
        );

        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->forceViewDataTable(Sparklines::ID)
                ->setName('VisitFrequency_WidgetOverview')
                ->setOrder(2)
        );
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view->isViewDataTableId(Sparklines::ID)) {
            $view->requestConfig->apiMethodToRequestDataTable = 'VisitFrequency.get';
            $this->addSparklineColumns($view);
            $view->config->addTranslations($this->getSparklineTranslations());

            $numberFormatter = NumberFormatter::getInstance();
            $view->config->filters[] = function (DataTable $table) use ($numberFormatter) {
                $firstRow = $table->getFirstRow();
                if ($firstRow) {
                    $value = $firstRow->getColumn('nb_visits_returning');
                    if (false !== $value) {
                        $firstRow->setColumn('nb_visits_returning', $numberFormatter->formatNumber($value));
                    }

                    $value = $firstRow->getColumn('nb_actions_returning');
                    if (false !== $value) {
                        $firstRow->setColumn('nb_actions_returning', $numberFormatter->formatNumber($value));
                    }

                    $value = $firstRow->getColumn('nb_actions_per_visit_returning');
                    if (false !== $value) {
                        $firstRow->setColumn('nb_actions_per_visit_returning', $numberFormatter->formatNumber($value, 1));
                    }

                    $value = $firstRow->getColumn('bounce_rate_returning');
                    if (false !== $value) {
                        $firstRow->setColumn('bounce_rate_returning', $numberFormatter->formatNumber($value, $precision = 1));
                    }
                }
            };
        }
    }

    private function getSparklineTranslations()
    {
        $translations = array(
            'nb_visits_returning' => 'ReturnVisits',
            'nb_actions_returning' => 'ReturnActions',
            'nb_actions_per_visit_returning' => 'ReturnAvgActions',
            'avg_time_on_site_returning' => 'ReturnAverageVisitDuration',
            'bounce_rate_returning' => 'ReturnBounceRate',
        );

        foreach ($translations as $metric => $key) {
            $translations[$metric] = Piwik::translate('VisitFrequency_' . $key);
        }

        return $translations;
    }

    private function addSparklineColumns(Sparklines $view)
    {
        $view->config->addSparklineMetric(array('nb_visits_returning'));
        $view->config->addSparklineMetric(array('avg_time_on_site_returning'));
        $view->config->addSparklineMetric(array('nb_actions_per_visit_returning'));
        $view->config->addSparklineMetric(array('bounce_rate_returning'));
        $view->config->addSparklineMetric(array('nb_actions_returning'));
    }

}
