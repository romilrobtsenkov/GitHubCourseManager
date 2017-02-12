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
    <title>Document</title>
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
        <input type="submit" value="Enter">
    </form>

    <br>

    <?php
        if(!empty($org) && !empty($admin)){
            echo "Organisatsioon: ".$org;
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
