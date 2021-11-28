<?php

namespace Usility\PageFactory\Macros;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
    ],
    'mdCompile' => false,
    'wrapInComment' => true,
    'summary' => <<<EOT
Renders a text fragment in the currently active language.  
For each  supported language you need to define the text fragment in the argument list.
EOT,
];

foreach ($this->pfy->supportedLanguages as $lang) {
    $macroConfig['parameters'][$lang] = ["Text for language=$lang", false];
}

class Lang extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    public function render($args)
    {
        $str = '';
        if (isset($args[$this->langCode])) {
            $str = $args[$this->langCode];
        } elseif (isset($args[$this->lang])) {
            $str = $args[$this->lang];
        }
        return$str;
    }
}





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
