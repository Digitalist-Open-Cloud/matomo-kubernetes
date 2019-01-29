<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\UserId\Columns;

use Piwik\Piwik;
use Piwik\Plugin\Dimension\VisitDimension;

/**
 * UserId dimension
 */
class UserId extends VisitDimension
{

    protected $nameSingular = 'UserId_UserId';

}