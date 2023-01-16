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

//use Kirby\Cms\App as Kirby;
use Usility\PageFactory\PageFactory as PageFactory;

// load twig-functions:
$twigFunctions = glob(TWIG_FUNCTIONS_FOLDER.'*.php');
foreach ($twigFunctions as $file) {
    if (basename($file)[0] !== '#') {
        require_once $file;
    }
}


Kirby::plugin('usility/pagefactory', [

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
        'pages/z' => function() {    // == PFY_PAGE_META_FILE_BASENAME
            require_once 'site/plugins/pagefactory/src/panelHelper.php';
            return assembleBlueprint();
        },
    ],

    'hooks' => [
        'page.render:before' => function (string $contentType, array $data, Kirby\Cms\Page $page) {
            $pfy = new PageFactory($data);

            // render page content and store in page.text variable, where the twig template picks it up:
            $page->pageContent()->value = $pfy->renderPageContent();

            return $data;
        },

        'page.render:after' => function (string $contentType, array $data, string $html, Kirby\Cms\Page $page) {
            return Usility\PageFactory\unshieldStr($html, true);
        },

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
