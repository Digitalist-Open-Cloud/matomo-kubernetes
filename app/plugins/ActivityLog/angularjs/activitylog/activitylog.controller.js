/*!
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * All information contained herein is, and remains the property of InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
(function () {
    angular.module('piwikApp').controller('ActivityLogController', ActivityLogController);

    ActivityLogController.$inject = [
        '$scope', 'piwik', 'activityLogModel'
    ];

    function ActivityLogController($scope, piwik, activityLogModel) {

        $scope.cacheBuster = piwik.cacheBuster;
        $scope.hasSuperUserAccess = piwik.hasSuperUserAccess;
        $scope.activityLogModel = activityLogModel;
        activityLogModel.fetchActivityLog();
    }
})();