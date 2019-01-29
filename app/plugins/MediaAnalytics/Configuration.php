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

namespace Piwik\Plugins\MediaAnalytics;

use Piwik\Config;

/**
 * Class Archiver
 * @package Piwik\Plugins\MediaAnalytics
 */
class Configuration
{
    private $parametersExcludeDefault = array('enablejsapi', 'player_id');

    public function getDefaultMediaParametersToExclude()
    {
        return $this->parametersExcludeDefault;
    }

    public function getMediaParametersToExclude()
    {
        $config = $this->getConfig();
        $media = $config->MediaAnalytics;

        if (empty($media)) {
            return $this->parametersExcludeDefault;
        }

        if (empty($media['media_analytics_exclude_query_parameters'])) {
            return array();
        }

        $values = explode(',', $media['media_analytics_exclude_query_parameters']);
        $values = array_map('trim', $values);

        return array_unique($values);
    }

    public function install()
    {
        $config = $this->getConfig();
        $config->MediaAnalytics = array(
            'media_analytics_exclude_query_parameters' => implode(',', $this->parametersExcludeDefault),
        );
        $config->forceSave();
    }

    public function uninstall()
    {
        $config = $this->getConfig();
        $config->MediaAnalytics = array();
        $config->forceSave();
    }

    private function getConfig()
    {
        return Config::getInstance();
    }
}
