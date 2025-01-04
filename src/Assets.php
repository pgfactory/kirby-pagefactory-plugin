<?php

namespace PgFactory\PageFactory;

use Kirby\Exception\Exception;

const JQUERY = ['js' => PFY_ASSETS_URL.'js/jquery-3.7.1.min.js', 'priority' => true];

 // DEFAULT_ASSET_GROUPS define where PageFactory will look for assets, compiling and aggregating them where necessary.
define('DEFAULT_AGGREGATED_ASSETS', [

    // 1) Plugin-Assets
    // Note: plugin assets are made available via URL 'media/plugins/pgfactory/pagefactory/...':
    'site/plugins/pagefactory/assets/css/-pagefactory.css' => 'site/plugins/pagefactory/scss/autoload/*',               // $sources

    'site/plugins/pagefactory/assets/js/-pagefactory.js' => 'site/plugins/pagefactory/assets/js/autoload/*',

    'site/plugins/pagefactory/assets/css/-pagefactory-async.css' => 'site/plugins/pagefactory/scss/autoload-async/*',

    // 2) Custom Assets
    'content/assets/css/-app.css' => 'content/assets/css/autoload/*',

    'content/assets/css/-app-async.css' => 'content/assets/css/autoload-async/*',

    'content/assets/js/-app.js' => 'content/assets/js/autoload/*',
]);

define('DEFAULT_SCSS_ASSET_LOCATIONS', [
    // scss-compile to site/plugins/pagefactory/css/xy.css, where xy is filename of source
   'site/plugins/pagefactory/assets/css/' => 'site/plugins/pagefactory/scss/*',
   'content/assets/css/' => 'content/assets/css/scss/*',
]);


define('ASSET_URL_DEFINITIONS', [
    'JQUERY' => JQUERY,
    'NAV' => [
       'site/plugins/pagefactory/assets/js/nav.js',
       'site/plugins/pagefactory/assets/css/-nav.css',
    ],
    'QUICKVIEW' => [
       'site/plugins/pagefactory/assets/js/quickview.js',
       'site/plugins/pagefactory/assets/css/-quickview.css',
    ],
    'PAGE_SWITCHER' => [
       'site/plugins/pagefactory/assets/css/-page-switcher.css',
       'site/plugins/pagefactory/assets/js/page-switcher.js',
    ],
]);

 // define system assets:
define('SYSTEM_ASSETS', [
    'css' => [
       'site/plugins/markdownplus/assets/css/markdownplus.css',
       'site/plugins/pagefactory/assets/css/-pagefactory.css',
       'site/plugins/pagefactory/assets/css/-pagefactory-async.css',
       'content/assets/css/-app.css',
    ],
    'js' => [
       'site/plugins/pagefactory/assets/js/-pagefactory.js',
       'content/assets/js/-app.js',
    ],
]);


class Assets
{
    private static array $assetUrlDefinitions = ASSET_URL_DEFINITIONS;
    private static array $aggregatedAssets = DEFAULT_AGGREGATED_ASSETS;
    private static array $assetsLocation = DEFAULT_SCSS_ASSET_LOCATIONS;
    private static array $cssAssets = [];
    private static array $jsAssets = [];
    private static array $cssPriorityAssets = [];
    private static array $jsPriorityAssets = [];


    /**
     * @param mixed $asset
     * @return void
     */
    public static function addCssFiles(mixed $asset): void
    {
        self::$cssAssets[] = $asset;
    } // addCssFiles


    /**
     * @param mixed $asset
     * @return void
     */
    public static function addJsFiles(mixed $asset): void
    {
        self::$jsAssets[] = $asset;
    } // addJqFiles


