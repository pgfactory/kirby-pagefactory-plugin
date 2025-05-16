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
     * @return void
     * @throws \Exception
     */
    public static function compileAll(string $srcPath, $targPath): void
    {
        $aggregatedTargetFile = preg_match('/\.(js|css)$/', $targPath) ? $targPath : '';
        $srcPath = rtrim($srcPath, '*');
        $files = getDir($srcPath.'*.js');
        $out = '';
        foreach ($files as $file) {
            self::$translated = [];
            $filename = basename($file);
            if ($aggregatedTargetFile) {
                $out .= "/* === Automatically created from $filename - do not modify! === */\n";
            } else {
                $out = "/* === Automatically created from $filename - do not modify! === */\n";
            }
            $target = $aggregatedTargetFile ?: $targPath."-$filename";
            $tSrc = fileTime($file);
            $tTarg = fileTime($target);
            if (($tTarg >= $tSrc) && !PageFactory::$forceAssetsUpdate) {
                continue;
            }
            $out .= self::compile($file);
            if (!$aggregatedTargetFile) {
                writeFile($target, $out);
            }
            mylog("JS: '$target' compiled");
        } // foreach file

        if ($aggregatedTargetFile) {
            writeFile($aggregatedTargetFile, $out);
        }
    } // compileAll


    public static function compile($file): string
    {
        $out = '';
        $jsStr = getFile($file, true);

        // handle "use strict" -> keep it at top of file:
        if (str_contains($jsStr,'use strict')) {
            $jsStr = preg_replace("/[\"']use strict[\"'];\n/", '', $jsStr);
            $out .= "\"use Strict\";\n\n";
        }

        // find all {{ xy }} and replace them with ${xy}, also add 'var xy = translateVar();' at top of file:
        $transVars = TransVars::$transVars;
        if (preg_match_all('/(\'?) \{\{ \s* (.*?) \s* }} (\'?)/xms', $jsStr, $m)) {
            foreach ($m[2] as $i => $key) {
                if (in_array($key, array_keys(self::$translated))) {
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
            $jsStr = $out.$jsStr;
        }
        return $jsStr;
    } // all

} // CompileJs