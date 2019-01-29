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

    var exports = require('piwik/UI'),
        DataTable = exports.DataTable,
        DataTablePrototype = DataTable.prototype;

    /**
     * UI control that handles extra functionality for Media datatables.
     *
     * @constructor
     */
    exports.FunnelDataTable = function (element) {
        this.parentAttributeParent = '';
        this.parentId = '';

        DataTable.call(this, element);
    };

    $.extend(exports.FunnelDataTable.prototype, DataTablePrototype, {
        preBindEventsAndApplyStyleHook: function (domElem) {
            var currentIsSubDataTable = $(domElem).parent().hasClass('cellSubDataTable');

            var width = '170px';
            if (currentIsSubDataTable) {
                width = '150px';
            }
            $("td:first-child", domElem).addClass('label').css('width', width);
        },

        postBindEventsAndApplyStyleHook: function (domElem) {
            $('tr.subDataTable > td:first-child .label .value', domElem).before('<img class="plusMinus whenExpanded" src="plugins/Morpheus/images/minus.png" />');
            $('tr.subDataTable > td:first-child .label .value', domElem).before('<img class="plusMinus whenNotExpanded" src="plugins/Morpheus/images/plus.png" />');
        }

    });

})(jQuery, require);
