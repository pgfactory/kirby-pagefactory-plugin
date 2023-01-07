<?php
namespace Usility\PageFactory;


require_once __DIR__.'/../src/helper.php';
require_once __DIR__.'/../src/twighelper.php';
require_once __DIR__.'/../src/SiteNav.php';

function nav($args = '')
{
    $funcName = basename(__FILE__, '.php');
    $config =  [
        'options' => [
            'wrapperClass' => ['Class applied to the Nav\'s wrapper.', ''],
            'class' => ['Class applied to the Nav element.', ''],
            'type' => ['[top,branch].', ''],
        ],
        'summary' => <<<EOT
## Nav()

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
    if ($args === 'help') {
        return renderTwigFunctionHelp($config);
    } elseif (TwigVars::$noTranslate) {
        return "&#123;&#123; $funcName('$args') &#125;&#125;";
    }

    $options = parseTwigFunctionArguments($config, $args);


    $nav = new SiteNav();
    $str = $nav->render($options);

    return $str;
} // nav

