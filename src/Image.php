<?php

namespace PgFactory\PageFactory;

const DEFAULT_MAX_IMAGE_WIDTH = 1920;
const DEFAULT_MAX_IMAGE_HEIGHT = 1440;
const SRCSET_START_SIZE = 250;
const SRCSET_DEFAULT_STEP_SIZE = 250;

class Image
{
    public static $instanceCount = 0;
    public int $inx = 0;
    private $options;
    private bool $isRelativeSize = false;
    private bool $resizingRequired = false;
    private bool|null $showQuickView = false;
    private object|null $kirbyFileObj = null;
    private string $srcFilePath = '';
    private string $srcFileUrl = '';
    private int $maxWidth = DEFAULT_MAX_IMAGE_WIDTH;
    private int $maxHeight = DEFAULT_MAX_IMAGE_HEIGHT;
    private int $srcsetDefaultStepSize = SRCSET_DEFAULT_STEP_SIZE;
    private int $width = 0;
    private int $origWidth = 0;
    private int $height = 0;
    private int $origHeight = 0;
    private float $ratio = 0.0;
    private string $sizeHint = '';
    private string $widthStr = '';
    private string $heightStr = '';
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
            $this->kirbyFileObj = page()->find($file);
            $srcFilePath = $this->getPathFromKirby();

        } elseif (str_starts_with($srcFilePath, '~')) {
            $srcFilePath = resolvePath($srcFilePath);

        } else {
            if (str_contains($srcFilePath, '/')) {
                $this->kirbyFileObj = page()->children()->images()->find($srcFilePath);
            } else {
                $this->kirbyFileObj = page()->images()->find($srcFilePath);
            }
            if (!$this->kirbyFileObj) {
                $srcFilePath = resolvePath($srcFilePath, relativeToPage: true);
            } else {
                $srcFilePath = $this->getPathFromKirby();
            }
        }

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
        if ($this->resizingRequired) {
            $this->srcFileUrl = $this->resizeImage([
                'src' => $this->srcFilePath,
                'width' => $this->width,
                'height' => $this->height,
            ]);
        }

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
        if ($this->kirbyFileObj) {
            $this->srcFileUrl = self::fixUrl($this->kirbyFileObj->url());
            $srcFilePath = $this->getPathFromKirby();
            $dim = $this->kirbyFileObj->dimensions();
            $this->origWidth = $dim->width;
            $this->origHeight = $dim->height;

        } else {
            if (!file_exists($srcFilePath)) {
                throw new \Exception("File '$srcFilePath' not found.");
            }
            try {
                list($this->origWidth, $this->origHeight) = getimagesize($srcFilePath);
            } catch (\Exception $e) {
                throw new \Exception($e);
            }
            $this->srcFileUrl = self::fixUrl(PageFactory::$appUrl.$srcFilePath);
        }
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
                $requestedWidth = convertToPx($requestedWidth);
            }
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
                $requestedHeight = convertToPx($requestedHeight);
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
            if ($this->resizingRequired = ($width !== $this->origWidth)) {
                if ($this->showQuickView !== false) {
                    $this->showQuickView = true;
                }
            }
        }
        if ($requestedHeight) {
            $height = min($requestedHeight, $height);
            if ($this->resizingRequired = ($height !== $this->origHeight)) {
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
        if (!$this->showQuickView && ($this->width >= $this->origWidth || $this->width >= $this->maxWidth ||
            $this->height >= $this->origHeight || $this->height >= $this->maxHeight)) {
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
        if (!$force && !$this->isRelativeSize) {
            return '';
        }
        $html = '';

        $w1 = SRCSET_START_SIZE;
        $tmpOptions = [];
        while (($w1 < $this->origWidth) && ($w1 < $this->maxWidth)) {
            $tmpOptions['src'] = $this->srcFilePath;
            $tmpOptions['width'] = $w1;
            $tmpOptions['height'] = intval($w1 / $this->ratio);
            $f = $this->resizeImage($tmpOptions);
            $html .= "\t  $f {$w1}w,\n";
            $w1 += $this->srcsetDefaultStepSize;
        }
        $html = rtrim($html, ",\n");
        $html = "\n\tsrcset='\n$html'";

        // Add 'sizes' attribute:
        $html .= "\n\tsizes='$this->widthStr'";
        $this->imgStyle .= " width:$this->widthStr";

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
            $dst =          $this->options['dst']?? false;
            $width =        $this->width;
            $height =       $this->height;
            $maxWidth =     $this->maxWidth;
            $maxHeight =    $this->maxHeight;
        } else {
            $src = $options['src'] ?? false;
            $dst = $options['dst'] ?? false;
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

        if ($this->kirbyFileObj) {
            // file inside content/ -> resize by Kirby:
            $imgUrl = $this->resizeImageByKirby($width, $height);

        } else {
            // file outside content/ -> resize by PHP:
            if (!$dst) {
                $dst = self::mediaPath($src, $width);
            } else {
                $dst = resolvePath($dst);
            }
            $imgUrl = PageFactory::$appUrl.$this->resizeImageNatively($src, $dst, $width, $height);
        }
        return $imgUrl;
    } // resizeImage


    /**
     * @param int $width
     * @param int $height
     * @return string
     */
    private function resizeImageByKirby(int $width, int $height): string
    {
        $resizedImg = $this->kirbyFileObj->resize($width, $height);
        return self::fixUrl($resizedImg);
    } // resizeImageByKirby


    /**
     * @param string $src
     * @param string $dst
     * @param int $width
     * @param int $height
     * @return string
     * @throws \Exception
     */
    private function resizeImageNatively(string $src, string $dst, int $width, int $height): string
    {
        if (file_exists($dst)) {
            return $dst;
        }
        $ext = strtolower(fileExt($src));
        if ($ext === 'svg') {
            return $src;
        }
        preparePath($dst);

        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        switch($ext){
            case 'bmp': $img = imagecreatefromwbmp($src); break;
            case 'gif': $img = imagecreatefromgif($src); break;
            case 'jpg': $img = imagecreatefromjpeg($src); break;
            case 'png': $img = imagecreatefrompng($src); break;
            default : return "Unsupported picture type!";
        }

        $new = imagecreatetruecolor((int)$width, (int)$height);

        // preserve transparency
        if ($ext === "gif" or $ext === "png") {
            imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }

        imagecopyresampled($new, $img, 0, 0, (int)0, 0, (int)$width, (int)$height,
            (int)$this->origWidth, (int)$this->origHeight);

        switch($ext){
            case 'bmp': imagewbmp($new, $dst); break;
            case 'gif': imagegif($new, $dst); break;
            case 'jpg':
                imageinterlace($new, true);
                imagejpeg($new, $dst);
                break;
            case 'png': imagepng($new, $dst); break;
        }
        imagedestroy($new);
        return $dst;
    } // resizeImageNatively


    /**
     * @param string|object $url
     * @return string
     */
    public static function fixUrl(string|object $url): string
    {
        if (is_object($url)) {
            $url = $url->url();
        }
        if (str_starts_with($url, PageFactory::$hostUrl)) {
            $url = substr($url, strlen(PageFactory::$hostUrl)-1);
        } elseif (PageFactory::$appUrl && !str_starts_with($url, PageFactory::$appUrl)) {
            $url = '';
        }
        return $url;
    } // fixUrl


    /**
     * @param string|object $path
     * @return string
     */
    public static function fixPath(string|object $path): string
    {
        if (is_object($path)) {
            return $path->getPathFromKirby();
        }
        if (str_starts_with($path, PageFactory::$absAppRoot)) {
            $path = substr($path, strlen(PageFactory::$absAppRoot));
        }
        return $path;
    } // fixPath


    /**
     * @return string
     */
    private function getPathFromKirby(): string{
        if ($this->kirbyFileObj) {
            $path = $this->kirbyFileObj->root();
            if (str_starts_with($path, PageFactory::$absAppRoot)) {
                $path = substr($path, strlen(PageFactory::$absAppRoot));
            }
            return $path;
        }
        return false;
    } // getPathFromKirby

    /**
     * @param string $srcFilePath
     * @param string|int $width
     * @return string
     */
    public static function mediaPath(string $srcFilePath, string|int $width): string
    {
        $ext = strtolower(fileExt($srcFilePath));
        $path = dirname($srcFilePath).'/';
        $file = base_name($srcFilePath, false);
        $dst = "{$path}_/$file($width).$ext";
        return $dst;
    } // mediaPath


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