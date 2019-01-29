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
 * Controller to change form fields
 */
(function () {
    angular.module('piwikApp').controller('FormFieldsController', FormFieldsController);

    FormFieldsController.$inject = ['$scope', 'piwik', 'piwikApi'];

    function FormFieldsController($scope, piwik, piwikApi) {

        var self = this;
        this.isLoading = false;
        this.names = {};

        this.renameFields = function (idForm) {

            this.isLoading = true;

            var fields = [];
            angular.forEach(angular.copy(this.names), function (displayName, name) {
                fields.push({name: name, displayName: displayName});
            });

            piwikApi.post({module: 'API', method: 'FormAnalytics.updateFormFieldDisplayName'}, {
                fields: fields,
                idForm: parseInt(idForm, 10)
            }).then(function (success) {
                piwik.helper.redirect();
            }, function () {
                self.isLoading = false;
            });
        };
    }
})();