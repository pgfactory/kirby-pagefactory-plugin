<?php
namespace PgFactory\PageFactory;

return function($argStr = '')
{
    $macroName = basename(__FILE__, '.php');
    $config =  [
        'options' => [
            'min' => ['If set, defines the minimum number of random words to be rendered.', null],
            'max' => ['If set, defines the maximum number of random words to be rendered (max: 99).', null],
            'dot' => ['If true, special characters will be removed and a dot will be appended at the end of the output', true],
            'random' => ['If true, random words will be selected. If min and max are defined, random is default', null],
            'class' => ['Class to be applied to the wrapper element', null],
            'wrapperClass' => ['Synonym for "class".', null],
            'wrapperTag' => ['Allows to define the tag of the wrapper element', 'div'],
        ],
        'summary' => <<<EOT
# $macroName()

Renders filler text "Lorem ipsum...".

If you set min or max values, rendered words are selected at random.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $sourceCode) = $str;
    }

    $lorem = <<<EOT
Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore
et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum.
Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet,
consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no
sea takimata sanctus est Lorem ipsum dolor sit amet.
EOT;
    $lorem = str_replace("\n", ' ', $lorem);

    $min = intval($args['min']);
    $max = intval($args['max']);
    if (!$max) {
        if ($min) {
            $max = $min;
            $min = 0;
        } else {
            $max = 99;
        }
    }
    $random = $args['random'];
    if ($random === null) {
        $random = ($min && $max);
    }
    $dot = $args['dot'];
    if (!$dot) {
        $lorem = preg_replace('/[^\w\s]/', '', $lorem);
    }

    if ($min || $max || $random) {
        $words = explode(' ', $lorem);
        $nWords = sizeof($words);
        $max = min($nWords, $max);
        $n = $max - $min;

        if ($random) {
            shuffle($words);
        }
        $words = array_slice($words, $min, $n);
        $lorem = implode(' ', $words);
    }

    if ($dot) {
        $lorem .= '.';
    }
    $lorem = ucfirst($lorem);
    $class = $args['class'] ?: ($args['wrapperClass'] ?: '');
    $class =$class ? " $class": '';
    if ($tag = $args['wrapperTag']) {
        $lorem = "<$tag class='lorem$class'>$lorem</$tag>";
    }

    return $sourceCode.$lorem;
}; // lorem

