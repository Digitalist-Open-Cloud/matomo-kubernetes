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
namespace Piwik\Plugins\FormAnalytics\Activity;

class FormUpdated extends BaseActivity
{
    protected $eventName = 'API.FormAnalytics.updateForm.end';

    public function extractParams($eventData)
    {
        list($return, $finalAPIParameters) = $eventData;

        $idForm = $finalAPIParameters['parameters']['idForm'];
        $idSite = $finalAPIParameters['parameters']['idSite'];

        return $this->formatActivityData($idSite, $idForm);
    }

    public function getTranslatedDescription($activityData, $performingUser)
    {
        $siteName = $this->getSiteNameFromActivityData($activityData);
        $formName = $this->getFormNameFromActivityData($activityData);

        $desc = sprintf('updated the form "%1$s" for site "%2$s"', $formName, $siteName);

        return $desc;
    }
}
