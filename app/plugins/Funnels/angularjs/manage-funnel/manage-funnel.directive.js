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

/**
 * Usage:
 * <div piwik-manage-funnel>
 */
(function () {
    angular.module('piwikApp').directive('piwikManageFunnel', piwikManageFunnel);

    piwikManageFunnel.$inject = ['piwik', 'piwikApi', '$timeout', '$filter', '$rootScope'];

    function piwikManageFunnel(piwik, piwikApi, $timeout, $filter, $rootScope){

        var translate = $filter('translate');

        function arrayFilter(theArray, filter)
        {
            theArray = angular.copy(theArray);
            var entries = [];

            angular.forEach(theArray, function (value) {
                if (filter(value)) {
                    entries.push(value);
                }
            });

            return entries;
        }

        function getStepsWithPattern(steps)
        {
            if (!steps || !steps.length) {
                return [];
            }

            steps = arrayFilter(steps, function (step) {
                return step && step.pattern && step.pattern_type;
            });

            for (var i = 0; i < steps.length; i++) {
                steps[i].required = steps[i].required ? '1' : '0';
            }

            return steps;
        }

        function getStepsWithNameAndPattern(steps)
        {
            steps = getStepsWithPattern(steps);
            steps = arrayFilter(steps, function (step) {
                return step && step.name;
            });

            return steps;
        }

        function applyScope(scope)
        {
            $timeout(function () {
                scope.$apply();
            }, 1);
        }

        function cannotActivateIncompleteSteps() {
            piwikHelper.modalConfirm('#cannotActivateIncompleteSteps', {});
        }

        function isNumeric(text) {
            return !isNaN(parseFloat(text)) && isFinite(text);
        }

        return {
            restrict: 'A',
            templateUrl: 'plugins/Funnels/angularjs/manage-funnel/manage-funnel.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, controller) {

                    var manageGoals = element.parents('[piwik-manage-goals]');
                    if (manageGoals.length) {
                        var id = manageGoals.attr('show-goal');
                        if (isNumeric(id)) {
                            controller.initGoal('Goals.updateGoal', id);
                        }
                    } else {
                        var id = element.attr('show-goal');
                        if (isNumeric(id)) {
                            controller.initGoal('Goals.updateGoal', id);
                        }
                    }
                };
            },
            controllerAs: 'manageFunnelCtrl',
            controller: function ($scope) {

                var self = this;
                var fetchFunnelPromise = null;
                var validateUrlPromise = null;
                var validateUrlTimeout = null;

                this.isLoading = false;
                this.patternMatchOptions = [];
                this.patternExamples = {};

                piwikApi.fetch({method: 'Funnels.getAvailablePatternMatches'}).then(function (response) {
                    self.patternMatchOptions = response;
                    angular.forEach(response, function (pattern) {
                        self.patternExamples[pattern.key] = 'eg. ' + pattern.example;
                    });
                });

                function doUnlock()
                {
                    self.funnel.isLocked = false;
                    self.funnel.isUnlocked = true;
                }

                function confirmFunnelIsLocked(callback) {
                    piwikHelper.modalConfirm('#infoFunnelIsLocked', {unlock: function () {
                        doUnlock();
                        if (callback) {
                            callback();
                        }
                        applyScope($scope);
                    }});
                }

                this.addStep = function () {
                    if (!this.funnel) {
                        return;
                    }

                    if (this.funnel.isLocked) {
                        confirmFunnelIsLocked(function () {
                            self.addStep();
                        });
                        return;
                    }

                    if (!this.funnel.steps || !this.funnel.steps.length) {
                        this.funnel.steps = [];
                    }

                    this.funnel.steps.push({
                        name: '',
                        pattern: '',
                        pattern_type: 'path_equals',
                        required: !this.funnel.steps.length
                    });  // we require the first step but users can unselect it
                };

                this.removeStep = function (index) {
                    if (!this.funnel) {
                        return;
                    }

                    if (this.funnel.isLocked) {
                        confirmFunnelIsLocked(function () {
                            self.removeStep(index);
                        });
                        return;
                    }

                    if (index > -1 && this.funnel && this.funnel.steps) {
                        this.funnel.steps.splice(index, 1);
                    }

                    this.validateSteps();
                };

                this.prefillValidateUrl = function () {
                    if (!this.validateUrl) {
                        this.validateUrl = 'https://www.';
                    }
                };

                function fetchMatchingSteps()
                {
                    var url = self.validateUrl;

                    if (!url || !self.funnel || !self.funnel.steps || !self.funnel.steps.length) {
                        return;
                    }

                    if (validateUrlPromise) {
                        validateUrlPromise.abort();
                        validateUrlPromise = null;
                    }

                    var steps = getStepsWithPattern(self.funnel.steps);

                    if (!steps || !steps.length) {
                        return;
                    }

                    self.isLoadingMatchingSteps = true;

                    validateUrlPromise = piwikApi.post({method: 'Funnels.testUrlMatchesSteps', url: url}, {steps: steps});
                    validateUrlPromise.then(function (response) {
                        self.isLoadingMatchingSteps = false;

                        if (!self.funnel || !self.funnel.steps) {
                            return;
                        }

                        if (!response || !response.url || !response.tests || response.url != self.validateUrl) {
                            return;
                        }

                        var step, i, j;

                        for (i = 0; i < self.funnel.steps.length; i++) {
                            step = self.funnel.steps[i];
                            for (j = 0; j < response.tests.length; j++) {
                                var test = response.tests[j];

                                // we do not test for step positions as the patterns might have changed and this way
                                // we always show a correct result whether something matches even if value changed
                                // since sending the request
                                if (test
                                    && step.pattern == test.pattern
                                    && step.pattern_type == test.pattern_type) {
                                    self.matches[i] = test.matches ? 'validateMatch' : 'validateMismatch'
                                }
                            }
                        }
                    })['catch'](function (error) {
                        self.isLoadingMatchingSteps = false;
                    });
                }

                this.validateSteps = function () {
                    this.matches = {1: ''};

                    if (!this.funnel || !this.funnel.steps || !this.funnel.steps.length) {
                        return;
                    }

                    for (var i = 0; i < this.funnel.steps.length; i++) {
                        this.matches[i] = 'noValidation';
                    }

                    if (!this.validateUrl) {
                        return;
                    }

                    if (validateUrlTimeout) {
                        $timeout.cancel(validateUrlTimeout);
                        validateUrlTimeout = null;
                    }

                    // we wait for 200ms before actually sending a request as user might be still typing
                    validateUrlTimeout = $timeout(function () {
                        fetchMatchingSteps();
                        validateUrlTimeout = null;
                    }, 200);
                };

                this.unlockFunnel = function () {
                    if (!this.funnel) {
                        return;
                    }

                    if (this.funnel.isLocked) {
                        piwikHelper.modalConfirm('#confirmUnlockFunnel', {yes: function () {
                            doUnlock();
                            applyScope($scope);
                        }});
                    }
                };

                this.toggleFunnelActivated = function () {
                    if (!this.funnel) {
                        return;
                    }

                    if (this.funnel.isLocked) {
                        // undo toggle change from checkbox
                        this.funnel.isActivated = this.funnel.activated;
                        confirmFunnelIsLocked(function () {
                            self.toggleFunnelActivated();
                        });
                        return;
                    }

                    if (this.funnel.activated) {
                        // we can currently not listen to ng-change because it would trigger an endless loop here
                        this.funnel.isActivated = true;

                        piwikHelper.modalConfirm('#confirmDeactivateFunnel', {yes: function () {
                            self.funnel.activated = false;
                            self.funnel.isActivated = false;
                            applyScope($scope);
                        }, no: function () {
                            self.funnel.activated = true;
                            self.funnel.isActivated = true;
                            applyScope($scope);
                        }});
                    } else {
                        this.funnel.isActivated = false;

                        if (!this.funnel.steps
                            || !this.funnel.steps.length) {
                            cannotActivateIncompleteSteps();
                            return;
                        }

                        for (var i in this.funnel.steps) {
                            if (!this.funnel.steps[i].name || !this.funnel.steps[i].pattern) {
                                cannotActivateIncompleteSteps();
                                return;
                            }
                        }

                        self.funnel.activated = true;
                        self.funnel.isActivated = true;
                    }
                };

                this.showHelpForStep = function (index) {
                    var step = null;

                    if (this.funnel && this.funnel.steps && this.funnel.steps[index]) {
                        step = this.funnel.steps[index];
                    }

                    var url = 'module=Funnels&action=stepHelp';

                    if (step && step.pattern && step.pattern_type) {
                        url += '&pattern=' + encodeURIComponent(step.pattern) + '&pattern_type=' + encodeURIComponent(step.pattern_type);
                    }

                    var help = translate('General_Help');

                    Piwik_Popover.createPopupAndLoadUrl(url, help, 'funnelStepHelp');
                };

                this.reset = function () {
                    // we need isActivated for the view handling, and activated for the funnel itself . Because we listen
                    // to ng-change / ng-click it would be confusing otherwise. we try to remove isActivated later
                    this.funnel = {isActivated: false, activated: false, steps: [], isLocked: false, isUnlocked: false};
                    this.matches = {1: ''};
                    this.addStep();
                    this.validateUrl = '';
                };

                this.reset();

                function resetForm() {
                    self.reset();

                    if (fetchFunnelPromise && fetchFunnelPromise.abort) {
                        fetchFunnelPromise.abort();
                        fetchFunnelPromise = null;
                        self.isLoading = false;
                        applyScope($scope);
                    }
                }

                $rootScope.$on('Goals.cancelForm', resetForm);

                function initGoalForm(event, goalMethodAPI, goalId) {

                    resetForm();

                    if (goalId === false || goalId === null || 'undefined' === typeof goalId || goalMethodAPI == 'Goals.addGoal') {
                        return;
                    }

                    self.isLoading = true;

                    fetchFunnelPromise = piwikApi.fetch({method: 'Funnels.getGoalFunnel', idGoal: goalId});
                    fetchFunnelPromise.then(function (response) {
                        self.isLoading = false;

                        if (fetchFunnelPromise && response) {
                            self.funnel = response;
                            if (!self.funnel.steps) {
                                self.funnel.steps = [];
                                self.addStep();
                            }

                            self.funnel.activated = (self.funnel.activated && self.funnel.activated !== '0');
                            self.funnel.isActivated = self.funnel.activated;

                            for (var i = 0; i < self.funnel.steps.length; i++) {
                                self.funnel.steps[i].required = (self.funnel.steps[i].required && self.funnel.steps[i].required !== '0');
                            }

                            if (self.funnel.activated) {
                                self.funnel.isLocked = true;
                                // we only allow save once user has confirmed to deactivate the funnel
                            } else {
                                self.funnel.isLocked = false;
                            }

                            self.validateSteps();
                        }
                        fetchFunnelPromise = null;
                        applyScope($scope);
                    })['catch'](function (error) {
                        self.isLoading = false;
                    });
                }

                $rootScope.$on('Goals.beforeInitGoalForm', initGoalForm);

                function onSetFunnel (event, parameters, piwikApi) {
                    if (!self.funnel || self.funnel.isLocked) {
                        return;
                    }

                    var steps = getStepsWithNameAndPattern(self.funnel.steps);

                    var isActivated = self.funnel.activated ? '1' : '0';

                    piwikApi.addPostParams({funnelSteps: steps, funnelActivated: isActivated});
                }

                function onSetFunnelDirect (event, parameters, piwikApi) {
                    if (!self.funnel || self.funnel.isLocked) {
                        parameters.isLocked = true;
                        return;
                    }

                    var steps = getStepsWithNameAndPattern(self.funnel.steps);

                    var isActivated = self.funnel.activated ? '1' : '0';

                    piwikApi.addPostParams({steps: steps, isActivated: isActivated});
                }

                $rootScope.$on('Goals.beforeAddGoal', onSetFunnel);
                $rootScope.$on('Goals.beforeUpdateGoal', onSetFunnel);
                $rootScope.$on('Funnels.beforeUpdateFunnel', onSetFunnelDirect);

                // eg when appending idGoal=$ID a goal will be edited directly. The event "Goals.beforeInitGoalForm" will
                // be posted before this controller is initialized, therefore need to have possibility to load goal
                // directly
                this.initGoal = function (method, idGoal) {
                    initGoalForm({}, method, idGoal);
                };
            }
        };
    }
})();