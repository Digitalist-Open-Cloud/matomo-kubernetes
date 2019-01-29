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
 * This file registers the Overlay row action on the pages report.
 */

(function () {

    var actionName = 'MediaDetails';

    var lastRowReference = null;

    function getDataTableFromApiMethod(apiMethod)
    {
        var div = $(require('piwik/UI').DataTable.getDataTableByReport(apiMethod));
        if (div.size() > 0 && div.data('uiControlObject')) {
            return div.data('uiControlObject');
        }
    }

    function DataTable_RowActions_MediaDetail(dataTable) {
        this.dataTable = dataTable;
        this.actionName = actionName;

        // has to be overridden in subclasses
        this.trEventName = 'piwikTriggerMediaDetailAction';
    }

    DataTable_RowActions_MediaDetail.prototype = new DataTable_RowAction();

    DataTable_RowActions_MediaDetail.prototype.openPopover = function (apiAction, idSubtable, extraParams) {
        var urlParam = apiAction + ':' + encodeURIComponent(idSubtable) + ':' + encodeURIComponent(JSON.stringify(extraParams));

        broadcast.propagateNewPopoverParameter('RowAction', actionName + ':' + urlParam);
    };

    DataTable_RowActions_MediaDetail.prototype.trigger = function (tr, e, subTableLabel) {
        var idSubtable = $(tr).attr('id');

        lastRowReference = tr;

        this.performAction(idSubtable, tr, e);
    };

    DataTable_RowActions_MediaDetail.prototype.performAction = function (idSubtable, tr, e) {

        var apiAction = this.dataTable.param.action;

        lastRowReference = tr;

        this.openPopover(apiAction, idSubtable, {});
    };

    DataTable_RowActions_MediaDetail.prototype.doOpenPopover = function (urlParam) {
        var urlParamParts = urlParam.split(':');

        var apiAction = urlParamParts.shift();
        var idSubtable = decodeURIComponent(urlParamParts.shift());

        var extraParamsString = urlParamParts.shift(),
            extraParams = {}; // 0/1 or "0"/"1"

        try {
            extraParams = JSON.parse(decodeURIComponent(extraParamsString));
        } catch (e) {
            // assume the parameter is an int/string describing whether to use multi row evolution
        }

        var box = Piwik_Popover.showLoading(_pk_translate('MediaAnalytics_MediaDetails'));
        box.addClass('mediaDetailPage');

        var callback = function (html) {
            Piwik_Popover.setContent(html);
            box.addClass('mediaDetailPage');

            // remove title returned from the server
            var title = box.find('h2');
            var defaultTitle = title.first().text();

            if (title.size() > 0) {
                title.first().remove();
            }

            var $lastRowReference = $(lastRowReference);
            if ($lastRowReference.size()) {
                defaultTitle += ' "' + $lastRowReference.find('.label .value').text() + '"';
            }

            Piwik_Popover.setTitle(defaultTitle);

            var $segmentLink = box.find('.segmentLink');
            if ($segmentLink.size()) {
                var applySegment = '';
                if ($lastRowReference.size() && $lastRowReference.attr('data-segment-filter')) {
                    applySegment = $lastRowReference.attr('data-segment-filter');
                }

                if (applySegment) {
                   $segmentLink.click((function (applySegment) {
                       return function (event) {
                           event.stopPropagation();
                           event.preventDefault();
                           Piwik_Popover.close();
                           var fullSegment = decodeURIComponent(applySegment) + ';media_spent_time%3E1';
                           broadcast.propagateNewPage('popover=&segment='+encodeURIComponent(applySegment), undefined, 'category=General_Visitors&subcategory=General_Overview');
                       }
                   })(applySegment));
                } else {
                   $segmentLink.parents('.segmentLinkInfo').hide();
                }
            }
        };

        var ajaxRequest = new ajaxHelper();
        ajaxRequest.addParams({
            module: 'MediaAnalytics',
            action: 'detail',
            idSubtable: idSubtable,
            isDetailPage: 1,
            reportAction: apiAction
        }, 'get');
        ajaxRequest.setCallback(callback);
        ajaxRequest.setErrorCallback(function (deferred, status) {
            if (status == 'abort' || !deferred || deferred.status < 400 || deferred.status >= 600) {
                return;
            }
            $('#loadingError').show();
        });
        ajaxRequest.setFormat('html');
        ajaxRequest.send();
    };

    DataTable_RowActions_Registry.register({

        name: actionName,

        dataTableIcon: 'plugins/MediaAnalytics/images/mediaDetail.png',
        dataTableIconHover: 'plugins/MediaAnalytics/images/mediaDetail_hover.png',

        order: 30,

        dataTableIconTooltip: [
            _pk_translate('MediaAnalytics_RowActionTooltipTitle'),
            _pk_translate('MediaAnalytics_RowActionTooltipDefault')
        ],

        isAvailableOnReport: function (dataTableParams, undefined) {
            return dataTableParams && dataTableParams.module && dataTableParams.module == 'MediaAnalytics';
        },

        isAvailableOnRow: function (dataTableParams, tr) {
            var $tr = $(tr);
            if (!$tr.hasClass('notOpenable')) {
                return false;
            }
            var idSubtable = $tr.attr('id');
            if (!idSubtable) {
                return false;
            }

            return true;
        },

        createInstance: function (dataTable, param) {
            if (dataTable !== null && typeof dataTable.mediaDetailInstance != 'undefined') {
                return dataTable.mediaDetailInstance;
            }

            if (dataTable === null && param) {
                // when segmented visitor log is triggered from the url (not a click on the data table)
                // we look for the data table instance in the dom
                var report = param.split(':')[0];
                var tempTable = getDataTableFromApiMethod(report);
                if (tempTable) {
                    dataTable = tempTable;
                    if (typeof dataTable.mediaDetailInstance != 'undefined') {
                        return dataTable.mediaDetailInstance;
                    }
                }
            }

            var instance = new DataTable_RowActions_MediaDetail(dataTable);
            if (dataTable !== null) {
                dataTable.mediaDetailInstance = instance;
            }

            return instance;
        }

    });

})();