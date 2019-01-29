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

namespace Piwik\Plugins\FormAnalytics\ProfileSummary;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\FormAnalytics\API;
use Piwik\Plugins\Live\ProfileSummary\ProfileSummaryAbstract;
use Piwik\View;

/**
 * Class FormSummary
 *
 * @api
 */
class FormSummary extends ProfileSummaryAbstract
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return Piwik::translate('FormAnalytics_ColumnFormConversions');
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        if (empty($this->profile['uniqueFormConversions'])) {
            return '';
        }

        $idSite            = Common::getRequestVar('idSite', null, 'int');
        $view              = new View('@FormAnalytics/_profileSummary.twig');
        $view->visitorData = $this->profile;
        $view->forms       = [];
        $forms             = Request::processRequest('FormAnalytics.getForms', ['idSite' => $idSite]);

        foreach ($forms AS $form) {
            $view->forms[$form['idsiteform']] = $form;
        }

        return $view->render();
    }

    /**
     * @inheritdoc
     */
    public function getOrder()
    {
        return 1;
    }
}