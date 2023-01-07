<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function style($args = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'text' => ['Text to apply styling to.', '&nbsp;'],
            'style' => ['Styling instructions, e.g. "border: 1px solid red;".', '&nbsp;'],
        ],
        'summary' => <<<EOT
# Style()

Renders text with given inline styles.
EOT,
    ];
    $funcName = basename(__FILE__, '.php');

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
    $inx = TwigVars::$funcIndexes[$funcName] = (TwigVars::$funcIndexes[$funcName]??false) ? TwigVars::$funcIndexes[$funcName]+1: 1;

    // assemble output:
    $str = "<span class='pfy-styled-$inx' style='{$options['style']}'>{$options['text']}</span>";

    return $str;
}

