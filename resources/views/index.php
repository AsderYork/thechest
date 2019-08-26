<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
</body>
</html>
