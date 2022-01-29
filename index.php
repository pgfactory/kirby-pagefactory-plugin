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


/**
 * @param string $path
 * @return bool
 */
function hasMdContent(string $path): bool
{
    $mdFiles = glob("$path/*.md");
    $hasContent = false;
    if ($mdFiles && is_array($mdFiles)) {
        foreach ($mdFiles as $file) {
            if (basename($file)[0] !== '#') {
                $hasContent = true;
            }
        }
    }
    return $hasContent;
}



Kirby::plugin('usility/pagefactory', [
    'pageMethods' => [
        'pageFactoryRender' => function($pages, $options = false) {
            return (new PageFactory($pages))->render($options);
        }
    ],

    'routes' => [
        [
            // catch tokens in URLs: (i.e. all capital letter codes like 'p1/ABCDEF'):
            'pattern' => '(:all)',
            'action'  => function ($slug) {
                $doDivert = false;

                // check for token in url (i.e access token), extract it
                if (preg_match('|^(.*?) / ([A-Z]{5,15})$|x', $slug, $m)) {
                    $slug = $m[1];
                    $doDivert = true;
                }

                // if folder contains no md-file, fall through to first child page:
                $page = page($slug);
                if ($page) {
                    $path = $page->root();
                    $hasContent = hasMdContent($path);
                    if (!$hasContent && $page->hasListedChildren()) {
                        $page = $page->children()->listed()->first();
                        $doDivert = true;
                    }
                }

                if ($doDivert) {
                    return site()->visit($page);
                } else {
                    $this->next();
                }
            }
        ],
    ],
]);
