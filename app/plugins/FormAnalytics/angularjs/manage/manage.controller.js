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
    angular.module('piwikApp').controller('ManageFormsController', ManageFormsController);

    ManageFormsController.$inject = ['$scope', '$rootScope', '$location'];

    function ManageFormsController($scope, $rootScope, $location) {

        this.editMode = false;

        var self = this;

        function removeAnyFormNotification()
        {
            var UI = require('piwik/UI');
            new UI.Notification().remove('formsmanagement');
        }

        function initState() {
            var $search = $location.search();
            if ('idForm' in $search) {
                if ($search.idForm === 0 || $search.idForm === '0') {

                    var parameters = {isAllowed: true};
                    $rootScope.$emit('FormAnalytics.initAddForm', parameters);
                    if (parameters && !parameters.isAllowed) {

                        self.editMode = false;
                        self.idForm = null;

                        return;
                    }
                }
                self.editMode = true;
                self.idForm = parseInt($search.idForm, 10);
            } else {
                self.editMode = false;
                self.idForm = null;
            }

            removeAnyFormNotification();
        }

        initState();

        var onChangeSuccess = $rootScope.$on('$locationChangeSuccess', initState);

        $scope.$on('$destroy', function() {
            if (onChangeSuccess) {
                onChangeSuccess();
            }
        });
    }
})();
