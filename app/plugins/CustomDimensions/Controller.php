<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomDimensions;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\View;

class Controller extends \Piwik\Plugin\Controller
{
    public function manage()
    {
        $idSite = Common::getRequestVar('idSite');

        Piwik::checkUserHasAdminAccess($idSite);

        return $this->renderTemplate('manage', array('title' => Piwik::translate('CustomDimensions_CustomDimensions')));
    }

}

