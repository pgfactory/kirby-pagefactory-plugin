<?php

namespace PgFactory\PageFactory;

use Kirby\Exception\Exception;


class Assets
{
    const PFY_PATH = 'site/plugins/pagefactory/';
    const PFY_ASSETS_PATH = self::PFY_PATH.'assets/';
    const JQUERY = ['js' => self::PFY_ASSETS_PATH.'js/jquery-3.7.1.min.js', 'priority' => true];

    // DEFAULT_ASSET_GROUPS define where PageFactory will look for assets, compiling and aggregating them where necessary.
    const DEFAULT_AGGREGATED_ASSETS = [

            // 1) Plugin-Assets
            // Note: plugin assets are made available via URL 'media/plugins/pgfactory/pagefactory/...':
        self::PFY_ASSETS_PATH.'css/-pagefactory.css' => self::PFY_PATH.'scss/autoload/*',               // $sources

        self::PFY_ASSETS_PATH.'js/-pagefactory.js' => self::PFY_ASSETS_PATH.'js/autoload/*',

        self::PFY_ASSETS_PATH.'css/-pagefactory-async.css' => self::PFY_PATH.'scss/autoload-async/*',

            // 2) Custom Assets
        'content/assets/css/-app.css' => 'content/assets/css/autoload/*',
        'assets/css/-app.css' => 'assets/css/autoload/*',

        'content/assets/css/-app-async.css' => 'content/assets/css/autoload-async/*',
        'assets/css/-app-async.css' => 'assets/css/autoload-async/*',

        'content/assets/js/-app.js' => 'content/assets/js/autoload/*',
        'assets/js/-app.js' => 'assets/js/autoload/*',
    ];

    const DEFAULT_SCSS_ASSET_LOCATIONS = [
        // scss-compile to site/plugins/pagefactory/css/xy.css, where xy is filename of source
        self::PFY_ASSETS_PATH.'css/' => self::PFY_PATH.'scss/*',
        'content/assets/css/' => 'content/assets/css/scss/*',
        'assets/css/' => 'assets/css/scss/*',
    ];

    const ASSETS_PATH_DEFINITIONS = [
        'JQUERY' => self::JQUERY,
        'NAV' => [
            self::PFY_ASSETS_PATH.'js/nav.js',
            self::PFY_ASSETS_PATH.'css/-nav.css',
        ],
        'QUICKZOOM' => [
            self::PFY_ASSETS_PATH.'js/quickzoom.js',
        ],
        'LAZY_SIZES' => [
            self::PFY_ASSETS_PATH.'js/lazysizes.min.js',
        ],
        'PAGE_SWITCHER' => [
        self::PFY_ASSETS_PATH.'css/-page-switcher.css',
        self::PFY_ASSETS_PATH.'js/page-switcher.js',
        ],
        'KEN_BURNS' => [
        self::PFY_ASSETS_PATH.'js/kenburns.js',
        self::PFY_ASSETS_PATH.'js/dragImgCrosshair.js',
        ],
        'CARDS' => [
        self::PFY_ASSETS_PATH.'css/-cards.css',
        ],
    ];

    // define system assets:
    const SYSTEM_ASSETS = [
        'css' => [
        'site/plugins/markdownplus/assets/css/markdownplus.css',
        self::PFY_ASSETS_PATH.'css/-pagefactory.css',
        self::PFY_ASSETS_PATH.'css/-pagefactory-async.css',
        'content/assets/css/-app.css',
        'assets/css/-app.css',
        ],
        'js' => [
        self::PFY_ASSETS_PATH.'js/-pagefactory.js',
        'content/assets/js/-app.js',
        'assets/js/-app.js',
        ],
    ];

    private static array $assetUrlDefinitions = self::ASSETS_PATH_DEFINITIONS;
    private static array $aggregatedAssets = self::DEFAULT_AGGREGATED_ASSETS;
    private static array $assetsLocation = self::DEFAULT_SCSS_ASSET_LOCATIONS;
    private static array $cssAssets = [];
    private static array $jsAssets = [];
    private static array $cssPriorityAssets = [];
    private static array $jsPriorityAssets = [];
    private static string $bustCache = '';


