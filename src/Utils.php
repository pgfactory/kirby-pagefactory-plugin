<?php

namespace PgFactory\PageFactory;

use Kirby;
use Kirby\Email\PHPMailer;
use Exception;
use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\MarkdownPlus\Permission;


class Utils
{
    public static string $loginLink;
    public static string $loginButton;
    public static mixed $loggedIn;
    public static mixed $menuIcon;

    private static string $pfyIcons = "";
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


    /**
     * Forces system reset when new or moved installation is detected.
     * @return void
     */
    public static function checkInstallationPath(): void
    {
        $prevInstallationPath = getFile(PFY_INSTALLATION_PATH_CHECK);
        if ($prevInstallationPath === PFY_APP_BASE_PATH) {
            return;
        }

        self::resetAll();
        self::setInstallationCheckFile();
        reloadAgent(message: "Automatic reset executed");
    } // checkInstallationPath


    /**
     * @return void
     */
    public static function importKirbyFieldsToVariables(): void
    {
        $fields = page()->content()->fields();
        foreach ($fields as $key => $field) {
            $value = $field->value();
            // check whether it's a content block, unpack it if necessary:
            if ($value && str_starts_with($value, '[{')) {
                $value = $field->toBlocks()->toHtml();
            }
            TransVars::setVariable($key, $value, propagateToField: false);
        }
    } // importKirbyFieldsToVariables


    /**
     * @return void
     */
    public static function prepareGenericVariables(): void
    {
        TransVars::setVariable('today', date('Y-m-d'));
        TransVars::setVariable('now', date('Y-m-d H:i'));
        TransVars::setVariable('pageTitle', page()->title()->value());
        TransVars::setVariable('siteTitle', site()->title()->value());

        $headTitle = TransVars::getVariable('headTitle', false);
        if (!$headTitle) {
            $headTitle = page()->title() . " / " . site()->title();
        }
        $headTitle = TransVars::translate($headTitle);
        TransVars::setVariable('headTitle', $headTitle);
    } // prepareGenericVariables



    /**
     * @return void
     */
    public static function prepareUserRelatedVars(): void
    {
        if (Extensions::$loadedExtensions['PageElements']??false) {
            $loginLink = PFY_PAGE_URL . '?login';
            $logoutLink = PFY_PAGE_URL . "?login";
        } else {
            $loginLink = PFY_APP_BASE_URL . 'panel/login/';
            $logoutLink = PFY_PAGE_URL . "?logout";
        }

        $user = PageFactory::$user;
        if ($user) {
            // user is already logged in, so inform and offer logout:
            $username = PageFactory::$userName;
            $logout = TransVars::getVariable('pfy-logout');
            self::$loginLink = "<a href='$logoutLink'>$logout</a>";

            $label = TransVars::getVariable('pfy-logged-in-label');
            self::$loggedIn = $label.$username;

            $logoutIcon = self::renderPfyIcon('logout');
            $pfyLoginButtonLabel = TransVars::getVariable('pfy-logout-button-title');
            self::$loginButton = "<span class='pfy-login-button'><a href='$logoutLink' class='pfy-login-button' title='$pfyLoginButtonLabel'>$logoutIcon</a></span>";

        } else {
            $login = TransVars::getVariable('pfy-login');
            self::$loginLink = "<a href='$loginLink'>$login</a>";

            $label = TransVars::getVariable('pfy-not-logged-in-label');
            self::$loggedIn = $label;

            $loginIcon = self::renderPfyIcon('user');
            $pfyLoginButtonLabel = TransVars::getVariable('pfy-login-button-title');
            self::$loginButton = "<span class='pfy-login-button'><a href='$loginLink' class='pfy-login-button' title='$pfyLoginButtonLabel'>$loginIcon</a></span>";
        }
    } // prepareUserRelatedVars


