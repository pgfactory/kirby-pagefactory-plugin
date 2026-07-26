<?php

namespace PgFactory\PageFactory;


use PgFactory\MarkdownPlus\MdPlusHelper;
use ScssPhp\ScssPhp\Exception\SassException;

class Page
{
    private static string $content = '';
    private static string $headInjections = '';
    public static string $bodyEndInjections = '';
    public static string $bodyTagClasses = '';
    public static string $bodyTagAttributes = '';
    public static string $css = '';
    public static string $scss = '';
    public static string $js = '';
    public static string $jsWhenReady = '';

    public static array $override = [];

    private static string $description = '';
    private static string $keywords = '';
    private static string $author = '';
    private static string|bool $robots = false;
    public static array|null $asset = [];
    private static string|false $overrideContent = false;
    public static array|null $definitions;


    // === Helper methods: accept queuing requests from macros and other objects ============

    /**
     * Tells PageExtruder to make sure jsFramework will be loaded.
     * @return void
     */
    public static function requireFramework(): void
    {
        //ToDo
        // Assets::requireFramework();
    } // requireFramework


    /**
     * Generic getter
     * @param string $key
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        return self::$$key ?? null;
    } // get


    /**
     * Generic setter
     * @param string $key
     * @param $value
     */
    public static function set(string $key, $value): void
    {
        self::$$key = $value;
    }


    /**
     * Appends $value to property identified by $key.
     * If renderingClosed (i.e. when rendering twig template), the $value is appended to the kirby field instead.
     * @param string $key
     * @param $value
     */
    public static function append(string $key, $value): void
    {
        if (PageFactory::$renderingClosed) {
            throw new \Exception("Error: Rendering closed for $key = '$value'");

        } elseif (!str_contains(self::$$key, $value)) { // avoid repetitions
            self::$$key .= $value;
        }
    } // append


    /**
     * Accepts a string to be injected into the <head> element
     * @param $str
     */
    public static function addHead(string $str): void
    {
        self::append('headInjections', $str);
    }


    /**
     * @param bool|string $robots
     * @return void
     */
    public static function applyRobotsAttrib(bool|string $robots = true): void
    {
        self::$robots = $robots;
    } // applyRobotsAttrib


    /**
     * Accepts a string which will replace the original page content.
     * @param string $str
     */
    public static function overrideContent(string $str, bool $compile = true): void
    {
        if ($compile) {
            $str = TransVars::compile($str);
        }
        self::$overrideContent = $str;

        // save states of in-text assets:
        self::$override['css'] = self::$css;
        self::$override['scss'] = self::$scss;
        self::$override['js'] = self::$js;
        self::$override['jsWhenReady'] = self::$jsWhenReady;
    } // overrideContent


