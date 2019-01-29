<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace\Widgets;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\Marketplace\Api\Client;
use Piwik\Plugins\Marketplace\Input\PurchaseType;
use Piwik\Plugins\Marketplace\Input\Sort;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class GetPremiumFeatures extends Widget
{
    /**
     * @var Client
     */
    private $marketplaceApiClient;

    public function __construct(Client $marketplaceApiClient)
    {
        $this->marketplaceApiClient = $marketplaceApiClient;
    }

    public static function getCategory()
    {
        return 'About Matomo';
    }

    public static function getName()
    {
        return Piwik::translate('Marketplace_PaidPlugins');
    }

    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId(self::getCategory());
        $config->setName(self::getName());
        $config->setOrder(20);
    }

    public function render()
    {
        $template = 'getPremiumFeatures';

        $plugins = $this->marketplaceApiClient->searchForPlugins('', '', Sort::METHOD_LAST_UPDATED, PurchaseType::TYPE_PAID);

        $plugins = array_filter($plugins, function ($plugin) {
            return empty($plugin['isBundle']);
        });

        if (empty($plugins)) {
            $plugins = array();
        } else {
            $plugins = array_splice($plugins, 0, 20);
        }

        return $this->renderTemplate($template, array(
            'plugins' => $plugins
        ));
    }

}