    /**
     * @return void
     * @throws Exception
     */
    public static function prepareWebmasterEmail(): void
    {
        if (!($webmasterEmail = kirby()->option('pgfactory.pagefactory.webmaster_email'))) {
            $webmasterEmail = TransVars::getVariable('webmaster_email');
        }
        if ($webmasterEmail) {
            PageFactory::$webmasterEmail = $webmasterEmail;
        } elseif (!isLocalhost()) {
            exit('Please define "webmaster_email" in config.php.');
        }
        $webmasterLink = Link::render([
            'url' => "mailto:$webmasterEmail",
            'text' => 'Webmaster',
        ]);
        TransVars::setVariable('webmaster_email', $webmasterEmail);
        TransVars::setVariable('pfy-webmaster-link', $webmasterLink);
    } // prepareWebmasterEmail


    /**
     * @return void
     */
    public static function queuePfyIconDefinitions(): void
    {
        $pfyIcons = svg(PFY_APP_BASE_PATH.'site/plugins/pagefactory/assets/icons/_pfy-icons.svg');
        Page::addBodyEndInjections($pfyIcons);
    } // queuePfyIconDefinitions


    /**
     * @return string
     */
    public static function renderHeadTitle(): string
    {
        return TransVars::getVariable('headTitle', false);
    } // renderHeadTitle


    /**
     * @return string
     * @throws Kirby\Exception\LogicException
     */
    public static function renderGenerator(): string
    {
        if (PageFactory::$dev) {
            $generator = 'Kirby v' . kirby()::version() . " + PageFactory " . getGitTag();
            $generator .= ' (on PHP ' . phpversion() . ')';
        } else {
            $generator = 'Kirby CMS';
        }
        return $generator;
    } // renderGenerator


    /**
     * @return string
     */
    public static function renderHomeLink(): string
    {
        if (PFY_PAGE_URL !== PFY_APP_BASE_URL) {
            $homeLink = Link::render([
                'url' => PFY_APP_BASE_URL,
                'text' => site()->title(),
                'title' => 'Homepage',
                'class' => 'pfy-home-link',
            ]);
        } else {
            $homeLink = site()->title();
        }
        return $homeLink;
    } // renderHomeLink


    /**
     * @return string
     */
    public static function renderAdminPanelLink(): string
    {
        $pfyAdminPanelLinkText = TransVars::getVariable('pfy-admin-panel-link-text');
        return "<a href='~/panel' target='_blank'>$pfyAdminPanelLinkText</a>";
    } // renderAdminPanelLink


    /**
     * @return string
     * @throws Exception
     */
    public static function renderSmallScreenHeader(): string
    {
        self::$menuIcon = $menuIcon = self::renderPfyIcon('menu');
        $smallScreenTitle = TransVars::$variables['smallScreenHeader']?? site()->title()->value();
        $smallScreenMenuLabel = TransVars::$variables['smallScreenMenuLabel']??'Menu';
        $beforeTitle = TransVars::$variables['smallScreenTitleBefore']??'';
        $afterTitle = TransVars::$variables['smallScreenTitleAfter']??'';
        $smallScreenHeader = <<<EOT

<div class="pfy-small-screen-header pfy-small-screen-only">
    $beforeTitle
    <h1>$smallScreenTitle</h1>
    $afterTitle
    <button id='pfy-nav-menu-icon' type="button" aria-label="$smallScreenMenuLabel">$menuIcon</button>
</div>
EOT;

        return TransVars::translate($smallScreenHeader);
    } // renderSmallScreenHeader


    /**
     * @return string
     */
    public static function renderBodyTagClasses(): string
    {
        $pageId = str_replace('/', '-', page()->id());
        $bodyTagClasses   = "page-$pageId ";
        $bodyTagClasses   .= Page::get('bodyTagClasses') ?: 'pfy-large-screen';
        if (isAdmin()) {
            $bodyTagClasses .= ' pfy-admin pfy-loggedin';
        } elseif (Permission::isLoggedIn()) {
            $bodyTagClasses .= ' pfy-loggedin';
        }
        // for debugging:
        //if (kirby()->session()->get()) {
        //    $bodyTagClasses = trim("session $bodyTagClasses");
        //}
        if (PageFactory::$isLocalhost && PageFactory::$dev) {
            $bodyTagClasses = "localhost $bodyTagClasses";
        }
        if (PageFactory::$dev) {
            $bodyTagClasses = "debug $bodyTagClasses";
        }
        if (PageFactory::$dev) {
            $bodyTagClasses = "dev $bodyTagClasses";
        }
        return trim($bodyTagClasses);
    } // renderBodyTagClasses
    
    


