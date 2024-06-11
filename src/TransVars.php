<?php

namespace PgFactory\PageFactory;

use PgFactory\PageFactory\Macros;
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

        $mdStr = Macros::executeMacros($mdStr);

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
            $out = $varNameIfNotFound ? $varName: false;
        } elseif (is_array($out)) {
            $out = reset($out);
        }
        return $out;
    } // getVariable


    /**
     * @param string $varName
     * @return void
     */
    public static function removeVariable(string $varName): void
    {
        if (isset(self::$variables[$varName])) {
            unset(self::$variables[$varName]);
        }
    } // removeVariable


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
    public static function resolveShortFormVariables(string $str): string
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
                    if ($value !== false) {
                        $str = substr($str, 0, $p1).$value.substr($str, $p2+1);
                    }

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
    public static function translate($str): string
    {
        $str = str_replace(['\\{{', '\\}}', '\\('], ['{!!{', '}!!}', '⟮'], $str);
        $str = self::resolveVariables($str);
        $str = Macros::executeMacros($str);
        $str = str_replace(['\\{{', '\\}}', '\\('], ['{!!{', '}!!}', '⟮'], $str);
        return $str;
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
     * @param string $file
     * @param array $config
     * @param string|array $args
     * @return string|array
     * @throws InvalidArgumentException
     */
    public static function initMacro(string $file, array $config, string|array $args): string|array
    {
        return Macros::initMacro($file, $config, $args);
    } // initMacro

    /**
     * @param mixed $forRegistering
     * @param bool $includePaths
     * @param bool $buildInOnly
     * @return array
     */
    public static function findAllMacros(mixed $forRegistering = false, bool $includePaths = false, bool $buildInOnly = false): array
    {
        require_once 'site/plugins/pagefactory/src/Macros.php';
        return Macros::findAllMacros($forRegistering, $includePaths, $buildInOnly);
    } // findAllMacros

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

} // TransVars
