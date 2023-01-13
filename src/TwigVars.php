<?php

namespace Usility\PageFactory;

use Kirby\Data\Yaml;

class TwigVars
{
    public static array $variables = [];
    public static array $transVars = [];
    public static array $funcIndexes = [];
    public static bool $noTranslate = false;
    private string $lang;
    private string $langCode;

    /**
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public function __construct()
    {
        // determine currently active language:
        $lang = kirby()->language() ?? kirby()->defaultLanguage();
        $lang = $lang? $lang->code() : 'en';
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

        // compile currently active set of variables and their values:
        foreach (self::$transVars as $key => $rec) {
            self::$variables[camelCase($key)] = $this->translateVariable($key);
        }
    } // __construct


    /**
     * Assign value to a variable
     * @param string $varName
     * @param mixed $value
     * @return string
     */
    public function setVariable(string $varName, mixed $value):string
    {
        $varName = camelCase($varName);
        self::$transVars[$varName] = $value;

        if (is_object($value)) {
            $value = (string) $value;
        } elseif (is_array($value)) {
            $value = $this->translateVariable($varName);
        }
        self::$variables[$varName] = $value;
        PageFactory::$page->$varName()->value = $value;
        return $value;
    } // setVariable


    /**
     * Get value of variable
     * @param string $varName0
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
    } // getVariable


    /**
     * Return array of all variables as presentable HTML
     * @return string
     */
    public function renderVariables(): string
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


    // Returns array of all variables
    /**
     * @return array
     */
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
    public function prepareStandardVariables(): void
    {
        $kirbyPageTitle = $this->setVariable('title', PageFactory::$page->title());
        $kirbySiteTitle = $this->setVariable('site', site()->title());
        $this->setVariable('headTitle', "$kirbyPageTitle / $kirbySiteTitle");

        // 'generator': we cache the gitTag, so, if that changes you need to remember to clear site/cache/pagefactory
        $gitTag = fileGetContents(PFY_CACHE_PATH.'gitTag.txt');
        if (!$gitTag) {
            $gitTag = getGitTag();
            file_put_contents(PFY_CACHE_PATH.'gitTag.txt', $gitTag);
        }
        $this->setVariable('generator', 'Kirby v'. kirby()::version(). " + PageFactory $gitTag");


        $appUrl = PageFactory::$appUrl;
        $menuIcon         = svg('site/plugins/pagefactory/assets/icons/menu.svg');
        $this->setVariable('menuIcon',$menuIcon);
        $smallScreenHeader = self::$variables['smallScreenHeader']?? site()->title()->value();
        $this->setVariable('smallScreenHeader', "\n\t<h1>$smallScreenHeader</h1>\n".
            "\t<button id='pfy-nav-menu-icon'>$menuIcon</button>\n");

        $this->setVariable('langSelection', $this->renderLanguageSelector());
        $this->setVariable('pageUrl', PageFactory::$pageUrl);
        $this->setVariable('appUrl', $appUrl);
        $this->setVariable('lang', PageFactory::$langCode);
        $this->setVariable('langActive', PageFactory::$lang); // can be lang-variant, e.g. de2
        $this->setVariable('phpVersion', phpversion());

        // default webmaster email derived from current domain:
        $this->setVariable('webmasterEmail', 'webmaster@'.preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url()));



        // Copy site field values to transvars:
        $siteAttributes = site()->content()->data();
        foreach ($siteAttributes as $key => $value) {
            if ($key === 'title') {
                continue;
            }
            $this->setVariable($key, $value);
        }

        // Copy page field values to transvars:
        $pageAttributes = page()->content()->data();
        foreach ($pageAttributes as $key => $value) {
            if (str_contains(',title,text,', ",$key,")) {
                continue;
            } elseif ($key === 'variables') {
                $values = Yaml::decode($value);
                foreach ($values as $k => $v) {
                    $this->setVariable($k, $v);
                }
            } else {
                $this->setVariable($key, (string)$value);
            }
        }

        $user = kirby()->user();
        if ($user) {
            $this->setVariable('loggedInAsUser', (string)$user->nameOrEmail());
        } else {
            $this->setVariable('loggedInAsUser', "<a href='{$appUrl}?login'>Login</a>");
        }

        $pfyLoginButtonLabel = $this->getVariable('pfy-login-button-label');
        $loginIcon = svg('site/plugins/pagefactory/assets/icons/user.svg');
        $this->setVariable('loginButton', "<button class='pfy-login-button' title='$pfyLoginButtonLabel'>$loginIcon</button>");

        $pfyAdminPanelLinkText = $this->getVariable('pfy-admin-panel-link-text');
        $this->setVariable('adminPanelLink', "<a href='{$appUrl}panel' target='_blank'>$pfyAdminPanelLinkText</a>");
    } // prepareStandardVariables



    /**
     * Assign values to variables that are used directly in templates (i.e. outside of page content)
     *  - headInjections
     *  - bodyTagClasses
     *  - bodyTagAttributes
     *  - bodyEndInjections
     * @return void
     * @throws \Kirby\Exception\LogicException|\ScssPhp\ScssPhp\Exception\SassException
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


    /**
     * Replaces all occurences of {{ }} patterns with variable contents.
     * @param string $str
     * @return string
     */
    public function resolveVariables(string $str): string
    {
        while (preg_match('/\{\{ \s* (.*?) \s* }}/x', $str, $m)) {
            $key = $m[1];
            if (self::$noTranslate) {
                $value = "<span class='pfy-untranslated'>&#123;&#123; $key &#125;&#125;</span>";;
            } else {
                $value = $this->getVariable($key);
            }
            $str = str_replace($m[0], $value, $str);
        }
        return $str;
    } // resolveVariables



    /**
     * if ?notranslate is active, all variables are replaced with untranslated var-names
     * @return void
     */
    public function lastPreparations()
    {
        if (self::$noTranslate) {
            foreach (self::$transVars as $varName => $rec) {
                self::$variables[camelCase($varName)] =
                    "<span class='pfy-untranslated'>&#123;&#123; $varName &#125;&#125;</span>";
            }
        }
    } // lastPreparations

} // TwigVars