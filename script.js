window.onload = function(){
    console.log('page loaded');

    var listOfAllRepos = [];

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
        update("updateRepos");
    });
    document.querySelector("#updateEvents").addEventListener("click", function(){
        update("updateEvents");
    });
    document.querySelector("#updateUsers").addEventListener("click", function(){
        update("updateUsers");
    });

    function update(eventName){

        var passwd = document.querySelector("#passwd").value;
        if(!passwd) {alert('add passwd'); return;}

        document.querySelector(".loader").style.display = "block";

        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                var data = JSON.parse(this.responseText);
                parseRequest(data);
            }
        };
        xhttp.open("GET", "api.php?repolist=true&org="+org+"&admin="+admin+"&"+eventName+"=true&passwd="+passwd, true);
        xhttp.send();
    }

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

        updateCloneLink();
    }

    function updateCloneLink(){
        var queryStringOpen = '';
        var queryStringClosed = '';
        var queryStringAll = '';


        listOfAllRepos.forEach(function(obj, key){
            if(key !== 0){
                queryStringAll += ' && ';
            }

            if(obj.closed){
                if(queryStringClosed !== ''){
                    queryStringClosed += ' && ';
                }
                queryStringClosed += 'git clone '+obj.repo+'.git';

            }else{
                if(queryStringOpen !== ''){
                    queryStringOpen += ' && ';
                }
                queryStringOpen += 'git clone '+obj.repo+'.git';
            }

            queryStringAll += 'git clone '+obj.repo+'.git';
        });

        document.querySelector('#cloneOpen').value = queryStringOpen;
        document.querySelector('#cloneClosed').value = queryStringClosed;
        document.querySelector('#cloneAll').value = queryStringAll;
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
                username: pull.user,
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
            headerColumn.innerHTML = '<a target="_blank" href="http://github.com/'+org+'/'+repo.name+'">'+repo.name+'</a>';
            headerRow.appendChild(headerColumn);
        });
        table.appendChild(headerRow);

        // CONTENT ROWS
        dataPerUsers.forEach(function(user, key){
            var row = document.createElement("tr");

            // name
            var col = document.createElement("td");
            col.innerHTML = '<a target="_blank" href="http://github.com/'+user.username+'">'+user.name+'</a>';
            row.appendChild(col);

            data.repos.forEach(function(repo){

                if(excludeTableHeaders && excludeTableHeaders.indexOf(repo.name) > -1){
                    return;
                }

                var currentLevel = [];
                var selectedIndex = -1;
                //console.log(selectedIndex);

                //add pulls to users
                user.pulls.forEach(function(pull, index){
                    if(repo.name == pull.repo_name){
                        currentLevel.push(pull);
                        if(pull.valid){ selectedIndex++; }
                    }
                });

                //console.log(user.pulls);

                if(currentLevel.length > 0){

                    var col = document.createElement("td");
                    var link = document.createElement("a");
                    link.setAttribute('target','_blank');
                    var linkToPull = document.createElement("a");
                    linkToPull.setAttribute('target','_blank');
                    linkToPull.innerHTML = "(pull)";

                    // valid
                    if(selectedIndex > -1){
                        //console.log(currentLevel);
                        //console.log(currentLevel[selectedIndex]);

                        link.href = currentLevel[selectedIndex].user_repo_url;
                        linkToPull.href = currentLevel[selectedIndex].html_url;
                        listOfAllRepos.push({"closed":true, "repo":currentLevel[selectedIndex].user_repo_url});

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
                        listOfAllRepos.push({"closed":false, "repo":currentLevel[0].user_repo_url});


                        link.innerHTML = formatDate(currentLevel[0].updated_at);

                    }
                    col.appendChild(link);
                    col.innerHTML += " | ";
                    col.appendChild(linkToPull);
                    row.appendChild(col);

                }else{
                    var emptycol = document.createElement("td");
                    emptycol.innerHTML = "--";
                    row.appendChild(emptycol);
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

        var day = date.getDate();
        var monthIndex = date.getMonth()+1;

        return appendZero(day) + '.' + appendZero(monthIndex);
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
