<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <title>The Chest</title>
</head>
<body>
<h1>Добро пожаловать, <?php echo $name; ?></h1>
<form class="hide-if-in-session" action="\create_session" method="GET">
    <input hidden name="usrid" value="<?php echo $userid; ?>">
    <input hidden name="char" value="1">
    <button type="submit">Создать новую сессию</button>
</form>
<form class="hide-if-in-session" action="\connect_to">
    <input hidden name="usrid" value="<?php echo $userid; ?>">
    <input hidden name="char" value="1">
    <input type="text" name="session">Код сессии<Br>
    <button type="submit">Подключиться</button>
</form>
<form class="show-if-in-session" hidden action="\leave">
    <input hidden name="usrid" value="<?php echo $userid; ?>">
    <input hidden name="char" value="1">
    <input class="currsessionfield" hidden name="session" value="1">
    <button type="submit">Покинуть сессию</button>
</form>

<div><h1 id="session-header"></h1></div>


    <div id="currlobby">
        <p id="currlobby_header">Участники:</p>
        <button id="ready_button" onclick="swap_ready(); return false;">Готов</button>
    </div>

    <div hidden id="start-game-module">
        <a href="#" onclick="start_game(); return false">Начать игру</a>
    </div>
</body>

<script>
    var usrid = <?php echo $userid; ?>;
    var usrname = '<?php echo $name; ?>';
    var $session = jQuery.parseJSON('<?php echo $session; ?>');
    var curr_ready = 0;

    function start_game() {
        window.open("/game?usrid=" + usrid + "&session=" + $session.id,"_self")
    }

    function refresh_ready_button() {

        if(curr_ready == 0) {
            $('#ready_button').text('Готов');
        } else {
            $('#ready_button').text('Не готов');
        }

    }

    function refresh_lobby() {

        $.ajax({
            url: '/lobby',
            type: "get", //send it through get method
            data: {
                usrid: usrid,
                session: $session['id']
            },
            success: function(data) {


                var parseddata = jQuery.parseJSON(data);

                if(parseddata.error != undefined && parseddata.error.length != 0) {
                    console.log(parseddata);
                    $('#currlobby').hide();
                    return;
                }

                if(!jQuery.isEmptyObject(parseddata)) {
                    $('#currlobby').show();
                    $('.usrstr').remove();

                    $.each(parseddata, function(key, element) {

                        if(element.id == usrid) {
                            curr_ready = element.is_ready;
                        }

                        $('#currlobby_header').append("<div class='usrstr'><p>" + element.name + "(" + element.character[0].name + ") " + (element.is_ready ? "ready" : "not ready") + "</p></div>");

                    });

                    if(parseddata.every(x => x.is_ready)) {
                        $('#start-game-module').show();
                    } else {
                        $('#start-game-module').hide();
                    }

                } else {
                    $('#currlobby').hide();
                }

                refresh_ready_button();


            }
        });

    }

    function swap_ready() {


        $.ajax({
            url: '/set_ready',
            type: "get",
            data: {
                usrid: usrid,
                session: $session['id'],
                ready: (!curr_ready) ? 1 : 0
            },
            success: function(data) {
                refresh_lobby();
            }
        });

    }

    $( document ).ready(function() {

        if(!jQuery.isEmptyObject($session)) {
            console.log($session);
            $('#session-header').text($session.name);
            $('.currsessionfield').val($session.id);
            $('.hide-if-in-session').hide();
            $('.show-if-in-session').show();
        } else {
            console.log('session not found');
            $('.hide-if-in-session').show();
            $('.show-if-in-session').hide();
        }

        refresh_lobby();
    });

</script>

</html>


