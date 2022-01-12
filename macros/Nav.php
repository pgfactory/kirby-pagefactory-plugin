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

- ``horizontal`` or ``lzy-nav-horizontal``      18em>> horizontal navigation menu
- ``vertical`` or ``lzy-nav-vertical``      18em>> vertical navigation menu
- ``lzy-nav-indented``       18em>>     sub-elements get hierarchically indented
- ``lzy-nav-collapsed``       18em>>    sub-elements are initially collapsed and can be opened
- ``lzy-nav-collapsible``       18em>>    sub-elements are initially open and can be collapsed
- ``lzy-nav-hoveropen``        18em>>    sub-elements opened on mouse over
- ``lzy-nav-open-current``       18em>>   nav tree is pre-opened down to the current page, the rest is collapsed
- ``lzy-nav-animated``         18em>>    adds an animation effect when opening/closing
- ``lzy-nav-colored``       18em>>    applies some default coloring

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
            $args['wrapperClass'] .= ' lzy-nav-top-horizontal lzy-nav-indented lzy-nav-collapsed lzy-nav-animated lzy-nav-hoveropen lzy-encapsulated lzy-nav-small-tree';

        } elseif ($wrapperClass === 'vertical') {
            $args['wrapperClass'] = ' lzy-nav-vertical lzy-nav-indented lzy-nav-animated lzy-encapsulated';

        } elseif (strpos($wrapperClass, 'lzy-nav-vertical') === false) {
            $args['wrapperClass'] .= ' lzy-nav-vertical';
        }
        if ((strpos($wrapperClass, 'lzy-nav-hoveropen') !== false) &&
            (strpos($wrapperClass, 'lzy-nav-collapsed') === false)) {
            $args['wrapperClass'] .= ' lzy-nav-collapsed lzy-nav-animated';
        }
        $nav = new DefaultNav($this->pfy);
        $str = $nav->render($args);
        return$str;
    }
}





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
