<?php

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
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
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    public function render($args, $argStr)
    {
        $str = "<span style='{$args['style']}'>{$args['text']}</span>";
        return$str;
    }
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
