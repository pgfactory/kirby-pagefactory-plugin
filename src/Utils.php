<?php

namespace PgFactory\PageFactory;

use Kirby;
use Kirby\Data\Yaml;
use Kirby\Email\PHPMailer;
use Exception;
use PgFactory\MarkdownPlus\Permission;


class Utils
{
    /**
     * Assign values to variables that are used in templates or in page content
     * - lang
     * - langActive
     * - pageUrl
     * - appUrl
     * - generator
     * - phpVersion
     * - pageTitle
     * - siteTitle
     * - headTitle
     * - webmasterEmail
     * - menuIcon
     * - smallScreenHeader
     * - langSelection
     * - loggedInAsUser
     * - loginButton
     * - adminPanelLink
     *      plus all fields defined for site, in particular 'siteTitle'
     * Note: variables used inside page content are handled/replaced elsewhere
     * @return void
     * @throws \Kirby\Exception\LogicException|\Kirby\Exception\InvalidArgumentException
     */
    public static function prepareStandardVariables(): void
    {
        $kirbyPageTitle = TransVars::$variables['pageTitle'] ?? PageFactory::$page->title();
        TransVars::setVariable('kirbyPageTitle', $kirbyPageTitle);

        $kirbySiteTitle = TransVars::$variables['siteTitle'] ?? site()->title();
        TransVars::setVariable('kirbySiteTitle', $kirbySiteTitle);
        $headTitle = TransVars::getVariable('headTitle');
        if (!$headTitle) {
            $headTitle = "$kirbyPageTitle / $kirbySiteTitle";
        } else {
            $headTitle = TransVars::translate($headTitle);
        }
        TransVars::setVariable('headTitle', $headTitle);
        TransVars::setVariable('pageTitle', $kirbyPageTitle);

        if (PageFactory::$debug) {
            TransVars::setVariable('generator', 'Kirby v' . kirby()::version() . " + PageFactory " . getGitTag());
        } else {
            TransVars::setVariable('generator', '');
        }

        // homeLink:
        if (PageFactory::$pageUrl !== PageFactory::$appUrl) {
            $homeLink = Link::render([
                'url' => PageFactory::$appUrl,
                'text' => $kirbySiteTitle,
                'title' => 'Homepage',
                'class' => 'pfy-home-link',
            ]);
        } else {
            $homeLink = $kirbySiteTitle;
        }
        TransVars::setVariable('homeLink', $homeLink);

        $appUrl = PageFactory::$appUrl;
        $menuIcon = self::renderPfyIcon('menu');
        TransVars::setVariable('menuIcon',$menuIcon);
        $smallScreenTitle = TransVars::$variables['smallScreenHeader']?? site()->title()->value();
        $smallScreenHeader = <<<EOT

<div class="pfy-small-screen-header pfy-small-screen-only">
    <h1>$smallScreenTitle</h1>
    <button id='pfy-nav-menu-icon' type="button">$menuIcon</button>
</div>
EOT;

        $smallScreenHeader = TransVars::translate($smallScreenHeader);
        TransVars::setVariable('smallScreenHeader', $smallScreenHeader);

        TransVars::setVariable('langSelection', self::renderLanguageSelector());
        TransVars::setVariable('pageUrl', PageFactory::$pageUrl);
        TransVars::setVariable('appUrl', $appUrl);
        TransVars::setVariable('hostUrl', PageFactory::$hostUrl);
        TransVars::setVariable('lang', PageFactory::$langCode);
        TransVars::setVariable('langActive', PageFactory::$lang); // can be lang-variant, e.g. de2
        TransVars::setVariable('phpVersion', phpversion());

        if (file_exists(PFY_WEBMASTER_EMAIL_CACHE)) {
            $webmasterEmail = file_get_contents(PFY_WEBMASTER_EMAIL_CACHE);
        } else {
            if (!($webmasterEmail = PageFactory::$config['webmaster_email'] ?? false)) {
                $webmasterEmail = TransVars::getVariable('webmaster_email');
            }
            if ($webmasterEmail) {
                PageFactory::$webmasterEmail = $webmasterEmail;
            } else {
                // default webmaster email derived from current domain:
                $domain = preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url());

                // for localhost: create pseudo
                if (str_contains($domain, 'localhost')) {
                    $domain .= '.net';
                }
                PageFactory::$webmasterEmail = $webmasterEmail = 'webmaster@' . $domain;
            }
            preparePath(PFY_WEBMASTER_EMAIL_CACHE);
            file_put_contents(PFY_WEBMASTER_EMAIL_CACHE, $webmasterEmail);
        }
        TransVars::setVariable('webmaster_email', $webmasterEmail);
        PageFactory::$webmasterEmail = $webmasterEmail;
        
