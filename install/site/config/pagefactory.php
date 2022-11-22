<?php
// Configuration file for PageFactory plugin

$menuIcon = svg('site/plugins/pagefactory/assets/icons/menu.svg');

return [
    'handleKirbyFrontmatter'        => false,
    'screenSizeBreakpoint'          => 480,
    'defaultLanguage'               => 'en',
    // 'allowCustomCode'               => true,  // -> used by Macro and Include
    // 'allowNonPfyPages'              => true,  // -> if true, Pagefactory will skip checks for presence of metafiles
    // 'defaultTargetForExternalLinks' => true,  // -> used by Link() -> whether to open external links in new window
    // 'imageAutoQuickview'            => true,  // -> used by Img() macro
    // 'imageAutoSrcset'               => true,  // -> used by Img() macro

    'variables' => [
        'pfy-page-title' => '{{ page-title }} / {{ site-title }}',
        'webmaster-email' => 'webmaster@'.preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url()),
        'pfy-small-screen-header' => <<<EOT

        <h1>{{ site-title }}</h1>
        <button id="pfy-nav-menu-icon">$menuIcon</button>
EOT,
        'pfy-footer' => ' Footer',
    ],

    // optionally define files to be used as css/js framework (e.g. jQuery or bootstrap etc):
//    'frontendFrameworkUrls' => [
//        'css' => 'assets/framework1.css, assets/framework12.css',
//        'js' => 'assets/framework1.js',
//    ],


    // Options for dev phase:
    'debug_compileScssWithLineNumbers'  => @(kirby()->options())['debug'],   // line numbers of original SCSS file
    // 'timezone' => 'Europe/Zurich', // PageFactory tries to guess the timezone - you can override this manually here
];
