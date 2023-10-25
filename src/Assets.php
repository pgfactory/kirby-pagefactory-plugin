<?php

namespace PgFactory\PageFactory;

use Kirby\Exception\Exception;


 // DEFAULT_ASSET_GROUPS define where PageFactory will look for assets, compiling and aggregating them where necessary.
const DEFAULT_ASSET_GROUPS = [

    // 1) Plugin-Assets
    // Note: plugin assets are made available via URL 'media/plugins/pgfactory/pagefactory/...':
    'site/plugins/pagefactory/assets/css/-pagefactory.css' => [   // $dest
        'site/plugins/pagefactory/scss/autoload/*',               // $sources
    ],
    // scss-compile to site/plugins/pagefactory/css/xy.css, where xy is filename of source
    'site/plugins/pagefactory/assets/css/' => [
        'site/plugins/pagefactory/scss/*',
    ],
    'site/plugins/pagefactory/assets/js/-pagefactory.js' => [
        'site/plugins/pagefactory/assets/js/autoload/*',
    ],

    'site/plugins/pagefactory/assets/css/-pagefactory-async.css' => [
        'site/plugins/pagefactory/scss/autoload-async/*',
    ],

    // 2) Custom Assets
    'content/assets/css/-app.css' => [
        'content/assets/css/autoload/*',
    ],
    'content/assets/css/' => [
        'content/assets/css/scss/*',
    ],
    'content/assets/css/-app-async.css' => [
        'content/assets/css/autoload-async/*',
    ],
    'content/assets/js/-app.js' => [
        'content/assets/js/autoload/*',
    ],
];

const ASSET_URL_DEFINITIONS = [
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
];

 // define system assets:
const SYSTEM_ASSETS = [
    'css' => [
        'site/plugins/pagefactory/assets/css/-pagefactory.css',
        'site/plugins/pagefactory/assets/css/-pagefactory-async.css',
        'content/assets/css/-app.css',
    ],
    'js' => [
        'site/plugins/pagefactory/assets/js/-pagefactory.js',
        'content/assets/js/-app.js',
    ],
];


class Assets
{
    public  $assetQueue = [];
    public  $systemAssets;
    public  $jsFrameworkRequired = false;
    private $scssModified = false;
    private $hostUrl;
    private $hostUrlLen;
    private $pageFolderfiles;
    private $pageFolderPath;
    private $definitions;
    private $frameworkFiles;
    private $filePriority = [];
    private $assetGroups;


    /**
     * @param $pfy
     */
    public function __construct($pfy = null)
    {
        new Scss();

        $this->hostUrl = PageFactory::$hostUrl;
        $this->hostUrlLen = strlen($this->hostUrl)-1;
        $this->pageFolderfiles = page()->files()->data();
        $this->pageFolderPath = page()->root().'/';

        $this->definitions = &Page::$definitions;
        if (!($this->assetGroups = PageFactory::$config['assetGroups']??false)) {
            $this->assetGroups = DEFAULT_ASSET_GROUPS;
        }
        if (class_exists('PgFactory\MarkdownPlus\MarkdownPlus')) {
            $this->assetGroups['site/plugins/pagefactory/assets/css/-pagefactory.css'][] = 'site/plugins/markdownplus/assets/css/*';
        }
        $this->prepareAssets();
    } // __construct


    /**
     * Adds an asset group to the assets definition list
     * @param $newAssetGroups
     * @return void
     */
    public function addAssetGroups($newAssetGroups)
    {
        $this->assetGroups = array_merge_recursive($this->assetGroups, $newAssetGroups);
    } // addAssetGroups


    /**
     * Add CSS or SCSS File(s) to asset-queue
     * @param mixed $assets  array or comma separated list
     * @return void
     */
    public function addCssFiles(mixed $assets):void
    {
        $this->addAssets($assets);
    } // addCssFiles


