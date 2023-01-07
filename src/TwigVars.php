<?php

namespace Usility\PageFactory;

use Kirby\Data\Yaml;

class TwigVars
{
    public static $variables = [];
    public static $transVars = [];
    public static $funcIndexes = [];
    public static $noTranslate = false;
    private $lang;
    private $langCode;

    /**
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public function __construct()
    {
        // determine currently active language:
        $lang = kirby()->language()->code() ?: (kirby()->defaultLanguage()->code() ?: 'en');
        $this->lang = PageFactory::$lang ?: $lang;
        $this->langCode = PageFactory::$langCode ?: $lang;

        // load variable definitions from specified folders:
        $varLocations = [
            'site/plugins/pagefactory/variables/',
            'site/custom/variables/',
        ];
        foreach ($varLocations as $dir) {
            $files = getDir("$dir*.yaml");
            foreach ($files as $file) {
                $transVars = loadFile($file);
                if ($transVars) {
                    self::$transVars = array_merge_recursive(self::$transVars, $transVars);
                }
            }
        }

        // obtain variable definitions from page meta file:
        if ($varStr = (PageFactory::$page->variables()->value() ?? '')) {
            $vars = Yaml::decode($varStr);
            self::$transVars = array_merge_recursive(self::$transVars, $vars);
        }

        // compile currently active set of variables and their values:
        foreach (self::$transVars as $key => $rec) {
            self::$variables[camelCase($key)] = $this->translateVariable($key);
        }

    } // __construct


    public function init()
    {
        if (self::$noTranslate) {
            foreach (self::$transVars as $key => $rec) {
                self::$variables[camelCase($key)] = "&#123;&#123; $key &#125;&#125;";
            }
        }
    } // init



    /**
     * Assign value to a variable
     * @param string $varName
     * @param string $value
     * @return void
     */
    public function setVariable(string $varName, string $value):void
    {
        $varName = camelCase($varName);
        PageFactory::$page->$varName()->value = $value;
        self::$variables[$varName] = $value;
    } // setVariable


    /**
     * Get value of variable
     * @param string $varName
     * @return string
     */
    public function getVariable(string $varName0): mixed
    {
        $varName = camelCase($varName0);
        $out = self::$variables[$varName] ?? PageFactory::$page->$varName()->value;
        if ($out === null) {
            $out = $varName0;
        } elseif (is_array($out)) {
            $out = reset($out);
        }
        return $out;
//        return self::$variables[$varName] ?? PageFactory::$page->$varName()->value;
    } // getVariable


    /**
     * Return array of all variables
     * @return mixed
     */
    public function renderVariables(): mixed
    {
        $variables = self::$transVars;
        $fields = PageFactory::$page->content()->fields();
        foreach ($fields as $key => $field) {
            $variables[$key] = $field->value();
        }

        ksort($variables);
        $html = "<dl class='pfy-list-variables'>\n";
        foreach ($variables as $key => $value) {
            if ($key === 'text') {
                $value = '[skipped]';
            }
            if (is_array($value)) {
                $tmp = '';
                foreach ($value as $lang => $val) {
                    $val = htmlentities($val);
                    $tmp .= "\t<div><span>$lang</span>: <span>$val</span></div>\n";
                }
                $value = $tmp;
            } else {
                $value = htmlentities($value);
            }
            $html .= "<dt>$key</dt><dd>$value</dd>\n";
        }
        $html .= "<dl>\n";
        return $html;

    } // renderVariables



    public function getVariables(): array
    {
        $variables = self::$variables;
        $fields = PageFactory::$page->content()->fields();
        foreach ($fields as $key => $field) {
            $variables[$key] = $field->value();
        }
        return $variables;
    } // getVariables


