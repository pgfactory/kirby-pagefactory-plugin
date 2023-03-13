<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

use Kirby\Exception\InvalidArgumentException;

function img($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'src' => ['Image source file.', false],
            'alt' => ['Alt-text for image, i.e. a short text that describes the image.', false],
            'id' => ['Id that will be applied to the image.', false],
            'class' => ['Class-name that will be applied to the image.', ''],
            'wrapperTag' => ['If false, image is rendered without a wrapper. Otherwise, defines the tag of the wrapping element', null],
            'wrapperClass' => ['Class to be applied to the wrapper tag.', ''],
            'caption' => ['Optional caption. If set, Lizzy will wrap the image into a &lt;figure> tag and wrap the ".
                "caption itself in &lt;figcaption> tag.', false],
            'srcset' => ["Let's you override the automatic srcset mechanism.", ''],
            'attributes' => ["Supplied string is put into the &lt;img> tag as is. This way you can apply advanced ".
                "attributes, such as 'sizes' or 'crossorigin', etc.", false],
            'imgTagAttributes' => ["Supplied string is put into the &lt;img> tag as is. This way you can apply advanced ".
                "attributes, such as 'sizes' or 'crossorigin', etc.", false],
            'quickview' => ["If true, activates the quickview mechanism (default: false). Quickview: click on the ".
                "image to see in full size.", ''],
            // 'lateImgLoading' => ["If true, activates the lazy-load mechanism: images get loaded after the page is ready otherwise.", false],
    
            'link' => ["Wraps a &lt;a href='link-argument'> tag round the image..", false],
            'linkClass' => ["Class applied to &lt;a> tag", false],
            'linkTitle' => ["Title-attribute applied to &lt;a> tag, e.g. linkTitle:'opens new window'", false],
            'linkTarget' => ["Target-attribute applied to &lt;a> tag, e.g. linkTarget:_blank", false],
            'linkAttributes' => ["Attributes applied to the \<a> tag, e.g. 'download'.", false],
            ],
        'summary' => <<<EOT
# img()

Renders an image tag.

Configuration options in 'site/config/config.php':

    'usility.pagefactory.options' \=> [
        'imageAutoQuickview'  \=> true,  \// turns quickview on by default
        'imageAutoSrcset'  \=> true,     \// turns srcset on by default
    ],

**Note**: if an attribute file exists (i.e. image-filename + '.txt') that will be read to extract attributes. 

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    // assemble output:
    $obj = new Img();
    $str .= $obj->render($options);

    $str = shieldStr($str);

    return $str;
}




class Img
{
    public static $inx = 1;
    private $srcFileObj = false;
    private $class = false;
    private $srcFileUrl = false;
    private $srcFilePath = false;
    private $args = [];
    private $srcsetDefaultStepSize = 250;
    private $imageDefaultMaxWidth = 1920;

    private $nominalWidth;
    private $relativeWidth;
    private $origWidth;
    private $sizesFactor;

