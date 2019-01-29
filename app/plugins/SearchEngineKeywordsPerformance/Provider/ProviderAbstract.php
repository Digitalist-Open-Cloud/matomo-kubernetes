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
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Provider;

use Piwik\Singleton;

abstract class ProviderAbstract extends Singleton
{
    /**
     * internal provider id
     */
    const ID = '';

    /**
     * Returns internal provider id
     *
     * @return string
     */
    public function getId()
    {
        return static::ID;
    }

    /**
     * Returns the display name of the provider
     *
     * @return string
     */
    abstract public function getName();

    /**
     * Returns an array with up to two logos to be displayed for the provider
     *
     * @return array
     */
    abstract public function getLogoUrls();

    /**
     * Returns the description to be shown for the provider
     *
     * @return string
     */
    abstract public function getDescription();

    /**
     * Returns additional notes to be displayed
     *
     * @return string
     */
    abstract public function getNote();

    /**
     * Returns wether the provider is fully configured and can be used
     *
     * @return bool
     */
    abstract public function isConfigured();

    /**
     * Returns the provider client
     *
     * @return mixed
     */
    abstract public function getClient();

    /**
     * Returns Site IDs that are configured for import
     *
     * @return array
     */
    abstract public function getConfiguredSiteIds();

    /**
     * Returns an array with problems in current account and website configuration
     *
     * @return array [ sites => [], accounts => [] ]
     */
    abstract public function getConfigurationProblems();
}
