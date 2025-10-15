<?php

namespace PgFactory\PageFactory;

use Kirby\Filesystem\Asset;
use Throwable;

const DEFAULT_MAX_IMAGE_WIDTH = 1920;
const DEFAULT_MAX_IMAGE_HEIGHT = 1440;

if (!defined('DEFAULT_SIZES')) {
    define('DEFAULT_SIZES', [200, 300, 600, 900, 1200, 1800, 2400, 3200]);
}
const VECTOR_IMG_TYPES = 'svg';

const PFY_IMG_DEFAULT_OPTIONS = [
    'src' => '',
    'imgTagAttrs' => '',
    'quickzoom' => null,
    'responsiveSteps' => DEFAULT_SIZES,
    'id' => '',
    'class' => '',
    'wrapperTag' => 'div',
    'wrapperClass' => '',
    'lazyLoading' => false,
    'alt' => '',
    'width' => '',
    'height' => '',
    'link' => '',
    'kenburns' => false,
    'caption' => '',
//    '' => '',
];
const PFY_KENBURNS_OPTIONS = [
    'duration' => 'rand',
    'direction' => 'rand',
    'distance' => 'rand',
    'scale' => 'rand',
    'origin' => 'rand',
    'easing' => 'ease-out',
];


class Image
{
    private static $inx = 0;
    private array $options;
    private int $origWidth;
    private int $origHeight;
    private int|float $effectiveWidth;
    private int|float $effectiveHeight;
    private string $absFilePath = '';
    private string $src = '';
    private string $link = '';
    private string $class = '';
    private string $alt = '';
    private string $attributes = '';
    private string $caption = '';
    private mixed $wrapperTag = 'div';
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
    private bool $kenburnsActive = false;
    private bool $quickzoomActive = false;
    private bool $lazyLoadingActive;

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->parseOptions($options);
    } // __construct


    /**
     * @return string
     * @throws \Exception
     */
    public function render(): string
    {
        $this->initQuickzoom();

        if (!$this->getImage()) {
            return '';
        }

        $this->activateLazyLoading();
        $this->activateKenBurns();

        $html = $this->renderImage();

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
            list($srcset, $src) = $this->applyLazyLoading($srcset, $src);
        }

        list($imgStyle, $wrapperStyle) = $this->renderStyles();

        $attributes    = "$this->attributes alt='$this->alt'";
        $imgStyle = $imgStyle ? " style='$imgStyle'" : '';
        $wrapperStyle = $wrapperStyle ? " style='$wrapperStyle'" : '';
        if ($this->requestedWidth || $this->requestedHeight) {
            $this->wrapperClass .= ' pfy-img-100';
        }

        $html = <<<EOT
    <img $attributes
        class="pfy-img $this->class"
        $src
        $srcset $sizes$imgStyle
    >

