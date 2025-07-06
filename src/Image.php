<?php

namespace PgFactory\PageFactory;

use Kirby\Filesystem\Asset;
use Throwable;

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
    private string $absFile = '';
    private string $src = '';
    private string $class = '';
    private string $style = '';
    private string $alt = '';
    private string $attributes = '';
    private string $srcset = '';
    private string $caption = '';
    private string $wrapperTag = '';
    private string $wrapperClass = '';
    private string $format = '';
    private int $quality; // %
    private string $unit = '';
    private bool $isRasterImage = true;
    private object $image;
    private float $aspectRatio;
    private mixed $requestedWidth = false;
    private mixed $requestedHeight = false;
    private string $sizes = '';
    private array $responsiveSteps = DEFAULT_SIZES;
    private bool $isAbsoluteUnit = false;
    private bool $quickzoomActive;
    private bool $lazyLoadingActive;

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
        $image = $this->image = $this->getImage();
        if (!$image) {
            return '';
        }
        $this->parseOptions();

        $this->initQuickzoom();
        $this->activateLazyLoading();
        $this->activateKenBurns();

        $html = $this->renderImage();

        if ($this->options['link']??false) {
            $html = $this->applyLinkWrapper($html);
        }

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
     * @return mixed
     */
    public function url()
    {
        return $this->image->url();
    } // url


    /**
     * @return string
     */
    private function renderImage(): string
    {
        $src            = "src='$this->src'";
        $sizes          = $this->sizes;
        $srcset         = '';
        if ($this->isRasterImage) {
            $srcset = $this->determineSrcset();
        } else {
            $this->lazyLoadingActive = false;
        }
        if ($this->lazyLoadingActive) {
            $this->class     .= ' lazyload';
            if ($srcset) {
                $srcset = 'data-' . $srcset;
            }
            $src = 'data-' . $src;
            $u = $this->image->thumb([
                'width' => 200,
                'format' => $this->format,
                'quality' => 50,
            ])->url();
            $src = "src='$u'\n\t\t$src";
        }

        if ($this->requestedWidth) {
            $w = $this->requestedWidth;
            $u = $this->unit;
            if ($u === 'px') {
                $w = intval($w);
            }
            $this->style = "width:$w$u;height: auto;";
        }
        $style = $this->style ? " style='$this->style'" : '';

        $zoomedSrc = '';
        if ($this->lazyLoadingActive && $this->quickzoomActive) {
            // if quickzoom, force lazy preload of large image:
            $zoomedSrc = "\n\t<img $src class='pfy-img-preload lazyload'>";
        }
        if (!$this->wrapperTag) {
            $html = <<<EOT
    <img $this->attributes
        class="pfy-img $this->class"$style
        $src
        $srcset $sizes
    >

EOT;
        } elseif ($this->caption) {
            $html = <<<EOT
<figure class="pfy-img-wrapper pfy-figure $this->wrapperClass">$zoomedSrc
    <img $this->attributes
        class="pfy-img $this->class"$style
        $src
        $srcset $sizes
    >
    <figcaption>$this->caption</figcaption>
</figure>
EOT;

        } else {
            $html = <<<EOT
<$this->wrapperTag class="pfy-img-wrapper $this->wrapperClass">
    <img $this->attributes
        class="pfy-img $this->class"$style
        $src
        $srcset $sizes
    >$zoomedSrc
</$this->wrapperTag><!-- .pfy-img-wrapper -->

EOT;
        }
        return $html;
    } // renderImage


    /**
     * @param $html
     * @return string
     */
    private function applyLinkWrapper(string $html): string
    {
        $options = $this->options;
        $href = $options['link'];

        $linkClass = trim($options['linkClass']." $this->wrapperClass");

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

        $html = <<<EOT
<a href='$href'$linkAttr>$html</a>
EOT;
        return $html;
    } // applyLinkWrapper


    /**
     * @param object $image
     * @return string
     */
    private function determineSrcset(): string
    {
        if ($this->isAbsoluteUnit && !$this->quickzoomActive) {
            $width = intval(convertToPx($this->requestedWidth.$this->unit));
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
        $srcset = $this->image->srcset($sizes);
        if ($srcset) {
            $srcset = str_replace(',', ",\n\t\t\t", $srcset);
            $srcset = "srcset='$srcset'";
        }

        return (string)$srcset;
    } // determineSrcset


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
        class="pfy-img $class"$style
        $src
        $srcset $sizes
    >

EOT;
        } elseif ($caption) {
            $html = <<<EOT
<figure class="pfy-img-wrapper pfy-figure $wrapperClass">$zoomedSrc
    <img $attributes
        class="pfy-img $class"$style
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
        class="pfy-img $class"$style
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
     * @return void
     * @throws \Exception
     */
    private function activateKenBurns(): void
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
    } // activateKenBurns


    /**
     * @return void
     */
    private function parseOptions()
    {
        $options = &$this->options;
        $inx = self::$inx;

        $this->determineRequestedSize(); // -> $this->requestedWidth and $this->requestedHeight

        $this->class          = ($options['class']??'') . " pfy-img-$inx";
        $this->wrapperTag     = ($options['wrapperTag']??false) ?: 'div';
        $this->wrapperClass   = $options['wrapperClass']??'';
        $this->caption        = $options['caption']??'';
        $this->lazyLoadingActive = $options['lazyLoading']??false;
        try {
            $this->alt = $this->image->alt()->value() ?: (($options['alt'] ?? false) ?: ' ');
        } catch (Throwable $e) {
            $this->alt = ($options['alt'] ?? false) ?: ' ';
        }

        $attributes           = $this->attributes;
        if ($options['id']??false) {
            $attributes .= " id='{$options['id']}'";
        } else {
            $attributes .= " id='pfy-img-$inx'";
        }
        $attributes    .= " alt='$this->alt'";
        if ($options['imgTagAttrs']??false) {
            $attributes .= " {$options['imgTagAttrs']}";
        }
        if ($this->quickzoomActive) {
            $attributes .= ' tabindex="0"';
        }

        $this->attributes = $attributes;
    } // parseOptions


    /**
     * @return object|\Kirby\Cms\File
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getImage(): object|null
    {
        $file = $this->options['src'];
        $file = $this->extractSizeDirectiveFromFilename($file);

        $this->absFile = Utils::resolvePath($file);
        if (!file_exists($this->absFile)) {
            if ($this->options['ignoreMissing']??false) {
                return null;
            }
            throw new \Exception("Error: file '{$this->options['src']}' not found");
        }

        $this->format  = fileExt($file);
        $this->isRasterImage = ($this->format !== 'svg');

        $image = $this->getImageObject($file);

        if ($this->isRasterImage) {
            $this->getRasterImage($image);
        } else {
            $this->getVectorImage();
        }
        $this->isAbsoluteUnit = !$this->isRelativeUnit($this->unit);

        $effectiveWidth = 0;
        if ($this->requestedWidth) {
            if ($this->isAbsoluteUnit) {
                $effectiveWidth = min($this->requestedWidth, $this->origWidth);
            } else {
                $effectiveWidth = DEFAULT_MAX_IMAGE_WIDTH;
            }
        } elseif (!$this->requestedHeight && $this->isRasterImage) {
            $effectiveWidth = min($this->origWidth, DEFAULT_MAX_IMAGE_WIDTH);
        }

        $effectiveHeight = 0;
        if ($this->requestedHeight) {
            if ($this->isAbsoluteUnit) {
                $effectiveHeight = min($this->requestedHeight, $this->origHeight);
            } else {
                $effectiveHeight = DEFAULT_MAX_IMAGE_HEIGHT;
            }
        } elseif (!$this->requestedHeight && $this->isRasterImage) {
            $effectiveHeight = min($this->origHeight, DEFAULT_MAX_IMAGE_HEIGHT);
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

        // check and fix erroneous asset Url:
        if (PFY_BASE_OFFSET && !str_starts_with($this->src, PFY_KIRBY_ASSETS_BASE_URL)) {
            $this->src = Utils::resolveUrls($file, forResoucres:true);
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
     * @param string $file
     * @return object|\Kirby\Cms\File|Asset|null
     * @throws \Exception
     */
    private function getImageObject(string $file): object
    {
        $page = page();
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

        } elseif (str_starts_with($file, '~pages/')) {
            // image in folder below content/assets/:
            $path = page(dirname(substr($file, 7)));
            $subdir = page($path);
            if (!$subdir) {
                throw new \Exception("Error: subdirectory '$path' not found");
            }
            $image = $subdir->image(basename($file));

        } else {
            // image outside of content/:
            $fPath = Utils::resolvePath($file, localToApproot:true);
            $image = new Asset($fPath);
        }

        return $image;
    } // getImageObject


    /**
     * @param object $image
     * @return void
     * @throws \Exception
     */
    private function getRasterImage(object $image): void
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
        $this->origWidth = $image->width();
        $this->origHeight = $image->height();
        if (!$this->origWidth) {
            $file = basename($this->absFile);
            throw new \Exception("Error: unable to determine dimensions of image '$file'.");
        }
        $this->aspectRatio = $this->origHeight / $this->origWidth;
    } // getRasterImage


    /**
     * @return void
     */
    private function getVectorImage(): void
    {
        $xmlget = simplexml_load_file($this->absFile);
        $xmlattributes = $xmlget->attributes();
        $width = (string) $xmlattributes->width;
        $height = (string) $xmlattributes->height;
        $unit = false;
        if (!$width && !$height) {
            $viewBox = (string) $xmlattributes->viewBox;
            $elems = explode(' ', $viewBox);
            $width = (int)$elems[2] - (int)$elems[0];
            $height = (int)$elems[3] - (int)$elems[1];
            $unit = 'px';
        } else {
            if ($height) {
                list($height, $unit) = $this->extractUnit($height);
            }
            if ($width) {
                list($width, $unit) = $this->extractUnit($width);
            }
        }

        $this->quality = 100;
        if (!$width) {
            $file = basename($this->absFile);
            throw new \Exception("Error: unable to determine dimensions of image '$file'.");
        }
        $this->aspectRatio = $height / $width;
        if ($unit) {
            $this->unit = $unit;
        }
        $this->origWidth = DEFAULT_MAX_IMAGE_WIDTH;
        $this->origHeight = (int) (DEFAULT_MAX_IMAGE_WIDTH * $this->aspectRatio);
    } // getRasterImage


    /**
     * @return void
     */
    private function determineRequestedSize(): void
    {
        $this->requestedHeight = ($this->options['height'] ?? false) ?: $this->requestedHeight;
        if ($this->requestedHeight) {
            list($this->requestedHeight, $unit) = $this->extractUnit($this->requestedHeight);
            if ($unit) {
                $this->unit = $unit;
            }
        }
        $this->requestedWidth = ($this->options['width'] ?? false) ?: $this->requestedWidth;
        if ($this->requestedWidth) {
            list($this->requestedWidth, $unit) = $this->extractUnit($this->requestedWidth);
            if (!$this->unit && $unit) {
                $this->unit = $unit;
            }
        }
    } // determineRequestedSize


    /**
     * @param string $file
     * @return string
     */
    private function extractSizeDirectiveFromFilename(string $file): string
    {
        // check for and extract size hints in filename:
        if (!preg_match('/(.*)\[(.*?)](\.\w+)/', $file, $m)) {
            return $file;
        }
        
        $file = $m[1] . $m[3];
        $sizeHint = $m[2];
        if ($sizeHint) {
            $unit = false;
            // analyze first part of expression, up to 'x' or end of string, e.g. ""200x150" or "100px":
            if (preg_match('/^([\d.]+)(\D*)/', $sizeHint, $m)) {
                $this->requestedWidth = $m[1];
                $unit = $m[2] ?: 'px';
                // handle units ending in 'x', eg 'px', 'vmax' etc.
                if ($unit === 'x') {
                    $unit = 'px';
                } elseif ((str_ends_with($unit, 'x')) &&
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
            if (preg_match('/^([\d.]+)(\w*)/', $sizeHint, $m)) {
                $this->requestedHeight = $m[1];
                if ($unit === false) {
                    $this->unit = $this->unit ?: ($m[2] ?: 'px');
                }
            }
        }
        return $file;
    } // extractSizeDirectiveFromFilename
    
} // Image
