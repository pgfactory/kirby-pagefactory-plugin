<?php

namespace PgFactory\PageFactory;

/*
 * HTML structure for baguetteBox:
<a href="path-to-large-image" title="TITLE">
    <img src="path-to-thumbnail" alt="TITLE" style='width:200px;height:150px;object-fit:cover;'>
</a>
*/

class Gallery
{
    /**
     * @param string $file
     * @param array $options
     * @param string $caption
     * @return string
     * @throws \Exception
     */
    public static function renderImage(string $file, array $options, string $caption = ''): string
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
        if (preg_match("/style='(.*?)'/", $html, $m )) {
            $html = str_replace($m[0], "style='{$m[1]} object-fit:cover;'", $html);
        } else {
            $html = substr($html, 0,-1) . "style='object-fit:cover;'>";
        }
        $html = <<<EOT
<a href="$imgUrl" title="$caption">
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
    public static function loadAssets(array $config, int $inx): void
    {
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
                $js .= "    '$key': $value,\n";
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
    public static function getImages(string $path, string $imageCaptionsFile0 = ''): array
    {
        if ($path[0] !== '~') {
            $path = "~page/$path";
        }
        $images = [];

        if ($imageCaptionsFile0) {
            $imageCaptionsFile = resolvePath($imageCaptionsFile0);
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
            $galleryPath = resolvePath($path);
            $pagePath = PFY_PAGE_PATH;
            $files = getDir("$galleryPath*");
            foreach ($files as $image) {
                if (is_file($image) && str_contains('jpg,jpeg,png,gif,bmp', fileExt($image))) {
                    $image = str_replace([PFY_APP_BASE_PATH . 'content/assets/', $pagePath], ['~assets/', '~page/'], $image);
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
    public static function prepareImage(string $file, array $options): array
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


} // Gallery

