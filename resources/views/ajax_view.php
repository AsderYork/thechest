<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <title>The Chest</title>
</head>
<body>

<form hidden id="login-form" >
    Name:<input type="text" name="name" id="login-form_name">
</form>

<div hidden id="lobby">
    <h2 id="greeting"></h2>

    <div hidden id="lobby_no_sessions">Нет текущих сессий
        <p>
            <form id="lobby_new-session">
                <button>Создать новую сессию</button>
            </form>
        </p>
        <p>
            <form id="lobby_connect-to">
                <input type="text" name="session_name" id="lobby_connect-to_name"><button type="submit">Подключиться</button>
            </form>
        </p>
    </div>
    <div hidden id="lobby_session">
        <h1 id="lobby_session_name"></h1>
        <div id="lobby_players">
        </div>
        <p>
            <button id="ready_button"></button>
        </p>
        <p hidden id="start_game_button_holder">
            <button id="start_game_button">Начать игру</button>
        </p>

    </div>

</div>

<div hidden id="game">
    <h2 id="curr_round"></h2>
    <h2 id="curr_level"></h2>

    <h3>Players</h3>
    <div id="player-table">

    </div>

    <form id="game_form">
    <h3>Party</h3>
    <div id="player_party">
    </div>
    <h3>Encounter</h3>
    <div id="encounter"></div>

    <p>
        <button name="next_level" value="1">Следующий уровень</button>
    </p>
    <p>
        <button name="end_turn" value="1">Завершить подземелье</button>
    </p>
    </form>

</div>


</body>

