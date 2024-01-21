<?php
namespace PgFactory\PageFactory;

const IMG_MAX_WIDTH         = 1920;
const IMG_MAX_HEIGHT        = 1440;
const DEFAULT_THUMB_WIDTH   = 200;
const DEFAULT_THUMB_HEIGHT  = 150;
const SRCSET_MIN_WIDTH      = 300;

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
function galery($args = ''): string
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'path'          => ['[path] Path of folder containing images.', false],
            'thumbWidth'    => ['[int] Width of thumbnails/preview images. '.
                'Supported units: in,cm,mm,pt,pc,px', DEFAULT_THUMB_WIDTH],
            'thumbHeight'   => ['[int] Height of thumbnails/preview images.', DEFAULT_THUMB_HEIGHT],
            'width'         => ['[int] Synonyme for "thumbWidth".', null],
            'height'        => ['[int] Synonyme for "thumbHeight".', null],
            'maxWidth'      => ['[int] Maximum width of images (i.e. in overlay).', IMG_MAX_WIDTH],
            'maxHeight'     => ['[int] Maximum height of images', IMG_MAX_HEIGHT],
            'class'         => ['[string] Class to be applied to the wrapper tag.', false],
            'fullscreen'    => ['[bool] If true, galery covers the entire screen when opened.', false],
            'background'    => ['[color] Color of the overlay background.', '#212121f2'],
            'config'        => ['Various options, see table above.', []],
            'imageCaptions' => ['(optional) .txt-file containing image descriptions. Also defines image order. '.
                '(file-path relative to galery-path or absolute like "\~/xy/z.yaml") ', 'index.txt'],
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

    // fix img dimensions -> support any type of absolute values:
    if (preg_match('/[\d.]+\w+/', $options['thumbWidth'])) {
        $options['thumbWidthPx'] = convertToPx($options['thumbWidth'], true).'px';
    } else {
        $options['thumbWidthPx'] = $options['thumbWidth'].'px';
    }
    if (preg_match('/[\d.]+\w+/', $options['thumbHeight'])) {
        $options['thumbHeightPx'] = convertToPx($options['thumbHeight'], true).'px';
    } else {
        $options['thumbHeightPx'] = $options['thumbHeight'].'px';
    }

    $class = $options['class']??'';

    // galery config options:
    if ($options['background']) {
        $options['config']['overlayBackgroundColor'] = $options['background'];
    }
    if ($options['fullscreen']) {
        $options['config']['fullScreen'] = $options['fullscreen'];
    }

    // assemble output:
    $html = '';
    $path = fixPath($options['path']);
    if (!$path) { // no path means all images in page folder
        $path = "~page/";
    } elseif ($path[0] !== '~') {
        $path = "~page/$path";
    }

    $images = getImages($path, $options['imageCaptions']);
    if (is_array($images)) {
        foreach ($images as $file => $caption) {
            $html .= renderImage($file, $options, $caption);
        }
    }

    $html = <<<EOT
<div class='pfy-galery pfy-galery-$inx $class'>
$html
</div><!-- /pfy-galery -->
EOT;

    loadAssets($options['config'], $inx);

    $html = shieldStr($html);
    return $str.$html; // return [$str]; if result needs to be shielded
} // galery


/**
 * @param string $file
 * @param array $options
 * @param string $caption
 * @return string
 * @throws \Exception
 */
function renderImage(string $file, array $options, string $caption = ''): string
{
    // create thiumbnail and size-variants if necessary:
    list($imgUrl, $thumb, $srcSet) = prepareImage($file, $options);

    $thumbCaption = '';
    if (($options['thumbCaptions']??false) === '') {
        $thumbCaption = "\n<div class='pfy-galery-thumb-caption'></div>";
    } elseif ($options['thumbCaptions']??false) {
        $thumbCaption = "\n<div class='pfy-galery-thumb-caption'>$caption</div>";
    }
    $style = "style='width:{$options['thumbWidthPx']};height:{$options['thumbHeightPx']};object-fit:cover;'";
    $html = <<<EOT
<a href="$imgUrl" title="$caption" \n$srcSet>
<img src="$thumb" alt="$caption" $style>$thumbCaption
</a>

EOT;
    return $html;
} // renderImage


/**
 * @param array $config
 * @param int $inx
 * @return void
 * @throws \Exception
 */
function loadAssets(array $config, int $inx): void
{
    if ($inx === 1) {
        PageFactory::$pg->addAssets([
            'media/plugins/pgfactory/pagefactory/css/baguetteBox.min.css',
            'media/plugins/pgfactory/pagefactory/css/-galery.css',
            'media/plugins/pgfactory/pagefactory/js/baguetteBox.min.js'
        ]);
    }
    $js = "baguetteBox.run('.pfy-galery-$inx', {\n";

    if ($config && is_array($config)) {
        foreach ($config as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (!is_numeric($value)) {
                $value = "'$value'";
            }
            $js .= "    '$key': $value,\n";
        }
    }
    $js .= "})\n";
    PageFactory::$pg->addJsReady($js);
} // loadAssets


/**
 * @param string $path
 * @param string $imageCaptionsFile
 * @return array
 */
function getImages(string $path, string $imageCaptionsFile0 = ''): array
{
    if ($path[0] !== '~') {
        $path = "~page/$path";
    }
    $images = [];

    if ($imageCaptionsFile0) {
        $imageCaptionsFile = resolvePath($imageCaptionsFile0, relativeToPage: true);
        if (!file_exists($imageCaptionsFile)) {
            $imageCaptionsFile = resolvePath($path.$imageCaptionsFile0);
        }
        if (file_exists($imageCaptionsFile)) {
            $imageCaptions = explodeTrim("\n", getFile($imageCaptionsFile));
            if (is_array($imageCaptions)) {
                foreach ($imageCaptions as $line) {
                    if (preg_match('/^(.*?):\s*(.*)/', $line, $m)) {
                        $images[$path.$m[1]] = $m[2];
                    }
                }
            }
        }
    }

    if (!$images) {
        $galeryPath = resolvePath($path);
        $pagePath = 'content/'.PageFactory::$pagePath;
        $files = getDir("$galeryPath*");
        foreach ($files as $image) {
            if (is_file($image) && str_contains('jpg,jpeg,png,gif,bmp', fileExt($image))) {
                $image = str_replace(['content/assets/', $pagePath], ['~assets/', '~page/'], $image);
                $images[$image] = '';
            }
        }
    }
    return $images;
} // getImages


/**
 * @param string $file
 * @param array $options
 * @return array|false
 * @throws \Exception
 */
function prepareImage(string $file, array $options): array|false
{
    $imgOptions = [
        'src'       => $file,
        'width'     => $options['thumbWidth'],
        'height'    => $options['thumbHeight'],
        'maxWidth'  => convertToPx($options['maxWidth'], true),
        'maxHeight' => convertToPx($options['maxHeight'], true),
        'quickview' => false,
    ];
    $img = new Image($imgOptions);
    $thumb = $img->resizeImage();
    $srcSet = $img->renderSrcset(true);

    $lines = explode("\n", $srcSet);
    array_shift($lines);
    array_shift($lines);
    array_pop($lines);
    $srcSet = '';
    foreach ($lines as $line) {
        if (preg_match('/(\S+) (\d+)/', trim($line), $m)) {
            $srcSet .= "  data-at-{$m[2]}=\"{$m[1]}\"\n";
        }
    }
    $imgUrl = $img->url();
    return [$imgUrl, $thumb, $srcSet];
} // prepareImage

