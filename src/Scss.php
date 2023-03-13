<?php

namespace Usility\PageFactory;
use Kirby\Exception\InvalidArgumentException;
use ScssPhp\ScssPhp\Compiler;

class Scss
{
    private static object $scssphp;

    /**
     * @param $pfy
     */
    public function __construct()
    {
        self::$scssphp = new Compiler;
    }


    /**
     * Compiles SCSS (supplied in a string) and renders it as CSS.
     * @param string $scssStr
     * @return string
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public static function compileStr(string $scssStr): string
    {
        if (!self::$scssphp) {
            self::$scssphp = new Compiler;
        }
        $scssStr = self::resolvePaths($scssStr);
        return self::$scssphp->compileString($scssStr)->getCss();
    } // compileStr


    /**
     * @param string $srcFile
     * @param string $targetPath
     * @return string|false
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public static function updateFile(string $srcFile, string $targetPath): string|false
    {
        $targetPath = self::dir_name($targetPath);
        $targetFile = $targetPath.'-'.basename($srcFile, 'scss').'css'; // mark compiled assets with '-'
        $tTarget = fileTime($targetFile);
        $tSrc = fileTime($srcFile);
        if ($tTarget < $tSrc) {
            self::compileFile($srcFile, $targetFile);
            return $targetFile;
        }
        return false;
    } // updateFile


    /**
     * Compiles SCSS (from a file) and renders it as CSS.
     * @param string $srcFile
     * @param string $targetFile
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public static function compileFile(string $srcFile, string $targetFile): void
    {
        if (fileExt($srcFile) !== 'scss') { // skip any non-scss files
            return;
        }
        $srcStr = self::getFile($srcFile);
        self::$scssphp->setImportPaths(dir_name($srcFile));
        $css = self::compileStr($srcStr);
        $css = "/* === Automatically created from ".basename($srcFile)." - do not modify! === */\n\n$css";
        file_put_contents($targetFile, $css);
    } // compileFile


    /**
     * converts path patterns starting with ~
     * @param $srcStr
     * @return array|string|string[]
     */
    private static function resolvePaths($srcStr)
    {
        $appRoot = PageFactory::$appUrl;
        $pathPatterns = [
            '~/'            => $appRoot,
            '~assets/'       => $appRoot.'assets/',
        ];
        $srcStr = str_replace(array_keys($pathPatterns), array_values($pathPatterns), $srcStr);

        return $srcStr;
    } // resolvePaths


    /**
     * @param string $path
     * @return string
     */
    private static function dir_name(string $path): string
    {
        if (!$path || str_starts_with($path, '/')) {
            return $path;
        }
        if (str_contains(basename($path), '.')) {  // if it contains a '.' we assume it's a file
            return dirname($path) . '/';
        } else {
            return rtrim($path, '/') . '/';
        }
    } // dir_name


    /**
     * Reads a file and injects comments cotaining line numbers, if requested by settings
     * @param string $file
     * @return string
     * @throws InvalidArgumentException
     */
    private static function getFile(string $file): string
    {
        $compileScssWithLineNumbers = PageFactory::$config['debug_compileScssWithLineNumbers'];
        if ($compileScssWithLineNumbers) {
            if (!file_exists($file)) {
                throw new \Exception("Error: file '$file' not found.");
            }
            $fname = basename($file);
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $out = '';
            $inComment = false;
            foreach ($lines as $i => $l) {
                $cont = self::skipComments($l, $inComment);
                if ($cont === 'break') {
                    break;
                } elseif ($cont === 'continue') {
                    continue;
                }
                if (preg_match('|^ [^/*]+ {|x', $l)) {  // add line-number in comment
                    $l .= " /* content: '$fname:".($i+1)."'; */";
                }
                if ($l) {
                    $out .= $l . "\n";
                }
            }
            $out = self::removeEmptyRules($out);
        } else {
            $out = loadFile($file);
        }
        return $out . "\n";
    } // getFile


    /**
     * Checks for c-style comments as well as __END__ marker.
     * @param $l
     * @param $inComment
     * @return false|string
     */
    private static function skipComments(&$l, &$inComment)
    {
        $result = false;
        if ($l === '__END__') {
            $result = 'break';
        }
        $l = preg_replace('|(?<!:)//.*|' ,'', $l);
        if ($inComment) {
            if (str_contains($l, '*/')) {
                $l = preg_replace('|.*\*/|' ,'', $l);
                $inComment = false;
            } else {
                $result = 'continue';
            }
        } elseif (str_contains($l, '/*')) {
            if (str_contains($l, '*/')) {
                $l = preg_replace('|/\*.*\*/|' ,'', $l);
            } else {
                $l = preg_replace('|/\*.*|', '', $l);
                $inComment = true;
            }
        }
        if (!$l) {
            $result = 'continue';
        }
        return $result;
    } // skipComments


    /**
     * Removes empty rules (including those only containing comments) from given CSS-string.
     * @param string $css
     * @return string
     */
    private static function removeEmptyRules(string $css): string
    {
        $p1 = strpos($css, '}');
        while ($p1 !== false) {
            $p2 = strpos($css, '}', $p1+1);
            if ($p2 === false) {
                break;
            }
            $str = substr($css, $p1, ($p2 - $p1 + 1));
            $str = preg_replace('| /\* .* \*/ |xms', '', $str);
            if (preg_match('/\{ \s* }/xms', $str)) {
                $css = substr($css, 0, $p1).substr($css, $p2);
            }
            $p1 = strpos($css, '}', $p2);
        }
        return $css;
    } // removeEmptyRules

} // Scss
