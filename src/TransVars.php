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
    public $value;

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
            if (is_array($files)) {
                foreach ($files as $file) {
                    self::loadVariables($file);
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
     * Loads variables from given file
     * @param string $file
     * @return void
     * @throws InvalidArgumentException
     */
    public static function loadVariables(string $file, bool $doTranslate = false): void
    {
        $transVars = loadFile($file);
        if ($transVars) {
            self::$transVars = array_merge_recursive(self::$transVars, $transVars);

            if ($doTranslate) {
                foreach ($transVars as $key => $rec) {
                    self::$variables[camelCase($key)] = self::translateVariable($key);
                }
            }
        }
    } // loadVariables



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
    public static function getVariable(string $varName0, bool $varNameIfNotFound = false, string $lang = ''): mixed
    {
        $varName = camelCase($varName0);
        $out = null;

        // check for lang-selector, e.g. 'varname.de':
        if (preg_match('/(.*)\.(\w+)$/', $varName, $m)) {
            $varName = $m[1];
            $lang = $m[2]?:$lang;
            $out = self::translateVariable($varName, $lang);
            if ($out === false) {
                $varName1 = preg_replace('/\.\w+$/', '', $varName0);
                $out = self::translateVariable($varName1, $lang);
            }
        } else {
            if (!isset(self::$variables[$varName])) {
                try {
                    $out = PageFactory::$page->$varName()->value; // try to get Kirby field
                } catch (\Exception $e) {
                    $out = $varName;
                }
            } else {
                $out = self::$variables[$varName];
            }
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
        self::setVariable('kirbyPageTitle', $kirbyPageTitle);
        $kirbySiteTitle = self::setVariable('siteTitle', site()->title());
        self::setVariable('kirbySiteTitle', $kirbySiteTitle);
        $headTitle = self::getVariable('headTitle');
        if (!$headTitle) {
            $headTitle = "$kirbyPageTitle / $kirbySiteTitle";
        } else {
            $headTitle = self::translate($headTitle);
        }
        self::setVariable('headTitle', $headTitle);

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
        $smallScreenTitle = self::$variables['smallScreenHeader']?? site()->title()->value();
        $smallScreenHeader = <<<EOT

<div class="pfy-small-screen-header pfy-small-screen-only">
    <h1>$smallScreenTitle</h1>
    <button id='pfy-nav-menu-icon' type="button">$menuIcon</button>
</div>
EOT;

        self::setVariable('smallScreenHeader', $smallScreenHeader);

        self::setVariable('langSelection', self::renderLanguageSelector());
        self::setVariable('pageUrl', PageFactory::$pageUrl);
        self::setVariable('appUrl', $appUrl);
        self::setVariable('hostUrl', PageFactory::$hostUrl);
        self::setVariable('lang', PageFactory::$langCode);
        self::setVariable('langActive', PageFactory::$lang); // can be lang-variant, e.g. de2
        self::setVariable('phpVersion', phpversion());

        $webmasterEmail = self::getVariable('webmaster-email');
        if ($webmasterEmail) {
            PageFactory::$webmasterEmail = $webmasterEmail;
        } else {
            // default webmaster email derived from current domain:
            PageFactory::$webmasterEmail = $webmasterEmail = 'webmaster@' . preg_replace('|^https?://([\w.-]+)(.*)|', "$1", site()->url());
            self::setVariable('webmaster-email', $webmasterEmail);
        }
        $lnk = new Link();
        $webmasterLink = $lnk->render([
            'url' => "mailto:$webmasterEmail",
        ]);
        self::setVariable('webmaster_link', $webmasterLink);


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
            if (str_contains(',title,text,uuid,accesscodes,', ",$key,") || str_ends_with($key, '_md')) {
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
            $out = "<span class='pfy-lang-selection'>$out</span>\n";
        }
        return $out;
    } // renderLanguageSelector


    /**
     * @param string $varName
     * @param string $lang
     * @return string|bool
     */
    private static function translateVariable(string $varName, string $lang = ''): mixed
    {
        if (!$lang) {
            $lang = self::$lang;
        }
        $varName = trim($varName);
        $out = false;
        // find variable definition:
        if (isset(self::$transVars[ $varName ])) {
            $out = self::$transVars[ $varName ];
            // if value is array -> determine which to use depending on current language/variant:
            if (is_array($out)) {
                if (isset($out[$lang])) {             // check language-variant (e.g. de2)
                    $out = $out[$lang];
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
     * @param $str
     * @return string
     */
    public static function translate($str, string $lang = ''): string
    {
        return self::resolveVariables($str, $lang);
    } // translate


    /**
     * Replaces all occurences of {{ }} patterns with variable contents.
     * @param string $str
     * @return string
     */
    public static function resolveVariables(string $str, string $lang = ''): string
    {
        // calls containing increment/decrement, e.g. {{ n++ }}
        list($p1, $p2) = strPosMatching($str);
        while ($p1 !== false && $p2 !== false) {
            $key = trim(substr($str, $p1+2, $p2-$p1-2));

            // handle '|raw':
            $doShield = false;
            if (preg_match('/ \s* \| \s* raw \s* $/mx', $key, $m)) {
                $doShield = true;
                $key = substr($key, 0, - strlen($m[0]));
            }

            // skip macro() calls:
            if (strpbrk($key, '()')) {
                list($p1, $p2) = strPosMatching($str, $p2);
                continue;
            }

            // catch in-text assignments, e.g. {{ n=3 }}:
            if (preg_match('/^(.*?) = (.*)/', $key, $m)) {
                $key1 = trim($m[1]);
                $value = trim($m[2]);
                self::setVariable($key1, $value);
                $value = "<span class='pfy-transvar-assigned'>$value</span>";

            } else {
                $varNameIfNotFound = true;
                if (($key[0]??false) === '^') {
                    $varNameIfNotFound = false;
                    $key = ltrim($key,'^ ');
                }
                $key1 = str_replace(['++', '--'], '', $key);
                $value = self::getVariable($key1, $varNameIfNotFound, $lang);
                if ($key !== $key1) {
                    $s1 = $s2 = '';
                    if (preg_match('/^(.*?)([-\d.]+)(.*)$/', $value, $m)) {
                        $s1 = $m[1];
                        $s2 = $m[3];
                        $n = $m[2];
                    }
                    if (str_starts_with($key, '++')) { // pre-increase
                        $n++;
                        $value = "$s1$n$s2";
                        self::setVariable($key1, $value);
                    } elseif (str_starts_with($key, '--')) { // pre-decrease
                        $n--;
                        $value = "$s1$n$s2";
                        self::setVariable($key1, $value);
                    } elseif (str_ends_with($key, '++')) { // post-increase
                        $n++;
                        self::setVariable($key1, "$s1$n$s2");
                    } elseif (str_ends_with($key, '--')) { // post-decrease
                        $n--;
                        self::setVariable($key1, "$s1$n$s2");
                    }
                }
            }
            if ($doShield) {
                $value = shieldStr($value, 'i');
            }
            $str = substr($str, 0, $p1).$value.substr($str, $p2+2);
            list($p1, $p2) = strPosMatching($str, $p1);
        }
        return $str;
    } // resolveVariables


    /**
     * @param $str
     * @return string
     * @throws \Exception
     */
    public static function executeMacros($str)
    {
        list($p1, $p2) = strPosMatching($str);
        while ($p1 !== false) {
            $cand = trim(substr($str, ($p1+2), ($p2-$p1-2)));
            $hideIfUnknown = false;
            if ($cand[0] === '^') {
                $hideIfUnknown = true;
                $cand = ltrim($cand,'^ ');
            }

            if (preg_match('/(\w+) \( (.*) \)/xms', $cand, $m)) {
                $macroName = $m[1];
                $argStr = $m[2];
                if (str_contains(',list,', $macroName)) {
                    $macroName = "_$macroName";
                }
                if (function_exists("Usility\\PageFactory\\$macroName")) {
                    $value = "Usility\\PageFactory\\$macroName"($argStr);
                    if (is_array($value)) {
                        $value = shieldStr($value[0], 'inline');
                    }
                } elseif ($hideIfUnknown) {
                    $value = '';
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
        $dir = getDir(PFY_USER_CODE_PATH.'*.php');
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
    public static function initMacro(string $file, array $config, string|array $args): string|array
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

        // get index:
        $inx = TransVars::$funcIndexes[$macroName] = (TransVars::$funcIndexes[$macroName]??false) ?
            TransVars::$funcIndexes[$macroName]+1: 1;

        $src = '';
        if (is_string($args)) {
            // handle showSource:
            list($args, $src) = self::handleShowSource($args, $src, $macroName);

        } elseif (!is_array($args)) {
            throw new \Exception("Macros: unexpected case -> macro arguments neither string nor array.");
        }

        // get arguments:
        $options = self::parseMacroArguments($config, $args);

        $auxOptions = [];
        $supportedKeys = array_keys($config['options']);
        foreach ($options as $key => $value) {
            if (!in_array($key, $supportedKeys)) {
                $auxOptions[$key] = $value;
                unset($options[$key]);
            }
        }

        $options['inx'] = $inx;
        return [$options, $src, $inx, $macroName, $auxOptions];
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
        $str .= "<dl class='pfy-help-argument-list'>\n";
        foreach ($config['options'] as $key => $elem) {
            $text = $elem[0];
            $text = markdown($text, true);
            $text = trim($text);
            $default = $elem[1]??'false';
            if (is_bool($default)) {
                $default = $default? 'true': 'false';
            } elseif (is_array($default)) {
                $default = json_encode($default);
            }
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
            $options = parseArgumentStr("$args,");
        }
        foreach ($config['options'] as $key => $value) {
            $optVal = &$options[$key];
            if (!isset($optVal)) {
                $optVal = $value[1];
            } else {
                $optVal = fixDataType($optVal);
            }
        }
        foreach ($options as $key => $value) {
            if (is_int($key) && $value) {
                $key = array_keys($config['options'])[$key];
                $options[$key] = fixDataType($value);
            }
        }
        return $options;
    } // parseTwigFunctionArguments


    /**
     * Helper for renderMacros()
     * @return array
     */
    public static function findAllMacros(bool $forRegistering = false, bool $includePaths = false, bool $buildInOnly = false): array
    {
        $functions = [];
        if ($buildInOnly) {
            $pfyPlugins = ['site/plugins/pagefactory']; // check pagefactory and its extensions
        } else {
            $pfyPlugins = glob('site/plugins/pagefactory*'); // check pagefactory and its extensions
        }
        $pfyPlugins[] = 'site/custom';                           // check place for custom macros
        foreach ($pfyPlugins as $plugin) {
            $dir = glob("$plugin/macros/*.php");
            foreach ($dir as $file) {
                $actualName = basename($file, '.php');
                if ($actualName[0] !== '#') {
                    if ($forRegistering) {
                        $name = ltrim($actualName, '_');
                        $functions["*$name"] = "Usility\\PageFactory\\$actualName";
                    } else {
                        if ($includePaths) {
                            $functions[] = $file;
                        } else {
                            $functions[] = ltrim($actualName, '_');
                        }
                    }
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

    private static function handleShowSource(string $args, array|string $src, string $macroName): array
    {
        if (preg_match('/,?\s*showSource:\s*true/', $args, $m)) {
            $args = str_replace($m[0], '', $args);
            $src = str_replace(["''", ',,', '->', '<'], ["\\''", "\\,,", "\\->", '&lt;'], $args);
            $src = markdownParagrah($src);

            $multiline = str_contains($src, "\n") ? "\n    " : '';
            $multiline2 = $multiline ? "\n" : ' ';
            $src = preg_replace('/\n\s*\n/', "\n", $src);
            if ($multiline) {
                $src = rtrim($src, "\n\t ");
            } else {
                $src = rtrim($src, ' ,');
            }
            $src = str_replace('~', '&#126;', $src);
            $macroName1 = ltrim($macroName, '_');
            $src = "<pre class='pfy-source-code'><code>{{ $macroName1($src$multiline)$multiline2}}\n</code></pre>";
            $src = shieldStr($src) . "\n\n";

            // remove some highlighting in args:
            $args = preg_replace('/\*\*(.*?)\*\*/', "$1", $args);
            $args = preg_replace('/\*(.*?)\*/', "$1", $args);
        }
        return array($args, $src);
    } // handleShowSource


} // TwigVars