<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function space($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'width' => ['Width of inserted space. Use any form allowed in CSS, e.g. 3em, 20px or 1cm', '4ch'],
            'class' => ['Class applied to SPAN', ''],
        ],
        'summary' => <<<EOT
# Space()

Inserts horizontal space of given width.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options,$sourceCode,$inx) = $str;
    }

    // assemble output:
    $width = $options['width'];
    $class = $options['class'];

    $width = ($width) ? " style='width:$width'" : '';
    $class = trim("pfy-h-space pfy-h-space-{$inx} $class");
    $str = "<span class='$class'$width></span>";

    return $sourceCode.$str;
}

