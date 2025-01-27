<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'src' => ['Image source file.', '~/assets/logo/logo.png'],
            'alt' => ['Alt-text for image, i.e. a short text that describes the image.', false],
            'text' => ['Text that will be placed next to the logo image.', ''],
            'id' => ['Id that will be applied to the image.', false],
            'class' => ['Classes that will be applied to the image.', ''],
            'wrapperClass' => ['Classes that will be applied to the image wrapper.', ''],
            'width' => ['Define width of the image to be shown.', null],
            'height' => ['Define height of the image to be shown.', null],
            'url' => ['The url used when logo is not displayed on the homepage (default: "\~/").', null],
            'link' => ['Synonyme for "url".', null],
        ],
        'summary' => <<<EOT

# $funcName()

Renders an image with is automatically wrapped in a link, unless current page is home page.

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
    $str .= '';

    $url = ($options['url']??false) ?: '~/';
    $text = ($options['text']??false) ? "<span>{$options['text']}</span>" : '';
    $options['quickzoom'] = false;
    $options['id'] = "pfy-logo-$inx";
    if ($options['link']??false) {
        $options['url'] = $options['link'];
    }
    unset($options['link']);

    $img = new Image($options);
    $html = $img->html();

    if (page()->id() !== 'home') {
        $html = "<a href='$url'>$html</a>";
    }

    $wrapperClass = $options['wrapperClass'];

    $str = <<<EOT
<div class="pfy-logo $wrapperClass">
$html$text
</div>
EOT;

    return $str;
};
