/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

(function () {
    angular.module('piwikApp').factory('formModel', formsModel);

    formsModel.$inject = ['piwikApi', '$q'];

    function formsModel(piwikApi, $q) {
        var fetchPromise = {};
        var goalsPromise = null;

        var model = {
            forms : [],
            isLoading: false,
            isUpdating: false,
            filterStatus: 'running',
            findForm: findForm,
            createOrUpdateForm: createOrUpdateForm,
            archiveForm: archiveForm,
            deleteForm: deleteForm,
            reload: reload,
            fetchForms: fetchForms,
            fetchAvailableStatuses: fetchAvailableStatuses,
            getAvailableFormRules: getAvailableFormRules,
            getAvailablePageRules: getAvailablePageRules,
            filterRules: filterRules
        };

        return model;

        function reload()
        {
            model.forms = [];
            fetchPromise = {};
            return fetchForms();
        }

        function arrayFilter(array, filter)
        {
            var entries = [];

            angular.forEach(array, function (value) {
                if (filter(value)) {
                    entries.push(value);
                }
            });

            return entries;
        }

        function filterRules(rules)
        {
            return arrayFilter(rules, function (target) {
                return !!target && target.value;
            });
        }

        function fetchAvailableStatuses() {
            return piwikApi.fetch({method: 'FormAnalytics.getAvailableStatuses'});
        }

        function getAvailableFormRules() {
            return piwikApi.fetch({method: 'FormAnalytics.getAvailableFormRules', filter_limit: '-1'});
        }

        function getAvailablePageRules() {
            return piwikApi.fetch({method: 'FormAnalytics.getAvailablePageRules', filter_limit: '-1'});
        }

        function fetchForms() {

            var params = {method: 'FormAnalytics.getFormsByStatuses', filter_limit: '-1', statuses: model.filterStatus};
            var key = params.method + params.statuses;

            if (!fetchPromise[key]) {
                fetchPromise[key] = piwikApi.fetch(params);
            }

            model.isLoading = true;
            model.forms = [];

            return fetchPromise[key].then(function (forms) {
                model.forms = forms;
                model.isLoading = false;
                return forms;
            }, function () {
                model.isLoading = false;
            });
        }

        function findForm(idSiteForm) {

            // before going through an API request we first try to find it in loaded forms
            var found;
            angular.forEach(model.forms, function (form) {
                if (parseInt(form.idsiteform, 10) === idSiteForm) {
                    found = form;
                }
            });

            if (found) {
                var deferred = $q.defer();
                deferred.resolve(found);
                return deferred.promise;
            }

            // otherwise we fetch it via API
            model.isLoading = true;

            return piwikApi.fetch({
                idForm: idSiteForm,
                method: 'FormAnalytics.getForm'
            }).then(function (form) {
                model.isLoading = false;
                return form;

            }, function (error) {
                model.isLoading = false;
            });
        }

        function deleteForm(idForm) {

            model.isUpdating = true;
            model.forms = [];

            piwikApi.withTokenInUrl();

            return piwikApi.fetch({idForm: idForm, method: 'FormAnalytics.deleteForm'}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success'};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function archiveForm(idForm) {

            model.isUpdating = true;
            model.forms = [];

            piwikApi.withTokenInUrl();

            return piwikApi.fetch({idForm: idForm, method: 'FormAnalytics.archiveForm'}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success'};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function createOrUpdateForm(form, method) {
            form = angular.copy(form);
            form.method = method;

            if (form.matchPageOnly) {
                form.match_form_rules = [];
            }

            var map = {
                idForm: 'idsiteform',
                matchFormRules: 'match_form_rules',
                matchPageRules: 'match_page_rules',
                conversionRules: 'conversion_rules',
            };

            angular.forEach(map, function (value, key) {
                if (typeof form[value] !== 'undefined') {
                    form[key] = form[value];
                    delete form[value];
                }
            });

            form.matchFormRules = filterRules(form.matchFormRules);
            form.matchPageRules = filterRules(form.matchPageRules);
            form.conversionRules = filterRules(form.conversionRules);

            angular.forEach(['name', 'description'], function (param) {
                if (form[param]) {
                    // trim values
                    form[param] = form[param].replace(/^\s+|\s+$/g, '');
                }
            });

            var postParams = ['matchFormRules', 'matchPageRules', 'conversionRules'];
            var post = {};
            for (var i = 0; i < postParams.length; i++) {
                var postParam = postParams[i];
                if (typeof form[postParam] !== 'undefined') {
                    post[postParam] = form[postParam];
                    delete form[postParam];
                }
            }

            model.isUpdating = true;

            piwikApi.withTokenInUrl();

            return piwikApi.post(form, post).then(function (response) {
                model.isUpdating = false;

                return {type: 'success', response: response};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

    }
})();