/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

(function () {
    'use strict';

    angular.module('ChatRoomModule').directive('chatRoomInput', [
        function () {
            return {
                restrict: 'E',
                replace: true,
                templateUrl: AngularApp.webDir + 'bundles/clarolinechat/js/ChatRoom/Directive/templates/chatRoomInput.html'
            };
        }
    ]);
})();