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

namespace Piwik\Plugins\UsersFlow\Archiver;

use Piwik\Piwik;

class DataSources
{

    const DATA_SOURCE_PAGE_URL = 'page_url';
    const DATA_SOURCE_PAGE_TITLE = 'page_title';

    public static function getValidDataSource($dataSource)
    {
        if (empty($dataSource)) {
            return self::DATA_SOURCE_PAGE_URL;
        }

        $dataSource = strtolower($dataSource);

        foreach (self::getAvailableDataSources() as $source) {
            if (strtolower($source['value']) === $dataSource) {
                return $dataSource;
            }
        }

        return self::DATA_SOURCE_PAGE_URL;
    }

    public static function getAvailableDataSources()
    {
        return array(
            array('value' => self::DATA_SOURCE_PAGE_URL, 'name' => Piwik::translate('Actions_PageUrls')),
            array('value' => self::DATA_SOURCE_PAGE_TITLE, 'name' => Piwik::translate('Actions_WidgetPageTitles')),
        );
    }

}
