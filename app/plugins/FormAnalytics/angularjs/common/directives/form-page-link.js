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

/**
 * Usage:
 * <div piwik-form-page-link="idForm">
 */
(function () {
    angular.module('piwikApp.directive').directive('piwikFormPageLink', piwikFormPageLink);

    piwikFormPageLink.$inject = ['$location', 'piwik'];

    function piwikFormPageLink($location, piwik){

        return {
            restrict: 'A',
            compile: function (element, attrs) {

                if (attrs.piwikFormPageLink
                    && piwik.helper.isAngularRenderingThePage()) {

                    var link = element;

                    if (('' + element.prop('tagName')).toLowerCase() != 'a') {
                        var headline = element.text();
                        element.html('<a></a>');

                        link = element.find('a');
                        link.text(headline);
                    }

                    link.attr('href', 'javascript:void(0)');
                    link.bind('click', function () {
                        var $search = $location.search();
                        $search.category = 'FormAnalytics_Forms';
                        $search.subcategory = encodeURIComponent(attrs.piwikFormPageLink);
                        $location.search($search);
                    });
                }

                return function (scope, element, attrs) {
                };
            }
        };
    }
})();