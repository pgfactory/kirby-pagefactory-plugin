<?php

namespace PgFactory\PageFactory;

use Kirby\Exception\Exception;

const PFY_PATH = 'site/plugins/pagefactory/';
const PFY_ASSETS_PATH = PFY_PATH.'assets/';
const JQUERY = ['js' => PFY_ASSETS_PATH.'js/jquery-3.7.1.min.js', 'priority' => true];

 // DEFAULT_ASSET_GROUPS define where PageFactory will look for assets, compiling and aggregating them where necessary.
define('DEFAULT_AGGREGATED_ASSETS', [

    // 1) Plugin-Assets
    // Note: plugin assets are made available via URL 'media/plugins/pgfactory/pagefactory/...':
    PFY_ASSETS_PATH.'css/-pagefactory.css' => PFY_PATH.'scss/autoload/*',               // $sources

    PFY_ASSETS_PATH.'js/-pagefactory.js' => PFY_ASSETS_PATH.'js/autoload/*',

    PFY_ASSETS_PATH.'css/-pagefactory-async.css' => PFY_PATH.'scss/autoload-async/*',

    // 2) Custom Assets
    'content/assets/css/-app.css' => 'content/assets/css/autoload/*',
    'assets/css/-app.css' => 'assets/css/autoload/*',

    'content/assets/css/-app-async.css' => 'content/assets/css/autoload-async/*',
    'assets/css/-app-async.css' => 'assets/css/autoload-async/*',

    'content/assets/js/-app.js' => 'content/assets/js/autoload/*',
    'assets/js/-app.js' => 'assets/js/autoload/*',
]);

define('DEFAULT_SCSS_ASSET_LOCATIONS', [
    // scss-compile to site/plugins/pagefactory/css/xy.css, where xy is filename of source
   PFY_ASSETS_PATH.'css/' => PFY_PATH.'scss/*',
   'content/assets/css/' => 'content/assets/css/scss/*',
   'assets/css/' => 'assets/css/scss/*',
]);


define('ASSETS_PATH_DEFINITIONS', [
    'JQUERY' => JQUERY,
    'NAV' => [
       PFY_ASSETS_PATH.'js/nav.js',
       PFY_ASSETS_PATH.'css/-nav.css',
    ],
    'QUICKZOOM' => [
       PFY_ASSETS_PATH.'js/quickzoom.js',
    ],
    'LAZY_SIZES' => [
       PFY_ASSETS_PATH.'js/lazysizes.min.js',
    ],
    'PAGE_SWITCHER' => [
       PFY_ASSETS_PATH.'css/-page-switcher.css',
       PFY_ASSETS_PATH.'js/page-switcher.js',
    ],
    'KEN_BURNS' => [
       PFY_ASSETS_PATH.'js/kenburns.js',
    ],
]);

 // define system assets:
define('SYSTEM_ASSETS', [
    'css' => [
       'site/plugins/markdownplus/assets/css/markdownplus.css',
       PFY_ASSETS_PATH.'css/-pagefactory.css',
       PFY_ASSETS_PATH.'css/-pagefactory-async.css',
       'content/assets/css/-app.css',
       'assets/css/-app.css',
    ],
    'js' => [
       PFY_ASSETS_PATH.'js/-pagefactory.js',
       'content/assets/js/-app.js',
       'assets/js/-app.js',
    ],
]);


