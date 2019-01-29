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
    angular.module('piwikApp').controller('SalesFunnelController', SalesFunnelController);

    SalesFunnelController.$inject = ['piwik', 'piwikApi', '$timeout', '$location', '$rootScope'];

    function SalesFunnelController(piwik, piwikApi, $timeout, $location, $rootScope) {

        var self = this;
        this.isLoading = false;

        this.openFunnelReport = function (reportId) {
            var $search = $location.search();
            $search.category = 'Funnels_Funnels';
            $search.subcategory = encodeURIComponent(reportId);
            $location.search($search);
        }

        this.save = function () {

            var parameters = {
                method: 'Funnels.setGoalFunnel',
                idGoal: '0'
            };
            $rootScope.$emit('Funnels.beforeUpdateFunnel', parameters, piwikApi);

            if (parameters && 'isLocked' in parameters && parameters.isLocked) {
                piwikHelper.modalConfirm('#funnelIsLockedCannotBeSaved', {});
                return;
            }

            this.isLoading = true;

            piwikApi.fetch(parameters).then(function () {
                location.reload();
            }, function () {
                self.isLoading = false;
            });
        };
    }
})();