    /**
     * Proxy for extension PageElements -> Overlay -> overrides page if Overlay not available.
     * @param string $str
     * @param bool $mdCompile
     */
    public static function setOverlay(string $str, bool $mdCompile = true): void
    {
        if (isset(Extensions::$availableExtensions['pageelements'])) {
            $pe = new \PgFactory\PageFactoryElements\Overlay();
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
    public static function setMessage(string $str, bool $mdCompile = true): void
    {
        if (isset(Extensions::$availableExtensions['pageelements'])) {
            $pe = new \PgFactory\PageFactoryElements\Message();
            $pe->set($str, $mdCompile);

        // if PageElements are not loaded, we need to create bare page and exit immediately:
        } else {
            if ($mdCompile) {
                $str = compileMarkdown($str);
            }
            self::addJsReady("window.alert('$str')");
        }
    } // setMessage


    /**
     * Proxy for extension PageElements -> Popup -> displays popup dialog.
     * @param string $str
     * @param string $header
     * @param bool $mdCompile
     * @return void
     */
    public static function setPopup(string $str, string $header, bool $mdCompile = true): void
    {
        if (isset(Extensions::$availableExtensions['pageelements'])) {
            $pe = new \PgFactory\PageFactoryElements\Popup();
            $pe->set($str, $header, $mdCompile);

        // if PageElements are not loaded, we need to create bare page and exit immediately:
        } else {
            if ($mdCompile) {
                $str = compileMarkdown($str);
            }
            self::addJsReady("window.alert('$str')");
        }
    } // setPopup


    /**
     * Accepts classes to be injected into the body's class attribute
     * @param $str
     */
    public static function addBodyTagClass(string $str): void
    {
        self::append('bodyTagClasses', "$str ");
    }


    /**
     * Accepts attributes to be injected into the <body> tag
     * @param $str
     */
    public static function addBodyTagAttributes(string $str): void
    {
        self::append('bodyTagAttributes', "$str ");
    }


    /**
     * Accepts a string to be injected just before the </body> tag
     * @param $str
     */
    public static function addBodyEndInjections(string $str): void
    {
        self::append('bodyEndInjections', trim($str, "\t\n ")."\n");
    } // addBodyEndInjections


    /**
     * Accepts styles to be injected into the <head> element
     * @param string $str
     */
    public static function addCss(string $str): void
    {
        self::append('css', trim($str, "\t\n ")."\n");
    }


    /**
     * Same as addCss(), but compiles SCSS first
     * @param string $str
     */
    public static function addScss(string $str): void
    {
        self::append('scss', trim($str, "\t\n ")."\n");
    }


    /**
     * Accepts JS code to be injected at the end of the <body> element, but before js-files are loaded
     * @param string $str
     */
    public static function addJs(string $str): void
    {
        self::append('js', trim($str, "\t\n ")."\n");
    }


    /**
     * Accepts jsFramework code (without the ready-statement) and injects it after loading instructions of js/jsReady-files
     * @param string $str
     */
    public static function addJsReady(string $str): void
    {
        self::append('jsWhenReady', trim($str, "\t\n ")."\n");
    }


    /**
     * Forwards call to Assets->addAssets()
     * @param mixed $assets  array or comma separated list
     * @return void
     */
    public static function addAssets(mixed $assets): void
    {
        if (PageFactory::$renderingClosed) {
            throw new \Exception("Error: a Macro is trying to queue a resource after page rendering has finished.");
        }
        Assets::addAssets($assets);
    } // addAssets


    // === Rendering methods ==========================================
    /**
     * @param string $html
     * @return string
     */
    public static function renderBody(string $html): string
    {
        if (self::$overrideContent) {
            $html = self::$overrideContent;
            $html = TransVars::resolveVariables($html);
        }
        return $html;
    } // renderBody


    /**
     * Assembles and renders the code that will be injected into the <head> element.
     * (Note: css-files containing '-async' will automatically be rendered for async loading)
     * @return string
     * @throws SassException
     */
    public static function renderHeadInjections(): string
    {
        $page = page();
        // check config settings, whether default-nav should be activated:
        if (PageFactory::$config['default-nav']) {
            self::addAssets('NAV');
        }

        // case override: restore assets to time of override-invokation:
        if (self::$overrideContent) {
            self::$css = self::$override['css'];
            self::$scss = self::$override['scss'];
        }


        // add misc elements from content/site.txt and the current page's frontmatter:
        $html  = self::getHeaderElem('head');
        $html .= self::getHeaderElem('description');
        $html .= self::getHeaderElem('keywords');
        $html .= self::getHeaderElem('author');
        $html .= self::getHeaderElem('robots');

        // add injections that had been supplied explicitly:
        $html .= self::$headInjections;

        // add CSS-Files loading instructions:
        $html .= Assets::renderCssLoadingCode();

        // add CSS-Code (compile if it's SCSS):
        $css = self::$css ? self::$css."\n" : '';
        $css .= $page->css()->value() ?? '';   // css from meta-file

        $scss = self::$scss ? self::$scss."\n" : '';
        $scss .= $page->scss()->value() ?? ''; // scss from meta-file

        if ($scss) {
            $css .= "\n".Scss::compileStr($scss);
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
    public static function renderBodyEndInjections(): string
    {
        self::addBodyEndInjections(MdPlusHelper::getBodyEndInjections());

        // case override: restore assets to time of override-invokation:
        if (self::$overrideContent) {
            self::$js = self::$override['js'];
            self::$jsWhenReady = self::$override['jsWhenReady'];
        }

        $page = page();

        $jsInjection = '';
        $jsReadyInjection = '';
        $miscInjection = "\n".self::$bodyEndInjections;
        $screenSizeBreakpoint = PageFactory::$config['screenSizeBreakpoint'] ?? false;
        $screenSizeBreakpoint = $screenSizeBreakpoint ?: 480;

        $js = "var screenSizeBreakpoint = $screenSizeBreakpoint;\n";
        $js .= "const hostUrl = '" .        PFY_APP_BASE_URL . "';\n";
        $js .= "const hostAssetUrl = '" .   PFY_APP_BASE_URL . PFY_BASE_OFFSET. "';\n";
        $js .= "const pageUrl = '" .        PFY_HOST_URL . ltrim($_SERVER['REQUEST_URI'], '/') . "';\n";
        $js .= "const pageUrl0 = '" .       PFY_PAGE_URL . "';\n";
        $js .= "const pageId = '" .         page()->id() . "';\n";
        $js .= "const loggedinUser = '" .   PageFactory::$userName . "';\n";
        $js .= "const currLang = '" .       PageFactory::$langCode . "';\n";
        $js .= "const pageLoaded =          Math.floor(Date.now()/1000);\n";
        $js .= self::$js ? self::$js."\n": '';
        $js .= $page->js()->value() ?? ''; // js from meta-file

        if (option('pgfactory.pagefactory.pageSwipeEnabled', false)) {
            $js .= "const pfyPageSwipeEnabled = true;\n";
        }
        if (option('pgfactory.pagefactory.pageSwitchingKeysEnabled', false) || PageFactory::$dev) {
            $js .= "const pfyPageSwitchingKeysEnabled = true;\n";
        }

        if ($js) {
            $js = "\t\t".str_replace("\n", "\n\t\t", rtrim($js, "\n"));
            $jsInjection .= <<<EOT

    <script>
$js
        if (document.documentElement.clientWidth < screenSizeBreakpoint) {
            document.body.classList.remove('pfy-large-screen');
            document.body.classList.add('pfy-small-screen');
        } else {
            document.body.classList.remove('pfy-small-screen');
            document.body.classList.add('pfy-large-screen');
        }
    </script>

EOT;
        }

        $jsWhenReady = self::$jsWhenReady ? self::$jsWhenReady."\n": '';
        $jsWhenReady .= $page->jsWhenReady()->value() ?? ''; // jsReady from meta-file
        if ($jsWhenReady) {
            $jsWhenReady = "\t\t\t".str_replace("\n", "\n\t\t\t", rtrim($jsWhenReady, "\n"));
            $jsReadyInjection .= <<<EOT

    <script>
document.addEventListener('DOMContentLoaded', function() {
$jsWhenReady
});
</script>

EOT;
        }

        $jsFilesInjection = Assets::renderJsLoadingCode();

        $busySpinner = Utils::renderBusySpinner();

        // now assemble final output for body end injection:
        $html = <<<EOT

$busySpinner
$jsInjection
$jsFilesInjection
$jsReadyInjection
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
    private static function getHeaderElem(string $name): string
    {
        // checks page-attrib, then site-attrib for requested keyword and returns it
        $out = page()->$name()->value() ?? '';
        if (!$out) {
            $out = site()->$name()->value();
        }

        // check frontmatter for overriding setting:
        if (in_array($name, ['description', 'keywords', 'author', 'robots']) && (self::$$name ?? false)) {
            $out = self::$$name; // overridden by frontmatter
        }

        // in dev-mode, always include robots-tag:
        if ($name === 'robots' && PageFactory::$dev) {
            $out = true;
        }

        if (!$out) {
            return '';
        }

        if ($name === 'robots') {
            $out = self::getRobotsElem($out);
        } else {
            if (stripos($out, '<meta') === false) {
                $out = "  <meta name='$name' content='$out'>\n";
            } else {
                $out = trim($out, "\n\t ");
                $out = "  $out\n";
            }
        }
        return $out;
    } // getHeaderElem


    /**
     * @param string|bool $value
     * @return string
     */
    private static function getRobotsElem(string|bool $value): string
    {
        if ($value) {
            $val = is_string($value) ? $value : true;
            if (is_bool($val) || $val === 'true' || $val === 'false') {
                $val = 'noindex,nofollow,noarchive';
            }
            return "  <meta name='robots' content='$val'>\n";
        } else {
            return '';
        }
    } // getRobotsElem

} // Page

