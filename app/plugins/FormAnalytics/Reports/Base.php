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
namespace Piwik\Plugins\FormAnalytics\Reports;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

abstract class Base extends Report
{
    private static $cachedForms = array();

    protected function init()
    {
        $this->categoryId = 'FormAnalytics_Forms';
    }

    protected function removeMetricIfSet($metric)
    {
        $index = array_search($metric, $this->metrics);
        if ($index !== false) {
            array_splice($this->metrics, $index, 1);
        }
    }

    protected function getIdSiteFromInfos($infos)
    {
        $idSite = $infos['idSite'];

        if (empty($idSite)) {
            return null;
        }

        return $idSite;
    }

    protected function getCachedFormsForSite($idSite)
    {
        if (empty($idSite)) {
            return array();
        }
        // this method will be called like 11 times or so (for each report) when loading Matomo reporting page
        // or when loading widgets etc
        if (!isset(self::$cachedForms[$idSite])) {
            $model =  StaticContainer::get('Piwik\Plugins\FormAnalytics\Model\FormsModel');
            self::$cachedForms[$idSite] = $model->getFormsByStatuses($idSite, FormsModel::STATUS_RUNNING);
        }
        return self::$cachedForms[$idSite];
    }

    public function addDimensionWidgetsForEachForm(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite = Common::getRequestVar('idSite', $default = 0, 'int');
        $forms = $this->getCachedFormsForSite($idSite);

        $dimensionsViewTitle = Piwik::translate('FormAnalytics_Fields');
        foreach ($forms as $form) {
            $widget = $factory->createWidget();
            $widget->setSubcategoryId($dimensionsViewTitle);
            $widget->setIsNotWidgetizable();
            $widget->setParameters(array('idForm' => $form['idsiteform']));
            $widgetsList->addToContainerWidget('forms_' . $form['idsiteform'], $widget);
        }
    }

    protected function configureReportMetadataForAllForms(&$availableReports, $infos)
    {
        if (!$this->isEnabled()) {
            return;
        }

        $idSite = $this->getIdSiteFromInfos($infos);
        $forms  = $this->getCachedFormsForSite($idSite);

        $name = $this->name;
        $params = $this->parameters;
        $order = $this->order;
        $subcategory = $this->subcategoryId;

        foreach ($forms as $form) {

            $this->subcategoryId = $form['idsiteform'];
            $this->name = Piwik::translate('FormAnalytics_FormX', '"' . Common::sanitizeInputValue($form['name']) . '"') . ' - ' . $name;
            $this->parameters = array('idForm' => $form['idsiteform']);
            $this->order      = $order;

            $availableReports[] = $this->buildReportMetadata();
        }

        $this->subcategoryId = $subcategory;
        $this->order = $order;
        $this->name = $name;
        $this->parameters = $params;
    }

    protected function isRequestingRowEvolutionPopover()
    {
        return Common::getRequestVar('action', '', 'string') === 'getRowEvolutionPopover';
    }
}
