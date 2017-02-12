<?php
    require('config.php');
    // Contains $token ="XXXXXX"; allows up to 5000 req/hr can be generated https://github.com/settings/tokens
    // public_repo  Access public repositories is only neccessery to mark

    $reposData = new StdClass();

    // GITHUB api requires browser simulation
    $options  = array('http' => array('user_agent'=> $_SERVER['HTTP_USER_AGENT'], 'header' => "Authorization: token ".$token));
    $context  = stream_context_create($options);

    // GET REPOS
    if(!isset($_GET["repolist"]) || !isset($_GET["org"]) || !isset($_GET["admin"])){ echo "no query"; return; }

    $fileData = json_decode(file_get_contents($file_name));
    $eventsHistory = json_decode(file_get_contents($events_file_name));
    $userData = json_decode(file_get_contents($users_file_name));

    // for optimization, do not save if not changed
    $eventsHistory_length = count($eventsHistory);
    $userData_length = count($userData);

    foreach ($fileData as $key => $org) {

        // if less than 200s passed for this organisation
        if($org->org == $_GET["org"] && (strtotime(date('c')) - intval($org->queryTime)) < 200){
            //echo "<pre>";
            //var_dump($org);
            //echo "</pre>";

            echo json_encode($org);
            return;

        }elseif ($org->org == $_GET["org"]) {
            // remember index to update
            $orgIndex = $key;

        }

    }

    // update data

    $repos = json_decode(file_get_contents("https://api.github.com/orgs/".$_GET["org"]."/repos", false, $context));

    if(count($repos) < 1){

        // try to show from file
        foreach ($fileData as $key => $org) {

            // if less than 100s passed for this organisation
            if($org->org == $_GET["org"]){
                //echo "<pre>";
                //var_dump($org);
                //echo "</pre>";

                echo json_encode($org);
                return;
            }
        }
        return;
    }

    $reposData->org = $_GET["org"];
    $reposData->admin = $_GET["admin"];
    $reposData->repos = array();
    $reposData->queryTime = strtotime(date('c'));

    foreach ($repos as $key => $repo) {
        getSingleRepoData($_GET["org"], $repo->name);

        //validate pull requests if valid
        validatePulls($key);
    }



    // replace with updated data
    if (isset($orgIndex)) {
        $fileData[$orgIndex] = $reposData;
    } else {
        // add new org to array
        array_push($fileData, $reposData);
    }

    file_put_contents($file_name, json_encode($fileData));

    if(count($eventsHistory) > $eventsHistory_length){
        // also update events history
        echo "UPDATED EVENTS FILE <br>";
        file_put_contents($events_file_name, json_encode($eventsHistory));
    }

    if(count($userData) > $userData_length){
        // also update users
        echo "UPDATED USERS FILE <br>";
        file_put_contents($users_file_name, json_encode($userData));
    }

    //echo "<pre>";
    //var_dump($fileData);
    //echo "</pre>";

    echo json_encode($reposData);

    return;


function getSingleRepoData($org, $repoName){
    $open = json_decode(file_get_contents("https://api.github.com/repos/".$org."/".$repoName."/pulls", false, $GLOBALS["context"]));
    $closed = json_decode(file_get_contents("https://api.github.com/repos/".$org."/".$repoName."/pulls?state=closed", false, $GLOBALS["context"]));

    //var_dump($open);
    //var_dump($closed);

    $o = new StdClass();
    $o->name = $repoName;
    $o->openPulls = array();
    $o->closedPulls = array();

    foreach ($open as $key => $pull) {

        $p = new StdClass();

        $p->number = $pull->number;
        $p->user = $pull->user->login;
        $p->user_real_name = getRealName($pull->user->login);
        $p->url = $pull->url;
        $p->html_url = $pull->html_url;
        $p->created_at = $pull->created_at;
        $p->updated_at = $pull->updated_at;
        $p->repo_name = $repoName;
        $p->user_repo_url = $pull->head->repo->html_url;

        array_push($o->openPulls, $p);
    }

    foreach ($closed as $key => $pull) {

        $p = new StdClass();

        $p->number = $pull->number;
        $p->user = $pull->user->login;
        $p->user_real_name = getRealName($pull->user->login);
        $p->url = $pull->url;
        $p->html_url = $pull->html_url;
        $p->created_at = $pull->created_at;
        $p->updated_at = $pull->updated_at;
        $p->closed_at = $pull->closed_at;
        $p->repo_name = $repoName;
        $p->user_repo_url = $pull->head->repo->html_url;

        array_push($o->closedPulls, $p);
    }

    getAllNewRepoEvents($_GET["org"], $repoName);

    array_push($GLOBALS["reposData"]->repos, $o);

}

function getAllNewRepoEvents($org, $repoName){

    $new = true;
    $page = 1;

    while ($new) {

        //echo "started page ".$page." <br>";

        $events = json_decode(file_get_contents("https://api.github.com/repos/".$org."/".$repoName."/issues/events?event=closed&page=".$page, false, $GLOBALS["context"]));

        $had_new = false;

        foreach ($events as $key => $newEvent) {
            if(!in_array($newEvent, $GLOBALS["eventsHistory"])){
                array_push($GLOBALS["eventsHistory"], $newEvent);

                //echo "added new event <br>";
                $had_new = true;
            }
        }

        if($had_new){
            // atleast one new on this page, get next page events
            $page += 1;
        }else{
            // stop event query
            $new = false;
        }
    }

    return;

}

function validatePulls($index) {

    foreach ($GLOBALS["reposData"]->repos[$index]->closedPulls as $key => $pull) {

        foreach ($GLOBALS["eventsHistory"] as $key => $event) {

            /// if admin closed current pull
            if($event->actor->login == $GLOBALS["reposData"]->admin && $pull->html_url == $event->issue->pull_request->html_url){

                $pull->valid = true;
                //echo "validated <br>";
            }

        }
    }
}

function getRealName($username){

    foreach ($GLOBALS["userData"] as $key => $u) {
        if($u->username == $username && !is_null($u->user_real_name)){
            return $u->user_real_name;
        }
    }

    // retrieve once in 4 hours, otherwise return null
    if (isset($GLOBALS["orgIndex"]) && (strtotime(date('c')) - intval($GLOBALS["fileData"][$GLOBALS["orgIndex"]]->queryTime)) < 14400) {
        return null;
    }

    // missing, get real name
    $userData = json_decode(file_get_contents("https://api.github.com/users/".$username, false, $GLOBALS["context"]));

    if(!is_null($userData->name) ){

        $u = new StdClass();
        $u->username = $username;
        $u->user_real_name = $userData->name;
        $u->html_url = $userData->html_url;

        array_push($GLOBALS["userData"], $u);

        return $userData->name;
    }else{
        return null;
    }
}

?>