    /**
     * Add jsFramework File(s) to asset-queue
     * Accepts jq-file-name(s) and queues them for loading; as a side effect makes sure that jsFramework is loaded as well
     * @param mixed $assets  array or comma separated list
     */
    public function addJqFiles(mixed $assets):void
    {
        $this->addAssets($assets, true);
    } // addJqFiles


    /**
     * Accepts js-file-name(s) and queues them for loading
     * @param mixed $assets  array or comma separated list
     */
    public function addJsFiles(mixed $assets):void
    {
        $this->addAssets($assets);
    } // addJsFiles


    /**
     * Add CSS or SCSS or JS or JQ File(s) to asset-queue
     * @param mixed $assets  array or comma separated list
     * @param bool $treatAsJq
     * @return void
     */
    public function addAssets(mixed $assets, bool $treatAsJq = false): void
    {
        // if arg is a string, transform:
        if (is_string($assets)) {
            if (isset($this->definitions['assets'][$assets])) {
                $assets = $this->definitions['assets'][$assets];
            } else {
                $assets = explodeTrim(',', $assets);
            }
        }

        // loop over all requested assets and check whether it corresponds to a definition, if so, replace:
        $i = 0;
        foreach ($assets as $asset) {
            if (isset($this->definitions['assets'][$asset])) {
                $assets2 = $this->definitions['assets'][$asset];
                array_splice($assets, $i, 1, $assets2);
                $i += sizeof($assets2)-1;
            }
            $i++;
        }

        // finally loop over resulting array of assets, check for priority-hints and add to queue:
        foreach ($assets as $asset) {
            $type = fileExt($asset);
            if ($type === 'jq') {
                $this->jsFrameworkRequired = true;
                $asset = rtrim($asset, 'jq').'js';
            } elseif ($type === 'js' && $treatAsJq) {
                $this->jsFrameworkRequired = true;
            }
            $this->extractPriorityHint($asset);
            $this->assetQueue[$type][] = $asset;
        }
    } // addAssets


    /**
     * Removes system styles from asset queue -> called if template doesn't contain 'pfy-default-styling'
     * @return void
     */
    public function excludeSystemAssets(): void
    {
        unset($this->systemAssets['css'][0]);
        unset($this->systemAssets['css'][1]);
    } // excludeSystemAssets



    /**
     * Extracts priority-hints from an array of files/urls:
     *   Priority-hints are patterns like '(n)' where 0 < n < 100.
     * @param array $queue
     * @param int $default
     * @return array
     */
    private function extractPriorityHints(array $queue, int $default = 50): array
    {
        foreach ($queue as $elem) {
            $this->extractPriorityHint($elem, $default);
        }
        return $queue;
    } // extractPriorityHints


    /**
     * Extracts priority-hint from a file/url:
     * @param string $file
     * @param int $default
     * @return mixed
     */
    private function extractPriorityHint(string $file, int $default = 50): mixed
    {
        if (preg_match('|(.*) \((.*)\) (.*)|x', $file, $m)) {
            $this->filePriority[basename($file)] = intval($m[2]);
            $file = $m[1].$m[3];
            $this->filePriority[basename($file)] = intval($m[2]);
            return $file;
        } else {
            $this->filePriority[basename($file)] = $default;
        }
        return false;
    } // extractPriorityHint


    /**
     * Sorts given queue according to priority-hints collected before (in $this->filePriority).
     * @param array $queue
     * @return array
     */
    private function sortQueue(array $queue): array
    {
        // extract prio-hints, sort and thereby remove multiple instances of files:
        $q = [];
        foreach ($queue as $elem) {
            $filename = basename($elem);
            if (isset($this->filePriority[$filename])) {
                $q[$elem] = $this->filePriority[$filename];
            } else {
                $q[$elem] = 50;
            }
        }
        asort($q);
        return array_keys($q);
    } // sortQueue


    /**
     * Sets the flag to get jsFramework loaded.
     * @return void
     */
    public function requireFramework(): void
    {
        $this->jsFrameworkRequired = true;
    } // requireFramework


