<?php

namespace PgFactory\PageFactory;

class CompileJs
{
    private static array $translated = [];

    /**
     * Checks and compiles all js files in $srcPath.
     * If $targPath is a file, compiles all js files into $targPath. Otherwise, compiles into
     * separate files in $targPath with leading '-' in filenames.
     *
     * Looks for all files in $srcPath that end with .js, e.g.
     * js/, compiles them and stores result in assets/js/-xy.js
     * 'compiling means: find variables like '{{ xy }}', replace them with a call to translateVar()
     *    at the same time assembles var definitions in the file head, such as
     *          var _pfyCancel = translateVar({"de":"Abbrechen","_":"Cancel"});
     */

    /**
     * @param string $srcPath
     * @param string $targPath
     * @return void
     * @throws \Exception
     */
    public static function compileAll(string $srcPath, string $targPath): void
    {
        $aggregatedTargetFile = preg_match('/\.(js|css)$/', $targPath) ? $targPath : '';
        // in case of $aggregatedTargetFile, check whether update is required:
        if ($aggregatedTargetFile) {
            self::compileAggregatedFile($srcPath, $aggregatedTargetFile);
            return;
        }

        $srcPath = rtrim($srcPath, '*');
        $files = getDir($srcPath.'*.js');
        foreach ($files as $file) {
            self::$translated = [];
            $filename = basename($file);
            $target = $targPath."-$filename";
            $tTarg = fileTime($target);
            $tSrc = fileTime($file);
            if (($tTarg >= $tSrc) && !PageFactory::$forceAssetsUpdate) {
                continue;
            }

            $out = "/* === Automatically created from $filename - do not modify! === */\n";
            $out .= self::compile($file);
            writeFile($target, $out);
            //mylog("JS: '$target' compiled");
        } // foreach file
    } // compileAll


    /**
     * @param string $srcPath
     * @param string $aggregatedTargetFile
     * @return void
     * @throws \Exception
     */
    public static function compileAggregatedFile(string $srcPath, string $aggregatedTargetFile): void
    {
        $srcPath = rtrim($srcPath, '*');
        $files = getDir($srcPath.'*.js');
        $tTarg = fileTime($aggregatedTargetFile);
        if ($tTarg && !PageFactory::$forceAssetsUpdate) {
            $update = false;
            foreach ($files as $file) {
                if (fileTime($file) > $tTarg) {
                    $update = true;
                    break;
                }
            }
            if (!$update) {
                return;
            }
        }

        $out = '';
        foreach ($files as $file) {
            self::$translated = [];
            $filename = basename($file);
            $out .= "/* === Automatically created from $filename - do not modify! === */\n";
            $out .= self::compile($file);
        } // foreach file

        writeFile($aggregatedTargetFile, $out);
    } // compileAggregatedFile


    /**
     * @param string $file
     * @return string
     * @throws \Exception
     */
    public static function compile(string $file): string
    {
        $out = '';
        $jsStr = getFile($file, true);

        // handle "use strict" -> keep it at top of file:
        if (str_contains($jsStr, 'use strict')) {
            $jsStr = preg_replace("/[\"']use strict[\"'];\n/", '', $jsStr);
            $out .= "\"use strict\";\n\n";
        }

        // find all {{ xy }} and replace them with ${xy}, also add 'const xy = translateVar();' at top of file:
        $transVars = TransVars::$transVars;
        if (preg_match_all('/(\'?) \{\{ \s* (.*?) \s* }} (\'?)/xms', $jsStr, $m)) {
            foreach ($m[2] as $i => $key) {
                if (isset(self::$translated[$key])) {
                    continue;
                }
                self::$translated[$key] = true;
                if (isset($transVars[$key])) {
                    $rec = $transVars[$key];
                    $val = json_encode($rec);
                    $varName = translateToIdentifier($key, true);
                    $out .= "const _$varName = translateVar($val);\n";
                    $jsStr = str_replace($m[0][$i], '${_'.$varName.'}', $jsStr);
                } else {
                    mylog("compileJs: missing key '$key'");
                }
            }
        }
        return $out . $jsStr;
    } // compile

} // CompileJs