<?php

namespace PgFactory\PageFactory;

use Kirby;
use Kirby\Data\Yaml;
use ScssPhp\ScssPhp\Exception\SassException;
use PgFactory\MarkdownPlus\Permission;

 // filesystem paths:
const PFY_BASE_PATH =              'site/plugins/pagefactory/';
const PFY_CONTENT_ASSETS_PATH =    'content/assets/';
const PFY_ASSETS_PATH =            'site/plugins/pagefactory/assets/';
const PFY_ICONS_PATH =             'site/plugins/pagefactory/assets/icons/';
const PFY_SVG_ICONS_PATH =         'site/plugins/markdownplus/assets/svg-icons/';
const PFY_CONFIG_FILE =            'site/config/config.php';
const PFY_CUSTOM_PATH =            'site/custom/';
const PFY_USER_CODE_PATH =         PFY_CUSTOM_PATH.'macros/';
const PFY_CUSTOM_CODE_PATH =       PFY_CUSTOM_PATH.'autoexecute/';
const PFY_CUSTOM_DATA_PATH =       PFY_CUSTOM_PATH.'data/';
const PFY_MACROS_PATH =            PFY_BASE_PATH.'macros/';
define('PFY_LOGS_PATH',            'site/logs/');
if (!defined('PFY_CACHE_PATH')) { // available in extensions
    define('PFY_CACHE_PATH', 'site/cache/pagefactory/'); // available in extensions
}
define('LOGIN_LOG_FILE',           'login-log.txt'); // available in extensions
define('DOWNLOAD_PATH',            'download/');
define('TEMP_DOWNLOAD_PATH',       DOWNLOAD_PATH.'temp/'); // for temp download of datasets (excel-format)

define('PFY_WEBMASTER_EMAIL_CACHE',  PFY_CACHE_PATH.'webmaster-email.txt');

const PFY_MKDIR_MASK =             0700; // permissions for file accesses by PageFactory
const BLOCK_SHIELD =               'div shielded';
const INLINE_SHIELD =              'span shielded';
const MD_SHIELD =                  'span mdshielded';

 // URLs:
const PFY_BASE_ASSETS_URL =        'media/plugins/pgfactory/';
const PFY_ASSETS_URL =             PFY_BASE_ASSETS_URL.'pagefactory/';
const PAGED_POLYFILL_SCRIPT_URL =  PFY_ASSETS_URL.'js/paged.polyfill.min.js';

const DEFAULT_FRONTEND_FRAMEWORK_URLS = ['js' => PFY_ASSETS_URL.'js/jquery-3.7.1.min.js'];

 // use this name for meta-files (aka text-files) in page folders:
define('PFY_PAGE_META_FILE_BASENAME','z'); // 'define' required by site/plugins/pagefactory/index.php

define('OPTIONS_DEFAULTS', [
    'defaultLanguage'               => 'en',  // default language used, if none is available from Kirby
    'default-nav'                   => true,  // automatically loads NAV assets
    'externalLinksToNewWindow'      => true,  // -> used by Link() -> whether to open external links in new window
    'imageAutoQuickview'            => true,  // -> default for Img() macro
    'imageAutoSrcset'               => true,  // -> default for Img() macro
    'includeMetaFileContent'        => true,  // -> option for website using '(include: *.md)' in metafile
                                              // e.g. when converting from MdP site to Pfy
    'screenSizeBreakpoint'          => 480,   // Value used by JS to switch body classes ('pfy-large-screen' and 'pfy-small-screen')
    'sourceWrapperTag'              => 'section', // tag used to wrap .md content
    'sourceWrapperClass'            => '',    // class applied to sourceWrapperTag
    'webmaster_email'               => '',    // email address of webmaster
    'maxCacheAge'                   => 86400, // [s] max time after which Kirby's file cache is automatically flushed
    // 'timezone' => 'Europe/Zurich', // PageFactory tries to guess the timezone - you can override this manually

    // optionally define files to be used as css/js framework (e.g. jQuery or bootstrap etc):
    //    'frontendFrameworkUrls' => [
    //        'css' => 'assets/framework1.css, assets/framework12.css',
    //        'js' => 'assets/framework1.js',
    //    ],


    // Options for dev phase:
    'debug_checkMetaFiles'   => false,   // if true, Pagefactory will skip checks for presence of metafiles
    'debug_compileScssWithSrcRef'   => false,   // injects ref to source SCSS file&line in compiled CSS
    //'debug_logIP'  // -> handled in ajax_server.php
]);



