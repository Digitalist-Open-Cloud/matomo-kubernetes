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
    angular.module('piwikApp').controller('FormEditController', FormEditController);

    FormEditController.$inject = ['$scope', 'formModel', 'piwik', '$location', '$filter', '$timeout', '$rootScope'];

    function FormEditController($scope, formModel, piwik, $location, $filter, $timeout, $rootScope) {

        var self = this;
        var currentId = null;
        var notificationId = 'formsmanagement';

        var translate = $filter('translate');

        this.isDirty = false;
        this.model = formModel;

        function setRules(rules, typeAttributes, typeExamples, typePatterns) {
            rules = angular.copy(rules);

            self[typeAttributes] = [];
            self[typeExamples] = {};
            self[typePatterns] = {};

            angular.forEach(rules, function (value) {
                self[typeAttributes].push({key: value.key, value: value.name});
                self[typeExamples][value.key] = value.example;
                self[typePatterns][value.key] = [];

                angular.forEach(value.patterns, function (type) {
                    self[typePatterns][value.key].push({value: type.name, key: type.key});
                });
            });
        }

        formModel.getAvailableFormRules().then(function (rules) {
            setRules(rules, 'formRulesAttributes', 'formRulesExamples', 'formRulesPatterns');
        });

        formModel.getAvailablePageRules().then(function (rules) {
            setRules(rules, 'pageRulesAttributes', 'pageRulesExamples', 'pageRulesPatterns');
        });

        function getNotification()
        {
            var UI = require('piwik/UI');
            return new UI.Notification();
        }

        function removeAnyFormNotification()
        {
            var notification = getNotification();
            notification.remove(notificationId);
            notification.remove('ajaxHelper');
        }

        function showNotification(message, context)
        {
            var notification = getNotification();
            notification.show(message, {context: context, id: notificationId});
            $timeout(function () {
                notification.scrollToNotification();
            }, 100);
        }

        function showErrorFieldNotProvidedNotification(title)
        {
            var message = _pk_translate('FormAnalytics_ErrorXNotProvided', [title]);
            showNotification(message, 'error');
        }

        function init(idSiteForm)
        {
            self.create = idSiteForm == '0';
            self.edit   = !self.create;
            self.form = {};

            piwik.helper.lazyScrollToContent();

            if (self.edit && idSiteForm) {
                self.editTitle = 'FormAnalytics_EditForm';
                self.model.findForm(idSiteForm).then(function (form) {
                    if (!form) {
                        return;
                    }
                    self.form = form;

                    if (self.form.match_form_rules && self.form.match_form_rules.length) {
                        self.form.matchPageOnly = false;
                    } else {
                        self.form.matchPageOnly = true;
                    }

                    self.addInitialMatchFormRule();
                    self.addInitialMatchPageRule();
                    self.addInitialConversionRule();
                    self.isDirty = false;
                });
            } else if (self.create) {
                self.editTitle = 'FormAnalytics_CreateNewForm';
                self.form = {
                    idSite: piwik.idSite,
                    name: '',
                    description: '',
                    status: 'running',
                    matchPageOnly: false
                };
                self.addInitialMatchFormRule();
                self.addInitialMatchPageRule();
                self.addInitialConversionRule();
                self.isDirty = false;
            }
        }

        this.addInitialMatchFormRule = function () {
            if (!this.form) {
                return;
            }
            if (this.form.match_form_rules && this.form.match_form_rules.length) {
                return;
            }
            this.addMatchFormRule();
        };

        this.addMatchFormRule = function () {
            if (!this.form) {
                return;
            }
            if (!this.form.match_form_rules || !this.form.match_form_rules.length) {
                this.form.match_form_rules = [];
            }

            this.form.match_form_rules.push({
                attribute: 'form_name',
                pattern: 'equals',
                value: '',
            });

            this.isDirty = true;
        };

        this.addInitialMatchPageRule = function () {
            if (!this.form) {
                return;
            }
            if (this.form.match_page_rules && this.form.match_page_rules.length) {
                return;
            }
            this.addMatchPageRule();
        };

        this.addMatchPageRule = function () {
            if (!this.form) {
                return;
            }
            if (!this.form.match_page_rules || !this.form.match_page_rules.length) {
                this.form.match_page_rules = [];
            }

            this.form.match_page_rules.push({
                attribute: 'page_url',
                pattern: 'equals',
                value: '',
            });

            this.isDirty = true;
        };

        this.addInitialConversionRule = function () {
            if (!this.form) {
                return;
            }
            if (this.form.conversion_rules && this.form.conversion_rules.length) {
                return;
            }
            this.addConversionRule();
        };

        this.addConversionRule = function () {
            if (!this.form) {
                return;
            }
            if (!this.form.conversion_rules || !this.form.conversion_rules.length) {
                this.form.conversion_rules = [];
            }

            this.form.conversion_rules.push({
                attribute: 'page_url',
                pattern: 'equals',
                value: '',
            });

            this.isDirty = true;
        };

        this.removeConversionRule = function (index) {
            if (this.form && index > -1) {
                this.form.conversion_rules.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.removeMatchFormRule = function (index) {
            if (this.form && index > -1) {
                this.form.match_form_rules.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.removeMatchPageRule = function (index) {
            if (this.form && index > -1) {
                this.form.match_page_rules.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.cancel = function () {
            $scope.idForm = null;
            currentId = null;

            var $search = $location.search();
            delete $search.idForm;
            $location.search($search);
        };

        function checkRequiredFieldsAreSet()
        {
            var title;

            if (!self.form.name) {
                title = _pk_translate('General_Name');
                showErrorFieldNotProvidedNotification(title);
                return false;
            }

            if (!self.form.matchPageOnly &&
                (!self.form.match_form_rules
                  || !self.form.match_form_rules.length
                  || !formModel.filterRules(self.form.match_form_rules).length)) {
                title = _pk_translate('FormAnalytics_ErrorFormRuleRequired');
                showNotification(title, 'error');
                return false;
            }

            return true;
        }

        this.createForm = function () {
            var method = 'FormAnalytics.addForm';

            removeAnyFormNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            this.isUpdating = true;

            formModel.createOrUpdateForm(this.form, method).then(function (response) {
                self.isUpdating = false;

                if (!response || response.type === 'error' || !response.response) {
                    return;
                }

                self.isDirty = false;

                var idForm = response.response.value;

                formModel.reload().then(function () {
                    if (piwik.helper.isAngularRenderingThePage()) {
                        $rootScope.$emit('updateReportingMenu');
                        var $search = $location.search();
                        $search.idForm = idForm;
                        $location.search($search);
                    } else {
                        $location.url('/?idForm=' + idForm);
                    }

                    $timeout(function () {
                        showNotification(translate('FormAnalytics_FormCreated'), response.type);
                    }, 200);
                });
            }, function () {
                self.isUpdating = false;
            });
        };

        this.setValueHasChanged = function () {
            this.isDirty = true;
        };

        this.updateForm = function () {

            removeAnyFormNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            var method = 'FormAnalytics.updateForm';

            this.isUpdating = true;

            formModel.createOrUpdateForm(self.form, method).then(function (response) {
                if (response.type === 'error') {
                    return;
                }

                var idSiteForm = self.form.idsiteform;

                self.isDirty = false;
                self.form = {};

                formModel.reload().then(function () {
                    init(idSiteForm);
                });
                showNotification(translate('FormAnalytics_FormUpdated'), response.type);
            });
        };

        $scope.$watch('idForm', function (newValue, oldValue) {
            if (newValue === null) {
                return;
            }
            if (newValue != oldValue || currentId === null) {
                currentId = newValue;
                init(newValue);
            }
        });
    }
})();