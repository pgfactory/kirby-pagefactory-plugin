<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function sitemap($argStr = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'type' => ['[branches].', ''],
            'wrapperClass' => ['Class applied to the Nav\'s wrapper.', ''],
            'class' => ['Class applied to the Nav element.', ''],
            'options' => ['[collapsible,collapsed] Adds corresponding classes to the wrapper (for convenience).', ''],
            ],
        'summary' => <<<EOT
# $funcName()

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

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    // assemble output:

    $options['wrapperClass'] = 'pfy-sitemap pfy-nav-vertical pfy-nav-indented pfy-nav-animated'.$options['wrapperClass'];
    if ($options['type'] = 'branches') {
        $options['wrapperClass'] .= ' pfy-sitemap-branches';
    }
    if ((strpos($options['options'], 'collapsed') !== false) &&
        (strpos($options['wrapperClass'], 'pfy-nav-collapsed') === false)){
        $options['wrapperClass'] .= ' pfy-nav-collapsed';
    }
    if ((strpos($options['options'], 'collapsible') !== false) &&
        (strpos($options['wrapperClass'], 'pfy-nav-collapsible') === false)){
        $options['wrapperClass'] .= ' pfy-nav-collapsible';
    }
    $nav = new SiteNav();
    $str = $nav->render($options);
    return [$str];
}

