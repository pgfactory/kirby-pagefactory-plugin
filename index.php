<?php

/**
 *
 * PageFactory for Kirby 3
 *
 * @version   0.5
 * @author    Dieter Stokar <https://pagefactory.info>
 * @copyright Usility GmbH <https://usility.ch>
 * @link      https://pagefactory.info
 * @license   MIT <https://opensource.org/licenses/MIT>
 */

require_once __DIR__ . '/vendor/autoload.php';

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
            // catch tokens in URLs: (i.e. all capital letter or digit codes like 'p1/A1B2C3')
            'pattern' => '(:all)',
            'action'  => function ($slug) {
                // check pattern 'p1/ABCDEF':
                if (preg_match('|^(.*?) / ([A-Z]{5,15})$|x', $slug, $m)) {
                    $slug = $m[1];
                    PageFactory::$slug = $slug;
                    PageFactory::$urlToken = $m[2];
                    return site()->visit($slug);

                // check pattern 'ABCDEF', i.e. page without slug:
                } elseif (preg_match('|^ ([A-Z]{5,15})$|x', $slug, $m)) {
                    PageFactory::$slug = '';
                    PageFactory::$urlToken = $m[1];
                    return site()->visit(page());
                }
                return $this->next();
            }
        ],
    ],

    'blueprints' => [
        'pages/z_pfy' => function() {    // == PFY_PAGE_DEF_BASENAME
            require_once 'site/plugins/pagefactory/src/panelHelper.php';
            return assembleBlueprint();
        },
    ],

    'hooks' => [
        // when user opens panel -> update .txt files according to .md content:
        'route:before' => function (\Kirby\Http\Route $route, string $path) {
            if (strpos($path, 'panel/pages/') === 0) {
                require_once 'site/plugins/pagefactory/src/panelHelper.php';
                onPanelLoad($path);
            }
        },

        // create initial .md content file for newly created pages:
        'page.create:after' => function (\Kirby\Cms\Page $page) {
            require_once 'site/plugins/pagefactory/src/panelHelper.php';
            onPageCreateAfter($page);
        },

        // after user modified page content via panel -> update .md-files:
        'page.update:after' => function (\Kirby\Cms\Page $newPage, \Kirby\Cms\Page $oldPage) {
            require_once 'site/plugins/pagefactory/src/panelHelper.php';
            onPageUpdateAfter($newPage);
        },
        //        'file.update:after' => function (Kirby\Cms\File $newFile) {
        //            require_once 'site/plugins/pagefactory/src/panelHelper.php';
        //            onFileUpdateAfter($newFile);
        //        },

    ], // hooks

]);
