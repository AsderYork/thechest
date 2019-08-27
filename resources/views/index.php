<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <title>The Chest</title>
</head>
<body>
<h1>Добро пожаловать, <?php echo $name; ?></h1>
<form action="\create_session" method="GET">
    <input hidden name="usrid" value="<?php echo $userid; ?>">
    <input hidden name="char" value="1">
    <button type="submit">Создать новую сессию</button>
</form>
<form action="\connect_to">
    <input hidden name="usrid" value="<?php echo $userid; ?>">
    <input hidden name="char" value="1">
    <input type="text" name="session">Код сессии<Br>
    <button type="submit">Подключиться</button>
</form>
<div><h1 id="session-header"></h1></div>


    <div id="currlobby">
        <p id="currlobby_header">Участники:</p>
        <button id="ready_button" onclick="swap_ready(); return false;">Готов</button>
    </div>
</body>

<script>
    var usrid = <?php echo $userid; ?>;
    var usrname = '<?php echo $name; ?>';
    var $session = jQuery.parseJSON('<?php echo $session; ?>');
    var curr_ready = 0;

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

                    console.log(parseddata);
                    $('.usrstr').remove();
                    $.each(parseddata, function(key, element) {

                        if(element.id == usrid) {
                            curr_ready = element.is_ready;
                        }

                        $('#currlobby_header').append("<div class='usrstr'><p>" + element.name + "(" + element.character[0].name + ") " + (element.is_ready ? "ready" : "not ready") + "</p></div>");
                        console.log(element);
                    });

                } else {
                    $('#currlobby').hide();
                }


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

        if($session != undefined) {
            $('#session-header').text($session.name);
        }

        refresh_lobby();
    });
    
</script>

</html>


