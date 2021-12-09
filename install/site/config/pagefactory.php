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
        '-app.css' => [
            PFY_USER_ASSETS_PATH . 'autoload/*',
        ],
        '-app-async.css' => [
            PFY_USER_ASSETS_PATH . 'autoload-async/*',
        ],
        '-app.js' => [
            PFY_USER_ASSETS_PATH . 'autoload/*',
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
    'variables' => [
        'lzy-page-title' => '{{ page-title }} / {{ site-title }}',
        'webmaster-email' => 'webmaster@'.preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url()),
    ],

    // Options for dev phase:
    'debug_compileScssWithLineNumbers'  => @(kirby()->options())['debug'],
];
