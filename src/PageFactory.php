<?php

namespace Usility\PageFactory;

use Kirby;
use Kirby\Data\Yaml as Yaml;
use Kirby\Cms\Language;

define('PFY_PLUGIN_PATH',           'site/plugins/pagefactory/');
define('PFY_DEFAULT_TEMPLATE_FILE', 'site/templates/page_template.html');
define('PFY_USER_ASSETS_PATH',      'content/assets/');
define('PFY_CONFIG_FILE',           'site/config/pagefactory.yaml');
define('PFY_USER_CODE_PATH',        'site/custom/');
define('PFY_MACROS_PATH',           'site/plugins/pagefactory/src/macros/');
define('PFY_MACROS_PLUGIN_PATH',    'site/plugins/pagefactory-macros/');
define('PFY_CSS_PATH',              'assets/');
define('PFY_ASSETS_PATHNAME',       'assets/');
define('PFY_LOGS_PATH',             'site/logs/');
define('PFY_CACHE_PATH',            'site/.#pfy-cache/');
define('PFY_MKDIR_MASK',             0700); // permissions for file accesses by PageFactory
define('PFY_DEFAULT_TRANSVARS',     'site/config/transvars.yaml');


require_once __DIR__ . '/../third_party/vendor/autoload.php';
require_once __DIR__ . '/helper.php';


class PageFactory
{
    public static $appRoot = null;
    public static $absAppRoot = null;
    public static $pagePath = null;
    public static $lang = null;
    public static $langCode = null;
    public static $trans = null;
    public static $debug = null;

    public function __construct($pages)
    {
        $this->kirby = kirby();
        $this->session = $this->kirby->session();
        $this->getPfyConfig();
        $this->determineDebugState();

        $this->page = page();
        $this->slug = $this->kirby->path();
        if (preg_match('|^(.*?) / ([A-Z]{5,15})$|x', $this->slug, $m)) {
            $this->slug = $m[1];
            $this->urlToken = $m[2];
        }
        $this->pages = $pages;
        $this->site = site();
        $this->siteOptions = $this->kirby->options();
        self::$trans = new TransVars($this);

        $this->setTimezone();

        $this->pageParams = $this->page->content()->data();
        $this->determineLanguage();
        $this->content = (string)$this->page->text()->kt();
        $this->mdContent = '';
        $this->bodyTagClasses = '';
        $this->bodyTagAttributes = '';
        $this->css = '';
        $this->scss = '';
        $this->js = '';
        $this->jq = '';
        $this->frontmatter = [];
        self::$pagePath = substr($this->page->root(), strlen(site()->root())) . '/';
        self::$absAppRoot = dirname($_SERVER['SCRIPT_FILENAME']).'/';
        self::$appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';

        $this->siteTitle = (string)site()->title()->value();

        $pgPath = str_replace('/','_', trim(self::$pagePath, '/'));
        $this->cssFiles = [
            '-pagefactory.css' => [
                'site/plugins/pagefactory/scss/normalize.scss',
                'site/plugins/pagefactory/scss/core.scss',
                'site/plugins/pagefactory/scss/misc.scss',
                'site/plugins/pagefactory/scss/nav.scss',
                'site/plugins/pagefactory/scss/buttons.scss',
                'site/plugins/pagefactory/scss/skiplinks.scss',
                'site/plugins/pagefactory/scss/texts.scss',
                ],
            '-pagefactory-async.css' => [
                'site/plugins/pagefactory/scss/printing.scss',
                ],
            '-styles.css' => [
                PFY_USER_ASSETS_PATH . 'scss/*',
                PFY_USER_ASSETS_PATH . 'css/*',
                ],
            "$pgPath.css" => [
                self::$pagePath . '*',
            ],
        ];
        $this->jsFiles = [
            'site/plugins/pagefactory/third_party/jquery/jquery-3.6.0.min.js',
            'site/plugins/pagefactory/js/helper.js',
        ];
        
        $this->headInjections = '';
        $this->bodyTagClasses = '';
        $this->bodyTagAttributes = '';
        $this->bodyEndInjections = '';
    } // __construct



