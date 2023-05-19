<?php

namespace Usility\PageFactory;

use Kirby;
use Exception;



class Utils
{
    /**
     * Entry point for handling UrlTokens, in particular for access-code-login:
     * @return void
     */
    public static function handleUrlToken()
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
    public static function handleAgentRequests()
    {
        if (!($_GET??false)) {
            return;
        }

        // ?logout
        if (isset($_GET['logout'])) {
            if ($user = kirby()->user()) {
                $user->logout();
            }
            reloadAgent(); // get rid of url-command
            return;
        }

        self::execAsAnon('printview,printpreview,print');
        self::execAsAdmin('help,localhost,timer,reset,notranslate');
    } // handleAgentRequests


    /**
     * @return void
     */
    public static function executeCustomCode()
    {
        $files = getDir(PFY_CUSTOM_CODE_PATH.'*.php');
        if (!$files) {
            return;
        }
        foreach ($files as $file)
        {
            require $file;
        }
    } // executeCustomCode


    /**
     * Execute those URL-commands that require no privileges: e.g. ?login, ?printpreview etc.
     * @param $cmds
     * @return void
     */
    private static function execAsAnon($cmds)
    {
        foreach (explode(',', $cmds) as $cmd) {
            if (!isset($_GET[$cmd])) {
                continue;
            }
            switch ($cmd) {
                case 'printview':
                case 'printpreview':
                    self::printPreview();
                    break;
                case 'print':
                    self::print();
                    break;
            }
        }
    } // execAsAnon


    /**
     * Renders the current page in print-preview mode.
     * @return void
     */
    private static function printPreview()
    {
        $pagedPolyfillScript = PageFactory::$appUrl.PAGED_POLYFILL_SCRIPT_URL;
        $printNow = TransVars::getVariable('pfy-print-now');
        $printClose = TransVars::getVariable('pfy-close');
        $jq = <<<EOT
setTimeout(function() {
    console.log('now running paged.polyfill.js');
    $.getScript( '$pagedPolyfillScript' );
}, 1000);
setTimeout(function() {
    console.log('now adding buttons');
    $('body').append( "<div class='pfy-print-btns'><a href='./?print' class='pfy-button' >$printNow</a><a href='./' class='pfy-button' >$printClose</a></div>" ).addClass('pfy-print-preview');
}, 1200);

EOT;
        PageFactory::$pg->addJq($jq);
        self::preparePrintVariables();
    } // printPreview


