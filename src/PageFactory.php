<?php

namespace Usility\PageFactory;

use Kirby;
use Kirby\Data\Yaml;
use Matrix\Exception;
use ScssPhp\ScssPhp\Exception\SassException;

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
const PFY_MACROS_PATH =            PFY_BASE_PATH.'macros/';
define('PFY_LOGS_PATH',            'site/logs/');
define('PFY_CACHE_PATH',           'site/cache/pagefactory/'); // available in extensions
const PFY_MKDIR_MASK =             0700; // permissions for file accesses by PageFactory
const BLOCK_SHIELD =               'div shielded';
const INLINE_SHIELD =              'span shielded';
const MD_SHIELD =                  'span mdshielded';

 // URLs:
const PFY_BASE_ASSETS_URL =        'media/plugins/pgfactory/';
const PFY_ASSETS_URL =             PFY_BASE_ASSETS_URL.'pagefactory/';
const PAGED_POLYFILL_SCRIPT_URL =  PFY_ASSETS_URL.'js/paged.polyfill.min.js';

const DEFAULT_FRONTEND_FRAMEWORK_URLS = ['js' => PFY_ASSETS_URL.'js/jquery-3.6.1.min.js'];

 // use this name for meta-files (aka text-files) in page folders:
define('PFY_PAGE_META_FILE_BASENAME','z'); // 'define' required by site/plugins/pagefactory/index.php