    public function render($templateFile = false)
    {
        $this->determineTemplateFile($templateFile);

        $this->loadMdFiles();

        $this->setStandardVariables();

        $html = $this->assembleHtml();
        $html = resolveLinks($html);
        $html = $this->unshield($html);
        return $html;
    } // render



    private function loadMdFiles()
    {
        $files = $this->findMdFiles();
        $inx = 1;
        $currLang = self::$langCode;
        foreach ($files as $fileObj) {
            $file = $fileObj->root();
            if (preg_match('/\.(\w\w\d?)\.\w{2,5}$/', $file, $m)) {
                if ($m[1] !== $currLang) {
                    continue;
                }
            }
            $mdStr = loadFile($file, 'cStyle');
            $mdStr = $this->extractFrontmatter($mdStr);
            $class = translateToClassName(basename($fileObj->id(), '.md'));
            $wrapperTag = @$this->frontmatter['wrapperTag'];
            if ($wrapperTag === null) {
                $wrapperTag = 'section';
            }
            if ($wrapperTag) {
                $this->mdContent .= <<<EOT

@@@@@@@@@@ <$wrapperTag #lzy-section-$inx .lzy-section-wrapper .lzy-section-$inx .lzy-section-$class

$mdStr

@@@@@@@@@@

EOT;
            } else {
                $this->mdContent .= $mdStr;
            }
            $inx++;
        }
    } // loadMdFiles



    private function extractFrontmatter($mdStr)
    {
        if (strpos($mdStr, '---') === 0) {
            $p = strpos($mdStr, "\n");
            $marker = substr($mdStr, 0, $p);
            $mdStr = substr($mdStr, $p+1);
            $p = strpos($mdStr, $marker);
            $frontmatter = substr($mdStr, 0, $p);
            $p = strpos($mdStr, "\n", $p);
            $mdStr = substr($mdStr, $p+1);
            $this->frontmatter = Yaml::decode($frontmatter, 'yaml');

            if (@$this->frontmatter->variables) {
                foreach ($this->frontmatter->variables as $varName => $value) {
                    self::$trans->setVariable($varName, $value);
                }
            }
        }
        return $mdStr;
    } // extractFrontmatter



    private function assembleHtml()
    {
        $html = loadFile($this->templateFile);
        $html = $this->shieldLateTranslatationVariables($html); // {{@ ...}}

        $content = $this->getContent(); // content of all .md files in page folder

        self::$trans->setVariable('content', $content);

        // repeat until no more variables appear in html:
        while (preg_match('/(?<!\\\){{/', $html)) {
            $html = self::$trans->translate($html);
            unshieldStr($html);
        }

        // last pass: now replace injection variables:
        $html = $this->unshieldLateTranslatationVariables($html);
        $this->applyHeadInjections();
        $this->applyBodyEndInjections();
        $html = self::$trans->translate($html);
        unshieldStr($html);

        // remove shields in specific cases:
        $html = preg_replace("/\\\BR/ms", "BR", $html);
        return $html;
    } // assembleHtml



    private function unshield($html)
    {
        return str_replace(['\\{', '\\~'], ['{', '~'], $html);
    }



    public function addCss($css)
    {
        $this->css .= "\n$css";
    } // addCss



    public function addScss($scss)
    {
        $this->scss .= "\n$scss";
    } // addScss



    public function addJs($js)
    {
        $this->js .= "\n$js";
    } // addJs



    public function addJq($jq)
    {
        $this->jq .= "\n$jq";
    } // addJq



    public function addModules($modules)
    {
        if (is_string($modules)) {
            $modules = [ $modules ];
        }
        foreach ($modules as $module) {
            $ext = fileExt($module);
            if ($ext === 'css') {
                if (in_array($module, $$this->cssFiles)) {
                    $this->cssFiles[] = $module;
                }
            } elseif ($ext === 'js') {
                if (in_array($module, $$this->jsFiles)) {
                    $this->jsFiles[] = $module;
                }
            }
        }
    }


    
    public function addHeadInjections($html)
    {
        $this->headInjections .= $html;
    }



