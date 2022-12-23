<?php

namespace Usility\PageFactory;

use Kirby;

 // filesystem paths:
const PFY_BASE_PATH =              'site/plugins/pagefactory/';
const PFY_DEFAULT_TEMPLATE_FILE =  'site/templates/page_template.html';
const PFY_CONTENT_ASSETS_PATH =    'content/assets/';
const PFY_ASSETS_PATH =            'site/plugins/pagefactory/assets/';
const PFY_ICONS_PATH =             'site/plugins/pagefactory/assets/icons/';
const PFY_SVG_ICONS_PATH =         'site/plugins/pagefactory/assets/svg-icons/';
const PFY_CONFIG_FILE =            'site/config/config.php';
const PFY_CUSTOM_PATH =            'site/custom/';
const PFY_USER_CODE_PATH =         PFY_CUSTOM_PATH.'macros/';
const PFY_MACROS_PATH =            PFY_BASE_PATH.'macros/';
define('PFY_LOGS_PATH',           'site/logs/');
define('PFY_CACHE_PATH',           PFY_CUSTOM_PATH.'.#cache/'); // available in extensions
const PFY_MKDIR_MASK =             0700; // permissions for file accesses by PageFactory
const PFY_DEFAULT_TRANSVARS =      PFY_BASE_PATH.'variables/pagefactory.yaml';

 // URLs:
const PFY_BASE_ASSETS_URL =        'media/plugins/usility/';
const PFY_ASSETS_URL =             PFY_BASE_ASSETS_URL.'pagefactory/';
const PAGED_POLYFILL_SCRIPT_URL =  PFY_ASSETS_URL.'js/paged.polyfill.min.js';

const DEFAULT_FRONTEND_FRAMEWORK_URLS = ['js' => PFY_ASSETS_URL.'js/jquery-3.6.1.min.js'];

 // use this name for meta-files (aka text-files) in page folders:
define('PFY_PAGE_DEF_BASENAME',     'z_pfy'); // 'define' required by site/plugins/pagefactory/index.php

define('OPTIONS_DEFAULTS', [
    'handleKirbyFrontmatter'        => false,
    'screenSizeBreakpoint'          => 480,
    'defaultLanguage'               => 'en',
    'allowNonPfyPages'              => false,  // -> if true, Pagefactory will skip checks for presence of metafiles
    'externalLinksToNewWindow'      => true,   // -> used by Link() -> whether to open external links in new window
    'imageAutoQuickview'            => true,  // -> used by Img() macro
    'imageAutoSrcset'               => true,  // -> used by Img() macro
    'divblock-chars'                => '@%',  // possible alternative: ':$@%'
    // 'timezone' => 'Europe/Zurich', // PageFactory tries to guess the timezone - you can override this manually

    'variables' => [
        'pfy-page-title' => '{{ kirby-page-title }} / {{ kirby-site-title }}',
        'webmaster-email' => 'webmaster@'.preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url()),
        'pfy-menu-icon' => svg('site/plugins/pagefactory/assets/icons/menu.svg'),
        'pfy-small-screen-header' => <<<EOT
        <h1>{{ kirby-site-title }}</h1>
        <button id="pfy-nav-menu-icon">{{ pfy-menu-icon }}</button>
EOT,

        'pfy-footer' => ' Footer...',
    ],

    // optionally define files to be used as css/js framework (e.g. jQuery or bootstrap etc):
    //    'frontendFrameworkUrls' => [
    //        'css' => 'assets/framework1.css, assets/framework12.css',
    //        'js' => 'assets/framework1.js',
    //    ],


    // Options for dev phase:
    'debug_compileScssWithLineNumbers'  => true,   // line numbers of original SCSS file
]);



require_once __DIR__ . '/helper.php';


