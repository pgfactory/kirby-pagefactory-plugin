<?php


return [
    'smartypants' => true,
    'languages' => true,
    'auth' => [
        'methods' => ['password','code']
    ],
    'hooks' => [
        // create first md content file for newly created page:
        'page.create:after' => function (Kirby\Cms\Page $page) {
            $root = $page->root();
            $name = $page->dirname();

            // get page title:
            $str = @file_get_contents(@(glob("$root/*.txt"))[0]);
            if ($str && preg_match('/Title: (.*)/', $str, $m)) {
                $title = $m[1];
            } else {
                $title = str_replace('-', ' ', $name);
                $title = ucfirst($title);
            }

            // create initial md content file:
            $md = "\n\n# $title\n\n";
            $mdFile = "$root/1_$name.md";
            file_put_contents($mdFile, $md);
        }
    ],

];
