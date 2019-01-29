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
namespace Piwik\Plugins\FormAnalytics\Tracker;

use Piwik\Date;
use Piwik\Plugins\FormAnalytics\Dao\LogFormField;
use Piwik\Plugins\FormAnalytics\FormAnalytics;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Piwik\Plugins\FormAnalytics\SystemSettings;
use Piwik\Tracker;
use Exception;

class FormManager
{
    /**
     * @var FormsModel
     */
    private $model;

    /**
     * @var SystemSettings
     */
    private $settings;

    public function __construct(FormsModel $model, SystemSettings $systemSettings)
    {
        $this->model = $model;
        $this->settings = $systemSettings;
    }

    public function getCachedRunningForms($idSite)
    {
        $cache = Tracker\Cache::getCacheWebsiteAttributes($idSite);

        if (!empty($cache[FormAnalytics::TRACKER_CACHE_RUNNING_FORMS_KEY])) {
            return $cache[FormAnalytics::TRACKER_CACHE_RUNNING_FORMS_KEY];
        }

        return array();
    }

    public function getNumFormsAutoCreated($idSite)
    {
        $cache = Tracker\Cache::getCacheWebsiteAttributes($idSite);

        if (!empty($cache[FormAnalytics::TRACKER_CACHE_NUM_AUTO_CREATED])) {
            return $cache[FormAnalytics::TRACKER_CACHE_NUM_AUTO_CREATED];
        }

        return 0;
    }

    public function getCachedAllForms($idSite)
    {
        $cache = Tracker\Cache::getCacheWebsiteAttributes($idSite);

        if (!empty($cache[FormAnalytics::TRACKER_CACHE_ALL_FORMS_KEY])) {
            return $cache[FormAnalytics::TRACKER_CACHE_ALL_FORMS_KEY];
        }

        return array();
    }

    public function findForms($idSite, $formName, $formId, $pageUrl)
    {
        $forms = $this->getCachedRunningForms($idSite);

        $matchingForms = array();

        foreach ($forms as $form) {
            if ($this->doesMatchFormRules($form, $formName, $formId) && $this->doesMatchPageRules($form, $pageUrl)) {
                $matchingForms[] = $form;
            }
        }

        return $matchingForms;
    }

    protected function doesMatchPageRules($form, $pageUrl)
    {
        if (empty($form['match_page_rules'])) {
            // no rule / restriction defined means it matches any page
            return true;
        }

        foreach ($form['match_page_rules'] as $pageRule) {
            $pageRule = new RuleMatcher($pageRule);
            if ($pageRule->matches('', '', $pageUrl)) {
                return true;
            }
        }

        return false;
    }

    protected function doesMatchFormRules($form, $formName, $formId)
    {
        if (empty($form['match_form_rules'])) {
            // no rule / restriction defined means it matches any form
            return true;
        }

        foreach ($form['match_form_rules'] as $formRule) {
            $formRule = new RuleMatcher($formRule);
            if ($formRule->matches($formName, $formId, '')) {
                return true;
            }
        }

        return false;
    }

    protected function doesAnyFormMatchFormRules($idSite, $formName, $formId)
    {
        // we need to include running and archived forms. This way, when you archive a form, it won't be re-created
        // automatically. Otherwise you archive a form, then Matomo will detect it again, see it is not running, and
        // create a running form
        $forms = $this->getCachedAllForms($idSite);

        foreach ($forms as $form) {
            if (empty($form['match_form_rules'])) {
                // we only match forms when they have a rule defined, otherwise we would never create any new form
                // automatically under circumstances
                continue;
            }

            foreach ($form['match_form_rules'] as $formRule) {
                $formRule = new RuleMatcher($formRule);

                // we ignore page rules as we only want to create new form if no other form exists that had any
                // restriction on page urls
                if ($formRule->matches($formName, $formId, '')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isConvertedForm($form, $pageUrl)
    {
        if (empty($form['conversion_rules'])) {
            return false;
        }

        foreach ($form['conversion_rules'] as $conversionRule) {
            $rule = new RuleMatcher($conversionRule);

            // we ignore form name and form id because only url rules can be used
            if ($rule->matches('', '', $pageUrl)) {
                return true;
            }
        }

        return false;
    }

    public function autoCreateForm($idSite, $formName, $formId, $pageUrl)
    {
        if (!empty($formName) || !empty($formId)) {
            $setting = $this->settings->autoCreateForm->getValue();

            if ($setting === SystemSettings::FORM_CREATION_DISABLED) {
                return;
            }

            $numAutoCreated = $this->getNumFormsAutoCreated($idSite);

            if ($setting === SystemSettings::FORM_CREATION_UP_TO_10 && $numAutoCreated >= 10) {
                return;
            }

            if ($setting === SystemSettings::FORM_CREATION_UP_TO_50 && $numAutoCreated >= 50) {
                return;
            }

            if (!$this->doesAnyFormMatchFormRules($idSite, $formName, $formId)) {
                // we make sure no other form matches this form name and form id. If other form matches form rules,
                // it means likely a different form is configured for this form name and form id but restricted by "match page rules".
                // In this case we do not auto create the form as we could end up creating thousands of new forms and
                // likely the user had a reason to restrict it by a page URL.

                $name = !empty($formName) ? $formName : $formId;
                $attribute = !empty($formName) ? RuleMatcher::ATTRIBUTE_FORM_NAME : RuleMatcher::ATTRIBUTE_FORM_ID;

                $matchFormRules = array(array(
                    'attribute' => $attribute,
                    'pattern' => RuleMatcher::PATTERN_EQUALS_EXACTLY,
                    'value' => $name
                ));
                $createdDate = Date::now()->getDatetime();
                // we do not translate this text as it is done during tracking
                $description = sprintf('Auto created from page %s', $pageUrl);

                try {
                    $idForm = $this->model->createForm($idSite, $name, $description, $matchFormRules, $matchPageRules = [], $conversionRules = [], $createdDate, $autoCreated = true);
                } catch (Exception $exception) {
                    // might fail because of duplicate name
                    // we have an index on idSite, name to not create 2 or several forms at the same time during tracking
                }

                if (!empty($idForm)) {
                    return $this->model->getForm($idSite, $idForm);
                }
            }
        }
    }

    public function completeFormFieldsIfNeeded($form, $fields)
    {
        if (!empty($form['fields'])) {
            $existingFields = $form['fields'];
        } else {
            $existingFields = array();
        }

        $namToIndexMap = array();
        foreach ($existingFields as $index => $field) {
            $namToIndexMap[$field['name']] = $index;
        }

        $changed = false;

        foreach ($fields as $field) {
            if (empty($field[RequestProcessor::PARAM_FORM_FIELD_NAME])
                || empty($field[RequestProcessor::PARAM_FORM_FIELD_TYPE])) {
                continue;
            }

            $name = substr($field[RequestProcessor::PARAM_FORM_FIELD_NAME], 0, LogFormField::MAX_FIELD_NAME_LENGTH);
            $type = $field[RequestProcessor::PARAM_FORM_FIELD_TYPE];

            if (isset($namToIndexMap[$name])) {
                $index = $namToIndexMap[$name];

                if ($existingFields[$index]['type'] !== $type) {
                    // the type of the form field changes
                    $existingFields[$index]['type'] = $type;
                    $changed = true;
                }
            } else {
                // new field
                $existingFields[] = array('name' => $name, 'type' => $type, 'displayName' => '');
                $changed = true;
            }
        }

        if ($changed) {
            $this->model->updateFormFields($form['idsite'], $form['idsiteform'], $existingFields);
        }
    }
}
