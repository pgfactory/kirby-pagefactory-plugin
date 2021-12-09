<?php

/**
 *
 * PageFactory for Kirby 3
 *
 * @version   0.1
 * @author    Dieter Stokar <https://pagefactory.info>
 * @copyright Usility GmbH <https://usility.ch>
 * @link      https://pagefactory.info
 * @license   MIT <http://opensource.org/licenses/MIT>
 */

require_once __DIR__ . '/src/PageFactory.php';
require_once __DIR__ . '/src/MarkdownPlus.php';

use Kirby\Cms\App as Kirby;
use Usility\PageFactory\PageFactory as PageFactory;
use Usility\PageFactory\MarkdownPlus as MarkdownPlus;


Kirby::plugin('usility/pagefactory', [
    'pageMethods' => [
        'pageFactoryRender' => function($pages, $templateFile = false) {
            return (new PageFactory($pages))->render($templateFile);
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
]);
