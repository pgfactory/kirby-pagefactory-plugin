<?php

namespace PgFactory\PageFactory;

use Kirby\Exception\InvalidArgumentException;

class Macros
{
    public static array $macros = [];
    private static array $macroFiles = [];
    public static array $macroIndexes = [];
    public static bool $noTranslate = false;


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
            if ($cand === '') {
                list($p1, $p2) = strPosMatching($str, $p2);
                continue;
            }
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
                    $argStr = TransVars::translate($argStr);
                }

                $value = self::execute($macroName, $argStr);
                if ($value === false) {
                    if ($hideIfUnknown) {
                        $value = '';
                    } else {
                        $value = "\\{{ $cand }}";
                    }
                }
            } elseif ($onlyMacros) {
                list($p1, $p2) = strPosMatching($str, $p2);
                continue;
            } else {
                if (self::$noTranslate) {
                    $value = "<span class='pfy-untranslated'>&#123;&#123; $cand &#125;&#125;</span>";
                } else {
                    $value = TransVars::getVariable($cand);
                }
            }
            $str = substr($str, 0, $p1) . $value . substr($str, $p2+2);
            list($p1, $p2) = strPosMatching($str, $p1);
        }
        return $str;
    } // executeMacros


    /**
     * @param string $macroName
     * @return bool
     */
    public static function exists(string $macroName): bool
    {
        return in_array($macroName, self::$macros);
    } // exists


    /**
     * @param string $macroName
     * @param string $argStr
     * @return string|false
     */
    public static function execute(string $macroName, string $argStr): string|false
    {
        $showHtml = false;
        if (preg_match('/\s*showHtml:\s*true/', $argStr, $m)) {
            $showHtml = true;
            $argStr = str_replace($m[0], '', $argStr);
        }
        $argStr = TransVars::resolveShortFormVariables($argStr, keepUnknowns: true);
        if (function_exists("PgFactory\\PageFactory\\_$macroName")) {
            $macroName = "_$macroName";
        } elseif (!function_exists("PgFactory\\PageFactory\\$macroName")) {
            $macroFile = "site/plugins/pagefactory/macros/$macroName.php";
            if (!file_exists($macroFile)) {
                return false;
            }
            self::instantiateMacroLoader($macroName, $macroFile);
        }

        // the actual macro call:
        $value = "PgFactory\\PageFactory\\$macroName"($argStr);

        if (is_array($value)) {
            $value = $value[0] ?? '';
        } else {
            if ($showHtml) {
                $html = str_replace('<', '&lt;', $value);
                $html = <<<EOT
<div class="pfy-html-source" style="font-size:8pt;">
<pre>
$html
</pre>
</div>
EOT;
                $value .= shieldStr($html);
            }
            $value = TransVars::resolveShortFormVariables($value, keepUnknowns: true);
            $value = shieldStr($value, 'inline');
        }
        return $value;
    } // execute


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
        if ($res = self::handleSpecialOptions($macroName, $config,$args)) {
            return $res;
        }

        // get index:
        $inx = self::$macroIndexes[$macroName] = (self::$macroIndexes[$macroName] ?? 0) + 1;

        $src = '';
        if (is_string($args)) {
            // handle showSource:
            list($args, $src) = self::handleShowSource($args, $src, $macroName);

        } elseif (!is_array($args)) {
            throw new \Exception("Macros: unexpected case -> macro arguments neither string nor array.");
        }

        // get arguments:
        if (($args[0] ?? '') === '{') {
            list($p1, $p2) = strPosMatching($args, 0, '{', '}');
            if ($p1 !== false) {
                $args = substr($args, 0, $p2) . substr($args, $p2 + 1);
                $args = substr($args, 1);
            }
        }
        $options = self::parseMacroArguments($config, $args);

        $auxOptions = [];
        $supportedKeys = array_keys($config['options']);
        foreach ($options as $key => $value) {
            if (is_string($value)) {
                $tmp = TransVars::resolveVariables($value);
                $options[$key] = str_replace(['{!!{', '}!!}', '⟮'], ['{{', '}}', '('], $tmp);
            }
            // check whether arg has optional TYPE specified, check it:
            $type = ($config['options'][$key][2]??false);
            $auxOptions += self::extractAuxOptions($type, $value, $key, $supportedKeys, $options);
        }

        $options['inx'] = $inx;
        $options['macroName'] = ltrim($macroName, '_');
        return [$options, $src, $inx, $macroName, $auxOptions];
    } // initMacro


    /**
     * @param string $macroName
     * @param array $config
     * @param mixed $args
     * @return string|false
     */
    private static function handleSpecialOptions(string $macroName, array $config, mixed &$args): string|false
    {
        // render help text:
        if ((is_string($args) && trim($args) === 'help') || ($args['help']??false)) {
            return self::renderMacroHelp($config);

        // render as unprocessed (?notranslate):
        } elseif (self::$noTranslate) {
            $macroName1 = ltrim($macroName, '_');
            if (is_array($args)) {
                $args = implode(',', $args);
            }
            return "<span class='pfy-untranslated'>&#123;&#123; $macroName1('$args') &#125;&#125;</span>";
        }
        return false;
    } // handleSpecialOptions


    /**
     * Renders Help output for given twig-function
     * @param array $config
     * @param bool $mdCompile
     * @return string
     */
    private static function renderMacroHelp(array $config, bool $mdCompile = true): string
    {
        $str = "<div class='pfy-help'>\n";
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
                } else {
                    $default = str_replace('{{', '\\{{', $default);
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
        // handle arguments defined by position -> submitted as "_anonInxN":
        foreach ($options as $key => $value) {
            if (str_starts_with($key,'_anonInx') && ($value !== null)) {
                $k = substr($key, 8);
                $key1 = array_keys($config['options'])[$k] ?? null;
                if ($key1 !== null) {
                    $options[$key1] = fixDataType($value);
                    unset($options[$key]);
                }
            }
        }
        return $options;
    } // parseMacroArguments


    /**
     * Helper for config.php
     * @return array
     */
    public static function findAllMacros(): array
    {
        $functions = [];
        $pfyPlugins = glob(PFY_KIRBY_BASE_PATH . 'site/plugins/pagefactory*'); // check pagefactory and its extensions
        $pfyPlugins[] = PFY_KIRBY_BASE_PATH . 'site/custom';                           // check place for custom macros
        foreach ($pfyPlugins as $plugin) {
            $dir = glob("$plugin/macros/*.php");
            foreach ($dir as $file) {
                $actualName = basename($file, '.php');
                if ($actualName[0] !== '#') {
                    $name = ltrim($actualName, '_');
                    $functions["*$name"] = "PgFactory\\PageFactory\\$actualName";
                    self::$macroFiles[$name] = $file;
                    self::$macros[] = $name;
                }
            }
        }
        return $functions;
    } // findAllMacros


    /**
     * @return array
     */
    public static function getMacros(): array
    {
        if (!self::$macros) {
            self::findAllMacros();
        }
        return self::$macroFiles;
    } // getMacros


    /**
     * Renders a list of all available macros as presentable HTML
     * @return string
     */
    public static function renderMacros(): string
    {
        $functions = self::findAllMacros();
        $html = "<ul class='pfy-list-functions'>\n";
        foreach ($functions as $name => $longName) {
            $name = ltrim($name, '*');
            $html .= "<li>$name()</li>\n";
        }
        $html .= "</ul>\n";
        return $html;
    } // renderMacros


    /**
     * Renders the macro call in presentable form. As a dropdown box, if requested
     * @param string $args
     * @param string $src
     * @param string $macroName
     * @return array
     * @throws \Exception
     */
    private static function handleShowSource(string $args, string $src, string $macroName): array
    {
        if (preg_match('/,?\s*showSource:\s*(\w+)/', $args, $m)) {
            $args = str_replace($m[0], '', $args);
            $optArg = $m[1];
            if ($optArg === 'false') {
                return array($args, '');
            }
            $reveal = ($optArg === 'reveal');

            $src = str_replace(["''", ',,', '->', '<', '[', '('], ["\\''", "\\,,", "\\->", '&lt;', '&#91;', '&#40;'], $args);
            if (preg_match_all('/".*?"/ms', $src, $m)) {
                foreach ($m[0] as $i => $rec) {
                    $s = $m[0][$i];
                    $s = str_replace(['\\', "\n", '-', '='], ['&#92;', '&#92;n', '&#45;', '&#61;'], $s);
                    $src = str_replace($m[0][$i], $s, $src);
                }
            }
            $src = preg_replace('/\n\s*\n/', "\n", $src);
            $src = markdownParagraph($src);

            $multiline = str_contains($src, "\n") ? "\n    " : '';
            $multiline2 = $multiline ? "\n" : ' ';
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
                $src = <<<EOT

<details class="mdp-accordion mdp-border mdp-accordion-initially-closed">
      <summary><span>{{ pfy-show-source-code }}</span></summary>
      <div class="mdp-accordion-body">

$src

      </div><!-- /.mdp-accordion-body -->
</details>

EOT;
            }
        }
        return array($args, $src);
    } // handleShowSource


    /**
     * Checks value against expected type, separates unsupported/mismatched options into auxOptions.
     * @param mixed $type
     * @param mixed $value
     * @param int|string $key
     * @param array $supportedKeys
     * @param array $options
     * @return array
     */
    private static function extractAuxOptions(mixed $type, mixed $value, int|string $key, array $supportedKeys, array &$options): array
    {
        $typeChecks = [
            'bool'    => 'is_bool',
            'int'     => 'is_int',
            'integer' => 'is_int',
            'number'  => 'is_numeric',
            'numeric' => 'is_numeric',
            'float'   => 'is_float',
            'string'  => 'is_string',
            'scalar'  => 'is_scalar',
            'array'   => 'is_array',
        ];

        $treatAsOption = true;
        if ($type && $value !== null && isset($typeChecks[$type])) {
            $treatAsOption = $typeChecks[$type]($value);
        }

        $auxOptions = [];
        if (!in_array($key, $supportedKeys) || !$treatAsOption) {
            $auxOptions[$key] = $value;
            unset($options[$key]);
        }
        return $auxOptions;
    } // extractAuxOptions


    /**
     * @return void
     */
    public static function initMacros(): void
    {
        $macros = self::getMacros();
        foreach ($macros as $funName => $file) {
            $funName = basename($file, '.php');
            self::instantiateMacroLoader($funName, $file);
        }
    } // initMacros


    /**
     * For each Macro instantiate a caller function which upon request loads and executes the actual macro.
     * @param string $funName
     * @param string $file
     * @return void
     */
    public static function instantiateMacroLoader(string $funName, string $file): void
    {
        if (function_exists("PgFactory\\PageFactory\\$funName")) {
            return;
        }
        if (!file_exists($file)) {
            $file = dirname($file) . '/_' . basename($file);
            if (!file_exists($file)) {
                return;
            }
        }

        // take care of legacy custom macros:
        if (str_starts_with($file, 'site/custom/') && !preg_match("/\nreturn function/", file_get_contents($file))) {
            // legacy mode -> preload entire macro:
            require_once $file;
            return;
        }

        // normal mode: instantiate a function that invokes requested macro via proxy function:
        $createFun = <<<EOT
namespace PgFactory\PageFactory;
function $funName(...\$args)
{
    return funProxy('$file', \$args); // call proxy function in helper.php
}

EOT;
        eval($createFun);
    } // instantiateMacroLoader


} // Macros
