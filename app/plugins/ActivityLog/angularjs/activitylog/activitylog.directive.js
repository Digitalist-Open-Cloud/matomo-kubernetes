/*!
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * All information contained herein is, and remains the property of InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
(function () {
    angular.module('piwikApp').directive('activityItem', activityItem);

    activityItem.$inject = ['piwik'];

    function activityItem(piwik) {

        return {
            restrict: 'A',
            transclude: true,
            scope: {
                item: '=activityItem'
            },
            link: function ($scope) {
                $scope.dynamicTemplateUrl = 'plugins/ActivityLog/angularjs/activitylog/item-' + $scope.item.type + '.html?cb=' + piwik.cacheBuster
            },
            template: function (element, attrs) {
                return '<div ng-include="dynamicTemplateUrl"></div>'
            }
        };
    }
})();