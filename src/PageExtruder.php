<?php

/**
 * This is the engine that accepts elements and at the end churns out the final page components.
 * The final step of assembling those components is done by PageFactory->assembleHtml().
 */

namespace Usility\PageFactory;

use Exception;

class PageExtruder
{
    //ToDo: -> private?
    public $content;
    public $headInjections = '';
    public $bodyEndInjections;
    public $bodyTagClasses;
    public $bodyTagAttributes;
    public $svg;
    public $css;
    public $scss;
    public $js;
    public $jq;
    public $jqFiles = [];
    public $frontmatter = [];
    public $assetFiles = [];
    public $requestedAssetFiles = [];
    private $jQueryActive;



    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->trans = $pfy::$trans;
        $this->pgExtr = new PageElements\PageElements($pfy, $this);
        $this->sc = new Scss($this->pfy);
        $this->assetFiles = &$pfy->assetFiles;
        $this->requestedAssetFiles = &$pfy->requestedAssetFiles;
        $this->jQueryActive = &$pfy->jQueryActive;
    }



    /**
     * Main method: populates injection variables that go into the page-template.
     */
    public function preparePageVariables(): void
    {
        $this->prepareAssets();

        $out = $this->renderHeadInjections();
        $this->trans->setVariable('lzy-head-injections', $out);

        $bodyClasses = $this->bodyTagClasses? $this->bodyTagClasses: 'lzy-large-screen';
        if (isAdmin()) {
            $bodyClasses .= ' lzy-admin';
        } elseif (kirby()->user()) {
            $bodyClasses .= ' lzy-loggedin';
        }
        if (PageFactory::$debug) {
            $bodyClasses = trim("debug $bodyClasses");
        }
        $this->trans->setVariable('lzy-body-classes', $bodyClasses);

        $this->trans->setVariable('lzy-body-tag-attributes', $this->bodyTagAttributes);

        $bodyEndInjections = $this->renderBodyEndInjections();
        $this->trans->setVariable('lzy-body-end-injections', $bodyEndInjections);
    } // preparePageVariables



    // === Helper methods: accept queuing requests from macros and other objects ============

    /**
     * Generic setter
     * @param string $key
     * @param $value
     */
    public function set(string $key, $value): void
    {
        $this->$key = $value;
    }

    /**
     * Generic setter that appends
     * @param string $key
     * @param $value
     */
    public function add(string $key, $value): void
    {
        $this->$key .= $value;
    }

    /**
     * Generic setter that appends (synonyme for add)
     * @param string $key
     * @param $value
     */
    public function append(string $key, $value): void
    {
        $this->$key .= $value;
    }


    /**
     * Accepts a string to be injected into the <head> element
     * @param $str
     */
    public function setHead($str): void
    {
        $this->headInjections = $str;
    }

    /**
     * Accepts a string to be appended to the <head> element
     * @param $str
     */
    public function addHead($str): void
    {
        $this->headInjections .= $str;
    }


    /**
     * Accepts classes to be injected into the body's class attribute
     * @param $str
     */
    public function setBodyTagClass($str): void
    {
        $this->bodyTagClasses = "$str ";
    }

    /**
     * Accepts classes to be added to the body's class attribute
     * @param $str
     */
    public function addBodyTagClass($str): void
    {
        $this->bodyTagClasses .= "$str ";
    }


    /**
     * Accepts attributes to be injected into the <body> tag
     * @param $str
     */
    public function setBodyTagAttributes($str): void
    {
        $this->bodyTagAttributes = "$str ";
    }

    /**
     * Accepts attributes to be added to the <body> tag
     * @param $str
     */
    public function addBodyTagAttributes($str): void
    {
        $this->bodyTagAttributes .= "$str ";
    }


    /**
     * Accepts a string to be injected just before the </body> tag
     * @param $str
     */
    public function setBodyEndInjections($str): void
    {
        $this->bodyEndInjections = trim($str, "\t\n ")."\n";
    }

    /**
     * Accepts a string to be added to the body-end-injection
     * @param $str
     */
    public function addBodyEndInjections($str): void
    {
        $this->bodyEndInjections .= trim($str, "\t\n ")."\n";
    }


    /**
     * Accepts styles to be injected into the <head> element
     * @param string $str
     */
    public function setCss(string $str):void
    {
        $this->css = trim($str, "\t\n ")."\n";
    }

    /**
     * Accepts styles to be added to the head injection
     * @param string $str
     */
    public function addCss(string $str):void
    {
        $this->css .= trim($str, "\t\n ")."\n";
    }


    /**
     * Same as setCss(), but compiles SCSS first
     * @param string $str
     */
    public function setScss(string $str):void
    {
        $this->scss = trim($str, "\t\n ")."\n";
    }

    /**
     * Same as addCss(), but compiles SCSS first
     * @param string $str
     */
    public function addScss(string $str):void
    {
        $this->scss .= trim($str, "\t\n ")."\n";
    }


    /**
     * Accepts JS code to be injected at the end of the <body> element, but before js-files are loaded
     * @param string $str
     */
    public function setJs(string $str):void
    {
        $this->js = trim($str, "\t\n ")."\n";
    }

    /**
     * Like setJs, but does not overwrite previous injection requests
     * @param string $str
     */
    public function addJs(string $str):void
    {
        $this->js .= trim($str, "\t\n ")."\n";
    }


    /**
     * Accepts jQuery code (without the ready-statement) and injects it after loading instructions of js/jq-files
     * @param string $str
     */
    public function setJq(string $str):void
    {
        $this->jq = trim($str, "\t\n ")."\n";
    }

    /**
     * Like setJq, but does not overwrite previous injection requests
     * @param string $str
     */
    public function addJq(string $str):void
    {
        $this->jq .= trim($str, "\t\n ")."\n";
        $this->jQueryActive = true;
    }

    /**
     * Accepts jq-file-name(s) and queues them for loading; as a side effect makes sure that jQuery is loaded as well
     * @param mixed $str
     */
    public function addJqFiles($str):void
    {
        $this->addAssets($str, true);
    } // addJqFiles


    /**
     * Accepts js-file-name(s) and queues them for loading
     * @param mixed $str
     */
    public function addJsFiles($str):void
    {
        $this->addAssets($str);
    } // addJsFiles


    /**
     * Appends one or multiple asset to the asset loading queue.
     * Submit filepath to the source file, PageFactory will make sure that it will be loaded by the browser.
     * @param string $assets comma-separated-list (string) or array, each containing the filepath of the source file
     * @param false $treatAsJq
     */
    public function addAssets($assets, $treatAsJq = false): void
    {
        $assetQueue = &$this->assetFiles;
        if (!is_array($assets)) {
            $assets = explodeTrim(',', $assets, true);
        }
        foreach ($assets as $asset) {
            $asset = resolvePath($asset);
            $basename = basename($asset);
            $ext = fileExt($asset);

            // handle special case 'scss' -> just change to css, will be compiled automatically:
            if ($ext === 'scss') {
                $basename = base_name($basename, false).'.css';

            // handle special case 'jq' -> it's a normal js-file, but requires jQuery to be loaded:
            } elseif ($ext === 'jq') {
                $basename = base_name($basename, false).'.js';
                $asset = fileExt($asset, true).'.js';
                $this->jQueryActive = true;
                
            } elseif (($ext === 'js') && $treatAsJq) {
                $this->jQueryActive = true;
            }

            // add to the assets-queue now:
            if (!isset($assetQueue[$basename])) {
                $assetQueue[$basename] = [$asset];
            }

            // all assets being requested this way have to be loaded, so add this to the 'explicit queue':
            $this->requestedAssetFiles[] = $basename;
        }
    } // addAssets



    //  "PageElements" i.e. higher level functionality: overlays, messages, popups:

    /**
     * Renders content in an overlay.
     * @param string $str
     * @param false $mdCompile
     */
    public function setOverlay(string $str, $mdCompile = false): void
    {
        $pelem = new PageElements\PageOverlay($this->pfy, $this);
        $this->bodyEndInjections .= $pelem->render( $str, $mdCompile);
    } // setOverlay


    /**
     * Renders cotent in a message that appears briefly in the upper right corner.
     * @param string $str
     * @param false $mdCompile
     */
    public function setMessage(string $str, $mdCompile = false): void
    {
        $pelem = new PageMessage($this->pfy, $this);
        $this->bodyEndInjections .= $pelem->render($str, $mdCompile);
    } // setMessage


    /**
     * Renders content in a popup window.
     * @param string $str
     * @param false $mdCompile
     */
    public function setPopup(string $str, $mdCompile = false): void
    {
        $pelem = new PagePopup($this->pfy, $this);
        $this->bodyEndInjections .= $pelem->render($str, $mdCompile);
    } // setPopup



    // === Preparation methods ========================================
    /**
     * Cycles through the assets queue, expands entries with wildcards, prepares all assets for rendering.
     * -> checks up-to-date, compiles/copies to content/assets/ if required.
     */
    public function prepareAssets(): void
    {
        $this->expandWildcards();
        $modified = $this->updateAssets();

        if ($modified) {
            reloadAgent();
        }
    } // prepareAssets



    /**
     * Cycles through the assets queue, expands entries with wildcards.
     * Note: assets queue entries may contain multiple files, in this case they are wrapped into one file for
     * loading.
     */
    private function expandWildcards()
    {
        $assetGroups = $this->assetFiles;
        foreach ($assetGroups as $target => $group) {
            if ($target[0] === '*') { // copy to target with same name:
                $gr = [];
                foreach ($group as $elem) {
                    if ($elem[strlen($elem)-1] === '*') {
                        $dir = getDir($elem);
                        foreach ($dir as $file) {
                            if (is_file($file)) {
                                $ext = fileExt($file);
                                $basename = base_name($file, false);
                                switch ($ext) {
                                    case 'js':
                                        $gr["$basename.js"] = $file;
                                        break;
                                    case 'scss':
                                    case 'css':
                                        $gr["$basename.css"] = $file;
                                }
                            }
                        }
                    } else {
                        $gr[basename($elem)] = $elem;
                    }
                }
                $assetGroups = array_merge($assetGroups, $gr);
                unset($assetGroups['*']);

            } else { // aggregate files into one
                $i = 0;
                $requestedType = fileExt($target);
                while (isset($group[$i])) {
                    $elem = $group[$i];
                    $dir = true;
                    if ($elem[strlen($elem)-1] === '*') {
                        $dir = getDir($elem);
                        foreach ($dir as $j => $f) {
                            if (!is_file($f) || (strpos(fileExt($f), $requestedType) === false)) {
                                unset($dir[$j]);
                            }
                        }
                        array_splice($group, $i, 1, $dir);
                    }
                    if ($dir) {
                        $i++;
                    }
                }
                if ($group) {
                    $assetGroups[$target] = $group;
                } else {
                    unset($assetGroups[$target]);
                }

            }
        }
        $this->assetFiles = $assetGroups;
    } // expandWildcards


    /**
     * Runs through queued assetFiles, checks whether their copy in content/assets/ is up to date.
     * In case of SCSS files, it compiles them first.
     * @return bool  files modified (browser reload required)
     */
    private function updateAssets(): bool
    {
        $cachePath = PFY_CACHE_PATH . 'assets/';

        $modified = false;
        $assetQueue = &$this->assetFiles;
        foreach ($assetQueue as $groupName => $files) {
            // 0) make sure the target folder exists:
            preparePath(PFY_USER_ASSETS_PATH);

            // 1) update from sources to cache:
            if (is_string($files)) {
                $files = [$files];
            }
            $targetPath = "$cachePath$groupName/";
            preparePath($targetPath);
            foreach ($files as $srcFile) {
                $modified |= $this->updateFile($srcFile, $targetPath);
            }

            // 2) aggregate from cache to target (content/assets/):
            $srcFile = $targetPath;
            $filename = basename($srcFile, '/');
            $targetFile = PFY_USER_ASSETS_PATH . $filename;
            $tTarget = lastModified($targetFile, false);
            $tSrc = lastModified($srcFile, false);
            if ($modified || ($tTarget < $tSrc)) {
                $this->aggregateFile($srcFile, $targetFile);
            }
        }

        // update any scss files in page folder:
        $targetPath = page()->root().'/';
        $files = getDirs([$targetPath.'*.scss', $targetPath.'scss/*.scss']);
        foreach ($files as $srcFile) {
            $modified |= $this->updateFile($srcFile, $targetPath);
        }

        return $modified;
    } // updateAssets





    // === Rendering methods ==========================================
    /**
     * Assembles and renders the code that will be injected into the <head> element.
     * (Note: css-files containing '-async' will automatically be rendered for async loading)
     * @return string
     */
    public function renderHeadInjections(): string
    {
        // add misc elements from content/site.txt and the current page's frontmatter:
        $out  = $this->getHeaderElem('head');
        $out .= $this->getHeaderElem('description');
        $out .= $this->getHeaderElem('keywords');
        $out .= $this->getHeaderElem('author');
        $out .= $this->getHeaderElem('robots');

        // add injections that had been supplied explicitly:
        $out .= $this->headInjections;

        // add CSS-Files loading instructions:
        $out .= $this->getFilesLoadingCode('css');
        $out .= $this->getPageFolderAssetsCode('css');

        // add CSS-Code (compile if it's SCSS):
        $css = @$this->pageParams['css'] . @$this->frontmatter['css'] . $this->css;
        $this->pageParams['css'] = $this->frontmatter['css'] = $this->css = false;

        $scss = @$this->pageParams['scss'] . @$this->frontmatter['scss']. $this->scss;
        $this->pageParams['scss'] = $this->frontmatter['scss'] = $this->scss = false;

        if ($scss) {
            $css .= $this->sc->compileStr($scss);
        }
        if ($css) {
            $css = indentLines($css, 8);
            $out .= <<<EOT
    <style>
$css
    </style>
EOT;
        }
        return $out;
    } // renderHeadInjections



    /**
     * Assembles and renders the body-end-injections, i.e. js-code and js-files loading instructions
     * @return string
     */
    private function renderBodyEndInjections(): string
    {
        $jsInjection = '';
        $jqInjection = '';
        $miscInjection = "\n$this->bodyEndInjections";

        $js = "var screenSizeBreakpoint = {$this->pfy->screenSizeBreakpoint}\n";
        $js .= "const hostUrl = '" . PageFactory::$appRoot . "';\n";
        $js .= $this->js . @$this->frontmatter['js'];
        if ($js) {
            $js = "\t\t".str_replace("\n", "\n\t\t", rtrim($js, "\n"));
            $jsInjection .= <<<EOT

    <script>
$js
    </script>

EOT;
        }

        $jq = $this->jq . @$this->frontmatter['jq'];
        if ($jq) {
            $this->jQueryActive = true;
            $jq = "\t\t\t".str_replace("\n", "\n\t\t\t", rtrim($jq, "\n"));
            $jqInjection .= <<<EOT

    <script>
        $(document).ready(function() {
$jq
        });        
    </script>

EOT;
        }

        $jsFilesInjection = $this->getFilesLoadingCode('js');
        if ($this->jQueryActive) {
            $jsFilesInjection = $this->getFileLoadingCode(basename(JQUERY), 'js').$jsFilesInjection;
        }
        $jsFilesInjection .= $this->getPageFolderAssetsCode('js');


        // now assemble final output for body end injection:
        $out = <<<EOT

$jsInjection
$jsFilesInjection
$jqInjection
$miscInjection
EOT;
        return $out;
    } // renderBodyEndInjections



    // === Helper methods ============================================
    /**
     * Cycles through the load-queue, renders loading code for each element.
     * Assets not starting with '-' and not queued explicitly will be skipped.
     * @param string $type
     * @return string
     * @throws Exception
     */
    private function getFilesLoadingCode(string $type): string
    {
        $queuedSssFiles = $this->getQueuedFiles($type);

        $out = '';
        if ($queuedSssFiles) {
            foreach ($queuedSssFiles as $filename) {
                // skip files if they are not for autoload ('-xxx') and not in requestedAssetFiles:
                if ((!in_array($filename, $this->requestedAssetFiles)) && ($filename[0] !== '-')) {
                    continue; // autoload only files starting with '-'
                }
                $out .= $this->getFileLoadingCode($filename, $type);
            }
        }
        return $out;
    } // getFilesLoadingCode



    /**
     * Renders loading code for given asset. If css-filename contains '-async', file will be loaded asynchronously.
     * @param string $filename
     * @param false $type
     * @return string
     * @throws Exception
     */
    private function getFileLoadingCode(string $filename, $type = false): string
    {
        if (!$type) {
            $type = fileExt($filename);
        } elseif (fileExt($filename) !== $type) {
            return '';
        }
        $siteFiles = $this->pfy->pages->files();
        if (strpos($filename, '/') === false) {
            $targetFile = "assets/$filename";
            $file = (string)$siteFiles->find($targetFile);
        } else {
            $file = $filename;
        }
        if ($file) {
            if ($type === 'css') {
                if (strpos($file, '-async') !== false) {
                    $out = "\t<link href='$file' rel='stylesheet' media='print' class='lzy-onload-css' />\n";
                    $out .= "\t<noscript><link href='$file' rel='stylesheet' /></noscript>\n";
                } else {
                    $out = "\t<link href='$file' rel='stylesheet' />\n";
                }
            } else {
                $out = "\t<script src='$file'></script>\n";
            }
        } else {
            throw new Exception("Error: file '$filename' not found.");
        }

        return $out;
    } // getFileLoadingCode



    /**
     * Returns asset loading code for files in the current page folder of requested type
     * @param string $type
     * @return string
     */
    private function getPageFolderAssetsCode(string $type): string
    {
        $out = '';
        $pageFiles = page()->files();

        if ($type === 'css') {
            $files = $pageFiles->filterBy('extension', 'css');
            foreach ($files as $file) {
                $f = (string)$file;
                if (basename($f)[0] === '#') { // skip commented files
                    continue;
                }
                $out .= $this->getFileLoadingCode($f, 'css');
            }

        } else {
            $files = $pageFiles->filterBy('extension', 'js');
            foreach ($files as $file) {
                $f = (string)$file;
                if (basename($f)[0] === '#') { // skip commented files
                    continue;
                }
                $out .= $this->getFileLoadingCode($f, 'js');
            }
        }
        return $out;
    } // getPageFolderAssetsCode



    /**
     * Returns queued assets of given type (css or js).
     * @param string $type
     * @return array
     */
    private function getQueuedFiles(string $type): array
    {
        $queuedSssFiles = array_keys($this->assetFiles);
        $queuedSssFiles = array_filter($queuedSssFiles, function ($f) use($type) {
            return (fileExt($f) === $type);
        });
        return $queuedSssFiles;
    }



    /**
     * Retrieves data regarding one of head, description, keywords, author, robots
     * @param $name
     * @return string
     */
    private function getHeaderElem(string $name): string
    {
        // checks page-attrib, then site-attrib for requested keyword and returns it
        $out = @$this->frontmatter[$name];
        if ($name === 'robots') {
            if ($out === false) {
                $out = 'noindex';
            } elseif ($out === true) {
                return ''; // skip, 'index' is already default
            }

        }
        if (!$out) {
            if (!$out = page()->$name()->value()) {
                $out = site()->$name()->value();
            }
        }
        if ($out) {
            if (stripos($out, '<meta') === false) {
                $out = "\t<meta name='$name' content='$out'>\n";
            } else {
                $out = trim($out, "\n\t ");
                $out = "\t$out\n";
            }
            return $out;
        } else {
            return '';
        }
    } // getHeaderElem



    /**
     * Checks given file whether it's copy in content/assets/ is up-to-date, copies/compiles it if necessary.
     * @param string $srcFile
     * @param string $targetPath
     * @return bool     files modified (browser reload required)
     */
    private function updateFile(string $srcFile, string $targetPath): bool
    {
        if (!file_exists($srcFile)) {
            return false;
        }
        $modified = false;
        $targetFile = $targetPath . basename($srcFile);

        if ($isScss = ((strrpos($srcFile, '/--') === false) && (fileExt($srcFile) === 'scss'))) {
            $targetFile = rtrim($targetFile, 'scss') . 'css';
        }
        $tTarget = lastModified($targetFile, false);
        $tSrc = lastModified($srcFile, false);
        if ($tTarget < $tSrc) {
            if ($isScss) {
                $this->sc->compileFile($srcFile, $targetFile);
            } else {
                copy($srcFile, $targetFile);
            }
            $modified = true;
        }
        if ($modified) {
            touch($targetPath);
        }
        return $modified;
    } // updateFile



    /**
     * Copies content of multiple source files into one target file.
     * @param string $srcFolder
     * @param string $targetFile
     */
    private function aggregateFile(string $srcFolder, string $targetFile): void
    {
        file_put_contents($targetFile, '');
        $files = getDir("$srcFolder*");
        $files = array_filter($files, function($f) {
            return (strpos($f, '/--') === false);
        });
        $addRef = (sizeof($files) > 1);
        foreach ($files as $file) {
            $str = file_get_contents($file);
            if ($addRef) {
                $filename = basename($file);
                $str = "/* @@@@@@@@ Imported from $filename @@@@@@@@ */\n\n" . $str;
            }
            file_put_contents($targetFile, $str, FILE_APPEND);
        }
    } // aggregateFile

} // PageExtruder

