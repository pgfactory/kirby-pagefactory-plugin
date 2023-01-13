<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function NAME($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
//            '' => ['', false],
        ],
        'summary' => <<<EOT
# NAME()

ToDo: describe purpose of function
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $sourceCode, $inx, $funcName) = $str;
        $str = $sourceCode;
    }

    // assemble output:
    $str .= '';

    //$str = markdown($str); // markdown-compile
    //$str = shieldStr($str); // shield from further processing if necessary

    return $str;
}

