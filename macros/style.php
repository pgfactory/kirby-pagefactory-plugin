<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

use Kirby\Exception\InvalidArgumentException;

/**
 * @throws InvalidArgumentException
 */
return function ($argStr = ''): array|string
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'text' => ['Text to apply styling to.', '&nbsp;'],
            'style' => ['Styling instructions, e.g. "border: 1px solid red;".', '&nbsp;'],
        ],
        'summary' => <<<EOT
# style()

Renders text with given inline styles.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, ,$inx) = $str;
    }

    // assemble output:
    return "<span class='pfy-styled-$inx' style='{$options['style']}'>{$options['text']}</span>";
};

