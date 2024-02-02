<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

use Kirby\Exception\InvalidArgumentException;

function img($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'src' => ['Image source file.', false],
            'alt' => ['Alt-text for image, i.e. a short text that describes the image.', false],
            'id' => ['Id that will be applied to the image.', false],
            'class' => ['Class-name that will be applied to the image.', ''],
            'width' => ['Define width of the image to be shown.', ''],
            'height' => ['Define height of the image to be shown.', ''],
            'wrapperTag' => ['Defines the tag of the element wrapped around the img. Set to false to omit wrapper.', 'div'],
            'wrapperClass' => ['Class to be applied to the wrapper tag.', ''],
            'caption' => ['Optional caption. If set, PageFactory will wrap the image into a &lt;figure> tag '.
                'and wrap the caption itself in a &lt;figcaption> tag.', false],
            'srcset' => ["Let's you override the automatic srcset mechanism.", null],
            'relativeWidth' => ["[%] Use this option if you want to set the image width relative to the viewport width. ".
                "For instance, if the image should cover no more than 30% of window width, use argument \"relativeWidth:30%\".<br>".
                "Based on this value, the browser will select the smallest possible source available (according to the ".
                "automatically generated srcset attribute).", false],
            'attributes' => ["Supplied string is put into the &lt;img> tag as is. This way you can apply advanced ".
                "attributes, such as 'sizes' or 'crossorigin', etc.", false],
            'imgTagAttributes' => ["Supplied string is put into the &lt;img> tag as is. This way you can apply advanced ".
                "attributes, such as 'sizes' or 'crossorigin', etc.", false],
            'quickview' => ["If true, activates the quickview mechanism (default: false). Quickview: click on the ".
                "image to see in full size.", null],
            // 'lateImgLoading' => ["If true, activates the lazy-load mechanism: images get loaded after the page is ready otherwise.", false],
    
            'link' => ["Wraps a &lt;a href='link-argument'> tag round the image..", false],
            'linkClass' => ["Class applied to &lt;a> tag", false],
            'linkTitle' => ["Title-attribute applied to &lt;a> tag, e.g. linkTitle:'opens new window'", false],
            'linkTarget' => ["Target-attribute applied to &lt;a> tag, e.g. linkTarget:_blank", false],
            'linkAttributes' => ["Attributes applied to the \<a> tag, e.g. 'download'.", false],
            'ignoreMissing' => ["If true, an empty string is rendered in case the image file is missing.", false],
            ],
        'summary' => <<<EOT
# img()

Renders an image tag.

Configuration options in 'site/config/config.php':

    'pgfactory.pagefactory.options' \=> [
        'imageAutoQuickview'  \=> true,  \// turns quickview on by default
        'imageAutoSrcset'  \=> true,     \// turns srcset on by default
    ],

**Note**: if an attribute file exists (i.e. image-filename + '.txt') that will be read to extract attributes. 

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    if (!($options['src']??false)) {
        throw new \Exception("Option 'src' is required.");
    }

    // assemble output:
    $img = new Image($options);
    $str .= $img->html();

    return $str;
}

