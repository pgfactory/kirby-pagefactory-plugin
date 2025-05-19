Hiding app folder of kirby app
==============================

- place .htaccess and index.php in root folder
- place app in subfolder, typically onair/

docroot/
    .htaccess
    index.php
    onair/
        kirby/
        content/
        ...


Legacy Installations:
---------------------
- place a redirect into onair/
- the app in _onair/

docroot/
    .htaccess
    index.php
    _onair/
        kirby/
        content/
        ...
    
    onair/
        .htaccess

