<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function button($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'label' => ['Text on the button.', null],
            'id' => ['ID to apply to button', null],
            'class' => ['Class  to apply to button (class "`pfy-button`" is always applied).', null],
            'callback' => ['Optional callback function (either name or closure)', null],
            'title' => ['(optional) Text to be placed in `title=""` attribute of button. ', null],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a button.
### Example

    js:
    function myCallback() {mylog('button clicked');}
    -\--\-
    \{{ button(
        label: My Button
        callback: myCallback
    ) }}

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    // assemble output:
    $id = $options['id']?: "pfy-button-$inx";
    $class = rtrim('pfy-button '.$options['class']);
    $label = $options['label'] ?: 'BUTTON';
    $title = $options['title'] ? " title='{$options['title']}'" : '';

    $str .= "<button id='$id' class='$class'$title>$label</button>";

    if ($options['callback']) {
        $callback = trim($options['callback']);
        if (preg_match('/^\w+$/', $callback)) {
            $callback = "$callback();";
        }
        $jq = <<<EOT
const button = document.querySelector('#$id');
if (button) {
    button.addEventListener('click', function(e) {
        try {
          $callback
        } catch (error) {
          console.error(error);
        }
    });
}
EOT;
        PageFactory::$pg->addJsReady($jq);
    }

    return $str;
}

