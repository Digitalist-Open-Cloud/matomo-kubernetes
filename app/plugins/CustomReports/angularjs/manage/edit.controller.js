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
    angular.module('piwikApp').controller('ReportEditController', ReportEditController);

    ReportEditController.$inject = ['$scope', 'reportModel', 'piwik', '$location', '$filter', '$timeout', '$rootScope'];

    function ReportEditController($scope, reportModel, piwik, $location, $filter, $timeout, $rootScope) {

        var self = this;
        var currentId = null;
        var notificationId = 'reportsmanagement';

        var translate = $filter('translate');

        this.isSuperUser = piwik.hasSuperUserAccess;
        this.isDirty = false;
        this.model = reportModel;

        this.metrics = [];
        this.allMetrics = [];
        this.dimensions = [];
        this.allDimensions = [];
        this.reportTypes = [];
        this.categories = [];
        this.subcategories = {};
        this.report = {canEdit: true};
        this.editTitle = 'CustomReports_CreateNewReport';

        function formatExpandableList(listByCategories, subcategoryField, extraField)
        {
            var list = [];
            if (angular.isArray(listByCategories)) {
                angular.forEach(listByCategories, function (category) {
                    angular.forEach(category[subcategoryField], function (value) {
                        var item = {group: category.category, key: value.uniqueId, value: value.name};
                        if (value.description) {
                            item.tooltip = value.description;
                        }
                        if (extraField && value[extraField]) {
                            item[extraField] = value[extraField];
                        }
                        list.push(item);
                    });
                });
            }
            return list;
        }

        reportModel.getAvailableReportTypes().then(function (reportTypes) {
            self.reportTypes = reportTypes;
        });

        function initReportOptions()
        {
            var idsite = self.report.site.id;
            if (!idsite) {
                idsite = 'all';
            }

            reportModel.getAvailableDimensions(idsite).then(function (dimensions) {
                self.allDimensions = formatExpandableList(dimensions, 'dimensions', 'sqlSegment');
                self.updateSelectableDimensions();
            });

            reportModel.getAvailableMetrics(idsite).then(function (metrics) {
                self.allMetrics = formatExpandableList(metrics, 'metrics');
                self.metrics = angular.copy(self.allMetrics);
            });

            reportModel.getAvailableCategories(idsite).then(function (categories) {
                angular.forEach(categories, function (category) {
                    category.key = category.uniqueId;
                    category.value = category.name;

                    angular.forEach(category.subcategories, function (subcategory) {
                        subcategory.key = subcategory.uniqueId;
                        subcategory.value = subcategory.name;
                    });
                });

                self.categories = categories;
                self.subcategories = {};
                angular.forEach(categories, function (category) {
                    self.subcategories[category.uniqueId] = category.subcategories;
                });
            });

        }

        function applyScope(scope)
        {
            $timeout(function () {
                scope.$apply();
            }, 1);
        }

        function doUnlock()
        {
            self.report.isLocked = false;
            self.report.isUnlocked = true;
        }

        function confirmReportIsLocked(callback) {
            piwikHelper.modalConfirm('#infoReportIsLocked', {unlock: function () {
                doUnlock();
                if (callback) {
                    callback();
                }
                applyScope($scope);
            }});
        }

        function getNotification()
        {
            var UI = require('piwik/UI');
            return new UI.Notification();
        }

        function removeAnyReportNotification()
        {
            var notification = getNotification();
            notification.remove(notificationId);
            notification.remove('ajaxHelper');
        }

        function showNotification(message, context)
        {
            var notification = getNotification();
            notification.show(message, {context: context, id: notificationId});
            $timeout(function () {
                notification.scrollToNotification();
            }, 100);
        }

        function showErrorFieldNotProvidedNotification(title)
        {
            var message = _pk_translate('CustomReports_ErrorXNotProvided', [title]);
            showNotification(message, 'error');
        }

        function init(idCustomReport)
        {
            self.create = idCustomReport == '0';
            self.edit   = !self.create;
            self.report = {canEdit: true};

            piwik.helper.lazyScrollToContent();

            if (self.edit && idCustomReport) {
                self.editTitle = 'CustomReports_EditReport';
                self.model.findReport(idCustomReport).then(function (report) {
                    if (!report) {
                        return;
                    }
                    self.report = angular.copy(report);

                    self.report.isLocked = true;
                    self.report.isUnlocked = false;

                    if (!self.report.segment_filter) {
                        self.report.segment_filter = '';
                    }

                    // needed for smooth unlocking meachinsm so we can undo changed values
                    self.report.initial_segment_filter = self.report.segment_filter;
                    self.report.initial_report_type = self.report.report_type;
                    self.report.initial_report_type = self.report.report_type;
                    self.report.initial_dimensions = angular.copy(self.report.dimensions);
                    self.report.initial_metrics = angular.copy(self.report.metrics);
                    self.report.canEdit = true;

                    var idSite = self.report.idsite;
                    if (idSite === 0 || idSite === '0' || idSite === 'all') {
                        idSite = 'all'; // we need to make sure to send 'all' and not '0' as otherwise piwikApi would
                        // consider 0 as no value set and replace it with the current idsite. Also the site selector
                        // expects us to set 'all' instead of 0
                        if (!self.isSuperUser) {
                            self.report.canEdit = false;
                            // a lock does not make sense because report cannot be changed anyway. we do not want to show a warning
                            // related to this in such a case
                            self.report.isLocked = false;
                        }
                    }

                    self.report.site = {id: idSite, name: self.report.site.name};

                    self.isDirty = false;
                    self.updateSelectableDimensions();
                    initReportOptions();
                });
            } else if (self.create) {
                self.editTitle = 'CustomReports_CreateNewReport';
                self.report = {
                    idSite: piwik.idSite,
                    site: {id: piwik.idSite, name: piwik.siteName},
                    name: '',
                    description: '',
                    dimensions: [],
                    initial_dimensions: [],
                    metrics: ['nb_uniq_visitors'],
                    initial_metrics: ['nb_uniq_visitors'],
                    report_type: 'table',
                    category: {id: 'CustomReports_CustomReports'},
                    subcategory: null,
                    segment_filter: '',
                    isLocked: false,
                    isUnlocked: false,
                    canEdit: true
                };
                self.isDirty = false;
                self.updateSelectableDimensions();
                initReportOptions();
            }
        }

        this.cancel = function () {
            $scope.idCustomReport = null;
            currentId = null;

            var $search = $location.search();
            delete $search.idCustomReport;
            $location.search($search);
        };

        this.unlockReport = function () {
            if (!this.report) {
                return;
            }

            if (this.report.isLocked) {
                piwikHelper.modalConfirm('#confirmUnlockReport', {yes: function () {
                    doUnlock();
                    applyScope($scope);
                }});
            }
        };

        function checkRequiredFieldsAreSet()
        {
            var title;

            if (!self.report.name) {
                title = _pk_translate('General_Name');
                showErrorFieldNotProvidedNotification(title);
                return false;
            }

            if (self.report.report_type !== 'evolution') {
                if (!self.report.dimensions || !self.report.dimensions.length || !reportModel.arrayFilter(self.report.dimensions).length) {
                    title = _pk_translate('CustomReports_ErrorMissingDimension');
                    showNotification(title, 'error');
                    return false;
                }
            }

            if (!self.report.metrics || !self.report.metrics.length || !reportModel.arrayFilter(self.report.metrics).length) {
                title = _pk_translate('CustomReports_ErrorMissingMetric');
                showNotification(title, 'error');
                return false;
            }

            return true;
        }

        this.createReport = function () {
            var method = 'CustomReports.addCustomReport';

            removeAnyReportNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            this.isUpdating = true;

            reportModel.createOrUpdateReport(this.report, method).then(function (response) {
                self.isUpdating = false;

                if (!response || response.type === 'error' || !response.response) {
                    return;
                }

                self.isDirty = false;

                var idCustomReport = response.response.value;

                if (self.report.site) {
                    var idSite = self.report.site.id;

                    if (idSite && idSite !== 'all' && idSite != piwik.idSite) {
                        // when creating a report for a different site...
                        // we need to reload this page for a different idsite, otherwise the report won't be found
                        piwik.broadcast.propagateNewPage('idSite=' + encodeURIComponent(idSite) + '&idCustomReport=' + encodeURIComponent(idCustomReport));
                        return;
                    }
                }

                reportModel.reload().then(function () {
                    if (piwik.helper.isAngularRenderingThePage()) {
                        $rootScope.$emit('updateReportingMenu');
                        var $search = $location.search();
                        $search.idCustomReport = idCustomReport;
                        $location.search($search);
                    } else {
                        $location.url('/?idCustomReport=' + encodeURIComponent(idCustomReport));
                    }

                    $timeout(function () {
                        showNotification(translate('CustomReports_ReportCreated'), response.type);
                    }, 200);
                });
            }, function () {
                self.isUpdating = false;
            });
        };

        this.showPreview = function () {
            var url = 'module=CustomReports&action=previewReport&period=day&date=today';

            if (this.report.site && this.report.site.id && this.report.site.id !== 'all') {
                url += '&idSite=' + encodeURIComponent(this.report.site.id);
            } else {
                url += '&idSite=' + encodeURIComponent(piwik.idSite);
            }

            url += '&report_type=' + encodeURIComponent(this.report.report_type);

            if (this.report.dimensions && this.report.dimensions.length && this.report.report_type && this.report.report_type !== 'evolution') {
                url += '&dimensions=' + encodeURIComponent(this.report.dimensions.join(','));
            }

            if (this.report.metrics && this.report.metrics.length) {
                url += '&metrics=' + encodeURIComponent(this.report.metrics.join(','));
            }

            if (this.report.segment_filter) {
                url += '&segment=' + encodeURIComponent(this.report.segment_filter);
            }

            var title = translate('CustomReports_Preview');

            Piwik_Popover.createPopupAndLoadUrl(url, title, 'customReportPreview');
        }

        this.setValueHasChanged = function () {
            this.isDirty = true;
        };

        this.addDimension = function (dimension) {
            if (!this.report || !dimension) {
                return;
            }

            if (this.report.isLocked) {
                confirmReportIsLocked(function () {
                    self.addDimension(dimension);
                });
                return;
            }

            if (!this.report.dimensions) {
                this.report.dimensions = [];
            }

            this.report.dimensions.push(dimension);

            this.setDimensionsChanged();
        };

        this.changeDimension = function (dimension, index) {
            if (!this.report || !dimension) {
                return;
            }

            if (this.report.isLocked) {
                confirmReportIsLocked(function () {
                    self.changeDimension(dimension, index);
                });
                return;
            }

            if (!this.report.dimensions || !(index in this.report.dimensions)) {
                return;
            }

            this.report.dimensions[index] = dimension;

            this.setDimensionsChanged();
        };

        this.changeMetric = function (metric, index) {
            if (!this.report || !metric) {
                return;
            }

            if (this.report.isLocked) {
                confirmReportIsLocked(function () {
                    self.changeMetric(metric, index);
                });
                return;
            }

            if (!this.report.metrics || !(index in this.report.metrics)) {
                return;
            }

            this.report.metrics[index] = metric;

            this.setMetricsChanged();
        };

        this.setWebsiteChanged = function () {
            this.setValueHasChanged();
            initReportOptions();
        };

        this.setDimensionsChanged = function () {
            this.setValueHasChanged();
            this.updateSelectableDimensions();
        };

        this.updateSelectableDimensions = function () {
            this.dimensions = angular.copy(this.allDimensions);

            if (!this.report || !this.report.dimensions || !this.report.dimensions.length) {
                return;
            }

            this.report.initial_dimensions = angular.copy(this.report.dimensions);

            var usedSqlSegments = [];
            for (i = 0; i < this.report.dimensions.length; i++) {
                for (var j = 0; j < this.dimensions.length; j++) {
                    if (this.dimensions[j].key === this.report.dimensions[i] && this.dimensions[j].sqlSegment) {
                        usedSqlSegments.push(this.dimensions[j].sqlSegment);
                        // we do not allow to select eg grouping by "Page URL, Clicked URL" as it wouldn't show any data
                    }
                }
            }

            var j;
            // make sure these dimensions cannot be selected a second time
            for (var j = 0; j < this.dimensions.length; j++) {
                var dim = this.dimensions[j];
                if (dim.sqlSegment
                    && usedSqlSegments.indexOf(dim.sqlSegment) > -1
                    && this.report.dimensions.indexOf(dim.key) === -1) {
                    // we want to make sure to not show incompatible dimensions but we still want to show an already
                    // selected dimension again so users can eg easily swap dimensions etc.
                    this.dimensions.splice(j, 1);
                    j--;
                }
            }
        };

        this.setMetricsChanged = function () {
            this.setValueHasChanged();
        };

        this.removeDimension = function (dimension) {

            if (this.report.isLocked) {
                confirmReportIsLocked(function () {
                    self.removeDimension(dimension);
                });
                return;
            }

            var index = this.report.dimensions.indexOf(dimension);
            if (index > -1) {
                this.report.dimensions.splice(index, 1);
                this.setDimensionsChanged();
            }
        };

        this.addMetric = function (metric) {
            if (!this.report || !metric) {
                return;
            }

            if (!this.report.metrics) {
                this.report.metrics = [];
            }

            if (this.report.isLocked) {
                confirmReportIsLocked(function () {
                    self.addMetric(metric);
                });
                return;
            }

            this.report.metrics.push(metric);
            this.setMetricsChanged();
        };

        this.removeMetric = function (metric) {
            if (this.report.isLocked) {
                confirmReportIsLocked(function () {
                    self.removeMetric(metric);
                });
                return;
            }

            var index = this.report.metrics.indexOf(metric);
            if (index > -1) {

                this.report.metrics.splice(index, 1);
                this.setMetricsChanged();
            }
        };

        this.setReportTypeHasChanged = function () {
            if (this.report && this.report.isLocked) {
                var reportTypeToSet = this.report.report_type;

                if (reportTypeToSet !== this.report.initial_report_type) {
                    this.report.report_type = this.report.initial_report_type;

                    confirmReportIsLocked(function () {
                        self.report.report_type = reportTypeToSet;
                        self.setValueHasChanged();
                    });
                }
            } else {
                this.setValueHasChanged();
            }
        };

        this.setSegmentFilterHasChanged = function () {
            if (this.report && this.report.isLocked) {
                var segmentFilterToSet = this.report.segment_filter;

                if (segmentFilterToSet !== this.report.initial_segment_filter) {
                    this.report.segment_filter = this.report.initial_segment_filter;

                    confirmReportIsLocked(function () {
                        self.report.segment_filter = segmentFilterToSet;
                        self.setValueHasChanged();
                    });
                }
            } else {
                this.setValueHasChanged();
            }
        };

        this.updateReport = function () {

            removeAnyReportNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            var method = 'CustomReports.updateCustomReport';

            this.isUpdating = true;

            reportModel.createOrUpdateReport(self.report, method).then(function (response) {
                if (!response || response.type === 'error') {
                    return;
                }

                var idCustomReport = self.report.idcustomreport;
                var idSite = self.report.site.id;

                self.isDirty = false;
                self.report = {canEdit: true};

                if (idSite && idSite !== 'all' && idSite != piwik.idSite) {
                    // when moving a report from one site to another...
                    // we need to reload this page for a different idsite, otherwise the report won't be found
                    piwik.broadcast.propagateNewPage('idSite=' + encodeURIComponent(idSite));
                    return;
                }

                reportModel.reload().then(function () {
                    init(idCustomReport);
                });

                showNotification(translate('CustomReports_ReportUpdated'), response.type);
            });
        };

        $rootScope.$on('piwikPageChange', function () {
            var $search = $location.search();
            if ('idCustomReport' in $search) {
                init($search.idCustomReport);
            }
        });

        $scope.$watch('idCustomReport', function (newValue, oldValue) {
            if (newValue === null) {
                return;
            }
            if (newValue != oldValue || currentId === null) {
                currentId = newValue;
                init(newValue);
            }
        });
    }
})();