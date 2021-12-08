<?php
// Configuration file for PageFactory plugin

return [
    'handleKirbyFrontmatter'  => true,

    'assetFiles' => [
        '-pagefactory.css' => [
            'site/plugins/pagefactory/scss/autoload/*',
        ],
        '-pagefactory-async.css' => [
            'site/plugins/pagefactory/scss/autoload-async/*',
        ],
        '-styles.css' => [
            PFY_USER_ASSETS_PATH . 'autoload/*',
        ],
        '-styles-async.css' => [
            PFY_USER_ASSETS_PATH . 'autoload-async/*',
        ],

        '-pagefactory.js' => [
            'site/plugins/pagefactory/js/autoload/*',
        ],

        // prepare rest as individual files ready for explicit queueing/loading:
        '*' => [
            'site/plugins/pagefactory/scss/*',
            'site/plugins/pagefactory/third_party/jquery/jquery-3.6.0.min.js',
            'site/plugins/pagefactory/js/*',
        ],
    ],

    // Options for dev phase:
    'debug_compileScssWithLineNumbers'  => @(kirby()->options())['debug'],
];