class Assets
{
    private static array $assetUrlDefinitions = ASSETS_PATH_DEFINITIONS;
    private static array $aggregatedAssets = DEFAULT_AGGREGATED_ASSETS;
    private static array $assetsLocation = DEFAULT_SCSS_ASSET_LOCATIONS;
    private static array $cssAssets = [];
    private static array $jsAssets = [];
    private static array $cssPriorityAssets = [];
    private static array $jsPriorityAssets = [];
    private static string $bustCache = '';


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
        if (is_string($asset) && preg_match('/^[A-Z_]+$/', $asset)) {
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

        // compile template assets:
        self::compileTemplateAssets();

        $assetLocations = self::$assetsLocation;
        $tmp = getDirDeep(PFY_KIRBY_BASE_PATH.'content/*.scss');
        $l = strlen(PFY_KIRBY_BASE_PATH);
        foreach ($tmp as $file) {
            $path = substr(dirname($file).'/', $l);
            if (str_starts_with($path, 'content/assets')) {
                continue;
            }
            $assetLocations[$path] = $path.'*';
        }
        foreach ($assetLocations as $destPath => $srcPath) {
            $destPath = PFY_KIRBY_BASE_PATH.$destPath;
            $srcPath = PFY_KIRBY_BASE_PATH.$srcPath;
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

        $bustCache = self::$bustCache;
        $html = "\n";
        $page = page('assets/css');
        $files = $page ? $page->files() : [];
        foreach ($cssAssets as $asset) {
            $code = '';
            // skip empty files:
            $f = PFY_BASE_OFFSET.$asset;
            if (!str_contains($asset, 'media/') && (!file_exists($f) || !filesize($f))) {
                continue;
            }

            // assets already provided as html:
            if (str_starts_with($asset, '<')) {
                $html .= "  $asset\n";

            // assets in content folder:
            } elseif (str_starts_with($asset, 'content')) {
                if (!$files) {
                    continue;
                }
                $file = $files->find(basename($asset));
                if (!$file) {
                    continue;
                }
                $code = css($file);

            // assets in plugin folders:
            } elseif (str_starts_with($asset, 'site/plugins')) {
                $asset = str_replace('site/plugins/markdownplus/assets/',
                    PFY_BASE_OFFSET.'media/plugins/pgfactory/markdownplus/', $asset);
                $asset = preg_replace('|site/plugins/pagefactory(-.*?)?/assets/|',
                    PFY_BASE_OFFSET.'media/plugins/pgfactory/pagefactory\1/', $asset);
                $code = css($asset);

            // assets in ~/assets folder:
            } elseif (str_starts_with($asset, 'assets/')) {
                $asset = PFY_BASE_OFFSET.$asset;
                $code = css($asset);

            // explicitly provided urls:
            } elseif (str_starts_with($asset, 'http')) {
                $code = "<link href='$asset' rel='stylesheet'>";

            // any other assets:
            } else {
                $code = css($asset);
                // double check that code points to the right location in case we have a PFY_BASE_OFFSET:
                if ($code && PFY_BASE_OFFSET && !str_contains($code, PFY_HOST_URL . PFY_BASE_OFFSET)) {
                    $code = str_replace(PFY_HOST_URL, PFY_HOST_URL . PFY_BASE_OFFSET, $code);
                }
            }

            if ($code) {
                // handle cache busting request:
                if ($bustCache) {
                    $code = str_replace('.css', ".css$bustCache", $code);
                }
                $html .= "  $code\n";
            } else {
                $html .= "  <!-- file not found: '$asset' -->\n";
            }
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

        $bustCache = self::$bustCache;
        $html = "\n";
        $page = page('assets/js');
        $files = $page ? $page->files() : [];
        foreach ($jsAssets as $asset) {
            $code = '';
            // assets already provided as html:
            if (str_starts_with($asset, '<')) {
                $html .= "  $asset\n";
                $code = '';

            // assets in content folder:
            } elseif (str_starts_with($asset, 'content')) {
                if (!$files) {
                    continue;
                }
                $file = $files->find(basename($asset));
                if ($file) {
                    $code = js($file);
                }

            // assets in plugin folders:
            } elseif (str_starts_with($asset, 'site/plugins')) {
                $asset = preg_replace('|site/plugins/pagefactory(-.*?)?/assets/|', 'media/plugins/pgfactory/pagefactory\1/', $asset);
                $code = js(PFY_BASE_OFFSET.$asset);

            // assets in ~/assets folder:
            } elseif (file_exists($asset)) {
                $code = js(PFY_BASE_OFFSET.$asset);

            // explicitly provided urls:
            } elseif (str_starts_with($asset, 'http')) {
                $code = "<script src='$asset'></script>";

            // any other assets:
            } else {
                $code = js($asset);

                // double check that code points to the right location in case we have a PFY_BASE_OFFSET:
                if ($code && PFY_BASE_OFFSET && !str_contains($code, PFY_HOST_URL . PFY_BASE_OFFSET)) {
                    $code = str_replace(PFY_HOST_URL, PFY_HOST_URL . PFY_BASE_OFFSET, $code);
                }
            }

            if ($code) {
                // handle cache busting request:
                if ($bustCache) {
                    $code = str_replace('.js', ".js$bustCache", $code);
                }
                $html .= "  $code\n";
            } else {
                $html .= "  <!-- file not found: '$asset' -->\n";
            }
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
            getDirDeep(PFY_PLUGIN_PFY_PATH.'assets/css/'),
            getDirDeep(PFY_PLUGIN_PFY_PATH.'assets/js/'),
            getDirDeep(PFY_KIRBY_BASE_PATH.'assets/-*.css'),
            getDirDeep(PFY_KIRBY_BASE_PATH.'content/-*.css'),
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
            $destFile = PFY_KIRBY_BASE_PATH.$destFile;
            $srcPath = PFY_KIRBY_BASE_PATH.$srcPath;
            $tTarg = fileTime($destFile);
            $modified = false;
            $ext = fileExt($destFile);
            $files = getDir("$srcPath*$ext");
            foreach ($files as $srcFile) {
                $modified = $modified || fileTime($srcFile) > $tTarg;
            }
            if (!$modified) {
                continue;
            }
            if ($ext === 'css') {
                $str = '';
                foreach ($files as $srcFile) {
                    $basename = base_name($srcFile, false);
                    if (!ctype_alnum($basename[0])) {
                        continue;
                    }
                    if (($ext = fileExt($srcFile)) === 'scss') {
                        $str .= Scss::compileFileToString($srcFile);
                    } elseif ($ext === 'css') {
                        $str .= "/* === Copied from " . basename($srcFile) . " - do not modify! === */\n\n";
                        $str .= getFile($srcFile);
                    }
                }
                writeFile($destFile, $str);
                mylog("Assets: '$destFile' compiled");
            } else {
                CompileJs::compileAll($srcPath, $destFile);
            }
        }
    } // compileAggregatedAssets


    /**
     * @return void
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    private static function compileTemplateAssets(): void
    {
        $templateCssFiles = getDir(PFY_KIRBY_BASE_PATH . 'assets/css/scss/templates/*');
        foreach ($templateCssFiles as $file) {
            $filename = basename($file, '.scss');
            $destFile = PFY_KIRBY_BASE_PATH . "assets/css/templates/$filename.css";
            Scss::compileFile($file, $destFile);
        }
    } // compileTemplateAssets


    /**
     * @return void
     */
    public static function activateBrowserCacheBusting(): void
    {
        self::$bustCache = '?bust='.rand(10,99);
    } // activateBrowserCacheBusting

} // Assets
