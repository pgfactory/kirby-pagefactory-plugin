<?php
namespace PgFactory\PageFactory;

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

/*
 * PageFactory Macro (and Twig Function)
 *
 * Uses js module "baguetteBox.js"
 *  -> https://github.com/feimosi/baguetteBox.js#customization
 */

use Kirby\Data\Data;

/**
 * @param $args
 * @return string
 * @throws \Kirby\Exception\InvalidArgumentException
 */
return function($args = ''): string
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'path'          => ['[path] Path of folder containing images.', false],
            'thumbWidth'    => ['[int] Width of thumbnails/preview images. '.
                'Supported units: in,cm,mm,pt,pc,px', null],
            'thumbHeight'   => ['[int] Height of thumbnails/preview images.', null],
            'width'         => ['[int] Synonyme for "thumbWidth".', null],
            'height'        => ['[int] Synonyme for "thumbHeight".', null],
            'maxWidth'      => ['[int] Maximum width of images (i.e. in overlay).', IMG_MAX_WIDTH],
            'maxHeight'     => ['[int] Maximum height of images', IMG_MAX_HEIGHT],
            'class'         => ['[string] Class to be applied to the wrapper tag.', false],
            'fullscreen'    => ['[bool] If true, gallery covers the entire screen when opened.', false],
            'background'    => ['[color] Color of the overlay background.', '#212121f2'],
            'config'        => ['Various options, see table above.', []],
            'imageCaptions' => ['(optional) .txt-file containing image descriptions. Also defines image order. '.
                '(file-path relative to gallery-path or absolute like "\~/xy/z.yaml") ', 'index.txt'],
            'thumbCaptions' => ['[bool] If true, captions from `imageCaptions` are rendered in thumbnail-preview '.
                'as well.', false],
        ],
        'summary' => <<<EOT

# $funcName()

Renders images.

### Sub-Options for Argument "config":

|===
|# Option | Type | Description
|---
| ``buttons`` 	| Boolean|``auto`` 	| Display buttons. 'auto' hides buttons on touch-enabled devices or 
when only one image is available (Default: auto)
|---
| ``fullScreen`` 	| Boolean 	| Enable full screen mode
|---
| ``noScrollbars`` 	| Boolean  	| Hide scrollbars when gallery is displayed
|---
| ``titleTag`` 	| Boolean  	| Use caption value also in the gallery img.title attribute
|---
| ``async`` 	| Boolean  	| Load files asynchronously)
|---
| ``preload`` 	| Integer  	| How many files should be preloaded (Default: 2)
|---
| ``animation`` 	| ``slideIn``|``fadeIn``|false  	| Animation type (Default: slideIn)
|---
| ``overlayBackgroundColor`` 	| String  	| Background color for the lightbox overlay
|---
| ``filter`` 	| RegExp  	| Pattern to match image files. Applied to the a.href attribute (Default: ``/.+\.(gif|jpe?g|png|webp)/i``)
|===

### imageCaptions-File

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    // synonymes:
    if ($options['width']) {
        $options['thumbWidth'] = $options['width'];
    }
    if ($options['height']) {
        $options['thumbHeight'] = $options['height'];
    }

    $html = Gallery::render($options);

    return $str.$html; // return [$str]; if result needs to be shielded
}; // gallery

