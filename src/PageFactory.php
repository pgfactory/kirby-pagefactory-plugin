<?php

namespace Usility\PageFactory;

use Kirby;

define('PFY_PLUGIN_PATH',           'site/plugins/pagefactory/');
define('PFY_DEFAULT_TEMPLATE_FILE', 'site/templates/page_template.html');
define('PFY_USER_ASSETS_PATH',      'content/assets/');
define('SVG_ICONS_PATH',            'site/plugins/pagefactory/install/media/pagefactory/svg-icons/');
define('PFY_CONFIG_FILE',           'site/config/pagefactory.php');
define('PFY_USER_CODE_PATH',        'site/custom/');
define('PFY_MACROS_PATH',           PFY_PLUGIN_PATH.'src/macros/');
define('PFY_MACROS_PLUGIN_PATH',    'site/plugins/pagefactory-extensions/macros/');
define('PFY_CSS_PATH',              'assets/');
define('PFY_ASSETS_PATHNAME',       'assets/');
define('PFY_LOGS_PATH',             'site/logs/');
define('PFY_CACHE_PATH',            PFY_PLUGIN_PATH.'.#cache/');
define('PFY_MKDIR_MASK',             0700); // permissions for file accesses by PageFactory
define('PFY_DEFAULT_TRANSVARS',     'site/config/transvars.yaml');
define('JQUERY',                    PFY_PLUGIN_PATH.'/third_party/jquery/jquery-3.6.0.min.js');


require_once __DIR__ . '/../third_party/vendor/autoload.php';
require_once __DIR__ . '/helper.php';


class PageFactory
{
    public static $appRoot = null;
    public static $absAppRoot = null;
    public static $pagePath = null;
    public static $pageRoot = null;
    public static $absPageRoot = null;
    public static $lang = null;
    public static $langCode = null;
    public static $trans = null;
    public static $siteOptions = null;
    public static $debug = null;

    public $config;
    public $mdContent = '';
    public $css = '';
    public $scss = '';
    public $js = '';
    public $jq = '';
    public $frontmatter = [];
    public $assetFiles = [];
    public $requestedAssetFiles = [];
    public $cssFiles = [];
    public $jQueryActive = false;
    public $noTranslate = false;

    public $headInjections = '';
    public $bodyTagClasses = '';
    public $bodyTagAttributes = '';
    public $bodyEndInjections = '';

    public $loadHelperJs = false;

    public $screenSizeBreakpoint = 480;


    public function __construct($pages)
    {
        $this->kirby = kirby();
        $this->session = $this->kirby->session();

        $this->page = page();
        $this->slug = $this->kirby->path();
        if (preg_match('|^(.*?) / ([A-Z]{5,15})$|x', $this->slug, $m)) {
            $this->slug = $m[1];
            $this->urlToken = $m[2];
        }
        $this->pages = $pages;
        $this->site = site();
        self::$siteOptions = $this->kirby->options(); // from site/config/config.php
        $this->pageOptions = $this->page->content()->data();


        self::$trans = new TransVars($this);

        $this->md = new MarkdownPlus();
        $this->pg = new PageExtruder($this);
        $this->pg->set('pageParams', $this->page->content()->data());

        $this->utils = new Utils($this);
        $this->utils->loadPfyConfig();
        $this->utils->determineDebugState();

        $this->utils->setTimezone();

        $this->utils->determineLanguage();

        $this->content = (string)$this->page->text()->kt();
        self::$pagePath = substr($this->page->root(), strlen(site()->root())+1) . '/';
        self::$absAppRoot = dirname($_SERVER['SCRIPT_FILENAME']).'/';
        self::$appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$pageRoot = 'content/' . self::$pagePath;
        self::$absPageRoot = $this->page->root() . '/';

        $this->siteTitle = (string)site()->title()->value();

        // get asset-files definition: first try site/config/pagefactory.php:
        if (@$this->config['assetFiles']) {
            $this->assetFiles = $this->config['assetFiles'];
        } else { // if not found, use following as default values:
            $this->assetFiles = [
                '-pagefactory.css' => [
                    'site/plugins/pagefactory/scss/autoload/*',
                ],
                '-pagefactory-async.css' => [
                    'site/plugins/pagefactory/scss/autoload-async/*',
                ],
                '-styles.css' => [
                    PFY_USER_ASSETS_PATH . 'autoload/*',
                ],
                '-styles-async.css' => [
                    PFY_USER_ASSETS_PATH . 'autoload-async/*',
                ],

                '-pagefactory.js' => [
                    'site/plugins/pagefactory/js/autoload/*',
                ],

                // prepare rest as individual files ready for explicit queueing/loading:
                '*' => [
                    'site/plugins/pagefactory/scss/*',
                    'site/plugins/pagefactory/third_party/jquery/jquery-3.6.0.min.js',
                    'site/plugins/pagefactory/js/*',
                ],
            ];
        }

        if (isAdminOrLocalhost() && isset($_GET['notranslate'])) {
            $notranslate = $_GET['notranslate'];
            $this->noTranslate = $notranslate? intval($notranslate): 1;
        }

    } // __construct


    
    /**
     * renders the final HTML
     * @param false $templateFile
     * @return string
     */
    public function render($templateFile = false): string
    {
        $this->utils->determineTemplateFile($templateFile);

        $this->utils->loadMdFiles();
        $this->setStandardVariables();

        $html = $this->assembleHtml();
        $html = str_replace('~/', '', $html);
        return $html;
    } // render



