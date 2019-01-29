<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomOptOut;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Version;

/**
 * This class allows you to add, remove or rename menu items.
 * To configure a menu (such as Admin Menu, Reporting Menu, User Menu...) simply call the corresponding methods as
 * described in the API-Reference http://developer.piwik.org/api-reference/Piwik/Menu/MenuAbstract
 */
class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::isUserHasSomeAdminAccess()) {
            if (Version::VERSION >= 3) {
                $menu->addSystemItem('Custom OptOut', $this->urlForDefaultAction(), 500);
            } else {
                $menu->addSettingsItem('Custom OptOut', $this->urlForDefaultAction());
            }
        }
    }
}
