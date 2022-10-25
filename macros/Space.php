<?php

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'width' => ['Width of inserted space. Use any form allowed in CSS, e.g. 3em, 20px or 1cm', '4ch'],
        'class' => ['Class applied to SPAN', ''],
    ],
    'summary' => <<<EOT
Inserts horizontal space of given width.

EOT,
];



class Space extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
        $this->counter = 0;
    }


    public function render($args, $argStr)
    {
        $inx = self::$inx++;
        $width = $args['width'];
        $class = $args['class'];

        $width = ($width) ? " style='width:$width'" : '';
        $class = trim("pfy-h-space pfy-h-space-{$inx} $class");
        $str = "<span class='$class'$width></span>";
        return$str;
    }
} // Space





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
