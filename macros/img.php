<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactory\Image;

/*
 * Twig function
 */

use Kirby\Exception\InvalidArgumentException;

if (!defined('IMG_MAX_WIDTH')) {
    define('IMG_MAX_WIDTH', 1920);
}
if (!defined('IMG_MAX_HEIGHT')) {
    define('IMG_MAX_HEIGHT', 1440);
}
if (!defined('DEFAULT_THUMB_WIDTH')) {
    define('DEFAULT_THUMB_WIDTH', 200);
}
if (!defined('DEFAULT_THUMB_HEIGHT')) {
    define('DEFAULT_THUMB_HEIGHT', 150);
}

return function($argStr = '')
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
            'imgTagAttributes' => ["Supplied string is put into the &lt;img> tag as is. This way you can apply advanced ".
                "attributes, such as 'sizes' or 'crossorigin', etc.", false],
            'quickzoom' => ["If true, activates the quickzoom mechanism (default: false). Quickzoom: click on the ".
                "image to see in full size.", null],
            'quickview' => ["Synonym for quickzoom (for backward compatibility).", null],
            'lazyLoading' => ["If true, activates the lazy-load mechanism: images get loaded after the page is ready otherwise.", null],

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
        'imageAutoQuickzoom'  \=> true,  \// turns quickzoom on by default
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

    if (($options['quickview']??null) !== null) {
        $options['quickzoom'] = $options['quickview'];
    }

    if (!($options['src']??false)) {
        throw new \Exception("Option 'src' is required.");
    }
    if ((($c = $options['src'][0]) !== '~') && ($c !== '/') && ($c !== '.')) {
        $options['src'] = '~page/'.$options['src'];
    }

    // assemble output:
    $img = new Image($options);
    $str .= $img->render();

    return $str;
};