        $webmasterLink = Link::render([
            'url' => "mailto:$webmasterEmail",
            'text' => 'Webmaster',
        ]);
        TransVars::setVariable('pfy-webmaster-link', $webmasterLink);


        // Copy site field values to transvars:
        $siteAttributes = site()->content()->data();
        foreach ($siteAttributes as $key => $value) {
            if ($key === 'title') {
                continue;
            }
            TransVars::setVariable($key, $value);
        }

        // Copy page field values to transvars:
        $pageAttributes = page()->content()->data();
        foreach ($pageAttributes as $key => $value) {
            if (str_contains(',title,text,uuid,accesscodes,', ",$key,") || str_ends_with($key, '_md')) {
                continue;
            } elseif ($key === 'variables') {
                $values = Yaml::decode($value);
                foreach ($values as $k => $v) {
                    TransVars::setVariable($k, $v);
                }
            } else {
                TransVars::setVariable($key, (string)$value);
            }
        }

        $pageUrl = PageFactory::$pageUrl;
        if (Extensions::$loadedExtensions['PageElements']??false) {
            $loginLink = "$pageUrl?login";
            $logoutLink = "$pageUrl?login";
        } else {
            $loginLink = PageFactory::$appUrl.'panel/login/';
            $logoutLink = "$pageUrl?logout";
        }

        $user = PageFactory::$user;
        if ($user) {
            // user is already logged in, so inform and offer logout:
            $username = PageFactory::$userName;
            $logout = TransVars::getVariable('pfy-logout');
            TransVars::setVariable('LoginLink', "<a href='$logoutLink'>$logout</a>");

            $label = TransVars::getVariable('pfy-logged-in-label');
            TransVars::setVariable('loggedIn', $label.$username);

            TransVars::setVariable('username', $username);

            $logoutIcon = self::renderPfyIcon('logout');
            $pfyLoginButtonLabel = TransVars::getVariable('pfy-logout-button-title');
            TransVars::setVariable('loginButton', "<span class='pfy-login-button'><a href='$logoutLink' class='pfy-login-button' title='$pfyLoginButtonLabel'>$logoutIcon</a></span>");

        } else {
            $login = TransVars::getVariable('pfy-login');
            TransVars::setVariable('LoginLink', "<a href='$loginLink'>$login</a>");

            $label = TransVars::getVariable('pfy-not-logged-in-label');
            TransVars::setVariable('loggedIn', $label);

            TransVars::setVariable('username', '');

            $loginIcon = self::renderPfyIcon('user');
            $pfyLoginButtonLabel = TransVars::getVariable('pfy-login-button-title');
            TransVars::setVariable('loginButton', "<span class='pfy-login-button'><a href='$loginLink' class='pfy-login-button' title='$pfyLoginButtonLabel'>$loginIcon</a></span>");
        }

        $pfyAdminPanelLinkText = TransVars::getVariable('pfy-admin-panel-link-text');
        TransVars::setVariable('adminPanelLink', "<a href='{$appUrl}panel' target='_blank'>$pfyAdminPanelLinkText</a>");

