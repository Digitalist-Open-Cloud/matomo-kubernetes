/*!
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
    angular.module('piwikApp').controller('ManageRollUpController', ManageRollUpController);

    ManageRollUpController.$inject = ['$scope', 'piwikApi', 'piwik', 'siteSelectorModel', '$filter'];

    function ManageRollUpController($scope, piwikApi, piwik, siteSelectorModel, $filter) {
        siteSelectorModel.loadInitialSites();

        var KEY_NO_SITE_DEFINED = 'nositedefined';

        var allSitesPromise = piwikApi.fetch({method: 'SitesManager.getSitesWithAdminAccess', filter_limit: -1});

        var self = this;
        var translate = $filter('translate');

        this.showAllSites = false;

        this.initPropertiesIfNeeded = function () {
            if ('undefined' === typeof this.sites) {
                this.sites = [];
            }

            if ('undefined' === typeof this.siteIds) {
                this.siteIds = [KEY_NO_SITE_DEFINED];
            }
        }

        this.addSite = function (site) {
            this.initPropertiesIfNeeded();

            if (this.showAllSites) {
                return;
            }

            if (site && site.id) {
                if (site.id === 'all') {
                    this.siteIds = [KEY_NO_SITE_DEFINED];
                    this.showAllSites = true;
                }

                // we only add the site id if it was not added before
                if (!this.isSiteIncludedAlready(site.id)) {
                    this.siteIds.push(site.id);

                    this.updateSites();
                }
            }
        };

        this.isSiteIncludedAlready = function (idSite) {
            return this.siteIds && this.siteIds.length && -1 !== this.siteIds.indexOf(idSite);
        };

        this.removeSite = function (site) {
            this.initPropertiesIfNeeded();

            var index = this.siteIds.indexOf(site.id);
            var index2 = this.sites.indexOf(site);

            if (index > -1) {
                this.siteIds.splice(index, 1);
                if (this.siteIds.length === 0 || (this.siteIds.length === 1 && this.hasKeyDefined())) {
                    this.showAllSites = false;
                    this.siteIds = [KEY_NO_SITE_DEFINED];
                }
            }

            if (index2 > -1) {
                this.sites.splice(index2, 1);
            }
        };

        this.addSitesContaining = function (searchTerm) {
            if (!searchTerm) {
                return;
            }

            var displaySearchTerm = '"' + piwik.helper.escape(piwik.helper.htmlEntities(searchTerm)) + '"';

            var params = {method: 'SitesManager.getSitesWithAdminAccess', pattern: searchTerm, filter_limit: -1};
            piwikApi.fetch(params).then(function (sites) {
                if (!sites || !sites.length) {
                    var sitesToAdd = '<div><h2>' + translate('RollUpReporting_MatchingSearchNotFound', displaySearchTerm) + '</h2><input role="ok" type="button" value="' + translate('General_Ok')+ '"/></div>';
                    piwik.helper.modalConfirm(sitesToAdd, {ok: function () {}});
                    return;
                }

                var newSites = [];
                var alreadyAddedSites = [];

                angular.forEach(sites, function (site, index) {
                    var siteTitle = piwik.helper.escape(piwik.helper.htmlEntities(site.name)) + ' (id ' + parseInt(site.idsite, 10) + ')<br />';
                    if (self.isSiteIncludedAlready(site.idsite)) {
                        alreadyAddedSites.push(siteTitle);
                    } else {
                        newSites.push(siteTitle);
                    }
                });

                var title = translate('RollUpReporting_MatchingSearchConfirmTitle', newSites.length);
                if (alreadyAddedSites.length) {
                    title += ' (' + translate('RollUpReporting_MatchingSearchConfirmTitleAlreadyAdded', alreadyAddedSites.length) + ')';
                }
                var sitesToAdd = '<div><h2>' + title +'</h2><p>' + translate('RollUpReporting_MatchingSearchMatchedAdd', newSites.length, displaySearchTerm) + ':<br /><br />';
                sitesToAdd += newSites.join('');

                if (alreadyAddedSites.length) {
                    sitesToAdd += '<br />' + translate('RollUpReporting_MatchingSearchMatchedAlreadyAdded', alreadyAddedSites.length, displaySearchTerm) +':<br /><br />';
                    sitesToAdd += alreadyAddedSites.join('');
                }

                sitesToAdd += '</p><input role="yes" type="button" value="' + translate('General_Yes')+ '"/>' +
                    '<input role="no" type="button" value="' + translate('General_No')+ '"/>' +
                    '</div>';
                piwik.helper.modalConfirm(sitesToAdd, {yes: function () {
                    angular.forEach(sites, function (site) {
                        self.addSite({id: site.idsite});
                    });
                }});
            });
        };

        this.hasKeyDefined = function()
        {
            return this.siteIds.indexOf(KEY_NO_SITE_DEFINED) > -1;
        };

        this.updateSites = function () {
            if (!this.hasKeyDefined()) {
                this.siteIds.push(KEY_NO_SITE_DEFINED);
            }

            if (this.siteIds && this.siteIds.indexOf('all') > -1) {
                this.showAllSites = true;
                this.sites = [{name: translate('RollUpReporting_AllMeasurablesAssigned'), id: 'all'}];
                return;
            }

            allSitesPromise.then(function (allSites) {
                if (allSites && allSites.length && self.siteIds) {
                    self.sites = [];
                    for (var i = 0; i < self.siteIds.length; i++) {
                        var idSite = self.siteIds[i];

                        if (idSite === KEY_NO_SITE_DEFINED) {
                            continue;
                        }

                        for (var j = 0; j < allSites.length; j++) {
                            if (allSites[j] && allSites[j].idsite == idSite) {
                                self.sites.push({name: allSites[j].name, id: idSite});
                            }
                        }

                    }
                }
            });
        }

        $scope.$watch('manageRollUp.siteIds', function (val, oldVal) {
            // we only update it when it is out of sync for some reason, otherwise we update directly when adding
            // a site or removing a site
            self.updateSites();
        });

    }
})();
