<?php
namespace PgFactory\PageFactory;


require_once __DIR__.'/../src/SiteNav.php';

return function($args = null)
{
    $funcName = basename(__FILE__, '.php');
    $config =  [
        'options' => [
            'type' => ['[top,side,sitemap,branch|primary].', null],
            'wrapperClass' => ['Class applied to the Nav\'s wrapper.', null],
            'class' => ['Class applied to the Nav element.', null],
            'id' => ['Id applied to the Nav element.', null],
            'siteSearch' => ['If true (and site has more than 3 pages) a site-search function is activated. '.
                'Then a search icon appears next to the menu, which opens the search field.', true],
            'isPrimary' => ['By default, first Nav is primary. Set to false to override.', null],
            'listTag' => ['[ul,ol] The tag to be used in list of nav-elements.', 'ol'],
            'prefix' => ['String to be prepended to nav, e.g. to add "link to home" in branch menu.', null],
            'showHome' => ['If true and type=branch, a pseudo nav-element is prependet pointing to '
                .'the site\'s homepage. If string is given, it is used as the label.', null],
        ],
        'summary' => <<<EOT
# $funcName()

Renders a navigation menu that reflects the currently published pages.

## Supported wrapperClasses:

- ``pfy-nav-horizontal``      18em>> horizontal top nav, sub-trees open as drop-downs
- ``pfy-sitemap-horizontal``  18em>> horizontal sitemap, all sub-trees visible
- (default)                   18em>> vertical, collapsible tree
- ``pfy-nav-collapsed``       18em>>    vertical: all sub-trees initially closed
- ``pfy-nav-open-current``       18em>>   vertical: sub-trees closed except the one containing the current page
- ``pfy-nav-primary``       18em>>    the nav used as mobile nav (default: first nav on the page)
- ``pfy-nav-colored``       18em>>    applies some default coloring based on  ``-\-nav-base-color1`` and ``-\-nav-base-color2``

## Supported CSS-Variables:

Base Colors (used by pfy-nav-colored):
- ``-\-nav-base-color1``
- ``-\-nav-base-color2``

Text:
- ``-\-pfy-nav-txt-size``
- ``-\-pfy-nav-txt-color``
- ``-\-pfy-nav-sub-txt-color``
- ``-\-pfy-nav-active-txt-color``
- ``-\-pfy-nav-curr-txt-color``
- ``-\-pfy-nav-surrogate-txt-color``
- ``-\-pfy-nav-hover-txt-color``

Background and border-left:
- ``-\-pfy-nav-bg-color``
- ``-\-pfy-nav-elem-bg-color``
- ``-\-pfy-nav-hover-bg-color``
- ``-\-pfy-nav-sub-bg-color``
- ``-\-pfy-nav-active-bg-color``
- ``-\-pfy-nav-curr-bg-color``
- ``-\-pfy-nav-surrogate-bg-color``

Focus Frame Colors:
- ``-\-pfy-focus-frame-color1``
- ``-\-pfy-focus-frame-color2``

EOT,
    ];
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode) = $res;
    }

    if (str_contains($options['type']??'', 'primary')) {
        $options['isPrimary'] = true;
    }

    $str = SiteNav::render($options);
    return $sourceCode.$str;
}; // nav