class PageFactory
{
    public static $pages;
    public static $siteFiles;
    public static $appRoot;
    public static $appRootUrl;
    public static $appUrl;
    public static $absAppRoot;
    public static $absPfyRoot;
    public static $pagePath;
    public static $pageRoot;
    public static $absPageRoot;
    public static $hostUrl;
    public static $absPageUrl;
    public static $pageUrl;
    public static $lang;
    public static $langCode;
    public static $defaultLanguage;
    public static $trans;
    public static $pg;
    public static $md;
    public static $availableExtensions = [];
    public static $loadedExtensions = [];
    public static $debug;
    public static $isLocalhost;
    public static $timer;
    public static $user;
    public static $slug;
    public static $pageId;
    public static $urlToken; // the hash code extracted from HTTP request (e.g. home/ABCDEF)
    public static $availableIcons;
    public static $phpSessionId;
    public static $assets;
    public static $config;

    public $templateFile = '';
    public $session;

    /**
     * @param $pages
     * @throws Kirby\Exception\InvalidArgumentException
     */
    public function __construct($pages)
    {
        self::$timer = microtime(true);
        self::$isLocalhost = isLocalhost();
        self::$pages = $pages;
        self::$siteFiles = $pages->files();
        $this->kirby = kirby();
        $this->session = $this->kirby->session();
        self::$phpSessionId = getSessionId();

        $this->page = page();
        $this->site = site();
        $this->pageOptions = $this->page->content()->data();

        // find available icons:
        self::$availableIcons = findAvailableIcons();

        $extensions = getDir(rtrim(PFY_BASE_PATH, '/').'-*');
        foreach ($extensions as $extension) {
            $extensionName = rtrim(substr($extension, 25), '/');
            self::$availableExtensions[$extensionName] = $extension;
        }

        self::$hostUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/';

        $this->utils = new Utils($this);
        $this->utils->loadPfyConfig();
        self::$trans = new TransVars($this);

        self::$assets = new Assets($this);
        self::$md = new MarkdownPlus($this);
        self::$pg = new Page($this);
        self::$pg->set('pageParams', $this->page->content()->data());

        $this->utils->init();
        self::$trans->loadCustomVariables(); // overrides other sources of variable definitions
        $this->utils->determineDebugState();

        $this->utils->setTimezone();

        $this->utils->determineLanguage();

        self::$pagePath = substr($this->page->root(), strlen(site()->root())+1) . '/';
        self::$absAppRoot = kirby()->root().'/';
        self::$absPfyRoot = __DIR__.'/';
        self::$appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appUrl = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appRootUrl = kirby()->url().'/';
        self::$slug = page()->slug();
        self::$pageId = page()->id();
        self::$pageRoot = 'content/' . self::$pagePath;
        self::$absPageRoot = $this->page->root() . '/';
        self::$absPageUrl = (string)$this->page->url() . '/';
        self::$pageUrl = substr(self::$absPageUrl, strlen(self::$hostUrl)-1);
        if ($user = $this->kirby->user()) {
            self::$user = (string)$user->name();
        }

        $this->utils->handleUrlToken();

        $this->siteTitle = (string)site()->title()->value();

        preparePath(PFY_LOGS_PATH);
    } // __construct


    /**
     * Destructor: invokes DataSet::unlockDatasources
     */
    public function __destruct()
    {
        $dataCachePath = self::$absAppRoot.PFY_CACHE_PATH.'data/';
        $sessionId = PageFactory::$phpSessionId;
        $lockFiles = getDir("$dataCachePath*.lock");
        foreach ($lockFiles as $lockFile) {
            $sid = fileGetContents($lockFile);
            if ($sid === $sessionId) {
                @unlink($lockFile);
            }
        }
    } // __destruct


