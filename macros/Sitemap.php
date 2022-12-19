<?php

namespace Usility\PageFactory;


$macroConfig =  [
    'parameters' => [
        'type' => ['[branches].', ''],
        'wrapperClass' => ['Class applied to the Nav\'s wrapper.', ''],
        'class' => ['Class applied to the Nav element.', ''],
        'options' => ['[collapsible,collapsed] Adds corresponding classes to the wrapper (for convenience).', ''],
    ],
    'mdCompile' => false,
    'summary' => <<<EOT
Renders a sitemap.

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



class Sitemap extends Macros
{
    public function render($args)
    {
        $args['wrapperClass'] = 'pfy-sitemap pfy-nav-vertical pfy-nav-indented pfy-nav-animated'.$args['wrapperClass'];
        if ($args['type'] = 'branches') {
            $args['wrapperClass'] .= ' pfy-sitemap-branches';
        }
        if ((strpos($args['options'], 'collapsed') !== false) &&
            (strpos($args['wrapperClass'], 'pfy-nav-collapsed') === false)){
            $args['wrapperClass'] .= ' pfy-nav-collapsed';
        }
        if ((strpos($args['options'], 'collapsible') !== false) &&
            (strpos($args['wrapperClass'], 'pfy-nav-collapsible') === false)){
            $args['wrapperClass'] .= ' pfy-nav-collapsible';
        }
        $nav = new SiteNav($this->pfy);
        $str = $nav->render($args);
        return$str;
    }
}





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
