<?php

namespace Usility\PageFactory;


$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'wrapperClass' => ['Class applied to the Nav\'s wrapper.', ''],
        'class' => ['Class applied to the Nav element.', ''],
        'type' => ['[top,branch].', ''],
    ],
    'mdCompile' => false,
    'summary' => <<<EOT
Renders a navigation menu that reflects the currently published pages.

Supported wrapperClasses:

- ``horizontal`` or ``pfy-nav-horizontal``      18em>> horizontal navigation menu
- ``vertical`` or ``pfy-nav-vertical``      18em>> vertical navigation menu
- ``pfy-nav-indented``       18em>>     sub-elements get hierarchically indented
- ``pfy-nav-collapsed``       18em>>    sub-elements are initially collapsed and can be opened
- ``pfy-nav-collapsible``       18em>>    sub-elements are initially open and can be collapsed
- ``pfy-nav-hoveropen``        18em>>    sub-elements opened on mouse over
- ``pfy-nav-open-current``       18em>>   nav tree is pre-opened down to the current page, the rest is collapsed
- ``pfy-nav-animated``         18em>>    adds an animation effect when opening/closing
- ``pfy-nav-colored``       18em>>    applies some default coloring

EOT,
];



class Nav extends Macros
{
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    public function render($args, $argStr)
    {
        $wrapperClass = $args['wrapperClass'];
        if (strpos($wrapperClass, 'horizontal') !== false) {
            $args['wrapperClass'] .= ' pfy-nav-top-horizontal pfy-nav-indented pfy-nav-collapsed pfy-nav-animated pfy-nav-hoveropen pfy-encapsulated pfy-nav-small-tree';

        } elseif ($wrapperClass === 'vertical') {
            $args['wrapperClass'] = ' pfy-nav-vertical pfy-nav-indented pfy-nav-animated pfy-encapsulated';

        } elseif (strpos($wrapperClass, 'pfy-nav-vertical') === false) {
            $args['wrapperClass'] .= ' pfy-nav-vertical';
        }
        if ((strpos($wrapperClass, 'pfy-nav-hoveropen') !== false) &&
            (strpos($wrapperClass, 'pfy-nav-collapsed') === false)) {
            $args['wrapperClass'] .= ' pfy-nav-collapsed pfy-nav-animated';
        }
        $nav = new DefaultNav($this->pfy);
        $str = $nav->render($args);
        return$str;
    }
}





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
