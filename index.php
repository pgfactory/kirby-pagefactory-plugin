<?php

/**
 *
 * PageFactory for Kirby 4
 *
 * @version   0.5
 * @author    Dieter Stokar <https://pagefactory.info>
 * @copyright Usility GmbH <https://usility.ch>
 * @link      https://pagefactory.info
 * @license   MIT <https://opensource.org/licenses/MIT>
 */

require_once __DIR__ . '/vendor/autoload.php';

use PgFactory\PageFactory\PageFactory as PageFactory;

define('PFY_DOWNLOAD_PATH', '~/download/');

Kirby::plugin('pgfactory/pagefactory', [

    'snippets' => [     // Macros that are available in templates
        'css' =>            __DIR__ . '/snippets/_css.php',
        'link' =>           __DIR__ . '/snippets/link.php',
        'logo' =>           __DIR__ . '/snippets/logo.php',
        'nav' =>            __DIR__ . '/snippets/nav.php',
        'prevnextlinks' =>  __DIR__ . '/snippets/prevnextlinks.php',
        'sitemap' =>        __DIR__ . '/snippets/sitemap.php',
    ],

    'controllers' => [
        'site' => function ($page, $pages, $site, $kirby) {
            return (new PageFactory($page, $pages, $site, $kirby))->prepareTemplateFields();
        }
    ],

    'hooks' => [
        // experimental: avoid requests for .map files
        'route:before' => function (\Kirby\Http\Route $route, string $path) {
            if (str_ends_with($path, '.map')) {
                exit();
            }
            if (str_starts_with($path, 'download') && \PgFactory\PageFactory\Download::handler($path)) {
                exit();
            }
        },


        'page.render:after' => function (string $contentType, array $data, string $html, Kirby\Cms\Page $page) {
            return PageFactory::cleanUp($html);
        },

        // create initial .md content file for newly created pages:
        'page.create:after' => function (\Kirby\Cms\Page $page) {
            require_once PFY_APP_BASE_PATH . 'site/plugins/pagefactory/src/panelHelper.php';
            onPageCreateAfter($page);
        },

    ], // hooks

]);

