<?php
namespace PgFactory\PageFactory;


function import($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['[filename] Loads given file and injects its content into the page. '.
            'If "file" contains glob-style wildcards, then all matching files will be loaded. '.
            'If file-extension is ".md", loaded content will be markdown-compiled automatically.', false],
            'subfolder' => ['Looks for file(s) in given sub-folders, e.g. ``subfolder:*``.', false],
            'literal' => ['If true, file content will be rendered as is - i.e. in \<pre> tags.', false],
            'highlight' => ['(true|list-of-markers) If true, patters ``&#96;``, ``&#96;&#96;`` and ``&#96;&#96;&#96;` are used. '.
                'These patterns will be detected and wrapped in "&lt;span class=\'hl{n}\'> elements".', false],
            'mdCompile' => ['If true, file content will be markdown-compiled before rendering.', false],
            'wrapperTag' => ['If defined, output will be wrapped in given tag.', false],
            'wrapperClass' => ['If defined, given class will be applied to the wrapper tag.', ''],
        ],
        'summary' => <<<EOT
# import()

Loads content from a file.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $source) = $str;
    }

    // assemble output:
    $imp = new Import();
    $str = $imp->render($args);

    $str = shieldStr($str);

    return $source.$str;
}



class Import
{
    public static $inx = 1;
    private $mdCompile;


    /**
     * Macro rendering method
     * @param $args                     // array of arguments
     * @return string                   // HTML or Markdown
     */
    public function render(array $args): string
    {
        $inx = self::$inx++;

        $file = $args['file'];
        $subfolder = $args['subfolder'];
        $literal = $args['literal'];
        $highlight = $args['highlight'];
        $wrapperTag = $args['wrapperTag'];
        $wrapperClass = $args['wrapperClass'];
        $this->mdCompile = $args['mdCompile'];

        $str = '';

        // handle subfolder:
        if ($subfolder) {
            $compileMd = $this->mdCompile;
            $this->mdCompile = null;
            $src = resolvePath($subfolder, relativeToPage: true);
            $folders = getDir($src, true);
            $keys = array_keys($folders);
            natsort($keys);
            foreach ($keys as $key) {
                $folder = $folders[$key];
                if (is_dir($folder)) {
                    $str .= $this->importFile("~/$folder$file")."\n\n";
                }
            }
            if ($compileMd) {
                $str = compileMarkdown($str);
            }
            // handle 'file':
        } elseif ($file) {
            $str = $this->importFile($file);
        }

        if ($literal) {
            $str = str_replace(['{{','<'], ['&#123;{', '&lt;'], $str);
            if ($highlight) {
                if ($highlight === true) {
                    $str = $this->doHighlight($str, '```', postfix: '3');
                    $str = $this->doHighlight($str, '``', postfix: '2');
                    $str = $this->doHighlight($str, '`', postfix: '1');
//                } else {
//ToDo: explicit patterns
                }
            }
            $str = str_replace('/', '&#47;', $str);
            $str = shieldStr($str);
        }
        if ($literal && !$wrapperTag) {
            $wrapperTag = 'pre';
        }

        if ($wrapperTag) {
            $str = <<<EOT

<$wrapperTag class='pfy-imported pfy-imported-$inx $wrapperClass'>
$str</$wrapperTag>

EOT;
        }
        return $str;
    } // render


    private function doHighlight($str, $pattern, $position = 0, $postfix = '')
    {
        list($p1, $p2) = strPosMatching($str, $position, $pattern, $pattern);
        $l = strlen($pattern);
        while ($p1 !== false) {
            $s1 = substr($str, 0, $p1);
            $s2 = substr($str, $p1+$l, $p2-$p1-$l);
            $s3 = substr($str, $p2+$l);
            $str = $s1."<span class='hl$postfix'>$s2</span>".$s3;
            list($p1, $p2) = strPosMatching($str, $p2+$l, $pattern);
        }
        return $str;
    } // doHighlight


    /**
     * Imports file(s), markdown-compiles it if necessary
     * @param string $file
     * @return string
     */
    private function importFile(string $file): string
    {
        $str = '';
        if ($file && (((strpbrk($file, '*{') !== false)) || ($file[strlen($file)-1] === '/'))) {
            if (($file[0]??false) !== '~') {
                $file = "~page/$file";
            }
            $file = resolvePath($file);
            $files = getDir($file);
            foreach ($files as $key => $file) {
                $files[$key] = "~/$file";
            }
        } else {
            $files = [$file];
        }
        foreach ($files as $file) {
            if (($file[0]??false) !== '~') {
                $file = "~page/$file";
            }
            $file = resolvePath($file);
            $s = @file_get_contents($file);
            if ($this->mdCompile || (fileExt($file) === 'md' && $this->mdCompile !== null)) {
                $s = compileMarkdown($s);
            }
            $str .= $s;
        }
        return $str;
    } // importFIle
} // Import