        // site/plugins/pagefactory/assets/icons/_pfy-icons.svg
        $pfyIcons = svg('site/plugins/pagefactory/assets/icons/_pfy-icons.svg');
        PageFactory::$pg->addBodyEndInjections($pfyIcons);

    } // prepareStandardVariables


    /**
     * Appends the svg source to end of body, returns a svg reference (<use...>)
     * @param string $iconName
     * @param string $iconFile
     * @return string
     * @throws Exception
     */
    public static function renderPfyIcon(string $iconName): string
    {
        $iconId = "pfy-iconset-$iconName";
        $icon = "<svg xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' viewBox='0 0 1000 1000' xml:space='preserve' width='1em'><use href='#$iconId' /></svg>";
        return $icon;
    } // renderSvgIcon



    /**
     * Defines variable 'pfy-lang-selection', which expands to a language selection block,
     * one language icon per supported language
     */
    public static function renderLanguageSelector(): string
    {
        $out = '';
        if (sizeof(PageFactory::$supportedLanguages) > 1) {
            foreach (PageFactory::$supportedLanguages as $lang) {
                $langCode = substr($lang, 0, 2);
                $text = TransVars::getVariable("pfy-lang-select-$langCode");
                if ($lang === PageFactory::$lang) {
                    $out .= "<span class='pfy-lang-elem pfy-active-lang $langCode'><span>$text</span></span> ";
                } else {
                    $title = TransVars::getVariable("pfy-lang-select-title-$langCode");
                    $out .= "<span class='pfy-lang-elem $langCode'><a href='?lang=$lang' title='$title'>$text</a></span> ";
                }
            }
            $out = "<span class='pfy-lang-selection'>$out</span>\n";
        }
        return $out;
    } // renderLanguageSelector



    /**
     * Assign values to variables that are used directly in templates (i.e. outside of page content)
     *  - headInjections
     *  - bodyTagClasses
     *  - bodyTagAttributes
     *  - bodyEndInjections
     * @return void
     * @throws \Kirby\Exception\LogicException|\ScssPhp\ScssPhp\Exception\SassException
     */
    public static function prepareTemplateVariables(): void
    {
        PageFactory::$page->headInjections()->value     = PageFactory::$pg->renderHeadInjections();

        $bodyTagClasses   = PageFactory::$pg->bodyTagClasses ?: 'pfy-large-screen';
        if (isAdmin()) {
            $bodyTagClasses .= ' pfy-admin pfy-loggedin';
        } elseif (Permission::isLoggedIn()) {
            $bodyTagClasses .= ' pfy-loggedin';
        }
        // for debugging:
        //if (kirby()->session()->get()) {
        //    $bodyTagClasses = trim("session $bodyTagClasses");
        //}
        if (PageFactory::$isLocalhost && PageFactory::$debug) {
            $bodyTagClasses = trim("localhost $bodyTagClasses");
        }
        if (PageFactory::$debug) {
            $bodyTagClasses = trim("debug $bodyTagClasses");
        }
        PageFactory::$page->bodyTagClasses()->value     = $bodyTagClasses;

        PageFactory::$page->bodyTagAttributes()->value  = PageFactory::$pg->bodyTagAttributes;
        PageFactory::$page->bodyEndInjections()->value  = PageFactory::$pg->renderBodyEndInjections();
    } // prepareTemplateVariables



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

        self::execAsAnon('printview,printpreview,print-preview,print,logout,reset,flush,flushcache,iframe');
        self::execAsAdmin('help,reset,notranslate,release');
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
     * Execute those URL-commands that require no privileges: e.g. ?logout, ?printpreview etc.
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
                case 'print-preview':
                self::printPreview();
                    break;
                case 'print':
                    self::print();
                    break;
                case 'flush':
                case 'flushcache':
                    if (PageFactory::$debug) {
                        Cache::flush();
                    }
                    break;
                case 'logout':  // ?logout
                    $name = 'anon';
                    // check whether Permission has an accessCode user registered, remove if so:
                    $session = kirby()->session();
                    if ($email = $session->get('pfy.accessCodeUser')) {
                        $user = kirby()->user($email);
                        if (is_object($user)) {
                            $name = (string)$user->nameOrEmail();
                        } else {
                            $name = (string)$user;
                        }
                        $session->remove('pfy.accessCodeUser');
                    }
                    if ($user = kirby()->user()) {
                        $name = (string)$user->nameOrEmail();
                        $user->logout();
                    }
                    mylog("User '$name' logged out.", LOGIN_LOG_FILE);
                    reloadAgent(message: '{{ pfy-logged-out-now }}'); // get rid of url-command
                    break;
                case 'reset': // ?reset (as non-admin): harmless reset => just undo previous '?debug' commands
                    self::resetDebugState();
                    break;
                case 'iframe':
                    if (!($a = page()->supportExportAsIframe()->value())) {
                        $a = kirby()->option('pgfactory.pagefactory.options.supportExportAsIframe');
                    }
                    if ($a) {
                        if ($a === true || $a === 'true') {
                            $a = '*';
                        }
                        PageFactory::$pg->addBodyTagClass('pfy-export-as-iframe');
                        header("Access-Control-Allow-Origin: $a");
                    }
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
  pullScript( '$pagedPolyfillScript' );
}, 1000);

