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
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

(function ($, require) {

    var exports = require('piwik/UI'),
        DataTable = exports.DataTable,
        dataTablePrototype = DataTable.prototype;

    /**
     * UI control that handles extra functionality for Google Crawl Issues datatable.
     *
     * @constructor
     */
    exports.GoogleCrawlIssuesDataTable = function (element) {
        this.parentAttributeParent = '';
        this.parentId = '';
        this.disabledRowDom = {}; // to handle double click on '+' row

        DataTable.call(this, element);
    };

    $.extend(exports.GoogleCrawlIssuesDataTable.prototype, dataTablePrototype, {

        doHandleRowActions: function (trs) {
            var self = this;

            trs.each(function () {
                var tr = $(this);
                var label = tr.find('td:first').text();
                var metaData = tr.data('row-metadata');
                if (metaData && metaData['links'] && metaData['links'].length) {
                    var elem = $(tr.find('td')[5]);
                    elem.html(elem.html() + '<span class="icon-info"></span>');
                    elem.bind('click', function() {
                        self.showInformationOverLay(label, metaData['links'], 'SearchEngineKeywordsPerformance_LinksToUrl');
                    });
                }
                if (metaData && metaData['sitemaps'] && metaData['sitemaps'].length) {
                    var elem = $(tr.find('td')[6]);
                    elem.html(elem.html() + '<span class="icon-info"></span>');
                    elem.bind('click', function() {
                        self.showInformationOverLay(label, metaData['sitemaps'], 'SearchEngineKeywordsPerformance_SitemapsContainingUrl');
                    });
                }
            });
        },

        showInformationOverLay: function (label, links, headline) {

            var infoOverlay = $('#googleurlinfo');
            if (!infoOverlay.length) {
                infoOverlay = $('<div>').attr('id', 'googleurlinfo').addClass('ui-confirm');
                infoOverlay.insertAfter('#content');
            }

            var html = '<h2>'+_pk_translate(headline, ['<i>'+label+'</i>'])+'</h2>' +
                       '<ul>';

            if (links) {
                $.each(links, function(id, link) {
                    html += '<li><a href="'+link+'" target="_blank" rel="noreferrer">' + link + '</a></li>'
                });
            }

            html += '</ul><input role="ok" type="button" value="' + _pk_translate('General_Close') + '"/>';

            infoOverlay.html(html);

            piwikHelper.modalConfirm('#googleurlinfo', {ok: function(){}});
        }

    });

})(jQuery, require);
