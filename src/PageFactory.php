<?php

namespace PgFactory\PageFactory;

use Kirby;
use Kirby\Data\Yaml;
use Kirby\Http\Url;
use PgFactory\MarkdownPlus\MdPlusHelper;
use ScssPhp\ScssPhp\Exception\SassException;
use PgFactory\MarkdownPlus\Permission;

 // System Paths:
 // defined in config.php:
 //  PFY_DOCROOT
 //  PFY_BASE_OFFSET    = onair/
 //  PFY_APP_BASE_PATH  = PFY_DOCROOT . PFY_BASE_OFFSET

if (!defined('PFY_DOCROOT')) {
    define('PFY_DOCROOT', dirname($_SERVER['SCRIPT_FILENAME']) . '/');
}
if (!defined('PFY_BASE_OFFSET')) {
    define('PFY_BASE_OFFSET', '');
}
if (!defined('PFY_APP_BASE_PATH')) {
    define('PFY_APP_BASE_PATH', PFY_DOCROOT . PFY_BASE_OFFSET);
}

// System ULRs:
define('PFY_HOST_URL',                  $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/'); // https://domain.net/
define('PFY_APP_BASE_URL',              URL::index().'/');    // https://domain.net/webapp/
define('PFY_PAGE_URL',                  page()->url() . '/'); // https://domain.net/webapp/pg1/

// Further Urls and Paths:
define('PFY_PAGEFACTORY_PATH',          dirname(__DIR__) . '/'); // site/plugins/pagefactory/
define('PFY_CONTENT_ASSETS_PATH',       PFY_APP_BASE_PATH . 'content/assets/');
define('PFY_PAGEFACTORY_ASSETS_PATH',   PFY_PAGEFACTORY_PATH . 'assets/');
define('PFY_PAGEFACTORY_ICONS_PATH',    PFY_PAGEFACTORY_PATH . 'assets/icons/');

define('PFY_PAGE_PATH',                 page()->root() . '/');
define('PFY_PAGE_URI',                  page()->uri() . '/');

define('PFY_SVG_ICONS_PATH',            PFY_APP_BASE_PATH . 'site/plugins/markdownplus/assets/svg-icons/');
define('PFY_CONFIG_PATH',               PFY_APP_BASE_PATH . 'site/config/');
define('PFY_CONFIG_FILE',               PFY_CONFIG_PATH.'config.php');
define('PFY_CUSTOM_PATH',               PFY_APP_BASE_PATH . 'site/custom/');
define('PFY_CUSTOM_DATA_PATH',          PFY_CUSTOM_PATH.'data/');
if (!defined('PFY_LOGS_PATH')) {
    define('PFY_LOGS_PATH',             PFY_APP_BASE_PATH . 'site/logs/');
}
if (!defined('PFY_CACHE_PATH')) {
    define('PFY_CACHE_PATH',            PFY_APP_BASE_PATH . 'site/cache/pagefactory/');
}
define('PFY_LOGIN_LOG_FILE',           'login-log.txt');
define('PFY_DOWNLOAD_PATH',             PFY_APP_BASE_PATH . 'download/');
define('PFY_TEMP_DOWNLOAD_PATH',        PFY_DOWNLOAD_PATH.'temp/'); // for temp download of datasets (excel-format)

define('PFY_WEBMASTER_EMAIL_CACHE',     PFY_CACHE_PATH.'webmaster-email.txt');
define('PFY_INSTALLATION_PATH_CHECK',   PFY_CACHE_PATH.'installation-path.txt');


 // misc constants:
const PFY_BASE_ASSETS_URL =        PFY_APP_BASE_URL . 'media/plugins/pgfactory/';
const PFY_ASSETS_URL =             PFY_BASE_ASSETS_URL.'pagefactory/';
const PAGED_POLYFILL_SCRIPT_URL =  PFY_ASSETS_URL.'js/paged.polyfill.min.js';
const PFY_DEFAULT_LOCALE =         'en_GB';


 // use this name for meta-files (aka text-files) in page folders:
define('PFY_PAGE_META_FILE_BASENAME','z'); // 'define' required by site/plugins/pagefactory/index.php

define('OPTIONS_DEFAULTS', [
    'defaultLanguage'               => 'en',  // default language used, if none is available from Kirby
    'default-nav'                   => true,  // automatically loads NAV assets
    'externalLinksToNewWindow'      => true,  // -> used by Link() -> whether to open external links in new window
    'imageAutoQuickzoom'            => true,  // -> default for Img() macro
    'imageAutoSrcset'               => true,  // -> default for Img() macro
    'includeMetaFileContent'        => true,  // -> option for website using '(include: *.md)' in metafile
                                              // e.g. when converting from MdP site to Pfy
    'screenSizeBreakpoint'          => 480,   // Value used by JS to switch body classes ('pfy-large-screen' and 'pfy-small-screen')
    'webmaster_email'               => '',    // email address of webmaster
    // 'timezone' => 'Europe/Zurich', // PageFactory tries to guess the timezone - you can override this manually
]);



require_once __DIR__ . '/helper.php';


class PageFactory
{
    public static $kirby;
    public static $page;
    public static $pages;
    public static $site;

    public static $debug;
    public static $dev;
    public static $productionMode;
    public static $lang;
    public static $langCode;
    public static $defaultLanguage;
    public static $supportedLanguages;
    public static $webmasterEmail;
    public static $pg;
    public static $md;
    public static string $timezone;
    public static string $locale;
    public static $isLocalhost;
    public static $user;
    public static $userName;
    public static $availableIcons;
    public static $assets;
    public static $config;
    public static $customConfigPath = PFY_CONFIG_PATH;
    public static $dataPath = PFY_CUSTOM_DATA_PATH;
    public static bool $forceAssetsUpdate = false;

    public static bool $lazyLoading = false;
    public static bool $renderingClosed = false;
    public static bool $addSectionInnerWrapper = false;
    public static string $sectionWrapperClass = '';

    public function __construct($data)
    {
        self::$kirby = $data['kirby'];
        self::$pages = $data['pages'];
        self::$page = $data['page'];
        self::$site = $data['site'];

        self::$dev = Utils::determineDevState();
        self::$productionMode = !self::$dev;
        Utils::prepareDataPaths();
        Cache::init(); // force cache reset on first request every day, inhibit cache in debug mode

        // find available icons:
        self::$availableIcons = findAvailableIcons();

        Extensions::findExtensions();

        Utils::loadPfyConfig();
        Utils::determineLanguage();
        self::$isLocalhost = isLocalhost();
    } // __construct


    /**
     * @return void
     * @throws Kirby\Exception\InvalidArgumentException
     * @throws Kirby\Exception\LogicException
     */
    public function prepareTemplateFields(): void
    {
        $page = self::$page;
        $pageFields = false;
        if (Cache::$pageCachingEnabled && $this->checkAccessRestriction()) {
            $pageFields = Cache::checkPageCache();
        }

        if (!$pageFields) {
            self::init();
            Utils::prepareUserRelatedVars();
            $pageFields = [
                'lang'                      => self::$langCode,
                'baseUrl'                   => PFY_APP_BASE_URL,
                'generator'                 => Utils::renderGenerator(),
                'homeLink'                  => Utils::renderHomeLink(),
                'adminPanelLink'            => Utils::renderAdminPanelLink(),
                'loggedIn'                  => Utils::$loggedIn,
                'loginLink'                 => Utils::$loginLink,
                'username'                  => self::$userName,
                'loginButton'               => Utils::$loginButton,

                'pageContent'               => $this->renderPageContent(),

                // variables that might be defined in Frontmatter:
                'headTitle'                 => Utils::renderHeadTitle(),
                'smallScreenHeader'         => Utils::renderSmallScreenHeader(),
                'menuIcon'                  => Utils::$menuIcon,
                'langSelection'             => Utils::renderLanguageSelector(),

                // the major page defining variables:
                'headInjections'            => Page::renderHeadInjections(),
                'bodyTagClasses'            => Utils::renderBodyTagClasses(),
                'bodyTagAttributes'         => Page::get('bodyTagAttributes'),
                'bodyEndInjections'         => Page::renderBodyEndInjections(),
                'cacheIndicator'            => '',
            ];
            self::$renderingClosed = true;
            Cache::updatePageCache($pageFields);
        }

        $page->lang()->value                = $pageFields['lang'];
        $page->baseUrl()->value             = $pageFields['baseUrl'];
        $page->headTitle()->value           = $pageFields['headTitle'];
        $page->generator()->value           = $pageFields['generator'];
        $page->homeLink()->value            = $pageFields['homeLink'];
        $page->localhost()->value           = isLocalhost();
        $page->debug()->value               = PageFactory::$debug;
        $page->dev()->value                 = PageFactory::$dev;
        $page->adminPanelLink()->value      = $pageFields['adminPanelLink'];
        $page->loggedIn()->value            = $pageFields['loggedIn'];
        $page->loginLink()->value           = $pageFields['loginLink'];
        $page->username()->value            = $pageFields['username'];
        $page->loginButton()->value         = $pageFields['loginButton'];
        $page->smallScreenHeader()->value   = $pageFields['smallScreenHeader'];
        $page->langSelection()->value       = $pageFields['langSelection'];
        $page->menuIcon()->value            = $pageFields['menuIcon'];
        $page->cacheIndicator()->value      = $pageFields['cacheIndicator'];

        $page->headInjections()->value      = $pageFields['headInjections'];
        $page->bodyTagClasses()->value      = $pageFields['bodyTagClasses'];
        $page->bodyTagAttributes()->value   = $pageFields['bodyTagAttributes'];
        $page->bodyEndInjections()->value   = $pageFields['bodyEndInjections'];

        $page->pageContent()->value         = $pageFields['pageContent'];

    } // prepareTemplateFields


    /**
     * @return void
     * @throws Kirby\Exception\InvalidArgumentException
     */
    private function init(): void
    {
        Utils::importKirbyFieldsToVariables();
        TransVars::init();
        Utils::prepareWebmasterEmail();

        if (!file_exists(PFY_APP_BASE_PATH.'site/plugins/pagefactory/assets/css/-pagefactory.css')) {
            self::$forceAssetsUpdate = true;
        }

        SiteNav::init(); // determines site structure, prev and next pages/links


        self::$timezone = Utils::getTimezone();
        self::$locale = Utils::getCurrentLocale();

        self::$user = Permission::checkPageAccessCode();
        self::$userName = is_object(self::$user) ? (string)self::$user->nameOrEmail() : (self::$user ?: '');

        Extensions::loadExtensions();
        Utils::checkInstallationPath();

        if (self::$dev) {
            Assets::compileAssets();
        }

        // load custom variables:
        TransVars::loadVariablesFromFolder('site/custom/variables/');

        Utils::prepareGenericVariables();
        preparePath(PFY_LOGS_PATH);
        Utils::showPendingMessage();
        Utils::handleAgentRequests();
        Macros::initMacros();

        Utils::queuePfyIconDefinitions();
    } // init



    /**
     * Wrapper for _renderPageContent(). Catches errors and redirects to error page while in productive mode.
     * @return string
     * @throws Kirby\Exception\LogicException
     * @throws SassException
     */
    public function renderPageContent(): string
    {
        Extensions::extensionsFinalCode();
        Utils::handleAgentRequestsOnRenderedPage();

        if (self::$dev) {
            return $this->_renderPageContent();
        } else {
            try {
                return $this->_renderPageContent();

            } catch (\Exception $e) {
                mylog($e->getMessage());
                if (!self::$dev) {
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

        return Page::renderBody($html);
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

        $excludePattern = kirby()->option('pgfactory.pagefactory.excludeFilesRegex');
        $path = self::$page->root();
        $files = getDir("$path/*.md");

        // first find _meta.md files (only containing frontmatter but no content):
        foreach ($files as $i => $file) {
            if (str_contains('#-_', basename($file)[0])) {
                continue;
            }
            if (str_ends_with($file, '_meta.md')) {
                $mdStr = getFile($file, 'cstyle,emptylines,twig');
                Frontmatter::extract($mdStr);
                Frontmatter::propagaterStyles('pfy-main');
                unset($files[$i]);

            // optionally exclude certain files from the rendering process:
            } elseif ($excludePattern && preg_match("/$excludePattern/", $file)) {
                unset($files[$i]);
            }
        }

        // process remaining .md files:
        $inx = 0;
        $finalHtml = '';
        foreach ($files as $file) {
            if (str_contains('#-_', basename($file)[0])) {
                continue;
            }
            $inx++;
            $mdStr = getFile($file, 'cstyle,emptylines,twig');
            if (!$res = Frontmatter::extract($mdStr)) {
                continue;
            }

            // inner wrappers for sections -> used by PresentationSupport:
            $innerWrapper1 = $innerWrapper2 = '';
            if (self::$addSectionInnerWrapper) {
                $innerWrapper1 = "<div class='pfy-section-inner'>\n";
                $innerWrapper2 = "\n</div><!-- /.pfy-section-inner -->";
            }

            list($mdStr, $wrapperTag, $wrapperClass) = $res;
            if (!$mdStr) {
                continue;
            }
            $wrapperClass .= self::$sectionWrapperClass; // -> used by Presentation

            $wrapperId = "pfy-part-$inx";
            $fileId = translateToClassName(base_name($file, false), false);
            $fileId = 'pfy-src-'.preg_replace('/^\d+[_\s]?/', '', $fileId);
            $wrapperClass = "pfy-$wrapperTag-wrapper$wrapperClass $wrapperId $fileId";
            $html = TransVars::compile($mdStr, $inx, removeComments: false);
            $html = <<<EOT

<$wrapperTag id='$wrapperId' class='$wrapperClass'>
$innerWrapper1
$html

$innerWrapper2
</$wrapperTag>


EOT;

            // if some CSS/SCSS found in frontmatter, request rendering it now:
            Frontmatter::propagaterStyles($wrapperId);

            $finalHtml .= $html;
        } // loop over files

        return $finalHtml;
    } // loadMdFiles


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
                        Page::overrideContent($html);
                    }
                    return false;

                } else {
                    $loginLink = PFY_APP_BASE_URL.'panel/login/';
                    reloadAgent($loginLink);
                }
            }
        }
        return true;
    } // checkAccessRestriction


    /**
     * @param string $html
     * @return string
     * @throws \Exception
     */
    public static function cleanUp(string $html): string
    {
        $html = unshieldStr($html);
        $html = Utils::resolveUrls($html);
        return unshieldStr($html, true, true);
    } // cleanUp
} // PageFactory
