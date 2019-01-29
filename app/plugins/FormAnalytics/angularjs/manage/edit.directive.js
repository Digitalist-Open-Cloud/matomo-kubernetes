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
 * <div piwik-form-edit>
 */
(function () {
    angular.module('piwikApp').directive('piwikFormEdit', piwikFormEdit);

    piwikFormEdit.$inject = ['piwik'];

    function piwikFormEdit(piwik){

        return {
            restrict: 'A',
            scope: {
                idForm: '=',
            },
            templateUrl: 'plugins/FormAnalytics/angularjs/manage/edit.directive.html?cb=' + piwik.cacheBuster,
            controller: 'FormEditController',
            controllerAs: 'editForm'
        };
    }
})();