/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Usage:
 *
 * <h2 piwik-enriched-headline>All Websites Dashboard</h2>
 * -> uses "All Websites Dashboard" as featurename
 *
 * <h2 piwik-enriched-headline feature-name="All Websites Dashboard">All Websites Dashboard (Total: 309 Visits)</h2>
 * -> custom featurename
 *
 * <h2 piwik-enriched-headline help-url="http://piwik.org/guide">All Websites Dashboard</h2>
 * -> shows help icon and links to external url
 *
 * <h2 piwik-enriched-headline edit-url="index.php?module=Foo&action=bar&id=4">All Websites Dashboard</h2>
 * -> makes the headline clickable linking to the specified url
 *
 * <h2 piwik-enriched-headline inline-help="inlineHelp">Pages report</h2>
 * -> inlineHelp specified via a attribute shows help icon on headline hover
 *
 * <h2 piwik-enriched-headline>All Websites Dashboard
 *     <div class="inlineHelp">My <strong>inline help</strong></div>
 * </h2>
 * -> alternative definition for inline help
 * -> shows help icon to display inline help on click. Note: You can combine inlinehelp and help-url
 */
(function () {
    angular.module('piwikApp').directive('piwikEnrichedHeadline', piwikEnrichedHeadline);

    piwikEnrichedHeadline.$inject = ['$document', 'piwik', '$filter'];

    function piwikEnrichedHeadline($document, piwik, $filter){
        var defaults = {
            helpUrl: '',
            editUrl: ''
        };

        return {
            transclude: true,
            restrict: 'A',
            scope: {
                helpUrl: '@',
                editUrl: '@',
                featureName: '@',
                inlineHelp: '@?'
            },
            templateUrl: 'plugins/CoreHome/angularjs/enrichedheadline/enrichedheadline.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                for (var index in defaults) {
                    if (!attrs[index]) { attrs[index] = defaults[index]; }
                }

                return function (scope, element, attrs) {
                    if (!scope.inlineHelp) {

                        var helpNode = $('[ng-transclude] .inlineHelp', element);

                        if ((!helpNode || !helpNode.length) && element.next()) {
                            // hack for reports :(
                            helpNode = element.next().find('.reportDocumentation');
                        }

                        if (helpNode && helpNode.length) {
                            if ($.trim(helpNode.text())) {
                                scope.inlineHelp = $.trim(helpNode.html());
                            }
                            helpNode.remove();
                        }
                    }

                    if (!attrs.featureName) {
                        attrs.featureName = $.trim(element.find('.title').first().text());
                    }
                };
            }
        };
    }
})();