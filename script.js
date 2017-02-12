window.onload = function(){
    console.log('page loaded');

    if(!org || !admin){
        console.log("no admin and org specified");
        return;
    }

    var content = document.querySelector("#content");

    var excludeTableHeaders = getURLParam('exclude',location.href);
    if(excludeTableHeaders){
        excludeTableHeaders = excludeTableHeaders.split(",");
    }

    console.log("all good, continue");

    loadContent();

    function loadContent(){
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {

                var data = JSON.parse(this.responseText);
                parseRequest(data);

            }
        };
        xhttp.open("GET", "api.php?repolist=true&org="+org+"&admin="+admin, true);
        xhttp.send();
    }

    document.querySelector("#updateRepos").addEventListener("click", function(){
        document.querySelector(".loader").style.display = "block";

        var passwd = document.querySelector("#passwd").value;

        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                var data = JSON.parse(this.responseText);
                parseRequest(data);
            }
        };
        xhttp.open("GET", "api.php?repolist=true&org="+org+"&admin="+admin+"&updateRepos=true&passwd="+passwd, true);
        xhttp.send();
    });
    document.querySelector("#updateEvent").addEventListener("click", function(){
        document.querySelector(".loader").style.display = "block";

        var passwd = document.querySelector("#passwd").value;

        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                var data = JSON.parse(this.responseText);
                parseRequest(data);
            }
        };
        xhttp.open("GET", "api.php?repolist=true&org="+org+"&admin="+admin+"&updateEvents=true&passwd="+passwd, true);
        xhttp.send();
    });
    document.querySelector("#updateUsers").addEventListener("click", function(){
        document.querySelector(".loader").style.display = "block";

        var passwd = document.querySelector("#passwd").value;

        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                var data = JSON.parse(this.responseText);
                parseRequest(data);
            }
        };
        xhttp.open("GET", "api.php?repolist=true&org="+org+"&admin="+admin+"&updateUsers=true&passwd="+passwd, true);
        xhttp.send();
    });

    function parseRequest(data){
        //console.log(data);
        document.querySelector(".loader").style.display = "none";
        dataPerUsers = [];
        content.innerHTML = "";

        reOrderData(data);
        // sort by name/username
        dataPerUsers = dataPerUsers.sort(function(a,b) {
            if(a.name.toLowerCase() < b.name.toLowerCase()) return -1;
            if(a.name.toLowerCase() > b.name.toLowerCase()) return 1;
            return 0;
        });

        printTable(data);
    }

    function reOrderData(data){
        data.repos.forEach(function(repo, key){

            repo.openPulls.forEach(function(pull, key){
                addPullToUser(pull);
            });
            repo.closedPulls.forEach(function(pull, key){
                addPullToUser(pull);
            });

        });
    }

    function addPullToUser(pull){

        var userIndex = getUserIndex(createName(pull));

        //console.log(userIndex);

        if(userIndex != -1) {

            dataPerUsers[userIndex].pulls.push(pull);
            //console.log(dataPerUsers[i]);
        } else {

            var name = createName(pull);

            var newUser = {
                name: name,
                pulls: [],
            };
            newUser.pulls.push(pull);
            dataPerUsers.push(newUser);
        }
    }

    function createName(pull){
        var name = pull.user;
        if(pull.user_real_name) {
            name = pull.user_real_name + " ("+ pull.user +")";
        }

        return name;
    }

    function printTable(data){

        var queryDate = new Date(data.queryTime*1000);

        document.querySelector("#timeFetched").innerHTML = "Main repo and pull requests retrieved: " + queryDate + "<br><br>";

        //console.log(dataPerUsers);

        var table = document.createElement("table");
        table.border = "1px solid black";

        // HEADERS
        var headerRow = document.createElement("tr");
        var nameCol = document.createElement("th");
        nameCol.innerHTML = "usernames";
        headerRow.appendChild(nameCol);
        data.repos.forEach(function(repo, key){

            if(excludeTableHeaders && excludeTableHeaders.indexOf(repo.name) > -1){
                return;
            }

            var headerColumn = document.createElement("th");
            headerColumn.innerHTML = repo.name;
            headerRow.appendChild(headerColumn);
        });
        table.appendChild(headerRow);

        // CONTENT ROWS
        dataPerUsers.forEach(function(user, key){
            var row = document.createElement("tr");

            // name
            var col = document.createElement("td");
            col.innerHTML = user.name;
            row.appendChild(col);

            data.repos.forEach(function(repo){

                if(excludeTableHeaders && excludeTableHeaders.indexOf(repo.name) > -1){
                    return;
                }

                var currentLevel = [];
                var selectedIndex = -1;

                user.pulls.forEach(function(pull, index){
                    if(repo.name == pull.repo_name){
                        currentLevel.push(pull);
                        if(pull.valid){ selectedIndex = index; }
                    }
                });

                if(currentLevel.length > 0){

                    var col = document.createElement("td");
                    var link = document.createElement("a");
                    link.setAttribute('target','_blank');
                    var linkToPull = document.createElement("a");
                    linkToPull.setAttribute('target','_blank');
                    linkToPull.innerHTML = "(pull)";

                    // valid
                    if(selectedIndex > -1){

                        link.href = currentLevel[selectedIndex].user_repo_url;
                        linkToPull.href = currentLevel[selectedIndex].html_url;

                        if(currentLevel.length > 1){
                            link.innerHTML = formatDate(currentLevel[selectedIndex].closed_at) + " ("+currentLevel.length+")";
                        }else{
                            link.innerHTML = formatDate(currentLevel[selectedIndex].closed_at);
                        }

                        col.style.backgroundColor = "lightgreen";
                    }else{
                        // first should be latest
                        link.href = currentLevel[0].user_repo_url;
                        linkToPull.href = currentLevel[0].html_url;

                        link.innerHTML = formatDate(currentLevel[0].updated_at);
                    }
                    col.appendChild(link);
                    col.innerHTML += " | ";
                    col.appendChild(linkToPull);
                    row.appendChild(col);

                }else{
                    var col = document.createElement("td");
                    col.innerHTML = "--";
                    row.appendChild(col);
                }
            });

            table.appendChild(row);
        });

        content.appendChild(table);
    }

    function getUserIndex(testName){

        var index = -1;

        dataPerUsers.forEach(function(user, key){
            //console.log(user.name+" == "+testName+ " " + (user.name === testName));
            if(user.name == testName){
                index = key;
                return;
            }
        });
        //console.log(username);
        return index;
    }

    function formatDate(date) {

        date = new Date(date);

        var day = date.getDay();
        var monthIndex = date.getMonth();
        var year = date.getYear();

        return appendZero(day) + '.' + appendZero(monthIndex) + '.' + appendZero(year);
    }

    function appendZero(nr){
        if(nr < 10){
            nr = '0' + nr;
        }

        return nr;
    }

    //http://stackoverflow.com/a/15866004
    function getURLParam(key,target){
        var values = [];
        if(!target){
            target = location.href;
        }

        key = key.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");

        var pattern = key + '=([^&#]+)';
        var o_reg = new RegExp(pattern,'ig');
        while(true){
            var matches = o_reg.exec(target);
            if(matches && matches[1]){
                values.push(matches[1]);
            }
            else{
                break;
            }
        }

        if(!values.length){
             return null;
         }
        else{
           return values.length == 1 ? values[0] : values;
        }

    }

};