    /**
     * Generic setter
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->$key = $value;
    }


    /**
     * Returns HTML for loading JS or CSS files which have been added to the queue.
     * @param string $jsOrCss
     * @return string
     */
    public function renderQueuedAssets(string $jsOrCss): string
    {
        $queuedFiles = $this->getQueuedFiles($jsOrCss);

        $html = '';
        if ($queuedFiles) {
            foreach ($queuedFiles as $file) {
                $html .= $this->renderAssetLoadingCode($file, $jsOrCss);
            }
        }
        return $html;
    } // renderQueuedAssets


    /**
     * Deletes all files created by Assets, i.e. filenames starting with '-' :
     * @return void
     */
    public static function reset(): void
    {
        $dir = array_merge(
                getDirDeep(PFY_ASSETS_PATH.'css/'),
                getDirDeep(PFY_ASSETS_PATH.'js/'),
                getDirDeep(PFY_CONTENT_ASSETS_PATH.'css/'),
                getDirDeep(PFY_CONTENT_ASSETS_PATH.'js/'));

        foreach ($dir as $file) {
            if ((basename($file)[0]) === '-') {
                unlink($file);
            }
        }
    } // reset


    /**
     * Prepares required folders and aggregates system assets if necessary.
     * @return void
     * @throws \Exception
     */
    public function prepareAssets(): bool
    {
        preparePath(PFY_CACHE_PATH . 'compiledScss/');
        preparePath(PFY_CONTENT_ASSETS_PATH . 'css/');
        preparePath(PFY_CONTENT_ASSETS_PATH . 'js/');

        $this->assetQueue = ['css' =>[], 'js' => [], 'jq' => []];

        // get system assets:
        $this->systemAssets = SYSTEM_ASSETS;

        // prepare asset groups:
        $assetGroups = $this->assetGroups;
        if ($assetGroups && is_array($assetGroups)) {
            foreach ($assetGroups as $dest => $sources) {
                if ($dest1 = $this->extractPriorityHint($dest)) {
                    $assetGroups[$dest1] = $assetGroups[$dest];
                    unset($assetGroups[$dest]);
                    $dest = $dest1;
                }
                if (PageFactory::$debug || PageFactory::$isLocalhost) {
                    $this->prepareAssetGroup($dest, $sources);
                }
            }
        }

        // get framework assets:
        if ($frontendFrameworkUrls = PageFactory::$config['frontendFrameworkUrls']??false) {
            $this->frameworkFiles = $frontendFrameworkUrls;
        } else {
            $this->frameworkFiles = DEFAULT_FRONTEND_FRAMEWORK_URLS;
        }
        foreach ($this->frameworkFiles as $i => $file) {
            $this->filePriority[basename($file)] = 10;
            if (!str_starts_with($file, 'http')) {
                $this->frameworkFiles[$i] = PageFactory::$appUrl . $file;
            }
        }
        return $this->scssModified;
    } // prepareAssets



    // === private =============================================
    /**
     * Returns queued assets of given type (css or js).
     * @param string $jsOrCss
     * @return array
     */
    private function getQueuedFiles(string $jsOrCss): array
    {
        // get assets from current page folder:
        $pageFolderAssets = $this->getAssetsFromPageFolder($jsOrCss);

        // if required, get framework-files (e.g. jQuery):
        if ($this->jsFrameworkRequired) {
            if ($frameworkFiles = $this->frameworkFiles[$jsOrCss]??false) {
                if (is_string($frameworkFiles)) {
                    $frameworkFiles = explodeTrim(',', $frameworkFiles);
                }
                $this->systemAssets[$jsOrCss] = array_merge($frameworkFiles, $this->systemAssets[$jsOrCss]);
            }
        }

        // extract priority hints from files in assetQueue:
        $assetQueue = $this->extractPriorityHints($this->assetQueue[$jsOrCss]);

        // get custom jQuery files -> to be added at the end:
        if ($jsOrCss === 'js') {
            $assetQueue = array_merge($assetQueue, $this->extractPriorityHints($this->assetQueue['jq'], 70));
        }

        // assemble queue out of systemAssets, assetQueue and pageFolderAssets:
        $queue = array_merge($this->systemAssets[$jsOrCss], $assetQueue, $pageFolderAssets);

        // sort queue according to priority-hints and remove multiple instances:
        $queue = $this->sortQueue($queue);

        // translate paths to urls:
        $queue = $this->translateToUrls($queue, $jsOrCss);
        return $queue;
    } // getQueuedFiles


