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

namespace Piwik\Plugins\CustomReports;

use Piwik\Config;

class Configuration
{
    const DEFAULT_ARCHIVE_MAX_ROWS = 500;
    const DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE = 500;
    const DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES = 1;
    const KEY_ARCHIVE_MAX_ROWS = 'datatable_archiving_maximum_rows_custom_reports';
    const KEY_ARCHIVE_MAX_ROWS_SUBTABLE = 'datatable_archiving_maximum_rows_subtable_custom_reports';
    const KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES = 'custom_reports_validate_report_content_all_websites';

    public function install()
    {
        $config = $this->getConfig();

        if (empty($config->CustomReports)) {
            $config->CustomReports = array();
        }
        $reports = $config->CustomReports;

        // we make sure to set a value only if none has been configured yet, eg in common config.
        if (empty($reports[self::KEY_ARCHIVE_MAX_ROWS])) {
            $reports[self::KEY_ARCHIVE_MAX_ROWS] = self::DEFAULT_ARCHIVE_MAX_ROWS;
        }
        if (empty($reports[self::KEY_ARCHIVE_MAX_ROWS_SUBTABLE])) {
            $reports[self::KEY_ARCHIVE_MAX_ROWS_SUBTABLE] = self::DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE;
        }
        if (empty($reports[self::KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES])) {
            $reports[self::KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES] = self::DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES;
        }
        $config->CustomReports = $reports;

        $config->forceSave();
    }

    public function uninstall()
    {
        $config = $this->getConfig();
        $config->CustomReports = array();
        $config->forceSave();
    }

    /**
     * @return int
     */
    public function getArchiveMaxRowsSubtable()
    {
        $value = $this->getConfigValue(self::KEY_ARCHIVE_MAX_ROWS_SUBTABLE, self::DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE);

        if ($value === false || $value === '' || $value === null) {
            $value = self::DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE;
        }

        return (int) $value;
    }

    /**
     * @return int
     */
    public function getArchiveMaxRows()
    {
        $value = $this->getConfigValue(self::KEY_ARCHIVE_MAX_ROWS, self::DEFAULT_ARCHIVE_MAX_ROWS);

        if ($value === false || $value === '' || $value === null) {
            $value = self::DEFAULT_ARCHIVE_MAX_ROWS;
        }

        return (int) $value;
    }

    /**
     * @return int
     */
    public function shouldValidateReportContentWhenAllSites()
    {
        $value = $this->getConfigValue(self::KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES, self::DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES);

        if ($value === false || $value === '' || $value === null) {
            $value = self::DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES;
        }

        return (bool) $value;
    }

    private function getConfig()
    {
        return Config::getInstance();
    }

    private function getConfigValue($name, $default)
    {
        $config = $this->getConfig();
        $attribution = $config->CustomReports;
        if (isset($attribution[$name])) {
            return $attribution[$name];
        }
        return $default;
    }
}
