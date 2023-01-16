<?php

namespace Usility\PageFactory;

use Kirby\Data\Yaml;
use Kirby\Exception\InvalidArgumentException;

class TransVars
{
    public static array $variables = [];
    public static array $transVars = [];
    public static array $funcIndexes = [];
    public static bool $noTranslate = false;
    private static string $lang;
    private static string $langCode;

    /**
     * Initializes TransVar
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function init(): void
    {
        // determine currently active language:
        $lang = kirby()->language() ?? kirby()->defaultLanguage();
        $lang = $lang? $lang->code() : 'en';
        self::$lang = PageFactory::$lang ?: $lang;
        self::$langCode = PageFactory::$langCode ?: $lang;

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

        $fields = PageFactory::$page->content()->fields();
        foreach ($fields as $key => $field) {
            if (!str_ends_with($key, '_md')) {
                self::$transVars[$key] = $field->value();
            }
        }

        // compile currently active set of variables and their values:
        foreach (self::$transVars as $key => $rec) {
            self::$variables[camelCase($key)] = self::translateVariable($key);
        }

        self::loadMacros();
    } // init




    /**
     * Assign value to a variable
     * @param string $varName
     * @param mixed $value
     * @return string
     */
    public static function setVariable(string $varName, mixed $value):string
    {
        $varName = camelCase($varName);
        self::$transVars[$varName] = $value;

        if (is_object($value)) {
            $value = (string) $value;
        } elseif (is_array($value)) {
            $value = self::translateVariable($varName);
        }
        self::$variables[$varName] = $value;
        PageFactory::$page->$varName()->value = $value;
        return $value;
    } // setVariable


    /**
     * Get value of variable
     * @param string $varName0
     * @param bool $varNameIfNotFound
     * @return string
     */
    public static function getVariable(string $varName0, bool $varNameIfNotFound = false): mixed
    {
        $varName = camelCase($varName0);
        $out = null;
        if (!isset(self::$variables[$varName])) {
            if (PageFactory::$page->$varName()) {
                $out = PageFactory::$page->$varName()->value;
            }
        } else {
            $out = self::$variables[$varName];
        }
        if ($out === null) {
            $out = $varNameIfNotFound ? $varName0: '';
        } elseif (is_array($out)) {
            $out = reset($out);
        }
        return $out;
    } // getVariable


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
        $kirbyPageTitle = self::setVariable('title', PageFactory::$page->title());
        $kirbySiteTitle = self::setVariable('site', site()->title());
        self::setVariable('headTitle', "$kirbyPageTitle / $kirbySiteTitle");

        // 'generator': we cache the gitTag, so, if that changes you need to remember to clear site/cache/pagefactory
        $gitTag = fileGetContents(PFY_CACHE_PATH.'gitTag.txt');
        if (!$gitTag) {
            $gitTag = getGitTag();
            file_put_contents(PFY_CACHE_PATH.'gitTag.txt', $gitTag);
        }
        self::setVariable('generator', 'Kirby v'. kirby()::version(). " + PageFactory $gitTag");


        $appUrl = PageFactory::$appUrl;
        $menuIcon         = svg('site/plugins/pagefactory/assets/icons/menu.svg');
        self::setVariable('menuIcon',$menuIcon);
        $smallScreenHeader = self::$variables['smallScreenHeader']?? site()->title()->value();
        self::setVariable('smallScreenHeader', "\n\t<h1>$smallScreenHeader</h1>\n".
            "\t<button id='pfy-nav-menu-icon'>$menuIcon</button>\n");

        self::setVariable('langSelection', self::renderLanguageSelector());
        self::setVariable('pageUrl', PageFactory::$pageUrl);
        self::setVariable('appUrl', $appUrl);
        self::setVariable('lang', PageFactory::$langCode);
        self::setVariable('langActive', PageFactory::$lang); // can be lang-variant, e.g. de2
        self::setVariable('phpVersion', phpversion());

