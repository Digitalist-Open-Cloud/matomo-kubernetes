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
    // truncateText is being used by form analytics... that's why there is a 2...
    angular.module('piwikApp.filter').filter('truncateText2', truncateText2);

    function truncateText2() {

        return function(text, length) {
            if (text && (text + '').length > length) {
                return text.substr(0, length - 3) + '...';
            }
            return text;
        };
    }
})();
