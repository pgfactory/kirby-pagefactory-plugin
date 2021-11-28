<?php

require_once __DIR__ . '/src/PageFactory.php';
require_once __DIR__ . '/src/MarkdownPlus.php';

use Kirby\Cms\App as Kirby;
use Usility\PageFactory\PageFactory as PageFactory;
use Usility\PageFactory\MarkdownPlus as MarkdownPlus;


Kirby::plugin('usility/pagefactory', [
    'pageMethods' => [
        'pageFactoryRender' => function($pages, $templateFile = false) {
            ob_start();
            $html = (new PageFactory($pages))->render($templateFile);
            if (strlen($buff = ob_get_clean ()) > 1) {
                $buff = strip_tags($buff);
                writeFile(PFY_LOGS_PATH . 'output-buffer.txt', $buff, FILE_APPEND);
            }
            print($html);
        }
    ],
    'components' => [
        'markdown' => function (Kirby $kirby, string $text = null, array $options = [], bool $inline = false) {
            return (new MarkdownPlus())->compile($text);
        }
    ],

    'routes' => [
        [
            // catch Tokens in URLs: (i.e. all captial letter codes)
            'pattern' => '(:all)',
            'action'  => function ($slug) {
                if (preg_match('|^(.*?) / ([A-Z]{5,15})$|x', $slug, $m)) {
                    $slug = $m[1];
                    return site()->visit($slug);
                }
                $this->next();
            }
        ],
    ],

//    'tags' => [
//        'lorem' => [
//            'attr' => [
//                'class'
//            ],
//            'html' => function($tag) {
//                return "<p class='{$tag->class}'>Lorem Ipsum dolor...</p>";
//            }
//        ],
//    ]
//
//    'fields' => [
//        'navigation' => [
//            'api' => require_once __DIR__ . '/config/api.php',
//            'props' => require_once __DIR__ . '/config/props.php',
//        ],
//    ],
//    'translations' => [
//        'en' => require_once __DIR__ . '/languages/en.php',
//        'de' => require_once __DIR__ . '/languages/de.php',
//        'tr' => require_once __DIR__ . '/languages/tr.php',
//    ],
//    'snippets' => [
//        'navigation' => __DIR__ . '/snippets/navigation.php'
//    ],
//    'fieldMethods' => require_once __DIR__ . '/config/methods.php',
]);
