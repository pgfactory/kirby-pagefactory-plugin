<?php

namespace Usility\PageFactory\Macros;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'text' => ['Text on button', false],
        'icon' => ['Icon on button', false],
        'callbackCode' => ['Defines JS code that will be executed when user clicks on button.', false],
        'callbackFunction' => ['[name of js-function] If defined, the function with that name is called when the button is activated.', false],
        'id' => ['Defines the button\'s ID (default: lzy-button-N)', false],
        'class' => ['Defines the class applied to the button (default: lzy-button)', 'lzy-button'],
        'type' => ['[toggle] Defines the button\'s type-attribute.<br>Special case "toggle": in '.
            'this case JS is added to toggle class "lzy-button-active" and aria-attributes.<br>', false],
    ],
    'summary' => <<<EOT
Renders a button, optionally with callback actions.
EOT,
];



class Button extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->pfy = $pfy;
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    public function render($args, $argStr)
    {
        $inx = self::$inx++;

        $text = $args['text'];
        $icon = $args['icon'];
        $callbackCode = $args['callbackCode'];
        $callbackFunction = $args['callbackFunction'];
        $id = $args['id'];
        $class = $args['class'];
        $type = $args['type'];

        if (!$text) {
            $text = 'Button';
        }
        if ($icon) {
            $text = "<span class='lzy-icon lzy-icon-$icon'></span>$text";
        }

        if (!$id) {
            $id = "lzy-button-$inx";
        } elseif ($id[0] === '#') {
            $id = substr($id, 1);
        }
        $aria = '';
        if ($type === 'toggle') {
            $textActive = $text;
            if (strpos($text, '|') !== false) {
                list($text, $textActive) = explodeTrim('|', $text);
            }
            $class .= ' lzy-toggle-button';
            $aria = ' aria-pressed="false"';
            $jq = <<<EOT

$('#$id').click(function() {
    let \$this = $(this);
    if (\$this.hasClass('lzy-button-active')) {
        \$this.removeClass('lzy-button-active').text('$text').attr('aria-pressed', 'false');
    } else {
        \$this.addClass('lzy-button-active').text('$textActive').attr('aria-pressed', 'true');
    }  
});

EOT;
            $this->pfy->addJq($jq);
            $type = 'button';
        }

        if ($class) {
            $class = " class='$class'";
        }
        $str = "\t<button id='$id'$class type='$type'$aria>$text</button>";  // text rendered by macro

        if ($callbackCode) {
            $callbackCode = str_replace(['&#34;', '&#39;'], ['"', "'"], $callbackCode);
            $jq = <<<EOT

$('#$id').click(function(e) {
    $callbackCode
});

EOT;
            $this->pfy->addJq($jq);
        }

        if ($callbackFunction) {
            $jq = <<<EOT

$('#$id').click(function(e) {
    if (typeof $callbackFunction === 'function') {
        $callbackFunction(this, e);
    } else {
        mylog('Error: callback function "$callbackFunction" for button is not a function.');
    }
});

EOT;
            $this->pfy->addJq($jq);
        }
        return$str;
    }
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
