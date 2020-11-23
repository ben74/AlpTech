###  AlpTech experimental tools, debug and functions !
- Contains various features, bugFixes / debug for drupal core / modules

        composer require alptech/wip
---
- Conf file is located at conf.php ( duplicated from default.conf ) at bootstrap
---
- Copy and paste this in order to use as simple firewall :
 
        if(isset($_SERVER['REQUEST_URI'])){
             require_once'vendor/autoload.php';use AlpTech\Wip\fun;
             $isblocked=fun::blockMaliciousRequests();
             if($isblocked){fun::r404($isblocked);}
             #fun::dbM($isblocked,'blocked','secu.log');#append to optional log file or send it to bus / logCollector
         }

- Copy vendor/alptech/wip/alptech.php to your directory root in order to test some things like : 
> - /alptech.php # loads framework, migrations, and debugs a catched error, then edit conf.php file which is a replica of default.conf.php ( mysql_user, password and database)
> - /alptech.php?a=logViewer& # in order to see those cumulated errors
- As it runs migrations and copies conf.php file which is the one where you want to place your settings, parameters, hostname etc ..

---

---
![visitors](https://visitor-badge.glitch.me/badge?page_id=gh:ben74:alpow:wip)
© 2020 <a href='//alptech.dev' title='alptech'>Alptech Technologies</a> 
