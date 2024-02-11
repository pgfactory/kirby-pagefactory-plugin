<?php

// find twig-functions in pagefactory:
$functions = false;
if (file_exists('site/plugins/pagefactory/src/TransVars.php')) {
    require_once 'site/plugins/pagefactory/src/TransVars.php';
    $functions = PgFactory\PageFactory\TransVars::findAllMacros(true);
}

// Defaults recommended by PageFactory plugin:
return [
    'debug' => false,
    'smartypants' => true,
    'languages' => true, // enables language option in Panel

    'thumbs' => [
        'interlace' => true,
    ],

    // define Kirby's login mode, e.g. allow login by mailed access-code:
    // 'auth' => [
    //     'methods' => ['code','password']
    // ],

    'wearejust.twig.env.functions' => $functions, // register pagefactory's twig-functions

    'pgfactory.markdownplus.options' => [
        // 'divblockChars'		=> '@%:', // chars identifying DIV-Blocks, default is '@%'
    ],

    'pgfactory.pagefactory.options' => [
        // 'defaultLanguage'               => 'de',   // multilang -> configure in panel instead! (Opt. use 'Code: de2' and 'PHP locale string: de_DE')
        // 'default-nav'                   => false,  // omit automatic loading of NAV resources
                // Note: normally, nav() is used in Twig template, but that's too late for loading assets.
                // Thus, Pfy loads NAV assets, unless option 'default-nav' is false
        // 'robots'                        => true,   // inject "robots" elem in HTML header
        // 'externalLinksToNewWindow'      => false,  // -> used by Link() -> whether to open external links in new window
        // 'imageAutoQuickview'            => false,  // -> default for Img() macro
        // 'imageAutoSrcset'               => false,  // -> default for Img() macro
        // 'includeMetaFileContent'        => false,  // -> option for website using '(include: *.md)' in metafile
                                                      // e.g. when converting from MdP site to Pfy
        // 'screenSizeBreakpoint'          => 480,    // Value used by JS to switch body classes ('pfy-large-screen' and 'pfy-small-screen')
        // 'sourceWrapperTag'              => 'section', // tag used to wrap .md content
        // 'sourceWrapperClass'            => '',     // class applied to sourceWrapperTag
        // 'webmaster_email'               => '',     // email address of webmaster (-> reset cache if modified!)
        // 'maxCacheAge'                   => 86400,  // [s] max time after which Kirby's file cache is automatically flushed
        // 'supportExportAsIframe'           => '*',  // Enables Access-Control-Allow-Origin support, to activate use ?iframe

      // Options for dev phase:
        // 'debug_checkMetaFiles'          => true,   // if true, Pagefactory will skip checks for presence of metafiles
        // 'debug_compileScssWithSrcRef'   => true,   // injects ref to source SCSS file&line in compiled CSS
        // 'debug_logIP'                   => true,   // if true, serverLog() includes agent's IP address
    ],

// pgfactory.pagefactory-elements.options

/* Cache support:
    'cache' => [
        'pages' => [
            'active' => true,
            'ignore' => function () {
                $cacheFlagFile = 'site/cache/pagefactory/last-cache-update.txt';
                $lastCacheRefresh = file_exists($cacheFlagFile) ? filemtime($cacheFlagFile) : 0;
                // if not daily refresh, try using commented line:
                // if (intval($lastCacheRefresh / 86400) !== intval(time() / 86400)) {
                if (date('d', $lastCacheRefresh) !== date('d')) {
                    return true; // cache expired, don't cache, let PageFactory re-build pages
                }
                return false; // page may be cached
            }
        ],
    ],
*/
];
