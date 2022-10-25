<?php
// Configuration file for PageFactory plugin

$menuIcon = svg('site/plugins/pagefactory/assets/icons/menu.svg');

return [
    'handleKirbyFrontmatter'        => false,
    'screenSizeBreakpoint'          => 480,
    'defaultLanguage'               => 'en',
    // 'defaultTargetForExternalLinks' => true,  // -> used by Link() -> whether to open external links in new window
    // 'allowCustomCode'               => true,  // -> used by Macro and Include
    // 'imageAutoQuickview'            => true,  // -> used by Img() macro
    // 'imageAutoSrcset'               => true,  // -> used by Img() macro

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
            'site/plugins/pagefactory/third_party/jquery/jquery-3.6.1.min.js',
            'site/plugins/pagefactory/js/*',
        ],
    ],

    'variables' => [
        'pfy-page-title' => '{{ page-title }} / {{ site-title }}',
        'webmaster-email' => 'webmaster@'.preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url()),
        'pfy-small-screen-header' => <<<EOT

        <h1>{{ site-title }}</h1>
        <button id="pfy-nav-menu-icon">$menuIcon</button>
EOT,
        'pfy-footer' => ' Footer',
    ],

    // Options for dev phase:
    'debug_compileScssWithLineNumbers'  => @(kirby()->options())['debug'],   // line numbers of original SCSS file
    'timezone' => 'Europe/Zurich', // Automatically set by PageFactory
];