    /**
     * Appends the svg source to end of body, returns a svg reference (<use...>)
     * @param string $iconName
     * @param string $iconFile
     * @return string
     * @throws Exception
     */
    public static function renderPfyIcon(string $iconName): string
    {
        if (self::iconExists($iconName)) {
            $iconId = "pfy-iconset-$iconName";
            $icon = "<svg viewBox='0 0 1000 1000' width='1em'><use href='#$iconId' /></svg>";
        } else {
            $icon = MdPlusHelper::renderIcon($iconName);
        }
        return $icon;
    } // renderSvgIcon


    /**
     * @param string $iconName
     * @return bool
     */
    public static function iconExists(string $iconName): bool
    {
        if (!self::$pfyIcons) {
            $pfyIconsFile = PFY_APP_BASE_PATH . 'site/plugins/pagefactory/assets/icons/_pfy-icons.svg';
            self::$pfyIcons = getFile($pfyIconsFile);
        }
        $exists = str_contains(self::$pfyIcons,  "pfy-iconset-$iconName");
        return $exists;
    } // iconExists



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
                    $out .= "<span class='pfy-lang-elem $langCode'><a href='~page/?lang=$lang' title='$title'>$text</a></span> ";
                }
            }
            $out = "<span class='pfy-lang-selection'>$out</span>\n";
        }
        return $out;
    } // renderLanguageSelector


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

        self::execAsAnon('printview,printpreview,print-preview,print,logout,reset,flush,flushcache,iframe,bust');
        self::execAsAdmin('help,reset,notranslate,release');
    } // handleAgentRequests


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
                    if (PageFactory::$dev) {
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
                    mylog("User '$name' logged out.", PFY_LOGIN_LOG_FILE);
                    reloadAgent(message: '{{ pfy-logged-out-now }}'); // get rid of url-command
                    break;
                case 'reset': // ?reset (as non-admin): harmless reset => just undo previous '?dev' commands
                    self::resetDevState();
                    if (isLocalhost()) { // exception: reset on localhost
                        self::resetAll();
                        self::handleDevDataUpdate();
                        self::setInstallationCheckFile();
                        reloadAgent(message: 'Reset executed.');
                    }
                    break;
                case 'iframe':
                    if (!($a = page()->supportExportAsIframe()->value())) {
                        $a = kirby()->option('pgfactory.pagefactory.supportExportAsIframe');
                    }
                    if ($a) {
                        if ($a === true || $a === 'true') {
                            $a = '*';
                        }
                        Page::addBodyTagClass('pfy-export-as-iframe');
                        header("Access-Control-Allow-Origin: $a");
                    }
                    break;
                case 'bust':  // ?bust
                    Assets::activateBrowserCacheBusting();
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
        $pagedPolyfillScript = PFY_APP_BASE_URL.PAGED_POLYFILL_SCRIPT_URL;
        $printNow = TransVars::getVariable('pfy-print-now');
        $printClose = TransVars::getVariable('pfy-close');
        $jq = <<<EOT
setTimeout(function() {
  console.log('now running paged.polyfill.js');
  pullScript( '$pagedPolyfillScript' );
}, 1000);

setTimeout(function() {
  console.log('now adding buttons');
  const printUrl = window.location.href.replace(/printview/, 'print');
  const origUrl = window.location.href.replace(/(\&|\?)printview/, '');
  console.log(`printUrl: \${printUrl}, origUrl: \${origUrl}`);
  const printBtns = document.createElement('div');
  printBtns.className = 'pfy-print-btns';
  printBtns.innerHTML = `<a href='\${printUrl}' class='pfy-button'>$printNow</a><a href='\${origUrl}' class='pfy-button'>$printClose</a>`;
  document.body.appendChild(printBtns);
  document.body.classList.add('pfy-print-preview');
}, 1200);

EOT;
        Page::addJq($jq);
        self::preparePrintVariables();
    } // printPreview


    /**
     * Renders the current page in print mode and initiates printing
     * @return void
     */
    private static function print()
    {
        $pagedPolyfillScript = PFY_APP_BASE_URL.PAGED_POLYFILL_SCRIPT_URL;

        $jq = <<<EOT
setTimeout(function() {
  console.log('now running paged.polyfill.js');
  pullScript( '$pagedPolyfillScript' );
}, 1000);

setTimeout(function() {
  document.body.classList.add('pfy-print');
  window.print();
}, 1200);

EOT;


        Page::addJq($jq);
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
        Page::addCss($css);
    } // preparePrintVariables


    /**
     * Execute those URL-commands that require admin privileges: e.g. ?help, ?notranslate etc.
     * @param $cmds
     * @return void
     */
    private static function execAsAdmin($cmds)
    {
        // note: 'dev' handled in PageFactory->__construct() => Utils->determineDevState()

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
                Page::setOverlay($str, true);
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
                    self::handleDevDataUpdate();
                    self::setInstallationCheckFile();
                    reloadAgent(message: 'Reset executed.');

                case 'release': // ?release
                    PageFactory::$dev = false;
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


        Cache::flushAll(); // -> deletes media/ and site/cache/
        Extensions::reset();
        Assets::reset(); // Deletes all files created by Assets
        PageFactory::$forceAssetsUpdate = true;
        Assets::compileAssets();
    } // resetAll


    private static function handleDevDataUpdate(): void
    {
        if (!isset($_GET['data'])) {
            return;
        }
        if (!PageFactory::$config['production_mode_data_path']??false) {
            return;
        }

        $prodDataPath = resolvePath('~/'.PageFactory::$config['production_mode_data_path']);
        $prodDataPath = normalizePath($prodDataPath);
        $configPath = resolvePath('~/site/config/');

        // copy files to site/config/:
        $files = getDir($prodDataPath.'config/*', true);
        foreach ($files as $file) {
            $filename = basename($file);
            $path = "$configPath$filename";
            copy($file, $path);
        }

        // copy files to site/custom/data/:
        $customPath = resolvePath('~/site/custom/');
        $dataPath = $customPath.'data/';
        // if folder already exists, move it to .history/:
        if (!is_dir($customPath)) {
            preparePath($customPath . '.history');
            rename($dataPath, "$customPath.history/" . date('Y-m-d_H-i-s') . '_data');
        }

        $files = getDirDeep($prodDataPath.'data/*');
        $l = strlen($prodDataPath.'data/');
        foreach ($files as $file) {
            $filename = substr($file, $l);
            $path = "$dataPath$filename";
            preparePath($path);
            copy($file, $path);
        }
    } // handleDevDataUpdate


    /**
     * Handles ?help request
     * @return void
     */
    private static function showHelp(): void
    {
        if (isset($_GET['help'])) {
            if (isAdminOrLocalhost()) {
                $str = <<<EOT
@@@ .pfy-general-help
# Help

[?help](./?help)       12em>> this information 
[?variables](./?variables)      >> shows currently defined variables
[?macros](./?macros)      >> shows currently defined macros()
[?lang=](./?lang)      >> activates given language
[?dev](./?dev)        >> activates dev mode
[?localhost=false](./?localhost=false)      >> mimicks running on a remote host (for testing)
[?notranslate](./?notranslate)      >> shows variables instead of translating them
[?login](./?login)      >> opens login window
[?logout](./?logout)      >> logs out user
[?print](./?print)		    	>> starts printing mode and launches the printing dialog
[?printpreview](./?printpreview)  	>> presents the page in print-view mode    
[?reset](./?reset)		    	>> resets all state-defining information: caches, tokens, session-vars.
[?release](./?release)		    >> like reset, but recompiles SCSS files without line numbers.
[?bust](./?bust)      >> activates 'browser cache busting'

@@@
EOT;
                $str = removeCStyleComments($str);
                $str .= Extensions::showHelp();
            } else {
                $str = <<<EOT
# Help

You need to be logged in as Admin to see requested information.

EOT;
            }
            Page::setOverlay($str);
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
            Page::setOverlay($str, false);

        // show macros:
        } elseif ((isset($_GET['functions']) || isset($_GET['macros'])) && isAdminOrLocalhost()) {
            $html = Macros::renderMacros();
            $str = <<<EOT
<h1>Macros</h1>
$html
EOT;
            Page::setOverlay($str, false);
        }
    } // handleAgentRequestsOnRenderedPage


    /**
     * Resolves path patterns of type '~x/' to correct urls
     * @param string $html
     * @return string
     */
    public static function resolveUrls(string $html, bool $forResoucres = false): string
    {
        // special case: ~assets/ -> need to get url from Kirby:
        if (preg_match_all('|~assets/([^\s"\')]*)|', $html, $m)) {
            foreach ($m[1] as $i => $item) {
                $filename = 'assets/'.$m[1][$i];
                $file= site()->index()->files()->find($filename);
                if ($file) {
                    $url = $file->url();
                    $html = str_replace($m[0][$i], $url, $html);
                } else {
                    throw new \Exception("Error: unable to find asset '~$filename'");
                }
            }
        }
        $pageId = page()->id() . '/';

        // ~page/ for <a> tags -> replace without redir-offset:
        if (preg_match_all('|(<a\s+href=[\'"])~page/|', $html, $m)) {
            $homeSlug = site()->homePage()->slug().'/';
            foreach ($m[1] as $i => $aTag) {
                // if it's homepage -> fix path to '':
                if ($pageId === $homeSlug) {
                    $pageId = '';
                }
                $html = str_replace($m[0][$i], $aTag.PFY_APP_BASE_URL.$pageId, $html);
            }

        // ~page/ for instances of "src=...":
        } elseif (preg_match_all('|(src=[\'"])~page/|', $html, $m)) {
            $pageUrl = page()->url().'/';
            foreach ($m[1] as $i => $aTag) {
                $html = str_replace($m[0][$i], $aTag.$pageUrl, $html);
            }
        }
        $html = str_replace('~page/', page()->url().'/', $html);

        // ~/ for <a> tags -> replace without redir-offset:
        if (!$forResoucres) {
            $html = preg_replace('|(<a\s+href=[\'"])~/|', "$1" . PFY_APP_BASE_URL, $html);
        } else {
            $html = preg_replace('|~/|', PFY_APP_BASE_URL.PFY_BASE_OFFSET, $html);
        }
        return $html;
    } // resolveUrls


    /**
     * Determines the currently active language. Consults Kirby's own mechanism, 
     * then checks for URL-arg "?lang=XX", which overrides previously set language
     */
    public static function determineLanguage(): void
    {
        $kirby = kirby();
        $supportedLanguages = PageFactory::$supportedLanguages = $kirby->languages()->codes();
        if (!$supportedLanguages || $supportedLanguages[0] === 'default') {
            if ($langObj = $kirby->language()) {
                $lang = $langObj->code();
            } elseif (!($lang = $kirby->defaultLanguage())) {
                $lang = PageFactory::$config['defaultLanguage'] ?: 'en';
            }
            PageFactory::$lang = $lang;
            PageFactory::$langCode = substr($lang, 0, 2);
            return;
        }

        if (!($lang = $kirby->session()->get('pfy.lang'))) {
            $lang = $kirby->defaultLanguage();
            if ($lang) {
                $lang = $kirby->defaultLanguage()->code();
            }
        }

        if ($lang) {
            $langCode = substr($lang, 0, 2);
            if (!in_array($lang, $supportedLanguages) && !in_array($langCode, $supportedLanguages)) {
                $lang = $langCode = $kirby->defaultLanguage()->code();
            }
            PageFactory::$defaultLanguage = $kirby->defaultLanguage()->code();
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
                $kirby->session()->set('pfy.lang', $lang);
                $kirby->setCurrentLanguage($langCode);
                $url = page()->url();
                reloadAgent($url);
            }
        }
    } // determineLanguage



    /**
     * Determines the current dev state
     * Note: PageFactory maintains its own "dev" state, which diverges from Kirby's debug state.
     * Enter dev state, if:
     * - on productive host:
     *      - false unless
     *          - $kirbyDebugState explicitly true
     *          - logged in as admin and $userDebugRequest true -> remember as long as logged in
     * - on localhost:
     *      - $kirbyDebugState, unless overridden by ?dev URL-Cmd
     */
    public static function determineDevState(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $devMode = $_SESSION['pfy.dev'] ?? null;

        if ($devMode !== null && !isset($_GET['dev'])) {
            session_abort();
            return $devMode;
        }

        $appRoot = dirname($_SERVER['SCRIPT_FILENAME']);
        $docRoot = $_SERVER['DOCUMENT_ROOT']??'';
        $patt = kirby()->option('pgfactory.pagefactory.production_host_path_pattern');
        if ($appRoot !== $docRoot) {
            // app in subfolder -> check against production_host_path_pattern:
            if ($patt && is_string($patt)) {
                $devMode = !preg_match("#$patt#", $appRoot);
            } elseif (is_bool($patt)) {
                $devMode = !$patt;
            }
        } else {
            if ($patt === '/') {
                $devMode = false;
            } elseif (is_bool($patt)) {
                $devMode = !$patt;
            } else {
                $devMode = Permission::isLocalhost();
            }
        }
        $devMode = $devMode || Permission::isAdmin();

        if (!isset($_GET['dev'])) {
            session_abort();
            return $devMode;
        }

        // if not on localhost, only admins may proceed with ?dev requests:
        if (!(Permission::isAdmin() || Permission::isLocalhost())) {
            session_abort();
            return $devMode;
        }

        // evaluate ?dev request:
        $devModeRequest = $_GET['dev'];
        if (($devModeRequest === '') || ($devModeRequest === 'true')) { // ?dev or ?dev=true
            $_SESSION['pfy.dev'] = true;
            session_write_close();
            reloadAgent(message: 'Dev mode enabled.');

        } elseif ($devModeRequest === 'false') { // ?dev=false -> simulate remote host without dev-mode
            $_SESSION['pfy.dev'] = false;
            session_write_close();
            reloadAgent(message: 'Dev mode disabled.');

        } elseif ($devModeRequest === 'reset') { // ?dev=reset
            unset($_SESSION['pfy.dev']);
            reloadAgent(message: 'Dev mode reset.');
        }
        return $devMode;
    } // determineDevState


    /**
     * @return void
     */
    public static function resetDevState(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['pfy.dev'])) {
            unset($_SESSION['pfy.dev']);
        }
        self::determineDevState();
    } // resetDevState


    /**
     * @return void
     */
    public static function prepareDataPaths(): void
    {
        // in productive mode, if config option is set, override $dataPath and $customConfigPath:
        if (PageFactory::$productionMode) {
            $dataPath = kirby()->option('pgfactory.pagefactory.production_mode_data_path');
            if ($dataPath) {
                $dataPath = normalizePath(PFY_APP_BASE_PATH . $dataPath);
                PageFactory::$dataPath = $dataPath . 'data/';
                PageFactory::$customConfigPath = $dataPath . 'config/';
            }
        }
        preparePath(PageFactory::$dataPath);
        preparePath(PageFactory::$customConfigPath);
    } // prepareDataPaths


    /**
     * Optains config values from Kirby and adds values from site/site.txt
     * @return void
     */
    public static function loadPfyConfig():void
    {
        $optionsFromConfigFile = kirby()->option('pgfactory.pagefactory');
        if ($optionsFromConfigFile) {
            PageFactory::$config = array_replace_recursive(OPTIONS_DEFAULTS, $optionsFromConfigFile);
        } else {
            PageFactory::$config = OPTIONS_DEFAULTS;
        }
        PageFactory::$lazyLoading =  $optionsFromConfigFile['lazyLoading'] ?? true;


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
        if (!isset($_GET['ajax'])) {
            $session = kirby()->session();
            if ($msg = $session->pull('pfy.message')) {
                Page::setMessage($msg);
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
        $l = kirby()->option('pgfactory.pagefactory.locale', PFY_DEFAULT_LOCALE);
        if ($l === 'auto') {
            $l = PageFactory::$langCode . '_' . strtoupper(PageFactory::$langCode);
        }
        return $l;
    } // getCurrentLocale


    /**
     * @param mixed|null $datetime
     * @param bool $includeTime
     * @return string
     */
    public static function timeToString(mixed $datetime = null, bool $includeTime = null, int $timeRef = 0): string
    {
        if (!$timeRef) {
            $timeRef = time();
        }
        if ($datetime === null) {
            $datetime = time();
        } elseif (is_string($datetime)) {
            if (preg_match('/(\d{4}-\d\d-\d\d) (\d\d:\d\d)/', $datetime, $m)) {
                $datetime = str_replace($m[0], "{$m[1]}T{$m[1]}", $datetime);
                $includeTime = true;
            } elseif (str_contains($datetime, 'T') && ($includeTime === null)) {
                $includeTime = true;
            }
            $datetime = strtotime($datetime, $timeRef);
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
        if (preg_match("/(['\"]pgfactory.pagefactory['\"]\s*=>\s*\[)/", $config, $m)) {
            $str = "\n\t\t'$key'\t\t=> '$value',$comment,";
            $config = str_replace($m[0], $m[0].$str, $config);
            file_put_contents(PFY_CONFIG_FILE, $config);

        } elseif (preg_match("/(];)/", $config, $m)) {
            $str = <<<EOT

    'pgfactory.pagefactory' => [
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
        $groupFilter = strtolower($options['role']??'');
        $selector = $options['selector']??false;
        $selectorOp = $options['selectorOp']??'!=';
        $selectorValue = $options['selectorValue']??'';
        $reversed = $options['reversed']??false;
        $sort = $options['sort']??false;
        $blueprintFile = $options['blueprintFile']??'site/blueprints/users/default.yml';
        $labels = self::getUserRecLabels($blueprintFile);

        $users = kirby()->users();
        if ($groupFilter) {
            if (str_contains($groupFilter, ',')) { // multiple roles
                $groups = explodeTrim(',', $groupFilter);
                $users = $users->filter(function ($user) use ($groups) {
                    $role = $user->role()->name();
                    return in_array($role, $groups);
                });

            } else { // single role requested
                $users = $users->filter('role', $groupFilter);
            }
        }
        if ($selector) {
            $users = $users->filterBy($selector, $selectorOp, $selectorValue);
        }
        $users = $users->sortBy('name');

        $usersArray = [];
        foreach ($users as $user) {
            $content = $user->content()->data();
            $rec = [
                $labels['username'] =>  (string)$user->name(),
                $labels['email'] =>  (string)$user->email(),
                $labels['group'] => (string)$user->role()->name(),
                ];
            if ($content) {
                foreach ($content as $k => $v) {
                    if (is_string($v) && ($k !== 'accesscode')) {
                        $k = isset($labels[$k]) ?$labels[$k]: $k;
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
                    $aa[] = $a[$e]??'';
                    $bb[] = $b[$e]??'';
                }
                return $aa <=> $bb;
            });
        }

        if ($reversed) {
            $usersArray = array_reverse($usersArray);
        }

        return $usersArray;
    } // getUsers


    /**
     * @param string $blueprintFile
     * @return array
     * @throws Kirby\Exception\InvalidArgumentException
     */
    private static function getUserRecLabels(string $blueprintFile): array
    {
        $array = loadFile($blueprintFile);
        $userLabels = [];
        $userLabels['username'] = 'Username';
        $userLabels['email'] = 'E-Mail';
        $userLabels['group'] = 'Group';
        $userLabels += self::_getUserRecLabels($array);
        return $userLabels;
    } // getUserRecLabels

    /**
     * @param array $array
     * @return array
     */
    private static function _getUserRecLabels(array $array): array
    {
        $userLabels = [];
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                if (isset($v['label'])) {
                    $userLabels[$k] = $v['label'];
                } else {
                    $userLabels += self::_getUserRecLabels($v);
                }
            }
        }
        return $userLabels;
    } // getUserRecLabels


    /**
     * @return void
     * @throws Exception
     */
    private static function setInstallationCheckFile(): void
    {
        writeFile(PFY_INSTALLATION_PATH_CHECK, PFY_APP_BASE_PATH);
    } // setInstallationCheckFile

} // Utils
