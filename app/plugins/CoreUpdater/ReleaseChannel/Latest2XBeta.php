<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreUpdater\ReleaseChannel;

use Piwik\Piwik;
use Piwik\Plugins\CoreUpdater\ReleaseChannel;

class Latest2XBeta extends ReleaseChannel
{
    public function getId()
    {
        return 'latest_2x_beta';
    }

    public function getName()
    {
        return Piwik::translate('CoreUpdater_Latest2XBetaRelease');
    }

    public function getDescription()
    {
        return Piwik::translate('CoreUpdater_LtsSupportVersion');
    }

    public function doesPreferStable()
    {
        return false;
    }

    public function getOrder()
    {
        return 21;
    }
}