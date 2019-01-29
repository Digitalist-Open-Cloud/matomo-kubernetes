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
        ActionsDataTable = exports.ActionsDataTable,
        ActionsDataTablePrototype = ActionsDataTable.prototype;

    function getRowMetadata($elem) {
        var metadata = $elem.attr('data-row-metadata');

        if (!metadata) {
            return;
        }

        try {
            metadata = JSON.parse(metadata);
        } catch (e) {
            metadata = null;
        }

        return metadata;
    }


    /**
     * UI control that handles extra functionality for Media datatables.
     *
     * @constructor
     */
    exports.MediaDataTable = function (element) {
        this.parentAttributeParent = '';
        this.parentId = '';

        ActionsDataTable.call(this, element);
    };

    $.extend(exports.MediaDataTable.prototype, ActionsDataTablePrototype, {

        preBindEventsAndApplyStyleHook: function (domElem) {
            $('tr.subDataTable', domElem).each(function () {
                var metadata = getRowMetadata($(this));

                if (!metadata || !metadata.openable) {
                    // those are second level rows
                    $(this).removeClass('subDataTable');
                    $(this).addClass('notOpenable');
                }
            });
        },

        //called when the full table actions is loaded
        dataTableLoaded: function (response, workingDivId) {
            var content = $(response);
            var idToReplace = workingDivId || $(content).attr('id');

            //reset parents id
            this.parentAttributeParent = '';
            this.parentId = '';

            var dataTableSel = $('#' + idToReplace);

            dataTableSel.replaceWith(content);

            content.trigger('piwik:dataTableLoaded');

            piwikHelper.lazyScrollTo(content[0], 400);
            piwikHelper.compileAngularComponents(content);

            return content;
        }
    });

})(jQuery, require);
