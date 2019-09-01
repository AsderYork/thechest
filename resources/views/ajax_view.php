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
    <h2 class="greeting_panel"></h2>

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
    </div><br>
    <div hidden id="lobby_session">
        <h1 id="lobby_session_name"></h1>
        <div id="lobby_players">
        </div>
        <p>
            <button id="ready_button"></button>
        </p>

    </div>

</div>

<div hidden id="game">
    <h3 class="greeting_panel"></h3>
    <h2 id="curr_round"></h2>
    <h2 id="curr_level"></h2>
    <h2 id="curr_dragons"></h2>

    <h3>Players</h3>
    <div id="player-table">

    </div>

    <form id="game_form">
    <h3>Party</h3>

    <h4 hidden id="potion_usage_display">Le potion</h4>
    <div id="player_party">
    </div>
    <div hidden id="enemy_panel">
        <h3>Encounter</h3>
        <div id="encounter"></div>
    </div>
    <div hidden id="dragon_panel">
        <h3>Битва с драконом<h3>
            <p>
                Выберете трех сопартийцев разных типов что бы одолеть дракона.
            </p>
    </div>
    <p>
        <button name="execute_action" class="activeplayer-only" value="1">Выполнить действие</button>
    </p>
    <p id="next_level_btn-envel">
        <button hidden id="next_level_btn" class="activeplayer-only" name="next_level" value="1">Следующий уровень</button>
    </p>
    <p id = 'discard_loot_btn-envel'>
        <button hidden id="discard_loot_btn" class="activeplayer-only" name="discard_loot" value="1">Сбросить добычу</button>
    </p>
    <p>
        <button name="end_turn" class="activeplayer-only" id="end_turn_btn" value="1">Завершить подземелье</button>
    </p>
    </form>

</div>

<div hidden id="win_table">
    <h3 class="greeting_panel"></h3>

    <h3>Winner table</h3>
    <div id="player-wintable">

    </div>
    <button onclick="show_lobby(); return false;">Вернуться в лобби</button>

</div>


</body>

