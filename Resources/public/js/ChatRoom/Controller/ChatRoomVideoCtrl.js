/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

var RTC = null;
var ice_config = {
    iceServers: [
        {url: 'stun:23.21.150.121'},
        {url: 'stun:stun.l.google.com:19302'}
    ]
};
var RTCPeerconnection = null;
//var AUTOACCEPT = true;
//var PRANSWER = false; // use either pranswer or autoaccept
//var RAWLOGGING = true;
//var MULTIPARTY = true;
//var localStream = null;
var connection = null;
//var myroomjid = null;
var roomjid = null;
var myUsername = null;
var videoTracks = null;
//var list_members = [];

(function () {
    'use strict';

    angular.module('ChatRoomModule').controller('ChatRoomVideoCtrl', [
        '$scope',
        '$rootScope', 
        'XmppService', 
        'XmppMucService', 
        function ($scope, $rootScope, XmppService, XmppMucService) {
            $scope.localStream = null;
            
            function setStatus(txt) {
                console.log('status', txt);
            }

            function onMediaReady(event, stream) {
                $scope.localStream = stream;
                connection.jingle.localStream = stream;
                for (var i = 0; i < $scope.localStream.getAudioTracks().length; i++) {
                    console.log('using audio device "' + $scope.localStream.getAudioTracks()[i].label + '"');
                }
                for (i = 0; i < $scope.localStream.getVideoTracks().length; i++) {
                    console.log('using video device "' + $scope.localStream.getVideoTracks()[i].label + '"');
                }
                // mute video on firefox and recent canary
                $('#minivideo')[0].muted = true;
                $('#minivideo')[0].volume = 0;

                RTC.attachMediaStream($('#minivideo'), $scope.localStream);

//                doConnect();

                if (typeof hark === "function") {
                    var options = { interval: 400 };
                    var speechEvents = hark(stream, options);

                    speechEvents.on('speaking', function () {
                        console.log('speaking');
                    });

                    speechEvents.on('stopped_speaking', function () {
                        console.log('stopped_speaking');
                    });
                    speechEvents.on('volume_change', function (volume, treshold) {
                      //console.log('volume', volume, treshold);
                        if (volume < -60) { // vary between -60 and -35
                            $('#ownvolume').css('width', 0);
                        } else if (volume > -35) {
                            $('#ownvolume').css('width', '100%');
                        } else {
                            $('#ownvolume').css('width', (volume + 100) * 100 / 25 - 160 + '%');
                        }
                    });
                } else {
                    console.warn('without hark, you are missing quite a nice feature');
                }
            }

            function onMediaFailure() {
                setStatus('media failure');
            }

            function onCallIncoming(event, sid) {
                setStatus('incoming call' + sid);
                var sess = connection.jingle.sessions[sid];
                sess.sendAnswer();
                sess.accept();

                // alternatively...
                //sess.terminate(busy)
                //connection.jingle.terminate(sid);
            }
            
            function arrangeVideos(selector) {
//                var floor = Math.floor,
//                    elements = $(selector),
//                    howMany = elements.length,
//                    availableWidth = $(selector).parent().innerWidth(),
//                    availableHeight = $(selector).parent().innerHeight(),
//                    usedWidth = 0,
//                    aspectRatio = 4 / 3;
//                if (availableHeight < availableWidth / aspectRatio) {
//                    availableWidth = availableHeight * aspectRatio;
//                }
//                elements.height(availableHeight);

                var elements = $(selector);
                elements.each(function (index) {
                    $(elements[index]).removeAttr('style');
                });

                // hardcoded layout for up to four videos
//                switch (howMany) {
//                case 1:
//                    usedWidth = availableWidth;
//                    $(elements[0]).css('top', 0);
//                    $(elements[0]).css('left', ($(selector).parent().innerWidth() - availableWidth) / 2);
//                    break;
//                case 2:
//                    usedWidth = availableWidth / 2;
//                    $(elements[0]).css({ left: '0px', top: '0px'});
//                    $(elements[1]).css({ right: '0px', bottom: '0px'});
//                    break;
//                case 3:
//                    usedWidth = availableWidth / 2;
//                    $(elements[0]).css({ left: '0px', top: '0px'});
//                    $(elements[1]).css({ right: '0px', top: '0px'});
//                    $(elements[2]).css({ left: ($(selector).parent().innerWidth() - availableWidth + usedWidth) / 2, bottom: '0px' });
//                    break;
//                case 4:
//                    usedWidth = availableWidth / 2;
//                    $(elements[0]).css({ left: '0px', top: '0px'});
//                    $(elements[0]).css('left', ($(selector).parent().innerWidth() - availableWidth) / 2);
//                    $(elements[1]).css({ right: '0px', top: '0px'});
//                    $(elements[1]).css('right', ($(selector).parent().innerWidth() - availableWidth) / 2);
//                    $(elements[2]).css({ left: '0px', bottom: '0px'});
//                    $(elements[2]).css('left', ($(selector).parent().innerWidth() - availableWidth) / 2);
//                    $(elements[3]).css({ right: '0px', bottom: '0px'});
//                    $(elements[3]).css('right', ($(selector).parent().innerWidth() - availableWidth) / 2);
//                    break;
//                }
//                elements.each(function (index) {
//                    $(elements[index]).css({
//                        position: 'absolute',
//                        width: usedWidth,
//                        height: usedWidth / aspectRatio
//                    });
//                    $(elements[index]).show();
//                });
            }
            
            function onCallActive(event, videoelem, sid) {
                console.log('------------------------------------------------');
                console.log(videoelem);
                console.log('------------------------------------------------');
                setStatus('call active ' + sid);
//                videoelem[0].style.display = 'inline-block';
                $(videoelem).appendTo('#largevideocontainer');
                arrangeVideos('#largevideocontainer >');
                connection.jingle.sessions[sid].getStats(1000);
            }

            function onCallTerminated(event, sid, reason) {
                setStatus('call terminated ' + sid + (reason ? (': ' + reason) : ''));
                if (Object.keys(connection.jingle.sessions).length === 0) {
                    setStatus('all calls terminated');
                }
                $('#largevideocontainer #largevideo_' + sid).remove();
                arrangeVideos('#largevideocontainer >');
            }
            
            function waitForRemoteVideo(selector, sid) {
                var sess = connection.jingle.sessions[sid];
                videoTracks = sess.remoteStream.getVideoTracks();
                if (videoTracks.length === 0 || selector[0].currentTime > 0) {
                    $(document).trigger('callactive.jingle', [selector, sid]);
                    RTC.attachMediaStream(selector, sess.remoteStream); // FIXME: why do i have to do this for FF?
                    console.log('waitForremotevideo', sess.peerconnection.iceConnectionState, sess.peerconnection.signalingState);
                } else {
                    setTimeout(function () { waitForRemoteVideo(selector, sid); }, 100);
                }
            }

            function onRemoteStreamAdded(event, data, sid) {
                setStatus('Remote stream for session ' + sid + ' added.');
                if ($('#largevideo_' + sid).length !== 0) {
                    console.log('ignoring duplicate onRemoteStreamAdded...'); // FF 20
                    return;
                }
                // after remote stream has been added, wait for ice to become connected
                // old code for compat with FF22 beta
                var el = $("<video autoplay='autoplay' style='display:none'/>").attr('id', 'largevideo_' + sid);
                RTC.attachMediaStream(el, data.stream);
                waitForRemoteVideo(el, sid);
                /* does not yet work for remote streams -- https://code.google.com/p/webrtc/issues/detail?id=861
                var options = { interval:500 };
                var speechEvents = hark(data.stream, options);

                speechEvents.on('volume_change', function (volume, treshold) {
                  console.log('volume for ' + sid, volume, treshold);
                });
                */
            }

            function onRemoteStreamRemoved(event, data, sid) {
                setStatus('Remote stream for session ' + sid + ' removed.');
            }

            function onIceConnectionStateChanged(event, sid, sess) {
                console.log('ice state for', sid, sess.peerconnection.iceConnectionState);
                console.log('sig state for', sid, sess.peerconnection.signalingState);
                // works like charm, unfortunately only in chrome and FF nightly, not FF22 beta
                /*
                if (sess.peerconnection.signalingState == 'stable' && sess.peerconnection.iceConnectionState == 'connected') {
                    var el = $("<video autoplay='autoplay' style='display:none'/>").attr('id', 'largevideo_' + sid);
                    $(document).trigger('callactive.jingle', [el, sid]);
                    RTC.attachMediaStream(el, sess.remoteStream); // moving this before the trigger doesn't work in FF?!
                }
                */
            }

            function noStunCandidates(event) {
                setStatus('webrtc did not encounter stun candidates, NAT traversal will not work');
                console.warn('webrtc did not encounter stun candidates, NAT traversal will not work');
            }

            $rootScope.$on('xmppMucConnectedEvent', function (event) {
//                var connection = XmppService.getConnection();
//                var roomjid = XmppMucService.getRoom();
//                var myroomjid = XmppMucService.getRoom() + '/' + XmppService.getUsername();
                connection = XmppService.getConnection();
                roomjid = XmppMucService.getRoom();
                myUsername = XmppService.getUsername();
                RTC = setupRTC();
                getUserMediaWithConstraints(['audio', 'video']);
                $(document).bind('mediaready.jingle', onMediaReady);
                $(document).bind('mediafailure.jingle', onMediaFailure);
                $(document).bind('callincoming.jingle', onCallIncoming);
                $(document).bind('callactive.jingle', onCallActive);
                $(document).bind('callterminated.jingle', onCallTerminated);

                $(document).bind('remotestreamadded.jingle', onRemoteStreamAdded);
                $(document).bind('remotestreamremoved.jingle', onRemoteStreamRemoved);
                $(document).bind('iceconnectionstatechange.jingle', onIceConnectionStateChanged);
                $(document).bind('nostuncandidates.jingle', noStunCandidates);
                $(document).bind('ack.jingle', function (event, sid, ack) {
                    console.log('got stanza ack for ' + sid, ack);
                });
                $(document).bind('error.jingle', function (event, sid, err) {
                    console.log('got stanza error for ' + sid, err);
                });
                $(document).bind('packetloss.jingle', function (event, sid, loss) {
                    console.warn('packetloss', sid, loss);
                });
    
                connection.jingle.ice_config = ice_config;
                
                if (RTC) {
                    connection.jingle.pc_constraints = RTC.pc_constraints;
                }
                RTCPeerconnection = RTC.peerconnection;
            });

            $scope.$on('newPresenceEvent', function (event, presenceDatas) {
                var status = presenceDatas['status'];
                
                if (status === 'connection' && presenceDatas['username'] !== myUsername) {
                    connection.jingle.initiate(
                        roomjid + '/' + presenceDatas['username'],
                        roomjid + '/' + myUsername
                    );
//                    console.log(XmppService.getXmppHost());
//                    console.log(XmppMucService.getRoom());
//                    console.log(XmppMucService.getUsers());
//                    console.log('salut ' + presenceDatas['username']);
//                    connection.jingle.initiate(list_members[i], myroomjid);
                }
            });
        }
    ]);
})();
 