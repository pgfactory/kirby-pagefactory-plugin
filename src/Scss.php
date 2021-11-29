<?php

namespace Usility\PageFactory;
use ScssPhp\ScssPhp\Compiler;

class Scss
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pages = $pfy->pages;
        $this->sourceDirs = $pfy->cssFiles;
        $this->scssphp = new Compiler;
    }


    public function update($forceUpdate = false)
    {
        $this->forceUpdate = $forceUpdate;
        $targetCssPath = PFY_USER_ASSETS_PATH;
        $namesOfCompiledFiles = '';

        $modified = false;
        $out = "\n";
        foreach ($this->sourceDirs as $targetName => $files) {
            $files = resolvePath($files);

            // expand wildecards in file-designators:
            $i = 0;
            while (isset($files[$i])) {
                $file = $files[$i];
                $dir = true;
                if ($file[strlen($file) - 1] === '*') {
                    $dir = getDir($file);
                    array_splice($files, $i, 1, $dir);
                }
                if ($dir) {
                    $i++;
                }
            }

            if (!$files) {
                continue;
            }

            $targetBasename = base_name($targetName, false);
            $targetFiles = getDir("{$targetCssPath}$targetBasename.*");
            if ($targetFiles) {
                $tTarget = lastModified($targetFiles, false);
                $tSrc = lastModified($files, false);
            } else {
                $tTarget = 0;
                $tSrc = 1;
            }

            // check whether update is required:
            if ($tTarget < $tSrc) {
                $this->aggregatedCss = '';
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        if (fileExt($file) === 'scss') {
                            $namesOfCompiledFiles .= $this->compile($file);
                        } else {
                            $cssStr = file_get_contents($file);
                            $cssStr = "/**** copied from '$file' ****/\n\n$cssStr";
                            $this->aggregatedCss .= $cssStr . "\n\n\n";
                        }
                    } else {
                        die("Error in Scss.php: file '$file' not found.");
                    }
                }
                $aggregatedCss = "/*** Automatically created -- do not modify! ****/\n\n$this->aggregatedCss";

                preparePath($targetCssPath);
                file_put_contents("{$targetCssPath}$targetName", $aggregatedCss);
                $modified = true;
            }

            $cssFiles = $this->pages->files();
            $cssFilePath = PFY_CSS_PATH . $targetName;
            $cssFile = $cssFiles->find($cssFilePath);
            if ($cssFile) {
                $link = css($cssFile);
                if (strpos($targetName, 'async') !== false) {
                    $out .= "\t<noscript>$link</noscript>\n";
                    $link = substr($link,0, -1) . ' class="lzy-async-load" media="print">';
                }
                $out .= "\t$link\n";
            }
        }
        if ($modified) {
            // if CSS files were updated we need to force the agent to reload,
            // otherwise, kirby misses changes in the filesystem
            reloadAgent();
        }
        return $out;
    } // update



    private function compile($file)
    {
        $scssStr = $this->getFile($file);
        $this->scssphp->setImportPaths(dir_name($file));
        $cssStr = $this->compileStr($scssStr);
        $cssStr = "/**** compiled from '$file'****/\n$cssStr";

        $this->aggregatedCss .= $cssStr . "\n\n\n";
        return basename($file).", ";
    } // compile



    public function compileStr($scssStr)
    {
        if (!$this->scssphp) {
            $this->scssphp = new Compiler;
        }
        return $this->scssphp->compileString($scssStr)->getCss();
    } // compileStr



    private function getFile($file)
    {
        $compileScssWithLineNumbers = site()->debug_compilescsswithlinenumbers()->value();
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
