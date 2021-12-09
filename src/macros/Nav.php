<?php

namespace Usility\PageFactory;


$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        '' => ['', false],
    ],
    'mdCompile' => false,
//    'assetsToLoad' => 'site/plugins/pagefactory/js/nav.jq',
    'summary' => <<<EOT
Renders a navigation menu that reflects the currently published pages.
EOT,
];



class Nav extends Macros
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

//        $arg = $args['arg'];
        $nav = new DefaultNav($this->pfy);
        $str = $nav->render();

//        $this->mdCompile = true;

    return$str;
    }
}





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
