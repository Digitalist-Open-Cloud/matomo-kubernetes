<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Validators;

use Piwik\Piwik;
use Piwik\Site;
use Piwik\UrlHelper;

class IdSite extends BaseValidator
{
    public function validate($value)
    {
        new Site($value);
    }
}