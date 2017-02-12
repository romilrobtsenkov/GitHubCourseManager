<?php

    $org = $admin = "";

    if(isset($_GET["org"]) && isset($_GET["admin"])){
        $org = $_GET["org"];
        $admin = $_GET["admin"];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>GitHub manager</title>
    <script>
        var org = "<?=$org;?>";
        var admin = "<?=$admin;?>";

        var dataPerUsers = [];
    </script>
    <script src="script.js" charset="utf-8"></script>
</head>
<body>

    <form method="get">
        <input type="text" name="org" placeholder="github organisation name" value="<?=$org;?>">
        <input type="text" name="admin" placeholder="admin" value="<?=$admin;?>">
        <input type="submit" value="Retrieve">
    </form>

    <br>
    <input type="password" id="passwd" placeholder="password">
    <button id="updateRepos" type="button" name="button">Update repos and pull requests</button>
    <button id="updateEvents" type="button" name="button">Validate pull requests</button>
    <button id="updateUsers" type="button" name="button">Update user real names</button>
    <br>

    <?php
        if(!empty($org) && !empty($admin)){
            echo "Organisation: ".$org;
            echo "<br>";
            echo "Admin: ".$admin;
            echo "<br>";
        }
    ?>
    <div id="timeFetched"></div>

    <div class="loader">
        <img src="default.gif" alt="">
    </div>

    <div id="content"></div>
</body>
</html>
