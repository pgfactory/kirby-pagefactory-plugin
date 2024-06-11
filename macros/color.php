<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

return function($argStr = '')
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
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options) = $str;
    }

    // assemble output:
    $str = "<span style='color:{$options['color']};'>{$options['text']}</span>";

    return $str;
};