    /**
     * renders the final HTML
     * @param false $options
     * @return string
     */
    public function render($options = false): string
    {
        // check for presence of site/plugins/pagefactory-*':
        self::$pg->loadExtensions();

        if (self::$assets->prepareAssets()) {
            reloadAgent();
        }

        // show message, if one is pending:
        $this->showPendingMessage();

        if ($options['mdVariant']??false) {
            MarkdownPlus::$mdVariant = $options['mdVariant'];
        }
        $this->utils->handleAgentRequests(); // login,logout,printpreview,print' and 'help,localhost,timer,reset,notranslate'

        $this->utils->determineTemplateFile($options['templateFile']??'');

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
        $html = loadFile($this->templateFile, false);
        $this->checkDefaultStylingActive($html);

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

        self::$pg->extensionsFinalCode(); // -> invokes plugin/xy/src/_finalCode.php

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
        self::$trans->setVariable('lang-active', self::$lang); // can be lang-variant, e.g. de2
        self::$trans->setVariable('php-version', phpversion());

        $this->utils->setLanguageSelector();

        // Copy site field values to transvars:
        $siteAttributes = site()->content()->data();
        foreach ($siteAttributes as $key => $value) {
            if ($key === 'title') {
                $key = 'kirby-site-title';
            }
            $key = str_replace('_', '-', $key);
            self::$trans->setVariable($key , (string)$value);
        }

        // Copy page field values to transvars:
        $pageAttributes = page()->content()->data();
        foreach ($pageAttributes as $key => $value) {
            if ($key === 'title') {
                $key = 'kirby-page-title';
            }
            $key = str_replace('_', '-', $key);
            self::$trans->setVariable($key , (string)$value);
        }

        // 'generator':
        // for performance reasons we cache the gitTag, so, if that changes you need to remember to clear site/.#pfy-cache
        $gitTag = fileGetContents(PFY_CACHE_PATH.'gitTag.txt');
        if (!$gitTag) {
            $gitTag = getGitTag();
            file_put_contents(PFY_CACHE_PATH.'gitTag.txt', $gitTag);
        }
        $version = 'Kirby v'. Kirby::version(). " + PageFactory $gitTag";
        self::$trans->setVariable('generator', $version);

        // 'user', 'pfy-logged-in-as-user', 'pfy-admin-panel-link':
        $appUrl = self::$appUrl;
        $loginIcon = svg('site/plugins/pagefactory/assets/icons/user.svg');
        $user = kirby()->user();
        if ($user) {
            $username = (string)$user->nameOrEmail();
            self::$trans->setVariable('user', $username);
            self::$trans->setVariable('pfy-login-button', "<button class='pfy-login-button' title='{{ pfy-edit-user-account }}'>$loginIcon</span></button>");
        } else {
            self::$trans->setVariable('pfy-logged-in-as-user', "<a href='{$appUrl}login'>Login</a>");
            self::$trans->setVariable('pfy-login-button', "<button class='pfy-login-button' title='{{ pfy-login-button-label }}'>$loginIcon</button>");
        }
        self::$trans->setVariable('pfy-admin-panel-link', "<a href='{$appUrl}panel' target='_blank'>{{ pfy-admin-panel-link-text }}</a>");
    } // setStandardVariables



    /**
     * @return void
     */
    private function determineAssetFilesList(): void
    {
        // get asset-files definition:
        if (self::$config['assetFiles']??false) {
            $this->assetFiles = self::$config['assetFiles'];
        } else { // if not found, use following as default values:
            $this->assetFiles = [
                '-pagefactory.css' => [
                    'site/plugins/pagefactory/scss/autoload/*',
                ],
                '-pagefactory-async.css' => [
                    'site/plugins/pagefactory/scss/autoload-async/*',
                ],
                '-styles.css' => [
                    'content/assets/autoload/*',
                ],
                '-styles-async.css' => [
                    'content/assets/autoload-async/*',
                ],

                '-pagefactory.js' => [
                    'site/plugins/pagefactory/assets/js/autoload/*',
                ],

                // prepare rest as individual files ready for explicit queueing/loading:
                '*' => [
                    'site/plugins/pagefactory/scss/*',
                ],
            ];
        }
    } // determineAssetFilesList


    /**
     * reloadAgent() can prepare a message to be shown on next page view, here we show the message:
     * @return void
     */
    private function showPendingMessage(): void
    {
        if ($msg = $this->session->get('pfy.message')) {
            self::$pg->setMessage($msg);
            $this->session->remove('pfy.message');
        }
    } // showPendingMessage


    /**
     * Checks whether template contains 'pfy-default-styling', i.e. uses default styling. If not, removes
     * those entries from the asset queue
     * @param mixed $html
     * @return void
     */
    private function checkDefaultStylingActive(mixed $html): void
    {
        $this->useDefaultStyling = str_contains($html, 'pfy-default-styling');
        if (!$this->useDefaultStyling) {
            self::$assets->excludeSystemAssets();
        }
    } // checkDefaultStylingActive

} // PageFactory
