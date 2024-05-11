<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function logo($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'src' => ['Image source file.', '~/assets/logo/logo.png'],
            'alt' => ['Alt-text for image, i.e. a short text that describes the image.', false],
            'id' => ['Id that will be applied to the image.', false],
            'class' => ['Class-name that will be applied to the image.', ''],
            'width' => ['Define width of the image to be shown.', '100%'],
            'height' => ['Define height of the image to be shown.', ''],
            'url' => ['The url used when logo is not displayed on the homepage.', '~/'],
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
    $options['quickview'] = false;
    $options['id'] = "pfy-logo-$inx";

    $img = new Image($options);
    $html = $img->html();

    if (page()->id() !== 'home') {
        $html = "<a href='$url'>$html</a>";
    }

    $str = <<<EOT
<div class="pfy-logo">
$html
</div>
EOT;

    return $str;
}
