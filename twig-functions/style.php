<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

use Kirby\Exception\InvalidArgumentException;

/**
 * @throws InvalidArgumentException
 */
function style($argStr = ''): array|string
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

    // parse arguments, handle help and showSource:
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, ,$inx) = $str;
    }

    // assemble output:
    return "<span class='pfy-styled-$inx' style='{$options['style']}'>{$options['text']}</span>";
}

