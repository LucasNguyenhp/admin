/*
 * Welcome to your app's main JavaScript file!
 *
 */
import 'regenerator-runtime/runtime'
import $ from 'jquery';

import('bootstrap');
import('popper.js');
global.$ = global.jQuery = $;
import('mdbootstrap');
import {masterNotify, initNotofication} from './lobbyNotification'
import {initCircle} from './initCircle'
import {initWebcam, choosenId,stopWebcam} from './cameraUtils'
import {initAUdio, micId, audioId,echoOff} from './audioUtils'
import {initAjaxSend} from './confirmation'
import {setSnackbar} from './myToastr';
import {initGenerell} from './init';


initNotofication();

initAjaxSend(confirmTitle, confirmCancel, confirmOk);

const es = new EventSource(topic);
es.onmessage = e => {
    var data = JSON.parse(e.data)
    masterNotify(data)
    if (data.type === 'newJitsi') {
        initJitsiMeet(data);
    }
}

initCircle();
var counter = 0;
var interval;
var text;
$('.renew').click(function (e) {
    e.preventDefault();
    if (counter <= 0) {
        text = $(this).text();
        $.get($(this).attr('href'), function (data) {
            counter = reknockingTime;
            interval = setInterval(function () {
                counter = counter - 1;
                $('.renew').text(text + ' (' + counter + ')');
                if (counter <= 0) {
                    $('.renew').text(text);
                    clearInterval(interval);
                }
            }, 1000);
            setSnackbar(data.message,data.color);
        })
    }
})
$('.leave').click(function (e) {
    e.preventDefault();

    text = $(this).text();
    $.get($(this).attr('href'), function (data) {
        window.location.href = "/";
    })

})

function initJitsiMeet(data) {
    stopWebcam();
    var options =data.options.options;
    options.device = choosenId;
    options.parentNode = document.querySelector( data.options.parentNode);
    const api = new JitsiMeetExternalAPI(data.options.domain, options);
    $(data.options.parentNode).prependTo('body').css('height', '100vh').find('iframe').css('height', 'inherit');
    $('#window').remove();
    $('.imageBackground').remove();
    document.title = data.options.roomName
    $('body').append('<div id="snackbar" class="bg-success d-none"></div>')
}
$(document).ready(function () {
    initGenerell()
    initAUdio();
    initWebcam();
})


