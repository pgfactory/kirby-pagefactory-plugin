<?php
namespace Usility\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function button($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'label' => ['', false],
            'id' => ['', false],
            'class' => ['', false],
            'callback' => ['', false],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a button.
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

    $str .= "<button id='$id' class='$class'>$label</button>";

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

    $str = shieldStr($str, 'inline'); // shield from further processing if necessary

    return $str;
}

