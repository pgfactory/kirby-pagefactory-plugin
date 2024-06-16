<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

return function ($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'gapSize' => ['Height of inserted space. Use any form allowed in CSS, e.g. 3em, 20px or 1cm.', '1em'],
            'class' => ['Class applied to DIV', ''],
            'line' => ["[true or border-style] Draws a horizontal line, e.g. line:'0.5em dashed #faa'", null],
        ],
        'summary' => <<<EOT
# vgap()

Inserts a vertical gap of given height.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, ,$inx) = $str;
    }

    // assemble output:
    $gapSize = $options['gapSize'];
    $class = $options['class'];
    $line = $options['line'];
    if ($line === true) {
        $line = 'border-bottom: 1px solid black;';
    } elseif ($line) {
        $line = "border-bottom: $line";
    }

    if (preg_match('/^[\d.]+$/', $gapSize)) {
        $gapSize = ($gapSize / 2).'px';
    } elseif (preg_match('/^([\d.]+)(.*)$/', $gapSize, $m)) {
        $gapSize = ($m[1] / 2).$m[2];
    }
    $class = trim("pfy-vgap pfy-vgap-{$inx} $class");
    return "<div id='pfy-vgap-$inx' class='$class' style='margin:$gapSize 0;$line'>&nbsp;</div>";
};

