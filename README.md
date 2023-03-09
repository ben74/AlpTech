###  AlpTech experimental tools, debug and functions !
- Contains various features, bugFixes / debug for drupal core / modules
---
        composer require alptech/wip;
        cp vendor/alptech/wip/test.php test.php;
---
Then run code sample 1

        php test.php '{"d":{"e":[4,5]}}' a=1 b=2 --c=3 --e=willEvaluateAllThoseParametersAs_HTTP_GET;
        # or 
        http://127.0.0.1/test.php?a=1&b=3


- Conf file is located at conf.php ( duplicated from default.conf ) at bootstrap
---
  - In order to use a simple request firewall :
 
          if (!fun::$local) {// Runs firewall in HTTP mode, try uploading a .php file, or some Obvious Injection Patterns
              $blocked = firewall();
              if ($blocked) die($blocked);
          }

- Copy vendor/alptech/wip/alptech.php to your directory root in order to test some things like : 
> - /alptech.php # loads framework, migrations, and debugs a catched error, then edit conf.php file which is a replica of default.conf.php ( mysql_user, password and database)
> - /alptech.php?a=logViewer& # in order to see those cumulated errors
- As it runs migrations and copies conf.php file which is the one where you want to place your settings, parameters, hostname etc ..

---

---
![visitors](https://visitor-badge.glitch.me/badge?page_id=gh:ben74:alpow:wip)
© 2023 <a href='//alptech.dev' title='alptech'>Alptech Technologies</a> 
