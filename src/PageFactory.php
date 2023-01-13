<?php

namespace Usility\PageFactory;

use Kirby;
use Kirby\Data\Yaml;

 // filesystem paths:
const PFY_BASE_PATH =              'site/plugins/pagefactory/';
const PFY_CONTENT_ASSETS_PATH =    'content/assets/';
const PFY_ASSETS_PATH =            'site/plugins/pagefactory/assets/';
const PFY_ICONS_PATH =             'site/plugins/pagefactory/assets/icons/';
const PFY_SVG_ICONS_PATH =         'site/plugins/pagefactory/assets/svg-icons/';
const PFY_CONFIG_FILE =            'site/config/config.php';
const PFY_CUSTOM_PATH =            'site/custom/';
const PFY_USER_CODE_PATH =         PFY_CUSTOM_PATH.'macros/';
const PFY_MACROS_PATH =            PFY_BASE_PATH.'macros/';
define('PFY_LOGS_PATH',            'site/logs/');
define('PFY_CACHE_PATH',           'site/cache/pagefactory/'); // available in extensions
const PFY_MKDIR_MASK =             0700; // permissions for file accesses by PageFactory

 // URLs:
const PFY_BASE_ASSETS_URL =        'media/plugins/usility/';
const PFY_ASSETS_URL =             PFY_BASE_ASSETS_URL.'pagefactory/';
const PAGED_POLYFILL_SCRIPT_URL =  PFY_ASSETS_URL.'js/paged.polyfill.min.js';

const DEFAULT_FRONTEND_FRAMEWORK_URLS = ['js' => PFY_ASSETS_URL.'js/jquery-3.6.1.min.js'];

 // use this name for meta-files (aka text-files) in page folders:
define('PFY_PAGE_DEF_BASENAME',     'z_pfy'); // 'define' required by site/plugins/pagefactory/index.php

define('OPTIONS_DEFAULTS', [
    'screenSizeBreakpoint'          => 480,
    'defaultLanguage'               => 'en',
    'allowNonPfyPages'              => false,  // -> if true, Pagefactory will skip checks for presence of metafiles
    'externalLinksToNewWindow'      => true,   // -> used by Link() -> whether to open external links in new window
    'imageAutoQuickview'            => true,  // -> used by Img() macro
    'imageAutoSrcset'               => true,  // -> used by Img() macro
    'divblock-chars'                => '@%',  // possible alternative: ':$@%'
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
    public $session;


    public function __construct($data)
    {
        self::$timer = microtime(true);
        self::$isLocalhost = isLocalhost();

        self::$kirby = $data['kirby'];
        self::$pages = $data['pages'];
        self::$page = $data['page'];
        self::$site = $data['site'];
        self::$siteFiles = self::$pages->files();
        $this->session = self::$kirby->session();
        self::$phpSessionId = getSessionId();

        $this->pageOptions = self::$page->content()->data();

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
        $this->utils->determineLanguage();
        self::$trans = new TwigVars();

        self::$assets = new Assets($this);
        self::$pg = new Page($this);
        self::$pg->set('pageParams', self::$page->content()->data());
        self::$pg->loadExtensions();

        $this->utils->init();
        $this->utils->determineDebugState();

        $this->utils->setTimezone();

        self::$pagePath = substr(self::$page->root(), strlen(site()->root())+1) . '/';
        self::$absAppRoot = kirby()->root().'/';
        self::$absPfyRoot = __DIR__.'/';
        self::$appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appUrl = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
        self::$appRootUrl = kirby()->url().'/';
        self::$slug = page()->slug();
        self::$pageId = page()->id();
        self::$pageRoot = 'content/' . self::$pagePath;
        self::$absPageRoot = self::$page->root() . '/';
        self::$absPageUrl = (string)self::$page->url() . '/';
        self::$pageUrl = substr(self::$absPageUrl, strlen(self::$hostUrl)-1);
        if ($user = self::$kirby->user()) {
            self::$user = (string)$user->name();
        }

        $this->utils->handleUrlToken();

        preparePath(PFY_LOGS_PATH);
        $this->utils->showPendingMessage();
        $this->utils->handleAgentRequests();
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
     */
    public function renderPageContent(): string
    {
        self::$pg->extensionsFinalCode(); //??? best position?
        self::$trans->prepareStandardVariables();

        $this->utils->handleAgentRequestsOnRenderedPage();

        // get and compile meta-file's text field:
        $mdStr = self::$page->text()->value()."\n\n";
        $html = $this->compile($mdStr);

        // load content from .md files:
        $html .= $this->loadMdFiles();

        // finalize:
        if ($html) {
            self::$trans->lastPreparations();

            // resolve (nested) variables:
            $cnt = 3;
            while ($cnt-- && str_contains($html, '{{')) {
                $html = twig($html, TwigVars::$variables);
            }
            $html = str_replace('{!!{', '{{', $html);
        }
        self::$trans->prepareTemplateVariables();

        return $html;
    } // renderPageContent


    private function loadMdFiles()
    {
        $path = self::$page->root();
        $dir = getDir("$path/*.md");
        $inx = 1;
        $finalHtml = '';
        foreach ($dir as $file) {
            if (str_contains('#-_', basename($file)[0])) {
                continue;
            }
            $mdStr = getFile($file);
            $this->extractFrontmatter($mdStr);
            $html = $this->compile($mdStr);
            $finalHtml .= <<<EOT

<section id='pfy-section-$inx' class='pfy-section.pfy-section-$inx'>

$html

</section>

EOT;
        }

        return $finalHtml;
    } // loadMdFiles


    private function compile(string $mdStr, $inx = 0): string
    {
        if (!$mdStr = removeComments($mdStr)) {
            return '';
        }

        $mdStr = self::$trans->resolveVariables($mdStr);
        $mdStr = twig($mdStr);
        $mdp = new \Usility\MarkdownPlus\MarkdownPlus();
        $html =  $mdp->compile($mdStr, sectionIdentifier: "pfy-section-$inx");

        // shield argument lists enclosed in '({' and '})'
        if (preg_match_all('/\(\{ (.*?) }\)/x', $html, $m)) {
            foreach ($m[1] as $i => $pattern) {
                $str = shieldStr($pattern);
                $html = str_replace($m[0][$i], "('$str')", $html);
            }
        }

        $html = str_replace('\\{{', '{!!{', $html);
        $html = str_replace('\\(', '⟮', $html);

        // add '|raw' to simple variables:
        if (preg_match_all('/\{\{ ( [^}|(]+ ) }}/msx', $html, $m)) {
            foreach ($m[1] as $i => $pattern) {
                $str = "$pattern|raw";
                $html = str_replace($m[0][$i], "{{ $str }}", $html);
            }
        }

        return $html;
    } // compile


    private function extractFrontmatter(&$mdStr)
    {
        $fields = preg_split('!\n----\s*\n*!', $mdStr);
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
                    self::$trans->setVariable($k, $v);
                }

            } else {
                // unescape escaped dividers within a field
                self::$trans->setVariable($key, $value);
            }
        }
    } // extractFrontmatter
} // PageFactory
