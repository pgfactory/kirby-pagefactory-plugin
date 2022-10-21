<?php

namespace Usility\PageFactory;

use Kirby;

define('PFY_BASE_PATH',             'site/plugins/pagefactory/');
define('PFY_DEFAULT_TEMPLATE_FILE', 'site/templates/page_template.html');
define('PFY_USER_ASSETS_PATH',      'content/assets/');
define('CUSTOM_ICONS_PATH',         'assets/pagefactory/icons/');
define('PFY_ICONS_PATH',            'site/plugins/pagefactory/assets/icons/');
define('PFY_PUB_ICONS_PATH',        'site/plugins/pagefactory/assets/pub-icons/');
define('PFY_CONFIG_FILE',           'site/config/pagefactory.php');
define('PFY_CUSTOM_PATH',           'site/custom/');
define('PFY_USER_CODE_PATH',        PFY_CUSTOM_PATH.'macros/');
define('PFY_MACROS_PATH',           PFY_BASE_PATH.'macros/');
define('PFY_CSS_PATH',              'assets/');
define('PFY_ASSETS_PATHNAME',        'assets/pagefactory/');
define('PFY_LOGS_PATH',             'site/logs/');
define('PFY_CACHE_PATH',            PFY_BASE_PATH.'.#cache/');
define('PFY_MKDIR_MASK',             0700); // permissions for file accesses by PageFactory
define('PFY_DEFAULT_TRANSVARS',     PFY_BASE_PATH.'variables/pagefactory.yaml');
define('JQUERY',                    PFY_BASE_PATH.'third_party/jquery/jquery-3.6.0.min.js');
define('PFY_PAGE_DEF_BASENAME',     'zzz_page'); // use this name for meta-files (aka text-files) in page folders


require_once __DIR__ . '/../third_party/vendor/autoload.php';
require_once __DIR__ . '/helper.php';


class PageFactory
{
    public static $pages = null;
    public static $siteFiles;
    public static $appRoot = null;
    public static $appRootUrl = null;
    public static $appUrl = null;
    public static $absAppRoot = null;
    public static $pagePath = null;
    public static $pageRoot = null;
    public static $absPageRoot = null;
    public static $hostUrl = null;
    public static $absPageUrl = null;
    public static $pageUrl = null;
    public static $lang = null;
    public static $langCode = null;
    public static $trans = null;
    public static $pg = null;
    public static $md = null;
    public static $siteOptions = null;
    public static $availableExtensions = [];
    public static $loadedExtensions = [];
    public static $debug = null;
    public static $timer = null;
    public static $user = null;
    public static $slug = null;
    public static $urlToken = null;
    public static $availableIcons = null;

    public $config;
    public $templateFile = '';
    public $session = null;
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

    public function __construct($pages)
    {
        self::$timer = microtime(true);
        self::$pages = $pages;
        self::$siteFiles = $pages->files();
        $this->kirby = kirby();
        $this->session = $this->kirby->session();

        $this->page = page();
        $this->site = site();
        self::$siteOptions = $this->kirby->options(); // from site/config/config.php
        unset(self::$siteOptions['hooks']);
        $this->pageOptions = $this->page->content()->data();

        // find available icons:
        self::$availableIcons = findAvailableIcons();

        $extensions = getDir(rtrim(PFY_BASE_PATH, '/').'-*');
        foreach ($extensions as $extension) {
            $extensionName = rtrim(substr($extension, 25), '/');
            self::$availableExtensions[$extensionName] = $extension;
        }

        self::$trans = new TransVars($this);

        self::$md = new MarkdownPlus($this);
        self::$pg = new PageExtruder($this);
        self::$pg->set('pageParams', $this->page->content()->data());

        $this->utils = new Utils($this);
        $this->utils->loadPfyConfig();
        $this->utils->determineDebugState();

        $this->utils->setTimezone();

        $this->utils->determineLanguage();

        $this->content = (string)$this->page->text()->kt();
        self::$pagePath = substr($this->page->root(), strlen(site()->root())+1) . '/';
        self::$absAppRoot = dirname($_SERVER['SCRIPT_FILENAME']).'/';
        self::$hostUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/';
        self::$appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appUrl = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appRootUrl = kirby()->url().'/';
        self::$pageRoot = 'content/' . self::$pagePath;
        self::$absPageRoot = $this->page->root() . '/';
        self::$absPageUrl = (string)$this->page->url() . '/';
        self::$pageUrl = substr(self::$absPageUrl, strlen(self::$hostUrl)-1);
        if ($user = $this->kirby->user()) {
            self::$user = (string)$user->name();
        }

        $this->utils->handleUrlToken();

        $this->siteTitle = (string)site()->title()->value();
        $this->determineAssetFilesList();

        preparePath(PFY_LOGS_PATH);
    } // __construct


    
    /**
     * renders the final HTML
     * @param false $templateFile
     * @return string
     */
    public function render($options = false): string
    {
        // check for presence of site/plugins/pagefactory-*':
        self::$pg->loadExtensions();

        if (@$options['mdVariant']) {
            MarkdownPlus::$mdVariant = $options['mdVariant'];
        }
        $this->utils->handleAgentRequests(); // login,logout,printpreview,print' and 'help,localhost,timer,reset,notranslate'

        $this->utils->determineTemplateFile(@$options['templateFile']);

        $this->utils->loadMdFiles();
        $this->setStandardVariables();

        $html = $this->assembleHtml();

        // report execution time (see result in browser/dev-tools/network -> main file):
        header("Server-Timing: total;dur=" . readTimer());
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
            $html = unshieldStr($html);
        }

        $this->utils->handleAgentRequestsOnRenderedPage(); // list variables, macros

        // last pass: now replace injection variables:
        $html = $this->utils->unshieldLateTranslatationVariables($html);

        self::$pg->preparePageVariables();

        $html = self::$trans->translate($html);
        $html = $this->utils->resolveUrls($html);
        $html = unshieldStr($html, true);

        return $html;
    } // assembleHtml



    /**
     * Defines standard variables used in most webpages, e.g. 'lang' and 'page-title' etc.
     * @throws Kirby\Exception\InvalidArgumentException
     */
    private function setStandardVariables(): void
    {
        self::$trans->setVariable('page-url', self::$pageUrl);
        self::$trans->setVariable('lang', self::$langCode);
        self::$trans->setVariable('lang-active', self::$lang);
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
        $appUrl = self::$appUrl;
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



    public static function instance()
    {
        return static::$instance ?? new static(pages());
    }

    /**
     * @return void
     */
    private function determineAssetFilesList(): void
    {
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
    }

} // PageFactory
