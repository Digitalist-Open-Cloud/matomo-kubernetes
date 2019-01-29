/*!
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * All information contained herein is, and remains the property of InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
(function () {
    angular.module('piwikApp').factory('activityLogModel', activityLogModel);

    activityLogModel.$inject = ['piwikApi', 'piwik', '$filter'];

    function activityLogModel(piwikApi, piwik, $filter) {
        var model = {
            activities: [],
            searchTerm: '',
            busy: false,
            pageSize: 10,
            currentPage: 0,
            offsetStart: 0,
            offsetEnd: 10,
            hasPrev: false,
            hasNext: false,
            totalNumberOfSites: 0,
            availableUsers: [],
            filter: {
                userLogin: '',
                activityType: ''
            },
            previousPage: previousPage,
            nextPage: nextPage,
            applyFilter: applyFilter,
            fetchActivityLog: fetchActivityLog
        };

        var translate = $filter('translate');

        fetchActivityCount();

        if (piwik.hasSuperUserAccess) {
            fetchAvailableUsers();
        } else {
            model.availableUsers = [ {key: piwik.userLogin, value: piwik.userLogin} ];
        }

        return model;

        /**
         * 
         * @returns void
         */
        function fetchActivityCount() {
            var params = {
                method: 'ActivityLog.getEntryCount',
                filterByUserLogin: model.filter.userLogin,
                filterByActivityType: model.filter.activityType
            };

            return piwikApi.fetch(params).then(function (count) {
                if (!count || !count.value) {
                    return;
                }

                model.totalNumberOfSites = count.value;
            });
        }

        function fetchAvailableUsers() {
            var params = {
                method: 'UsersManager.getUsersLogin'
            };

            return piwikApi.fetch(params).then(function (userLogins) {
                if (!userLogins || !angular.isArray(userLogins)) {
                    return;
                }

                var availableUsers = [
                    {key: '', value: translate('General_All')},
                    {key: 'Console Command', value: translate('ActivityLog_ConsoleCommand')},
                    {key: 'Matomo System', value: translate('ActivityLog_System')}
                ];

                for (var i = 0; i < userLogins.length; i++) {
                    availableUsers.push({key: userLogins[i], value: userLogins[i]});
                }

                model.availableUsers = availableUsers;
            });
        }

        function onError() {
            setActivities([]);
        }

        function setActivities(activities) {
            model.activities = activities;

            var numSites = activities.length;
            model.offsetStart = model.currentPage * model.pageSize;
            model.offsetEnd = model.offsetStart + numSites;
            model.hasPrev = model.currentPage >= 1;
            model.hasNext = numSites === model.pageSize;
        }

        function setCurrentPage(page) {
            if (page < 0) {
                page = 0;
            }

            model.currentPage = page;
        }

        function previousPage() {
            setCurrentPage(model.currentPage - 1);
            fetchActivityLog();
        }

        function nextPage() {
            setCurrentPage(model.currentPage + 1);
            fetchActivityLog();
        }

        function applyFilter() {
            model.currentPage = 0;
            fetchActivityCount();
            fetchActivityLog();
        }

        function fetchActivityLog() {
            if (model.busy) {
                return;
            }

            model.busy = true;
            var limit = model.pageSize;
            var offset = model.currentPage * model.pageSize;

            var params = {
                method: 'ActivityLog.getEntries',
                offset: offset,
                limit: limit,
                filterByUserLogin: model.filter.userLogin,
                filterByActivityType: model.filter.activityType
            };

            return piwikApi.fetch(params).then(function (activities) {
                if (!activities) {
                    onError();
                    return;
                }

                setActivities(activities);
            }, onError)['finally'](function () {
                model.busy = false;
                model.offset += model.limit;
            });
        }
    }
})();