    /**
     * @param string $asset
     * @return void
     */
    public static function addCssFiles(string $asset): void
    {
        self::$cssAssets[$asset] = '';
    } // addCssFiles


    /**
     * @param string $asset
     * @return void
     */
    public static function addJsFiles(string $asset): void
    {
        self::$jsAssets[$asset] = '';
    } // addJsFiles


    /**
     * @param mixed $asset
     * @return void
     * @throws Exception
     */
    public static function addAssets(mixed $asset): void
    {
        // resolve asset group names (e.g. 'JQUERY') to their definitions:
        if (is_string($asset) && preg_match('/^[A-Z_]+$/', $asset)) {
            if (isset(self::$assetUrlDefinitions[$asset])) {
                $asset = self::$assetUrlDefinitions[$asset];
            } else {
                throw new Exception("Unknown asset group: '$asset'.");
            }
        }
        if (is_array($asset)) {
            $priority = $asset['priority'] ?? false;
            foreach ($asset as $key => $item) {
                // skip non-path entries (e.g. 'priority' flag):
                if (!is_string($item) || $key === 'priority') {
                    continue;
                }
                if ($priority) {
                    if (fileExt($item) === 'css') {
                        self::$cssPriorityAssets[$item] = '';
                    } elseif (fileExt($item) === 'js') {
                        self::$jsPriorityAssets[$item] = '';
                    }
                } else {
                    if (fileExt($item) === 'css') {
                        self::$cssAssets[$item] = '';
                    } else {
                        self::$jsAssets[$item] = '';
                    }
                }
            }
        } elseif (is_string($asset)) {
            if (fileExt($asset) === 'css') {
                self::$cssAssets[$asset] = '';
            } else {
                self::$jsAssets[$asset] = '';
            }
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

        $assetLocations += self::getPageAssets();

        foreach ($assetLocations as $destPath => $srcPath) {
            $destPath = PFY_KIRBY_BASE_PATH.$destPath;
            $srcPath = PFY_KIRBY_BASE_PATH.$srcPath;
            $files = getDir($srcPath);
            foreach ($files as $file) {
                $basename = base_name($file, false);
                if (is_dir($file) || $basename[0] === '_' || fileExt($file) !== 'scss') {
                    continue;
                }
                $basename = str_replace(' ', '-', $basename); // css filenames must not contain spaces
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
        $assets = array_merge(array_keys(
            self::$cssPriorityAssets),
            self::SYSTEM_ASSETS['css'],
            array_keys(self::$cssAssets),
            self::getPageAssetTags('css')
        );
        return self::renderAssetLoadingCode($assets, 'css');
    } // renderCssLoadingCode


    /**
     * @return string
     */
    public static function renderJsLoadingCode(): string
    {
        $assets = array_merge(
            array_keys(self::$jsPriorityAssets), self::SYSTEM_ASSETS['js'],
            array_keys(self::$jsAssets),
            self::getPageAssetTags('js')
        );
        return self::renderAssetLoadingCode($assets, 'js');
    } // renderJsLoadingCode


    /**
     * Renders HTML loading code for CSS or JS assets.
     * @param array $assets
     * @param string $type  'css' or 'js'
     * @return string
     */
    private static function renderAssetLoadingCode(array $assets, string $type): string
    {
        $bustCache = self::$bustCache;
        $html = "\n";
        $contentPage = page("assets/$type");
        $contentFiles = $contentPage ? $contentPage->files() : [];

        foreach ($assets as $asset) {
            $code = self::compileAssetPath($asset, $type, $contentFiles, $html);
            if ($code === false) {
                continue;
            }
            if ($code) {
                // handle cache busting request:
                if ($bustCache) {
                    $code = str_replace(".$type", ".$type$bustCache", $code);
                }
                $html .= "  $code\n";
            } else {
                $html .= "  <!-- file not found: '$asset' -->\n";
            }
        }
        return $html;
    } // renderAssetLoadingCode


    /**
     * @param string $asset
     * @param string $type
     * @param $contentFiles
     * @param $html
     * @return string|false
     */
    private static function compileAssetPath(string $asset, string $type, $contentFiles, &$html): string|false
    {
        // assets already provided as html:
        if (str_starts_with($asset, '<')) {
            $html .= "  $asset\n";
            return false;
        }

        // skip empty CSS files:
        if ($type === 'css') {
            $f = PFY_BASE_OFFSET.$asset;
            if (!str_contains($asset, 'media/') && (!file_exists($f) || !filesize($f))) {
                return false;
            }
        }

        // assets in content folder:
        if (str_starts_with($asset, 'content')) {
            if (!$contentFiles) {
                return false;
            }
            $file = $contentFiles->find(basename($asset));
            if (!$file) {
                return false;
            }
            $code = ($type === 'css') ? css($file) : js($file);

            // assets in plugin folders:
        } elseif (str_starts_with($asset, 'site/plugins')) {
            $asset = convertToKirbyMediaFolder($asset);
            $code = ($type === 'css') ? css($asset) : js($asset);

            // assets in folder starting in app root:
        } elseif (str_starts_with($asset, '~/')) {
            $asset = PFY_BASE_OFFSET . substr($asset, 2);
            $code = ($type === 'css') ? css($asset) : js($asset);

            // assets in ~/assets folder:
        } elseif (str_starts_with($asset, 'assets/')) {
            if (!file_exists($asset)) {
                return false;
            }
            $asset = PFY_BASE_OFFSET.$asset;
            $code = ($type === 'css') ? css($asset) : js($asset);

            // explicitly provided urls:
        } elseif (str_starts_with($asset, 'http')) {
            $code = ($type === 'css')
                ? "<link href='$asset' rel='stylesheet'>"
                : "<script src='$asset'></script>";

            // any other assets:
        } else {
            $code = ($type === 'css') ? css($asset) : js($asset);
            // double check that code points to the right location in case we have a PFY_BASE_OFFSET:
            if ($code && PFY_BASE_OFFSET && !str_contains($code, PFY_HOST_URL . PFY_BASE_OFFSET)) {
                $code = str_replace(PFY_HOST_URL, PFY_HOST_URL . PFY_BASE_OFFSET, $code);
            }
        }
        return $code;
    } // compileAssetPath


    /**
     * Returns HTML tags for page-level CSS or JS assets.
     * @param string $type  'css' or 'js'
     * @return array
     */
    private static function getPageAssetTags(string $type): array
    {
        $tags = [];
        $files = page()->files()->filterBy('extension', $type);
        foreach ($files as $file) {
            if ($file->filename()[0] !== '#') {
                $tags[] = ($type === 'css') ? css($file) : js($file);
            }
        }
        return $tags;
    } // getPageAssetTags


    /**
     * Deletes all files created by Assets, i.e. filenames starting with '-' :
     * @return void
     */
    public static function reset(): void
    {
        $dir = [];
        if (is_dir(PFY_KIRBY_BASE_PATH.'assets/')) {
            $dir = array_merge(
                getDirDeep(PFY_PLUGIN_PATH.'assets/css/'),
                getDirDeep(PFY_PLUGIN_PATH.'assets/js/'),
                getDirDeep(PFY_KIRBY_BASE_PATH.'assets/-*.css'),
            );
        }
        $dir = array_merge($dir, getDirDeep(PFY_KIRBY_BASE_PATH.'content/-*.css'));

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
                    $srcExt = fileExt($srcFile);
                    if ($srcExt === 'scss') {
                        $str .= Scss::compileFileToString($srcFile);
                    } elseif ($srcExt === 'css') {
                        $str .= "/* === Copied from " . basename($srcFile) . " - do not modify! === */\n\n";
                        $str .= getFile($srcFile);
                    }
                }
                writeFile($destFile, $str);
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
     * @return array
     * @throws \Exception
     */
    private static function getPageAssets(): array
    {
        // find scss assets in all page folders:
        $assetLocations = [];
        $l = strlen(PFY_KIRBY_BASE_PATH);
        $files = getDirDeep(PFY_KIRBY_BASE_PATH.'content/*.scss');
        foreach ($files as $file) {
            $path = substr(dirname($file).'/', $l);
            $assetLocations[$path] = $path.'*';
        }
        return $assetLocations;
    } // getPageAssets


    /**
     * @return void
     */
    public static function activateBrowserCacheBusting(): void
    {
        self::$bustCache = '?bust='.rand(10,99);
        Page::addBodyTagClass('pfy-cache-busting');
    } // activateBrowserCacheBusting

} // Assets
