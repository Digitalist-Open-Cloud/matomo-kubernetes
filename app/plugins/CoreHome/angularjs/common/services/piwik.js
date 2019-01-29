/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
(function () {
    angular.module('piwikApp.service').service('piwik', piwikService);

    piwikService.$inject = ['piwikPeriods'];

    function piwikService(piwikPeriods) {
        var originalTitle;
        piwik.helper    = piwikHelper;
        piwik.broadcast = broadcast;
        piwik.updatePeriodParamsFromUrl = updatePeriodParamsFromUrl;
        piwik.updateDateInTitle = updateDateInTitle;
        piwik.hasUserCapability = hasUserCapability;
        return piwik;

        function hasUserCapability(capability) {
            return angular.isArray(piwik.userCapabilities) && piwik.userCapabilities.indexOf(capability) !== -1;
        }

        function updatePeriodParamsFromUrl() {
            var date = piwik.broadcast.getValueFromHash('date') || piwik.broadcast.getValueFromUrl('date');
            var period = piwik.broadcast.getValueFromHash('period') || piwik.broadcast.getValueFromUrl('period');
            if (!isValidPeriod(period, date)) {
                // invalid data in URL
                return;
            }

            if (piwik.period === period && piwik.currentDateString === date) {
                // this period / date is already loaded
                return;
            }

            piwik.period = period;

            var dateRange = piwikPeriods.parse(period, date).getDateRange();
            piwik.startDateString = $.datepicker.formatDate('yy-mm-dd', dateRange[0]);
            piwik.endDateString = $.datepicker.formatDate('yy-mm-dd', dateRange[1]);

            updateDateInTitle(date, period);

            // do not set anything to previousN/lastN, as it's more useful to plugins
            // to have the dates than previousN/lastN.
            if (piwik.period === 'range') {
                date = piwik.startDateString + ',' + piwik.endDateString;
            }

            piwik.currentDateString = date;
        }

        function isValidPeriod(periodStr, dateStr) {
            try {
                piwikPeriods.get(periodStr).parse(dateStr);
                return true;
            } catch (e) {
                return false;
            }
        }

        function updateDateInTitle( date, period ) {
            // Cache server-rendered page title
            originalTitle = originalTitle || document.title;
            var titleParts = originalTitle.split('-');
            var dateString = ' ' + piwikPeriods.parse(period, date).getPrettyString() + ' ';
            titleParts.splice(1, 0, dateString);
            document.title = titleParts.join('-');
        }
    }

    angular.module('piwikApp.service').run(initPiwikService);

    initPiwikService.$inject = ['piwik', '$rootScope'];

    function initPiwikService(piwik, $rootScope) {
        $rootScope.$on('$locationChangeSuccess', piwik.updatePeriodParamsFromUrl);
    }
})();
