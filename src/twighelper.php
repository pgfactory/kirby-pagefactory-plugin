<?php
namespace Usility\PageFactory;

/**
 * Renders Help output for given twig-function
 * @param array $config
 * @param $mdCompile
 * @return string
 */
function renderTwigFunctionHelp(array $config, $mdCompile = true): string
{
    $str = "<div class='pfy-help pfy-encapsulated'>\n";
    if ($mdCompile) {
        $summary = markdown($config['summary'] ?? '');
    }
    $str .= "<div class='pfy-help-summary'>$summary</div>\n";
    $str .= "<h2>Arguments</h2>\n";
    foreach ($config['options'] as $key => $elem) {
        $text = $elem[0];
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
} // renderTwigFunctionHelp


/**
 * Helper for twig-functions -> prepares options, handles special cases: help, showSource, ?notranslate
 * @param string $file
 * @param array $config
 * @param string $args
 * @return mixed
 */
function prepareTwigFunction(string $file, array $config, string $args): mixed
{
    $funcName = basename($file, '.php');
    // render help text:
    if ($args === 'help') {
        return renderTwigFunctionHelp($config);

    // render as unprocessed (?notranslate):
    } elseif (TwigVars::$noTranslate) {
        return "&#123;&#123; $funcName('$args') &#125;&#125;";
    }

    // get arguments:
    $options = parseTwigFunctionArguments($config, $args);

    // get index:
    $inx = TwigVars::$funcIndexes[$funcName] = (TwigVars::$funcIndexes[$funcName]??false) ?
        TwigVars::$funcIndexes[$funcName]+1: 1;

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
        $str = shieldStr("    {{ $funcName('$args$multiline')$multiline2}}\n\n", false);

    }
    return [$str, $options, $inx, $funcName];
} // prepareTwigFunction


/**
 * Parses argument string of twig-functions, returns as $options array
 * @param array $config
 * @param mixed $args
 * @return array
 * @throws \Kirby\Exception\InvalidArgumentException
 */
function parseTwigFunctionArguments(array $config, mixed $args): array
{
    if (is_array($args)) {
        $options = $args;
    } else {
        $args = unshieldStr($args, true);
        $args = trim($args, '{}');
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



function renderTwigFunctions()
{
    $functions = findTwigFunctions();
    $html = "<ul class='pfy-list-functions'>\n";
    foreach ($functions as $name) {
        $html .= "<li>$name()</li>\n";
    }
    $html .= "<ul>\n";
    return $html;
} // renderTwigFunctions



function findTwigFunctions($forRegistering = false)
{
    $functions = [];
    $pfyPlugins = glob('site/plugins/pagefactory*');
    foreach ($pfyPlugins as $plugin) {
        $dir = glob("$plugin/twig-functions/*.php");
        foreach ($dir as $file) {
            $actualName = basename($file, '.php');
            if ($actualName[0] !== '#') {
                if ($forRegistering) {
                    $name = ltrim($actualName, '_');
                    $functions["*$name"] = "Usility\\PageFactory\\$actualName";
                } else {
                    $functions[] = ltrim($actualName, '_');
                }
            }
        }
    }
    return $functions;
} // findTwigFunctions