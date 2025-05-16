<?php

namespace PgFactory\PageFactory;

class CompileJs
{
    private static array $translated = [];
    /**
     * Checks all js files in js/, compiles them and stores result in assets/js/-xy.js
     * 'compiling means: find variables like '{{ xy }}', replace them with a call to translateVar()
     *    at the same time assembles var definitions in the file head, such as
     *          var _pfyCancel = translateVar({"de":"Abbrechen","_":"Cancel"});
     * @return void
     * @throws \Exception
     */
    public static function compileAll(string $srcPath, $targPath): void
    {
        $srcPath = rtrim($srcPath, '*');
        $files = getDir($srcPath.'*.js');
        foreach ($files as $file) {
            self::$translated = [];
            $filename = basename($file);
            $out = "/* === Automatically created from $filename - do not modify! === */\n";
            $target = $targPath."-$filename";
            $tSrc = fileTime($file);
            $tTarg = fileTime($target);
            if (($tTarg >= $tSrc) && !PageFactory::$forceAssetsUpdate) {
                continue;
            }
            $out .= self::compile($file);
            writeFile($target, $out);
            mylog("JS: '$target' compiled");
        } // foreach file
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