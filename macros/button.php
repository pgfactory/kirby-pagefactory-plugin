<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

return function($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'label' => ['Text on the button.', null],
            'text' => ['Synonyme for "label".', null],
            'icon' => ['Name of icon.', null],
            'id' => ['ID to apply to button', null],
            'class' => ['Class  to apply to button (class "`pfy-button`" is always applied).', null],
            'callback' => ['Optional callback function (either name or closure)', null],
            'title' => ['(optional) Text to be placed in `title=""` attribute of button. ', null],
            'attrib' => ['HTML attribute, e.g. "data-x=\'y\'" ', null],
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
    $label = $options['label'] ?: ($options['text'] ?: 'BUTTON');
    $attrib = $options['attrib'] ? " {$options['attrib']}" : '';
    $icon = $options['icon'] ?: '';
    $title = $options['title'] ? " title='{$options['title']}'" : '';

    if (preg_match_all('/(:(\w{1,15}):)/', $label, $m)) {
        foreach ($m[0] as $i => $rec) {
            $iconCode = Utils::renderPfyIcon($m[2][$i]);
            if ($iconCode) {
                $label = str_replace($m[0][$i], $iconCode, $label);
            }
        }
    }

    if ($icon) {
        $icon = trim($icon, ':');
        $icon = Utils::renderPfyIcon($icon);
        if ($label === 'BUTTON') {
            $label = '';
        }
        $class .= " pfy-btn-icon-".$options['icon'];
    }

    $str .= "<button id='$id' class='$class'$title$attrib>$icon$label</button>";

    if ($callback = trim($options['callback']??'')) {
        if (str_starts_with($callback, 'function')) {
            // closure:
            $jq = <<<EOT
pfyButton = document.querySelector('#$id');
if (pfyButton) {
    pfyButton.addEventListener('click', $callback);
}
EOT;

        } else {
            // function name
            if (preg_match('/^\w+$/', $callback)) {
                $callback = "$callback();";
            }
            $jq = <<<EOT
pfyButton = document.querySelector('#$id');
if (pfyButton) {
    pfyButton.addEventListener('click', function(e) {
        try {
          $callback
        } catch (error) {
          console.error(error);
        }
    });
}
EOT;
        }

        Page::addJs('let pfyButton = null;');
        Page::addJsReady($jq);
    }

    return $str;
};