    /**
     * Assign values to variables that are used in templates or in page content
     *  - lang
     *  - langActive
     *  - pageUrl
     *  - phpVersion
     *  - generator
     *  - headTitle
     *  - smallScreenHeader
     *  - pfyLangSelection
     *  - user
     *  - pfyLoginButton
     *  - pfyLoggedInAsUser
     *  - pfyLoginButton
     *  - pfyAdminPanelLink
     *      plus all fields defined for site, in particular 'kirbySiteTitle'
     * Note: variables used inside page content are handled/replaced elsewhere
     * @return void
     * @throws \Kirby\Exception\LogicException
     */
    public function prepareStandardVariables(): void
    {
        PageFactory::$page->headTitle()->value = PageFactory::$page->title() . ' / ' . site()->title();
        // 'generator':
        // for performance reasons we cache the gitTag, so, if that changes you need to remember to clear site/cache/pagefactory
        $gitTag = fileGetContents(PFY_CACHE_PATH.'gitTag.txt');
        if (!$gitTag) {
            $gitTag = getGitTag();
            file_put_contents(PFY_CACHE_PATH.'gitTag.txt', $gitTag);
        }
        PageFactory::$page->generator()->value = 'Kirby v'. kirby()::version(). " + PageFactory $gitTag";

        $smallScreenHeader = self::$variables['smallScreenHeader']?? site()->title()->value();
        PageFactory::$page->smallScreenHeader()->value  = "\n<h1>$smallScreenHeader</h1>\n";

        PageFactory::$page->pfyLangSelection()->value   = $this->renderLanguageSelector();
        PageFactory::$page->pageUrl()->value            = PageFactory::$pageUrl;
        PageFactory::$page->lang()->value               = PageFactory::$langCode;
        PageFactory::$page->langActive()->value         = PageFactory::$lang; // can be lang-variant, e.g. de2
        PageFactory::$page->phpVersion()->value         = phpversion();

        // Copy site field values to transvars:
        $siteAttributes = site()->content()->data();
        foreach ($siteAttributes as $key => $value) {
            if ($key === 'title') {
                $key = 'kirby-site-title';
            }
            $key = camelCase($key);
            PageFactory::$page->$key()->value = (string)$value;
        }

        // 'user', 'pfy-logged-in-as-user', 'pfy-admin-panel-link':
        $appUrl = PageFactory::$appUrl;
        $loginIcon = svg('site/plugins/pagefactory/assets/icons/user.svg');
        $user = kirby()->user();
        if ($user) {
            $username = (string)$user->nameOrEmail();
            PageFactory::$page->user()->value = $username;
            $pfyEditUserAccount = $this->getVariable('pfy-edit-user-account');
            PageFactory::$page->pfyLoginButton()->value = "<button class='pfy-login-button' title='$pfyEditUserAccount'>$loginIcon</span></button>";
        } else {
            PageFactory::$page->pfyLoggedInAsUser()->value = "<a href='{$appUrl}?login'>Login</a>";
            $pfyLoginButtonLabel = $this->getVariable('pfy-login-button-label');
            PageFactory::$page->pfyLoginButton()->value = "<button class='pfy-login-button' title='$pfyLoginButtonLabel'>$loginIcon</button>";
        }
        $pfyAdminPanelLinkText = $this->getVariable('pfy-admin-panel-link-text');
        PageFactory::$page->pfyAdminPanelLink()->value = "<a href='{$appUrl}panel' target='_blank'>$pfyAdminPanelLinkText</a>";
    } // prepareStandardVariables



    /**
     * Assign values to variables that are used directly in templates (i.e. outside of page content)
     *  - headInjections
     *  - bodyTagClasses
     *  - bodyTagAttributes
     *  - bodyEndInjections
     * @return void
     * @throws \Kirby\Exception\LogicException
     */
    public function prepareTemplateVariables(): void
    {
        PageFactory::$page->headInjections()->value     = PageFactory::$pg->renderHeadInjections();

        $bodyTagClasses   = PageFactory::$pg->bodyTagClasses ?: 'pfy-large-screen';
        if (isAdmin()) {
            $bodyTagClasses .= ' pfy-admin';
        } elseif (kirby()->user()) {
            $bodyTagClasses .= ' pfy-loggedin';
        }
        if (PageFactory::$debug) {
            $bodyTagClasses = trim("debug $bodyTagClasses");
        }
        PageFactory::$page->bodyTagClasses()->value     = $bodyTagClasses;

        PageFactory::$page->bodyTagAttributes()->value  = PageFactory::$pg->bodyTagAttributes;
        PageFactory::$page->bodyEndInjections()->value  = PageFactory::$pg->renderBodyEndInjections();
    } // prepareTemplateVariables



    /**
     * Defines variable 'pfy-lang-selection', which expands to a language selection block,
     * one language icon per supported language
     */
    public function renderLanguageSelector(): string
    {
        $out = '';
        if (sizeof(PageFactory::$supportedLanguages) > 1) {
            foreach (PageFactory::$supportedLanguages as $lang) {
                $langCode = substr($lang, 0, 2);
                $text = $this->getVariable("pfy-lang-select-$langCode");
                if ($lang === PageFactory::$lang) {
                    $out .= "<span class='pfy-lang-elem pfy-active-lang $langCode'><span>$text</span></span> ";
                } else {
                    $title = $this->getVariable("pfy-lang-select-title-$langCode");
                    $out .= "<span class='pfy-lang-elem $langCode'><a href='?lang=$lang' title='$title'>$text</a></span> ";
                }
            }
            $out = "\t<span class='pfy-lang-selection'>$out</span>\n";
        }
        return $out;
    } // renderLanguageSelector


    /**
     * @param string $varName
     * @return string|bool
     */
    private function translateVariable(string $varName): mixed
    {
        $varName = trim($varName);
        $out = false;
        // find variable definition:
        if (isset(self::$transVars[ $varName ])) {
            $out = self::$transVars[ $varName ];
            // if value is array -> determine which to use depending on current language/variant:
            if (is_array($out)) {
                if (isset($out[$this->lang])) {             // check language-variant (e.g. de2)
                    $out = $out[$this->lang];
                } elseif (isset($out[$this->langCode])) {   // check base language (e.g. de)
                    $out = $out[$this->langCode];
                } elseif (isset($out['_'])) {               // check default language
                    $out = $out['_'];
                } else {
                    $out = false;                           // nothing found
                }
            }
        }
        return $out;
    } // translateVariable

} // TwigVars