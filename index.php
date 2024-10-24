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

const TWIG_FUNCTIONS_FOLDER = __DIR__.'/twig-functions/';
require_once __DIR__ . '/vendor/autoload.php';

use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\TransVars;

loadTwigFunctions();

Kirby::plugin('pgfactory/pagefactory', [

    'blueprints' => [
        'pages/z' => function() {    // == PFY_PAGE_META_FILE_BASENAME
            require_once 'site/plugins/pagefactory/src/panelHelper.php';
            return assembleBlueprint();
        },
    ],

    'hooks' => [
        'route:before' => function (\Kirby\Http\Route $route, string $path) {
            // when user opens panel -> update .txt files according to .md content:
            if (strpos($path, 'panel/pages/') === 0) {
                require_once 'site/plugins/pagefactory/src/panelHelper.php';
                onPanelLoad($path);
            }
        },

        'page.render:before' => function (string $contentType, array $data, Kirby\Cms\Page $page) {
            $pfy = new PageFactory($data);

            // render page content and store in page.text variable, where the twig template picks it up:
            $page->pageContent()->value = $pfy->renderPageContent();
            return $data;
        },

        'page.render:after' => function (string $contentType, array $data, string $html, Kirby\Cms\Page $page) {
            $html = PgFactory\PageFactory\Utils::resolveUrl($html);
            return PgFactory\PageFactory\unshieldStr($html, true);
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


/**
 * @return void
 */
function loadTwigFunctions(): void
{
    // load twig-functions:
    $twigFunctions = TransVars::findAllMacros(includePaths: true);
    foreach ($twigFunctions as $file) {
        if (basename($file)[0] !== '#') {
            require_once $file;
        }
    }
}