<?php

namespace Usility\PageFactory;

use Kirby;
use Kirby\Data\Yaml as Yaml;
use Exception;



class Utils
{
    private $mdContent;
    
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
    }


    /**
     * Entry point for handling UrlTokens, in particular for access-code-login:
     * @return void
     */
    public function handleUrlToken()
    {
        $urlToken = PageFactory::$urlToken;
        if (!$urlToken) {
            return;
        }

        // do something with $urlToken...

        // remove the urlToken:
        $target = page()->url();
        $target .= '/' . PageFactory::$slug;
        reloadAgent($target);
    } // handleUrlToken


    /**
     * Handles URL-commands, e.g. ?help, ?print etc.
     * Checks privileges which are required for some commands.
     * @return void
     */
    public function handleAgentRequests()
    {
        if (!($_GET??false)) {
            return;
        }
        $this->execAsAnon('printview,printpreview,print');
        $this->execAsAdmin('help,localhost,timer,reset,notranslate');
    } // handleAgentRequests


    /**
     * Execute those URL-commands that require no privileges: e.g. ?login, ?printpreview etc.
     * @param $cmds
     * @return void
     */
    private function execAsAnon($cmds)
    {
        foreach (explode(',', $cmds) as $cmd) {
            if (!isset($_GET[$cmd])) {
                continue;
            }
            switch ($cmd) {
                case 'printview':
                case 'printpreview':
                    $this->printPreview();
                    break;
                case 'print':
                    $this->print();
                    break;
            }
        }
    } // execAsAnon


    /**
     * Renders the current page in print-preview mode.
     * @return void
     */
    private function printPreview()
    {
        $pagedPolyfillScript = PageFactory::$appUrl.PAGED_POLYFILL_SCRIPT_URL;
        $jq = <<<EOT
setTimeout(function() {
    console.log('now running paged.polyfill.js');
    $.getScript( '$pagedPolyfillScript' );
}, 1000);
setTimeout(function() {
    console.log('now adding buttons');
    $('body').append( "<div class='pfy-print-btns'><a href='./?print' class='pfy-button' >{{ pfy-print-now }}</a><a href='./' class='pfy-button' >{{ pfy-close }}</a></div>" ).addClass('pfy-print-preview');
}, 1200);

EOT;
        PageFactory::$pg->addJq($jq);
        $this->preparePrintVariables();
    } // printPreview


    /**
     * Renders the current page in print mode and initiates printing
     * @return void
     */
    private function print()
    {
        $pagedPolyfillScript = PageFactory::$appUrl.PAGED_POLYFILL_SCRIPT_URL;
        $jq = <<<EOT
setTimeout(function() {
    console.log('now running paged.polyfill.js'); 
    $.getScript( '$pagedPolyfillScript' );
}, 1000);
setTimeout(function() {
    window.print();
}, 1200);

EOT;
        PageFactory::$pg->addJq($jq);
        $this->preparePrintVariables();
    } // print


    /**
     * Helper for printPreview() and print():
     * -> prepares default header and footer elements in printing layout.
     * @return void
     */
    private function preparePrintVariables()
    {
        // prepare css-variables:
        $url = (string) page()->url().'/';
        $pageTitle = (string) page()->title();
        $siteTitle = (string) site()->title();
        $css = <<<EOT
body {
    --pfy-page-title: '$pageTitle';
    --pfy-site-title: '$siteTitle';
    --pfy-url: '$url';
}
EOT;
        PageFactory::$pg->addCss($css);
    } // preparePrintVariables


    /**
     * Execute those URL-commands that require admin privileges: e.g. ?help, ?notranslate etc.
     * @param $cmds
     * @return void
     */
    private function execAsAdmin($cmds)
    {
        // note: 'debug' handled in PageFactory->__construct() => Utils->determineDebugState()

        foreach (explode(',', $cmds) as $cmd) {
            if (!isset($_GET[$cmd])) {
                continue;
            }
            if (!isAdminOrLocalhost()) {
                $str = <<<EOT
# Help

You need to be logged in as Admin to use system commands.
EOT;
                PageFactory::$pg->setOverlay($str, true);
                return;
            }
            $arg = $_GET[$cmd];
            switch ($cmd) {
                case 'help': // ?help
                    $this->showHelp();
                    break;

                case 'notranslate': // ?notranslate
                    TransVars::$noTranslate = $arg? intval($arg): 1;
                    break;

                case 'reset': // ?reset
                    Assets::reset();
                    $this->pfy->session->clear();
                    clearCache();
                    reloadAgent();
            }
        }
    } // execAsAdmin


    /**
     * Handles ?help request
     * @return void
     */
    private function showHelp()
    {
        if (isset($_GET['help'])) {
            if (isAdminOrLocalhost()) {
                $str = <<<EOT
@@@ .pfy-general-help
# Help

[?help](./?help)       12em>> this information 
[?variables](./?variables)      >> show currently defined variables
[?macros](./?macros)      >> show currently defined macros()
[?lang=](./?lang)      >> activate given language
[?debug](./?debug)      >> activate debug mode
[?localhost=false](./?localhost=false)      >> mimicks running on a remote host (for testing)
[?notranslate](./?notranslate)      >> show variables instead of translating them
 // for later:
 //[?login](./?login)      >> open login window
 //[?logout](./?logout)      >> logout user
[?print](./?print)		    	>> starts printing mode and launches the printing dialog
[?printpreview](./?printpreview)  	>> presents the page in print-view mode    
[?reset](./?reset)		    	>> resets all state-defining information: caches, tokens, session-vars.

@@@
EOT;
                $str = removeCStyleComments($str);
            } else {
                $str = <<<EOT
# Help

You need to be logged in as Admin to see requested information.

EOT;
            }
            PageFactory::$pg->setOverlay($str);
        }
    } // showHelp


    /**
     * Shows Variables or Macros in Overlay
     * @return void
     */
    public function handleAgentRequestsOnRenderedPage(): void
    {
        if (!($_GET ?? false)) {
            return;
        }
        // show variables:
        if (isset($_GET['variables']) && isAdminOrLocalhost()) {
            $str = <<<EOT
<h1>Variables</h1>
{{ list(variables) }}
EOT;
            $str = PageFactory::$trans->translate($str);
            PageFactory::$pg->setOverlay($str, false);

        // show macros:
        } elseif (isset($_GET['macros']) && isAdminOrLocalhost()) {
            $str = <<<EOT
<h1>Macros</h1>
{{ list(macros) }}
EOT;
            $str = PageFactory::$trans->translate($str);
            PageFactory::$pg->setOverlay($str);
        }
    } // handleAgentRequestsOnRenderedPage


    /**
     * Loads .md files found inside the current page folder
     * Files ignored:
     *      a) files starting with '#' (-> aka commented out)
     *      b) files starting with '-' (-> reserved for explicit loading)
     *      c) files ending with '.XX.md', where XX is a language code and doesn't match the currently active language
     */
    public function loadMdFiles(): void
    {
        $files = $this->findMdFiles();
        $inx = 1;
        $currLang = PageFactory::$langCode;
        foreach ($files as $fileObj) {
            $file = $fileObj->root();
            if (preg_match('/\.(\w\w\d?)\.\w{2,5}$/', $file, $m)) {
                if ($m[1] !== $currLang) {
                    continue;
                }
            }
            $mdStr = loadFile($file, 'cStyle');
            $mdStr = $this->extractFrontmatter($mdStr);
            $html = PageFactory::$md->compile($mdStr);
            $class = translateToClassName(basename($fileObj->id(), '.md'));
            $wrapperTag = Page::$frontmatter['wrapperTag']??null;
            if ($wrapperTag === null) {
                $wrapperTag = 'section';
            }
            $this->sectionClass = "pfy-section-$class";
            $this->sectionId = "pfy-section-$inx";
            $this->fixFrontmatterCss();
            if ($wrapperTag) {
             Page::$content .= <<<EOT

<$wrapperTag id='pfy-section-$inx' class='pfy-section-wrapper pfy-section-$inx pfy-section-$class'>

$html

</$wrapperTag>

EOT;
            } else {
                Page::$content .= $html;
            }
            $inx++;
        }
    } // loadMdFiles


    /**
     * Attempts to extract 'frontmatter'-info from page files, both .txt and .md
     * @param string $mdStr
     * @return string
     * @throws Kirby\Exception\InvalidArgumentException
     */
    public function extractFrontmatter(string $mdStr):string
    {
        // extract MD-Frontmatter: block at top of page starting and ending with '---':
        if (strpos($mdStr, '---') === 0) {
            $p = strpos($mdStr, "\n");
            $marker = substr($mdStr, 0, $p);
            $mdStr = substr($mdStr, $p+1);
            $p = strpos($mdStr, $marker);
            $frontmatter = substr($mdStr, 0, $p);
            $p = strpos($mdStr, "\n", $p);
            $mdStr = substr($mdStr, $p+1);
            $frontmatter = Yaml::decode($frontmatter, 'yaml');
            $this->addToFrontmatter($frontmatter);
        }

        // extract Kirby-Frontmatter: blocks at top of page, each one ending with '----':
        if (PageFactory::$config['handleKirbyFrontmatter']??false) {
            $options = $this->extractKirbyFrontmatter($mdStr);
            if ($options) {
                $this->addToFrontmatter($options);
            }
        }

        // if variables were defined in Frontmatter, propagate them into PFY's variables:
        if ((Page::$frontmatter['variables']??false) && is_array(Page::$frontmatter['variables'])) {
            foreach (Page::$frontmatter['variables'] as $varName => $value) {
                $array[$varName] = $value;
                PageFactory::$trans->setVariable($varName, $array);
            }
        }

        if ((Page::$frontmatter['template']??false)) {
            $this->pfy->templateFile = 'site/templates/'.basename(Page::$frontmatter['template']);
        }

        // if PageElements extension is loaded -> handle overlay,popup,message:
        if (in_array('pageelements', array_keys(PageFactory::$availableExtensions))) {
            if (Page::$frontmatter['overlay']??false) {
                $pe = new \Usility\PageFactoryElements\Overlay($this->pfy);
                $pe->set(Page::$frontmatter['overlay'], true);
            }
            if (Page::$frontmatter['message']??false) {
                $pe = new \Usility\PageFactoryElements\Message($this->pfy);
                $pe->set(Page::$frontmatter['message'], true);
            }
            if (Page::$frontmatter['popup']??false) {
                $pe = new \Usility\PageFactoryElements\Popup($this->pfy);
                $pe->set(Page::$frontmatter['popup'], true);
            }
        }

        if (Page::$frontmatter['loadAssets']??false) {
            PageFactory::$assets->addAssets(Page::$frontmatter['loadAssets']);
        }
        if (Page::$frontmatter['assets']??false) {
            PageFactory::$assets->addAssets(Page::$frontmatter['assets']);
        }
        if (Page::$frontmatter['jqFile']??false) {
            PageFactory::$assets->addAssets(Page::$frontmatter['jqFile'], true);
        }
        if (Page::$frontmatter['jqFiles']??false) {
            PageFactory::$assets->addJqFiles(Page::$frontmatter['jqFiles']);
        }

        // save frontmatter for further use (e.g. by macros):

        return $mdStr;
    } // extractFrontmatter


    /**
     * Adds new frontmatter values to PageExtruder::$frontmatter
     * @param array $frontmatter
     */
    private function addToFrontmatter(array $frontmatter): void
    {
        if (!Page::$frontmatter) {
            Page::$frontmatter = $frontmatter;
        } else {
            foreach ($frontmatter as $key => $value) {
                if ((Page::$frontmatter[$key]??false) && is_string($value)) {
                    Page::$frontmatter[$key] .= $value;

                } elseif ((Page::$frontmatter[$key]??false) && is_array($value)) {
                    Page::$frontmatter[$key] = array_merge(Page::$frontmatter[$key], $value);
                } else {
                    Page::$frontmatter[$key] = $value;
                }
            }
        }
    } // addToFrontmatter


    /**
     * Replaces placeholders '#this' and '.this' with current values.
     * @return void
     */
    private function fixFrontmatterCss(): void
    {
        $css = &Page::$frontmatter['css'];
        if ($css) {
            $css = str_replace(['#this', '.this'], ['#'.$this->sectionId, '.'.$this->sectionClass], $css);
        }

        $scss = &Page::$frontmatter['scss'];
        if ($scss) {
            $scss = str_replace(['#this', '.this'], ['#'.$this->sectionId, '.'.$this->sectionClass], $scss);
        }
    } // fixFrontmatterCss


    /**
     * Attempts to extract 'frontmatter'-info from current page's .txt file
     *  Note: if there happen to be multiple, that last one is picked, same as Kirby does
     * @param string $string
     * @return array
     */
    public function extractKirbyFrontmatter(&$string): array
    {
        if ($string === null || $string === '') {
            return [];
        }
        if (!preg_match('/\n----/ms', $string)) {
            return [];
        }

        $p = strrpos($string, "\n----");
        $frontmatter = substr($string, 0, $p);
        $string = substr($string, $p+5);

        // explode all fields by the line separator
        $fields = preg_split('!\n----\s*\n*!', $frontmatter);
        $data = [];

        // loop through all fields and add them to the content
        foreach ($fields as $field) {
            $pos = strpos($field, ':');
            $key = str_replace(['-', ' '], '_', strtolower(trim(substr($field, 0, $pos))));

            // Don't add fields with empty keys
            if (empty($key) === true) {
                continue;
            }

            $value = trim(substr($field, $pos + 1));

            // unescape escaped dividers within a field
            $data[$key] = preg_replace('!(?<=\n|^)\\\\----!', '----', $value);
        }

        return $data;
    } // extractKirbyFrontmatter


    /**
     * Resolves path patterns of type '~x/' to correct urls
     * @param string $html
     * @return string
     */
    public function resolveUrl(string $url): string
    {
        $patterns = [
            '~/'        => '',
            '~media/'   => 'media/',
            '~assets/'  => 'content/assets/',
            '~data/'    => 'site/custom/data/',
            '~page/'    => PageFactory::$pagePath,
        ];
        $url = str_replace(array_keys($patterns), array_values($patterns), $url);
        $url = normalizePath($url);
        return $url;
    } // resolveUrl


    /**
     * Resolves path patterns of type '~x/' to correct urls
     * @param string $html
     * @return string
     */
    public function resolveUrls(string $html): string
    {
        $patterns = [
            '~/'        => PageFactory::$appUrl,
            '~media/'   => PageFactory::$appRootUrl.'media/',
            '~assets/'  => PageFactory::$appRootUrl.'content/assets/',
            '~data/'    => PageFactory::$appRootUrl.'site/custom/data/',
            '~page/'    => PageFactory::$pageUrl,
        ];
        $html = str_replace(array_keys($patterns), array_values($patterns), $html);
        return $html;
    } // resolveUrls


    /**
     * Removes any remaining '\' in front of '{' and '~'  from the HTML output
     * @param string $html
     * @return string
     */
    public function unshield(string $html):string
    {
        return str_replace(['\\{', '\\~'], ['{', '~'], $html);
    }


    /**
     * Variables marked by '{{@' must be resolved at the very end, this method hides them from translation
     * @param string $html
     * @return string
     */
    public function shieldLateTranslatationVariables(string $html):string
    {
        if (preg_match_all('/{{@ (.*?) }}/xms', $html, $m)) {
            foreach ($m[1] as $i => $item) {
                $html = str_replace($m[0][$i], "{@@{{$item}}@@}", $html);
            }
        }
        return $html;
    } // shieldLateTranslatationVariables


    /**
     * Variables originally marked by '{{@' are unshielded and left as '{{', so they can be translated now 
     * @param string $html
     * @return string
     */
    public function unshieldLateTranslatationVariables(string $html):string
    {
        if (preg_match_all('/{@@{ (.*?) }@@}/xms', $html, $m)) {
            foreach ($m[1] as $i => $item) {
                $html = str_replace($m[0][$i], "{{{$item}}}", $html);
            }
        }
        return $html;
    } // unshieldLateTranslatationVariables


    /**
     * Determines the currently active language. Consults Kirby's own mechanism, 
     * then checks for URL-arg "?lang=XX", which overrides previously set language
     */
    public function determineLanguage(): void
    {
        $supportedLanguages = $this->pfy->supportedLanguages = kirby()->languages()->codes();
        if (!$supportedLanguages) {
            if ($langObj = kirby()->language()) {
                $lang = $langObj->code();
            } elseif (!($lang = kirby()->defaultLanguage())) {
                $lang = 'en';
            }
            PageFactory::$lang = $lang;
            PageFactory::$langCode = substr($lang, 0, 2);
            return;
        }

        if (!($lang = kirby()->session()->get('pfy.lang'))) {
            $lang = kirby()->defaultLanguage()->code();
        }

        if ($lang) {
            $langCode = substr($lang, 0, 2);
            if (!in_array($lang, $supportedLanguages) && !in_array($langCode, $supportedLanguages)) {
                $lang = $langCode = kirby()->defaultLanguage()->code();
            }
            PageFactory::$defaultLanguage = kirby()->defaultLanguage()->code();
            PageFactory::$lang = $lang;
            PageFactory::$langCode = $langCode;
        } else {
            throw new Exception("Error: language not defined");
        }

        // check whether user requested a language explicitly via url-arg:
        if (isset($_GET['lang'])) {
            $lang = $_GET['lang'];
            if (!$lang) {
                $lang = PageFactory::$defaultLanguage;
            }
            $langCode = substr($lang, 0, 2);
            if (in_array($lang, $supportedLanguages) || in_array($langCode, $supportedLanguages)) {
                PageFactory::$lang = $lang;
                PageFactory::$langCode = $langCode;
                kirby()->session()->set('pfy.lang', $lang);
                kirby()->setCurrentLanguage($langCode);
                $url = $this->pfy->page->url();
                reloadAgent($url);
            }
        }
    } // determineLanguage


    /**
     * Defines variable 'pfy-lang-selection', which expands to a language selection block,
     * one language icon per supported language
     */
    public function setLanguageSelector():void
    {
        $out = '';
        if (sizeof($this->pfy->supportedLanguages) > 1) {
            foreach ($this->pfy->supportedLanguages as $lang) {
                $langCode = substr($lang, 0, 2);
                if ($lang === PageFactory::$lang) {
                    $out .= "<span class='pfy-lang-elem pfy-active-lang $langCode'><span>{{^ pfy-lang-select-$langCode }}</span></span> ";
                } else {
                    $out .= "<span class='pfy-lang-elem $langCode'><a href='?lang=$lang' title='{{ pfy-lang-select-title-$langCode }}'>{{^ pfy-lang-select-$langCode }}</a></span> ";
                }
            }
            $out = "\t<span class='pfy-lang-selection'>$out</span>\n";
        }

        PageFactory::$trans->setVariable('pfy-lang-selection', $out);
    } // setLanguageSelector


    /**
     * Determines the current debug state
     * Note: PageFactory maintains its own "debug" state, which diverges slightly from Kirby's.
     * enter debug state, if:
     * - on localhost:
     *      - true, unless $userDebugRequest or $kirbyDebugState explicitly false
     * - on productive host:
     *      - false unless
     *          - $kirbyDebugState explicitly true
     *          - logged in as admin and $userDebugRequest true -> remember as long as logged in
     */
    public function determineDebugState():void
    {
        $debug = kirby()->option('debug');
        if (isAdminOrLocalhost()) {
            $debug = $_GET['debug']??false;
            if (($debug === null) && $this->pfy->session->get('pfy.debug')) {
                $debug = true;
            } elseif ($debug === 'false' || $debug === null) {
                $debug = false;
                $this->pfy->session->remove('pfy.debug');
            } else {
                $debug = ($debug !== 'false');
            }
            if ($debug) {
                $this->pfy->session->set('pfy.debug', $debug);
            }
        }
        PageFactory::$debug = $debug;
    } // determineDebugState


    /**
     * Finds .md files found inside the current page folder
     * Files ignored:
     *      a) files starting with '#' (-> aka commented out)
     *      b) files starting with '-' (-> reserved for explicit loading)
     * @return object
     */
    public function findMdFiles():object
    {
        $mdFiles = $this->pfy->page->files()->filterBy('extension', 'md');
        $mdFiles = $mdFiles->filter(function($file) {
            $c1 = $file->filename()[0];
            return (($c1 !== '#') && ($c1 !== '-'));
        });
        return $mdFiles;
    } // findMdFiles


    /**
     * Returns the page content as HTML
     * To get that, obtains markdown (previously assembled) and compiles it to HTML
     * Removes pseudo tags <skip> which may have been injected to exclude certain content, e.g. of inactive languages
     * Recover elements shielded from md-compilation
     * @return string
     */
    public function getContent(): string
    {
        if ($content = PageFactory::$pg->overrideContent) {
            return $content;
        }

        $content = $this->mdContent;
        if ($this->mdContent) {
            $content = $this->pfy->kirby->markdown($this->mdContent);
        }
        $content = Page::$content . $content;

        // remove pseudo tags <skip>:
        $content = preg_replace('|<skip.*?</skip>|ms', '', $content);

        // recover elements shielded from md-compilation:
        while (_unshieldStr($content)) {};
        return $content;
    } // getContent


    public function loadPfyConfig():void
    {
        $optionsFromConfigFile = kirby()->option('usility.pagefactory.options');
        if ($optionsFromConfigFile) {
            PageFactory::$config = array_replace_recursive(OPTIONS_DEFAULTS, $optionsFromConfigFile);
        } else {
            PageFactory::$config = OPTIONS_DEFAULTS;
        }

        // propagate variables from config into TransVars:
        if (isset(PageFactory::$config['variables'])) {
            PageFactory::$trans->setVariables(PageFactory::$config['variables']);
        }

        // add values from site/site.txt:
        $site = site();
        if ($s = $site->title()->value()) {
            PageFactory::$config['title'] = $s;
        }
        if ($s = $site->text()->value()) {
            PageFactory::$config['text'] = $s;
        }
        if ($s = $site->author()->value()) {
            PageFactory::$config['author'] = $s;
        }
        if ($s = $site->description()->value()) {
            PageFactory::$config['description'] = $s;
        }
        if ($s = $site->keywords()->value()) {
            PageFactory::$config['keywords'] = $s;
        }
    } // loadPfyConfig


    /**
     * Determines the page template file to be used:
     *  a) explicitly stated
     *  b) html file in 'site/templates/' with name corresponding to .txt file in page folder
     *  c) html file in 'site/templates/' with name corresponding to page's dir-name
     *  d) default template 'site/templates/page_template.html'
     * @param mixed $templateFile
     */
    public function determineTemplateFile($templateFile = false): void
    {
        $templatePath = dir_name(PFY_DEFAULT_TEMPLATE_FILE);
        if (!$templateFile) {
            $intendedTemplate = $this->pfy->page->content()->parent()->intendedTemplate()->name();
            if ($intendedTemplate) {
                $templateFile = "$templatePath$intendedTemplate.html";
            }
        }
        if (!file_exists($templateFile)) {
            $templateFile = $templatePath.$templateFile;
            if (!file_exists($templateFile)) {
                $templateFile = $templatePath . $this->pfy->page->dirname() . '.html';
                if (!file_exists($templateFile)) {
                    $templateFile = PFY_DEFAULT_TEMPLATE_FILE;
                }
            }
        }
        $this->pfy->templateFile = $templateFile;
    } // determineTemplateFile


    /**
     * Checks timezone. If that's not in "area/city" format, tries to obtain it from PageFactory's config file.
     * If that fails, tries to determine it via https://ipapi.co/timezone, then saves in the config file.
     * (thus avoiding subsequent calls to https://ipapi.co/timezone)
     * @return string
     */
    public function setTimezone():string
    {
        // check whether timezone is properly set (e.g. "UTC" is not sufficient):
        $systemTimeZone = date_default_timezone_get();
        if (!preg_match('|\w+/\w+|', $systemTimeZone)) {
            // check whether timezone is defined in PageFactory's config settings:
            $systemTimeZone = PageFactory::$config['timezone']??false;
            if (!$systemTimeZone) {
                $systemTimeZone = $this->getServerTimezone();
                $this->appendToConfigFile('timezone', $systemTimeZone, 'Automatically set by PageFactory');
            }
            \Kirby\Toolkit\Locale::set($systemTimeZone);
        }
        return $systemTimeZone;
    } // setTimezone


    /**
     * Injects a new key,value pair into PageFactory's config file site/config/config.php
     * @param string $key
     * @param string $value
     * @param string|null $comment
     */
    private function appendToConfigFile(string $key, string $value, ?string $comment = ''): void
    {
        if ($comment) {
            $comment = " // $comment";
        }

        $config = (string)fileGetContents(PFY_CONFIG_FILE);

        // check whether section pagefactory already exists, then inject values accordingly:
        if (preg_match("/(['\"]usility.pagefactory.options['\"]\s*=>\s*\[)/", $config, $m)) {
            $str = "\n\t\t\t'$key'\t\t=> '$value',$comment,";
            $config = str_replace($m[0], $m[0].$str, $config);
            file_put_contents(PFY_CONFIG_FILE, $config);

        } elseif (preg_match("/(];)/", $config, $m)) {
            $str = <<<EOT

    'usility.pagefactory.options' => [
        '$key'		=> '$value',$comment
    ],

EOT;
            $config = str_replace($m[0], $str.$m[0], $config);
            file_put_contents(PFY_CONFIG_FILE, $config);
        }
    } // appendToConfigFile


    /**
     * Obtains the host's timezone from https://ipapi.co/timezone
     * @return string
     */
    public function getServerTimezone():string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://ipapi.co/timezone");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    } // getServerTimezone

} // Utils