    /**
     * Renders the current page in print mode and initiates printing
     * @return void
     */
    private static function print()
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
        self::preparePrintVariables();
    } // print


    /**
     * Helper for printPreview() and print():
     * -> prepares default header and footer elements in printing layout.
     * @return void
     */
    private static function preparePrintVariables()
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
    private static function execAsAdmin($cmds)
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
                    self::showHelp();
                    break;

                case 'notranslate': // ?notranslate
                    TransVars::$noTranslate = true;
                    break;

                case 'reset': // ?reset
                    Assets::reset();
                    PageFactory::$session->clear();
                    clearCache();
                    reloadAgent();
            }
        }
    } // execAsAdmin


    /**
     * Handles ?help request
     * @return void
     */
    private static function showHelp()
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
[?logout](./?logout)      >> log out user
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
    public static function handleAgentRequestsOnRenderedPage(): void
    {
        if (!($_GET ?? false)) {
            return;
        }
        // show variables:
        if (isset($_GET['variables']) && isAdminOrLocalhost()) {
            $html = TransVars::renderVariables();
            $str = <<<EOT
<h1>Variables</h1>
$html
EOT;
            PageFactory::$pg->setOverlay($str, false);

        // show macros:
        } elseif ((isset($_GET['functions']) || isset($_GET['macros'])) && isAdminOrLocalhost()) {
            $html = "<ul class='pfy-list-functions'>\n";
            $macros = TransVars::findAllMacros(buildInOnly: true);
            foreach ($macros as $macro) {
                $html .= "\t<li>$macro()</li>\n";
            }
            $html .= "</ul>\n";

            $str = <<<EOT
<h1>Macros</h1>
$html
EOT;
            PageFactory::$pg->setOverlay($str, false);
        }
    } // handleAgentRequestsOnRenderedPage


    /**
     * Resolves path patterns of type '~x/' to correct urls
     * @param string $html
     * @return string
     */
    public static function resolveUrl(string $url): string
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
    public static function resolveUrls(string $html): string
    {
        $l = strlen(PageFactory::$hostUrl);
        // special case: ~assets/ -> need to get url from Kirby:
        if (preg_match_all('|~assets/([^\s"\']*)|', $html, $m)) {
            foreach ($m[1] as $i => $item) {
                $filename = 'assets/'.$m[1][$i];
                $file= site()->index()->files()->find($filename);
                if ($file) {
                    $url = $file->url();
                    $url = substr($url, $l);
                    $html = str_replace($m[0][$i], $url, $html);
                } else {
                    throw new \Exception("Error: unable to find asset '~$filename'");
                }
            }
        }
        $patterns = [
            '~/'        => PageFactory::$appUrl,
            '~media/'   => PageFactory::$appRootUrl.'media/',
            '~data/'    => PageFactory::$appRootUrl.'site/custom/data/',
            '~page/'    => PageFactory::$pageUrl,
        ];
        $html = str_replace(array_keys($patterns), array_values($patterns), $html);
        return $html;
    } // resolveUrls


    /**
     * Determines the currently active language. Consults Kirby's own mechanism, 
     * then checks for URL-arg "?lang=XX", which overrides previously set language
     */
    public static function determineLanguage(): void
    {
        $supportedLanguages = PageFactory::$supportedLanguages = kirby()->languages()->codes();
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
                $url = page()->url();
                reloadAgent($url);
            }
        }
    } // determineLanguage



    /**
     * Determines the current debug state
     * Note: PageFactory maintains its own "debug" state, which diverges slightly from Kirby's.
     * enter debug state, if:
     * - on productive host:
     *      - false unless
     *          - $kirbyDebugState explicitly true
     *          - logged in as admin and $userDebugRequest true -> remember as long as logged in
     * - on localhost:
     *      - $kirbyDebugState, unless overridden by ?debug URL-Cmd
     */
    public static function determineDebugState(): bool
    {
        $kirbyDebugState = kirby()->option('debug');
        if (isset($_GET['debug'])) {
            $userDebugRequest = $_GET['debug'];
            if (($userDebugRequest === '') || ($userDebugRequest === 'true')) {
                PageFactory::$session->set('pfy.debug', true);
            } elseif ($userDebugRequest === 'false') {
                PageFactory::$session->set('pfy.debug', false);
            } elseif ($userDebugRequest === 'reset') {
                PageFactory::$session->remove('pfy.debug');
            }
        } elseif (isset($_GET['reset'])) {
            PageFactory::$session->remove('pfy.debug');
        }
        $debug = PageFactory::$session->get('pfy.debug'); // null, if not exists

        // on productive host:
        if (!isLocalhost()) {
            if (isAdmin()) { // if admin, use Kirby's debug state:
                $debug = $kirbyDebugState;
            } else {
                if ($debug !== null) { // remove cookie if exists
                    PageFactory::$session->remove('pfy.debug');
                }
                $debug = false;
            }
        // on localhost:
        } else {
            if ($debug === null) { // use Kirby's debug state, unless overridden by ?debug URL-Cmd
                $debug = $kirbyDebugState;
            }
        }
        PageFactory::$debug = $debug;
        return $debug;
    } // determineDebugState


    /**
     * Optains config values from Kirby and adds values from site/site.txt
     * @return void
     */
    public static function loadPfyConfig():void
    {
        $optionsFromConfigFile = kirby()->option('pgfactory.pagefactory.options');
        if ($optionsFromConfigFile) {
            PageFactory::$config = array_replace_recursive(OPTIONS_DEFAULTS, $optionsFromConfigFile);
        } else {
            PageFactory::$config = OPTIONS_DEFAULTS;
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
     * reloadAgent() can prepare a message to be shown on next page view, here we show the message:
     * @return void
     */
    public static function showPendingMessage(): void
    {
        if ($msg = PageFactory::$session->get('pfy.message')) {
            PageFactory::$pg->setMessage($msg);
            PageFactory::$session->remove('pfy.message');
        }
    } // showPendingMessage



    /**
     * Checks timezone. If that's not in "area/city" format, tries to obtain it from PageFactory's config file.
     * If that fails, tries to determine it via https://ipapi.co/timezone, then saves in the config file.
     * (thus avoiding subsequent calls to https://ipapi.co/timezone)
     * @return string
     */
    public static function getTimezone():string
    {
        // check whether timezone is properly set (e.g. "UTC" is not sufficient):
        $systemTimeZone = date_default_timezone_get();
        if (!preg_match('|\w+/\w+|', $systemTimeZone)) {
            // check whether timezone is defined in PageFactory's config settings:
            $systemTimeZone = PageFactory::$config['timezone']??false;
            if (!$systemTimeZone) {
                $systemTimeZone = self::getServerTimezone();
                self::appendToConfigFile('timezone', $systemTimeZone, 'Automatically set by PageFactory');
            }
            \Kirby\Toolkit\Locale::set($systemTimeZone);
        }
        date_default_timezone_set($systemTimeZone);
        return $systemTimeZone;
    } // getTimezone


    /**
     * @return string
     */
    public static function setTimezone(): string
    {
        return self::getTimezone();
    }


    /**
     * @return string
     */
    public static function getCurrentLocale(): string
    {
        return \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    } // getCurrentLocale


    /**
     * @param mixed|null $datetime
     * @param bool $includeTime
     * @return string
     */
    public static function timeToString(mixed $datetime = null, bool $includeTime = null): string
    {
        if ($datetime === null) {
            $datetime = time();
        } elseif (is_string($datetime)) {
            if (str_contains($datetime, 'T') && ($includeTime === null)) {
                $includeTime = true;
            }
            $datetime = strtotime($datetime);
        }
        if (!is_object('IntlDateFormatter')) {
            if ($includeTime) {
                $out = date('d.n.Y, H:i', $datetime);
            } else {
                $out = date('d.n.Y', $datetime);
            }

        } else {
            try {
                $includeTime = $includeTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE;
                $fmt = datefmt_create(
                    self::getCurrentLocale(),
                    IntlDateFormatter::SHORT,
                    $includeTime,
                    self::getTimezone(),
                    IntlDateFormatter::GREGORIAN
                );
                $out = datefmt_format($fmt, $datetime);
            } catch (\Exception $e) {
                $out = date('d.n.Y', $datetime);
            }
        }
        return $out;
    } // timeToString


    /**
     * Injects a new key,value pair into PageFactory's config file site/config/config.php
     * @param string $key
     * @param string $value
     * @param string|null $comment
     */
    private static function appendToConfigFile(string $key, string $value, ?string $comment = ''): void
    {
        if ($comment) {
            $comment = " // $comment";
        }

        $config = (string)fileGetContents(PFY_CONFIG_FILE);

        // check whether section pagefactory already exists, then inject values accordingly:
        if (preg_match("/(['\"]pgfactory.pagefactory.options['\"]\s*=>\s*\[)/", $config, $m)) {
            $str = "\n\t\t'$key'\t\t=> '$value',$comment,";
            $config = str_replace($m[0], $m[0].$str, $config);
            file_put_contents(PFY_CONFIG_FILE, $config);

        } elseif (preg_match("/(];)/", $config, $m)) {
            $str = <<<EOT

    'pgfactory.pagefactory.options' => [
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
    public static function getServerTimezone():string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://ipapi.co/timezone");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    } // getServerTimezone

} // Utils
