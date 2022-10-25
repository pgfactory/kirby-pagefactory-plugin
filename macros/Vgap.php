<?php

namespace Usility\PageFactory;


$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'gapSize' => ['Height of inserted space. Use any form allowed in CSS, e.g. 3em, 20px or 1cm.', '1em'],
        'class' => ['Class applied to DIV', ''],
    ],
    'summary' => <<<EOT
Inserts a vertical gap of given height.

EOT,
];



class Vgap extends Macros
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

        $gapSize = $args['gapSize'];
        $class = $args['class'];

        if (preg_match('/^[\d.]+$/', $gapSize)) {
            $gapSize = ($gapSize / 2).'px';
        } elseif (preg_match('/^([\d.]+)(.*)$/', $gapSize, $m)) {
            $gapSize = ($m[1] / 2).$m[2];
        }
        $class = trim("pfy-vgap pfy-vgap-{$inx} $class");
        $str = "<div id='pfy-vgap-$inx' class='$class' style='margin:$gapSize 0;'>&nbsp;</div>";
        return$str;
    }
} // Vgap





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
