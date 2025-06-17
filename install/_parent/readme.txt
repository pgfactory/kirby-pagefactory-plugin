Hiding app folder of kirby app
==============================

- place .htaccess and index.php in root folder
- place app in subfolder, typically onair/
- remove onair/.htaccess (from subfolder)

docroot/
    .htaccess
    index.php
    onair/
        assets/
        kirby/
        content/
        ...
        #.htaccess


Legacy Installations:
---------------------
- place a redirect into onair/
- the app in _onair/

docroot/
    .htaccess
    index.php
    _onair/
        assets/
        kirby/
        content/
        ...
        #.htaccess
    
    onair/
        .htaccess  *)

*) content of legacy onair/.htaccess:

# requests to domain.net/onair are (permanently) redirecte to root folder domain.net/
RewriteEngine on
RewriteRule (.*) /$1 [R=301,L]

