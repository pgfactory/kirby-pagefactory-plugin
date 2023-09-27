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
            'id' => ['Id applied to the Nav element.', ''],
            'class' => ['Class applied to the Nav element.', ''],
            'wrapperClass' => ['Class applied to the Nav\'s wrapper.', ''],
            'options' => ['[collapsible,collapsed] Adds corresponding classes to the wrapper (for convenience).', ''],
            'listTag' => ['[ul,ol] The tag to be used in list of nav-elements.', 'ol'],
        ],
        'summary' => <<<EOT
# $funcName()

Renders a sitemap.

Supported wrapperClasses:

- ``pfy-nav-horizontal``      18em>> horizontal navigation menu
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

    $options['wrapperClass'] = ' pfy-sitemap pfy-nav-indented pfy-nav-animated '.$options['wrapperClass'];
//    $options['wrapperClass'] = 'pfy-sitemap pfy-nav-vertical pfy-nav-indented pfy-nav-animated'.$options['wrapperClass'];
    $options['isPrimary'] = false;
    if (str_contains($options['type'], 'branches')) {
        $options['wrapperClass'] .= ' pfy-sitemap-branches';
    }
    if (str_contains($options['type'], 'hori')) {
        $options['wrapperClass'] .= ' pfy-sitemap-horizontal';
    }
    if ((str_contains($options['options'], 'collapsed')) &&
        (!str_contains($options['wrapperClass'], 'pfy-nav-collapsed'))){
        $options['wrapperClass'] .= ' pfy-nav-collapsed';
    }
    if ((str_contains($options['options'], 'collapsible')) &&
        (!str_contains($options['wrapperClass'], 'pfy-nav-collapsible'))){
        $options['wrapperClass'] .= ' pfy-nav-collapsible';
    }
    $nav = new SiteNav();
    $str .= $nav->render($options);
    return [$str];
}