setTimeout(function() {
  console.log('now adding buttons');
  var printBtns = document.createElement('div');
  printBtns.className = 'pfy-print-btns';
  printBtns.innerHTML = "<a href='./?print' class='pfy-button'>$printNow</a><a href='./' class='pfy-button'>$printClose</a>";
  document.body.appendChild(printBtns);
  document.body.classList.add('pfy-print-preview');
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
    window.print();
}, 200);

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

            // check admin-privilege:
            if (!isAdminOrLocalhost()) {
                $str = <<<EOT
# ?$cmd

You need to be logged in as Admin to use this system command.
EOT;
                PageFactory::$pg->setOverlay($str, true);
                continue;
            }

            switch ($cmd) {
                case 'help': // ?help
                    self::showHelp();
                    break;

                case 'notranslate': // ?notranslate
                    TransVars::$noTranslate = true;
                    break;

                case 'reset': // ?reset
                    self::resetAll();
                    reloadAgent();

                case 'release': // ?release
                    PageFactory::$debug = false;
                    self::resetAll();
                    reloadAgent();
            }
        }
    } // execAsAdmin


    /**
     * Resets Kirby and PageFactory
     * @return void
     */
    private static function resetAll(): void
    {
        kirby()->session()->clear(); // Resets all Kirby sessions

        // deletes all PHP-session variables used by PageFactory:
        session_start();
        foreach ($_SESSION as $key => $value) {
            if (str_starts_with($key, 'pfy.')) {
                unset($_SESSION[$key]);
            }
        }
        session_write_close();

        PageFactory::$forceAssetsUpdate = true;

        Cache::flushAll(); // -> deletes media/ and site/cache/
        Assets::reset(); // Deletes all files created by Assets
        PageFactory::$assets->prepareAssets(); // -> recompile scss files while still privileged
    } // resetAll


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
[?login](./?login)      >> open login window
[?logout](./?logout)      >> logout user
[?print](./?print)		    	>> starts printing mode and launches the printing dialog
[?printpreview](./?printpreview)  	>> presents the page in print-view mode    
[?reset](./?reset)		    	>> resets all state-defining information: caches, tokens, session-vars.
[?release](./?release)		    >> like reset, but recompiles SCSS files without line numbers.

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
            '~/'        => PageFactory::$appUrl,
            '~media/'   => 'media/',
            '~assets/'  => 'content/assets/',
            '~page/'    => PageFactory::$pageUrl,
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
            '~download/'=> PageFactory::$appUrl.'download/',
            '~media/'   => PageFactory::$appRootUrl.'media/',
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
        if (!$supportedLanguages || $supportedLanguages[0] === 'default') {
            if ($langObj = kirby()->language()) {
                $lang = $langObj->code();
            } elseif (!($lang = kirby()->defaultLanguage())) {
                $lang = PageFactory::$config['defaultLanguage'] ?: 'en';
            }
            PageFactory::$lang = $lang;
            PageFactory::$langCode = substr($lang, 0, 2);
            return;
        }

        if (!($lang = kirby()->session()->get('pfy.lang'))) {
            $lang = kirby()->defaultLanguage();
            if ($lang) {
                $lang = kirby()->defaultLanguage()->code();
            }
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $kirbyDebugState = kirby()->option('debug');
        if (isset($_GET['debug']) && (isAdmin() || isLocalhost())) {
            $userDebugRequest = $_GET['debug'];

            if (($userDebugRequest === '') || ($userDebugRequest === 'true')) { // ?debug or ?debug=true
                $_SESSION['pfy.debug'] = true;

            } elseif ($userDebugRequest === 'false') { // ?debug=false -> simulate remote host without debug-mode
                $_SESSION['pfy.debug'] = false;
                session_write_close();
                reloadAgent();

            } elseif ($userDebugRequest === 'reset') { // ?debug=reset
                unset($_SESSION['pfy.debug']);
                reloadAgent();
            }
        }
        $debug = $_SESSION['pfy.debug']??null;

        // on productive host:
        if (!isLocalhost()) {
            if (isAdmin()) { // if admin, use Kirby's debug state:
                $debug = $kirbyDebugState;
            } else {
                if ($debug !== null) { // remove cookie if exists
                    unset($_SESSION['pfy.debug']);
                }
                $debug = false;
            }
        // on localhost:
        } else {
            if ($debug === null) { // use Kirby's debug state, unless overridden by ?debug URL-Cmd
                $debug = $kirbyDebugState;
            }
        }

        // Right after installation on remote host, assets have not been compiled. Activate debug in that case:
        if (!is_dir('site/plugins/pagefactory/assets/css/')) {
            $debug = true;
        }

        session_write_close();
        return $debug;
    } // determineDebugState


    /**
     * @return void
     */
    public static function resetDebugState(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['pfy.debug'])) {
            unset($_SESSION['pfy.debug']);
        }
        self::determineDebugState();
    } // resetDebugState


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
        PageFactory::$wrapperTag = PageFactory::$config['sourceWrapperTag'];
        PageFactory::$wrapperClass = PageFactory::$config['sourceWrapperClass'];

    } // loadPfyConfig


    /**
     * reloadAgent() can prepare a message to be shown on next page view, here we show the message:
     * @return void
     */
    public static function showPendingMessage(): void
    {
        $session = kirby()->session();
        if (!isset($_GET['ajax'])) {
            if ($msg = $session->pull('pfy.message')) {
                PageFactory::$pg->setMessage($msg);
            }
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
        // check config setting:
        if ($l = kirby()->option('pgfactory.pagefactory.options.locale')) {
            return $l;
        }
        // get locale from agent:
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
            if (preg_match('/(\d{4}-\d\d-\d\d) (\d\d:\d\d)/', $datetime, $m)) {
                $datetime = str_replace($m[0], "{$m[1]}T{$m[1]}", $datetime);
                $includeTime = true;
            } elseif (str_contains($datetime, 'T') && ($includeTime === null)) {
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


    /**
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string $debugInfo
     * @param string $from
     * @param string $fromName
     * @return void
     */
    public static function sendMail(string $to, string $subject, string $body, string $debugInfo = '', string $from = '', string $fromName = ''): void
    {
        if (str_contains($subject, '{{')) {
            $subject = TransVars::translate($subject);
        }
        if (str_contains($body, '{{')) {
            $body = TransVars::translate($body);
        }
        $props = [
            'to' => $to,
            'from' => $from ?: TransVars::getVariable('webmaster_email'),
            'fromName' => $fromName ?: false,
            'subject' => $subject,
            'body' => $body,
        ];

        new PHPMailer($props);
        mylog("$subject\n\n$body", 'mail-log.txt');
        //        if (PageFactory::$isLocalhost) {
        //            $props['body'] = "\n\n" . $props['body'];
        //            $text = var_r($props);
        //            $html = "<pre>$debugInfo:\n$text</pre>";
        //            PageFactory::$pg->setOverlay($html);
        //        } else {
        //            new PHPMailer($props);
        //        }
    } // sendMail


    /**
     * Obtains list of users from Kirby, filters, sorts and converts by template.
     * @param array $options
     * @return string
     */
    public static function getUsers(array $options = []): array
    {
        $groupFilter = $options['role']??false;
        $reversed = $options['reversed']??false;
        $sort = $options['sort']??false;

        $users = kirby()->users();
        if ($groupFilter) {
            $users = $users->filterBy('role', $groupFilter);
        }
        $users = $users->sortBy('name');

        $usersArray = [];
        foreach ($users as $user) {
            $content = $user->content();
            $rec = [
                'username' =>  (string)$user->name(),
                'email' =>  (string)$user->email(),
                ];
            if ($content) {
                foreach ($content->data() as $k => $v) {
                    if (is_string($v)) {
                        $rec[$k] = $v;
                    }
                }
            }
            $usersArray[] = $rec;
        }

        if ($sort) {
            $sortElems = explodeTrim(',', $sort);

            usort($usersArray, function($a, $b) use($sortElems) {
                $aa = [];
                $bb = [];
                foreach ($sortElems as $e) {
                    $aa[] = $a[$e];
                    $bb[] = $b[$e];
                }
                return $aa <=> $bb;
            });
        }

        if ($reversed) {
            $usersArray = array_reverse($usersArray);
        }

        return $usersArray;
    } // getUsers

} // Utils
