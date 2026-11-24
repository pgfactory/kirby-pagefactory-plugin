<?php
namespace PgFactory\PageFactory;

/*
 * Macro
 */

return function ($argStr = '')
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

- ``pfy-nav-horizontal``      18em>> horizontal top nav, sub-trees open as drop-downs
- ``pfy-sitemap-horizontal``  18em>> horizontal sitemap, all sub-trees visible
- (default)                   18em>> vertical, collapsible tree
- ``pfy-nav-collapsed``       18em>>    vertical: all sub-trees initially closed
- ``pfy-nav-open-current``       18em>>   vertical: sub-trees closed except the one containing the current page
- ``pfy-nav-primary``       18em>>    the nav used as mobile nav (default: first nav on the page)
- ``pfy-nav-colored``       18em>>    applies some default coloring based on  ``-\-nav-base-color1`` and ``-\-nav-base-color2``

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
    $str .= SiteNav::render($options);
    return $str;
};

