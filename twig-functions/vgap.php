<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function vgap($args = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'gapSize' => ['Height of inserted space. Use any form allowed in CSS, e.g. 3em, 20px or 1cm.', '1em'],
            'class' => ['Class applied to DIV', ''],
        ],
        'summary' => <<<EOT
# Vgap()
Inserts a vertical gap of given height.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $args))) {
        return $str;
    } else {
        list($str, $options, $inx) = $str;
    }

    // assemble output:
    $gapSize = $options['gapSize'];
    $class = $options['class'];

    if (preg_match('/^[\d.]+$/', $gapSize)) {
        $gapSize = ($gapSize / 2).'px';
    } elseif (preg_match('/^([\d.]+)(.*)$/', $gapSize, $m)) {
        $gapSize = ($m[1] / 2).$m[2];
    }
    $class = trim("pfy-vgap pfy-vgap-{$inx} $class");
    $str .= "<div id='pfy-vgap-$inx' class='$class' style='margin:$gapSize 0;'>&nbsp;</div>";

    return $str;
}