    /**
     * @param array $queue
     * @param string $jsOrCss
     * @return array
     * @throws \Exception
     */
    private function translateToUrls(array $queue, string $jsOrCss): array
    {
        // find assets in asset folder:
        $assetFolderFiles = PageFactory::$pages->find("assets/$jsOrCss")->files()->data();
    
        foreach ($queue as $i => $file) {
            if (!$file) { // skip empty items:
                unset($queue[$i]);
                continue;
            }
    
            // cope with case request contains prio, but not file:
            $url = resolvePath($file);

            if (!(str_starts_with($url, 'http') || str_starts_with($url, 'media/') || str_starts_with($url, PageFactory::$appUrl)) &&
                !file_exists($url)) {
                $file1 = $this->extractPriorityHint($url);
                if ($url && file_exists($file1)) {
                    $url = dirname($url).'/'.basename($file1);
                } else {
                    // mylog("Error: requested asset `$file` not found.");
                    unset($queue[$i]);
                    continue;
                    // throw new \Exception("Error: requested asset `$file` not found.");
                }
            }

            if (str_starts_with($url, 'site/plugins/')) {       // filepath to asset in pluginXY/assets/
                if (preg_match('|^site/plugins/(.+?)/.+?/(.*)|', $url, $m)) {
                    $url = PageFactory::$appUrl . PFY_BASE_ASSETS_URL . "{$m[1]}/{$m[2]}";
                } else {
                    throw new \Exception("Internal Error: unexpected pattern '$url'");
                }
    
            } elseif (str_starts_with($url, 'media/plugins/')) { // already a plugin-url
                $url = PageFactory::$appUrl . $url;

            } elseif (str_starts_with($url, 'content/assets/')) { // filepath to asset in content/assets/
                $url = (string)($assetFolderFiles[substr($url, 8)] ?? '');
            }
    
            // beautify urls, i.e. get rid of hostUrl part if present:
            if (str_starts_with($url, $this->hostUrl)) {
                $url = substr($url, $this->hostUrlLen);
            }
    
            if (!$url) {
                throw new \Exception("Unable to find requested asset '{$queue[$i]}'");
            }
            $queue[$i] = $url;
        }
        return $queue;
    } // translateToUrls


    /**
     * Checks the current page foder for CSS, SCSS and JS files and returns them, compiled if necessary.
     * @param string $jsOrCss
     * @return array
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    private function getAssetsFromPageFolder(string $jsOrCss): array
    {
        $modified = false;
        if (($jsOrCss === 'css') && ($scssFiles = getDir($this->pageFolderPath.'*.scss'))) {
            // compile any scss files in page folder:
            foreach ($scssFiles as $file) {
                $modified |= (bool)Scss::updateFile($file, $file);
            }
        }
        if ($modified) {
            reloadAgent();
        }

        $assets = [];
        foreach ($this->pageFolderfiles as $file => $url) {
            $this->extractPriorityHint($file, 60);
            $ext = fileExt($file);
            if ($ext !== $jsOrCss) {
                continue;
            }
            if ($ext === 'js') {  // js:
                $assets[] = (string)$url;

            } else {             // css:
                $assets[] = (string)$url;
            }
        }
        return $assets;
    } // getAssetsFromPageFolder


    /**
     * Returns HTML for loading given asset.
     * @param string $fileUrl
     * @param string $jsOrCss
     * @return string
     */
    private function renderAssetLoadingCode(string $fileUrl, string $jsOrCss): string
    {
        if ($jsOrCss === 'js') { // js
            $html = "\t<script src='$fileUrl'></script>\n";

        } else { // css
            if (strpos($fileUrl, '-async') !== false) {
                $html = "\t<link href='$fileUrl' rel='stylesheet' media='print' class='pfy-onload-css'>\n";
                $html .= "\t<noscript><link href='$fileUrl' rel='stylesheet'></noscript>\n";
            } else {
                $html = "\t<link href='$fileUrl' rel='stylesheet'>\n";
            }
        }
        return $html;
    } // renderAssetLoadingCode


