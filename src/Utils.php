<?php

namespace Usility\PageFactory;

use Kirby;
use Kirby\Data\Yaml as Yaml;
use Exception;


class Utils
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pg = $pfy->pg;
    }


    /**
     * Handles URL-commands, e.g. ?help, ?print etc.
     * Checks privileges which are required for some commands.
     * @return void
     */
    public function handleSpecialRequests()
    {
        if (!@$_GET) {
            return;
        }
        $this->execAsAnon('login,logout,printpreview,print');
        $this->execAsAdmin('help,localhost,timer,reset,notranslate');
    } // handleSpecialRequests


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
            //$arg = $_GET[$cmd];
            switch ($cmd) {
                case 'login':
                    break;
                case 'logout':
                    break;
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
        $pagedPolyfillScript = PAGED_POLYFILL_SCRIPT;
        $url = './?print';
        $jq = <<<EOT
setTimeout(function() {
    console.log('now running paged.polyfill.js');
    $.getScript( '$pagedPolyfillScript' );
}, 1000);
setTimeout(function() {
    console.log('now adding buttons');
    $('body').append( "<div class='lzy-print-btns'><a href='$url' class='lzy-button' >{{ lzy-print-now }}</a><a href='./' onclick='window.close();' class='lzy-button' >{{ lzy-close }}</a></div>" ).addClass('lzy-print-preview');
}, 1200);

EOT;
        $this->pfy->pg->addJq($jq);
        $this->preparePrintVariables();
    } // printPreview


    /**
     * Renders the current page in print mode and initiates printing
     * @return void
     */
    private function print()
    {
        $pagedPolyfillScript = PAGED_POLYFILL_SCRIPT;
        $jq = <<<EOT
setTimeout(function() {
    console.log('now running paged.polyfill.js'); 
    $.getScript( '$pagedPolyfillScript' );
}, 1000);
setTimeout(function() {
    window.print();
}, 1200);

EOT;
        $this->pfy->pg->addJq($jq);
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
    --lzy-page-title: '$pageTitle';
    --lzy-site-title: '$siteTitle';
    --lzy-url: '$url';
}
EOT;
        $this->pfy->pg->addCss($css);
    } // preparePrintVariables


    /**
     * Execute those URL-commands that require admin privileges: e.g. ?help, ?notranslate etc.
     * @param $cmds
     * @return void
     */
    private function execAsAdmin($cmds)
    {
        if (!isAdminOrLocalhost()) {
            $str = <<<EOT
# Help

You need to be logged in as Admin to use.
EOT;
            $this->pg->setOverlay($str, true);
            return;
        }

        // note: 'debug' handled in PageFactory->__construct() => Utils->determineDebugState()

        foreach (explode(',', $cmds) as $cmd) {
            if (!isset($_GET[$cmd])) {
                continue;
            }
            $arg = $_GET[$cmd];
            switch ($cmd) {
                case 'help':
                    $this->showHelp();
                    break;
                case 'notranslate':
                    $this->noTranslate = $arg? intval($arg): 1;
                    break;
                case 'localhost':
                    if ($arg === 'false') {
                        PageFactory::$localhostOverride = true;
                    }
                    break;
                case 'reset':
                    $this->pfy->session->clear();
                    clearCache();
                    break;

                case 'timer':
                    $this->pfy->session->set('pfy.timer', (bool)$arg);
                    break;
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
# Help

[?help](./?help)       12em>> this information 
[?variables](./?variables)      >> show currently defined variables
[?macros](./?macros)      >> show currently defined macros()
[?lang=](./?lang)      >> activate given language
[?debug](./?debug)      >> activate debug mode
[?localhost=false](./?localhost=false)      >> simulate remote host
[?notranslate](./?notranslate)      >> show variables instead of translating them
[?login](./?login)      >> open login window
[?logout](./?logout)      >> logout user
[?print](./?print)		    	>> starts printing mode and launches the printing dialog
[?printpreview](./?printpreview)  	>> presents the page in print-view mode    
[?timer](./?timer)		    	>> switch timer on or off
[?reset](./?reset)		    	>> resets all state-defining information: caches, tokens, session-vars.

EOT;
                $str = removeCStyleComments($str);
            } else {
                $str = <<<EOT
# Help

You need to be logged in as Admin to see requested information.

EOT;
            }
            $this->pg->setOverlay($str, true);
        }
    } // showHelp



    /**
     * Shows Variables or Macros in Overlay
     * @return void
     */
    public function handleLastMomentSpecialRequests(): void
    {
        if (!@$_GET) {
            return;
        }
        // show variables:
        if (isset($_GET['variables']) && isAdminOrLocalhost()) {
            $str = <<<EOT
<h1>Variables</h1>
{{ list(variables) }}
EOT;
            $str = PageFactory::$trans->translate($str);
            $this->pg->setOverlay($str);

        // show macros:
        } elseif (isset($_GET['macros']) && isAdminOrLocalhost()) {
            $str = <<<EOT
<h1>Macros</h1>
{{ list(macros) }}
EOT;
            $str = PageFactory::$trans->translate($str);
            $this->pg->setOverlay($str);
        }
    } // handleLastMomentSpecialRequests


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
            $wrapperTag = @$this->frontmatter['wrapperTag'];
            if ($wrapperTag === null) {
                $wrapperTag = 'section';
            }
            $this->sectionClass = "lzy-section-$class";
            $this->sectionId = "lzy-section-$inx";
            $this->fixFrontmatterCss();
            if ($wrapperTag) {
                $this->pfy->content .= <<<EOT

<$wrapperTag id='lzy-section-$inx' class='lzy-section-wrapper lzy-section-$inx lzy-section-$class'>

$html

</$wrapperTag>

EOT;
            } else {
                $this->pfy->content .= $html;
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
            $this->pfy->frontmatter = Yaml::decode($frontmatter, 'yaml');

        // extract Kirby-Frontmatter: blocks at top of page, each one ending with '----':
        }
        if (@$this->pfy->config['handleKirbyFrontmatter']) {
            $options = $this->extractKirbyFrontmatter($mdStr);
            if ($options) {
                $this->pfy->frontmatter = array_merge_recursive($this->pfy->frontmatter, $options);
            }
        }

        // if variables were defined in Frontmatter, propagate them into PFY's variables:
        if (@$this->pfy->frontmatter['variables'] && is_array($this->pfy->frontmatter['variables'])) {
            foreach ($this->pfy->frontmatter['variables'] as $varName => $value) {
                PageFactory::$trans->setVariable($varName, $value);
            }
        }

        if (@$this->pfy->frontmatter['overlay']) {
            $this->pg->setOverlay($this->pfy->frontmatter['overlay'], true);
        }
        if (@$this->pfy->frontmatter['message']) {
            $this->pg->setMessage($this->pfy->frontmatter['message'], true);
        }
        if (@$this->pfy->frontmatter['popup']) {
            $this->pg->setPopup($this->pfy->frontmatter['popup'], true);
        }

        if (@$this->pfy->frontmatter['loadAssets']) {
            $this->pg->addAssets($this->pfy->frontmatter['loadAssets'], true);
        }
        if (@$this->pfy->frontmatter['assets']) {
            $this->pg->addAssets($this->pfy->frontmatter['assets'], true);
        }
        if (@$this->pfy->frontmatter['jqFile']) {
            $this->pg->addAssets($this->pfy->frontmatter['jqFile'], true);
        }
        if (@$this->pfy->frontmatter['jqFiles']) {
            $this->pg->addJqFiles($this->pfy->frontmatter['jqFiles'], true);
        }

        // save frontmatter for further use (e.g. by macros):
        $this->pg->set('frontmatter', $this->pfy->frontmatter);

        return $mdStr;
    } // extractFrontmatter


    /**
     * Replaces placeholders '#this' and '.this' with current values.
     * @return void
     */
    private function fixFrontmatterCss(): void
    {
        $css = &$this->pg->frontmatter['css'];
        $css = str_replace('.this', '.'.$this->sectionClass, $css);
        $css = str_replace('#this', '#'.$this->sectionId, $css);

        $scss = &$this->pg->frontmatter['scss'];
        $scss = str_replace('.this', '.'.$this->sectionClass, $scss);
        $scss = str_replace('#this', '#'.$this->sectionId, $scss);
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
        $lang = false;
        $languagesObj = kirby()->languages();
        $supportedLanguages = $this->pfy->supportedLanguages = $languagesObj->codes();
        if ($supportedLanguages) {
            $lang = kirby()->language()->code();
            //ToDo: how to take user's language preference into account?
            //            $lang = $this->kirby->visitor()->acceptedLanguage()->code(); // lagunage prefered by user
            //            if (!in_array($lang, $supportedLanguages)) {
            //                $lang = (string)kirby()->defaultLanguage();
            //            }
        } else {
            $lang = 'en';
            $supportedLanguages[] = 'en';
        }

        if ($lang) {
            PageFactory::$lang = $lang;
            PageFactory::$langCode = $langCode = substr($lang, 0, 2);
            if (!in_array($langCode, $supportedLanguages)) {
                throw new Exception("Error: language not defined");
            }
        } else {
            throw new Exception("Error: language not defined");
        }

        // check whether user requested a language explicitly via url-arg:
        if ($lang = @$_GET['lang']) {
            if (in_array($langCode, $supportedLanguages)) {
                PageFactory::$lang = $lang;
                PageFactory::$langCode = substr($lang, 0, 2);
                $slug = $this->pfy->slug;
                $slug = $lang . substr($slug,2);
                $appRoot = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
                $url = $appRoot.$slug;
                reloadAgent($url);
            }
        }
    } // determineLanguage



    /**
     * Defines variable 'lzy-lang-selection', which expands to a language selection block,
     * one language icon per supported language
     */
    public function setLanguageSelector():void
    {
        $out = '';
        if (sizeof($this->pfy->supportedLanguages) > 1) {
            foreach ($this->pfy->supportedLanguages as $lang) {
                $langCode = substr($lang, 0, 2);
                if ($lang === PageFactory::$lang) {
                    $out .= "<span class='lzy-lang-elem lzy-active-lang $langCode'><span>{{^ lzy-lang-select-$langCode }}</span></span> ";
                } else {
                    $out .= "<span class='lzy-lang-elem $langCode'><a href='?lang=$lang' title='{{ lzy-lang-select-title-$langCode }}'>{{^ lzy-lang-select-$langCode }}</a></span> ";
                }
            }
            $out = "\t<span class='lzy-lang-selection'>$out</span>\n";
        }

        PageFactory::$trans->setVariable('lzy-lang-selection', $out);
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
        $debug = false;
        $kirbyDebugState = @(kirby()->options())['debug'];
        if ($kirbyDebugState !== null) {
            $debug = $kirbyDebugState;

        }
        if (isAdminOrLocalhost()) {
            $debug = @$_GET['debug'];
            if (($debug === null) && $this->pfy->session->get('pfy.debug')) {
                $debug = true;
            } elseif ($debug === 'false') {
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
        $content = $this->pfy->mdContent;
        if ($this->pfy->mdContent) {
            $content = $this->pfy->kirby->markdown($this->pfy->mdContent);
        }
        $content = $this->pfy->content . $content;

        // remove pseudo tags <skip>:
        $content = preg_replace('|<skip.*?</skip>|ms', '', $content);

        // recover elements shielded from md-compilation:
        while (unshieldStr($content)) {};
        return $content;
    } // getContent



    /**
     * Obtains PageFactory's own config file 'site/config/pagefactory.yaml'.
     * Also lead important values from 'site/site.txt'.
     */
    public function loadPfyConfig():void
    {
        if (file_exists(PFY_CONFIG_FILE)) {
            $this->pfy->config = include(PFY_CONFIG_FILE);
        } else {
            $this->pfy->config = [];
        }

        // propagate variables from config into TransVars:
        if (isset($this->pfy->config['variables'])) {
            PageFactory::$trans->setVariables($this->pfy->config['variables']);
        }

        // add values from site/site.txt:
        if ($s = site()->title()->value()) {
            $this->pfy->config['title'] = $s;
        }
        if ($s = site()->text()->value()) {
            $this->pfy->config['text'] = $s;
        }
        if ($s = site()->author()->value()) {
            $this->pfy->config['author'] = $s;
        }
        if ($s = site()->description()->value()) {
            $this->pfy->config['description'] = $s;
        }
        if ($s = site()->keywords()->value()) {
            $this->pfy->config['keywords'] = $s;
        }
    } // loadPfyConfig



    /**
     * Determines the page template file to be used:
     *  a) explicitly stated
     *  b) html file in 'site/templates/' with name corresponding to .txt file in page folder
     *  c) html file in 'site/templates/' with name corresponding to page's dir-name
     *  d) default template 'site/templates/page_template.html'
     * @param string $templateFile
     */
    public function determineTemplateFile(string $templateFile):void
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
            $systemTimeZone = @$this->pfy->config['timezone'];
            if (!$systemTimeZone) {
                $systemTimeZone = $this->getServerTimezone();
                $this->appendToConfigFile('timezone', $systemTimeZone, 'Automatically set by PageFactory');
            }
            \Kirby\Toolkit\Locale::set($systemTimeZone);
        }
        return $systemTimeZone;
    } // setTimezone



    /**
     * Injects a new key,value pair into PageFactory's config file site/config/pagefactory.php
     * @param string $key
     * @param string $value
     * @param string|null $comment
     */
    private function appendToConfigFile(string $key, string $value, ?string $comment = ''): void
    {
        if ($comment) {
            $comment = " // $comment";
        }
        $str = "\t,'$key' => '$value',$comment\n";

        $config = @file_get_contents(PFY_CONFIG_FILE);
        if ($config) {
           $config = str_replace('];', "$str];", $config);
        } else {
            $config = <<<EOT
<?php
// Configuration file for PageFactory plugin

return [
$str];

EOT;
        }
        file_put_contents(PFY_CONFIG_FILE, $config);
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