<script>

    var usrid = null;
    var session_id = null;
    var curr_ready = null;
    var interval = null;
    var all_ready = false;

    $( "#login-form" ).submit(function( event ) {
        event.preventDefault();

        $.ajax({
            url: '/ajax/login',
            type: 'POST',
            data: {name:$('#login-form_name').val()},
            cache: false,
            success: function (result) {
                var returnedData = JSON.parse(result);
                usrid = returnedData.usrid;
                show_lobby();
            },
            error: function () {
            }
        });

    });
    $( "#lobby_new-session" ).submit(function( event ) {
        event.preventDefault();

        $.ajax({
            url: '/ajax/new_session',
            type: 'POST',
            data: {usrid:usrid},
            cache: false,
            success: function (result) {
                console.log(result);
                show_lobby();
            },
            error: function () {
            }
        });

    });
    $( "#lobby_connect-to" ).submit(function( event ) {
        event.preventDefault();

        console.log($('#lobby_connect-to_name').val());

        $.ajax({
            url: '/ajax/connect_to',
            type: 'POST',
            data: {usrid:usrid, session_name:$('#lobby_connect-to_name').val()},
            cache: false,
            success: function (result) {
                console.log(result);
                show_lobby();
            },
            error: function () {
            }
        });

    });
    $( "#ready_button" ).click(function () {

        $.ajax({
            url: '/ajax/set_ready',
            type: 'POST',
            data: {usrid: usrid, session_id: session_id, ready: !curr_ready ? 1 : 0},
            cache: false,
            success: function (result) {
                reload_players();
            }
        });

    });
    $( "#start_game_button" ).click(function () {

        $.ajax({
            url: '/ajax/game',
            type: 'POST',
            data: {usrid: usrid, session_id: session_id,},
            cache: false,
            success: function (result) {
                var returnedData = JSON.parse(result);
                process_game_state(returnedData);
            }
        });

    });
    $( "#game_form" ).submit(function ( event ) {

        splitted = $('#game_form').serialize().split('&');
        console.log(splitted);


        event.preventDefault();


    });



    function process_game_state(data) {

        $('#login-form').hide();
        $('#lobby').hide();
        $('#game').show();


        $('#curr_round').text('Раунд:' + data.curr_player.round);
        $('#curr_level').text('Уровень:' + data.curr_player.curr_level);

        $.each(data.players, function(key, element) {
            $('#player-table').append(
                '<tr><td id=\'player'+ element.id + '\'>'
                + element.name
                + '('
                + element.character.name
                + ') (exp '
                + element.exp
                + ')</td></tr>');
        });
        $('#player'+ data.curr_player.player_id ).css("background-color", "#cceecc");

        $('#player_party').empty();
        $.each(data.curr_party, function(key, element) {
            if(element.is_alive) {
                $('#player_party').append('<div><input type="checkbox" name="party[]" value="' + element.id + '">' + element.name + '</div>');
            }else{
                $('#player_party').append('<div style="background: coral;"><input type="checkbox" id="party-checkbox" name="party[]" value="' + element.id + '">' + element.name + '</div>');

            }
        });

        $('#encounter').empty();
        $.each(data.curr_encounter, function(key, element) {
            if(element.is_alive) {
                $('#encounter').append('<div><input type="checkbox" name="enemies[]" value=\'' + element.id + '\'>' + element.name + '</div>');
            } else {
                $('#encounter').append('<div style="background: coral;"><input type="checkbox" name="enemies[]" value=\'' + element.id + '\'>' + element.name + '</div>');

            }
        });

    }


    function login_form() {
        $('#login-form').show();
        $('#lobby').hide();
        $('#game').hide();
    }

    function reset_ready_button() {

        if(curr_ready) {
            $('#ready_button').text('Не готов');
        } else {
            $('#ready_button').text('Готов');
        }

    }
    function reset_start_game_button() {

        if(all_ready) {
            $('#start_game_button_holder').show();
        } else {
            $('#start_game_button_holder').hide();
        }

    }

    function reload_players() {

        $.ajax({
            url: '/ajax/ready_table',
            type: 'POST',
            data: {usrid:usrid, session_id:session_id},
            cache: false,
            success: function (result) {
                var returnedData = JSON.parse(result);

                $('#lobby_players').empty();
                all_ready = returnedData.all_ready;

                returnedData.players.forEach(function(element) {
                    $('#lobby_players').append("<div class='usrstr'><p>" + element.name + "(" + element.character[0].name + ") " + (element.is_ready ? "ready" : "not ready") + "</p></div>");

                    if(element.player_id == usrid) {
                        curr_ready = element.is_ready;
                        reset_ready_button();
                    }

                });

                reset_start_game_button();

            }
        });

    }

    function process_lobby_data(data) {

        $('#greeting').text('Wellocmen, ' + data.user.name);

        if(data.sessions.length == 0) {
            $('#lobby_no_sessions').show();
            $('#lobby_session').hide();
        } else {
            $('#lobby_no_sessions').hide();
            $('#lobby_session').show();

            $('#lobby_session_name').text(data.sessions[0].name);
            session_id = data.sessions[0].id;
            reload_players();
            interval = setInterval(reload_players, 1000);
        }

    }

    function show_lobby() {
        $('#login-form').hide();
        $('#lobby').show();
        $('#game').hide();

        $.ajax({
            url: '/ajax/lobby',
            type: 'POST',
            data: {usrid:usrid},
            cache: false,
            success: function (result) {
                var returnedData = JSON.parse(result);
                console.log(returnedData);

                switch (returnedData.err) {
                    case 'NO_AUTH': login_form(); break;
                    case 'OK': process_lobby_data(returnedData);

                }



            },
            error: function () {
            }
        });


    }

    $( document ).ready(function() {
        show_lobby();

    });


/*
    var arrq = ["party%5B%5D=9", "party%5B%5D=10", "enemies%5B%5D=1"];

    arrq = arrq
        .map(x => x.replace('%5B%5D', '').
        split('=').
        map(x => x)).
        map(x => ({k:x[0], v:x[1]}))
        .reduce((x,y) => {x.keys.push(y.k); return x;}, {keys:[]});

    console.log(arrq);

*/

</script>

</html>