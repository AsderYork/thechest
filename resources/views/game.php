<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <title>The Chest</title>
</head>
<body>
<table id="player-table">
    <tr >
        <td>
            Игроки
        </td>
    </tr>
</table>
<br>
<br>
    <form action="/game" method="get">
        <input hidden type="text" name="usrid" value="<?php echo $params['usrid']; ?>">
        <input hidden type="text" name="session" value="<?php echo $params['session']; ?>">
        <div id="playerparty-div">
            <p>Команда игрока</p>
        </div>

        <br>
        <br>
        <div id="encounter-div">
            <p>Противник</p>
        </div>
        <input type="submit">
    </form>
</body>

<script>

    $( document ).ready(function() {

        $players = JSON.parse('<?php echo json_encode($players); ?>');
        $curr_player = JSON.parse('<?php echo json_encode($curr_player); ?>');
        $curr_encounter = JSON.parse('<?php echo json_encode($curr_encounter); ?>');
        $curr_party = JSON.parse('<?php echo json_encode($curr_party); ?>');

        console.log($curr_party);

        $.each($players, function(key, element) {
            $('#player-table').append(
                '<tr><td id=\'player'+ element.id + '\'>'
                + element.name
                + '('
                + element.character.name
                + ') (exp '
                + element.exp
                + ')</td></tr>');
        });

        $.each($curr_party, function(key, element) {
            $('#playerparty-div').append('<div><input type="checkbox" name="party[]" value="' + element.id + '">' + element.name + '</div>');
        });

        $.each($curr_encounter, function(key, element) {
            $('#encounter-div').append('<div><input type="checkbox" name="enemies[]" value=\'' + element.id + '\'>' + element.name + '</div>');
        });


    });

</script>

</html>
