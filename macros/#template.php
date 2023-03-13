<?php
namespace Usility\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function NAME($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
//            '' => ['', false],
        ],
        'summary' => <<<EOT

# $funcName()

ToDo: describe purpose of function
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    // assemble output:
    $str .= '';

    //$str = markdown($str); // markdown-compile
    //$str = shieldStr($str); // shield from further processing if necessary

    //PageFactory::$pg->requireFramework();
    //PageFactory::$pg->addAssets('XY');

    return $str;
}

