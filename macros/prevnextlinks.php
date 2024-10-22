<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

require_once dirname(__DIR__).'/src/PrevNextLinks.php';


return function ($argStr = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'class' => ['Class to be applied to the element', false],
            'wrapperClass' => ['Class to be applied to the wrapper element. ', false],
            'type' => ['[links,head-elem] "links" returns a DIV to be placed inside page body. '.
                '"head-elem" returns two &lt;link> elements for the head section. ', 'links'],
            'option' => ['[top,bottom] Defines which default class to apply.', 'bottom'],
            'center' => ['[str] Defines text that is rendered between the preveous and next links.', null],
        ],
        'summary' => <<<EOT
# $funcName()

Renders two links, one to the next page, one to the previous page. Activates scripts to trigger on cursor 
keys <- (left) and  -> (right).

Classes:
- .pfy-previous-page-link
- .pfy-next-page-link
- .pfy-show-as-arrows-and-text     13em>> predefined styles  (apply to wrapperClass)
- .pfy-show-as-top-arrows     13em>> predefined styles  (apply to wrapperClass)

Use variables ``\{{ pfy-previous-page-text }}`` and ``\{{ pfy-next-page-text }}`` to define the text (visible and invisible parts).

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    $options['wrapperClass'] .= ($options['option'][0] === 't')? ' pfy-show-as-top-arrows': ' pfy-show-as-arrows-and-text';

    // assemble output:
    $obj = new PrevNextLinks();

    $str .= $obj->render($options);

    return $str;
};
