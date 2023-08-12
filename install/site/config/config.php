<?php

// Uncomment to activate PageFactory's cache support:
//const PFY_MAX_CACHE_AGE = 86400; // [s] default: 1day = 86400

// find twig-functions in pagefactory:
$functions = false;
if (file_exists('site/plugins/pagefactory/src/TransVars.php')) {
    require_once 'site/plugins/pagefactory/src/TransVars.php';
    $functions = Usility\PageFactory\TransVars::findAllMacros(true);
}

// Defaults recommended by PageFactory plugin:
return [
    'debug' => false,
    'smartypants' => true,
    'languages' => true, // enables language option in Panel

    'amteich.twig.env.functions' => $functions, // register pagefactory's twig-functions
    //'amteich.twig.cache' => false,            // disable if necessary

    'pgfactory.pagefactory.options' => [
        'default-nav'   => true,            // set to true when using the built-in nav() function
        // 'defaultLanguage' => 'de',
        // 'webmaster-email'  => 'webmaster@sfs-meilen.ch',
        // 'maxCacheAge'     => PFY_MAX_CACHE_AGE,          // 1 day
        //'timezone'		 => 'Europe/Zurich', // Override PageFactory's guess if necessary
    ],

/* -> Uncomment to activate PageFactory's cache support
    'cache' => [
        'pages' => [
            'active' => true,
            'ignore' => function () {
                $cacheFlagFile = 'site/cache/pagefactory/last-cache-update.txt';
                $lastCacheRefresh = file_exists($cacheFlagFile) ? filemtime($cacheFlagFile) : 0;
                if (intval($lastCacheRefresh / PFY_MAX_CACHE_AGE) !== intval(time() / PFY_MAX_CACHE_AGE)) {
                    return true; // cache expired, don't cache, let PageFactory re-build pages
                }
                return false; // page may be cached
            }
        ],
    ],
*/
];
