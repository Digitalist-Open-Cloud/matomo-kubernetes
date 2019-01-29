<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\UserCountryMap\Categories;

use Piwik\Category\Subcategory;

class RealTimeMapSubcategory extends Subcategory
{
    protected $categoryId = 'General_Visitors';
    protected $id = 'UserCountryMap_RealTimeMap';
    protected $order = 45;

}
