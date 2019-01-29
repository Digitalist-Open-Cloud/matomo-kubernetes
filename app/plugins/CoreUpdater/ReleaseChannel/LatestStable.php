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

class LatestStable extends ReleaseChannel
{
    public function getId()
    {
        return 'latest_stable';
    }

    public function getName()
    {
        return Piwik::translate('CoreUpdater_LatestStableRelease');
    }

    public function getDescription()
    {
        return Piwik::translate('General_Recommended');
    }

    public function getDownloadUrlWithoutScheme($version)
    {
        return '://builds.matomo.org/piwik.zip';
    }

    public function getOrder()
    {
        return 10;
    }
}