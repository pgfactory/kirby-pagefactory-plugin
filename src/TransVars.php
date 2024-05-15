<?php

namespace PgFactory\PageFactory;

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

        // load PFY's standard variable definitions:
        $files = getDir('site/plugins/pagefactory/variables/*.yaml');
        if (is_array($files)) {
            foreach ($files as $file) {
                self::loadVariables($file);
            }
        }

        $fields = PageFactory::$page->content()->fields();
        foreach ($fields as $key => $field) {
            if (!str_ends_with($key, '_md')) {
                self::$transVars[$key] = $field->value();
            }
        }
        self::compileVars();

        self::loadMacros();
    } // init


    /**
     * Resolves given string: variables and macros, finally md-compiles, optionally for input to Twig
     * @param string $mdStr
     * @param $inx
     * @param $removeComments
     * @return string
     * @throws \Exception
     */
    public static function compile(string $mdStr, int $inx = 0, bool|string $removeComments = true, bool $forTwig = true): string
    {
        if ($removeComments) {
            $mdStr = removeComments($mdStr, 'c,t');
        }
        if (!$mdStr) {
            return '';
        }

        $mdStr = str_replace(['\\{{', '\\}}', '\\('], ['{!!{', '}!!}', '⟮'], $mdStr);

        $mdStr = self::resolveVariables($mdStr);

        $mdStr = self::executeMacros($mdStr);

        $html = markdown($mdStr, sectionIdentifier: "pfy-section-$inx", removeComments: false);

        // shield argument lists enclosed in '({' and '})'
        if (preg_match_all('/\(\{ (.*?) }\)/x', $html, $m)) {
            foreach ($m[1] as $i => $pattern) {
                $str = shieldStr($pattern, 'inline');
                $html = str_replace($m[0][$i], "('$str')", $html);
            }
        }

        $html = str_replace(['\\{{', '\\}}', '\\('], ['{!!{', '}!!}', '⟮'], $html);

        if ($forTwig) {
            // add '|raw' to simple variables:
            if (preg_match_all('/\{\{ ( [^}|(]+ ) }}/msx', $html, $m)) {
                foreach ($m[1] as $i => $pattern) {
                    $str = "$pattern|raw";
                    $html = str_replace($m[0][$i], "{{ $str }}", $html);
                }
            }
        }
        return $html;
    } // compile


    /**
     * Loads custom variables from 'site/custom/variables/'
     * @return void
     * @throws InvalidArgumentException
     */
    public static function loadCustomVars(): void
    {
        // load custom variable definitions:
        $files = getDir('site/custom/variables/*.yaml');
        if (is_array($files)) {
            foreach ($files as $file) {
                self::loadVariables($file);
            }
            self::compileVars();
        }
    } // loadCustomVars


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
            if (self::$transVars) {
                foreach ($transVars as $key => $value) {
                    self::$transVars[$key] = $value;
                }
            } else {
                self::$transVars = $transVars;
            }

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
    public static function getVariable(string $varName, bool $varNameIfNotFound = false, string $lang = ''): mixed
    {
        $varName1 = camelCase($varName);

        // check for lang-selector, e.g. 'varname.de':
        if (preg_match('/(.*)\.(\w+)$/', $varName1, $m)) {
            $varName1 = $m[1];
            $lang = $m[2]?:$lang;
            $out = self::translateVariable($varName1, $lang);
            if ($out === false) {
                $varName1 = preg_replace('/\.\w+$/', '', $varName);
                $out = self::translateVariable($varName1, $lang);
            }
        } else {
            if (!isset(self::$variables[$varName1])) {
                try {
                    $out = PageFactory::$page->$varName1()->value; // try to get Kirby field
                } catch (\Exception $e) {
                    $out = $varName1;
                }
            } else {
                $out = self::$variables[$varName1];
            }
        }
        if ($out === null) {
            $out = $varNameIfNotFound ? $varName: '';
        } elseif (is_array($out)) {
            $out = reset($out);
        }
        return $out;
    } // getVariable


    /**
     * From a variable definition, selects the value for the current (or requested) language
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
     * Short-form: '%varname%'
     * @param string $str
     * @return string
     */
    private static function resolveShortFormVariables(string $str): string
    {
        if (str_contains($str, '%')) {
            $p1 = strpos($str, '%');
            while ($p1 !== false) {
                $p2 = strpos($str, '%',$p1 + 1);
                if ($p2 === false) {
                    break;
                }
                $shield = $str[$p1-1]??false;
                if (($shield === '\\') || ($p2-$p1 > 16)) {
                    $p1 = strpos($str, '%', $p1+1);
                    continue;
                } else {
                    $varName = substr($str, $p1+1, $p2-$p1-1);
                    $value = self::getVariable($varName);
                    $str = substr($str, 0, $p1).$value.substr($str, $p2+1);

                }
                $p1 = strpos($str, '%', $p1+1);
            }
            // remove \ from shielded vars:
            if (str_contains($str, '\\%')) {
                $str = str_replace('\\%', '%', $str);
            }
        }
        return $str;
    } // resolveShortFormVariables


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
     * Synonym for resolveVariables()
     * @param $str
     * @return string
     */
    public static function translate($str, string $lang = ''): string
    {
        return self::resolveVariables($str, $lang);
    } // translate


    /**
     * Replaces all occurences of {{ }} patterns with variable contents.
     * -> does NOT execute macros.
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
            if (preg_match('/^([\w-]*?)=(.*)/', $key, $m)) {
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
     * Executes all macros found in given string.
     * @param $str
     * @return string
     * @throws \Exception
     */
    public static function executeMacros($str, $onlyMacros = false)
    {
        list($p1, $p2) = strPosMatching($str);
        while ($p1 !== false) {
            $cand = trim(substr($str, ($p1+2), ($p2-$p1-2)));
            $hideIfUnknown = false;
            if ($cand[0] === '^') {
                $hideIfUnknown = true;
                $cand = ltrim($cand,'^ ');
            }

            if (preg_match('/^([\w|]+) \( (.*) \)/xms', $cand, $m)) {
                $macroName = $m[1];
                if (str_contains($macroName, '|')) {
                    list($p1, $p2) = strPosMatching($str, $p2);
                    continue;
                }
                $argStr = $m[2];
                if (str_contains($argStr, '{{')) {
                    $argStr = self::resolveVariables($argStr);
                }
                
                // check for macro name with leading '_':
                if (function_exists("PgFactory\\PageFactory\\_$macroName")) {
                    $macroName = "_$macroName";
                }
                if (function_exists("PgFactory\\PageFactory\\$macroName")) {
                    $value = "PgFactory\\PageFactory\\$macroName"($argStr);
                    if (is_array($value)) {
                        $value = $value[0]??'';
                    } else {
                        $value = self::resolveShortFormVariables($value);
                        $value = shieldStr($value, 'inline');
                    }

                } elseif ($hideIfUnknown) {
                    $value = '';
                } else {
                    $value = "\\{{ $cand }}";
                }
            } elseif ($onlyMacros) {
                list($p1, $p2) = strPosMatching($str, $p2);
                continue;
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
     * Loads all macro definitions in this plug-in's as well as custom folders.
     * -> Does NOT load macros in extensions.
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
        } elseif (self::$noTranslate) {
            $macroName1 = ltrim($macroName, '_');
            if (is_array($args)) {
                $args = implode(',', $args);
            }
            return "<span class='pfy-untranslated'>&#123;&#123; $macroName1('$args') &#125;&#125;</span>";
        }

        // get index:
        $inx = self::$funcIndexes[$macroName] = (self::$funcIndexes[$macroName]??false) ?
            self::$funcIndexes[$macroName]+1: 1;

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
            if (is_string($value)) {
                $options[$key] = self::resolveVariables($value);
            }
            // check whether arg has optional TYPE specified, check it:
            $treatAsOption = true;
            if ($type = ($config['options'][$key][2]??false)) {
                if ($value !== null) {
                    switch ($type) {
                        case 'bool':
                            if (!is_bool($value)) {
                                $treatAsOption = false;
                            }
                            break;
                        case 'int':
                        case 'integer':
                            if (!is_int($value)) {
                                $treatAsOption = false;
                            }
                            break;
                        case 'number':
                        case 'numeric':
                            if (!is_numeric($value)) {
                                $treatAsOption = false;
                            }
                            break;
                        case 'float':
                            if (!is_float($value)) {
                                $treatAsOption = false;
                            }
                            break;
                        case 'string':
                            if (!is_string($value)) {
                                $treatAsOption = false;
                            }
                            break;
                        case 'scalar':
                            if (!is_scalar($value)) {
                                $treatAsOption = false;
                            }
                            break;
                        case 'array':
                            if (!is_array($value)) {
                                $treatAsOption = false;
                            }
                            break;
                    }
                }
            }
            if (!in_array($key, $supportedKeys) || !$treatAsOption) {
                $auxOptions[$key] = $value;
                unset($options[$key]);
            }
        }

        // apply robots attribte on request:
        if ($options['rejectRobots']??false) {
            PageFactory::$pg->applyRobotsAttrib();
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
        $summary = shieldStr($summary);
        $str .= "<div class='pfy-help-summary'>$summary</div>\n";
        $str .= "<h2>Arguments</h2>\n";
        $str .= "<dl class='pfy-help-argument-list'>\n";
        foreach ($config['options'] as $key => $elem) {
            $text = $elem[0];
            $text = markdown($text, true);
            $text = trim($text);

            // omit '(default: xy)' if default is null:
            if (($elem[1]??null) === null) {
                $default = '';
            } else {
                $default = $elem[1]??'false';
                if (is_bool($default)) {
                    $default = $default? 'true': 'false';
                } elseif (is_array($default)) {
                    $default = json_encode($default);
                }
                $default = " (default: $default)";
            }
            $str .= <<<EOT
<dt>$key</dt>
<dd>$text$default</dd>

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
            $args = trim($args);
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
                $key1 = array_keys($config['options'])[$key];
                $options[$key1] = fixDataType($value);
                unset($options[$key]);
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
                        $functions["*$name"] = "PgFactory\\PageFactory\\$actualName";
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


    /**
     * Updates self::$variables to contain key:value tuples for the current language.
     * @return void
     */
    private static function compileVars(): void
    {
        // compile currently active set of variables and their values:
        foreach (self::$transVars as $key => $rec) {
            self::$variables[camelCase($key)] = self::translateVariable($key);
        }
    } // compileVars


    /**
     * Renders the macro call in presentable form. As a dropdown box, if requested
     * @param string $args
     * @param array|string $src
     * @param string $macroName
     * @return array
     * @throws \Exception
     */
    private static function handleShowSource(string $args, array|string $src, string $macroName): array
    {
        if (preg_match('/,?\s*showSource:\s*(\w+)/', $args, $m)) {
            $args = str_replace($m[0], '', $args);
            $optArg = $m[1];
            if ($optArg === 'false') {
                return array($args, '');
            } 
            $reveal = ($optArg === 'reveal');
            
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
            $args = preg_replace('|\\\//.*|', '', $args);

            if ($reveal) {
                PageFactory::$pg->addAssets('REVEAL');
                $src = <<<EOT
<div class="pfy-reveal-source">
<div class="pfy-reveal-controller-wrapper-src pfy-reveal-controller-wrapper">
<input id="pfy-reveal-controller-src" class="pfy-reveal-controller" type="checkbox" data-reveal-target="#pfy-reveal-container-src" data-icon-closed="▷" data-icon-open="▷" aria-expanded="false">
<label for="pfy-reveal-controller-src">{{ pfy-show-source-code }}</label>
</div>

<div id='pfy-reveal-container-src' class="pfy-reveal-container" aria-hidden="true">
$src
</div>
</div>

EOT;
            }
        }
        return array($args, $src);
    } // handleShowSource

} // TwigVars