<?php

return [
// uncomment as desired:
    'smartypants' => true,
    'languages' => true, // enables language option in Panel
//     'auth' => [
//         'methods' => ['password','code']
//     ],

    // PageFactory related hooks adding functionality:
    'hooks' => [
        // when user opens panel -> update .txt files according to .md content:
        'route:before' => function (Kirby\Http\Route $route, string $path) {
            if (strpos($path, 'panel/pages/') === 0) {
                require_once 'site/plugins/pagefactory/src/panelHelper.php';
                onPanelLoad($path);
            }
        },

        // create initial .md content file for newly created pages:
        'page.create:after' => function (Kirby\Cms\Page $page) {
            require_once 'site/plugins/pagefactory/src/panelHelper.php';
            onPageCreateAfter($page);
        },

        // after user modified page content via panel -> update .md-files:
        'page.update:after' => function (Kirby\Cms\Page $newPage, Kirby\Cms\Page $oldPage) {
            require_once 'site/plugins/pagefactory/src/panelHelper.php';
            onPageUpdateAfter($newPage);
        },
//        'file.update:after' => function (Kirby\Cms\File $newFile) {
//            require_once 'site/plugins/pagefactory/src/panelHelper.php';
//            onFileUpdateAfter($newFile);
//        },

    ], // hooks

    // panel custom styling:
    'panel' => [
        'css' => 'assets/pagefactory/css/custom-panel.css'
    ], // panel

];
