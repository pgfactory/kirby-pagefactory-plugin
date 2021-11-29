<?php

namespace Usility\PageFactory\Macros;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        '' => ['', false],
    ],
    'mdCompile' => false,
    'summary' => <<<EOT
[Short description of macro.]
EOT,
];



class >replace with Classname< extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    public function render($args, $argStr)
    {
        $inx = self::$inx++;

        $arg = $args['arg'];
        $str = '';

//        $this->mdCompile = true;

    return$str;
    }
}





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
