<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use function PgFactory\PageFactoryElements\intlDate;

function filedate($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'path' => ['', false],
            'format' => ['', 'Y-m-d'],
            'wrapperTag' => ['', false],
            'wrapperClass' => ['', false],
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

    $path = $options['path'];
    $path = resolvePath($path);
    $files = getDir($path);
    $ftime = 0;
    if ($files) {
        foreach ($files as $file) {
            $ftime = max($ftime, filemtime($file));
        }
    }

    $format = $options['format'];

    $date = intlDate($format, $ftime);

    $wrapperTag = $options['wrapperTag'];
    $wrapperClass = $options['wrapperClass'];

    if ($wrapperTag || $wrapperClass) {
        $wrapperTag = $wrapperTag ?: 'div';
        $wrapperClass = $wrapperClass ? " class='$wrapperClass'" : '';
        $str.= "<$wrapperTag$wrapperClass>$date</$wrapperTag";
    } else {
        $str .= $date;
    }

    return $str;
}
