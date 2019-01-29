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
namespace Piwik\Plugins\FormAnalytics\Widgets;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Piwik\Widget\WidgetConfig;

class GettingStarted extends BaseWidget
{
    public static function configure(WidgetConfig $config)
    {
        parent::configure($config);
        $config->setIsNotWidgetizable();
        $config->setName('FormAnalytics_GettingStarted');
        $config->setOrder(10);

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (self::shouldEnable($idSite)) {
            $forms = StaticContainer::get('Piwik\Plugins\FormAnalytics\Model\FormsModel');
            $forms = $forms->getFormsByStatuses($idSite, FormsModel::STATUS_RUNNING);
            if (empty($forms)) {
                // we only make it visible in the UI when there are no forms. We cannot disable/enable it
                // as we otherwise would show an error message "not allowed to view widget" when suddenly
                // forms are configured
                $config->setSubcategoryId('FormAnalytics_GettingStarted');
            }
        }
    }

    private static function shouldEnable($idSite)
    {
        return !empty($idSite) && Piwik::isUserHasViewAccess($idSite) && !Piwik::isUserHasAdminAccess($idSite);
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $isAdmin = Piwik::isUserHasAdminAccess($idSite);

        return $this->renderTemplate('gettingStarted.twig', array(
            'isAdmin' => $isAdmin
        ));
    }

}