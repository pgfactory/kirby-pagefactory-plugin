<?php

namespace Usility\PageFactory;

$macroConfig =  [
    'parameters' => [
        'text' => ['Text to apply styling to.', '&nbsp;'],
        'style' => ['Styling instructions, e.g. "border: 1px solid red;".', '&nbsp;'],
    ],
    'summary' => <<<EOT
Renders text with given inline styles.
EOT,
];



class Style extends Macros
{
    public function render($args, $argStr)
    {
        $str = "<span style='{$args['style']}'>{$args['text']}</span>";
        return$str;
    }
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
