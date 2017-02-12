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

// for  later optimization, do not save if not changed
$eventsHistory_length = count($eventsHistory);
$userData_length = count($userData);

if(isset($_GET["org"]) && isset($_GET["updateRepos"]) && isset($_GET["passwd"]) && $_GET["passwd"] == $passwd){
    //echo "updated repos";

    $existingData = returnRepoData($_GET["org"]);
    if(!$existingData){ echo '{"error": "no org data"}'; return; }

    getRepoData($existingData->org, $existingData->admin);

    echo json_encode(returnRepoData($existingData->org));

    return;
}

if(isset($_GET["org"]) && isset($_GET["admin"]) && isset($_GET["updateEvents"]) && isset($_GET["passwd"]) && $_GET["passwd"] == $passwd){
    //echo "updated events and pull";

    $existingData = returnRepoData($_GET["org"]);
    if(!$existingData){ echo '{"error": "no org data"}'; return; }

    foreach ($existingData->repos as $key => $repo) {
        getAllNewRepoEvents($_GET["org"], $repo->name);
        validatePulls($key, $_GET["admin"]);
    }

    // if new events
    if(count($eventsHistory) > $eventsHistory_length){
        // also update events history
        //echo "UPDATED EVENTS FILE <br>";
        file_put_contents($events_file_name, json_encode($eventsHistory));
    }

    //update valid pull list
    $fileData[$orgIndex] = $existingData;
    file_put_contents($file_name, json_encode($fileData));

    echo json_encode(returnRepoData($existingData->org));

    return;
}

if(isset($_GET["org"]) && isset($_GET["updateUsers"]) && isset($_GET["passwd"]) && $_GET["passwd"] == $passwd){
    //echo "updated users";

    $existingData = returnRepoData($_GET["org"]);
    if(!$existingData){ echo '{"error": "no org data"}'; return; }

    foreach ($existingData->repos as $repo) {
        foreach ($repo->openPulls as $open) {
            if(!$open->user_real_name){
                $open->user_real_name = updateUserName($open->user);
            }
        }
        foreach ($repo->closedPulls as $closed) {
            if(!$closed->user_real_name){
                $closed->user_real_name = updateUserName($closed->user);
            }
        }
    }

    if(count($userData) > $userData_length){
        // if any new names
        //echo "UPDATED USERS AND MAIN FILE <br>";
        file_put_contents($users_file_name, json_encode($userData));

        // also update main file
        $fileData[$orgIndex] = $existingData;
        file_put_contents($file_name, json_encode($fileData));
    }

    echo json_encode(returnRepoData($existingData->org));

    return;
}

// ELSE

// just return repo data
$existingData = returnRepoData($_GET["org"]);
if($existingData){
    echo json_encode($existingData);
}else{
    // get latest main data
    echo json_encode(getRepoData($_GET["org"], $_GET["admin"]));
}

/*
    FUNCTIONS
*/

function returnRepoData($org_name){

    foreach ($GLOBALS["fileData"] as $key => $org) {
        // if less than 200s passed for this organisation
        if($org->org == $org_name && (strtotime(date('c')) - intval($org->queryTime)) < 20000000){
            $GLOBALS["orgIndex"] = $key;
            return $org;
        }
    }

    return null;
}

function getRepoData($getOrg, $getAdmin){

    $repos = json_decode(file_get_contents("https://api.github.com/orgs/".$getOrg."/repos", false, $GLOBALS["context"]));

    if(count($repos) < 1){
        return json_encode(new StdClass());
    }

    $GLOBALS["reposData"]->org = $getOrg;
    $GLOBALS["reposData"]->admin = $getAdmin;
    $GLOBALS["reposData"]->repos = array();
    $GLOBALS["reposData"]->queryTime = strtotime(date('c'));

    foreach ($repos as $key => $repo) {
        getSingleRepoData($getOrg, $repo->name);

    }

    // replace with updated data
    if (isset($GLOBALS["orgIndex"])) {
        $GLOBALS["fileData"][$GLOBALS["orgIndex"]] = $GLOBALS["reposData"];
    } else {
        // add new org to array
        array_push($GLOBALS["fileData"], $GLOBALS["reposData"]);
    }

    file_put_contents($GLOBALS["file_name"], json_encode($GLOBALS["fileData"]));

    return $GLOBALS["reposData"];
}

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

    //getAllNewRepoEvents($_GET["org"], $repoName);

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

function validatePulls($index, $admin) {

    foreach ($GLOBALS["existingData"]->repos[$index]->closedPulls as $key => $pull) {

        foreach ($GLOBALS["eventsHistory"] as $key => $event) {

            /// if admin closed current pull
            if($event->actor->login == $admin && $pull->html_url == $event->issue->pull_request->html_url){

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
    return null;
}

function updateUserName($username){
    $ud = json_decode(file_get_contents("https://api.github.com/users/".$username, false, $GLOBALS["context"]));

    if(!is_null($ud->name) ){

        $u = new StdClass();
        $u->username = $username;
        $u->user_real_name = $ud->name;
        $u->html_url = $ud->html_url;

        array_push($GLOBALS["userData"], $u);

        //echo ($ud->name." ".count($GLOBALS["userData"])." <br>");

        return $ud->name;
    }else{
        return null;
    }
}

?>
