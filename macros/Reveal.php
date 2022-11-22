<?php

/*
 * 
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'label' => ['Text that prepresents the controlling element.', false],
        'target' => ['[css selector] CSS selector of the DIV that shall be revealed, e.g. "#box"', false],
        'class' => ['(optional) A class that will be applied to the controlling element.', false],
        'symbol' => ['(triangle) If defined, the symbol on the left hand side of the label will be modified. (currently just "triangle" implemented.)', false],
        'frame' => ['(true, class) If true, class "pfy-reveal-frame" is added, painting a frame around the element by default.', false],
    ],
    'summary' => <<<EOT
Displays a clickable label. When clicked, opens and closes the target element specified in argument ``target``.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => [
        'site/plugins/pagefactory/scss/reveal.scss',
        'site/plugins/pagefactory/assets/js/reveal.js',
    ],
];



class Reveal extends Macros
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
        $inx = self::$inx++;

        $id = "pfy-reveal-controller-$inx";
        $class = $args['class'];

        if ($args['frame']) {
            $class = $class? "$class pfy-reveal-frame": 'pfy-reveal-frame';
        }
        if (stripos($args['symbol'], 'tri') !== false) {
            $class .= ' pfy-reveal-triangle';
        }

        $class = $class? " $class": '';
        $out = '';
        $out .= "\n\t\t\t\t<input id='$id' class='pfy-reveal-controller-elem pfy-reveal-icon' type='checkbox' data-reveal-target='{$args['target']}'>".
            "\n\t\t\t\t<label for='$id'>{$args['label']}</label>\n";

        $out = "\t\t\t<div class='pfy-reveal-controller$class'>$out\t\t\t</div>\n";

        return $out;
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
