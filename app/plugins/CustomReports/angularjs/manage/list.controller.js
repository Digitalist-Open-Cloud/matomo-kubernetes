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
    angular.module('piwikApp').controller('ReportsListController', ReportsListController);

    ReportsListController.$inject = ['$scope', 'reportModel', 'piwik', 'piwikApi', '$location', '$rootScope'];

    function ReportsListController($scope, reportModel, piwik, piwikApi, $location, $rootScope) {

        this.model = reportModel;
        this.isSuperUser = piwik.hasSuperUserAccess;

        var self = this;

        reportModel.getAvailableReportTypes();

        this.createReport = function () {
            this.editReport(0);
        };

        this.editReport = function (idCustomReport) {
            var $search = $location.search();
            $search.idCustomReport = idCustomReport;
            $location.search($search);
        };

        this.deleteReport = function (report) {
            function doDelete() {
                reportModel.deleteReport(report.idcustomreport, report.idsite).then(function () {
                    reportModel.reload();

                    $rootScope.$emit('updateReportingMenu');
                });
            }

            piwik.helper.modalConfirm('#confirmDeleteReport', {yes: doDelete});
        };

        this.model.fetchReports();
    }
})();