    public function addBodyEndInjections($html)
    {
        $this->bodyEndInjections .= $html;
    }



    public function addBodyTagAttributes($str)
    {
        $this->bodyTagAttributes .= $str;
    }


    
    public function addBodyTagClasses($str)
    {
        $this->bodyTagClasses .= $str;
    }



    private function setStandardVariables()
    {
        self::$trans->setVariable('nav', $this->getNav());

        $this->setBodyClasses();
        $this->setBodyAttributes();
        $this->setLanguageSelector();

        $appUrl = site()->url();
        $siteTitle = site()->title()->value();
        self::$trans->setVariable('lzy-site-title', $siteTitle);
        self::$trans->setVariable('lzy-page-title', $this->page->title() . ' / ' . $siteTitle);
        $siteAttributes = site()->content()->data();

        foreach ($siteAttributes as $key => $value) {
            if ($key !== 'supportedlanguages') {
                $key = str_replace('_', '-', $key);
                self::$trans->setVariable($key , (string)$value);
            }
        }

        // for performance reasons we cache the gitTag, so, if that changes you need to remember to clear site/.#pfy-cache
        $gitTag = @file_get_contents(PFY_CACHE_PATH.'gitTag.txt');
        if ($gitTag === false) {
            $gitTag = getGitTag();
            file_put_contents(PFY_CACHE_PATH.'gitTag.txt', $gitTag);
        }
         $version = 'Kirby v'. Kirby::version(). " + PageFactory $gitTag";
        self::$trans->setVariable('generator', $version);
        $user = kirby()->user();
        if ($user) {
            $username = (string)$user->nameOrEmail();
            self::$trans->setVariable('user', $username);
        } else {
            self::$trans->setVariable('lzy-logged-in-as-user', "<a href='$appUrl/login'>Login</a>");
        }
        if (isAdmin()) {
            self::$trans->setVariable('lzy-backend-link', "<a href='$appUrl/panel' target='_blank'>Admin-Panel</a>");
        }
    } // setStandardVariables



    private function applyHeadInjections()
    {
        $sc = new Scss($this);
        $out = $sc->update();

        $css = @$this->pageParams['css'] . @$this->frontmatter['css'] . $this->css;
        $this->pageParams['css'] = $this->frontmatter['css'] = $this->css = false;

        $scss = @$this->pageParams['scss'] . @$this->frontmatter['scss']. $this->scss;
        $this->pageParams['scss'] = $this->frontmatter['scss'] = $this->scss = false;

        if ($scss) {
            $css .= $sc->compileStr($scss);
        }
        if ($css) {
            $css = indentLines($css, 8);
            $out .= <<<EOT
    <style>
$css
    </style>
EOT;
        }

        $out .= $this->getHeaderElem('description');
        $out .= $this->getHeaderElem('keywords');
        $out .= $this->getHeaderElem('author');

        self::$trans->setVariable('lzy-head-injections', $out);
        $bodyClasses = $this->bodyTagClasses? $this->bodyTagClasses: 'lzy-large-screen';
        if (self::$debug) {
            $bodyClasses = trim("debug $bodyClasses");
        }
        self::$trans->setVariable('lzy-body-classes', $bodyClasses);
        self::$trans->setVariable('lzy-body-tag-attributes', $this->bodyTagAttributes);
    } // applyHeadInjections



    private function getHeaderElem($name, $default = '')
    {
        // checks page-attrib, then site-attrib for requested keyword and returns it
        if (!$out = $this->page->$name()->value()) {
            $out = $this->site->$name()->value();
        }
        if ($out) {
            $out = <<<EOT
     <meta name="$name" content="$out">
EOT;
            return $out;
        } else {
            return $default;
        }
    } // getHeaderElem