    /**
     * Prepares given asset-group, compiles SCSS and aggregates into collective file, if requirec.
     * @param string $dest
     * @param array $sources
     * @return void
     */
    private function prepareAssetGroup(string $dest, array $sources): void
    {
        // system assets are defined as an array of dest =>
        if ($dest[strlen($dest)-1] === '/') {   // dest is defined as a folder -> prepare each file individually
            foreach ($sources as $sourceDir) {
                $files = getDir($sourceDir);
                foreach ($files as $sourceFile) {
                    $sourceFileName = basename($sourceFile);
                    $fileExt = fileExt($sourceFileName);
                    if ($fileExt !== 'scss' || $sourceFileName[0] === '_' || $sourceFileName[0] === '-') {
                        continue;
                    }
                    $targetFile = $dest.'-'.base_name($sourceFileName, false).'.css';
                    $this->compileScss($sourceFile, $targetFile);
                }
            }

        } else {        // dest is defined as a filename -> aggregate all files into one
            $this->aggregate($dest, $sources);
        }
    } // preparePfyAsset


    /**
     * Copies a group of files into one.
     * @param string $dest
     * @param array $sources
     * @return void
     * @throws \Exception
     */
    private function aggregate(string $dest, array $sources): void
    {
        $lastModifiedFile = 0;
        $tempSrcFiles = [];
        $this->scssModified = false;
        foreach ($sources as $sourceFolder) {
            $files = getDir($sourceFolder);
            foreach ($files as $srcFile) {
                $ext = fileExt($srcFile);
                if ($ext === 'txt') { // skip readme.txt
                    continue;
                }
                $filename = basename($srcFile);
                if (fileExt($srcFile) === 'scss') {
                    $targetFile = PFY_CACHE_PATH . "compiledScss/".basename($dest).'/-'.base_name($filename, false).'.css';
                    $this->compileScss($srcFile, $targetFile);
                    $tempSrcFiles[] = $targetFile;
                }
            }
        }
        if ($this->scssModified) {
            $out = '';
            foreach ($tempSrcFiles as $srcFile) {
                $out .= "/* @@@@@@@@ Imported from $filename @@@@@@@@ */\n\n";
                $out .= getFile($srcFile, !PageFactory::$config['debug_compileScssWithSrcRef']);
                $out .= "\n\n";
            }
            preparePath($dest);
            file_put_contents($dest, $out);
        }
    } // aggregate


    /**
     * Compiles given SCSS file, if necessary (i.e. compiled file is outdated).
     * @param string $srcFile
     * @param string $targetFile
     * @return string
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    private function compileScss(string $srcFile, string $targetFile): string
    {
        if (fileExt($targetFile) !== 'css') { // skip any non-scss files
            $targetFile = fileExt($targetFile, true).'.css';
        }
        $tTarget = lastModified($targetFile, false);
        $tSrc = lastModified($srcFile, false);
        if ($tTarget < $tSrc) {
            Scss::compileFile($srcFile, $targetFile);
            $this->scssModified = true;
        }
        return $targetFile;
    } // compileScss

} // Assets