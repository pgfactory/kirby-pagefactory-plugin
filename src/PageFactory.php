<?php

namespace Usility\PageFactory;

use Kirby;
use Kirby\Data\Yaml as Yaml;
use Kirby\Cms\Language;

define('PFY_PLUGIN_PATH',           'site/plugins/pagefactory/');
define('PFY_DEFAULT_TEMPLATE_FILE', 'site/templates/page_template.html');
define('PFY_USER_ASSETS_PATH',      'content/assets/');
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
        //ToDo: How to start session the Kirby way?
        session_start();

        $this->determineDebugState();

        $this->kirby = kirby();
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

        // potentially useful info:
        $this->visitor = $this->kirby->visitor();

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
        $this->templateFile = $templateFile? $templateFile : PFY_DEFAULT_TEMPLATE_FILE;

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
                    self::$trans->addVariable($varName, $value);
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

        self::$trans->addVariable('content', $content);

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
        self::$trans->addVariable('nav', $this->getNav());

        $this->setBodyClasses();
        $this->setBodyAttributes();
        $this->setLanguageSelector();

        $appUrl = site()->url();
        self::$trans->addVariable('lzy-backend-link', "<a href='$appUrl/panel' target='_blank'>Admin</a>");
        $siteTitle = site()->title()->value();
        self::$trans->addVariable('lzy-site-title', $siteTitle);
        self::$trans->addVariable('lzy-page-title', $this->page->title() . ' / ' . $siteTitle);
        $siteAttributes = site()->content()->data();

        foreach ($siteAttributes as $key => $value) {
            if ($key !== 'supportedlanguages') {
                $key = str_replace('_', '-', $key);
                self::$trans->addVariable($key , (string)$value);
            }
        }

        //        $gitTag = @file_get_contents(PFY_CACHE_PATH.'gitTag.txt');
        //        if (!$gitTag) {
        //            $gitTag = getGitTag();
        //            file_put_contents(PFY_CACHE_PATH.'gitTag.txt', $gitTag);
        //        }
        $version = 'Kirby v'. Kirby::version(). ' + PageFactory ';
        // $version = 'Kirby v'. Kirby::version(). " + PageFactory $gitTag"; //ToDo: activate when checked in
        self::$trans->addVariable('generator', $version);
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

        self::$trans->addVariable('lzy-head-injections', $out);
        $bodyClasses = $this->bodyTagClasses? $this->bodyTagClasses: 'lzy-large-screen';
        self::$trans->addVariable('lzy-body-classes', $bodyClasses);
        self::$trans->addVariable('lzy-body-tag-attributes', $this->bodyTagAttributes);
    } // applyHeadInjections



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
        self::$trans->addVariable('lzy-body-end-injections', $out);
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
        self::$trans->addVariable('lzy-body-classes', $this->bodyTagClasses);
    } // setBodyClasses



    private function setBodyAttributes()
    {
        self::$trans->addVariable('lzy-body-tag-attributes', $this->bodyTagAttributes);
    } // setBodyAttributes



    private function setLanguageSelector()
    {
//self::$trans->addVariable('lzy-lang-selection', 'TBD');
//return;
        $out = '';
//        $languages = explode(',', $this->supportedLanguages);
//        if ($languages) {
            foreach ($this->supportedLanguages as $lang) {
                $langCode = substr($lang, 0,2);
                if ($lang === self::$lang) {
                    $out .= "<span class='lzy-lang-elem lzy-active-lang $langCode'>{{ lzy-lang-select $langCode }}</span>";
                } else {
                    $out .= "<span class='lzy-lang-elem $langCode'><a href='?lang=$lang'>{{ lzy-lang-select $langCode }}</a></span>";
                }

            }
            $out = "\t<div class='lzy-lang-selection'>$out</div>\n";
//        }

        self::$trans->addVariable('lzy-lang-selection', $out);
    } // setLanguageSelector



    private function determineLanguage(): void
    {
        $languagesObj = kirby()->languages();
        $supportedLanguages = $this->supportedLanguages = $languagesObj->codes();
        if ($supportedLanguages) {
            if (!$lang = kirby()->language()->code()) {
                $lang = kirby()->defaultLanguage();
            }
        } else {
            $lang = 'en';
            $supportedLanguages[] = 'en';
        }

        if ($lang) {
            self::$lang = $lang;
            self::$langCode = $langCode = substr($lang, 0, 2);
            if (!in_array($langCode, $supportedLanguages)) {
                die("Error: language not defined (see)");
            }
        } else {
            die("Error: language not defined (see)");
        }
        if ($lang = @$_GET['lang']) {
            if (in_array($langCode, $supportedLanguages)) {
                self::$lang = $lang;
                self::$langCode = substr($lang, 0, 2);
                kirby()->setCurrentLanguage(self::$langCode);
            }
        }
        self::$trans->addVariable('lang', self::$langCode);
    } // determineLanguage



    private function determineDebugState()
    {
        self::$debug = @$_GET['debug'];
        if (self::$debug === 'false') {
            self::$debug = false;
        } elseif ((self::$debug === null) && @$_SESSION['pfy']['debug']) {
            self::$debug = true;
        } else {
            self::$debug = (self::$debug !== 'false');
        }
        $_SESSION['pfy']['debug'] = self::$debug;
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
            $content = self::$trans->flattenMacroCalls($this->mdContent);
            $content = $this->kirby->markdown($content);
        }
        $content = $this->content . $content;

        // remove pseudo tags <skip>:
        $content = preg_replace('|<skip.*?</skip>|ms', '', $content);

        // recover elements shielded from md-compilation:
        while (unshieldStr($content)) {};
        return $content;
    } // getContent



    private function setTimezone()
    {
        // first try Session variable:
        $systemTimeZone = @$_SESSION['pfy']['systemTimeZone'];

        // if not defined, try to read it from config.yaml:
        if (!$systemTimeZone) {
            $config = file_get_contents('content/site.txt');
            $config1 = zapFileEND($config);
            $config1 = removeHashTypeComments($config1);
            if (preg_match('|site_timeZone:\s*([\w/]*)|ms', $config1, $m)) {
                $systemTimeZone = $m[1];
            }
        }
        // if not defined yet, try to obtain it automatically:
        if (!$systemTimeZone) {
            $systemTimeZone = $this->getServerTimezone();
            $config = "\n\n----\nsite_timeZone: $systemTimeZone     # autmatically set to timezone of webhost\n";
            file_put_contents('content/site.txt', $config, FILE_APPEND);
        }
        if (!$systemTimeZone) {
            die("Error: 'site_timeZone' entry missing in config/config.yaml");
        }

        // activate timezone:
        \Kirby\Toolkit\Locale::set($systemTimeZone);
        $_SESSION['pfy']['systemTimeZone'] = $systemTimeZone;

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