    /**
     * @param mixed $asset
     * @return void
     * @throws Exception
     */
    public static function addAssets(mixed $asset): void
    {
        if (is_string($asset) && ctype_upper($asset)) {
            if (in_array($asset, array_keys(self::$assetUrlDefinitions))) {
                $asset = self::$assetUrlDefinitions[$asset];
            } else {
                throw new Exception("Unknown asset group: '$asset'.");
            }
        }
        if (is_array($asset)) {
            foreach ($asset as $ass) {
                if ($asset['priority']?? false) {
                    if (fileExt($ass) === 'css') {
                        self::$cssPriorityAssets[$ass] = '';
                    } elseif (fileExt($ass) === 'js') {
                        self::$jsPriorityAssets[$ass] = '';
                    };

                } else {
                    if (fileExt($ass) === 'css') {
                        self::$cssAssets[$ass] = '';
                    } else {
                        self::$jsAssets[$ass] = '';
                    };
                }
            }
        } elseif (is_string($asset)) {
            if (fileExt($asset) === 'css') {
                self::$cssAssets[$asset] = '';
            } else {
                self::$jsAssets[$asset] = '';
            };
        }
    } // addAssets


    /**
     * @param array $assetGroups
     * @return void
     */
    public static function addAssetGroups(array $assetGroups): void
    {
        self::$assetUrlDefinitions += $assetGroups;
    } // addAssetGroups


    /**
     * @param array $assets
     * @return void
     */
    public static function addAggregatedAssets(array $assets): void
    {
        self::$aggregatedAssets += $assets;
    } // addAggregatedAssets


    /**
     * @param array $assets
     * @return void
     */
    public static function addAssetLocation(array $assets): void
    {
        self::$assetsLocation += $assets;
    } // addAssetLocation


    // === compiling ========================================
    /**
     * @return void
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public static function compileAssets(): void
    {
        // compile aggregated system assets:
        self::compileAggregatedAssets();

        $assetLocations = self::$assetsLocation;
        $tmp = getDirDeep(PFY_APP_BASE_PATH.'content/*.scss');
        $l = strlen(PFY_APP_BASE_PATH);
        foreach ($tmp as $file) {
            $path = substr(dirname($file).'/', $l);
            if (str_starts_with($path, 'content/assets')) {
                continue;
            }
            $assetLocations[$path] = $path.'*';
        }
        foreach ($assetLocations as $destPath => $srcPath) {
            $destPath = PFY_APP_BASE_PATH.$destPath;
            $srcPath = PFY_APP_BASE_PATH.$srcPath;
            $files = getDir($srcPath);
            foreach ($files as $file) {
                $basename = base_name($file, false);
                if (is_dir($file) || $basename[0] === '_' || fileExt($file) !== 'scss') {
                    continue;
                }
                $destFile = "$destPath-$basename.css";
                if (PageFactory::$forceAssetsUpdate) {
                    Scss::compileFile($file, $destFile);
                } else {
                    Scss::updateFile($file, $destFile);
                }
            }
        }

    } // compileAssets


    // === rendering ========================================
    /**
     * @return string
     */
    public static function renderCssLoadingCode(): string
    {
        $cssAssets = array_merge(array_keys(self::$cssPriorityAssets), SYSTEM_ASSETS['css']);
        $cssAssets = array_merge($cssAssets, array_keys(self::$cssAssets));
        $cssAssets = array_merge($cssAssets, self::addPageAssets('css'));

        $html = "\n";
        $page = page('assets/css');
        $files = page('assets/css')->files();
        foreach ($cssAssets as $asset) {
            if (str_starts_with($asset, '<')) {
                $html .= "  $asset\n";
            } elseif (str_starts_with($asset, 'content')) {
                $file = $files->find(basename($asset));
                if (!$file) {
                    continue;
                }
                $html .= '  ' . css($file) . "\n";
            } else {
                $html .= '  ' . css($asset) . "\n";
            }
        }
        $html = str_replace('/site/plugins/markdownplus/assets/',
                            '/media/plugins/pgfactory/markdownplus/', $html);
        $html = preg_replace('|/site/plugins/pagefactory(-.*?)?/assets/|',
                          '/media/plugins/pgfactory/pagefactory\1/', $html);
        if (PFY_BASE_OFFSET) {
            $html = preg_replace('|'.PFY_APP_BASE_URL.'(?!'.PFY_BASE_OFFSET.')|',PFY_APP_BASE_URL.PFY_BASE_OFFSET, $html);
        }
        return $html;
    } // renderCssLoadingCode


