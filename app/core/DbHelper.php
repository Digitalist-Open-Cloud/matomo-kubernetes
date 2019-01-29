<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik;

use Exception;
use Piwik\Db\Schema;
use Piwik\DataAccess\ArchiveTableCreator;

/**
 * Contains database related helper functions.
 */
class DbHelper
{
    /**
     * Get list of tables installed
     *
     * @param bool $forceReload Invalidate cache
     * @return array  Tables installed
     */
    public static function getTablesInstalled($forceReload = true)
    {
        return Schema::getInstance()->getTablesInstalled($forceReload);
    }

    /**
     * Get list of installed columns in a table
     *
     * @param  string $tableName The name of a table.
     *
     * @return array  Installed columns indexed by the column name.
     */
    public static function getTableColumns($tableName)
    {
        return Schema::getInstance()->getTableColumns($tableName);
    }

    /**
     * Creates a new table in the database.
     *
     * Example:
     * ```
     * $tableDefinition = "`age` INT(11) NOT NULL AUTO_INCREMENT,
     *                     `name` VARCHAR(255) NOT NULL";
     *
     * DbHelper::createTable('tablename', $tableDefinition);
     * ``
     *
     * @param string $nameWithoutPrefix   The name of the table without any piwik prefix.
     * @param string $createDefinition    The table create definition
     *
     * @api
     */
    public static function createTable($nameWithoutPrefix, $createDefinition)
    {
        Schema::getInstance()->createTable($nameWithoutPrefix, $createDefinition);
    }

    /**
     * Returns true if Piwik is installed
     *
     * @since 0.6.3
     *
     * @return bool  True if installed; false otherwise
     */
    public static function isInstalled()
    {
        try {
            return Schema::getInstance()->hasTables();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Truncate all tables
     */
    public static function truncateAllTables()
    {
        Schema::getInstance()->truncateAllTables();
    }

    /**
     * Creates an entry in the User table for the "anonymous" user.
     */
    public static function createAnonymousUser()
    {
        Schema::getInstance()->createAnonymousUser();
    }

    /**
     * Create all tables
     */
    public static function createTables()
    {
        Schema::getInstance()->createTables();
    }

    /**
     * Drop database, used in tests
     */
    public static function dropDatabase($dbName = null)
    {
        if (defined('PIWIK_TEST_MODE') && PIWIK_TEST_MODE) {
            Schema::getInstance()->dropDatabase($dbName);
        }
    }

    /**
     * Check database connection character set is utf8.
     *
     * @return bool  True if it is (or doesn't matter); false otherwise
     */
    public static function isDatabaseConnectionUTF8()
    {
        return Db::get()->isConnectionUTF8();
    }

    /**
     * Checks the database server version against the required minimum
     * version.
     *
     * @see config/global.ini.php
     * @since 0.4.4
     * @throws Exception if server version is less than the required version
     */
    public static function checkDatabaseVersion()
    {
        Db::get()->checkServerVersion();
    }

    /**
     * Disconnect from database
     */
    public static function disconnectDatabase()
    {
        Db::get()->closeConnection();
    }

    /**
     * Create database
     *
     * @param string|null $dbName
     */
    public static function createDatabase($dbName = null)
    {
        Schema::getInstance()->createDatabase($dbName);
    }

    /**
     * Get the SQL to create Piwik tables
     *
     * @return array  array of strings containing SQL
     */
    public static function getTablesCreateSql()
    {
        return Schema::getInstance()->getTablesCreateSql();
    }

    /**
     * Get the SQL to create a specific Piwik table
     *
     * @param string $tableName Unprefixed table name.
     * @return string  SQL
     */
    public static function getTableCreateSql($tableName)
    {
        return Schema::getInstance()->getTableCreateSql($tableName);
    }

    /**
     * Deletes archive tables. For use in tests.
     */
    public static function deleteArchiveTables()
    {
        foreach (ArchiveTableCreator::getTablesArchivesInstalled() as $table) {
            Log::debug("Dropping table $table");

            Db::query("DROP TABLE IF EXISTS `$table`");
        }

        ArchiveTableCreator::refreshTableList($forceReload = true);
    }

    /**
     * Returns true if the string is a valid database name for MySQL. MySQL allows + in the database names.
     * Database names that start with a-Z or 0-9 and contain a-Z, 0-9, underscore(_), dash(-), plus(+), and dot(.) will be accepted.
     * File names beginning with anything but a-Z or 0-9 will be rejected (including .htaccess for example).
     * File names containing anything other than above mentioned will also be rejected (file names with spaces won't be accepted).
     *
     * @param string $dbname
     * @return bool
     */
    public static function isValidDbname($dbname)
    {
        return (0 !== preg_match('/(^[a-zA-Z0-9]+([a-zA-Z0-9\_\.\-\+]*))$/D', $dbname));
    }

}
