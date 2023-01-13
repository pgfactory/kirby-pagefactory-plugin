<?php

/**
 * This is the engine that accepts elements and at the end churns out the final page components.
 * The final step of assembling those components is done by PageFactory->assembleHtml().
 */

namespace Usility\PageFactory;


use ScssPhp\ScssPhp\Exception\SassException;

class Page
{
    public static string $content = '';
    public string $headInjections = '';
    public string $bodyEndInjections = '';
    public string $bodyTagClasses = '';
    public string $bodyTagAttributes = '';
    public string $css = '';
    public string $scss = '';
    public string $js = '';
    public string $jq = '';
    public array|null $assetFiles = [];
    public bool $overrideContent = false;
    public static array|null $definitions;
    private object $pfy;
    private object $trans;
    private Scss $sc;


    /**
     * @param $pfy
     */
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->trans = $pfy::$trans;
        $this->sc = new Scss();
        $this->assetFiles = &$pfy->assetFiles;
        self::$definitions['assets'] = ASSET_URL_DEFINITIONS;
    } // __construct


    /**
     * Loads extensions, i.e. plugins with names "pagefactory-*":
     */
    public function loadExtensions(): void {
        // check for and load extensions:
        if (PageFactory::$availableExtensions) {
            foreach (PageFactory::$availableExtensions as $extPath) {
                // look for 'src/index.php' within the extension:
                $indexFile = "{$extPath}src/index.php";
                if (!file_exists($indexFile)) {
                    return;
                }
                // === load index.php now:
                $extensionClassName = require_once $indexFile;
                PageFactory::$loadedExtensions[$extensionClassName] = $extPath;

                // instantiate extension object:
                $extensionClass = "Usility\\PageFactoryElements\\$extensionClassName";
                $obj = new $extensionClass($this->pfy);

                // check for and load extension's asset-definitions:
                if (method_exists($obj, 'getAssetDefs')) {
                    $newAssets = $obj->getAssetDefs();
                    self::$definitions = array_merge_recursive(self::$definitions, ['assets' => $newAssets]);
                }

                // check for and load extension's asset-definitions:
                if (method_exists($obj, 'getAssetGroups')) {
                    $newAssetGroupss = $obj->getAssetGroups();
                    PageFactory::$assets->addAssetGroups($newAssetGroupss);
                }
            }
        }
    } // loadExtensions


    /**
     * Checks loaded extensions whether they contain a special file 'src/_finalCode.php' and executes it.
     * @return void
     */
    public function extensionsFinalCode(): void
    {
        foreach (PageFactory::$loadedExtensions as $path) {
            $file = $path.'src/_finalCode.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    } // extensionsFinalCode



    // === Helper methods: accept queuing requests from macros and other objects ============

    /**
     * Tells PageExtruder to make sure jsFramework will be loaded.
     * @return void
     */
    public function requireFramework(): void
    {
        PageFactory::$assets->requireFramework();
    } // requireFramework


    /**
     * Generic getter
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->$key ?? null;
    } // get


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
     * Accepts a string which will replace the original page content.
     * @param string $str
     */
    public function overrideContent(string $str): void
    {
        $this->overrideContent = $str;
    } // overrideContent


    /**
     * Proxy for extension PageElements -> Overlay -> overrides page if Overlay not available.
     * @param string $str
     * @param bool $mdCompile
     */
    public function setOverlay(string $str, bool $mdCompile = true): void
    {
        if (isset(PageFactory::$availableExtensions['pageelements'])) {
            $pe = new \Usility\PageFactoryElements\Overlay($this->pfy);
            $pe->set($str, $mdCompile);

        // if PageElements are not loaded, we need to create bare page and exit immediately:
        } else {
            if ($mdCompile) {
                $str = markdown($str);
            }
            $html = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title></title>
</head>
<body>
$str
</body>
</html>

EOT;
            exit($html);
        }
    } // setOverlay


    /**
     * Proxy for extension PageElements -> Message -> displays message in upper right corner.
     * @param string $str
     * @param bool $mdCompile
     * @return void
     */
    public function setMessage(string $str, bool $mdCompile = true): void
    {
        if (isset(PageFactory::$availableExtensions['pageelements'])) {
            $pe = new \Usility\PageFactoryElements\Message($this->pfy);
            $pe->set($str, $mdCompile);

        // if PageElements are not loaded, we need to create bare page and exit immediately:
        } else {
            if ($mdCompile) {
                $str = compileMarkdown($str);
            }
            $this->addJq("window.alert('$str')");
        }
    } // setMessage


    /**
     * Proxy for extension PageElements -> Message -> displays message in upper right corner.
     * @param string $str
     * @param $mdCompile
     * @return void
     */
    public function setPopup(string $str, $mdCompile = true): void
    {
        if (isset(PageFactory::$availableExtensions['pageelements'])) {
            $pe = new \Usility\PageFactoryElements\Popup($this->pfy);
            $pe->set($str, $mdCompile);

        // if PageElements are not loaded, we need to create bare page and exit immediately:
        } else {
            if ($mdCompile) {
                $str = compileMarkdown($str);
            }
            $this->addJq("window.alert('$str')");
        }
    } // setMessage


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
    } // setCss


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
     * Accepts jsFramework code (without the ready-statement) and injects it after loading instructions of js/jq-files
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
        $this->requireFramework();
    }


    /**
     * Forwards call to Assets->addAssets()
     * @param mixed $assets  array or comma separated list
     * @param bool $treatAsJq
     * @return void
     */
    public function addAssets(mixed $assets, bool $treatAsJq = false): void
    {
        PageFactory::$assets->addAssets($assets, $treatAsJq);
    } // addAssets


    // === Rendering methods ==========================================
    /**
     * Assembles and renders the code that will be injected into the <head> element.
     * (Note: css-files containing '-async' will automatically be rendered for async loading)
     * @return string
     * @throws SassException
     */
    public function renderHeadInjections(): string
    {
        // add misc elements from content/site.txt and the current page's frontmatter:
        $html  = $this->getHeaderElem('head');
        $html .= $this->getHeaderElem('description');
        $html .= $this->getHeaderElem('keywords');
        $html .= $this->getHeaderElem('author');
        $html .= $this->getHeaderElem('robots');

        // add injections that had been supplied explicitly:
        $html .= $this->headInjections;

        // add CSS-Files loading instructions:
        $html .= PageFactory::$assets->renderQueuedAssets('css');

        // add CSS-Code (compile if it's SCSS):
        $css = $this->css ? "$this->css\n": '';
        $css .= PageFactory::$page->css()->value() ?? '';

        $scss = $this->scss ? "$this->scss\n": '';
        $scss .= PageFactory::$page->scss()->value() ?? '';

        if ($scss) {
            $css .= "\n".$this->sc->compileStr($scss);
        }
        if ($css) {
            $css = indentLines($css, 8);
            $html .= <<<EOT
    <style>
$css
    </style>
EOT;
        }
        return $html;
    } // renderHeadInjections


    /**
     * Assembles and renders the body-end-injections, i.e. js-code and js-files loading instructions
     * @return string
     */
    public function renderBodyEndInjections(): string
    {
        $jsInjection = '';
        $jqInjection = '';
        $miscInjection = "\n$this->bodyEndInjections";
        $screenSizeBreakpoint = PageFactory::$config['screenSizeBreakpoint']??false;
        if (!$screenSizeBreakpoint) {
            $screenSizeBreakpoint = 480;
        }

        $js = "var screenSizeBreakpoint = $screenSizeBreakpoint\n";
        $js .= "const hostUrl = '" .        PageFactory::$appRoot . "';\n";
        $js .= "const pageUrl = '" .        PageFactory::$pageUrl . "';\n";
        $js .= "const loggedinUser = '" .   PageFactory::$user . "';\n";
        $js .= "const currLang = '" .       PageFactory::$langCode . "';\n";
        $js .= $this->js ? "$this->js\n": '';
        $js .= PageFactory::$page->js()->value() ?? '';

        if ($js) {
            $js = "\t\t".str_replace("\n", "\n\t\t", rtrim($js, "\n"));
            $jsInjection .= <<<EOT

    <script>
$js
    </script>

EOT;
        }

        $jq = $this->jq ? "$this->jq\n": '';
        $jq .= PageFactory::$page->jq()->value() ?? '';
        if ($jq) {
            $this->requireFramework();
            $jq = "\t\t\t".str_replace("\n", "\n\t\t\t", rtrim($jq, "\n"));
            $jqInjection .= <<<EOT

    <script>
        $(document).ready(function() {
$jq
        });        
    </script>

EOT;
        }

        // check config settings, whether default-nav should be activated:
        if (PageFactory::$config['default-nav']??false) {
            $this->requireFramework();
            $this->addAssets('site/plugins/pagefactory/assets/js/nav.jq');
        }

        $jsFilesInjection = PageFactory::$assets->renderQueuedAssets('js');

        // now assemble final output for body end injection:
        $html = <<<EOT

$jsInjection
$jsFilesInjection
$jqInjection
$miscInjection
EOT;
        return $html;
    } // renderBodyEndInjections


    // === Helper methods ============================================
    /**
     * Retrieves data regarding one of head, description, keywords, author, robots
     * @param string $name
     * @return string
     */
    private function getHeaderElem(string $name): string
    {
        // checks page-attrib, then site-attrib for requested keyword and returns it
        $out = PageFactory::$page->$name()->value() ?? '';
        if (!$out) {
            $out = site()->$name()->value();
        }

        if (($name === 'robots') && ($out === 'true' || $out === 'false')) {
            // => any string value is rendered as is in robots meta tag
            $out = 'noindex,nofollow,noarchive';
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

} // Page

