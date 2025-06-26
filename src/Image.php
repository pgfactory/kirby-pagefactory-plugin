<?php

namespace PgFactory\PageFactory;

use Kirby\Filesystem\Asset;

const DEFAULT_MAX_IMAGE_WIDTH = 1920;
const DEFAULT_MAX_IMAGE_HEIGHT = 1440;

if (!defined('DEFAULT_SIZES')) {
    define('DEFAULT_SIZES', [200, 300, 600, 900, 1200, 1800, 2400, 3200]);
}

class Image
{
    private static $inx = 0;
    private array $options;
    private int $origWidth;
    private int $origHeight;
    private string $src = '';
    private string $format = '';
    private int $quality; // %
    private string $unit = '';
    private object $image;
    private float $aspectRatio;
    private mixed $requestedWidth = false;
    private mixed $requestedHeight = false;
    private string $sizes = '';
    private array $responsiveSteps = DEFAULT_SIZES;
    private bool $isAbsoluteUnit = false;
    private bool $quickzoomActive;
    private bool $lazyLoadingActive;
    private string $attributes = '';

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        if (!isset($options['imgTagAttrs'])) {
            $options['imgTagAttrs'] = $options['imgTagAttributes'] ?? '';
        }
        $this->options = $options;
        self::$inx++;

        if (($q = ($options['quickzoom']??null)) !== null) {
            $this->quickzoomActive = $q;
        } else {
            $this->quickzoomActive = kirby()->option('pgfactory.pagefactory.quickzoom', false);
        }

        if (($l = ($options['lazyLoading']??null)) !== null) {
            $this->lazyLoadingActive = $l;
        } else {
            $this->lazyLoadingActive = PageFactory::$lazyLoading;
        }

