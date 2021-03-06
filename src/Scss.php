<?php

namespace Usility\PageFactory;
use ScssPhp\ScssPhp\Compiler;
use Exception;

class Scss
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pages = $pfy->pages;
        $this->sourceDirs = $pfy->cssFiles;
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
        $srcStr = $this->getFile($srcFile);
        $this->scssphp->setImportPaths(dir_name($srcFile));
        $scss = $this->compileStr($srcStr);
        file_put_contents($targetFile, $scss);
    } // compileFile



    /**
     * Reads a file and injects comments cotaining line numbers, if requested by settings
     * @param string $file
     * @return string
     */
    private function getFile(string $file): string
    {
        $compileScssWithLineNumbers = @$this->pfy->config['debug_compileScssWithLineNumbers'];
        if ($compileScssWithLineNumbers !== 'false') {
            $out = loadFile($file);
            $fname = basename($file);
            $lines = explode(PHP_EOL, $out);
            $out = '';
            foreach ($lines as $i => $l) {
                $l = preg_replace('|^ (.*?) (?<!:)// .*|x', "$1", $l);
                if (preg_match('|^ [^/*]+ {|x', $l)) {  // add line-number in comment
                    $l .= " [[* content: '$fname:".($i+1)."'; *]]";
                }
                if ($l) {
                    $out .= $l . "\n";
                }
            }

            $p1 = strpos($out, '/*');
            while ($p1 !== false) {
                $p2 = strpos($out, '*/');
                if (($p2 !== false) && ($p1 < $p2)) {
                    $out = substr($out, 0, $p1) . substr($out, $p2 + 2);
                }
                $p1 = strpos($out, '/*', $p1 + 1);
            }
            $out = str_replace(['[[*', '*]]'], ['/*', '*/'], $out);
        } else {
            $out = loadFile($file, true);
        }
        return $out . "\n";
    } // getFile

} // Scss
