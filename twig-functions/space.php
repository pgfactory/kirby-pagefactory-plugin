<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function space($args = '')
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
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $args))) {
        return $str;
    } else {
        list($str, $options, $inx, $funcName) = $str;
    }

    // assemble output:
    $width = $options['width'];
    $class = $options['class'];

    $width = ($width) ? " style='width:$width'" : '';
    $class = trim("pfy-h-space pfy-h-space-{$inx} $class");
    $str .= "<span class='$class'$width></span>";

    //$str = markdown($str); // markdown-compile

    return $str;
}

