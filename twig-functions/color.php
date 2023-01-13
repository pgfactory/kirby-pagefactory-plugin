<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function color($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'text' => ['Text to which to apply color.', false],
            'color' => ['The color.', false],
            ],
        'summary' => <<<EOT
# color()

Renders text in given color.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options) = $str;
    }

    // assemble output:
    $str = "<span style='color:var(--pfy-color, {$options['color']});'>{$options['text']}</span>";
    $str = shieldStr($str);

    return $str;
}

