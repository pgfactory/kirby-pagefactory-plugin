<?php

/*
 * PageFactory Macro Template
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ), // <- don't modify
    'parameters' => [
//      'my-arg' => ['Help text for this argument', 'default value'],
        '' => ['', false],              // <- add your arguments here
    ],
    'summary' => <<<EOT
[Short description of macro.]
EOT,                                    // <- Help text to explain function of this macro
    'mdCompile' => false,               // <- whether output needs to be markdown-compiled
    'assetsToLoad' => '',               // <- comma-separated list of scss,css or js files (incl. path from app-root)
];



class replace_with_filename extends Macros // <- modify classname (must be qual to name of this file w/t extension!)
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render(array $args, string $argStr): string
    {
        $inx = self::$inx++;             // instantiation counter, e.g. to create unique element ids

//        $arg = $args['my-arg'];        // <- how to access an argument
        $str = '';

        // Your code goes here...

//        $this->mdCompile = true;       // <- another way to request markdown compilatoin of output
    return $str;
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
