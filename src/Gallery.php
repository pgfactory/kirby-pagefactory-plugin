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
    private static int $inx = 0;


    /**
     * @param array $options
     * @return string
     * @throws \Exception
     */
    public static function render(array $options): string
    {
        self::$inx++;
        $inx = self::$inx;

        $class = $options['class'] ?? '';

        // gallery config options:
        if ($options['background'] ?? false) {
            $options['config']['overlayBackgroundColor'] = $options['background'];
        }
        if ($fullScreen = ($options['fullscreen'] ?? false) ?: ($options['fullScreen'] ?? false)) {
            $options['config']['fullScreen'] = $fullScreen;
        }

        // assemble output:
        $html = '';
        $path = fixPath($options['path'] ?? '');
        if (!$path) { // no path means all images in page folder
            $path = "~page/";
        } elseif ($path[0] !== '~') {
            $path = "~page/$path";
        }

        $images = self::getImages($path, $options['imageCaptions'] ?? '');
        foreach ($images as $file => $caption) {
            $html .= self::renderImage($file, $options, $caption);
        }

        $html = <<<EOT
<div class='pfy-gallery pfy-gallery-$inx $class'>
$html
</div><!-- /pfy-gallery -->
EOT;

        self::loadAssets($options['config'] ?? []);

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
        [$imgUrl, $html] = self::prepareImage($file, $options);
        if (!$imgUrl) {
            return '';
        }

        $thumbCaption = '';
        if (($options['thumbCaptions'] ?? false) === '') {
            $thumbCaption = "\n<div class='pfy-gallery-thumb-caption'></div>";
        } elseif ($options['thumbCaptions'] ?? false) {
            $thumbCaption = "\n<div class='pfy-gallery-thumb-caption'>$caption</div>";
        }

        $style = '';
        $thumbWidth = $options['thumbWidth'] ?? '';
        if ($thumbWidth) {
            $w = is_numeric($thumbWidth) ? $thumbWidth . 'px' : $thumbWidth;
            $style = " width: $w;";
        }
        $thumbHeight = $options['thumbHeight'] ?? '';
        if ($thumbHeight) {
            $h = is_numeric($thumbHeight) ? $thumbHeight . 'px' : $thumbHeight;
            $style .= "height: $h;";
        }
        if ($style) {
            $style = " style=\"$style\"";
        }

        $titleAttr = $caption ? ' title="' . htmlspecialchars($caption, ENT_QUOTES) . '"' : '';
        $html = <<<EOT
<a href="$imgUrl"$titleAttr$style>
$html$thumbCaption
</a>

EOT;
        return $html;
    } // renderImage


    /**
     * @param array $config
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

        if ($config) {
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
     * @param string $captionFilename
     * @return array
     */
    private static function getImages(string $path, string $captionFilename = ''): array
    {
        if ($path[0] !== '~') {
            $path = "~page/$path";
        }
        $images = [];
        $imageTypes = explode(',', PFY_GALLERY_IMAGE_TYPES);

        if ($captionFilename) {
            $captionFile = Utils::resolvePath($captionFilename);
            if (!file_exists($captionFile)) {
                $captionFile = Utils::resolvePath($path . $captionFilename);
            }
            if (file_exists($captionFile)) {
                $imageCaptions = explodeTrim("\n", getFile($captionFile));
                if (is_array($imageCaptions)) {
                    foreach ($imageCaptions as $line) {
                        if (preg_match('/^(.*?):\s*(.*)/', $line, $m)) {
                            $images[$path . $m[1]] = $m[2];
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
                if (is_file($image) && in_array(fileExt($image), $imageTypes)) {
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
     * @return array
     * @throws \Exception
     */
    private static function prepareImage(string $file, array $options): array
    {
        $imgOptions = [
            'src'           => $file,
            'width'         => $options['thumbWidth'] ?? '',
            'height'        => $options['thumbHeight'] ?? '',
            'ignoreMissing' => $options['ignoreMissing'] ?? true,
            'maxWidth'      => convertToPx($options['maxWidth'] ?? '', true),
            'maxHeight'     => convertToPx($options['maxHeight'] ?? '', true),
            'quickzoom'     => false,
            'wrapperTag'    => '',
        ];
        $img = new Image($imgOptions);
        $html = $img->render();
        if (!$html) {
            return ['', ''];
        }
        $imgUrl = $img->url();
        return [$imgUrl, $html];
    } // prepareImage


} // Gallery
