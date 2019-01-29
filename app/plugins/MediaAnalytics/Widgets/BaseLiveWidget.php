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
namespace Piwik\Plugins\MediaAnalytics\Widgets;

use Piwik\Common;
use Piwik\Metrics\Formatter;
use Piwik\Translation\Translator;
use Piwik\View;
use Piwik\Config;

abstract class BaseLiveWidget extends BaseWidget
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var Formatter
     */
    private $formatter;

    public function __construct(Translator $translator, Formatter $formatter)
    {
        $this->translator = $translator;
        $this->formatter = $formatter;
    }

    protected function renderLiveMetrics($action, $last30, $last3600, $translationKey)
    {
        $view = new View('@MediaAnalytics/liveMetrics');

        if (empty($last30)) {
            $last30 = 0;
        }
        if (empty($last3600)) {
            $last3600 = 0;
        }

        if ($translationKey === 'prettyTime') {
            $view->last30 = $this->formatter->getPrettyTimeFromSeconds($last30, $sentence = true);
            $view->last3600 = $this->formatter->getPrettyTimeFromSeconds($last3600, $sentence = true);
        } else {
            $view->last30 = $this->translator->translate($translationKey, $last30);
            $view->last3600 = $this->translator->translate($translationKey, $last3600);
        }

        $view->is_auto_refresh = Common::getRequestVar('is_auto_refresh', 0, 'int');
        $view->action = $action;
        $view->liveRefreshAfterMs = $this->getLiveRefreshInterval();

        return $view->render();
    }

    protected function getLiveRefreshInterval()
    {
        return (int) Config::getInstance()->General['live_widget_refresh_after_seconds'] * 1000;
    }

}