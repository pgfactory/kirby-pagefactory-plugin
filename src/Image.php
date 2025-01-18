<?php

namespace PgFactory\PageFactory;

const DEFAULT_MAX_IMAGE_WIDTH = 1920;
const DEFAULT_MAX_IMAGE_HEIGHT = 1440;
const DEFAULT_SIZES = [300, 600, 900, 1200, 1800, 2400, 3200];

class Image
{
    private static $inx = 0;
    private array $options;
    private int $origWidth;
    private int $origHeight;
    private string $unit = '';
    private float $aspectRatio;
    private false|string $requestedWidth = false;
    private false|string $requestedHeight = false;
    private bool $isAbsoluteUnit = false;

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
        self::$inx++;
    } // __construct


    /**
     * @return string
     * @throws \Exception
     */
    public function render(): string
    {
        $options = $this->options;
        $inx = self::$inx;
        $image = $this->getImage($options);

        $attributes = '';
        if ($options['id']??false) {
            $attributes .= " id='{$options['id']}'";
        } else {
            $attributes .= " id='pfy-img-$inx'";
        }
        $class          = $options['class']??'';
        $wrapperTag     = ($options['wrapperTag']??false) ?: 'dev';
        $wrapperClass   = $options['wrapperClass']??'';
        $caption        = $options['caption']??'';
        $alt            = $image->alt()->value() ?: ($options['alt'] ?: ' ');
        $src            = $image->url();
        $srcset         = $this->prepareSrcset($image);
        $style          = "width:$this->requestedWidth$this->unit;";
        $sizes          = " sizes='$this->requestedWidth$this->unit'";

        $attributes .= " alt='$alt'";

        if ($options['attributes']??false) {
            $attributes .= " {$options['attributes']}";
        }
        if ($options['imgTagAttributes']??false) {
            $attributes .= " {$options['imgTagAttributes']}";
        }

        if ($style??false) {
            $style = " style='$style'";
        }

        if ($caption) {
            $html = <<<EOT
<figure class="pfy-img-wrapper pfy-figure $wrapperClass">
    <img $attributes
        class="pfy-image $class"$style
        alt="$alt"
        src="$src"
        $srcset$sizes
    >
    <figcaption>$caption</figcaption>
</figure>
EOT;

        } else {
            $html = <<<EOT
<$wrapperTag class="pfy-image-wrapper $wrapperClass">
    <img
        class="pfy-image $class"$style
        alt="$alt"
        src="$src"
        $srcset$sizes
    >
</$wrapperTag><!-- .pfy-image-wrapper -->

EOT;
        }
        if ($options['link']??false) {
            $html = $this->applyLinkWrapper($html);
        }

        return $html;
    } // render


    /**
     * @param array $options
     * @return object|\Kirby\Cms\File
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getImage(array $options): object
    {
        $file = $options['src'];

        $file = $this->getSizeInstructions($file);

        $page = page();
        if (str_starts_with($file, '~page/')) {
            $filename = basename($file);
            $image = $page->image($filename);
        } else {
            throw new \Exception('Not implemented yet');
        }
        if (!$image) {
            throw new \Exception('Error');
        }

        $this->origWidth = $image->width();
        $this->origHeight = $image->height();
        $this->aspectRatio = $this->origWidth / $this->origHeight;

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
        if (!$effectiveWidth && $effectiveHeight) {
            $effectiveWidth = $effectiveHeight * $this->aspectRatio;
        }
        // resize image if required:
        if ($effectiveWidth) {
            $image->resize(intval($effectiveWidth));
        }
        return $image;
    } // getImage


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

        if (preg_match('/(.*)\[(.*?)](\.\w+)/', $file, $m)) {
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
                    if ($unit ===  false) {
                        $this->unit = $this->unit ?: $m[2];
                    }
                }
            }
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
        $this->requestedWidth = floatval($this->requestedWidth);
        $this->requestedHeight = floatval($this->requestedHeight);
        return $file;
    } // getSizeInstructions


    /**
     * @param object $image
     * @return string
     */
    public function prepareSrcset(object $image): string
    {
        if ($this->isAbsoluteUnit) {
            $width = $this->requestedWidth;
            $sizes = [];
            foreach ([1,2,3] as $size) {
                $sizes["{$size}x"] = $width * $size;
            }
        } else {
            $width = DEFAULT_MAX_IMAGE_WIDTH;
            $maxUsedSize = 3 * $width;
            $sizes = array_filter(DEFAULT_SIZES, function ($size) use ($maxUsedSize) {
                return $size <= $maxUsedSize;
            });
        }
        $srcset = $image->srcset($sizes);
        $srcset = str_replace(',', ",\n\t\t\t", $srcset);

        return "srcset='$srcset'";
    } // prepareSrcset


    /**
     * @param $str
     * @return string
     */
    private function applyLinkWrapper($str)
    {
        $options = $this->options;
        $href = $options['link'];

        if ($options['linkClass']) {
            $linkAttr = " class='{$options['linkClass']}'";
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
        return $unit && !str_contains(',px,ex,cm,mm,in,pt,pc,', ",$unit,");
    } // isRelativeUnit

} // Image
