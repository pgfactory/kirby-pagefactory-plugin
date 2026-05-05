<?php

namespace PgFactory\PageFactory;


class Import
{
    public static int $inx = 1;
    private static bool|null $mdCompile = null;


    /**
     * Macro rendering method
     * @param array $args
     * @return string
     */
    public static function render(array $args): string
    {
        $inx = self::$inx++;

        $file = $args['file'];
        $subfolder = $args['subfolder'];
        $literal = $args['literal'];
        $pre = $args['pre'];
        $highlight = $args['highlight'];
        $wrapperTag = $args['wrapperTag'];
        $wrapperClass = $args['wrapperClass'];
        self::$mdCompile = $args['mdCompile'];
        $elemHeader = $args['elemHeader'] ? $args['elemHeader']."\n" : '';
        $elemFooter = $args['elemFooter'] ? "\n".$args['elemFooter'] : '';

        $str = '';

        // handle subfolder:
        if ($subfolder) {
            $compileMd = self::$mdCompile;
            self::$mdCompile = null;
            $src = Utils::resolvePath($subfolder);
            $folders = getDir($src, true);
            $keys = array_keys($folders);
            natsort($keys);
            $j = 0;
            foreach ($keys as $key) {
                $path = $folders[$key];
                if (is_dir($path)) {
                    $s = $elemHeader.self::importFile("~/$path$file", $pre, $literal)."$elemFooter\n\n";
                } elseif (is_file($path)) {
                    $s = $elemHeader.self::importFile("~/$path", $pre, $literal)."$elemFooter\n\n";
                } else {
                    continue;
                }
                if ($wrapperTag) {
                    $j++;
                    $str .= <<<EOT

<$wrapperTag class='pfy-imported-elem pfy-imported-elem-$j $wrapperClass'>
$s
</$wrapperTag><!-- /pfy-imported-elem-$j -->

EOT;
                } else {
                    $str .= $s;
                }
            }
            if ($compileMd) {
                $str = compileMarkdown($str);
            }
            // handle 'file':
        } elseif ($file) {
            $str = self::importFile($file, $pre, $literal);
        }

        if ($pre) {
            $str = str_replace(['{{','<'], ['&#123;{', '&lt;'], $str);
            $str = str_replace('/', '&#47;', $str);
            if ($highlight) {
                if ($highlight === true) {
                    $str = self::doHighlight($str, '```', postfix: '3');
                    $str = self::doHighlight($str, '``', postfix: '2');
                    $str = self::doHighlight($str, '`', postfix: '1');
 //                } else {
 //ToDo: explicit patterns
                }
            }
            $str = shieldStr($str);
        }
        if ($pre && !$wrapperTag) {
            $wrapperTag = 'pre';
        }
        if ($args['translate']) {
            $str = TransVars::compile($str);
        }

        if ($wrapperTag) {
            $str = <<<EOT

<$wrapperTag class='pfy-imported pfy-imported-$inx $wrapperClass'>
$str</$wrapperTag>

EOT;
        }
        return $str;
    } // render


    /**
     * @param string $str
     * @param string $pattern
     * @param int $position
     * @param string $postfix
     * @return string
     * @throws \Exception
     */
    private static function doHighlight(string $str, string $pattern, int $position = 0, string $postfix = ''): string
    {
        [$p1, $p2] = strPosMatching($str, $position, $pattern, $pattern);
        $l = strlen($pattern);
        while ($p1 !== false) {
            $s1 = substr($str, 0, $p1);
            $s2 = substr($str, $p1+$l, $p2-$p1-$l);
            $s3 = substr($str, $p2+$l);
            $str = $s1."<span class='hl$postfix'>$s2</span>".$s3;
            [$p1, $p2] = strPosMatching($str, $p2+$l, $pattern, $pattern);
        }
        return $str;
    } // doHighlight


    /**
     * Imports file(s), markdown-compiles it if necessary
     * @param string $file
     * @param bool $literal
     * @return string
     */
    private static function importFile(string $file, bool $pre = true, bool $literal = false): string
    {
        $str = '';
        if ($file && (strpbrk($file, '*{') !== false || $file[strlen($file)-1] === '/')) {
            if (($file[0]??false) !== '~') {
                $file = "~page/$file";
            }
            $resolved = Utils::resolvePath($file);
            $files = getDir($resolved);
            foreach ($files as $key => $f) {
                $files[$key] = "~/$f";
            }
        } else {
            $files = [$file];
        }
        foreach ($files as $f) {
            if ($f && ($f[0] !== '~') && ($f[0] !== '/')) {
                $f = "~page/$f";
            }
            $f = Utils::resolvePath($f);
            if ($literal) {
                $str .= @file_get_contents($f) ?: '';
                continue;
            }
            if ($pre) {
                $s = @file_get_contents($f) ?: '';
            } else {
                $s = getFile($f);
            }
            if (self::$mdCompile || (fileExt($f) === 'md' && self::$mdCompile !== null)) {
                $s = compileMarkdown($s);
            }
            $str .= $s;
        }
        return $str;
    } // importFile
} // Import
