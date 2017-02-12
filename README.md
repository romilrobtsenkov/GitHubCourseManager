# GitHubCourseManager

Requirements
- 3 text files for working with writing permission for php files
- config file
- GitHub token (get here)[https://github.com/settings/tokens]

**NB! When first running application, text files must contain `[]`**

**config.php contents**
```PHP
<?php
    $token = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    $file_name = "data.txt";
    $events_file_name = "events.txt";
    $users_file_name = "users.txt";
    $passwd = "password";
?>
```

When using, links can be shared 
- org (github organisation name, used to find and list all its repos) 
- admin (course teacher, used to validate if pull request is closed by teacher)
- exclude (excludes columns from table in UI)
```
/view.php?org=ORGANISATION_NAME&admin=COURSE_TEACHER&exclude=REPOSITORY1,REPOSITORY2
```
After updating data all commands must be done in order
  1. repos and pull requests
  2. validatation
  3. user real names (optional)
