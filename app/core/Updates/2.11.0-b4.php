<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Updates;

use Piwik\DataAccess\ArchiveTableCreator;
use Piwik\Updater;
use Piwik\Updates;
use Piwik\Updater\Migration\Factory as MigrationFactory;

class Updates_2_11_0_b4 extends Updates
{
    /**
     * @var MigrationFactory
     */
    private $migration;

    public function __construct(MigrationFactory $factory)
    {
        $this->migration = $factory;
    }

    public function getMigrations(Updater $updater)
    {
        $migrations = array();

        $archiveTables = ArchiveTableCreator::getTablesArchivesInstalled();

        $archiveBlobTables = array_filter($archiveTables, function ($name) {
            return ArchiveTableCreator::getTypeFromTableName($name) == ArchiveTableCreator::BLOB_TABLE;
        });

        foreach ($archiveBlobTables as $table) {
            $migrations[] = $this->migration->db->sql("UPDATE $table SET name = 'UserLanguage_language' WHERE name = 'UserSettings_language'");
        }

        return $migrations;
    }

    public function doUpdate(Updater $updater)
    {
        $pluginManager = \Piwik\Plugin\Manager::getInstance();

        try {
            $pluginManager->activatePlugin('UserLanguage');
        } catch (\Exception $e) {
        }

        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}
