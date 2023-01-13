<?php

namespace Usility\PageFactory;
use ScssPhp\ScssPhp\Compiler;
use Exception;

class Scss
{
    /**
     * @param $pfy
     */
    public function __construct()
    {
        $this->pages = PageFactory::$pages;
        $this->individualFiles = [];
        $this->scssphp = new Compiler;
    }


    /**
     * Compiles SCSS (supplied in a string) and renders it as CSS.
     * @param string $scssStr
     * @return string
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public function compileStr(string $scssStr): string
    {
        if (!$this->scssphp) {
            $this->scssphp = new Compiler;
        }
        $scssStr = $this->resolvePaths($scssStr);
        return $this->scssphp->compileString($scssStr)->getCss();
    } // compileStr


    /**
     * Compiles SCSS (from a file) and renders it as CSS.
     * @param string $srcFile
     * @param string $targetFile
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public function compileFile(string $srcFile, string $targetFile): void
    {
        if (fileExt($srcFile) !== 'scss') { // skip any non-scss files
            return;
        }
        $srcStr = $this->getFile($srcFile);
        $this->scssphp->setImportPaths(dir_name($srcFile));
        $css = $this->compileStr($srcStr);
        $css = "/* === Automatically created from ".basename($srcFile)." - do not modify! === */\n\n$css";
        file_put_contents($targetFile, $css);
    } // compileFile


    /**
     * converts path patterns starting with ~
     * @param $srcStr
     * @return array|string|string[]
     */
    private function resolvePaths($srcStr)
    {
        $appRoot = PageFactory::$appUrl;
        $pathPatterns = [
            '~/'            => $appRoot,
            '~assets/'       => $appRoot.'assets/',
        ];
        $srcStr = str_replace(array_keys($pathPatterns), array_values($pathPatterns), $srcStr);

        return $srcStr;
    } // $this->resolvePaths


    /**
     * Reads a file and injects comments cotaining line numbers, if requested by settings
     * @param string $file
     * @return string
     */
    private function getFile(string $file): string
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
                $cont = $this->skipComments($l, $inComment);
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
            $out = $this->removeEmptyRules($out);
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
    private function skipComments(&$l, &$inComment)
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
    private function removeEmptyRules(string $css): string
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
