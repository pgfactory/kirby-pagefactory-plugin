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
 //  PFY_DOCROOT            = /path/to/localhost/
 //  PFY_APP_BASE_PATH      = /path/to/localhost/app/
 //  PFY_BASE_OFFSET        = onair/
 //  PFY_KIRBY_BASE_PATH    = /path/to/localhost/app/onair/     = PFY_APP_BASE_PATH . PFY_BASE_OFFSET


 // System ULRs:
define('PFY_HOST_URL',                  $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/'); // https://domain.net/
define('PFY_APP_BASE_URL',              URL::index().'/');    // https://domain.net/webapp/
define('PFY_PAGE_URL',                  page()->url() . '/'); // https://domain.net/webapp/pg1/

 // Further Urls and Paths:
define('PFY_PLUGIN_PFY_PATH',           dirname(__DIR__) . '/'); // site/plugins/pagefactory/
define('PFY_CONTENT_ASSETS_PATH',       PFY_KIRBY_BASE_PATH . 'content/assets/');

define('PFY_PAGE_PATH',                 page()->root() . '/');
define('PFY_PAGE_URI',                  page()->uri() . '/');
define('PFY_PAGE_ID',                   page()->id());

define('PFY_SVG_ICONS_PATH',            PFY_KIRBY_BASE_PATH . 'site/plugins/markdownplus/assets/svg-icons/');
define('PFY_CONFIG_PATH',               PFY_KIRBY_BASE_PATH . 'site/config/');
define('PFY_CONFIG_FILE',               PFY_CONFIG_PATH.'config.php');
define('PFY_CUSTOM_PATH',               PFY_KIRBY_BASE_PATH . 'site/custom/');
define('PFY_CUSTOM_DATA_PATH',          PFY_CUSTOM_PATH.'data/');
if (!defined('PFY_LOGS_PATH')) {
    define('PFY_LOGS_PATH',             PFY_KIRBY_BASE_PATH . 'site/logs/');
}
if (!defined('PFY_CACHE_PATH')) {
    define('PFY_CACHE_PATH',            PFY_KIRBY_BASE_PATH . 'site/cache/pagefactory/');
}
define('PFY_LOGIN_LOG_FILE',           'login-log.txt');
define('PFY_TEMP_PATH',                 '~/media/pgfactory/');
define('PFY_TEMP_DOWNLOAD_PATH',        PFY_TEMP_PATH.'download/'); // for temp download of datasets (excel-format)

const PFY_GITTAG_FILE =                 PFY_KIRBY_BASE_PATH.'site/custom/gittag.txt';

define('PFY_WEBMASTER_EMAIL_CACHE',     PFY_CACHE_PATH.'webmaster-email.txt');
define('PFY_INSTALLATION_PATH_CHECK',   PFY_CACHE_PATH.'installation-path.txt');


 // misc constants:
const PFY_KIRBY_ASSETS_BASE_URL =       PFY_APP_BASE_URL . PFY_BASE_OFFSET;
const PAGED_POLYFILL_SCRIPT =           PFY_KIRBY_ASSETS_BASE_URL.'media/plugins/pgfactory/pagefactory/js/paged.polyfill.min.js';
const PFY_DEFAULT_LOCALE =              'en_GB';
const PFY_DEFAULT_FAVICON_FILE =        PFY_KIRBY_BASE_PATH . 'assets/favicon/favicon.png';
const PFY_IMG_PLACEHOLDER =             PFY_KIRBY_ASSETS_BASE_URL.'media/plugins/pgfactory/pagefactory/icons/hourglass.png';


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
    public static bool $slidingPanels = false;

    public function __construct($page, $pages, $site, $kirby)
    {
        self::checkInstallation();

        self::$kirby = $kirby;
        self::$pages = $pages;
        self::$page = $page;
        self::$site = $site;

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
    public function prepareTemplateFields(): array
    {
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
                'adminPanelLink'            => Utils::renderAdminPanelLink(),
                'favicon'                   => Utils::renderFavicon(),
                'homeLink'                  => Utils::renderHomeLink(),
                'loggedInAs'                => Utils::$loggedIn,
                'loginLink'                 => Utils::$loginLink,
                'username'                  => self::$userName,
                'loginButton'               => Utils::$loginButton,

                'pageContent'               => $this->renderPageContent(),

                // variables that might be defined in Frontmatter:
                'headTitle'                 => Utils::renderHeadTitle(),
                'smallScreenHeader'         => Utils::renderSmallScreenHeader(),
                //'menuIcon'                  => Utils::$menuIcon,
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
        $pageFields['localhost'] = isLocalhost();
        $pageFields['debug'] = self::$debug;
        $pageFields['dev'] = self::$dev;
        return $pageFields;
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

        if (!file_exists(PFY_KIRBY_BASE_PATH.'site/plugins/pagefactory/assets/css/-pagefactory.css')) {
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
                unset($files[$i]);
                continue;
            }
            if (str_ends_with($file, '.meta.md')) {
                $mdStr = getFile($file, 'cstyle,emptylines,twig');
                Frontmatter::extract($mdStr);
                Frontmatter::propagaterStyles('pfy-main');
                unset($files[$i]);

            // optionally exclude certain files from the rendering process:
            } elseif ($excludePattern && preg_match("/$excludePattern/", $file)) {
                unset($files[$i]);
            }
        }

        $slidingPanelsMode = !strcasecmp((string)page()->mode()->value(), 'slidingPanels');
        $slidingPanelsHeader = '';
        $sectionTitles = [];
        $mdContents = [];
        $outerWrapper1 = $outerWrapper2 = '';

        // sort out remaining files:
        foreach ($files as $i => $file) {
            $mdStr = getFile($file, 'cstyle');

            // extract frontmatter:
            if ((!$res = Frontmatter::extract($mdStr)) || !trim($res[0], " \n\t")) {
                // frontmatter indicated that this file shall be supressed
                unset($files[$i]);
                continue;
            }

            $mdContents[] = $res;
            if (preg_match("/\n#\s+(.*?)\n/ms", "\n".$res[0], $m)) {
                $sectionTitle = $m[1];
                $sectionTitle = preg_replace('/\s*\{:.*/', '', $sectionTitle);
                $sectionTitles[] = $sectionTitle;
            } else {
                $sectionTitle = base_name($file, false);
                $sectionTitle = ucfirst(preg_replace('/^\S_/', '', $sectionTitle));
                $sectionTitles[] = $sectionTitle;
            }
        }

        $slidingPanelsMode |= self::$slidingPanels;
        if ($slidingPanelsMode) {
            $outerWrapper1 = "<div>\n";
            $outerWrapper2 = "\n</div><!-- /.pfy-section-outer -->";
        }

        // process remaining .md files:
        $inx = 0;
        $finalHtml = '';
        $abort = false;
        foreach ($files as $file) {
            // inner wrappers for sections -> used by PresentationSupport:
            $innerWrapper1 = $innerWrapper2 = '';
            if (self::$addSectionInnerWrapper) {
                $innerWrapper1 = "<div class='pfy-section-inner'>\n";
                $innerWrapper2 = "\n</div><!-- /.pfy-section-inner -->";
            }

            list($mdStr, $wrapperTag, $wrapperClass) = $mdContents[$inx];
            $inx++;

            // check for end-of-page tag:
            if (str_contains($mdStr, '__EOP__')) {
                $abort = true; // skip any forther md files
                $mdStr = substr($mdStr, 0, strpos($mdStr, '__EOP__')); // cut off tag and all that follows
            }

            $wrapperClass .= self::$sectionWrapperClass; // -> used by Presentation

            if ($slidingPanelsMode) {
                list($wrapperCls, $innerWrapper1, $innerWrapper2, $slidingPanelsHeader) = $this->handleSlidingPanels($inx, $sectionTitles);
                $wrapperClass .= $wrapperCls;
            }

            $wrapperId = "pfy-part-$inx";
            $fileId = translateToClassName(base_name($file, false), false);
            $fileId = 'pfy-src-'.preg_replace('/^\d+[_\s]?/', '', $fileId);
            $wrapperClass = "pfy-$wrapperTag-wrapper$wrapperClass $wrapperId $fileId";
            $html = TransVars::compile($mdStr, $inx, removeComments: false);
            $html = <<<EOT

<$wrapperTag id='$wrapperId' class='$wrapperClass'>
$slidingPanelsHeader$innerWrapper1
$html

$innerWrapper2
</$wrapperTag>


EOT;

            // if some CSS/SCSS found in frontmatter, request rendering it now:
            Frontmatter::propagaterStyles($wrapperId);

            $finalHtml .= $html;
            if ($abort) {
                break;
            }
        } // loop over files

        if ($slidingPanelsMode) {
            $finalHtml = <<<EOT
<div class="pfy-panels-wrapper">
$outerWrapper1$finalHtml$outerWrapper2
</div><!-- /panels-wrapper -->

EOT;

        }
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


    /**
     * @return void
     * @throws \Exception
     */
    private static function checkInstallation(): void
    {
        // check presence of .htaccess file in app root, depending on presence of app-base-offset:
        $htaccessFile = PFY_KIRBY_BASE_PATH . '.htaccess';
        $htaccessDisabledFile = PFY_KIRBY_BASE_PATH . '#.htaccess';
        if (PFY_BASE_OFFSET) {
            if (file_exists($htaccessFile)) {
                mylog("Installation Check: BASE_OFFSET active -> renaming '.htaccess' to '#.htaccess'");
                @rename($htaccessFile, $htaccessDisabledFile);
                reloadAgent(PFY_PAGE_URL);
            }
        } elseif (!file_exists($htaccessFile)) {
            if (file_exists($htaccessDisabledFile)) {
                mylog("Installation Check: BASE_OFFSET inactive -> renaming '#.htaccess' to '.htaccess'");
                @rename($htaccessDisabledFile, $htaccessFile);
                reloadAgent(PFY_PAGE_URL);
            } else {
                exit('Fatal error: file ".htaccess" is missing');
            }
        }
    } // checkInstallation


    /**
     * @param mixed $inx
     * @param array $sectionTitles
     * @return string[]
     */
    private function handleSlidingPanels(mixed $inx, array $sectionTitles): array
    {
        $wrapperClass = " pfy-panel pfy-panel-$inx";
        if ($inx === 1) {
            $wrapperClass .= ' pfy-panel-open';
        }

        $panelCenter = "";
        foreach ($sectionTitles as $i => $label) {
            $pInx = ($i + 1);
            $class = ($inx === $i+1) ? 'pfy-curr-panel' : '';
            $title = ($sectionTitles[$i] ?? false) ? " title='{$sectionTitles[$i]}'" : '';
            $panelCenter .= "<button data-panel='$pInx' class='$class'$title>$pInx</button><span></span>";
        }
        $panelCenter = substr($panelCenter, 0, strlen($panelCenter)-13);

        $innerWrapper1 = "  <div class='pfy-section-inner'>\n";
        $innerWrapper2 = "\n  </div><!-- /.pfy-section-inner -->";
        $labelPrev = ($sectionTitles[$inx-2] ?? false) ? '&larr; ' . ($inx-1) : '';
        $labelNext = ($sectionTitles[$inx] ?? false) ? ($inx+1) ." &rarr;" : '';
        $titlePrev = ($sectionTitles[$inx-2] ?? false) ? " title='{$sectionTitles[$inx-2]}'" : '';
        $titleNext = ($sectionTitles[$inx] ?? false) ? " title='{$sectionTitles[$inx]}'" : '';
        $slidingPanelsHeader = <<<EOT
    <div class='pfy-panel-arrows'>
        <button class='pfy-panel-arrow-prev'$titlePrev><span>$labelPrev</span></button>
        <div class='pfy-panel-header-center'>$panelCenter</div>
        <button class='pfy-panel-arrow-next'$titleNext><span>$labelNext</span></button>
    </div>

EOT;
        return [$wrapperClass, $innerWrapper1, $innerWrapper2, $slidingPanelsHeader];
    } // handleSlidingPanels

} // PageFactory
