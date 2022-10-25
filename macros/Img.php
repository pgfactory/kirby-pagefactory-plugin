<?php

/*
 * Img() macro
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'src' => ['Image source file.', false],
        'alt' => ['Alt-text for image, i.e. a short text that describes the image.', false],
        'id' => ['Id that will be applied to the image.', false],
        'class' => ['Class-name that will be applied to the image.', ''],
        'caption' => ['Optional caption. If set, Lizzy will wrap the image into a &lt;figure> tag and wrap the caption itself in &lt;figcaption> tag.', false],
        'srcset' => ["Let's you override the automatic srcset mechanism.", ''],
        'imgTagAttributes' => ["Supplied string is put into the &lt;img> tag as is. This way you can apply advanced attributes, such as 'sizes' or 'crossorigin', etc.", false],
        'quickview' => ["If true, activates the quickview mechanism (default: false). Quickview: click on the image to see in full size.", ''],
        // 'lateImgLoading' => ["If true, activates the lazy-load mechanism: images get loaded after the page is ready otherwise.", false],

        'link' => ["Wraps a &lt;a href='link-argument'> tag round the image..", false],
        'linkClass' => ["Class applied to &lt;a> tag", false],
        'linkTitle' => ["Title-attribute applied to &lt;a> tag, e.g. linkTitle:'opens new window'", false],
        'linkTarget' => ["Target-attribute applied to &lt;a> tag, e.g. linkTarget:_blank", false],
        'linkAttributes' => ["Attributes applied to the \<a> tag, e.g. 'download'.", false],
    ],
    'summary' => <<<EOT
Renders an image tag.

Configuration options in 'site/config/pagefactory.php':

    'imageAutoQuickview'  \=> true,  \// turns quickview on by default
    'imageAutoSrcset'  \=> true,     \// turns srcset on by default

**Note**: if an attribute file exists (i.e. image-filename + '.txt') that will be read to extract attributes. 

EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class Img extends Macros
{
    public static $inx = 1;
    private $srcFileObj = false;
    private $class = false;
    private $srcFileUrl = false;
    private $srcFilePath = false;
    private $args = [];
    private $srcsetDefaultStepSize = 250;
    private $imageDefaultMaxWidth = 1920;

    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render(array $args, string $argStr): string
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
        if ($args['class']) {
            $this->class = $args['class'];
        } else {
            $this->class = "pfy-img pfy-img-$inx";
        }
        if ($args['alt']) {
            $alt = str_replace("'", '&#39;', $args['alt']);
            $attributes .= " alt='$alt'";
        }
        if ($args['imgTagAttributes']) {
            $attributes .= " {$args['imgTagAttributes']}";
        }
        if (($args['quickview'] === true) ||
            (($args['quickview'] === '') && @$this->pfy->config['imageAutoQuickview'])) {
            $attributes .= $this->renderQuickview();
        }

        // determine srcset/sizes attributes:
        if (($args['srcset'] !== false) ||
            (($args['srcset'] === '') && @$this->pfy->config['imageAutoSrcset'])) {
            $attributes .= $this->renderSrcset();
        }


        if (trim($this->class)) {
            $attributes = "class='{$this->class}' $attributes";
        } else {
            $attributes = "class='pfy-img-$inx' $attributes";
        }
        if (!$src) {
            throw new \Exception("Error: image file '{$args['src']}' not found.");
        }
        $attributes = "src='$src' $attributes";

        $str = "<img $attributes />";

        if ($args['link']) {
            $str = $this->applyLinkWrapper($str);
        }
        if ($args['caption']) {
            $str = <<<EOT

  <figure class="pfy-figure">
    $str
    <figcaption>{$args['caption']}</figcaption>
  </figure>

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
            if (@$dim[0]) {
                $maxWidth = intval($dim[0]);
                if (strpos($dim[0], 'vw') !== false) {
                    $this->relativeWidth = true;
                }
            }
            if (@$dim[1]) {
                $maxHeight = intval($dim[1]);
                if (strpos($dim[1], 'vw') !== false) {
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
                $files = $this->site->index()->files();
                $file = $files->find($src);
                if ($file) {
                    $this->srcFileObj = $file;
                    $this->srcFileUrl = (string)$file->url();
                }
            } else {
                $file = $this->appRoot.$src;
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
            $file = $file->resize($maxWidth, $maxHeight);
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
        $w = $this->nominalWidth;
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
        $str .= "\n\tsizes='{$this->nominalWidth}px'";
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
        $screenSizeBreakpoint = $this->pfy->config['screenSizeBreakpoint'];
        $str .= "\n\tsizes='(max-width: {$screenSizeBreakpoint}px) 100vw, {$this->nominalWidth}vw'";

        $str = rtrim($str, ",\n");
        $str .= " style='width: {$this->nominalWidth}vw; height: auto;'";
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
                $this->imgFullsizeWidth = $w0;
                $this->imgFullsizeHeight = $h0;
                $qvDataAttr = " data-qv-src='{$this->srcFileUrl}' data-qv-width='$w0' data-qv-height='$h0'";
                PageFactory::$pg->addAssets('QUICKVIEW');
                if (strpos($this->class, 'pfy-quickview') === false) {
                    $this->class .= ' pfy-quickview';
                }
            }
        }
        return $qvDataAttr;
    } // renderQuickview

} // Img




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
