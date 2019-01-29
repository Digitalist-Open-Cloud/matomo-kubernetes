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
namespace Piwik\Plugins\FormAnalytics;

use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\FormAnalytics\Actions\ActionForm;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\View;

class VisitorDetails extends VisitorDetailsAbstract
{
    const FORM_TYPE      = 'form';

    protected $formConversions = [];

    public function extendVisitorDetails(&$visitor)
    {
        if (!array_key_exists($visitor['idVisit'], $this->formConversions)) {
            $this->queryFormInteractionsForVisitIds([$visitor['idVisit']]);
        }

        $visitor['formConversions'] = isset($this->formConversions[$visitor['idVisit']]) ? $this->formConversions[$visitor['idVisit']] : 0;
    }

    public function provideActionsForVisitIds(&$actions, $visitIds)
    {
        $formDetails = $this->queryFormInteractionsForVisitIds($visitIds);

        // use while / array_shift combination instead of foreach to save memory
        while (is_array($formDetails) && count($formDetails)) {
            $action = array_shift($formDetails);
            $idVisit = $action['idvisit'];
            unset($action['idvisit']);
            $actions[$idVisit][] = $action;
        }
    }

    public function renderAction($action, $previousAction, $visitorDetails)
    {
        if ($action['type'] != self::FORM_TYPE) {
            return;
        }

        $view = new View('@FormAnalytics/_actionForm.twig');
        $view->action = $action;
        $view->previousAction = $previousAction;
        $view->visitInfo = $visitorDetails;
        return $view->render();
    }

    public function renderActionTooltip($action, $visitInfo)
    {
        if ($action['type'] != self::FORM_TYPE) {
            return [];
        }

        $view         = new View('@FormAnalytics/_actionTooltip');
        $view->action = $action;
        return [[ 100, $view->render() ]];
    }

    public function renderIcons($visitorDetails)
    {
        if (empty($visitorDetails['formConversions'])) {
            return '';
        }

        $view         = new View('@FormAnalytics/_visitorLogIcons');
        $view->formConversions = $visitorDetails['formConversions'];
        return $view->render();
    }

    public function initProfile($visits, &$profile)
    {
        $profile['uniqueFormConversions']   = 0;
        $profile['totalConversionsByForm'] = array();
    }

    public function handleProfileAction($action, &$profile)
    {
        if ($action['type'] != self::FORM_TYPE || empty($action['converted'])) {
            return;
        }

        $idForm    = $action['formId'];

        if (!isset($profile['totalConversionsByForm'][$idForm])) {
            $profile['totalConversionsByForm'][$idForm] = 0;
        }
        ++$profile['totalConversionsByForm'][$idForm];
    }

    public function finalizeProfile($visits, &$profile)
    {
        $profile['uniqueFormConversions'] = count($profile['totalConversionsByForm']);
    }

    /**
     * @param $visitIds
     * @return array
     * @throws \Exception
     */
    protected function queryFormInteractionsForVisitIds($visitIds)
    {
        if (empty($visitIds)) {
            return;
        }
	    
        $visitIds = array_map('intval', $visitIds);
        $limit = (int)Config::getInstance()->General['visitor_log_maximum_actions_per_visit'];
        $sql = sprintf("SELECT
                        log_form.idvisit,
                        log_form.num_submissions,
                        log_form.time_spent AS time_spent_form,
                        log_form.time_hesitation AS time_hesitation_form,
                        site_form.name AS form_name,
                        site_form.idsiteform AS form_id,
                        log_form_page.idlogform,
                        log_form_field.idpageview,
                        log_form_field.field_name,
                        log_form_field.time_spent,
                        log_form_field.time_hesitation,
                        log_form_field.left_blank,
                        log_form_field.submitted,
                        log_form.converted,
                        log_form.time_to_first_submission,
						COALESCE(log_link_visit_action.server_time, log_form.form_last_action_time) AS server_time,
						log_link_visit_action.idlink_va
					FROM " . Common::prefixTable('log_form') . " AS log_form
					LEFT JOIN " . Common::prefixTable('site_form') . " AS site_form
						ON log_form.idsiteform = site_form.idsiteform
					LEFT JOIN " . Common::prefixTable('log_form_field') . " AS log_form_field
						ON log_form.idlogform = log_form_field.idlogform
					LEFT JOIN " . Common::prefixTable('log_form_page') . " AS log_form_page
						ON log_form_field.idlogformpage = log_form_page.idlogformpage
					LEFT JOIN " . Common::prefixTable('log_link_visit_action') . " AS log_link_visit_action
						ON log_form_field.idpageview = log_link_visit_action.idpageview 
						   AND log_form.idvisit = log_link_visit_action.idvisit 
					WHERE log_form.idvisit IN (%s) AND log_form.time_spent > 0 AND (site_form.auto_created = 0 OR site_form.status != '". FormsModel::STATUS_DELETED ."')
					LIMIT 0, $limit", implode(",", $visitIds));
        $fieldInteractions = Db::fetchAll($sql);

        $formInteractions = [];

        foreach ($fieldInteractions as $fieldInteraction) {

            if (empty($fieldInteraction['idpageview'])) {
                // form view only (no field interactions)
                continue;
            }

            $formId   = $fieldInteraction['idpageview'] . $fieldInteraction['idlogform'];

            if (empty($formInteractions[$formId])) {
                $formInteractions[$formId] = [
                    'idvisit' => $fieldInteraction['idvisit'],
                    'type' => self::FORM_TYPE,
                    'icon' => 'plugins/FormAnalytics/images/form.png',
                    'title' => Piwik::translate('FormAnalytics_InteractedWithFormX', $fieldInteraction['form_name']),
                    'formName' => $fieldInteraction['form_name'],
                    'formId' => $fieldInteraction['form_id'],
                    'converted' => $fieldInteraction['converted'],
                    'submitted' => 0,
                    'serverTimePretty' => $fieldInteraction['server_time'],
                    'idlink_va' => $fieldInteraction['idlink_va'] + $fieldInteraction['idlogform'],
                    'timeToFirstSubmission' => $fieldInteraction['time_to_first_submission'],
                    'timeSpent' => $fieldInteraction['time_spent_form'],
                    'timeHesitation' => $fieldInteraction['time_hesitation_form'],
                    'leftBlank' => 0,
                    'fields' => []
                ];

                if (!isset($this->formConversions[$fieldInteraction['idvisit']])) {
                    $this->formConversions[$fieldInteraction['idvisit']] = 0;
                }

                $this->formConversions[$fieldInteraction['idvisit']] += $fieldInteraction['converted'];
            }

            $formInteractions[$formId]['fields'][] = [
                'fieldName' => $fieldInteraction['field_name'],
                'timeSpent' => $fieldInteraction['time_spent'],
                'timeHesitation' => $fieldInteraction['time_hesitation'],
                'leftBlank' => $fieldInteraction['left_blank'],
                'submitted' => $fieldInteraction['submitted'],
            ];

            $formInteractions[$formId]['leftBlank'] += $fieldInteraction['left_blank'];

            if ($fieldInteraction['submitted']) {
                $formInteractions[$formId]['submitted'] = 1;
                $formInteractions[$formId]['title'] = Piwik::translate('FormAnalytics_SubmittedFormX', $fieldInteraction['form_name']);
            }
        }

        return $formInteractions;
    }
}
