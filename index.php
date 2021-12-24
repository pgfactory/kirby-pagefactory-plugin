<?php

/**
 *
 * PageFactory for Kirby 3
 *
 * @version   0.2
 * @author    Dieter Stokar <https://pagefactory.info>
 * @copyright Usility GmbH <https://usility.ch>
 * @link      https://pagefactory.info
 * @license   MIT <https://opensource.org/licenses/MIT>
 */

require_once __DIR__ . '/src/PageFactory.php';
require_once __DIR__ . '/src/MarkdownPlus.php';

use Kirby\Cms\App as Kirby;
use Usility\PageFactory\PageFactory as PageFactory;


Kirby::plugin('usility/pagefactory', [
    'pageMethods' => [
        'pageFactoryRender' => function($pages, $options = false) {
            return (new PageFactory($pages))->render($options);
        }
    ],

    'routes' => [
        [
            // catch tokens in URLs: (i.e. all capital letter codes like 'p1/ABCDEF')
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
]);
