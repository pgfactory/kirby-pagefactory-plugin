<?php

namespace Usility\PageFactory;

$macroConfig =  [
    'parameters' => [
        'text' => ['Text to which to apply color.', false],
        'color' => ['The color.', false],
    ],
    'summary' => <<<EOT
Renders text in given color.
EOT,
];



class Color extends Macros
{
    public function render($args, $argStr)
    {
        $str = "<span style='color:var(--pfy-color, {$args['color']});'>{$args['text']}</span>";
        return$str;
    }
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
