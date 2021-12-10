<?php

/*
 * 
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        '' => ['', false],
    ],
    'summary' => <<<EOT
[Short description of macro.]
EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class replace_with_filename extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Macro rendering method
     * @param $args                     // array of arguments
     * @param $argStr                   // original argument string
     * @return string                   // HTML or Markdown
     */
    public function render($args, $argStr)
    {
        $inx = self::$inx++;

//        $arg = $args['my-arg'];
        $str = '';

//        $this->mdCompile = true;
    return $str;
    }
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
