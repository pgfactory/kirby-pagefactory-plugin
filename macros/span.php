<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

use Kirby\Exception\InvalidArgumentException;

/**
 * @throws InvalidArgumentException
 */
function span($argStr = ''): array|string
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'text' => ['Text to be wrapped into a &lt;span> element.', ''],
            'class' => ['Class to be applied to the \<span> element.', ''],
            'style' => ['Styling instructions, e.g. "border: 1px solid red;".', ''],
            'attr' => ['Other attributes, e.g. "aria-hidden=\'true\'".', ''],
        ],
        'summary' => <<<EOT
# span()

Renders given text wrapped into a &lt;span> element.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $source ,$inx) = $str;
    }

    $attr = '';
    $class = "pfy-span-$inx";
    if ($options['class']) {
        $class .= ' '.$options['class'];
    }
    $attr .= " class='$class'";
    if ($options['style']) {
        $attr .= " style='{$options['style']}'";
    }
    if ($options['attr']) {
        $attr .= " {$options['attr']}";
    }

    // assemble output:
    return "$source<span$attr>{$options['text']}</span>";
}

