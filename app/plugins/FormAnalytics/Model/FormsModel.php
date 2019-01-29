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
namespace Piwik\Plugins\FormAnalytics\Model;

use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\FormAnalytics\Input\ConversionRules;
use Piwik\Plugins\FormAnalytics\Input\MatchPageRules;
use Piwik\Plugins\FormAnalytics\Input\Name;
use Piwik\Plugins\FormAnalytics\SystemSettings;
use Piwik\Tracker;
use Piwik\Plugins\FormAnalytics\Dao\SiteForm;
use Exception;
use Piwik\Plugins\FormAnalytics\Input\MatchFormRules;
use Piwik\Plugins\FormAnalytics\Input\Description;

class FormsModel
{
    const STATUS_RUNNING = 'running';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_DELETED = 'deleted';

    /**
     * @var SiteForm
     */
    private $siteFormDao;

    /**
     * @var SystemSettings
     */
    private $settings;

    public function __construct(SiteForm $siteForm, SystemSettings $settings)
    {
        $this->siteFormDao = $siteForm;
        $this->settings = $settings;
    }

    public function createForm($idSite, $name, $description, $matchFormRules, $matchPageRules, $conversionRules, $createdDate, $autoCreated)
    {
        $this->validateFormValues($name, $description, $matchFormRules, $matchPageRules, $conversionRules);

        $autoCreated = (bool) $autoCreated;
        $status = self::STATUS_RUNNING;

        $idForm = $this->siteFormDao->createForm($idSite, $name, $description, $status, $matchFormRules, $matchPageRules, $conversionRules, $createdDate, $autoCreated);

        $this->clearTrackerCache($idSite);

        return $idForm;
    }

    public function updateForm($idSite, $idForm, $name, $description, $matchFormRules, $matchPageRules, $conversionRules, $updatedDate)
    {
        $this->validateFormValues($name, $description, $matchFormRules, $matchPageRules, $conversionRules);

        $columns = array(
            'name' => $name,
            'description' => $description,
            'match_form_rules' => $matchFormRules,
            'match_page_rules' => $matchPageRules,
            'conversion_rules' => $conversionRules,
            'updated_date' => $updatedDate
        );
        $this->updateFormColumns($idSite, $idForm, $columns);
    }

    /**
     * @param $idForm
     * @param $idSite
     * @return array|false
     * @throws \Exception
     */
    public function getForm($idSite, $idForm)
    {
        return $this->siteFormDao->getForm($idSite, $idForm);
    }

    /**
     * @return array
     */
    public function getAllFormsForSite($idSite)
    {
        $validStatuses = array();
        foreach ($this->getValidStatuses() as $status) {
            $validStatuses[] = $status['value'];
        }

        return $this->siteFormDao->getFormsByStatuses($idSite, $validStatuses);
    }

    /**
     * @param int $idSite
     * @param string|array $statuses
     * @return array
     */
    public function getFormsByStatuses($idSite, $statuses)
    {
        if (is_string($statuses)) {
            $statuses = array($statuses);
        }

        return $this->siteFormDao->getFormsByStatuses($idSite, $statuses);
    }

    /**
     * We also count deleted and archived forms. Otherwise you delete a form and it would re-create a form again under
     * circumstances.
     *
     * @param $idSite
     * @return int
     */
    public function getNumFormsAutoCreated($idSite)
    {
        $count = 0;
        $forms = $this->siteFormDao->getAllFormsForSite($idSite);

        foreach ($forms as $form) {
            if ($form['auto_created']) {
                $count++;
            }
        }

        return $count;
    }

    public function checkFormExists($idSite, $idSiteForm)
    {
        $form = $this->siteFormDao->getForm($idSite, $idSiteForm);

        if (empty($form)) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorFormDoesNotExist'));
        }
    }

    public function getValidStatuses()
    {
        // we do not return status "deleted" here as it would be otherwise shown in the forms UI in the filter
        // it shouldn't be possible to fetch deleted forms
        return array(
            array('value' => self::STATUS_RUNNING, 'name' => Piwik::translate('FormAnalytics_StatusRunning')),
            array('value' => self::STATUS_ARCHIVED, 'name' => Piwik::translate('FormAnalytics_StatusArchived')),
        );
    }

    public function updateFormFields($idSite, $idForm, $fields)
    {
        $columns = array('fields' => $fields);
        $this->updateFormColumns($idSite, $idForm, $columns);
    }

    public function deactivateForm($idSite, $idForm)
    {
        $columns = array('status' => self::STATUS_DELETED);
        $this->updateFormColumns($idSite, $idForm, $columns);
    }

    public function archiveForm($idSite, $idForm)
    {
        $columns = array('status' => self::STATUS_ARCHIVED);
        $this->updateFormColumns($idSite, $idForm, $columns);
    }

    /**
     * @param int $idSite
     */
    public function deactivateFormsForSite($idSite)
    {
        foreach ($this->siteFormDao->getAllFormsForSite($idSite) as $form) {
            $this->deactivateForm($idSite, $form['idsiteform']);
        }
    }

    protected function getCurrentDateTime()
    {
        return Date::now()->getDatetime();
    }

    private function clearTrackerCache($idSite)
    {
        Tracker\Cache::deleteCacheWebsiteAttributes($idSite);
    }

    private function validateFormValues($name, $description, $matchFormRules, $matchPageRules, $conversionRules)
    {
        $name = new Name($name);
        $name->check();

        $description = new Description($description);
        $description->check();

        $formRules = new MatchFormRules($matchFormRules);
        $formRules->check();

        $pageRules = new MatchPageRules($matchPageRules);
        $pageRules->check();

        $conversionRules = new ConversionRules($conversionRules);
        $conversionRules->check();

        if (empty($matchFormRules) && empty($matchPageRules)) {
            throw new Exception(Piwik::translate('FormAnalytics_ErrorFormOrPageRuleRequired'));
        }
    }

    private function updateFormColumns($idSite, $idForm, $columns)
    {
        if (!isset($columns['updated_date'])) {
            $columns['updated_date'] = $this->getCurrentDateTime();
        }
        $this->siteFormDao->updateFormColumns($idSite, $idForm, $columns);
        $this->clearTrackerCache($idSite);
    }

}

