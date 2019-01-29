/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Usage:
 * <div piwik-siteselector>
 *
 * More advanced example
 * <div piwik-siteselector
 *      show-selected-site="true" show-all-sites-item="true" switch-site-on-select="true"
 *      all-sites-location="top|bottom" all-sites-text="test" show-selected-site="true"
 *      show-all-sites-item="true" only-sites-with-admin-access="true">
 *
 * Within a form
 * <div piwik-siteselector input-name="siteId">
 *
 * Events:
 * Triggers a `change` event on any change
 * <div piwik-siteselector id="mySelector">
 * $('#mySelector').on('change', function (event) { event.id/event.name })
 */
(function () {
    angular.module('piwikApp').directive('piwikSiteselector', piwikSiteselector);

    piwikSiteselector.$inject = ['$document', 'piwik', '$filter', '$timeout'];

    function piwikSiteselector($document, piwik, $filter, $timeout){
        var defaults = {
            name: '',
            siteid: piwik.idSite,
            sitename: piwik.siteName,
            allSitesLocation: 'bottom',
            allSitesText: $filter('translate')('General_MultiSitesSummary'),
            showSelectedSite: 'false',
            showAllSitesItem: 'true',
            switchSiteOnSelect: 'true',
            onlySitesWithAdminAccess: 'false'
        };

        return {
            restrict: 'A',
            scope: {
                showSelectedSite: '=',
                showAllSitesItem: '=',
                switchSiteOnSelect: '=',
                onlySitesWithAdminAccess: '=',
                inputName: '@name',
                allSitesText: '@',
                allSitesLocation: '@',
                placeholder: '@'
            },
            require: "?ngModel",
            templateUrl: 'plugins/CoreHome/angularjs/siteselector/siteselector.directive.html?cb=' + piwik.cacheBuster,
            controller: 'SiteSelectorController',
            compile: function (element, attrs) {

                for (var index in defaults) {
                    if (attrs[index] === undefined) {
                        attrs[index] = defaults[index];
                    }
                }

                return function (scope, element, attrs, ngModel) {
                    if (attrs.siteid && attrs.sitename) {
                        scope.selectedSite = {id: attrs.siteid, name: attrs.sitename};
                    }

                    scope.model.onlySitesWithAdminAccess = scope.onlySitesWithAdminAccess;

                    if (ngModel) {
                        ngModel.$setViewValue(scope.selectedSite);
                    }

                    scope.$watch('selectedSite.id', function (newValue, oldValue, scope) {
                        if (newValue != oldValue) {
                            element.attr('siteid', newValue);
                            element.trigger('change', scope.selectedSite);
                        }
                    });

                    if (ngModel) {
                        ngModel.$render = function() {
                            if (angular.isString(ngModel.$viewValue)) {
                                scope.selectedSite = JSON.parse(ngModel.$viewValue);
                            } else {
                                scope.selectedSite = ngModel.$viewValue;
                            }
                        };
                    }

                    scope.$watch('selectedSite', function (newValue) {
                        if (ngModel) {
                            ngModel.$setViewValue(newValue);
                        }
                    });

                    scope.$watch('view.showSitesList', function (newValue) {
                        element.toggleClass('expanded', !! newValue);
                    });

                    $timeout(function () {
                        initTopControls();
                    });
                };
            }
        };
    }
})();