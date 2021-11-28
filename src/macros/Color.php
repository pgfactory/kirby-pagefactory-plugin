<?php

namespace Usility\PageFactory\Macros;

$macroConfig =  [
    'name' => strtolower( $macroName ),
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
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    public function render($args, $argStr)
    {
        $str = "<span style='color:var(--lzy-color, {$args['color']});'>{$args['text']}</span>";
        return$str;
    }
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