<script>

    var usrid = null;
    var session_id = null;
    var curr_ready = null;
    var interval = null;
    var all_ready = false;
    var in_game = false;
    var selected_party = [];
    var selected_enemy = [];
    var is_activeplayer = false;
    var curr_encounter = null;
    var curr_party = null;
    var greetings = [
        'Бзиала шәаабеит,',
        'Къеблагъ,',
        'Welkom,',
        'ḫaṣānu,',
        'Mir se vjên,',
        'Qaĝaasakung huzuu haqakux̂,',
        'Wellkumma,',
        'Эзендер,',
        'Ahla w sahla',
        'Բարի գալուստ!',
        'আদৰণি',
        'Xoş gəlmişsiniz!',
        'Сәләм бирем!',
        'Horas!',
        'Прывiтанне,',
        'Tervetuloa,',
        'Velkommen,'
    ];
    var curr_greeting = greetings[Math.floor(Math.random()*greetings.length)];
    var selected_potions = [];
    var party_types = null;
    var enemy_types = null;

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
                show_lobby();
            },
            error: function () {
            }
        });

    });
    $( "#lobby_connect-to" ).submit(function( event ) {
        event.preventDefault();

        $.ajax({
            url: '/ajax/connect_to',
            type: 'POST',
            data: {usrid:usrid, session_name:$('#lobby_connect-to_name').val()},
            cache: false,
            success: function (result) {
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
    $( "#execute_action" ).click(function () {

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

        $.ajax({
            url: '/ajax/action',
            type: 'POST',
            data: {
                action:{enemies:selected_enemy, party:selected_party},
                usrid:usrid,
                session_id:session_id,
                changes:$('.potion_selector').map(function() {return {id:this.name,val:this.value}}).get()
            },
            cache: false,
            success: function (result) {
                reload_game();
            }
        });
        selected_enemy = [];
        selected_party = [];
        selected_potions = [];
        $('.potion_selector').remove();

        event.preventDefault();

    });
    $( "#next_level_btn" ).click(function ( ) {

        $.ajax({
            url: '/ajax/next_level',
            type: 'POST',
            data: {usrid:usrid, session_id:session_id},
            cache: false,
            success: function (result) {
                reload_game();
            }
        });

        event.preventDefault();

    });
    $( "#discard_loot_btn" ).click(function ( ) {

        $.ajax({
            url: '/ajax/discard_loot',
            type: 'POST',
            data: {usrid:usrid, session_id:session_id},
            cache: false,
            success: function (result) {
                reload_game();
            }
        });

        event.preventDefault();

    });
    $( "#end_turn_btn" ).click(function ( ) {

        $.ajax({
            url: '/ajax/end_turn',
            type: 'POST',
            data: {usrid:usrid, session_id:session_id},
            cache: false,
            success: function (result) {
                reload_game();
            }
        });

        event.preventDefault();

    });

    function sortHTMLby(sel, elem, predicate) {
        var $selector = $(sel),
            $element = $selector.children(elem);
        $element.sort(predicate);
        $element.detach().appendTo($selector);
    }

    function reload_game() {

        $.ajax({
            url: '/ajax/game',
            type: 'POST',
            data: {usrid: usrid, session_id: session_id,},
            cache: false,
            success: function (result) {
                in_game = true;
                var returnedData = JSON.parse(result);
                process_game_state(returnedData);

            }
        });

    }

    function remove_from_array_by_value(arr, val) {
        for( var i = 0; i < arr.length; i++){
            if ( arr[i] === val) {
                arr.splice(i, 1);
            }
        }
        return arr;
    }
    function process_checkbox(id, type) {

        switch (type) {
            case 'party':
                if(selected_party.includes(id)) {
                    selected_party = remove_from_array_by_value(selected_party, id);
                    if(selected_potions.length > 0) {
                        dead_checked_under_potion(id, false);
                    }
                } else {
                    selected_party.push(id);
                    if(selected_potions.length > 0) {
                        dead_checked_under_potion(id, true);
                    }
                }
                break;
            case 'enemy':
                if(selected_enemy.includes(id)) {
                    selected_enemy = remove_from_array_by_value(selected_enemy, id);
                    if(selected_potions.includes('enemy|' + id)) {
                        selected_potions = remove_from_array_by_value(selected_potions, 'enemy|' + id);
                        refresh_potion_state();
                    }

                } else {
                    selected_enemy.push(id);
                    if(!selected_potions.includes('enemy|' + id) && curr_encounter[id].is_alive && curr_encounter[id].name === 'POTION') {
                        selected_potions.push('enemy|' + id);
                        refresh_potion_state();
                    }
                }
                break;

        }

    }

    function form_greeting(name) {
        $('.greeting_panel').text(curr_greeting + ' ' + name)
    }

    function refresh_potion_state() {

        if(selected_potions.length === 0){
            $('#potion_usage_display').hide();
        }
        else {
            $('#potion_usage_display').show();
            remaining_heals = selected_potions.length - $('.potion_selector').length;
            if(remaining_heals > 0) {
                $('#potion_usage_display').text(remaining_heals + ' heals remain');
            } else {
                $('#potion_usage_display').text('All heals are spent');
            }
        }

    }
    function repopulate_dead_checkers() {

        if(selected_potions.length > 0) {
            selected_party.forEach(function (element) {
                dead_checked_under_potion(element, true);
            });
        }

    }
    function dead_checked_under_potion(id, checked) {

        if(checked && $(`#potselect${id}`).length === 0 && $('.potion_selector').length < selected_potions.length) {
            if (!curr_party[id].is_alive) {
                $('#party-checkbox' + id).parent().append(`<select class="potion_selector" id=potselect${id} name="${id}"></select>`);

                Object.values(party_types).forEach(function (element) {
                    if (element.id == curr_party[id].partymember_type) {
                        $(`#potselect${id}`).append(`<option value="${element.id}" selected>${element.name}</option>`);
                    } else {
                        $(`#potselect${id}`).append(`<option value="${element.id}" >${element.name}</option>`);
                    }
                });

            }
        }

    }

    function process_game_state(data) {

        curr_encounter = data.curr_encounter;
        curr_party = data.curr_party;

        $('#login-form').hide();
        $('#lobby').hide();
        $('#game').show();
        $('#win_table').hide();

        form_greeting(data.user.name);

        if(data.game_ended) {
            clearInterval(interval);
            show_win_table(data);
        }


        $('#curr_round').text('Раунд:' + data.session.round + ' из ' + data.session.max_rounds);
        $('#curr_level').text('Уровень:' + data.curr_player.curr_level + ' из ' + data.session.max_level);
        if(data.session.curr_dragons < 3) {
            $('#curr_dragons').text('Дракон:' + data.session.curr_dragons + ' из 3');
        } else {
            $('#curr_dragons').text('Дракон пробудился!');
        }

        battle_time = Object.values(data.curr_encounter).filter(x => x.is_alive && !x.avoidable).length;
        loot_time = Object.values(data.curr_encounter).filter(x => x.is_alive && x.avoidable).length;

        if((battle_time === 0) && (loot_time > 0) && !data.can_level_end) {
            $('#discard_loot_btn-envel').show();
            $('#next_level_btn-envel').hide();
        } else {
            $('#discard_loot_btn-envel').hide();
            if(data.can_level_end) {
                $('#next_level_btn-envel').show();
            } else {
                $('#next_level_btn-envel').hide();
            }
        }

        if((battle_time === 0) && (loot_time === 0) && data.session.curr_dragons >= 3) {
            $('#dragon_panel').show();
            $('#enemy_panel').hide();
        } else {
            $('#dragon_panel').hide();
            $('#enemy_panel').show();
        }


        $('#player-table').empty();
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
        if(data.curr_player.player_id == usrid) {
            is_activeplayer = true;
        } else {
            is_activeplayer = false;

        }


        sorted_party = Object.values(data.curr_party);

        player_list_changed = false;
        shown_players = $(".playerlabel").map(function() { return { id:this.value, name:$(this).attr('typename') }; }).get();
        shown_players.forEach(function (element) {
            if(!sorted_party.some(function (x) {return x.id == element.id && x.name == element.name})) {
                player_list_changed = true;
                $(`#playerdiv${element.id}`).remove();
            }
        });

        $.each(sorted_party, function(key, element) {

            if($(`#playerdiv${element.id}`).length === 0) {
                $('#player_party').append(`<div id="playerdiv${element.id}" pid="${element.id}"><label><input type="checkbox" class="activeplayer-only playerlabel" id="party-checkbox${element.id}" name="party[]" value="${element.id}" onclick="process_checkbox(${element.id},\'party\');" typename="${element.name}">${element.name}</label></div>`);
                player_list_changed = true;
            }

            if(element.is_alive != curr_party[element.id].is_alive) {
                player_list_changed = true;
            }

            if(element.is_alive) {
                $(`#playerdiv${element.id}`).css('background', '');
           }else{
                $(`#playerdiv${element.id}`).css('background', 'coral');
            }

            if(selected_party.includes(element.id)) {
                $('#party-checkbox'+element.id).prop('checked', true);
            } else {
                $('#party-checkbox'+element.id).prop('checked', false);
            }

        });

        if(player_list_changed) {
            sortHTMLby('#player_party', 'div', function (a, b) {

                alive_dif = curr_party[b.getAttribute('pid')].is_alive - curr_party[a.getAttribute('pid')].is_alive;
                if (alive_dif === 0) {
                    return curr_party[a.getAttribute('pid')].partymember_type - curr_party[b.getAttribute('pid')].partymember_type;
                }

                return alive_dif;
            });
        }

        sorted_encounter = Object.values(data.curr_encounter);
        sorted_encounter.sort((a, b) => a.enemy_id - b.enemy_id);
        sorted_encounter.sort((a, b) => b.is_alive - a.is_alive);


        selected_potions = [];
        $('#encounter').empty();
        $.each(sorted_encounter, function(key, element) {

            $('#encounter').append('<div><label><input type="checkbox" class="activeplayer-only" name="enemies[]" id="enemy-checkbox'+element.id+'" value=\'' + element.id + '\' onclick="process_checkbox('+element.id+',\'enemy\');">' + element.name + '</label></div>');
            if(element.is_alive) {
                $('#enemy-checkbox'+element.id).parent().parent().css('background', '');
            } else {
                $('#enemy-checkbox'+element.id).parent().parent().css('background', 'coral');
            }

            if(selected_enemy.includes(element.id)) {
                if(element.name === "POTION" && element.is_alive) {
                    selected_potions.push('enemy|' + element.id);
                }
                $('#enemy-checkbox'+element.id).prop('checked', true);
            } else {
                $('#enemy-checkbox'+element.id).prop('checked', false);
            }
        });


        if(is_activeplayer) {
            $('.activeplayer-only').show();
        } else {
            $('.activeplayer-only').hide();

        }

        refresh_potion_state();
        repopulate_dead_checkers();

    }

    function login_form() {
        $('#login-form').show();
        $('#lobby').hide();
        $('#game').hide();
        $('#win_table').hide();
    }

    function reset_ready_button() {

        if(curr_ready) {
            $('#ready_button').text('Не готов');
        } else {
            $('#ready_button').text('Готов');
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

                if(returnedData.all_ready) {
                    reload_game();
                    clearInterval(interval);
                    interval = setInterval(reload_game, 1000);
                }

                returnedData.players.forEach(function(element) {
                    $('#lobby_players').append("<div class='usrstr'><p>" + element.name + "(" + element.character[0].name + ") " + (element.is_ready ? "ready" : "not ready") + "</p></div>");

                    if(element.player_id == usrid) {
                        curr_ready = element.is_ready;
                        reset_ready_button();
                    }

                });

            }
        });

    }

    function process_lobby_data(data) {

        form_greeting(data.user.name)

        if(data.sessions.length == 0) {
            $('#lobby_no_sessions').show();
            $('#lobby_session').hide();
        } else {
            $('#lobby_no_sessions').hide();
            $('#lobby_session').show();

            $('#lobby_session_name').text(data.sessions[0].name);
            session_id = data.sessions[0].id;
            party_types = data.party_types;
            enemy_types = data.enemy_types;

            reload_players();
            clearInterval(interval);
            interval = setInterval(reload_players, 1000);
        }

    }

    function show_lobby() {
        $('#login-form').hide();
        $('#lobby').show();
        $('#game').hide();
        $('#win_table').hide();

        $.ajax({
            url: '/ajax/lobby',
            type: 'POST',
            data: {usrid:usrid},
            cache: false,
            success: function (result) {
                var returnedData = JSON.parse(result);

                switch (returnedData.err) {
                    case 'NO_AUTH': login_form(); break;
                    case 'OK': process_lobby_data(returnedData);

                }



            }
        });


    }

    function show_win_table(data) {

        $('#login-form').hide();
        $('#lobby').hide();
        $('#game').hide();
        $('#win_table').show();

        $('#player-wintable').empty();

        curr_max = data.players[0].exp;

        $.each(data.players, function(key, element) {
            $('#player-wintable').append(
                '<tr><td id=\'winplayer'+ element.id + '\'>'
                + element.name
                + '('
                + element.character.name
                + ') (exp '
                + element.exp
                + ')</td></tr>');

            if(element.exp == curr_max) {

                $('#winplayer'+ element.id).css('background-color', '#f9ef99');

            }
        });
        $('#player'+ data.curr_player.player_id ).css("background-color", "#cceecc");
        if(data.curr_player.player_id == usrid) {
            is_activeplayer = true;
        } else {
            is_activeplayer = false;

        }

    }

    $( document ).ready(function() {
        show_lobby();

    });


</script>

</html>
