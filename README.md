###  AlpTech experimental tools, debug and functions !
- Contains various features, bugFixes / debug for drupal core / modules

        composer require alptech/wip
---
- Copy and paste this in order to use as simple firewall :
 
        if(isset($_SERVER['REQUEST_URI'])){
             require_once'vendor/autoload.php';use AlpTech\Wip\fun;
             $isblocked=fun::blockMaliciousRequests();
             if($isblocked){fun::r404($isblocked);}
             #fun::dbM($isblocked,'blocked','secu.log');#append to optional log file or send it to bus / logCollector
         }
- Edit conf.php in order to place your own variables ( logfolder, logcollector, thumbnails path, authorized thumbnails dimensional parameters )


---

---
![visitors](https://visitor-badge.glitch.me/badge?page_id=gh:ben74:alpow:wip)
© 2020 <a href='//alptech.dev' title='alptech'>Alptech Technologies</a> 