define('OPTIONS_DEFAULTS', [
    'screenSizeBreakpoint'          => 480,
    'defaultLanguage'               => 'en',
    'allowNonPfyPages'              => false, // -> if true, Pagefactory will skip checks for presence of metafiles
    'externalLinksToNewWindow'      => true,  // -> used by Link() -> whether to open external links in new window
    'imageAutoQuickview'            => true,  // -> used by Img() macro
    'imageAutoSrcset'               => true,  // -> used by Img() macro
    'divblockChars'                => '@%',  // possible alternative: ':$@%'
    'includeMetaFileContent'        => true,  // -> option for website using '(include: *.md)' in metafile
                                              // e.g. when converting from MdP site to Pfy
    'activateH1Sections'            => false, //
    'sourceWrapperTag'              => 'section', //
    'sourceWrapperClass'            => '', //
    // 'timezone' => 'Europe/Zurich', // PageFactory tries to guess the timezone - you can override this manually

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
    public static $trans;
    public static $pg;
    public static $md;
    public static $debug;
    public static string $timezone;
    public static string $locale;
    public static $isLocalhost;
    public static $timer;
    public static $user;
    public static string $slug = '';
    public static $pageId;
    public static $urlToken; // the hash code extracted from HTTP request (e.g. home/ABCDEF)
    public static $availableIcons;
    public static $phpSessionId;
    public static $assets;
    public static $config;
    public static $session;
    public $pageOptions;
    public $utils;
    public $value; //???
    private string $wrapperTag;
    private string $wrapperClass;
    private string $wrapperClass2;
    private string $sectionsCss;
    private string $sectionsScss;

    private bool $autoSplitSections;
    public static bool $renderingClosed = false;

    public function __construct($data)
    {
        self::$timer = microtime(true);
        self::$isLocalhost = isLocalhost();

        self::$kirby = $data['kirby'];
        self::$pages = $data['pages'];
        self::$page = $data['page'];
        self::$site = $data['site'];
        self::$siteFiles = self::$pages->files();
        self::$session = self::$kirby->session();
        self::$phpSessionId = getSessionId();

        $this->pageOptions = self::$page->content()->data();

        // find available icons:
        self::$availableIcons = findAvailableIcons();

        Extensions::findExtensions();

        self::$hostUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/';

        $this->utils = new Utils();
        Utils::loadPfyConfig();
        Utils::determineLanguage();
        TransVars::init();

        self::$assets = new Assets($this);
        self::$pg = new Page($this);
        self::$pg->set('pageParams', self::$page->content()->data());

        Extensions::loadExtensions();

        Utils::determineDebugState();

        self::$timezone = Utils::getTimezone();
        self::$locale = Utils::getCurrentLocale();

        self::$pagePath = substr(self::$page->root(), strlen(site()->root())+1) . '/';
        self::$absAppRoot = kirby()->root().'/';
        self::$absPfyRoot = __DIR__.'/';
        self::$appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appUrl = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appRootUrl = kirby()->url().'/';
        if (!self::$slug) {
            self::$slug = page()->slug();
        }
        self::$pageId = page()->id();
        self::$pageRoot = 'content/' . self::$pagePath;
        self::$absPageRoot = self::$page->root() . '/';
        self::$absPageUrl = (string)self::$page->url() . '/';
        self::$pageUrl = substr(self::$absPageUrl, strlen(self::$hostUrl)-1);
        if ($user = self::$kirby->user()) {
            self::$user = (string)$user->name();
        }
        $this->autoSplitSections = self::$config['autoSplitSectionsOnH1']??false;

        preparePath(PFY_LOGS_PATH);
        Utils::showPendingMessage();
        Utils::handleAgentRequests();
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
     * Renders the actual content of the current page,
     *   i.e. what is invoked in template as {{ page.text.kirbytext | raw }}
     * @return string
     * @throws Kirby\Exception\LogicException
     * @throws SassException
     */
    public function renderPageContent(): string
    {
        Extensions::extensionsFinalCode(); //??? best position?
        TransVars::prepareStandardVariables();

        Utils::handleAgentRequestsOnRenderedPage();
        Utils::executeCustomCode();

        $html = '';
        $inx = 0;

        if (PageFactory::$config['includeMetaFileContent']) {
            // get and compile meta-file's text field:
            $mdStr = self::$page->text()->value() . "\n\n";
            $html = $this->compile($mdStr, $inx);
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

        TransVars::prepareTemplateVariables();
        self::$renderingClosed = true;
        return $html;
    } // renderPageContent


    /**
     * @return string
     * @throws \Exception
     */
    private function loadMdFiles(): string
    {
        $this->wrapperTag = PageFactory::$config['sourceWrapperTag'];
        $this->wrapperClass = PageFactory::$config['sourceWrapperClass'];
        $path = self::$page->root();
        $dir = getDir("$path/*.md");
        $inx = 0;
        $finalHtml = '';
        foreach ($dir as $file) {
            $inx++;
            $inx0 = $inx;
            $this->wrapperClass2 = '';
            if (str_contains('#-_', basename($file)[0])) {
                continue;
            }
            $mdStr = getFile($file);
            $this->sectionsCss = '';
            $this->sectionsScss = '';
            $this->extractFrontmatter($mdStr);
            $sectId = '';

            if ($this->autoSplitSections) {
                $sections = preg_split("/(\n|)#(?!#)/ms", $mdStr, 0, PREG_SPLIT_NO_EMPTY);
                $html = '';
                foreach ($sections as $i => $md) {
                    $sectId = '';
                    if (preg_match("/^\s+ ( .+? ) [\n{] /xms", $md, $m)) {
                        $sectId = "$this->wrapperTag-".translateToClassName(rtrim($m[1]), false);

                        $this->sectionsCss = str_replace(['#this','.this'], ["#$sectId", ".$sectId"], $this->sectionsCss);
                        $this->sectionsScss = str_replace(['#this','.this'], ["#$sectId", ".$sectId"], $this->sectionsScss);
                        $sectId = " $sectId";
                    }
                    $md = "#$md";
                    $html1 = $this->compile($md, $inx, removeComments: false);

                    if ($i === 0) {
                        $html = $html1;
                    } else {
                        $section = "\n</$this->wrapperTag>\n\n\n<$this->wrapperTag id='pfy-$this->wrapperTag-$inx' class='pfy-$this->wrapperTag-wrapper pfy-$this->wrapperTag-$inx $sectId $this->wrapperClass'>\n";
                        $html .= $section.$html1;
                    }

                    $inx++;
                }

            } else {
                if (preg_match("/^\s* \# \s ( .+? ) [\n{] /xms", $mdStr, $m)) {
                    $sectId = "$this->wrapperTag-".translateToClassName(trim($m[1]), false);

                    $this->sectionsCss = str_replace(['#this','.this'], ["#$sectId", ".$sectId"], $this->sectionsCss);
                    $this->sectionsScss = str_replace(['#this','.this'], ["#$sectId", ".$sectId"], $this->sectionsScss);
                    $sectId = " $sectId";
                }

                $html = $this->compile($mdStr, $inx, removeComments: false);
            }
            $html = Utils::resolveUrls($html);

            // if some CSS/SCSS found in frontmatter, request rendering it now:
            if ($this->sectionsCss) {
                self::$pg->addCss($this->sectionsCss);
            }
            if ($this->sectionsScss) {
                self::$pg->addScss($this->sectionsScss);
            }

            $finalHtml .= <<<EOT

<$this->wrapperTag id='pfy-$this->wrapperTag-$inx0' class='pfy-$this->wrapperTag-wrapper pfy-$this->wrapperTag-$inx0 $this->wrapperClass$sectId'>
$html
</$this->wrapperTag>


EOT;
        }

        return $finalHtml;
    } // loadMdFiles


    /**
     * @param string $mdStr
     * @param $inx
     * @param $removeComments
     * @return string
     * @throws \Exception
     */
    private function compile(string $mdStr, $inx = 0, $removeComments = true): string
    {
        if ($removeComments) {
            $mdStr = removeComments($mdStr);
        }
        if (!$mdStr) {
            return '';
        }

        $mdStr = str_replace(['\\{{', '\\}}', '\\('], ['{!!{', '}!!}', '⟮'], $mdStr);
        $mdStr = TransVars::resolveVariables($mdStr);
        $mdStr = TransVars::executeMacros($mdStr);

        $html = markdown($mdStr, sectionIdentifier: "pfy-section-$inx", removeComments: false);

        // shield argument lists enclosed in '({' and '})'
        if (preg_match_all('/\(\{ (.*?) }\)/x', $html, $m)) {
            foreach ($m[1] as $i => $pattern) {
                $str = shieldStr($pattern, 'inline');
                $html = str_replace($m[0][$i], "('$str')", $html);
            }
        }

        $html = str_replace(['\\{{', '\\}}', '\\('], ['{!!{', '}!!}', '⟮'], $html);

        // add '|raw' to simple variables:
        if (preg_match_all('/\{\{ ( [^}|(]+ ) }}/msx', $html, $m)) {
            foreach ($m[1] as $i => $pattern) {
                $str = "$pattern|raw";
                $html = str_replace($m[0][$i], "{{ $str }}", $html);
            }
        }
        return $html;
    } // compile


    /**
     * @param $mdStr
     * @return void
     * @throws Kirby\Exception\InvalidArgumentException
     */
    private function extractFrontmatter(&$mdStr)
    {
        $fields = preg_split('!\n-{4}\n!', $mdStr);
        $n = sizeof($fields)-1;
        $mdStr = $fields[$n];

        // loop through all fields and add them to the content
        for ($i=0; $i<$n; $i++) {
            $field = trim($fields[$i]);
            $pos = strpos($field, ':');
            $key = camelCase(trim(substr($field, 0, $pos)));

            // Don't add fields with empty keys
            if (empty($key) === true) {
                continue;
            }

            $value = trim(substr($field, $pos + 1));

            if ($key === 'variables') {
                $values = Yaml::decode($value);
                foreach ($values as $k => $v) {
                    TransVars::setVariable($k, $v);
                }

            } elseif (str_contains('description,keywords,author', $key)) {
                self::$pg->addHead("  <meta name='$key' content='$value'>\n");

            } elseif ($key === 'robots') {
                self::$pg->addHead("  <meta name='robots' content='noindex,nofollow,noarchive'>\n");

            } elseif ($key === 'wrapperTag') {
                $this->wrapperTag = $value;

            } elseif ($key === 'wrapperClass') {
                $this->wrapperClass = $value;

            } elseif ($key === 'css') {
                // hold back till ".this"/"#this" can be resolved:
                $this->sectionsCss = $value;

            } elseif ($key === 'scss') {
                // hold back till ".this"/"#this" can be resolved:
                $this->sectionsScss = $value;

            } elseif ($key === 'js') {
                self::$pg->addJs($value);

            } elseif ($key === 'jq') {
                self::$pg->addJq($value);

            } elseif ($key === 'assets') {
                $assets = Yaml::decode($value);
                foreach ($assets as $asset) {
                    self::$pg->addAssets($asset);
                }

            } else {
                // unescape escaped dividers within a field
                TransVars::setVariable($key, $value);
            }
        }
    } // extractFrontmatter

} // PageFactory
