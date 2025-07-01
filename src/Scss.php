<?php

namespace PgFactory\PageFactory;
use Kirby\Exception\InvalidArgumentException;
use ScssPhp\ScssPhp\Compiler;

class Scss
{
    private static object $scssphp;


    /**
     * Compiles SCSS (supplied in a string) and renders it as CSS.
     * @param string $scssStr
     * @return string
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public static function compileStr(string $scssStr, string $importPath = ''): string
    {
        if (!isset(self::$scssphp)) {
            self::$scssphp = new Compiler;
        }
        $scssStr = self::resolvePaths($scssStr);
        if ($importPath) {
            self::$scssphp->setImportPaths($importPath);
        }
        return self::$scssphp->compileString($scssStr)->getCss();
    } // compileStr


    /**
     * @param string $srcFile
     * @param string $targetPath
     * @return string|false
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public static function updateFile(string $srcFile, string $targetFile = ''): string|false
    {
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
     * @throws \Exception
     */
    public static function compileFileToString(string $srcFile): string
    {
        $srcStr = self::getFile($srcFile);
        $css = self::compileStr($srcStr, dirname($srcFile).'/' );
        $css = "/* === Automatically created from ".basename($srcFile)." - do not modify! === */\n\n$css";
        return $css;
    } // compileFileToString


    /**
     * @param string $srcFile
     * @param string $targetFile
     * @return void
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     */
    public static function compileFile(string $srcFile, string $targetFile): void
    {
        if (fileExt($srcFile) !== 'scss') { // skip any non-scss files
            return;
        }
        $css = self::compileFileToString($srcFile);
        writeFile($targetFile, $css);
    } // compileFile


    /**
     * Reads a file and injects comments containing line numbers, if requested by settings
     * @param string $file
     * @return string
     * @throws InvalidArgumentException
     */
    private static function getFile(string $file): string
    {
        $compileScssWithLineNumbers = kirby()->option('pgfactory.pagefactory.debug_compileScssWithSrcRef', false) &&
            (PageFactory::$dev || isAdminOrLocalhost());
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
            if ($p2 < strlen($css)) {
                $p1 = strpos($css, '}', $p2);
            } else {
                break;
            }
        }
        return $css;
    } // removeEmptyRules


    /**
     * @param string $html
     * @return string
     * @throws \Exception
     */
    private static function resolvePaths(string $html): string
    {
        // special case: url(~/ -> need to get url from pagefactory:
        if (preg_match_all('|url\((\'?)~/([^\s"\')]*)|', $html, $m)) {
            foreach ($m[1] as $i => $q) {
                $html = str_replace($m[0][$i], "url($q".PFY_APP_BASE_URL.$m[2][$i], $html);
            }
        }

        // special case: ~assets/ -> need to get url from Kirby:
        if (preg_match_all('|~assets/([^\s"\')]*)|', $html, $m)) {
            foreach ($m[1] as $i => $item) {
                $filename = 'assets/'.$m[1][$i];
                $file= site()->index()->files()->find($filename);
                if ($file) {
                    $path = $file->root();
                    $html = str_replace($m[0][$i], $path, $html);
                } else {
                    throw new \Exception("Error: unable to find asset '~$filename'");
                }
            }
        }
        $patterns = [
            '~/'        => PFY_KIRBY_BASE_PATH,
            '~data/'    => PFY_KIRBY_BASE_PATH.'site/custom/data/',
        ];
        $html = str_replace(array_keys($patterns), array_values($patterns), $html);
        return $html;
    } // resolvePaths

} // Scss