    /**
     * Macro rendering method
     * @param array $args
     * @return string
     * @throws InvalidArgumentException
     */
    public function render(array $args): string
    {
        $inx = self::$inx++;
        $this->args = &$args;

        if (!$args['src']) {
           throw new \Exception("Error in Img(): argument 'src' not defined.");
        }

        $src = $this->parseSrcFilename($args['src']);

        $this->getImgAttribFileInfo();

        $attributes = '';
        if ($args['id']) {
            $attributes .= " id='{$args['id']}'";
        } else {
            $attributes .= " id='pfy-img-$inx'";
        }

        $wrapperClass = $args['wrapperClass'];
        $this->class = $args['class'];

        if ($args['alt']) {
            $alt = str_replace("'", '&#39;', $args['alt']);
        } else {
            $alt = ' ';
        }
        $attributes .= " alt='$alt'";

        if ($args['attributes']) {
            $attributes .= " {$args['attributes']}";
        }
        if ($args['imgTagAttributes']) {
            $attributes .= " {$args['imgTagAttributes']}";
        }
        if (($args['quickview'] === true) ||
            (($args['quickview'] === '') && (PageFactory::$config['imageAutoQuickview']??false))) {
            $attributes .= $this->renderQuickview();
            $this->class .= ' pfy-quickview';
        }

        // determine srcset/sizes attributes:
        if (($args['srcset'] !== false) ||
            (($args['srcset'] === '') && (PageFactory::$config['imageAutoSrcset']??false))) {
            $attributes .= $this->renderSrcset();
        }


        $attributes = "class='pfy-img pfy-img-$inx $this->class' $attributes";
        if (!$src) {
            throw new \Exception("Error: image file '{$args['src']}' not found.");
        }
        $attributes = "src='$src' $attributes";

        $str = "<img $attributes >";

        if ($args['link']) {
            $str = $this->applyLinkWrapper($str);
        }

        $wrapperTag = 'div';
        if ($args['wrapperTag'] === false) {
            return $str;
        } elseif ($args['wrapperTag'] !== null) {
            $wrapperTag = $args['wrapperTag'];
        }
        if ($args['caption']) {
            $str = <<<EOT

<figure class="pfy-img-wrapper pfy-figure $wrapperClass">
$str
<figcaption>{$args['caption']}</figcaption>
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
    } // render


    /**
     * Extracts size info from filename, e.g. name[500x300].jpg
     * @param $src
     * @return false|string
     */
    private function parseSrcFilename($src)
    {
        $size = false;
        $this->nominalWidth = false;
        $this->relativeWidth = false;
        if (preg_match('/(.*)\[(.*?)](\.\w+)/', $src, $m)) {
            $src = $m[1].$m[3];
            $size = $m[2];
            $dim = explode('x', $size);
            $maxWidth = null;
            $maxHeight = null;
            if ($dim[0]??false) {
                $maxWidth = $dim[0];
                if (str_contains($maxWidth, 'vw') || str_contains($maxWidth, '%') || str_contains($maxWidth, 'em')) {
                    $this->relativeWidth = true;
                } else {
                $maxWidth = intval($dim[0]).'px';
                }
            }
            if ($dim[1]??false) {
                $maxHeight = intval($dim[1]);
                if (str_contains($dim[1], 'vw') || str_contains($dim[1], '%') || str_contains($dim[1], 'em')) {
                    $this->relativeWidth = true;
                }
            }
        }

        $src = trim($src);
        if (!$src) {
            return '';
        }

        if ($src[0] !== '~') {
            $files = page()->files();
            $file = $files->find($src);
            if ($file) {
                $this->srcFileObj = $file;
                $this->srcFileUrl = (string)$file->url();
                $this->srcFilePath = $file->root();
            }

        } else {
            $src = resolvePath($src);
            $this->srcFilePath = $src;
            if (strpos($src, 'content/') === 0) {
                $src = substr($src, 8);
                $files = site()->index()->files();
                $file = $files->find($src);
                if ($file) {
                    $this->srcFileObj = $file;
                    $this->srcFileUrl = (string)$file->url();
                }
            } else {
                $file = PageFactory::$appRoot.$src;
                $this->srcFileUrl = $file;
                return $file;
            }
        }
        if (!$file) {
            return '';
        }

        $dim = $file->dimensions();
        $this->origWidth = $dim->width;
        if ($size) {
            $w = convertToPx($maxWidth);
            $file = $file->resize($w, $maxHeight);
            $this->nominalWidth = $maxWidth;
        } else {
            $this->nominalWidth = $dim->width;
        }
        if ($file) {
            return substr($file->url(), strlen(PageFactory::$hostUrl)-1);
        }
        return '';
    } // parseSrcFilename


    /**
     * Looks for attribute file (image-filename + .txt), imports attributes found there.
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getImgAttribFileInfo()
    {
        $attribFile = $this->srcFilePath . '.txt';
        $attribs = loadFile($attribFile);
        if ($attribs) {
            $args = extractKirbyFrontmatter($attribs);
            if ($args) {
                foreach ($args as $key => $value) {
                    $this->args[$key] = $value;
                }
            }
        }
    } // getImgAttribFileInfo


    /**
     * Renders srcset attribute -> for relative and absolute sizes
     * @return string
     */
    private function renderSrcset()
    {
        if (!$this->nominalWidth ||
            !$this->srcFileObj ||
            !$this->srcFileObj->isResizable() ||
            ($this->srcFileObj->size() < 50000)) {
            return '';
        }

        if ($this->relativeWidth) {
            return $this->renderRelativeSrcset();
        } else {
            return $this->renderAbsSrcset();
        }
    } // renderSrcset


    /**
     * Renders srcset attrib for absolute sizes
     * @return string
     */
    private function renderAbsSrcset()
    {
        $str = '';
        $l = strlen(PageFactory::$hostUrl);
        $w = convertToPx($this->nominalWidth);
        for ($i=1; $i<=4; $i++) {
            $w1 = $w * $i;
            if ($w1 > $this->origWidth) {
                break;
            }
            $f = $this->srcFileObj->resize($w1)->url();
            $f = substr($f, $l-1);
            $str .= "\t  $f {$i}x,\n";
        }
        $str = rtrim($str, ",\n");
        $str = "\n\tsrcset='\n$str'";
        return $str;
    } // renderAbsSrcset


    /**
     * Renders srcset attrib for relative sizes
     * @return string
     */
    private function renderRelativeSrcset()
    {
        $str = '';
        $l = strlen(PageFactory::$hostUrl);

        $w1 = 250;
        $this->sizesFactor = 250;
        while (($w1 < $this->origWidth) && ($w1 < $this->imageDefaultMaxWidth)) {
            $f = $this->srcFileObj->resize($w1)->url();
            $f = substr($f, $l-1);
            $str .= "\t  $f {$w1}w,\n";
            $w1 += $this->srcsetDefaultStepSize;
        }
        $str = rtrim($str, ",\n");
        $str = "\n\tsrcset='\n$str'";

        // Add 'sizes' attribute
        $screenSizeBreakpoint = PageFactory::$config['screenSizeBreakpoint'];
        $str .= "\n\tsizes='(max-width: {$screenSizeBreakpoint}px) 100vw, {$this->nominalWidth}'";

        $str = rtrim($str, ",\n");
        $str .= " style='width: {$this->nominalWidth}; height: auto;'";
        return $str;
    } // renderSrcset


    /**
     * Adds <a> tag around an image if requested.
     * @param $str
     * @return string
     */
    private function applyLinkWrapper($str)
    {
        $href = $this->args['link'];

        if ($this->args['linkClass']) {
            $linkAttr = " class='{$this->args['linkClass']}'";
        } else {
            $linkAttr = " class='pfy-img-link'";
        }
        if ($this->args['linkTarget'] === true) {
            $linkAttr .= " target='_blank'";
        } elseif ($this->args['linkTarget']) {
            $linkAttr .= " target='{$this->args['linkTarget']}'";
        }
        if ($this->args['linkTitle']) {
            $linkTitle = str_replace("'", '&#39;', $this->args['linkTitle']);
            $linkAttr .= " title='$linkTitle'";
        }
        if ($this->args['linkAttributes']) {
            $linkAttr .= " {$this->args['linkAttributes']}";
        }

        $str = <<<EOT
<a href='$href'$linkAttr>$str</a>
EOT;
        return $str;
    } // applyLinkWrapper


    /**
     * Injects code for quickview feature: attrib, css and jq
     * @return string
     */
    private function renderQuickview()
    {
        $qvDataAttr = '';
        if ((!preg_match('/\bpfy-noquickview\b/', $this->class))  // config setting, but no 'pfy-noquickview' override
            || preg_match('/\bpfy-quickview\b/', $this->class)) { // or 'pfy-quickview' class

            $src = $this->srcFilePath;
            if ($src && file_exists($src)) {
                list($w0, $h0) = getimagesize($src);
                $qvDataAttr = " data-qv-src='{$this->srcFileUrl}' data-qv-width='$w0' data-qv-height='$h0'";
                PageFactory::$assets->addAssets('QUICKVIEW');
                if (strpos($this->class, 'pfy-quickview') === false) {
                    $this->class .= ' pfy-quickview';
                }
            }
        }
        return $qvDataAttr;
    } // renderQuickview

} // Img