    /**
     * @return string
     */
    public static function renderJsLoadingCode(): string
    {
        $jsAssets = array_merge(array_keys(self::$jsPriorityAssets), SYSTEM_ASSETS['js']);
        $jsAssets = array_merge($jsAssets, array_keys(self::$jsAssets));
        $jsAssets = array_merge($jsAssets, self::addPageAssets('js'));

        $html = "\n";
        $files = page('assets/js')->files();
        foreach ($jsAssets as $asset) {
            if (str_starts_with($asset, '<')) {
                $html .= "  $asset\n";
            } elseif (str_starts_with($asset, 'content')) {
                $file = $files->find(basename($asset));
                $html .= '  ' . js($file) . "\n";
            } else {
                $html .= '  ' . js($asset) . "\n";
            }
        }
        $html = preg_replace('|/site/plugins/pagefactory(-.*?)?/assets/|', '/media/plugins/pgfactory/pagefactory\1/', $html);
        if (PFY_BASE_OFFSET) {
            $html = preg_replace('|'.PFY_APP_BASE_URL.'(?!'.PFY_BASE_OFFSET.')|',PFY_APP_BASE_URL.PFY_BASE_OFFSET, $html);
        }
        return $html;
    } // renderJsLoadingCode


    /**
     * @param string $cssOrJs
     * @return array
     */
    private static function addPageAssets(string $cssOrJs): array
    {
        $pageAssets = [];
        $files = page()->files()->filterBy('extension', $cssOrJs);
        foreach ($files as $file) {
            if (($file->filename())[0] !== '#') {
                $pageAssets[] = $cssOrJs($file);
            }
        }
        return $pageAssets;
    } // addPageAssets


    /**
     * Deletes all files created by Assets, i.e. filenames starting with '-' :
     * @return void
     */
    public static function reset(): void
    {
        $dir = array_merge(
            getDirDeep(PFY_PAGEFACTORY_ASSETS_PATH.'css/'),
            getDirDeep(PFY_PAGEFACTORY_ASSETS_PATH.'js/'),
            getDirDeep(PFY_APP_BASE_PATH.'content/-*.css'),
        );

        foreach ($dir as $file) {
            if ((basename($file)[0]) === '-') {
                unlink($file);
            }
        }
    } // reset


    /**
     * @return void
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    private static function compileAggregatedAssets(): void
    {
        foreach (self::$aggregatedAssets as $destFile => $srcPath) {
            $destFile = PFY_APP_BASE_PATH.$destFile;
            $srcPath = PFY_APP_BASE_PATH.$srcPath;
            $tTarg = fileTime($destFile);
            $modified = false;
            $files = getDir($srcPath);
            foreach ($files as $srcFile) {
                $modified = $modified || fileTime($srcFile) > $tTarg;
            }
            if (!$modified) {
                continue;
            }

            $str = '';
            foreach ($files as $srcFile) {
                $basename = base_name($srcFile, false);
                if (!ctype_alnum($basename[0])) {
                    continue;
                }
                if (($ext = fileExt($srcFile)) === 'scss') {
                    $str .= Scss::compileFileToString($srcFile);
                } elseif ($ext === 'css' || $ext === 'js') {
                    $str .= "/* === Copied from " . basename($srcFile) . " - do not modify! === */\n\n";
                    $str .= getFile($srcFile);
                }
            }
            writeFile($destFile, $str);
            mylog("Assets: '$destFile' compiled");
        }
    } // compileAggregatedAssets

} // Assets