        $this->responsiveSteps = $options['responsiveSteps'] ?? DEFAULT_SIZES;
    } // __construct


    /**
     * @return string
     * @throws \Exception
     */
    public function render(): string
    {
        $options = &$this->options;
        $inx = self::$inx;
        $image = $this->getImage();
        if (!$image) {
            return '';
        }

        $this->initQuickzoom();
        $this->activateLazyLoading();

        $this->handleKenBurns();

        $attributes = $this->attributes;
        if ($options['id']??false) {
            $attributes .= " id='{$options['id']}'";
        } else {
            $attributes .= " id='pfy-img-$inx'";
        }
        $class          = ($options['class']??'') . " pfy-img-$inx";
        $wrapperTag     = ($options['wrapperTag']??false) ?: 'div';
        $wrapperClass   = $options['wrapperClass']??'';
        $caption        = $options['caption']??'';
        $alt            = $image->alt()->value() ?: (($options['alt'] ?? false) ?: ' ');

        $src            = "src='$this->src'";
        $style = '';
        $srcset         = $this->prepareSrcset($image);
        $isVectorGrafic = fileExt($this->src) !== 'svg';
        if ($this->requestedWidth == 0 && $this->unit === 'px') {
            $this->requestedWidth = '100';
            $this->unit = '%';
            if ($isVectorGrafic) { // only pixel images:
                $style = "max-width:{$this->origWidth}px;";
            }
        }
        if ($isVectorGrafic) { // only pixel images:
            $style = "width:$this->requestedWidth$this->unit;$style;height: auto;";
//            $style = "width:$this->requestedWidth$this->unit;$style"; //???
        }
        $sizes          = $this->sizes;

        $attributes    .= " alt='$alt'";

        if ($options['imgTagAttrs']??false) {
            $attributes .= " {$options['imgTagAttrs']}";
        }

        if ($style??false) {
            $style = " style='$style'";
        }
        if ($this->lazyLoadingActive) {
            $src = 'data-' . $src;
            if ($srcset) {
                $srcset = 'data-' . $srcset;
            }
        }
        if ($this->quickzoomActive) {
            $attributes .= ' tabindex="0"';
        }

        $html = $this->applyImgWrapper($caption, $wrapperClass, $attributes, $class, $style, $src, $srcset, $sizes, $wrapperTag);

        return $html;
    } // render


    /**
     * @return string
     * @throws \Exception
     */
    public function html(): string
    {
        return $this->render();
    } // html


    /**
     * @return object|\Kirby\Cms\File
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getImage(): object|null
    {
        $this->format  = $this->options['format']?? kirby()->option('thumbs.format', 'webp');
        if ($this->format === 'avif') {
            throw new \Exception('Image format ".avif" not supported yet.');
        }
        $quality  = $this->options['quality']?? kirby()->option('thumbs.quality', 80);
        if (is_string($quality)) {
            $this->quality = (int)rtrim($quality, '%');
        } else {
            $this->quality = (int)$quality;
        }

        $file = $this->options['src'];

        $file = $this->getSizeInstructions($file);
        $page = page();
        $path = '';
        if (str_starts_with($file, '~page/')) {
            $filename = substr($file, 6);
            if (str_contains($filename, '/')) {
                // image in subfolder of page:
                $path = $page->id() . '/' . dirname($filename);
                $subdir = page($path);
                if (!$subdir) {
                    throw new \Exception("Error: subdirectory '$path' not found");
                }
                $image = $subdir->image(basename($filename));
            } else {
                // image in page folder:
                $image = $page->file($filename);
            }

        } elseif (str_starts_with($file, '~assets/')) {
            // image in folder below content/assets/:
            $path = page(dirname(substr($file, 1)));
            $subdir = page($path);
            if (!$subdir) {
                throw new \Exception("Error: subdirectory '$path' not found");
            }
            $image = $subdir->image(basename($file));

        } else {
            // image outside of content/:
            $fPath = Utils::resolvePath($file, true);
            $image = image($fPath);
        }

        if (!$image) {
            if ($this->options['ignoreMissing']??false) {
                return null;
            }
            throw new \Exception("Error: file '{$this->options['src']}' not found");
        }

        $this->origWidth = $image->width();
        $this->origHeight = $image->height();
        $this->aspectRatio = $this->origHeight / $this->origWidth;

        $effectiveWidth = 0;
        if ($this->requestedWidth) {
            if ($this->isAbsoluteUnit) {
                $effectiveWidth = min($this->requestedWidth, $this->origWidth);
            } else {
                $effectiveWidth = DEFAULT_MAX_IMAGE_WIDTH;
            }
        } elseif ($this->origWidth > DEFAULT_MAX_IMAGE_WIDTH) {
            $effectiveWidth = DEFAULT_MAX_IMAGE_WIDTH;
        }

        $effectiveHeight = 0;
        if ($this->requestedHeight) {
            if ($this->isAbsoluteUnit) {
                $effectiveHeight = min($this->requestedHeight, $this->origHeight);
            } else {
                $effectiveHeight = DEFAULT_MAX_IMAGE_HEIGHT;
            }
        } elseif ($this->origHeight > DEFAULT_MAX_IMAGE_HEIGHT) {
            $effectiveHeight = DEFAULT_MAX_IMAGE_HEIGHT;
        }

        // case height but no width defined:
        if ($effectiveWidth && $effectiveHeight) {
            if ($effectiveWidth > $effectiveHeight / $this->aspectRatio) {
                $effectiveWidth = $effectiveHeight / $this->aspectRatio;
                if ($this->requestedHeight && is_numeric($this->requestedHeight)) {
                    $this->requestedWidth = $this->requestedHeight / $this->aspectRatio;
                }
            } elseif ($effectiveHeight > $effectiveWidth * $this->aspectRatio) {
                $effectiveHeight = $effectiveWidth * $this->aspectRatio;
                $this->requestedHeight = $effectiveWidth * $this->aspectRatio;
            }
        } elseif (!$effectiveWidth && $effectiveHeight) {
            $effectiveWidth = $effectiveHeight / $this->aspectRatio;
        } elseif ($effectiveWidth && !$effectiveHeight) {
            $effectiveHeight = $effectiveWidth / $this->aspectRatio;
        }

// ToDo: automate preparation of source image file:
//      reformat image file in case its original has not target format or is too big:
//        $fileFormat = fileExt($file);
//        if ($fileFormat !== $this->format) {
//            $w = min($this->origWidth, DEFAULT_MAX_IMAGE_WIDTH);
//            $image = $image->thumb([
//                'width' => $w,
//                'format' => $this->format,
//            ]);
//            $newImgPath = $image->root();
//            if (fileExt($newImgPath)) {
//                copy($newImgPath, $file);
//            }
//        }

        // resize image if required:
        if ($this->origWidth > DEFAULT_MAX_IMAGE_WIDTH) {
            $this->origWidth = DEFAULT_MAX_IMAGE_WIDTH;
            $image0 = $image->thumb([
                'width' => $this->origWidth,
                'format' => $this->format,
                'quality' => $this->quality,
            ]);
            $this->src = $image0->url();
        } else {
            $this->src = $image->url();
        }


        if ($effectiveWidth) {
            $image->thumb([
                'width' => intval($effectiveWidth),
                'format' => $this->format,
                'quality' => $this->quality,
            ]);
        }
        if ($this->isAbsoluteUnit) {
            $this->requestedWidth = round($effectiveWidth, 1);
            $this->requestedHeight = round($effectiveHeight, 1);
        }

        $this->image = $image;
        return $image;
    } // getImage


    /**
     * @return mixed
     */
    public function url()
    {
        return $this->image->url();
    } // url


    /**
     * @param string $file
     * @return string
     */
    private function getSizeInstructions(string $file): string
    {
        if ($this->requestedWidth = ($this->options['width'] ?? false)) {
            list($this->requestedWidth, $this->unit) = $this->extractUnit($this->requestedWidth);
        }
        if ($this->requestedHeight = ($this->options['height'] ?? false)) {
            list($this->requestedHeight, $this->unit) = $this->extractUnit($this->requestedHeight);
        }

        // check for and extract size hints in filename:
        if (preg_match('/(.*)\[(.*?)](\.\w+)/', $file, $m)) {
            $file = $this->parseSizeHint($m);
        }

        if (!$this->isRelativeUnit($this->unit)) {
            if ($this->requestedWidth) {
                $this->requestedWidth = convertToPx($this->requestedWidth.$this->unit);
            }
            if ($this->requestedHeight) {
                $this->requestedHeight = convertToPx($this->requestedHeight.$this->unit);
            }
            $this->unit = 'px';
            $this->isAbsoluteUnit = true;
        }
        if (!$this->unit) {
            $this->unit = 'px';
        }
        if ($this->isAbsoluteUnit) {
            $this->requestedWidth = floatval($this->requestedWidth);
            $this->requestedHeight = floatval($this->requestedHeight);
        }
        return $file;
    } // getSizeInstructions


    /**
     * @param object $image
     * @return string
     */
    public function prepareSrcset(object $image): string
    {
        if ($this->isAbsoluteUnit && !$this->quickzoomActive) {
            $width = intval($this->requestedWidth);
            $sizes = [];
            foreach ([1,2,3] as $size) {
                $sizes[$width * $size] = "{$size}x";
            }
        } else {
            $maxUsedSize = min(3 * DEFAULT_MAX_IMAGE_WIDTH, $this->origWidth);
            $sizes = array_filter($this->responsiveSteps, function ($size) use ($maxUsedSize) {
                return $size <= $maxUsedSize;
            });
            $this->sizes = " sizes='$this->requestedWidth$this->unit'";
        }
        $srcset = $image->srcset($sizes);
        if ($srcset) {
            $srcset = str_replace(',', ",\n\t\t\t", $srcset);
            $srcset = "srcset='$srcset'";
        }

        return (string)$srcset;
    } // prepareSrcset


    /**
     * @param $str
     * @return string
     */
    private function applyLinkWrapper($str, $wrapperClass)
    {
        $options = $this->options;
        $href = $options['link'];

        $linkClass = trim($options['linkClass']." $wrapperClass");

        if ($linkClass) {
            $linkAttr = " class='$linkClass'";
        } else {
            $linkAttr = " class='pfy-img-link'";
        }
        if ($options['linkTarget'] === true) {
            $linkAttr .= " target='_blank'";
        } elseif ($options['linkTarget']) {
            $linkAttr .= " target='{$options['linkTarget']}'";
        }
        if ($options['linkTitle']) {
            $linkTitle = str_replace("'", '&#39;', $options['linkTitle']);
            $linkAttr .= " title='$linkTitle'";
        }
        if ($options['linkAttributes']) {
            $linkAttr .= " {$options['linkAttributes']}";
        }

        $str = <<<EOT
<a href='$href'$linkAttr>$str</a>
EOT;
        return $str;
    } // applyLinkWrapper


    /**
     * @param string $str
     * @return array
     */
    private function extractUnit(string $str): array
    {
        $unit = 'px';
        if (preg_match('/([\d.]+)([\w%]*)/', $str, $m)) {
            $str = $m[1];
            $unit = $m[2];
        }
        $value = floatval($str);

        return [$value, $unit];
    } // extractUnit


    /**
     * @param string $unit
     * @return bool
     */
    private function isRelativeUnit(string $unit): bool
    {
        return $unit && !str_contains(',px,cm,mm,in,pt,pc,', ",$unit,");
    } // isRelativeUnit


    /**
     * @return void
     * @throws \Kirby\Exception\Exception
     */
    private function initQuickzoom(): void
    {
        if (!$this->quickzoomActive) {
            return;
        }

        Assets::addAssets('QUICKZOOM');
        $this->options['class'] = ($this->options['class']??'') . ' pfy-quickzoom';
    } // renderQuickzoom


    /**
     * @return void
     * @throws \Exception
     */
    private function activateLazyLoading(): void
    {
        if (!$this->lazyLoadingActive) {
            return;
        }

        Page::addAssets('LAZY_SIZES');
        $this->options['class'] = ($this->options['class']??'') . ' lazyload';

        if ($this->quickzoomActive) {
            if ($this->isAbsoluteUnit) {
                $u = $this->image->thumb([
                    'width' => intval($this->requestedWidth),
                    'format' => $this->format,
                    'quality' => $this->quality,
                ])->url();
            } else {
                $u = $this->image->thumb([
                    'width' => 300,
                    'format' => $this->format,
                    'quality' => $this->quality,
                ])->url();
            }
            $src = "src='$u'";
            $this->attributes .= "\n\t\t$src";
        }

    } // activateLazyLoading


    /**
     * @param mixed $caption
     * @param mixed $wrapperClass
     * @param string $attributes
     * @param mixed $class
     * @param string $style
     * @param string $src
     * @param string $srcset
     * @param string $sizes
     * @param mixed $wrapperTag
     * @return string
     */
    private function applyImgWrapper(mixed $caption, mixed $wrapperClass, string $attributes, mixed $class, string $style, string $src, string $srcset, string $sizes, mixed $wrapperTag): string
    {
        $zoomedSrc = '';
        if ($this->lazyLoadingActive && $this->quickzoomActive) {
            // if quickzoom, force lazy preload of large image:
            $zoomedSrc = "\n\t<img $src class='pfy-img-preload lazyload'>";
        }
        if (!$wrapperTag) {
            $html = <<<EOT
    <img $attributes
        class="pfy-image $class"$style
        $src
        $srcset $sizes
    >

EOT;
        } elseif ($caption) {
            $html = <<<EOT
<figure class="pfy-img-wrapper pfy-figure $wrapperClass">$zoomedSrc
    <img $attributes
        class="pfy-image $class"$style
        $src
        $srcset $sizes
    >
    <figcaption>$caption</figcaption>
</figure>
EOT;

        } else {
            $html = <<<EOT
<$wrapperTag class="pfy-img-wrapper $wrapperClass">
    <img $attributes
        class="pfy-image $class"$style
        $src
        $srcset $sizes
    >$zoomedSrc
</$wrapperTag><!-- .pfy-img-wrapper -->

EOT;
        }
        if ($this->options['link']??false) {
            $html = $this->applyLinkWrapper($html, $wrapperClass);
        }
        return $html;
    } // applyImgWrapper


    /**
     * @param array $m
     * @return string
     */
    private function parseSizeHint(array $m): string
    {
        $file = $m[1] . $m[3];
        $sizeHint = $m[2];
        if ($sizeHint) {
            $unit = false;
            // analyze first part of expression, up to 'x' or end of string, e.g. ""200x150" or "100px":
            if (!$this->requestedWidth && preg_match('/^([\d.]+)(\D*)/', $sizeHint, $m)) {
                $this->requestedWidth = $m[1];
                $unit = $m[2];
                // handle units ending in 'x', eg 'px', 'vmax' etc.
                if ((str_ends_with($unit, 'x')) &&
                    ($unit !== 'px') && ($unit !== 'ex') &&
                    !str_ends_with($unit, 'max')) {
                    $unit = substr($unit, 0, -1);
                }
                $this->unit = $unit;
                $sizeHint = str_replace($m[0], '', $sizeHint);

                // check whether it was only height expression written as "x100":
            } elseif ($sizeHint[0] === 'x') {
                $sizeHint = substr($sizeHint, 1);
            }
            // analyze remaining expression
            if (!$this->requestedHeight && preg_match('/^([\d.]+)(\w*)/', $sizeHint, $m)) {
                $this->requestedHeight = $m[1];
                if ($unit === false) {
                    $this->unit = $this->unit ?: $m[2];
                }
            }
        }
        return $file;
    } // parseSizeHint


    /**
     * @return void
     * @throws \Exception
     */
    private function handleKenBurns(): void
    {
        $options = &$this->options;
        $inx = self::$inx;
        $kenBurns = $options['kenburns'] ?? false;
        if ($kenBurns) {
            if (!($options['id'] ?? false)) {
                $id = $options['id'] = "pfy-img-$inx";
            } else {
                $id = $options['id'];
            }
            $kenBurnsStr = '';
            foreach ($kenBurns as $k => $v) {
                $kenBurnsStr .= "$k: $v, ";
            }
            $js = <<<EOT
let kenBurnsEffect$inx = new KenBurns('#$id', { $kenBurnsStr });

EOT;
            Page::addJsReady($js);
            Page::addAssets('KEN_BURNS');
        }
    } // handleKenBurns

} // Image
