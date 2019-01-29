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
    angular.module('piwikApp').factory('reportModel', reportsModel);

    reportsModel.$inject = ['piwikApi', '$q', 'piwik'];

    function reportsModel(piwikApi, $q, piwik) {
        var fetchPromise = {};
        var availableReportTypesPromise = null;
        var dimensionsPromise = null;
        var metricsPromise = null;

        var dimensionsIdsiteLoaded = null;
        var metricsIdsiteLoaded = null;

        var model = {
            reports : [],
            reportTypesReadable: {},
            dimensionsReadable: {},
            metricsReadable: {},
            isLoading: false,
            isUpdating: false,
            findReport: findReport,
            createOrUpdateReport: createOrUpdateReport,
            deleteReport: deleteReport,
            reload: reload,
            fetchReports: fetchReports,
            getAvailableDimensions: getAvailableDimensions,
            getAvailableReportTypes: getAvailableReportTypes,
            getAvailableMetrics: getAvailableMetrics,
            getAvailableCategories: getAvailableCategories,
            arrayFilter: arrayFilterAndRemoveDuplicates
        };

        return model;

        function reload()
        {
            model.reports = [];
            fetchPromise = {};
            return fetchReports();
        }

        function arrayFilterAndRemoveDuplicates(array, filter)
        {
            var entries = [];

            if (!filter) {
                filter = function (value) { return !!value; };
            }

            angular.forEach(array, function (value) {
                if (filter(value) && entries.indexOf(value) === -1) {
                    entries.push(value);
                }
            });

            return entries;
        }

        function cleanupSegmentDefinition(definition) {
            definition = definition.replace("'", "%27");
            definition = definition.replace("&", "%26");
            return definition;
        };

        function getAvailableDimensions(idsite) {
            if (!dimensionsPromise || dimensionsIdsiteLoaded !== idsite) {
                dimensionsIdsiteLoaded = idsite;
                dimensionsPromise = piwikApi.fetch({method: 'CustomReports.getAvailableDimensions',filter_limit: '-1',idSite: idsite});
                dimensionsPromise.then(function (dimensions) {
                    model.dimensionsReadable = {};
                    angular.forEach(dimensions, function (dimensionCategory) {
                        angular.forEach(dimensionCategory.dimensions, function (dimension) {
                            model.dimensionsReadable[dimension.uniqueId] = dimension.name;
                        });
                    });
                    return dimensions;
                });
            }
            return dimensionsPromise;
        }

        function getAvailableReportTypes() {
            if (!availableReportTypesPromise) {
                availableReportTypesPromise = piwikApi.fetch({method: 'CustomReports.getAvailableReportTypes',filter_limit: '-1'});
                availableReportTypesPromise.then(function (reportTypes) {
                    model.reportTypesReadable = {};
                    angular.forEach(reportTypes, function (reportType) {
                        model.reportTypesReadable[reportType.key] = reportType.value;
                    });
                    return reportTypes;
                });
            }
            return availableReportTypesPromise;
        }

        function getAvailableMetrics(idsite) {
            if (!metricsPromise || metricsIdsiteLoaded !== idsite) {
                metricsIdsiteLoaded = idsite;
                metricsPromise = piwikApi.fetch({method: 'CustomReports.getAvailableMetrics',filter_limit: '-1', idSite: idsite});
                metricsPromise.then(function (metrics) {
                    model.metricsReadable = {};
                    angular.forEach(metrics, function (metricsCategory) {
                        angular.forEach(metricsCategory.metrics, function (metric) {
                            model.metricsReadable[metric.uniqueId] = metric.name;
                        });
                    });
                    return metrics;
                });
            }
            return metricsPromise;
        }

        function getAvailableCategories(idsite) {
            if (!idsite || idsite === 'all') {
                idsite = piwik.idSite;
            }
            return piwikApi.fetch({method: 'CustomReports.getAvailableCategories',filter_limit: '-1', idSite: idsite});
        }

        function fetchReports() {

            var params = {method: 'CustomReports.getConfiguredReports',filter_limit: '-1'};
            var key = params.method + params.statuses;

            if (!fetchPromise[key]) {
                fetchPromise[key] = piwikApi.fetch(params);
            }

            model.isLoading = true;
            model.reports = [];

            return fetchPromise[key].then(function (reports) {
                angular.forEach(reports, function (report) {
                    if (report && report.subcategory && report.subcategory.id) {
                        report.subcategoryLink = report.subcategory.id;
                    } else if (report && report.category && report.category.id && report.category.id === 'CustomReports_CustomReports') {
                        report.subcategoryLink = report.idcustomreport;
                    } else {
                        report.subcategoryLink = report.name;
                    }
                });

                model.reports = reports;
                model.isLoading = false;
                return reports;
            }, function () {
                model.isLoading = false;
            });
        }

        function findReport(idCustomReport) {

            // before going through an API request we first try to find it in loaded reports
            var found;
            angular.forEach(model.reports, function (report) {
                if (parseInt(report.idcustomreport, 10) === idCustomReport) {
                    found = report;
                }
            });

            if (found) {
                var deferred = $q.defer();
                deferred.resolve(found);
                return deferred.promise;
            }

            // otherwise we fetch it via API
            model.isLoading = true;

            return piwikApi.fetch({
                idCustomReport: idCustomReport,
                method: 'CustomReports.getConfiguredReport'
            }).then(function (report) {
                model.isLoading = false;
                return report;

            }, function (error) {
                model.isLoading = false;
            });
        }

        function deleteReport(idCustomReport, idSite) {

            model.isUpdating = true;
            model.reports = [];

            piwikApi.withTokenInUrl();

            return piwikApi.fetch({idCustomReport: idCustomReport, idSite: idSite, method: 'CustomReports.deleteCustomReport'}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success'};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function createOrUpdateReport(report, method) {
            report = angular.copy(report);
            report.method = method;

            var map = {
                idCustomReport: 'idcustomreport',
                reportType: 'report_type',
                segmentFilter: 'segment_filter'
            };

            angular.forEach(map, function (value, key) {
                if (typeof report[value] !== 'undefined') {
                    report[key] = report[value];
                    delete report[value];
                }
            });

            angular.forEach(['name', 'description'], function (param) {
                if (report[param]) {
                    // trim values
                    report[param] = report[param].replace(/^\s+|\s+$/g, '');
                }
            });

            report.dimensionIds = arrayFilterAndRemoveDuplicates(report.dimensions);
            report.metricIds = arrayFilterAndRemoveDuplicates(report.metrics);

            if (report.segmentFilter) {
                report.segmentFilter = encodeURIComponent(report.segmentFilter);
            }

            if (report.category && report.category.id) {
                report.categoryId = report.category.id;
            }
            if (report.subcategory && report.subcategory.id) {
                report.subcategoryId = report.subcategory.id;
            }

            delete report.dimensions;
            delete report.metrics;
            delete report.category;
            delete report.subcategory;
            delete report.initial_dimensions;
            delete report.initial_metrics;
            delete report.initial_report_type;
            delete report.initial_segment_filter;
            delete report.idsite;

            report.idSite = report.site.id;
            delete report.site;

            var postParams = ['dimensionIds', 'metricIds'];
            var post = {};
            for (var i = 0; i < postParams.length; i++) {
                var postParam = postParams[i];
                if (typeof report[postParam] !== 'undefined') {
                    post[postParam] = report[postParam];
                    delete report[postParam];
                }
            }

            model.isUpdating = true;

            piwikApi.withTokenInUrl();

            return piwikApi.post(report, post).then(function (response) {
                model.isUpdating = false;

                return {type: 'success', response: response};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

    }
})();