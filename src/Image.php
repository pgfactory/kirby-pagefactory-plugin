<?php

namespace PgFactory\PageFactory;

const DEFAULT_MAX_IMAGE_WIDTH = 1920;
const DEFAULT_MAX_IMAGE_HEIGHT = 1440;
const SRCSET_START_SIZE = 384;
const SRCSET_DEFAULT_STEP_SIZE = 384;
const SRCSET_REQUIRED_BREAKPOINT = 500;

class Image
{
    public static $instanceCount = 0;
    public int $inx = 0;
    private $options;
    private bool $isRelativeSize = false;
    private bool|null $showQuickView = false;
    private object|null $kirbyFileObj = null;
    private string $srcFilePath = '';
    private string $srcFileUrl = '';
    private int $maxWidth = DEFAULT_MAX_IMAGE_WIDTH;
    private int $maxHeight = DEFAULT_MAX_IMAGE_HEIGHT;
    private int $width = 0;
    private int $origWidth = 0;
    private int $height = 0;
    private int $origHeight = 0;
    private float $ratio = 0.0;
    private string $sizeHint = '';
    private string $widthStr = '';
    private string $imgStyle = '';
    private string $imgClass = '';
    private static bool $quickViewInitialized = false;


    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->inx = self::$instanceCount++;
        $this->parseOptions($options);
    } // __construct


    /**
     * @param $options
     * @return void
     * @throws \Exception
     */
    private function parseOptions($options)
    {
        $this->options = &$options;

        $srcFilePath = $options['src'];
        $this->srcFilePath = &$srcFilePath;

        // extract optional sizeHint:
        if (preg_match('/(.*)\[(.*?)](\.\w+)/', $srcFilePath, $m)) {
            $srcFilePath = $m[1] . $m[3];
            $this->sizeHint = $m[2];
        }

        // determine whether file is managed by Kirby:
        if (str_starts_with($srcFilePath, '~page/')) {
            $file = substr($srcFilePath, 6);
            if (str_contains($file, '/')) {
                $this->kirbyFileObj = page()->children()->images()->find($file);
            } else {
                $this->kirbyFileObj = page()->images()->find($file);
            }

        } elseif (str_starts_with($srcFilePath, '~assets/')) {
            $file = substr($srcFilePath, 1);
            if (!$obj = page(dirname($file))) {
                throw new \Exception("Image file not found: '$srcFilePath'");
            }
            $images = $obj->images();
            if (!$this->kirbyFileObj = $images->find(basename($file))) {
                throw new \Exception("Image file not found: '$srcFilePath'");
            }

        } else {
            if (str_contains($srcFilePath, '/')) {
                $this->kirbyFileObj = page()->children()->images()->find($srcFilePath);
            } else {
                $this->kirbyFileObj = page()->images()->find($srcFilePath);
            }
        }
        if (!$this->kirbyFileObj) {
            throw new \Exception("Image file not found: '{$options['src']}'");
        }
        $srcFilePath = $this->getPath();

        // check image-info file for arguments:
        $this->getImgAttribFileInfo();

        $this->imgClass = $options['class']??'';

        $this->showQuickView = $options['quickview']??null;


        foreach ($options as $key => $value) {
            if (isset($this->$key) && !str_contains(',width,height,srcFileUrl,', ",$key,")) {
                $this->$key = $value;
            }
        }

        $this->determineImageSize();

    } // parseOptions


    /**
     * @return string
     * @throws \Exception
     */
    public function html()
    {
        $attributes = '';
        $options = $this->options;
        if ($options['id']) {
            $attributes .= " id='{$options['id']}'";
        } else {
            $attributes .= " id='pfy-img-$this->inx'";
        }

        $srcSet = $this->renderSrcset();
        $this->srcFileUrl = $this->resizeImage([
            'src' => $this->srcFilePath,
            'width' => $this->width,
            'height' => $this->height,
        ]);

        if ($options['alt']??false) {
            $alt = str_replace("'", '&#39;', $options['alt']);
        } else {
            $alt = ' ';
        }
        $attributes .= " alt='$alt'";

        if ($options['attributes']) {
            $attributes .= " {$options['attributes']}";
        }
        if ($options['imgTagAttributes']) {
            $attributes .= " {$options['imgTagAttributes']}";
        }
        if ($options['quickview'] ||
            (($options['quickview'] === null) && (PageFactory::$config['imageAutoQuickview']??false))) {
            $attributes .= $this->renderQuickview();
        }
        $attributes = "class='pfy-img pfy-img-$this->inx $this->imgClass' $attributes$srcSet";
        if (!$this->srcFileUrl) {
            throw new \Exception("Error: image file '{$options['src']}' not found.");
        }
        $attributes = "src='$this->srcFileUrl' $attributes";
        if ($this->imgStyle) {
            $attributes .= " style='".trim($this->imgStyle)."'";
        }

        $html = "<img $attributes >";

        if ($options['link']) {
            $html = $this->applyLinkWrapper($html);
        }

        if ($options['wrapperTag'] !== false) {
            $html = $this->applyWrapper($html);
        }
        return $html;
    } // html


    /**
     * @return string|false
     */
    public function root(): string|false
    {
        return $this->srcFilePath;
    } // url


    /**
     * @return string
     */
    public function url(): string
    {
        return $this->srcFileUrl;
    } // url


    /**
     * @return void
     * @throws \Exception
     */
    private function determineImageSize(): void
    {
        $srcFilePath = &$this->srcFilePath;
        $this->srcFileUrl = $this->getUrl();
        $srcFilePath = $this->getPath();
        $dim = $this->kirbyFileObj->dimensions();
        $this->origWidth = $dim->width;
        $this->origHeight = $dim->height;
        $this->ratio = $this->origWidth / $this->origHeight;

        // determine max values:
        $width  = $this->maxWidth = min($this->origWidth, $this->maxWidth);
        $height = $this->maxHeight = min($this->origHeight, $this->maxHeight);

        // get requested sizes from macro args 'width'/'height':
        $requestedWidth = $this->options['width']??false;
        $requestedHeight = $this->options['height']??false;

        // check size-hints embedded in filename, e.g. pic[20vw]:
        if ($this->sizeHint && !$requestedWidth && !$requestedHeight) {
            // 10xy {x ...}
            if (preg_match('/^(\d+(px|cm|mm|in|pt|pc|%|rem|em|ex|ch|vw|vh|vmin|vmax)?)/', $this->sizeHint, $m)) {
                $requestedWidth = $m[1];
            }
            // ... x 10xy:
            if (preg_match('/x\s*(\d+(px|cm|mm|in|pt|pc|%|rem|em|ex|ch|vw|vh|vmin|vmax)?)$/', $this->sizeHint, $m)) {
                $requestedHeight = $m[1];
            }
        }

        // parse $requestedWidth:
        if (preg_match('/([\d.]+)([\w%]*)/', $requestedWidth, $m)) {
            $requestedWidth = $m[1];
            $unit = $m[2];
            if (isRelativeUnit($unit)) {
                $this->widthStr = $m[0];
                $this->isRelativeSize = true;
                $this->showQuickView = true;
                $requestedWidth = false;
            } else {
                // absolute size:
                $requestedWidth = convertToPx($requestedWidth);
                $this->maxWidth = $requestedWidth;
                $this->widthStr = $requestedWidth.'px';
            }
        } else {
            $this->widthStr = $width.'px';
        }
        // parse $requestedHeight:
        if (preg_match('/([\d.]+)([\w%]*)/', $requestedHeight, $m)) {
            $requestedHeight = $m[1];
            $unit = $m[2];
            if (isRelativeUnit($unit)) {
                $this->isRelativeSize = true;
                $this->showQuickView = true;
                $requestedHeight = false;
            } else {
                // absolute size:
                $requestedHeight = convertToPx($requestedHeight);
                $this->maxHeight = $requestedHeight;
                $this->widthStr = $requestedWidth.'px';
            }
        }

        // only absolute sizes beyond this point.
        // complement if one of width/height is missing:
        if ($requestedWidth && !$requestedHeight) {
            $requestedHeight = intval($requestedWidth / $this->ratio);
        }
        if (!$requestedWidth && $requestedHeight) {
            $requestedWidth = intval($requestedHeight * $this->ratio);
        }

        if ($requestedWidth) {
            $width = min($requestedWidth, $width);
            if ($width !== $this->maxWidth) {
                if ($this->showQuickView !== false) {
                    $this->showQuickView = true;
                }
            }
        }
        if ($requestedHeight) {
            $height = min($requestedHeight, $height);
            if ($height !== $this->maxHeight) {
                if ($this->showQuickView !== false) {
                    $this->showQuickView = true;
                }
            }
        }

        $this->width = $width;
        $this->height = $height;
    } // determineImageSize


    /**
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getImgAttribFileInfo(): void
    {
        $attribFile = $this->srcFilePath . '.txt';
        $attribs = loadFile($attribFile);
        if ($attribs) {
            $args = extractKirbyFrontmatter($attribs);
            if ($args) {
                foreach ($args as $key => $value) {
                    $this->options[$key] = $value;
                }
            }
        }
    } // getImgAttribFileInfo


    /**
     * @return string
     * @throws \Exception
     */
    private function renderQuickview()
    {
        // skip quickview, if image source is small:
        if (!$this->showQuickView && ($this->width >= $this->maxWidth || $this->height >= $this->maxHeight)) {
           return '';
        }

        $this->prepareQuickview();

        $largeImg = $this->resizeImage([
            'width' => $this->maxWidth,
            'height' => $this->maxHeight,
        ]);
        $attr = " data-zoom-src='$largeImg'";
        $this->imgClass .= ' pfy-quickview';
        return $attr;
    } // renderQuickview


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
     * @return string
     */
    private function applyWrapper(string $str): string
    {
        $wrapperTag = 'div';
        if ($this->options['wrapperTag'] !== null) {
            $wrapperTag = $this->options['wrapperTag'];
        }

        $wrapperClass = $this->options['wrapperClass'];
        if ($this->options['caption']) {
            $caption = $this->options['caption'];
            $str = <<<EOT

<figure class="pfy-img-wrapper pfy-figure $wrapperClass">
$str
<figcaption>$caption</figcaption>
</figure>

EOT;
        } else {
            $str = <<<EOT

<$wrapperTag class="pfy-img-wrapper $wrapperClass">
$str
</$wrapperTag>

EOT;
        }
        return $str;
    } // applyWrapper


    /**
     * @param $force
     * @return string
     * @throws \Exception
     */
    public function renderSrcset($force = false)
    {
        // determine whether srcset is required:
        $maxWidth = $this->maxWidth;
        $sizes = [];
        for ($w=SRCSET_START_SIZE; $w <= $maxWidth; $w += SRCSET_DEFAULT_STEP_SIZE) {
            $sizes[] = $w;
        }
        if (!$sizes) {
            $this->imgStyle .= " max-width: min(100%, $this->widthStr);";
            return '';
        }
        $srcset = $this->kirbyFileObj->srcset($sizes);
        $srcset = $this->fixUrls($srcset);
        $srcset = str_replace(', ', ",\n", $srcset);
        $html = "\n\tsrcset='\n$srcset'";
        $html .= "\n\tsizes='$this->widthStr'";
        if ($this->isRelativeSize) {
            $this->imgStyle .= " width: $this->widthStr;";
        }
        $this->imgStyle .= " max-width: min(100%, $this->widthStr);";
        $html = rtrim($html, ",\n");
        return $html;
    } // renderSrcset


    /**
     * @param array $options
     * @return bool|string
     * @throws \Exception
     */
    public function resizeImage(array|false $options = false): bool|string
    {
        if (!$options) {
            $src =          $this->srcFilePath;
            $width =        $this->width;
            $height =       $this->height;
            $maxWidth =     $this->maxWidth;
            $maxHeight =    $this->maxHeight;
        } else {
            $src = $options['src'] ?? false;
            $width = $options['width'] ?? false;
            $height = $options['height'] ?? false;
            $maxWidth = $options['maxWidth'] ?? IMG_MAX_WIDTH;
            $maxHeight = $options['maxHeight'] ?? IMG_MAX_HEIGHT;
        }

        if (!$src) {
            $src = $this->srcFilePath;
        }
        if (!$src || !file_exists($src)) {
            return false;
        }

        $ratio = $this->ratio;
        $width = max($width, (int)ceil($height * $ratio));
        $height = max($height, (int)ceil($width / $ratio));
        $width = min($width, $maxWidth);
        $height = min($height, $maxHeight);

        // check and fix aspect ratio of new image:
        $r = $width / $height;
        if ($r !== $ratio) {
            if ($ratio > 1) {
                $height = intval(ceil($width / $ratio));
            } else {
                $width = intval(ceil($height * $ratio));
            }
        }

        $resizedImg = $this->kirbyFileObj->resize($width, $height);
        return  $this->getUrl($resizedImg);
    } // resizeImage


    /**
     * @return string
     */
    private function getPath(): string{
        $path = $this->kirbyFileObj->root();
        if (str_starts_with($path, PageFactory::$absAppRoot)) {
            $path = substr($path, strlen(PageFactory::$absAppRoot));
        }
        return $path;
    } // getPath


    /**
     * @param string|object $url
     * @return string
     */
    private function getUrl(mixed $url = false): string
    {
        if (!$url) {
            $url = $this->kirbyFileObj->url();
        } elseif (is_object($url)) {
            $url = $url->url();
        }
        if (str_starts_with($url, PageFactory::$hostUrl)) {
            $url = substr($url, strlen(PageFactory::$hostUrl)-1);
        } elseif (PageFactory::$appUrl && !str_starts_with($url, PageFactory::$appUrl)) {
            $url = '';
        }
        return $url;
    } // getUrl


    /**
     * @param string $url
     * @return string
     */
    public static function fixUrls(string $url): string
    {
        $patt = substr(PageFactory::$hostUrl, 0, -1);
        return str_replace($patt, '', $url);
    } // fixUrls


    /**
     * @return void
     * @throws \Exception
     */
    private function prepareQuickview(): void
    {
        if (!self::$quickViewInitialized && ($options['quickview']??true)) {
            self::$quickViewInitialized = true;
            PageFactory::$pg->addAssets('media/plugins/pgfactory/pagefactory/js/medium-zoom.min.js');
            $js = <<<EOT
const zoom = mediumZoom('.pfy-quickview', {background:'#444', margin:4});
EOT;
            PageFactory::$pg->addJsReady($js);
        }
    } // prepareQuickview

} // Image