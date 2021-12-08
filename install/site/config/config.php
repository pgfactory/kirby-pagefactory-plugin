<?php


return [
// uncomment as desired:
//     'smartypants' => true,
//     'languages' => true,
//     'auth' => [
//         'methods' => ['password','code']
//     ],
    'hooks' => [
        // create initial .md content file for newly created pages:
        'page.create:after' => function (Kirby\Cms\Page $page) {
            $filename = 'a_'.$page->dirname().'.md';
            $md = "\n\n# ".site()->title()->value()."\n\n";
            file_put_contents($page->root().'/'.$filename, $md);
        }
    ],

];
