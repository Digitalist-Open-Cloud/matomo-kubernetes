/*!
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
 * <div piwik-manage-roll-up>
 */
(function () {
    angular.module('piwikApp').directive('piwikManageRollUp', piwikManageRollUp);

    piwikManageRollUp.$inject = ['piwik'];

    function piwikManageRollUp(piwik){

        return {
            restrict: 'A',
            require: '?ngModel',
            scope: {},
            templateUrl: 'plugins/RollUpReporting/angularjs/manage-roll-up/manage-roll-up.directive.html?cb=' + piwik.cacheBuster,
            controller: 'ManageRollUpController',
            controllerAs: 'manageRollUp',
            link: function(scope, elm, attrs, ctrl) {
                if (!ctrl) {
                    return;
                }

                // view -> model
                scope.$watch('manageRollUp.siteIds', function (val, oldVal) {
                    if (val !== oldVal && val !== ctrl.$viewValue) {
                        ctrl.$setViewValue(val);
                    }
                });

                // model -> view
                ctrl.$render = function() {
                    scope.manageRollUp.siteIds = ctrl.$viewValue;
                };

            }
        };
    }
})();