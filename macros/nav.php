<?php
namespace PgFactory\PageFactory;


require_once __DIR__.'/../src/SiteNav.php';

function nav($args = null)
{
    $funcName = basename(__FILE__, '.php');
    $config =  [
        'options' => [
            'wrapperClass' => ['Class applied to the Nav\'s wrapper.', ''],
            'class' => ['Class applied to the Nav element.', ''],
            'id' => ['Id applied to the Nav element.', ''],
            'type' => ['[top,side,branch|primary].', ''],
            'isPrimary' => ['By default, first Nav is primary. Set to false to override.', null],
            'listTag' => ['[ul,ol] The tag to be used in list of nav-elements.', 'ol'],
        ],
        'summary' => <<<EOT
# $funcName()

Renders a navigation menu that reflects the currently published pages.

## Supported wrapperClasses:

- ``pfy-nav-horizontal``      18em>> horizontal navigation menu
- ``pfy-nav-indented``       18em>>     sub-elements get hierarchically indented
- ``pfy-nav-collapsed``       18em>>    sub-elements are initially collapsed and can be opened
- ``pfy-nav-collapsible``       18em>>    sub-elements are initially open and can be collapsed
- ``pfy-nav-hoveropen``        18em>>    sub-elements opened on mouse over
- ``pfy-nav-open-current``       18em>>   nav tree is pre-opened down to the current page, the rest is collapsed
- ``pfy-nav-animated``         18em>>    adds an animation effect when opening/closing
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
} // nav

