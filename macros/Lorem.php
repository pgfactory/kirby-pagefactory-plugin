<?php

namespace Usility\PageFactory;

$macroConfig =  [
    'parameters' => [
        'min' => ['If set, defines the minimum number of random words to be rendered.', false],
        'max' => ['If set, defines the maximum number of random words to be rendered (max: 99).', false],
        'dot' => ['If true, a dot will be appended at the end of all random words', false],
        'class' => ['Class to be applied to the wrapper element', ''],
        'wrapper' => ['Allows to define the tag of the wrapper element', 'div'],
    ],
    'summary' => <<<EOT
Renders filler text "Lorem ipsum...". 

If you set min or max values, rendered words are selected at random.

EOT,
];



class Lorem extends Macros
{
    public static $inx = 1;


    public function render($args)
    {
        $inx = self::$inx++;

        $min = $args['min'];
        $max = $args['max'];
        $dot = $args['dot'];
        $class = $args['class'];
        $wrapper = $args['wrapper'];
        $lorem = <<<EOT
Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore 
et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. 
Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, 
consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, 
sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no 
sea takimata sanctus est Lorem ipsum dolor sit amet.
EOT;
        if (!$min && !$max) {
            $str = $lorem;

        } else {
            $words = explode(' ', $lorem);
            $nWords = sizeof($words) - 1;
            $min = intval($min);
            if (!intval($max)) {
                $n = $min;
            } else {
                $max = intval($max);
                $n = rand($min, min($nWords, $max));
            }

            $str = "";
            for ($i=0; $i<$n; $i++) {
                $str .= $words[rand(1, $nWords - 1)].' ';
            }
            $str = preg_replace('/\W$/', '', trim($str));
            if ($dot && ($n > 3)) {
                $str .= '.';
            }
            $str = ucfirst($str);
        }
        if ($wrapper) {
            $class = trim("pfy-lorem pfy-lorem-$inx $class");
            $str = "<$wrapper class='$class'>" . ucfirst($str) . "</$wrapper>";
        }

        return $str;
    } // render
} // Lorem





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
