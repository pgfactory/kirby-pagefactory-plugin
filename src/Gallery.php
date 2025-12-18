<?php

namespace PgFactory\PageFactory;

/*
 * HTML structure for baguetteBox:
<a href="path-to-large-image" title="TITLE">
    <img src="path-to-thumbnail" alt="TITLE" style='width:200px;height:150px;object-fit:cover;'>
</a>
*/

const PFY_GALLERY_IMAGE_TYPES = 'jpg,jpeg,png,gif,webp'; // 'avif'  not supported yet

class Gallery
{
    private static $inx = 0;


    /**
     * @param array $options
     * @return string
     * @throws \Exception
     */
    public static function render(array $options): string
    {
        self::$inx++;
        $inx = self::$inx;

        // fix img dimensions -> support any type of absolute values:
        if ($options['thumbWidth'] && is_string($options['thumbWidth']) && preg_match('/[\d.]+\w+/', $options['thumbWidth'])) {
            $options['thumbWidthPx'] = convertToPx($options['thumbWidth'], true);
        } else {
            $options['thumbWidthPx'] = $options['thumbWidth'].'px';
        }

        if ($options['thumbHeight'] && is_string($options['thumbHeight']) && preg_match('/[\d.]+\w+/', $options['thumbHeight'])) {
            $options['thumbHeightPx'] = convertToPx($options['thumbHeight'], true);
        } else {
            $options['thumbHeightPx'] = $options['thumbHeight'];
        }

        $class = $options['class']??'';

        // gallery config options:
        if ($options['background']) {
            $options['config']['overlayBackgroundColor'] = $options['background'];
        }
        if ($fullScreen = ($options['fullscreen']??false) ?: ($options['fullScreen']??false)) {
            $options['config']['fullScreen'] = $fullScreen;
        }

        // assemble output:
        $html = '';
        $path = fixPath($options['path']);
        if (!$path) { // no path means all images in page folder
            $path = "~page/";
        } elseif ($path[0] !== '~') {
            $path = "~page/$path";
        }

        $images = self::getImages($path, $options['imageCaptions']);
        if (is_array($images)) {
            foreach ($images as $file => $caption) {
                $html .= self::renderImage($file, $options, $caption);
            }
        }

        $html = <<<EOT
<div class='pfy-gallery pfy-gallery-$inx $class'>
$html
</div><!-- /pfy-gallery -->
EOT;

        self::loadAssets($options['config'], $inx);

        return $html;
    } // render



    /**
     * @param string $file
     * @param array $options
     * @param string $caption
     * @return string
     * @throws \Exception
     */
    private static function renderImage(string $file, array $options, string $caption = ''): string
    {
        // create thumbnail and size-variants if necessary:
        list($imgUrl, $html) = self::prepareImage($file, $options);
        if (!$imgUrl) {
            return '';
        }

        $thumbCaption = '';
        if (($options['thumbCaptions']??false) === '') {
            $thumbCaption = "\n<div class='pfy-gallery-thumb-caption'></div>";
        } elseif ($options['thumbCaptions']??false) {
            $thumbCaption = "\n<div class='pfy-gallery-thumb-caption'>$caption</div>";
        }

        $style = '';
        if ($options['thumbWidth']) {
            $w = $options['thumbWidth'];
            if (!preg_match('/\D/', $w)) {
                $w .= 'px';
            }
            $style = " width: $w;";
        }
        if ($options['thumbHeight']) {
            $h = $options['thumbHeight'];
            if (!preg_match('/\D/', $h)) {
                $h .= 'px';
            }
            $style .= "height: $h;";
        }
        if ($style) {
            $style = " style=\"$style\"";
        }

        $caption = $caption? " title='$caption'" : '';
        $html = <<<EOT
<a href="$imgUrl"$caption$style>
$html$thumbCaption
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
    private static function loadAssets(array $config): void
    {
        $inx = self::$inx;
        if ($inx === 1) {
            Assets::addAssets([
                'media/plugins/pgfactory/pagefactory/css/baguetteBox.min.css',
                'media/plugins/pgfactory/pagefactory/css/-gallery.css',
                'media/plugins/pgfactory/pagefactory/js/baguetteBox.min.js'
            ]);
        }
        $js = "baguetteBox.run('.pfy-gallery-$inx', {\n";

        if ($config && is_array($config)) {
            foreach ($config as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif (!is_numeric($value)) {
                    $value = "'$value'";
                }
                $js .= "    $key: $value,\n";
            }
        }
        $js .= "})\n";
        Page::addJsReady($js);
    } // loadAssets


    /**
     * @param string $path
     * @param string $imageCaptionsFile
     * @return array
     */
    private static function getImages(string $path, string $imageCaptionsFile0 = ''): array
    {
        if ($path[0] !== '~') {
            $path = "~page/$path";
        }
        $images = [];

        if ($imageCaptionsFile0) {
            $imageCaptionsFile = Utils::resolvePath($imageCaptionsFile0);
            if (!file_exists($imageCaptionsFile)) {
                $imageCaptionsFile = Utils::resolvePath($path.$imageCaptionsFile0);
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
            $galleryPath = Utils::resolvePath($path);
            $pagePath = PFY_PAGE_PATH;
            $path1 = str_contains($galleryPath, '*') ? $galleryPath : "$galleryPath*";
            $files = getDir($path1);
            foreach ($files as $image) {
                if (is_file($image) && str_contains(PFY_GALLERY_IMAGE_TYPES, fileExt($image))) {
                    $image = str_replace([PFY_KIRBY_BASE_PATH . 'content/assets/', $pagePath], ['~assets/', '~page/'], $image);
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
    private static function prepareImage(string $file, array $options): array
    {
        $imgOptions = [
            'src'           => $file,
            'width'         => $options['thumbWidth'],
            'height'        => $options['thumbHeight'],
            'ignoreMissing' => $options['ignoreMissing']??true,
            'maxWidth'      => convertToPx($options['maxWidth'], true),
            'maxHeight'     => convertToPx($options['maxHeight'], true),
            'quickzoom'     => false,
            'wrapperTag'    => '',
        ];
        $img = new Image($imgOptions);
        $html = $img->render();
        if (!$html) {
            return ['', '', ''];
        }
        $imgUrl = $img->url();
        return [$imgUrl, $html];
    } // prepareImage


    private static function renderStyling($options): void
    {
        $css = '';
        $inx = self::$inx;
        if ($options['thumbWidth']) {
            $thumbWidth = $options['thumbWidth'];
            $css .= "width: $thumbWidth;";
        }
        if ($options['thumbHeight']) {
            $thumbHeight = $options['thumbHeight'];
            $css .= "height: $thumbHeight;";
        }
        if ($css) {
            $css .= ".pfy-gallery-$inx .pfy-img { $css }\n";
            Page::addCss($css);

        }
    }


} // Gallery