    /**
     * Loads the template, obtains the page content und keeps translating variables till none are left.
     * Then in the last step 'late-translation-variables' are translated. They are the ones that define injections
     * into the <head> and the bottom of <body>. They include CSS and load CSS- and JS files. 
     * @return string
     */
    private function assembleHtml(): string
    {
        $html = loadFile($this->templateFile);
        $html = $this->utils->shieldLateTranslatationVariables($html); // {{@ ...}}

        // 'content' (everything that's defined by the page):
        $content = $this->utils->getContent(); // content of all .md files in page folder
        self::$trans->setVariable('content', $content);

        // repeat until no more variables appear in html:
        $depth = 1;
        while (preg_match('/(?<!\\\){{/', $html)) {
            $html = self::$trans->translate($html, $depth++);
            unshieldStr($html);
        }

        // last pass: now replace injection variables:
        $html = $this->utils->unshieldLateTranslatationVariables($html);

        $this->pg->preparePageVariables();

        $html = self::$trans->translate($html);
        unshieldStr($html);

        // remove shields in specific cases:
        $html = preg_replace("/\\\BR/ms", "BR", $html);
        return $html;
    } // assembleHtml



    /**
     * Defines standard variables used in most webpages, e.g. 'lang' and 'page-title' etc.
     * @throws Kirby\Exception\InvalidArgumentException
     */
    private function setStandardVariables(): void
    {
        self::$trans->setVariable('lang', self::$langCode);
        self::$trans->setVariable('lzy-body-tag-attributes', $this->bodyTagAttributes);

        $this->utils->setLanguageSelector();

        // Copy site field values to transvars:
        $siteAttributes = site()->content()->data();
        foreach ($siteAttributes as $key => $value) {
            if ($key === 'title') {
                $key = 'site-title';
            }
            $key = str_replace('_', '-', $key);
            self::$trans->setVariable($key , (string)$value);
        }

        // Copy page field values to transvars:
        $pageAttributes = page()->content()->data();
        foreach ($pageAttributes as $key => $value) {
            if ($key === 'title') {
                $key = 'page-title';
            }
            $key = str_replace('_', '-', $key);
            self::$trans->setVariable($key , (string)$value);
        }

        // 'generator':
        // for performance reasons we cache the gitTag, so, if that changes you need to remember to clear site/.#pfy-cache
        $gitTag = @file_get_contents(PFY_CACHE_PATH.'gitTag.txt');
        if ($gitTag === false) {
            $gitTag = getGitTag();
            file_put_contents(PFY_CACHE_PATH.'gitTag.txt', $gitTag);
        }
        $version = 'Kirby v'. Kirby::version(). " + PageFactory $gitTag";
        self::$trans->setVariable('generator', $version);

        // 'user', 'lzy-logged-in-as-user', 'lzy-backend-link':
        $appUrl = self::$appRoot;
        $loginIcon = svg('site/plugins/pagefactory/assets/user.svg');
        $user = kirby()->user();
        if ($user) {
            $username = (string)$user->nameOrEmail();
            self::$trans->setVariable('user', $username);
            self::$trans->setVariable('lzy-login-button', "<button class='lzy-login-button' title='{{ lzy-edit-user-account }}'>$loginIcon</span></button>");
        } else {
            self::$trans->setVariable('lzy-logged-in-as-user', "<a href='{$appUrl}login'>Login</a>");
            self::$trans->setVariable('lzy-login-button', "<button class='lzy-login-button' title='{{ lzy-login-button-label }}'>$loginIcon</button>");
        }
        self::$trans->setVariable('lzy-backend-link', "<a href='{$appUrl}panel' target='_blank'>{{ lzy-admin-panel-link-text }}</a>");
    } // setStandardVariables

} // PageFactory
