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

(function ($, require) {

    // we cannot save the timer in the dataTable instance since the data table would be reset every time
    var delay = null;

    var exports = require('piwik/UI'),
        DataTable = exports.DataTable,
        dataTablePrototype = DataTable.prototype;

    /**
     * UI control that handles extra functionality for Media datatables.
     *
     * @constructor
     */
    exports.LiveMediaDataTable = function (element) {
        this.parentAttributeParent = '';
        this.parentId = '';

        DataTable.call(this, element);
    };

    $.extend(exports.LiveMediaDataTable.prototype, dataTablePrototype, {

        postBindEventsAndApplyStyleHook: function (domElem) {
            this.refreshTable();
        },

        refreshTable: function () {
            if (this.refreshTimeout || !this.param.updateInterval) {
                return;
            }

            if (null === delay) {
                delay = this.param.updateInterval;
            } else {
                delay = delay + 2200;
                // we slowly increase timeout
            }

            if (delay > 150000) {
                delay = 150000; // max delay of 2.5min
            }

            var self = this;
            this.refreshTimeout = setTimeout(function () {
                self.reloadAjaxDataTable(false, function (response) {
                    self.refreshTimeout = null;

                    var scrollTo = piwikHelper.lazyScrollTo;
                    piwikHelper.lazyScrollTo = function () {}; // make sure to prevent scrolling
                    var content = self.dataTableLoaded(response, self.workingDivId, false);
                    piwikHelper.lazyScrollTo = scrollTo;
                    var $wrapper = content.find('.dataTableWrapper');
                    var $columns = $wrapper.find('td');

                    if ($columns.size()) {
                        $wrapper = $columns;
                        // if there are columns, we prefer to update columns but there might be none when there is no data
                    }
                    $wrapper.effect('highlight', {}, 600);
                });
            }, delay);
        }
    });

})(jQuery, require);