EOT;
        if ($this->link) {
            $html = $this->applyLinkWrapper($html);
        }


        if ($this->wrapperTag) {
            $html = <<<EOT
<$this->wrapperTag class="pfy-img-wrapper $this->wrapperClass"$wrapperStyle>
$html
</$this->wrapperTag><!-- .pfy-img-wrapper -->

EOT;
        }

        if ($this->caption) {
            $html = <<<EOT
<figure class="pfy-figure">
$html
    <figcaption>$this->caption</figcaption>
</figure>
EOT;

        }

        return $html;
    } // renderImage


    /**
     * @return mixed
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getImage(): mixed
    {
        if (!file_exists($this->absFilePath)) {
            if ($this->options['ignoreMissing']??false) {
                return null;
            }
            throw new \Exception("Error: file '{$this->options['src']}' not found");
        }

        $image = $this->getImageObject();

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
        if ($effectiveWidth && !$effectiveHeight) {
            if ($effectiveWidth > $effectiveHeight / $this->aspectRatio) {
                $effectiveWidth = $effectiveHeight / $this->aspectRatio;
            }
        } elseif (!$effectiveWidth && $effectiveHeight) {
            $effectiveWidth = $effectiveHeight / $this->aspectRatio;
        }
        $this->effectiveWidth = $effectiveWidth;
        $this->effectiveHeight = $effectiveHeight;

        // resize image if required:
        if ($this->origWidth > DEFAULT_MAX_IMAGE_WIDTH) {
            $this->origWidth = DEFAULT_MAX_IMAGE_WIDTH;
        }
        // convert and resize to target format (default webp):
        $image0 = $image->thumb([
            'width' => $this->origWidth,
            'format' => $this->format,
            'quality' => $this->quality,
        ]);
        $this->src = $image0->url();

        if ($effectiveWidth) {
            $image->thumb([
                'width' => intval($effectiveWidth),
                'format' => $this->format,
                'quality' => $this->quality,
            ]);
        }

        try {
            $this->alt = $image->alt()->value() ?: (($this->options['alt'] ?? false) ?: ' ');
        } catch (Throwable $e) {
            $this->alt = ($this->options['alt'] ?? false) ?: ' ';
        }

        $this->image = $image;
        return true;
    } // getImage


    /**
     * @param string $file
     * @return object|\Kirby\Cms\File|Asset|null
     * @throws \Exception
     */
    private function getImageObject(): object
    {
        $file = $this->src;
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

        if ($this->isRasterImage) {
            $this->getRasterImage($image);
        } else {
            $this->getVectorImage();
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
            $file = basename($this->absFilePath);
            throw new \Exception("Error: unable to determine dimensions of image '$file'.");
        }
        $this->aspectRatio = $this->origHeight / $this->origWidth;
    } // getRasterImage


    /**
     * @return void
     */
    private function getVectorImage(): void
    {
        $xmlget = simplexml_load_file($this->absFilePath);
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
                list($height, $unit) = $this->extractCssUnit($height);
            }
            if ($width) {
                list($width, $unit) = $this->extractCssUnit($width);
            }
        }

        $this->quality = 100;
        if (!$width) {
            $file = basename($this->absFilePath);
            throw new \Exception("Error: unable to determine dimensions of image '$file'.");
        }
        $this->aspectRatio = $height / $width;
        if (!$width && !$height) {
            $this->origWidth = DEFAULT_MAX_IMAGE_WIDTH;
            $this->origHeight = (int)(DEFAULT_MAX_IMAGE_WIDTH * $this->aspectRatio);
        } else {
            $this->origWidth = $width;
            $this->origHeight = $height;
        }
    } // getVectorImage


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
     * @return void
     * @throws \Kirby\Exception\Exception
     */
    private function initQuickzoom(): void
    {
        if (!$this->quickzoomActive || $this->options['link']??false) {
            $this->quickzoomActive = false;
            return;
        }

        Assets::addAssets('QUICKZOOM');
        $this->attributes .= ' tabindex="0"';
        $this->wrapperClass .= ' pfy-quickzoom';
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
    } // activateLazyLoading


    private function applyLazyLoading(string $srcset, string $src): array
    {
        $this->class .= ' lazyload';
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
        return array($srcset, $src);
    } // applyLazyLoading


    private function renderStyles(): array
    {
        $imgStyle = $wrapperStyle = '';
        if ($this->requestedWidth) {
            $w = $this->requestedWidth;
            $u = $this->unit;
            if ($u === 'px') {
                $w = intval($w);
            } elseif (is_numeric($w) && !$u) {
                $u = 'px';
            }
            $imgStyle = "width: 100%;";
            $wrapperStyle = "width:$w$u;";
            if (!$this->requestedHeight) {
                $h = $w * $this->aspectRatio . $u;
                $wrapperStyle .= "height:$h;";
            }
        }
        if ($this->requestedHeight) {
            $h = $this->requestedHeight;
            $u = $this->unit;
            if ($u === 'px') {
                $h = intval($h);
            } elseif (is_numeric($h) && !$u) {
                $u = 'px';
            }
            $imgStyle .= "height: 100%;";
            $wrapperStyle .= "height:$h$u;";
            if (!$this->requestedWidth) {
                $w = $h / $this->aspectRatio . $u;
                $wrapperStyle .= "width:$w;";
            }
        }
        // in case of no size requests, make sure that the image presentation is limited by a) natural size and b) container:
        if (!$imgStyle) {
            $imgStyle = "width: min(100%, {$this->effectiveWidth}px); height: min(100%, {$this->effectiveHeight}px);";
        }
        if (!$this->wrapperTag && !$this->caption) {
            $imgStyle = $wrapperStyle;
            $wrapperStyle = '';
        }
        return [$imgStyle, $wrapperStyle];
    } // renderStyles



    /**
     * @return void
     * @throws \Exception
     */
    private function activateKenBurns(): void
    {
        if (!$this->kenburnsActive) {
            return;
        }
        $options = &$this->options;
        $inx = self::$inx;

        if (!($options['id'])) {
            $id = $options['id'] = "pfy-img-$inx";
        } else {
            $id = $options['id'];
        }
        $kenBurnsStr = $options['kenburns']['js'];
        $duration = $options['kenburns']['duration'];
        $easing = $options['kenburns']['easing'];
        $debug = ($options['kenburns']['debug']??false) ? "\n  debug: true," : '';
        $measure = ($options['kenburns']['measure']??false) ? "\n  measure: true," : '';
        $js = <<<EOT
let kenBurnsEffect$inx = new KenBurns('#$id', {
$kenBurnsStr},
{
  duration: $duration,
  easing: "$easing",$debug$measure
  imgInx: $inx,
});

EOT;
        Page::addJsReady($js);
        Page::addAssets('KEN_BURNS');
        $this->wrapperClass .= ' pfy-kenburns';
    } // activateKenBurns



    // === helpers =========================================================================
    /**
     * @param string $str
     * @return array
     */
    private function extractCssUnit(string $str): array
    {
        $unit = $this->unit ?: 'px';
        if (preg_match('/([\d.]+)([a-z%]+)/', $str, $m)) {
            $str = $m[1];
            $unit = $m[2];
        }
        $value = floatval($str);

        return [$value, $unit];
    } // extractCssUnit


    private function extractUnit(string $value, bool $intval = false): array
    {
        $unit = '';
        if (preg_match('/([\d.]+)([a-z%]+)/', $value, $m)) {
            $value = $m[1];
            $unit = $m[2];
        }

        if ($intval) {
            $value = intval($value);
        }

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
     */
    private function determineRequestedSize(): void
    {
        $this->requestedHeight = $this->options['height'] ?: $this->requestedHeight;
        if ($this->requestedHeight) {
            list($this->requestedHeight, $unit) = $this->extractUnit($this->requestedHeight);
            if ($unit) {
                $this->unit = $unit;
            }
        }
        $this->requestedWidth = $this->options['width'] ?: $this->requestedWidth;
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



    /**
     * @return void
     */
    private function parseOptions(array $options): void
    {
        self::$inx++;
        $inx = self::$inx;

        $options += PFY_IMG_DEFAULT_OPTIONS;
        $this->options = $options;
        $options = &$this->options;

        $file = $options['src'];
        $file = $this->extractSizeDirectiveFromFilename($file);
        $this->src = $file;
        $this->absFilePath = Utils::resolvePath($file);

        $options['imgTagAttrs'] = ($options['imgTagAttrs']??false) ?: ($options['imgTagAttributes']??'');

        if (is_array($options['kenburns'])) {
            $this->kenburnsActive = true;
            $options['kenburns'] = $this->parseKenBurnsOptions($options['kenburns']);
        } elseif ($options['kenburns'] === true) {
            $this->kenburnsActive = true;
            $options['kenburns'] = $this->parseKenBurnsOptions([]);
        } else {
            if ($options['quickzoom'] !== null) {
                $this->quickzoomActive = !!$options['quickzoom'];
            } else {
                $this->quickzoomActive = kirby()->option('pgfactory.pagefactory.imageAutoQuickzoom', true);
            }
        }

        if (($l = ($options['lazyLoading']??null)) !== null) {
            $this->lazyLoadingActive = $l;
        } else {
            $this->lazyLoadingActive = PageFactory::$lazyLoading;
        }

        $this->responsiveSteps = $options['responsiveSteps'];


        $this->determineRequestedSize(); // -> $this->requestedWidth and $this->requestedHeight

        $this->class                = ($options['class']) . " pfy-img-$inx";
        $this->wrapperTag           = (($options['wrapperTag']) !== null) ?($options['wrapperTag']??'div'): 'div'; //???
        $this->wrapperClass         = $options['wrapperClass'];
        $this->caption              = $options['caption'];
        $this->lazyLoadingActive    = $options['lazyLoading'];

        $attributes           = $this->attributes;
        if ($options['id']) {
            $attributes .= " id='{$options['id']}'";
        } else {
            $attributes .= " id='pfy-img-$inx'";
        }

        if ($options['imgTagAttrs']) {
            $attributes .= " {$options['imgTagAttrs']}";
        }

        if ($options['width']) {
            list($this->requestedWidth, $this->unit) = $this->extractUnit($options['width']);
        }

        $this->link = $options['link'];
        $this->attributes = $attributes;

        $this->format  = strtolower(fileExt($this->absFilePath));
        $this->isRasterImage = !str_contains(VECTOR_IMG_TYPES, $this->format);
        $this->isAbsoluteUnit = !$this->isRelativeUnit($this->unit);
    } // parseOptions


    private function parseKenBurnsOptions(array $kbOptions): array
    {
        $out = '';
        $kbOptions += PFY_KENBURNS_OPTIONS;

        list($value, $unit) = $this->extractUnit((string)$kbOptions['duration']);
        if (str_starts_with((string)$value, 'rand')) {
            $value = rand(1000, 10000);
        } else {
            if ($unit === 'ms') {
                $value = intval($value);
            } else {
                $value = intval(floatval($value) * 1000);
            }
        }
        $kbOptions['duration'] = $value;

        list($value, $unit) = $this->extractUnit((string)$kbOptions['direction']);
        if (str_starts_with((string)$value, 'rand')) {
            $value = rand(0, 360);
        }
        $out .= "  'direction': $value,\n";

        list($value, $unit) = $this->extractUnit((string)$kbOptions['distance']);
        if (str_starts_with((string)$value, 'rand')) {
            $value = rand(1, 10) / 10;
        }
        $out .= "  'distance': $value,\n";

        // supported formats for scale: 1.5 | 0.6 | 1,2 | [1, 2]:
        $value = $kbOptions['scale'];
        if (!is_array($value) && str_starts_with((string)$value, 'rand')) {
            $value = '[' . (1 + rand(1, 10) / 10) . ',' . (1 + rand(1, 10) / 10) . ']';
        } else {
            if (!is_array($value)) {
                $a = preg_split('/[\s,]/', $value);
                $scale1 = floatval($a[0] ?? 1);
                if ($a[1]??false) {
                    $scale2 = floatval($a[1] ?? 1);
                } else {
                    if ($scale1 < 1) {
                        $scale2 = 1 / $scale1;
                        $scale1 = 1;
                    } else {
                        $scale2 = $scale1;
                        $scale1 = 1;
                    }
                }
                $value = [$scale1, $scale2];
            }
            if (is_array($value)) {
                $value = '[' . implode(', ', $value) . ']';
            }
        }
        $out .= "  'scale': $value,\n";


        $value = $kbOptions['origin'];
        if (is_string($value) && str_starts_with($value, 'rand')) {
            $origin1 = rand(0, 100);
            $origin2 = rand(0, 100);
        } else {
            if (!is_array($value)) {
                if (preg_match('/^(.*?)[\s,]+(.*)$/', (string)$value, $m)) {
                    $value = [$m[1], $m[2]];
                } else {
                    $value = [$value, 1];
                }
            }
            $value = array_values($value);
            $origin1 = $value[0] ?? 1;
            if (str_starts_with((string)$origin1, 'rand')) {
                $origin1 = rand(1, 100);
            } else {
                $origin1 = floatval($origin1);
                if (floor($origin1) < 1) {
                    $origin1 *= 100;
                }
            }
            $origin2 = $value[1] ?? 1;
            if (str_starts_with((string)$origin2, 'rand')) {
                $origin2 = rand(1, 100);
            } else {
                $origin2 = floatval($origin2);
                if (floor($origin2) < 1) {
                    $origin2 *= 100;
                }
            }
        }
        $origin1 /= 100;
        $origin2 /= 100;
        $value = "[$origin1, $origin2]";
        $out .= "  'origin': $value,\n";

       $kbOptions['js'] = $out;

        return $kbOptions;
    } // parseKenBurnsOptions

} // Image