    private function applyBodyEndInjections()
    {
        $out = "\n";
        if ($this->bodyEndInjections) {
            $out .= "$this->bodyEndInjections\n";
            $this->bodyEndInjections = false;
        }

        $js = $this->js . @$this->frontmatter['js'];
        if ($js) {
            $js = "\t\t".str_replace("\n", "\n\t\t", rtrim($js, "\n"));
            $out .= <<<EOT

    <script>
$js
    </script>

EOT;
        }

        $siteFiles = $this->pages->files();
        $modified = false;
        if ($this->jsFiles) {
            foreach ($this->jsFiles as $file) {
                $basename = basename($file);
                $targetFile = PFY_USER_ASSETS_PATH . $basename;
                if (!file_exists($file)) {
                    continue;
                }
                $tFile = filemtime($file);
                $tTargetFile = @filemtime($targetFile);
                $update = ($tTargetFile < $tFile);
                if ($update) {
                    copy($file, $targetFile);
                    $modified = true;
                }
                $jsFile = $siteFiles->find(PFY_ASSETS_PATHNAME . $basename);
                if ($jsFile) {
                    $jsCode = js($jsFile);
                    $out .= "\t$jsCode\n";
                }
            }
        }
        $this->jsFiles = false;
        if ($modified) {
            // if CSS files were updated we need to force the agent to reload,
            // otherwise, kirby misses changes in the filesystem
            reloadAgent();
        }

        $jq = $this->jq . @$this->frontmatter['jq'];
        if ($jq) {
            $jq = "\t\t\t".str_replace("\n", "\n\t\t\t", rtrim($jq, "\n"));
            $out .= <<<EOT

    <script>
        $(document).ready(function() {
$jq
        });        
    </script>

EOT;
        }
        self::$trans->setVariable('lzy-body-end-injections', $out);
    } // applyBodyEndInjections



    private function shieldLateTranslatationVariables($html)
    {
        if (preg_match_all('/{{@ (.*?) }}/xms', $html, $m)) {
            foreach ($m[1] as $i => $item) {
                $html = str_replace($m[0][$i], "{@@{{$item}}@@}", $html);
            }
        }
        return $html;
    } // shieldLateTranslatationVariables



    private function unshieldLateTranslatationVariables($html)
    {
        if (preg_match_all('/{@@{ (.*?) }@@}/xms', $html, $m)) {
            foreach ($m[1] as $i => $item) {
                $html = str_replace($m[0][$i], "{{{$item}}}", $html);
            }
        }
        return $html;
    } // unshieldLateTranslatationVariables



    private function setBodyClasses()
    {
        self::$trans->setVariable('lzy-body-classes', $this->bodyTagClasses);
    } // setBodyClasses



    private function setBodyAttributes()
    {
        self::$trans->setVariable('lzy-body-tag-attributes', $this->bodyTagAttributes);
    } // setBodyAttributes



    private function setLanguageSelector()
    {
        $out = '';
        foreach ($this->supportedLanguages as $lang) {
            $langCode = substr($lang, 0,2);
            if ($lang === self::$lang) {
                $out .= "<span class='lzy-lang-elem lzy-active-lang $langCode'>{{ lzy-lang-select $langCode }}</span>";
            } else {
                $out .= "<span class='lzy-lang-elem $langCode'><a href='?lang=$lang'>{{ lzy-lang-select $langCode }}</a></span>";
            }

        }
        if ($out) {
            $out = "\t<span class='lzy-lang-selection'>$out</span>\n";
        }

        self::$trans->setVariable('lzy-lang-selection', $out);
    } // setLanguageSelector



    private function determineLanguage(): void
    {
        $lang = false;
        $languagesObj = kirby()->languages();
        $supportedLanguages = $this->supportedLanguages = $languagesObj->codes();
        if ($supportedLanguages) {
            $lang = kirby()->language()->code();
    //ToDo: how to take user's language preference into account?
    //            $lang = $this->kirby->visitor()->acceptedLanguage()->code(); // lagunage prefered by user
    //            if (!in_array($lang, $supportedLanguages)) {
    //                $lang = (string)kirby()->defaultLanguage();
    //            }
        } else {
            $lang = 'en';
            $supportedLanguages[] = 'en';
        }

        if ($lang) {
            self::$lang = $lang;
            self::$langCode = $langCode = substr($lang, 0, 2);
            if (!in_array($langCode, $supportedLanguages)) {
                die("Error: language not defined");
            }
        } else {
            die("Error: language not defined");
        }

        // check whether user requested a language explicitly via url-arg:
        if ($lang = @$_GET['lang']) {
            if (in_array($langCode, $supportedLanguages)) {
                self::$lang = $lang;
                self::$langCode = substr($lang, 0, 2);
                kirby()->setCurrentLanguage(self::$langCode);
            }
        }
        self::$trans->setVariable('lang', self::$langCode);
    } // determineLanguage