        // default webmaster email derived from current domain:
        self::setVariable('webmasterEmail', 'webmaster@'.preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url()));



        // Copy site field values to transvars:
        $siteAttributes = site()->content()->data();
        foreach ($siteAttributes as $key => $value) {
            if ($key === 'title') {
                continue;
            }
            self::setVariable($key, $value);
        }

        // Copy page field values to transvars:
        $pageAttributes = page()->content()->data();
        foreach ($pageAttributes as $key => $value) {
            if (str_contains(',title,text,', ",$key,") || str_ends_with($key, '_md')) {
                continue;
            } elseif ($key === 'variables') {
                $values = Yaml::decode($value);
                foreach ($values as $k => $v) {
                    self::setVariable($k, $v);
                }
            } else {
                self::setVariable($key, (string)$value);
            }
        }

        $user = kirby()->user();
        if ($user) {
            self::setVariable('loggedInAsUser', (string)$user->nameOrEmail());
        } else {
            self::setVariable('loggedInAsUser', "<a href='{$appUrl}?login'>Login</a>");
        }

        $pfyLoginButtonLabel = self::getVariable('pfy-login-button-label');
        $loginIcon = svg('site/plugins/pagefactory/assets/icons/user.svg');
        self::setVariable('loginButton', "<button class='pfy-login-button' title='$pfyLoginButtonLabel'>$loginIcon</button>");

        $pfyAdminPanelLinkText = self::getVariable('pfy-admin-panel-link-text');
        self::setVariable('adminPanelLink', "<a href='{$appUrl}panel' target='_blank'>$pfyAdminPanelLinkText</a>");
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
    public static function prepareTemplateVariables(): void
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
    public static function renderLanguageSelector(): string
    {
        $out = '';
        if (sizeof(PageFactory::$supportedLanguages) > 1) {
            foreach (PageFactory::$supportedLanguages as $lang) {
                $langCode = substr($lang, 0, 2);
                $text = self::getVariable("pfy-lang-select-$langCode");
                if ($lang === PageFactory::$lang) {
                    $out .= "<span class='pfy-lang-elem pfy-active-lang $langCode'><span>$text</span></span> ";
                } else {
                    $title = self::getVariable("pfy-lang-select-title-$langCode");
                    $out .= "<span class='pfy-lang-elem $langCode'><a href='?lang=$lang' title='$title'>$text</a></span> ";
                }
            }
            $out = "\t<span class='pfy-lang-selection'>$out</span>\n";
        }
        return $out;
    } // renderLanguageSelector


    public static function translate($str): string
    {
        return self::translateVariable($str);
    } // translate


    /**
     * @param string $varName
     * @return string|bool
     */
    private static function translateVariable(string $varName): mixed
    {
        $varName = trim($varName);
        $out = false;
        // find variable definition:
        if (isset(self::$transVars[ $varName ])) {
            $out = self::$transVars[ $varName ];
            // if value is array -> determine which to use depending on current language/variant:
            if (is_array($out)) {
                if (isset($out[self::$lang])) {             // check language-variant (e.g. de2)
                    $out = $out[self::$lang];
                } elseif (isset($out[self::$langCode])) {   // check base language (e.g. de)
                    $out = $out[self::$langCode];
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
     * Returns list of all variables as presentable HTML
     * @return string
     */
    public static function renderVariables(): string
    {
        $variables = self::$transVars;

        ksort($variables);
        $html = "<dl class='pfy-list-variables'>\n";
        foreach ($variables as $key => $value) {
            if (($key === 'text') || str_ends_with($key, '_md')) {
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


    /**
     * Replaces all occurences of {{ }} patterns with variable contents.
     * @param string $str
     * @return string
     */
    public static function resolveVariables(string $str): string
    {
        while (preg_match('/(?<!\\\)\{\{ \s* ([-\w]*?) \s* }}/x', $str, $m)) {
            $key = $m[1];
            if (self::$noTranslate) {
                $value = "<span class='pfy-untranslated'>&#123;&#123; $key &#125;&#125;</span>";;
            } else {
                $value = self::getVariable($key);
            }
            $str = str_replace($m[0], $value, $str);
        }
        return $str;
    } // resolveVariables


    public static function executeMacros($str)
    {
        list($p1, $p2) = strPosMatching($str);
        while ($p1 !== false) {
            $cand = trim(substr($str, ($p1+2), ($p2-$p1-2)));
            if (preg_match('/(\w+) \( (.*) \)/xms', $cand, $m)) {
                $macroName = $m[1];
                $argStr = $m[2];
                if (str_contains(',list,', $macroName)) {
                    $macroName = "_$macroName";
                }
                if (function_exists("Usility\\PageFactory\\$macroName")) {
                    $value = "Usility\\PageFactory\\$macroName"($argStr);
                } else {
                    $value = "\\{{ $cand }}";
                }
            } else {
                if (self::$noTranslate) {
                    $value = "<span class='pfy-untranslated'>&#123;&#123; $cand &#125;&#125;</span>";;
                } else {
                    $value = self::getVariable($cand);
                }
            }
            $str = substr($str, 0, $p1) . $value . substr($str, $p2+2);
            list($p1, $p2) = strPosMatching($str, $p1);
        }
        return $str;
    } // executeMacros


    /**
     * @return void
     */
    private static function loadMacros(): void
    {
        $dir = getDir(PFY_MACROS_PATH.'*.php');
        foreach ($dir as $file) {
            if (basename($file)[0] !== '#') {
                require_once $file;
            }
        }
    } // loadMacros



    /**
     * if ?notranslate is active, all variables are replaced with untranslated var-names
     * @return void
     */
    public static function lastPreparations()
    {
        if (self::$noTranslate) {
            foreach (self::$transVars as $varName => $rec) {
                self::$variables[camelCase($varName)] =
                    "<span class='pfy-untranslated'>&#123;&#123; $varName &#125;&#125;</span>";
            }
        }
    } // lastPreparations



    /**
     * Helper for twig-functions -> prepares options, handles special cases: help, showSource, ?notranslate
     * @param string $file
     * @param array $config
     * @param string $args
     * @return string|array
     * @throws InvalidArgumentException
     */
    public static function initMacro(string $file, array $config, string $args): string|array
    {
        $macroName = basename($file, '.php');
        // render help text:
        if ($args === 'help') {
            return self::renderMacroHelp($config);

            // render as unprocessed (?notranslate):
        } elseif (TransVars::$noTranslate) {
            $macroName1 = ltrim($macroName, '_');
            return "<span class='pfy-untranslated'>&#123;&#123; $macroName1('$args') &#125;&#125;</span>";
        }

        // get arguments:
        $options = self::parseMacroArguments($config, $args);

        // get index:
        $inx = TransVars::$funcIndexes[$macroName] = (TransVars::$funcIndexes[$macroName]??false) ?
            TransVars::$funcIndexes[$macroName]+1: 1;

        $str = '';
        if ($options['showSource']??false) {
            $multiline = str_contains($args, "\n")? "\n        " : '';
            $multiline2 = $multiline? "\n    " : ' ';
            $args = preg_replace('/showSource:[^,\n]+/', '', $args);
            $args = preg_replace('/\n\s*\n/', "\n", $args);
            if ($multiline) {
                $args = indentLines($args);
            } else {
                $args = rtrim($args, ' ,');
            }
            $macroName1 = ltrim($macroName, '_');
            $str = shieldStr("<pre><code>\\{{ $macroName1('$args$multiline')$multiline2}}\n</code></pre>")."\n\n";
        }
        return [$options, $str, $inx, $macroName];
    } // initMacro


    /**
     * Renders Help output for given twig-function
     * @param array $config
     * @param bool $mdCompile
     * @return string
     */
    private static function renderMacroHelp(array $config, bool $mdCompile = true): string
    {
        $str = "<div class='pfy-help pfy-encapsulated'>\n";
        $summary = $mdCompile? markdown($config['summary'] ?? '') : '';
        $str .= "<div class='pfy-help-summary'>$summary</div>\n";
        $str .= "<h2>Arguments</h2>\n";
        foreach ($config['options'] as $key => $elem) {
            $text = $elem[0];
            $text = markdown($text, true);
            $default = $elem[1]??'false';
            if (is_bool($default)) {
                $default = $default? 'true': 'false';
            }
            $str .= "<dl class='pfy-help-argument-list'>\n";
            $str .= <<<EOT
    <dt>$key</dt>
        <dd>$text (default: $default)</dd>
EOT;
        }
        $str .= "\n</dl>\n";
        $str .= "</div>\n";
        return $str;
    } // renderMacroHelp


    /**
     * Parses argument string of twig-functions, returns as $options array
     * @param array $config
     * @param mixed $args
     * @return array
     * @throws InvalidArgumentException
     */
    private static function parseMacroArguments(array $config, mixed $args): array
    {
        if (is_array($args)) {
            $options = $args;
        } else {
            $args = unshieldStr($args, true);
            $args = preg_replace('/^(["\'])(.*)\1$/', "$2", $args);
            $options = parseArgumentStr($args);
        }
        foreach ($config['options'] as $key => $value) {
            if (!isset($options[$key])) {
                $options[$key] = $value[1];
            }
        }
        foreach ($options as $key => $value) {
            if (is_int($key)) {
                $key = array_keys($config['options'])[$key];
                $options[$key] = $value;
            }
        }
        return $options;
    } // parseTwigFunctionArguments



    private static function findAllMacros(): array
    {
        $functions = findTwigFunctions();
        $pfyPlugins = glob('site/plugins/pagefactory*');
        foreach ($pfyPlugins as $plugin) {
            $dir = glob("$plugin/macros/*.php");
            foreach ($dir as $file) {
                $actualName = basename($file, '.php');
                if ($actualName[0] !== '#') {
                    $functions[] = ltrim($actualName, '_');
                }
            }
        }
        return $functions;
    } // findAllMacros


    /**
     * Renders a list of all available macros as presentable HTML
     * @return string
     */
    public static function renderMacros(): string
    {
        $functions = self::findAllMacros();
        $html = "<ul class='pfy-list-functions'>\n";
        foreach ($functions as $name) {
            $html .= "<li>$name()</li>\n";
        }
        $html .= "<ul>\n";
        return $html;
    } // renderTwigFunctions


} // TwigVars