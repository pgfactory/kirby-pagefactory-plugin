<?php

if (!defined('PFY_DOCROOT')) {      // possibly defined in index.php of parent folder
    define('PFY_DOCROOT', dirname($_SERVER['SCRIPT_FILENAME']) . '/');
}
if (!defined('PFY_BASE_OFFSET')) { // possibly defined in index.php of parent folder
    define('PFY_BASE_OFFSET', '');
}

define('PFY_APP_BASE_PATH', PFY_DOCROOT . PFY_BASE_OFFSET);


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
    // 'auth.challenge.email.from' => 'webmaster@domain.net',


    'pgfactory.markdownplus.options' => [
        // 'divblockChars'		=> '@%:',  // chars identifying DIV-Blocks, default is '@%'
        // 'accessCodeKey'      => 'key' , // URL-key to submit AccessCode, default: 'a' (e.g. ?a=ABCDEF)
        // 'autoConvertLinks'   => true,   // automatically convert URLs and email addresses to <link> tags
    ],

    'pgfactory.pagefactory.options' => [
        // 'defaultLanguage'               => 'de',   // multilang -> configure in panel instead! (Opt. use 'Code: de2' and 'PHP locale string: de_DE')
        // 'robots'                        => true,   // inject "robots" elem in HTML header
        // 'excludeFilesRegex'             => '\.old\.md$',// regex pattern to exclude certain .md files from rendering
        // 'locale'                        => 'fr_FR',// if not defined, locale is derived from agent request
        // 'default-nav'                   => false,  // omit automatic loading of NAV resources
                // Note: normally, nav() is used in Twig template, but that's too late for loading assets.
                // Thus, Pfy loads NAV assets, unless option 'default-nav' is false
        // 'externalLinksToNewWindow'      => false,  // -> used by Link() -> whether to open external links in new window
        // 'imageAutoQuickview'            => false,  // -> default for Img() macro
        // 'imageAutoSrcset'               => false,  // -> default for Img() macro
        // 'includeMetaFileContent'        => false,  // -> option for website using '(include: *.md)' in metafile
                                                      // e.g. when converting from MdP site to Pfy
        // 'screenSizeBreakpoint'          => 480,    // Value used by JS to switch body classes ('pfy-large-screen' and 'pfy-small-screen')
        // 'supportExportAsIframe'         => '*',    // Enables Access-Control-Allow-Origin support, to activate use ?iframe

        // 'keepDbHistory'                 => 6,      // If true, old state is copied to dated file (e.g. /.history/xx) whenever 
                                                      // a DB is updated; int arg = number of month to keep
        // 'enablePageCache'                 => true,  // -> caching of template variables, e.g. 'pageContent' etc.

        // Options for dev phase:
        // 'debug_checkMetaFiles'          => true,   // if true, Pagefactory will skip checks for presence of metafiles
        // 'debug_compileScssWithSrcRef'   => true,   // injects ref to source SCSS file&line in compiled CSS
        // 'debug_logIP'                   => true,   // if true, serverLog() includes agent's IP address
    ],

/* Enable Kirby-Cache support:
    // note: caching always disabled while in debug mode.
    'cache' => [
        'pages' => [
            'active' => true,
            'ignore' => function () {
                $cacheFlagFile = 'site/cache/pagefactory/last-cache-update.txt';
                $lastCacheRefresh = file_exists($cacheFlagFile) ? filemtime($cacheFlagFile) : 0;
                if (date('d', $lastCacheRefresh) !== date('d')) {
                    return true; // cache expired, don't cache, let PageFactory re-build pages
                }
                return false; // page may be cached
            }
        ],
    ],
*/
];