    private function determineDebugState()
    {
        // Note: PageFactory maintains its own "debug" state, which diverges slightly from Kirby's.
        // enter debug state, if:
        // - on localhost:
        //      - true, unless $userDebugRequest or $kirbyDebugState explicitly false
        // - on productive host:
        //      - false unless
        //          - $kirbyDebugState explicitly true
        //          - logged in as admin and $userDebugRequest true -> remember as long as logged in
        $debug = false;
        $kirbyDebugState = @(kirby()->options())['debug'];
        if ($kirbyDebugState !== null) {
            $debug = $kirbyDebugState;

        } elseif (isLocalhost() || isAdmin()) {
            $debug = @$_GET['debug'];
            if ($debug === 'false') {
                $debug = false;
            } elseif (($debug === null) && $this->session->get('pfy.debug')) {
                $debug = true;
            } else {
                $debug = ($debug !== 'false');
            }
        }
        self::$debug = $debug;
        $this->session->set('pfy.debug', $debug);
    } // determineDebugState



    private function getNav()
    {
        $nav = new Nav($this);
        $out = $nav->render();
        return $out;
    }



    private function findMdFiles()
    {
        $mdFiles = $this->page->files()->filterBy('extension', 'md');
        $mdFiles = $mdFiles->filter(function($file) {
            $c1 = $file->filename()[0];
            return (($c1 !== '#') && ($c1 !== '-'));
        });
        return $mdFiles;
    } // findMdFiles



    /**
     * @param
     * @return string
     */
    private function getContent(): string
    {
        $content = '';
        if ($this->mdContent) {
            $content = self::$trans->flattenMacroCalls($this->mdContent); // need to flatten so MD doesn't get confused
            $content = $this->kirby->markdown($content);
        }
        $content = $this->content . $content;

        // remove pseudo tags <skip>:
        $content = preg_replace('|<skip.*?</skip>|ms', '', $content);

        // recover elements shielded from md-compilation:
        while (unshieldStr($content)) {};
        return $content;
    } // getContent



    private function getPfyConfig()
    {
        $this->config = loadFile('site/config/pagefactory.yaml');
        if (!$this->config) {
            $this->config = [];
        }
    } // getPfyConfig



    private function determineTemplateFile($templateFile)
    {
        if (!$templateFile) {
            if ($dir = getDir($this->page->root() . '/*.txt')) {
                $templateFile = 'site/templates/' . basename(end($dir), '.txt') . '.html';
                if (!file_exists($templateFile)) {
                    $templateFile = false;
                }
            }
        }
        $this->templateFile = $templateFile ? $templateFile : PFY_DEFAULT_TEMPLATE_FILE;
    } // determineTemplateFile



    private function setTimezone()
    {
        // check whether timezone is properly set (e.g. "UTC" is not sufficient):
        $systemTimeZone = date_default_timezone_get();
        if (!preg_match('|\w+/\w+|', $systemTimeZone)) {
            // check whether timezone is defined in PageFactory's config settings:
            $systemTimeZone = @$this->config['timezone'];
            if (!$systemTimeZone) {
                $systemTimeZone = $this->getServerTimezone();
                appendFile(PFY_CONFIG_FILE, "\n# Autmatically set by PageFactory:\ntimezone: $systemTimeZone\n\n",
                    "# Configuration file for PageFactory plugin\n\n");
            }
            \Kirby\Toolkit\Locale::set($systemTimeZone);
        }
        return $systemTimeZone;
    } // setTimezone



    private function getServerTimezone()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://ipapi.co/timezone");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    } // getServerTimezone


} // PageFactory
