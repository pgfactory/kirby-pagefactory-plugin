<?php

namespace PgFactory\PageFactory;

class Galery
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
        // create thiumbnail and size-variants if necessary:
        list($imgUrl, $thumb, $srcSet) = self::prepareImage($file, $options);

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
    public static function loadAssets(array $config, int $inx): void
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
    public static function prepareImage(string $file, array $options): array|false
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


} // Galery