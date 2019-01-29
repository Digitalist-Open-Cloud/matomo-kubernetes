<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Events\Widgets;

use Piwik\Plugins\CoreHome\CoreHome;
use Piwik\Translation\Translator;
use Piwik\Widget\WidgetContainerConfig;

class EventsByDimension extends WidgetContainerConfig
{
    protected $layout = CoreHome::WIDGET_CONTAINER_LAYOUT_BY_DIMENSION;
    protected $id = 'Events';
    protected $categoryId = 'General_Actions';
    protected $subcategoryId = 'Events_Events';

}
