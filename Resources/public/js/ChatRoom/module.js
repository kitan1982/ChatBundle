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

    angular.module('ChatRoomModule', ['XmppModule', 'ui.translation']);

    $('#chat-room-app').on('keypress', '#msg-input', function (e) {

        if (e.keyCode === 13) {
            var msgContent = $(this).val();

            if (msgContent !== undefined && msgContent !== '') {
                angular.element(document.getElementById('input-box')).scope().sendMessage(msgContent);
            }
            $('#msg-input').val('');
        }
    });

    $(window).unload(function(){
        angular.element(document.getElementById('chat-room-main')).scope().disconnect();
    });
})();