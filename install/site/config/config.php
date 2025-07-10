<?php

if (!defined('PFY_DOCROOT')) {
    define('PFY_DOCROOT', $_SERVER['DOCUMENT_ROOT'].'/');
}
if (!defined('PFY_BASE_OFFSET')) { // if not defined in parent folder, offset is ''
    define('PFY_BASE_OFFSET', '');
}
if (!defined('PFY_APP_BASE_PATH')) {
    define('PFY_APP_BASE_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
}
if (!defined('PFY_KIRBY_BASE_PATH')) {
    define('PFY_KIRBY_BASE_PATH', PFY_APP_BASE_PATH . PFY_BASE_OFFSET);
}


// Defaults recommended by PageFactory plugin:
return [
    'debug' => false,
    'smartypants' => true,
    'languages' => true, // enables language option in Panel

    'thumbs' => [
        'interlace' => true,
        'format' => 'webp',
    ],

    // define Kirby's login mode, e.g. allow login by mailed access-code:
    // 'auth' => [
    //     'methods' => ['code','password']
    // ],
    // 'auth.challenge.email.from' => 'webmaster@domain.net',


    'pgfactory.markdownplus' => [
        // 'divblockChars'		=> '@%:',  // chars identifying DIV-Blocks, default is '@%'
        // 'accessCodeKey'      => 'key' , // URL-key to submit AccessCode, default: 'a' (e.g. ?a=ABCDEF)
        // 'autoConvertLinks'   => true,   // automatically convert URLs and email addresses to <link> tags
        // 'enableIcons'        => true,   // makes icons available in markdown
    ],

    'pgfactory.pagefactory' => [
        // 'defaultLanguage'               => 'de',   // multilang -> configure in panel instead! (Opt. use 'Code: de2' and 'PHP locale string: de_DE')
        // 'locale'                        => 'de_DE',// default: 'en_GB'
        // 'webmaster_email'               => 'webmaster@MY-DOMAIN.NET', // define a webmaster address
        // 'emailDevModeOverride'          => 'test@MY-DOMAIN.NET',     // email used for forms in dev mode
        // 'robots'                        => true,   // inject "robots" elem in HTML header
        // 'productionHostPathPattern'     => 'onair', // if defined, mismatch of (regex) pattern in URL triggers dev mode (docroot always assumed productive)
        // 'productionModeDataPath'        => '../production_mode_db/', // if defined, ~data/ is redirected here in production mode
        // 'lazyLoading'                   => false,   // disable lazy loading of images
        // 'favicon'                       => PFY_BASE_OFFSET.'assets/favicon/favicon.png', // defines source favicon file
        // 'excludeFilesRegex'             => '\.old\.md$',// regex pattern to exclude certain .md files from rendering
        // 'pfyPageSwipeEnabled'           => true,  // enables page switching by right and left swipes on touch devices
        // 'default-nav'                   => false,  // omit automatic loading of NAV resources
                // Note: normally, nav() is used in Twig template, but that's too late for loading assets.
                // Thus, Pfy loads NAV assets, unless option 'default-nav' is false
        // 'externalLinksToNewWindow'      => false,  // -> used by Link() -> whether to open external links in new window
        // 'imageAutoQuickzoom'            => false,  // -> default for Img() macro
        // 'imageAutoSrcset'               => false,  // -> default for Img() macro
        // 'includeMetaFileContent'        => false,  // -> option for website using '(include: *.md)' in metafile
                                                      // e.g. when converting from MdP site to Pfy
        // 'screenSizeBreakpoint'          => 480,    // Value used by JS to switch body classes ('pfy-large-screen' and 'pfy-small-screen')
        // 'supportExportAsIframe'         => '*',    // Enables Access-Control-Allow-Origin support, to activate use ?iframe

        // 'keepDbHistory'                 => 6,      // If true, old state is copied to dated file (e.g. /.history/xx) whenever 
                                                      // a DB is updated; int arg = number of month to keep
        // 'enablePageCache'               => true,   // -> caching of template variables, e.g. 'pageContent' etc.

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
