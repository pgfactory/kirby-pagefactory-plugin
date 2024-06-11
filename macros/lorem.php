<?php
namespace PgFactory\PageFactory;

return function($argStr = '')
{
    $macroName = basename(__FILE__, '.php');
    $config =  [
        'options' => [
            'min' => ['If set, defines the minimum number of random words to be rendered.', false],
            'max' => ['If set, defines the maximum number of random words to be rendered (max: 99).', false],
            'dot' => ['If true, a dot will be appended at the end of the output', false],
            'class' => ['Class to be applied to the wrapper element', ''],
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
    if ($args['max'] === false) {
        $args['max'] = $args['min'];
    }
    if ($args['min']) {
        $words = explode(' ', $lorem);
        $nWords = sizeof($words) - 1;
        $min = intval($args['min']);
        if (!intval($args['max'])) {
            $n = $min;
        } else {
            $max = intval($args['max']);
            $n = rand($min, min($nWords, $max));
        }

        $str = "";
        for ($i = 0; $i < $n; $i++) {
            $str .= $words[rand(1, $nWords - 1)] . ' ';
        }
        $str = preg_replace('/\W$/', '', trim($str));

    } else {
        $str = $lorem;
    }
    if ($args['dot']) {
        $str .= '.';
    }
    $str = ucfirst($str);
    $class = $args['class']? " {$args['class']}": '';
    if ($tag = $args['wrapperTag']) {
        $str = "<$tag class='lorem$class'>$str</$tag>";
    }

    return $sourceCode.$str;
}; // lorem