require_once __DIR__ . '/helper.php';


class PageFactory
{
    public static $kirby;
    public static $page;
    public static $pages;
    public static $site;
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
    public static $supportedLanguages;
    public static $webmasterEmail;
    public static $pg;
    public static $md;
    public static $debug;
    public static string $timezone;
    public static string $locale;
    public static $isLocalhost;
    public static $user;
    public static $userName;
    public static string $slug = '';
    public static $pageId;
    public static $urlToken; // the hash code extracted from HTTP request (e.g. home/ABCDEF)
    public static $availableIcons;
    public static $phpSessionId;
    public static $assets;
    public static $config;
    public static $dataPath = PFY_CUSTOM_DATA_PATH;
    public        $pageOptions;
    public        $utils;
    public static $mdFileProcessor = false;
    public static string $wrapperTag = '';
    public static string $wrapperClass = '';
    private string $sectionsCss;
    private string $sectionsScss;

    public static bool $renderingClosed = false;

    public function __construct($data)
    {
        self::$kirby = $data['kirby'];
        self::$pages = $data['pages'];
        self::$page = $data['page'];
        self::$site = $data['site'];
        self::$siteFiles = self::$pages->files();
        self::$phpSessionId = getSessionId();

        $this->pageOptions = self::$page->content()->data();

        // find available icons:
        self::$availableIcons = findAvailableIcons();

        Extensions::findExtensions();

        self::$hostUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/';

        $this->utils = new Utils();
        Utils::loadPfyConfig();
        Utils::determineLanguage();
        TransVars::init();

        self::$debug = Utils::determineDebugState();
        self::$isLocalhost = isLocalhost();

        self::$assets = new Assets($this);
        self::$pg = new Page($this);
        self::$pg->set('pageParams', self::$page->content()->data());

        self::$timezone = Utils::getTimezone();
        self::$locale = Utils::getCurrentLocale();

        self::$pagePath = substr(self::$page->root(), strlen(site()->root()) + 1) . '/';
        self::$absAppRoot = kirby()->root() . '/';
        self::$absPfyRoot = __DIR__ . '/';
        self::$appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))) . '/';
        self::$appUrl = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))) . '/';
        self::$appUrl = str_replace('//', '/', self::$appUrl);
        self::$appRootUrl = kirby()->url() . '/';
        if (!self::$slug) {
            self::$slug = page()->slug();
        }
        self::$pageId = page()->id();
        self::$pageRoot = 'content/' . self::$pagePath;
        self::$absPageRoot = self::$page->root() . '/';
        self::$absPageUrl = (string)self::$page->url() . '/';
        self::$pageUrl = substr(self::$absPageUrl, strlen(self::$hostUrl) - 1);

        self::$user = Permission::checkPageAccessCode();
        self::$userName = is_object(self::$user) ? (string)self::$user->nameOrEmail() : (self::$user ?: '');

        SiteNav::render();

        Extensions::loadExtensions();

        TransVars::loadCustomVars();

        preparePath(PFY_LOGS_PATH);
        Utils::showPendingMessage();
        Utils::handleAgentRequests();
    } // __construct


    /**
     * Wrapper for _renderPageContent(). Catches errors and redirects to error page while in productive mode.
     * @return string
     * @throws Kirby\Exception\LogicException
     * @throws SassException
     */
    public function renderPageContent(): string
    {
        if (self::$debug) {
            return $this->_renderPageContent();
        } else {
            try {
                return $this->_renderPageContent();

            } catch (\Exception $e) {
                mylog($e->getMessage());
                if (!self::$debug) {
                    // in productive mode: try flush-cache-and-reload once, then give up and return error msg:
                    //  -> in particular after first upload this can fix problems.
                    $session = kirby()->session();
                    if ($session->get('pfy.secondErrorRun')) {
                        $session->remove('pfy.secondErrorRun');
                        return 'An error occurred on the server - please try again later';
                    } else {
                        $session->set('pfy.secondErrorRun', true);
                        Cache::flushAll();
                        mylog("=== reloading after first attempt to flush cache. ===");
                        reloadAgent();
                    }
                } else {
                    return $e->getMessage();
                }
            }
        }
        return '';
    } // renderPageContent


    /**
     * Renders the actual content of the current page,
     *   i.e. what is invoked in template as {{ page.text.kirbytext | raw }}
     * @return string
     * @throws Kirby\Exception\LogicException
     * @throws SassException
     */
    public function _renderPageContent(): string
    {
        Maintenance::trigger(1); // first run -> superviseKirbyCache()

        Extensions::extensionsFinalCode(); //??? best position?
        Utils::prepareStandardVariables();

        Utils::handleAgentRequestsOnRenderedPage();
        Utils::executeCustomCode();

        $html = '';
        $inx = 0;

        if (self::$config['includeMetaFileContent']) {
            // get and compile meta-file's text field:
            if ($mdStr = self::$page->text()->value()) {
                $html = TransVars::compile($mdStr. "\n\n", $inx);
            }
        }

        // load content from .md files:
        $html .= $this->loadMdFiles();

        // finalize:
        if ($html) {
            TransVars::lastPreparations();

            // resolve (nested) variables:
            $cnt = 3;
            while ($cnt-- && str_contains($html, '{{')) {
                $html = TransVars::resolveVariables($html);
            }
            $html = unshieldStr($html);
            $html = str_replace(['{!!{', '}!!}', '⟮'], ['{{', '}}', '('], $html);
        }

        Utils::prepareTemplateVariables();
        $html = self::$pg->renderBody($html);
        self::$renderingClosed = true;

        Maintenance::trigger(2); // second run -> registered callbacks

        return $html;
    } // _renderPageContent


    /**
     * @return string
     * @throws \Exception
     */
    private function loadMdFiles(): string
    {
        // check page access restriction:
        if (!$this->checkAccessRestriction()) {
            // insufficient privilege -> show login form instead (via $pg->overrideContent)
            return '';
        }

        $wrapperTag = PageFactory::$wrapperTag;
        $customWrapperClass = PageFactory::$wrapperClass;
        $path = self::$page->root();
        $dir = getDir("$path/*.md");

        // first find _meta.md files (only containing frontmatter but no content):
        foreach ($dir as $i => $file) {
            if (str_contains('#-_', basename($file)[0])) {
                continue;
            }
            if (str_ends_with($file, '_meta.md')) {
                $mdStr = getFile($file, 'cstyle,emptylines,twig');
                $this->extractFrontmatter($mdStr);
                // if some CSS/SCSS found in frontmatter, request rendering it now:
                $this->propagateFrontmatterStyles('pfy-main');
                unset($dir[$i]);
            }
        }

        // process remaining .md files:
        $inx = 0;
        $finalHtml = '';
        foreach ($dir as $file) {
            if (str_contains('#-_', basename($file)[0])) {
                continue;
            }
            $inx++;
            $mdStr = getFile($file, 'cstyle,emptylines,twig');
            if (!$this->extractFrontmatter($mdStr)) {
                continue;
            }

            $wrapperId = "pfy-part-$inx";
            $fileId = translateToClassName(base_name($file, false), false);
            $fileId = 'pfy-src-'.preg_replace('/^\d+[_\s]?/', '', $fileId);
            $wrapperClass = "pfy-$wrapperTag-wrapper $wrapperId $fileId $customWrapperClass";

            if (self::$mdFileProcessor) {
                $html = (self::$mdFileProcessor)($mdStr, $inx, $wrapperTag, $wrapperId, $wrapperClass);

            } else {
                $html = TransVars::compile($mdStr, $inx, removeComments: false);
                $html = <<<EOT

<$wrapperTag id='$wrapperId' class='$wrapperClass'>

$html
</$wrapperTag>


EOT;
            }

            // if some CSS/SCSS found in frontmatter, request rendering it now:
            $this->propagateFrontmatterStyles($wrapperId);

            $finalHtml .= $html;
        } // loop over files

        $finalHtml = Utils::resolveUrls($finalHtml);

        return $finalHtml;
    } // loadMdFiles


    /**
     * @param $mdStr
     * @return bool
     * @throws Kirby\Exception\InvalidArgumentException
     */
    private function extractFrontmatter(&$mdStr): bool
    {
        $this->sectionsCss = '';
        $this->sectionsScss = '';
        $fields = preg_split('!\n-{4}\n!', $mdStr);
        $n = sizeof($fields)-1;
        $mdStr = $fields[$n];
        $continue = true;

        // loop through all fields and add them to the content
        for ($i=0; $i<$n; $i++) {
            $field = trim($fields[$i]);
            $pos = strpos($field, ':');
            $key = camelCase(trim(substr($field, 0, $pos)));
            $key = strtolower($key);

            // Don't add fields with empty keys
            if (empty($key) === true) {
                continue;
            }

            $value = trim(substr($field, $pos + 1));

            if ($key === 'variables') {
                $values = Yaml::decode($value);
                foreach ($values as $k => $v) {
                    if (str_contains($v, '{{')) {
                        $v = TransVars::compile($v);
                    }
                    TransVars::setVariable($k, $v);
                }

            } elseif (str_contains('description,keywords,author', $key)) {
                self::$pg->addHead("  <meta name='$key' content='$value'>\n");

            } elseif ($key === 'robots') {
                self::$pg->applyRobotsAttrib($value);

            } elseif ($key === 'wrappertag') {
                $this->wrapperTag = $value;

            } elseif ($key === 'wrapperclass') {
                $this->wrapperClass = $value;

            } elseif ($key === 'css') {
                // hold back till ".this"/"#this" can be resolved:
                $this->sectionsCss = $value;

            } elseif ($key === 'scss') {
                // hold back till ".this"/"#this" can be resolved:
                $this->sectionsScss = $value;

            } elseif ($key === 'js') {
                self::$pg->addJs($value);

            } elseif ($key === 'jsready') {
                self::$pg->addJsReady($value);

            } elseif ($key === 'jq') {
                self::$pg->addJq($value);

            } elseif ($key === 'assets') {
                $assets = Yaml::decode($value);
                foreach ($assets as $asset) {
                    self::$pg->addAssets($asset);
                }

            } elseif ($key === 'visibility') {
                if (!Permission::evaluate($value)) {
                    $continue = false;
                }

            } else {
                // unescape escaped dividers within a field
                TransVars::setVariable($key, $value);
            }
        }
        return $continue;
    } // extractFrontmatter


    /**
     * @return bool
     * @throws \Exception
     */
    private function checkAccessRestriction(): bool
    {
        if ($accessRestriction = page()->accessrestriction()->value()) {
            $accessGranted = Permission::evaluate($accessRestriction);
            if (!$accessGranted) {
                if (Extensions::$loadedExtensions['PageElements']??false) {
                    \PgFactory\PageFactoryElements\Login::init(['as-popup' => true]);
                    $html = \PgFactory\PageFactoryElements\Login::render('{{ pfy-restricted-page }}');
                    if ($html) {
                        PageFactory::$pg->overrideContent($html);
                    }
                    return false;

                } else {
                    $loginLink = PageFactory::$appUrl.'panel/login/';
                    reloadAgent($loginLink);
                }
            }
        }
        return true;
    } // checkAccessRestriction


    public static function registerSrcFileProcessor(string $functionName): void
    {
        self::$mdFileProcessor = $functionName;
    } // registerSrcFileProcessor

    private function propagateFrontmatterStyles(string $wrapperId): void
    {
        if ($this->sectionsCss) {
            $this->sectionsCss = str_replace(['#this', '.this'], ["#$wrapperId", ".$wrapperId"], $this->sectionsCss);
            self::$pg->addCss($this->sectionsCss);
        }
        if ($this->sectionsScss) {
            $this->sectionsScss = str_replace(['#this', '.this'], ["#$wrapperId", ".$wrapperId"], $this->sectionsScss);
            self::$pg->addScss($this->sectionsScss);
        }
    }
} // PageFactory
