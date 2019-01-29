<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Updates;

use Piwik\Common;
use Piwik\Config;
use Piwik\Updater;
use Piwik\Updates;
use Piwik\Updater\Migration\Factory as MigrationFactory;

/**
 */
class Updates_0_5_4 extends Updates
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
        return array(
            $this->migration->db->changeColumnType('log_action', 'name', 'TEXT'),
        );
    }

    public function doUpdate(Updater $updater)
    {
        $salt = Common::generateUniqId();
        $config = Config::getInstance();
        $superuser = $config->superuser;
        if (!isset($superuser['salt'])) {
            try {
                if (is_writable(Config::getLocalConfigPath())) {
                    $superuser['salt'] = $salt;
                    $config->superuser = $superuser;
                    $config->forceSave();
                } else {
                    throw new \Exception('mandatory update failed');
                }
            } catch (\Exception $e) {
                throw new \Piwik\UpdaterErrorException("Please edit your config/config.ini.php file and add below <code>[superuser]</code> the following line: <br /><code>salt = $salt</code>");
            }
        }

        $plugins = $config->Plugins;
        if (!in_array('MultiSites', $plugins)) {
            try {
                if (is_writable(Config::getLocalConfigPath())) {
                    $plugins[] = 'MultiSites';
                    $config->Plugins = $plugins;
                    $config->forceSave();
                } else {
                    throw new \Exception('optional update failed');
                }
            } catch (\Exception $e) {
                throw new \Exception("You can now enable the new MultiSites plugin in the Plugins screen in the Matomo admin!");
            }
        }

        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}
