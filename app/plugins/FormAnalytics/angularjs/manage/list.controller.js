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
    angular.module('piwikApp').controller('FormsListController', FormsListController);

    FormsListController.$inject = ['$scope', 'formModel', 'piwik', 'piwikApi', '$location', '$rootScope'];

    function FormsListController($scope, formModel, piwik, piwikApi, $location, $rootScope) {

        this.model = formModel;
        this.autoCreationMessage = '';

        var self = this;
        piwikApi.fetch({method: 'FormAnalytics.getAutoCreationSettings'}).then(function (response) {
            if (response && response.message) {
                self.autoCreationMessage = response.message;
            }
        });

        formModel.fetchAvailableStatuses().then(function (statuses) {
            self.statusOptions = [];
            if (statuses && statuses.length) {
                for (var i = 0; i < statuses.length; i++) {
                    if (statuses[i].value === 'deleted') {
                        continue;
                    }
                    self.statusOptions.push({key: statuses[i].value, value: statuses[i].name});
                }
            }
        });

        this.createForm = function () {
            this.editForm(0);
        };

        this.editForm = function (idForm) {
            var $search = $location.search();
            $search.idForm = idForm;
            $location.search($search);
        };

        this.deleteForm = function (form) {
            function doDelete() {
                formModel.deleteForm(form.idsiteform).then(function () {
                    formModel.reload();

                    $rootScope.$emit('updateReportingMenu');
                });
            }

            piwik.helper.modalConfirm('#confirmDeleteForm', {yes: doDelete});
        };

        this.archiveForm = function (form) {
            function doArchive() {
                formModel.archiveForm(form.idsiteform).then(function () {
                    formModel.reload();

                    $rootScope.$emit('updateReportingMenu');
                });
            }

            piwik.helper.modalConfirm('#confirmArchiveForm', {yes: doArchive});
        };

        this.onFilterStatusChange = function () {
            this.model.fetchForms();
        };

        this.onFilterStatusChange();
    }
})();