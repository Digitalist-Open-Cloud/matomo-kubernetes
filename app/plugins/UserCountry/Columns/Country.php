<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\UserCountry\Columns;

use Piwik\Common;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Intl\Data\Provider\RegionDataProvider;
use Piwik\Metrics\Formatter;
use Piwik\Network\IP;
use Piwik\Plugin\Manager;
use Piwik\Plugins\Provider\Provider as ProviderProvider;
use Piwik\Plugins\UserCountry\LocationProvider;
use Piwik\Tracker\Visit;
use Piwik\Tracker\Visitor;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;

require_once PIWIK_INCLUDE_PATH . '/plugins/UserCountry/functions.php';

class Country extends Base
{
    protected $columnName = 'location_country';
    protected $columnType = 'CHAR(3) NULL';
    protected $type = self::TYPE_TEXT;

    protected $category =  'UserCountry_VisitLocation';
    protected $nameSingular = 'UserCountry_Country';
    protected $namePlural = 'UserCountryMap_Countries';
    protected $segmentName = 'countryCode';
    protected $acceptValues = 'de, us, fr, in, es, etc.';

    public function formatValue($value, $idSite, Formatter $formatter)
    {
        return \Piwik\Plugins\UserCountry\countryTranslate($value);
    }

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        $value = $this->getUrlOverrideValueIfAllowed('country', $request);

        if ($value !== false) {
            return $value;
        }

        $userInfo = $this->getUserInfo($request, $visitor);
        $country  = $this->getLocationDetail($userInfo, LocationProvider::COUNTRY_CODE_KEY);

        if (!empty($country) && $country != Visit::UNKNOWN_CODE) {
            return strtolower($country);
        }

        $country = $this->getCountryUsingProviderExtensionIfValid($userInfo['ip']);

        if (!empty($country)) {
            return $country;
        }

        return Visit::UNKNOWN_CODE;
    }

    private function getCountryUsingProviderExtensionIfValid($ipAddress)
    {
        if (!Manager::getInstance()->isPluginInstalled('Provider')) {
            return false;
        }

        $hostname = $this->getHost($ipAddress);
        $hostnameExtension = ProviderProvider::getCleanHostname($hostname);

        $hostnameDomain = substr($hostnameExtension, 1 + strrpos($hostnameExtension, '.'));
        if ($hostnameDomain == 'uk') {
            $hostnameDomain = 'gb';
        }

        /** @var RegionDataProvider $regionDataProvider */
        $regionDataProvider = StaticContainer::get('Piwik\Intl\Data\Provider\RegionDataProvider');

        if (array_key_exists($hostnameDomain, $regionDataProvider->getCountryList())) {
            return $hostnameDomain;
        }

        return false;
    }

    /**
     * Returns the hostname given the IP address string
     *
     * @param string $ipStr IP Address
     * @return string hostname (or human-readable IP address)
     */
    private function getHost($ipStr)
    {
        $ip = IP::fromStringIP($ipStr);

        $host = $ip->getHostname();
        $host = ($host === null ? $ipStr : $host);

        return trim(strtolower($host));
    }

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return int
     */
    public function onExistingVisit(Request $request, Visitor $visitor, $action)
    {
        return $this->getUrlOverrideValueIfAllowed('country', $request);
    }

    /**
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onAnyGoalConversion(Request $request, Visitor $visitor, $action)
    {
        $country = $visitor->getVisitorColumn($this->columnName);

        if (isset($country) && false !== $country) {
            return $country;
        }

        $browserLanguage = $request->getBrowserLanguage();
        $enableLanguageToCountryGuess = Config::getInstance()->Tracker['enable_language_to_country_guess'];
        $locationIp = $visitor->getVisitorColumn('location_ip');

        $country = Common::getCountry($browserLanguage, $enableLanguageToCountryGuess, $locationIp);

        return $country;
    }
}