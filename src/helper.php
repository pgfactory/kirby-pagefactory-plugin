<?php

namespace Usility\PageFactory;

 // Use helper functions with prefix '\Usility\PageFactory\'
use \Kirby\Data\Yaml as Yaml;
use \Kirby\Data\Json as Json;
use Kirby\Exception\Exception;

define('TRANSVAR_ARG_QUOTES', 	'!@#$%&:?');    // Special quotes to enclose transvar args: e.g. %% xy %%

function isLocalhost()
{
    $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
    return (($remoteAddress == 'localhost') || (strpos($remoteAddress, '192.') === 0) || ($remoteAddress == '::1'));
} // isLocalhost



function loadFile($file, $removeComments = true, $useCaching = false)
{
    if (!$file || !is_string($file)) {
        return '';
    }

    $file = resolvePath($file);

    if ($useCaching) {
        $data = checkDataCache($file);
        if ($data !== null) {
            return $data;
        }
    }

    $data = @file_get_contents($file);
    if (!$data) {
        return '';
    }
    if ($removeComments) {
        $data = zapFileEND($data);
    }
    if (is_string($removeComments)) {
        if (stripos($removeComments, 'cstyle') !== false) {
            $data = removeCStyleComments($data);
        }
        if (stripos($removeComments, 'hash') !== false) {
            $data = removeHashTypeComments($data);
        }
        if (stripos($removeComments, 'empty') !== false) {
            $data = removeEmptyLines($data);
        }
    }

    // if it's data of a known format (i.e. yaml,json etc), decode it:
    $ext = fileExt($file);
    if (strpos(',yaml,yml,json,csv', $ext) !== false) {
        $data = Yaml::decode($data, $ext);
        if ($useCaching) {
            updateDataCache($file, $data);
        }
    }
    return $data;
} // loadFile



function loadFiles($files, $removeComments = true, $useCaching = false) {
    // Note: files of type string and structured data must not be mixed (first file wins).
    if (!$files || !is_array($files)) {
        return null;
    }

    if ($useCaching) {
        if (($data = checkDataCache($files)) !== null) {
            return $data;
        }
    }

    $file1 = @$files[0];
    $ext = fileExt($file1);
    if (strpos(',yaml,yml,json,csv', $ext) !== false) {
        $data = [];
        foreach ($files as $f) {
            if ($newData = loadFile($f, $removeComments, false)) {
                $data = array_merge($data, $newData);
            }
        }
        if ($useCaching) {
            updateDataCache($file1, $data, '.0');
        }

    } else {
        $data = '';
        foreach ($files as $f) {
            $data .= loadFile($f, $removeComments, false);
        }
    }
    return $data;
} // loadFiles



function checkDataCache($file)
{
    if (is_array($file)) {
        $file1 = @$file[0];
        $cacheFile = cacheFileName($file1, '.0');
        if (!file_exists($cacheFile)) {
            return null;
        }
        $tCache = filemtime($cacheFile);
        $tFiles = 0;
        foreach ($file as $f) {
            $tFiles = max($tFiles, @filemtime($f));
        }
        if ($tFiles < $tCache) {
            $raw = file_get_contents($cacheFile);
            return unserialize($raw);
        }

    } else {
        $cacheFile = cacheFileName($file);
        if (file_exists($cacheFile)) {
            $tFile = @filemtime($file);
            $tCache = @filemtime($cacheFile);
            if ($tFile < $tCache) {
                $raw = file_get_contents($cacheFile);
                return unserialize($raw);
            }
        }
    }
    return null;
} // checkDataCache



function updateDataCache($file, $data, $tag = '')
{
    $raw = serialize($data);
    $cacheFile = cacheFileName($file, $tag);
    preparePath($cacheFile);
    file_put_contents($cacheFile, $raw);
} // updateDataCache



function cacheFileName($file, $tag = '')
{
    $cacheFile = localPath($file);
    $cacheFile = str_replace('/', '_', $cacheFile);
    return PFY_CACHE_PATH . $cacheFile . $tag .'.cache';
} // cacheFileName



function fileExt($file0, $reverse = false)
{
    $file = basename($file0);
    $file = preg_replace(['|^\w{1,6}://|', '/[#?&:].*/'], '', $file);
    if ($reverse) {
        $path = dirname($file0) . '/';
        if ($path === './') {
            $path = '';
        }
        $file = pathinfo($file, PATHINFO_FILENAME);
        return $path . $file;

    } else {
        return pathinfo($file, PATHINFO_EXTENSION);
    }
} // fileExt



function base_name($file, $incl_ext = true, $incl_args = false)
{
    if (!$incl_args && ($pos = strpos($file, '?'))) {
        $file = substr($file, 0, $pos);
    }
    if (preg_match('/&#\d+;/', $file)) {
        $file = htmlspecialchars_decode($file);
        $file = preg_replace('/&#\d+;/', '', $file);
    }
    if (!$incl_args && ($pos = strpos($file, '#'))) {
        $file = substr($file, 0, $pos);
    }
    if (substr($file, -1) === '/') {
        return '';
    }
    $file = basename($file);
    if (!$incl_ext) {
        $file = preg_replace("/(\.\w*)$/U", '', $file);
    }
    return $file;
} // baseName



function localPath($absPath)
{
    if (@$absPath[0] === '/') {
        return substr($absPath, strlen(PageFactory::$absAppRoot));
    } else {
        return $absPath;
    }
}


function dir_name($path)
{
    // last element considered a filename, if doesn't end in '/' and contains a dot
    if (!$path) {
        return '';
    }

    if ($path[strlen($path) - 1] === '/') {  // ends in '/'
        return $path;
    }
    $path = preg_replace('/[#?*].*/', '', $path);
    if (strpos(basename($path), '.') !== false) {  // if it contains a '.' we assume it's a file
        return dirname($path) . '/';
    } else {
        return rtrim($path, '/') . '/';
    }
} // dir_name



function fixPath($path)
{
    if ($path) {
        $path = rtrim($path, '/').'/';
    }
    return $path;
} // fixPath



function zapFileEND($str)
{
    $p = strpos($str, "__END__");
    if ($p === false) {
        return $str;
    }

    if ($p === 0) {     // on first line?
        return '';
    } else {
        while ($str[$p - 1] !== "\n") {
            $p = strpos($str, "__END__", $p+7);
            if (!$p) {
                return $str;
            }
        }
        $str = substr($str, 0, $p);
    }
    return $str;
} // zapFileEND



function removeEmptyLines($str)
{
    $lines = explode(PHP_EOL, $str);
    foreach ($lines as $i => $l) {
        if (!$l) {
            unset($lines[$i]);
        }
    }
    return implode("\n", $lines);
} // removeEmptyLines



function getHashCommentedHeader($fileName)
{
    $str = loadFile($fileName);
    if (!$str) {
        return '';
    }
    $lines = explode(PHP_EOL, $str);
    $out = '';
    foreach ($lines as $i => $l) {
        if (isset($l[0])) {
            $c1 = $l[0];
            if (($c1 !== '#') && ($c1 !== ' ')) {
                break;
            }
        }
        $out .= "$l\n";
    }
    return $out;
} // getHashCommentedHeader



function removeHashTypeComments($str)
{
    if (!$str) {
        return '';
    }
    $lines = explode(PHP_EOL, $str);
    $lead = true;
    foreach ($lines as $i => $l) {
        if (isset($l[0]) && ($l[0] === '#')) {  // # at beginning of line
            unset($lines[$i]);
        } elseif ($lead && !$l) {   // empty line while no data line encountered
            unset($lines[$i]);
        } else {
            $lead = false;
        }
    }
    return implode("\n", $lines);
} // removeHashTypeComments



function removeCStyleComments($str)
{
    $p = 0;
    while (($p = strpos($str, '/*', $p)) !== false) {        // /* */ style comments

        $ch_1 = $p ? $str[$p - 1] : "\n"; // char preceding '/*' must be whitespace
        if (strpbrk(" \n\t", $ch_1) === false) {
            $p += 2;
            continue;
        }
        $p2 = strpos($str, "*/", $p);
        $str = substr($str, 0, $p) . substr($str, $p2 + 2);
    }

    $p = 0;
    while (($p = strpos($str, '//', $p)) !== false) {        // // style comments

        if ($p && ($str[$p - 1] === ':')) {            // avoid http://
            $p += 2;
            continue;
        }

        if ($p && ($str[$p - 1] === '\\')) {                    // avoid shielded //
            $str = substr($str, 0, $p - 1) . substr($str, $p);
            $p += 2;
            continue;
        }
        $p2 = strpos($str, "\n", $p);
        if ($p2 === false) {
            return substr($str, 0, $p);
        }

        if ((!$p || ($str[$p - 1] === "\n")) && ($str[$p2])) {
            $p2++;
        }
        $str = substr($str, 0, $p) . substr($str, $p2);
    }
    return $str;
} // removeCStyleComments



function getDir($pat)
{
    if (strpos($pat, '{') === false) {
        $files = glob($pat);
    } else {
        $files = glob($pat, GLOB_BRACE);
    }
    if (!$files) {
        return [];
    }
    $files = array_filter($files, function ($str) {
        return ($str && ($str[0] !== '#') && (strpos($str, '/#') === false));
    });
    foreach ($files as $i => $item) {
        if (is_dir($item)) {
            $files[$i] = $item.'/';
        }
    }
    return $files;
} // getDir



function getDirDeep($path, $onlyDir = false, $assoc = false, $returnAll = false)
{
    $files = [];
    $f = basename($path);
    $pattern = '*';
    if (strpos($f, '*') !== false) {
        $pattern = basename($path);
        $path = dirname($path);
    }
    $patt = '|/[_#]|';
    $patt2 = '|/[._#]|';
    if ($returnAll) {
        $patt = '|/#|';
        $patt2 = '|/[.#]|';
    }
    $it = new RecursiveDirectoryIterator($path);
    foreach (new RecursiveIteratorIterator($it) as $fileRec) {
        $f = $fileRec->getFilename();
        $p = $fileRec->getPathname();
        if ($onlyDir) {
            if (($f === '.') && !preg_match($patt, $p)) {
                $files[] = rtrim($p, '.');
            }
            continue;
        }
        if (!$returnAll && (preg_match($patt2, $p) || !fnmatch($pattern, $f))) {
            continue;
        }
        if ($assoc) {
            $files[$f] = $p;
        } else {
            $files[] = $p;
        }
    }
    return $files;
} // getDirDeep



function lastModified($paths, $recursive = true, $exclude = null)
{
    $newest = 0;
    $paths = resolvePath($paths);

    if (is_string($paths)) {
        $paths = [$paths];
    }
    if ($recursive) {
        foreach ($paths as $path) {
            $path = './' . rtrim($path, '*');
            $it = new RecursiveDirectoryIterator($path);
            foreach (new RecursiveIteratorIterator($it) as $fileRec) {
                // ignore files starting with . or # or _
                if (preg_match('|^[._#]|', $fileRec->getFilename())) {
                    continue;
                }
                $newest = max($newest, $fileRec->getMTime());
            }
        }
    } else {
        foreach ($paths as $path) {
            $files = glob($path);
            foreach ($files as $file) {
                $newest = max($newest, filemtime($file));
            }
        }
    }

    return $newest;
} // filesTime



function deleteFiles($files)
{
    if (is_string($files)) {
        @unlink($files);
    } else {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}


function resolvePath($path)
{
    if (@$path[0] !== '~') {
        return $path;
    }
    
    // resolve PFY's specific folders:
    $absAppRoot = PageFactory::$absAppRoot;
    $pathPatterns = [
        '~/' =>             $absAppRoot,
        '~page/' =>         null,
        '~pagefactory/' =>  $absAppRoot . 'site/plugins/pagefactory/',
        '~assets/' =>       $absAppRoot . PFY_USER_ASSETS_PATH,
        '~data/' =>         $absAppRoot . 'site/data/',
    ];

    $patterns['~page/'] = page()->root().'/';
    $path = str_replace(array_keys($patterns), array_values($patterns), $path);
    
    // resolve Kirby's roots:
    if (preg_match('|^~(\w+)/|', $path, $m)) {
        $key = $m[1];
        $s = kirby()->roots()->$key();
        if ($s) {
            $path = str_replace($m[0], "$s/", $path);
        }
    }
    return $path;
} // resolvePath



function resolvePaths($paths)
{
    if (is_string($paths)) {
        $paths = resolvePath($paths);

    } elseif (is_array($paths)) {
        foreach ($paths as $i => $path) {
            $paths[$i] = resolvePath($path);
        }
    }
    return $paths;
} // resolvePaths



function resolveLinks($html)
{
    if (strpos($html, '~/') !== false) {
        $appUrl = PageFactory::$appRoot;
        $patterns = [
            '~/' =>             $appUrl,
            '~page/' =>         page()->url().'/',
            '~pagefactory/' =>  $appUrl . 'site/plugins/pagefactory/',
            '~assets/' =>       $appUrl . PFY_USER_ASSETS_PATH,
        ];
        $html= str_replace(array_keys($patterns), array_values($patterns), $html);
    }
    return $html;
} // resolveLinks



function getGitTag($shortForm = true)
{
    $str = shell_exec('cd site/plugins/pagefactory/; git describe --tags --abbrev=0; git log --pretty="%ci" -n1 HEAD');
    if ($shortForm) {
        return preg_replace("/\n.*/", '', $str);
    } else {
        return str_replace("\n", ' ', $str);
    }
} // getGitTag



function writeFile($file, $content, $args = 0)
{
    $file = resolvePath($file);
    preparePath($file);
    file_put_contents($file, $content, $args);
} // writeFile



function preparePath($path0, $accessRights = false)
{
    if ($path0 && ($path0[0] === '~')) {
        $path0 = resolvePath($path0);
    }

    // check for inappropriate path:
    if (strpos($path0, '../') !== false) {
        $path0 = normalizePath($path0);
        if (strpos($path0, '../') !== false) {
            mylog("=== Warning: preparePath() trying to access inappropriate location: '$path0'");
            return;
        }
    }

    $path = dirname($path0.'x');
    if (!file_exists($path)) {
        $accessRights1 = $accessRights ? $accessRights : PFY_MKDIR_MASK;
        try {
            mkdir($path, $accessRights1, true);
        } catch (Exception $e) {
            fatalError("Error: failed to create folder '$path'", 'File: '.__FILE__.' Line: '.__LINE__);
        }
    }

    if ($accessRights) {
        $path1 = '';
        foreach (explode('/', $path) as $p) {
            $path1 .= "$p/";
            chmod($path1, $accessRights);
        }
    }

    if ($path0 && !file_exists($path0)) {
        touch($path0);
    }
} // preparePath



function mylog($str)
{
    $logFile = PFY_LOGS_PATH. 'log.txt';
    $logMaxWidth = 80;

    if ((strlen($str) > $logMaxWidth) || (strpos($str, "\n") !== false)) {
        $str = wordwrap($str, $logMaxWidth, "\n", false);
        $str1 = '';
        foreach (explode("\n", $str) as $i => $l) {
            if ($i > 0) {
                $str1 .= '                     ';
            }
            $str1 .= "$l\n";
        }
        $str = $str1;
    }
    $str = timestampStr()."  $str\n\n";
    writeFile($logFile, $str, FILE_APPEND);
} // mylog



function timestampStr($short = false)
{
    if (!$short) {
        return date('Y-m-d H:i:s');
    } else {
        return date('Y-m-d');
    }
} // timestampStr



function indentLines($str, $width = 4)
{
    $str1 = '';
    $indent = str_pad('', $width, ' ');
    foreach (explode("\n", $str) as $l) {
        $str1 .= "$indent$l\n";
    }
    return rtrim($str1, "\n");
} // indentLines



function parseArgumentStr($str, $delim = ',')
{
    // get indent of first indented element:
    $indent = '';
    if (preg_match('/\n(\s*)/m', $str, $m)) {
        $indent = $m[1];
    }
    // terminate if string empty:
    if (!($str = trim($str))) {
        return [];
    }

    // skip '{{ ... }}' to avoid conflict with '{ ... }':
    if (preg_match('/^\s* {{ .* }} \s* $/x', $str)) {
        return [ $str ];
    }

    // if string starts with { we assume it's json:
    if ($str[0] === '{') {
        return Json::decode($str);
    }

    // otherwise, interpret as 'relaxed Yaml':
    // (meaning: first elements may come without key, then they are interpreted by position)
    $rest = ltrim($str, ", \n");
    $out = '';
    while ($rest) {
        $key = parseArgKey($rest, $delim);
        $ch = ltrim($rest);
        $ch = @$ch[0];
        if ($ch !== ':') {
            $out .= "- $key\n";
            $rest = ltrim($rest, " $delim\n");
        } else {
            $rest = substr($rest, 1);
            $value = parseArgValue($rest, $delim);
            if (trim($value)) {
                $out .= "$key: $value\n";
            } else {
                $out .= "$key:\n";
            }
        }
    }

    $pattern = "/^$indent/";
    $yaml = '';
    if ($indent) {
        foreach (explode("\n", $out) as $line) {
            $yaml .= preg_replace($pattern, '', rtrim($line, ', ')) . "\n";
        }
    } else {
        $yaml = $out;
    }

    try {
        $options = Yaml::decode($yaml);
    } catch (Exception $e) {
        die($e);
    }

    return $options;
} // parseArgumentStr

function parseArgKey(&$rest, $delim)
{
    $lead = '';
    if (preg_match('/^(\s*)(.*)/', $rest, $m)) {
        $lead = $m[1];
        $rest = substr($rest, strlen($lead));
    }
    // case quoted key or value:
    if ((($ch1 = $rest[0]) === '"') || ($ch1 === "'")) {
        $pattern = "$ch1 (.*?) $ch1";
        // case 'value' without key:
        if (preg_match("/^ ($pattern) (.*)/xms", $rest, $m)) {
            $key = $m[2];
            $rest = $m[3];
        }

    // case naked key or value:
    } else {
        // case value without key:
        $pattern = "[^$delim\n:]+";
        if (preg_match("/^ ($pattern) (.*) /xms", $rest, $m)) {
            $key = $m[1];
            $rest = $m[2];
        }
    }
    return "$lead'$key'";
} // parseArgKey

function parseArgValue(&$rest, $delim)
{
    // case quoted key or value:
    $ch1 = ltrim($rest);
    $ch1 = @$ch1[0];
    if (($ch1 === '"') || ($ch1 === "'")) {
        $pattern = "$ch1 (.*?) $ch1";
        // case 'value' without key:
        if (preg_match("/^ ($pattern) (.*)/xms", $rest, $m)) {
            $value = $m[1];
            $rest = $m[3];
        }

        // case naked key or value:
    } else {
        // case value without key:
        $pattern = "[^$delim\n]+";
        if (preg_match("/^ ($pattern) (.*) /xms", $rest, $m)) {
            $value = $m[1];
            $rest = $m[2];
        }
    }
    $pattern = "^[$delim\n]+";
    $rest = preg_replace("/$pattern/", '', $rest);
    return $value;
} // parseArgValue



function findNextPattern($str, $pat, $p1 = 0)
{
    while (($p1 = strpos($str, $pat, $p1)) !== false) {
        if (($p1 === 0) || (substr($str, $p1 - 1, 1) !== '\\')) {
            break;
        }
        $p1 += strlen($pat);
    }
    return $p1;
} // findNextPattern



function parseInlineBlockArguments($str)
{
    /*
     *  patterns:
     *      <x  = html tag
     *      #x  = id
     *      .x  = class
     *      x:y = style
     *      x=y = html attribute, e.g. aria-live=polite
     *      !x  = meta command, e.g. !off or !lang=en
     *      'x  = text
     *      "x  = text
     *      x   = text
     *
     * test string:
     *  $str = "<div \"u v w\" #id1 .cls1 !lang=de !showtill:2021-11-17T10:18 color:red; !literal !off lorem ipsum aria-live=\"polite\" .cls.cls2 'dolor dada' data-tmp='x y'";
     */
    $tag = $id = $class = $style = $text = $lang = '';
    $literal = $mdCompile = null;
    $attr = [];

    // catch quoted elements:
    if (preg_match_all('/(?<!=) (["\']) (.*?) \1/x', $str, $m)) {
        foreach ($m[2] as $i => $t) {
            $text = $text? "$text $t": $t;
            $str = str_replace($m[0][$i], '', $str);
        }
    }

    // catch attributes with quoted args:
    if (preg_match_all('/([=!\w-]+) = \' (.+?)  \'/x', $str, $m)) {
        foreach ($m[2] as $i => $t) {
            $ch1 = $m[1][$i][0];
            if (($ch1 === '!') || ($ch1 === '=')){
                continue;
            }
            $attr[ $m[1][$i] ] = $t;
            $str = str_replace($m[0][$i], '', $str);
        }
    }
    if (preg_match_all('/([=!\w-]+) = " (.+?)  "/x', $str, $m)) {
        foreach ($m[2] as $i => $t) {
            $ch1 = $m[1][$i][0];
            if (($ch1 === '!') || ($ch1 === '=')){
                continue;
            }
            $attr[ $m[1][$i] ] = $t;
            $str = str_replace($m[0][$i], '', $str);
        }
    }
    if (preg_match_all('/([=!\w-]+) = (\S+) /x', $str, $m)) {
        foreach ($m[2] as $i => $t) {
            $ch1 = $m[1][$i][0];
            if (($ch1 === '!') || ($ch1 === '=')){
                continue;
            }
            $attr[ $m[1][$i] ] = $t;
            $str = str_replace($m[0][$i], '', $str);
        }
    }

    if (preg_match_all('/([=!\w-]+) : ([^\s;]+) ;?/x', $str, $m)) {
        foreach ($m[2] as $i => $t) {
            $ch1 = $m[1][$i][0];
            if (($ch1 === '!') || ($ch1 === '=')){
                continue;
            }
            $style ="$style{$m[1][$i]}:$t;";
            $str = str_replace($m[0][$i], '', $str);
        }
    }

    // catch rest:
    $str = str_replace(['#','.'],[' #',' .'], $str);
    $args = explodeTrim(' ', $str, true);
    foreach ($args as $arg) {
        $c1 = $arg[0];
        $arg1 = substr($arg,1);
        switch ($c1) {
            case '<':
                $tag = $arg1;
                break;
            case '#':
                $id = $arg1;
                break;
            case '.':
                $arg1 = str_replace('.', ' ', $arg1);
                $class = $class? "$class $arg1" : $arg1;
                break;
            case '!':
                _parseMetaCmds($arg1, $lang, $literal, $mdCompile, $style, $tag);
                break;
            case '"':
                $t = rtrim($arg1, '"');
                $text = $text ? "$text $t" : $t;
                break;
            case "'":
                $t = rtrim($arg1, "'");
                $text = $text ? "$text $t" : $t;
                break;
        }
    }
    $style = trim($style);
    list($htmlAttrs, $htmlAttrArray) = _assembleHtmlAttrs($id, $class, $style, $attr);

    return [
        'tag' => $tag,
        'id' => $id,
        'class' => $class,
        'style' => $style,
        'attr' => $attr,
        'text' => $text,
        'literal' => $literal,
        'mdCompile' => $mdCompile,
        'lang' => $lang,
        'htmlAttrs' => $htmlAttrs,
        'htmlAttrArray' => $htmlAttrArray,
    ];

} // parseInlineBlockArguments


function _parseMetaCmds($arg, &$lang, &$literal, &$mdCompile, &$style, &$tag)
{
    if (preg_match('/^([\w-]+) [=:]? (.*) /x', $arg, $m)) {
        $arg = strtolower($m[1]);
        $param = $m[2];
        if ($arg === 'literal') {
            $literal = true;
        } elseif ($arg === 'mdCompile') {
            $mdCompile = true;
        } elseif ($arg === 'lang') {
            $lang = $param;
        } elseif (($arg === 'off') || (($arg === 'visible') && ($param !== 'true')))  {
            $style = $style? " $style display:none;" : 'display:none;';
        } elseif ($arg === 'showtill') {
            $t = strtotime($param) - time();
            if ($t < 0) {
                $lang = 'none';
                $tag = 'skip';
            }
        } elseif ($arg === 'showfrom') {
            $t = strtotime($param) - time();
            if ($t > 0) {
                $lang = 'none';
                $tag = 'skip';
            }
        }
    }
} // _parseMetaCmds



function _assembleHtmlAttrs($id, $class, $style, $attr) {
    $out = '';
    $htmlAttrArray = [];
    if ($id) {
        $out .= " id='$id'";
        $htmlAttrArray['id'] = $id;
    }
    if ($class) {
        $out .= " class='$class'";
        $htmlAttrArray['class'] = $class;
    }
    if ($style) {
        $out .= " style='$style'";
        $htmlAttrArray['style'] = $style;
    }
    if ($attr) {
        foreach ($attr as $k => $v) {
            $out .= " $k='$v'";
        }
        $htmlAttrArray = array_merge($htmlAttrArray, $attr);
    }
    return [$out, $htmlAttrArray];
} // _assembleHtmlAttrs



function explodeTrim($sep, $str, $excludeEmptyElems = false)
{
    if (!is_string($str)) {
        return [];
    }
    $str = trim($str);
    if ($str === '') {
        return [];
    }
    if (strlen($sep) > 1) {
        if ($sep[0]  === '/') {
            if (($m = preg_split($sep, $str)) !== false) {
                return $m;
            }
        } elseif (!preg_match("/[$sep]/", $str)) {
            return [ $str ];
        }
        $sep = preg_quote($sep);
        $out = array_map('trim', preg_split("/[$sep]/", $str));

    } else {
        if (strpos($str, $sep) === false) {
            return [ $str ];
        }
        $out = array_map('trim', explode($sep, $str));
    }

    if ($excludeEmptyElems) {
        $out = array_filter($out, function ($item) {
            return ($item !== '');
        });
    }
    return $out;
} // explodeTrim



function compileMarkdown($mdStr)
{
    return kirby()->markdown($mdStr);
} // compileMarkdown



function shieldStr($str, $type = 'raw')
{
    if ($type === 'md') {
        return '{md{' . base64_encode($str) . '}md}';
    } else {
        return '{raw{' . base64_encode($str) . '}raw}';
    }
} // shieldStr



function unshieldStr(&$str)
{
    if (preg_match_all('/{raw{(.*?)}raw}/m', $str, $m)) {
        foreach ($m[1] as $i => $item) {
            $literal = base64_decode($m[1][$i]);
            $str = str_replace($m[0][$i], $literal, $str);
        }
    }
    if (preg_match_all('/{md{(.*?)}md}/m', $str, $m)) {
        foreach ($m[1] as $i => $md) {
            $md = base64_decode($md);
            $html = kirby()->markdown($md);
            $str = str_replace($m[0][$i], "\n\n$html\n\n", $str);
        }
        return true;
    }
    return false;
} // unshieldStr



function strToASCII($str)
{
    // transliterate special characters (such as ä, ö, ü) into pure ASCII
    $specChars = array('ä','ö','ü','Ä','Ö','Ü','é','â','á','à',
        'ç','ñ','Ñ','Ç','É','Â','Á','À','ẞ','ß','ø','å');
    $specCodes2 = array('ae','oe','ue','Ae',
        'Oe','Ue','e','a','a','a','c',
        'n','N','C','E','A','A','A',
        'SS','ss','o','a');
    return str_replace($specChars, $specCodes2, $str);
} // strToASCII




function translateToFilename($str, $appendExt = true)
{
    // translates special characters (such as , , ) into "filename-safe" non-special equivalents (a, o, U)
    $str = strToASCII(trim(mb_strtolower($str)));	// replace special chars
    $str = strip_tags($str);						// strip any html tags
    $str = str_replace([' ', '-'], '_', $str);				// replace blanks with _
    $str = str_replace('/', '_', $str);				// replace '/' with _
    $str = preg_replace("/[^[:alnum:]._-`]/m", '', $str);	// remove any non-printables
    $str = preg_replace("/\.+/", '.', $str);		// reduce multiple ... to one .
    if ($appendExt && !preg_match('/\.html?$/', $str)) {	// append file extension '.html'
        if ($appendExt === true) {
            $str .= '.html';
        } else {
            $str .= '.'.$appendExt;
        }
    }
    return $str;

} // translateToFilename




function translateToIdentifier($str, $removeDashes = false, $removeNonAlpha = false, $toLowerCase = true)
{
    // translates special characters (such as , , ) into identifier which contains but safe characters:
    if ($toLowerCase) {
        $str = mb_strtolower($str);        // all lowercase
    }
    $str = strToASCII($str);		// replace umlaute etc.
    $str = strip_tags($str);							// strip any html tags
    if ($removeNonAlpha) {
        $str = preg_replace('/[^a-zA-Z-_\s]/ms', '', $str);

    } elseif (preg_match('/^ \W* (\w .*?) \W* $/x', $str, $m)) { // cut leading/trailing non-chars;
        $str = trim($m[1]);
    }
    $str = preg_replace('/\s+/', '_', $str);			// replace blanks with _
    $str = preg_replace("/[^[:alnum:]_-]/m", '', $str);	// remove any non-letters, except _ and -
    if ($removeDashes) {
        $str = str_replace("-", '_', $str);				// remove -, if requested
    }
    return $str;
} // translateToIdentifier




function translateToClassName($str)
{
    $str = strip_tags($str);					// strip any html tags
    $str = strToASCII(mb_strtolower($str));		// replace special chars
    $str = preg_replace(['|[./]|', '/\s+/'], '-', $str);		// replace blanks, '.' and '/' with '-'
    $str = preg_replace("/[^[:alnum:]_-]/m", '', $str);	// remove any non-letters, except '_' and '-'
    if (!preg_match('/[a-z]/', @$str[0])) { // prepend '_' if first char non-alpha
        $str = "_$str";
    }
    return $str;
} // translateToClassName



function var_r($var, $varName = '', $flat = false)
{
    if ($flat) {
        $out = preg_replace("/" . PHP_EOL . "/", '', var_export($var, true));
        if (preg_match('/array \((.*),\)/', $out, $m)) {
            $out = "[{$m[1]} ]";
        }
        if ($varName) {
            $out = "$varName: $out";
        }
    } else {
        $out = "<div><pre>$varName: " . var_export($var, true) . "\n</pre></div>\n";
    }
    return $out;
} // var_r



function reloadAgent($target = false, $getArg = false)
{
    if (!$target) {
        $target = page()->url();
    }
    header("Location: $target");
    exit;
} // reloadAgent


function fatalError($msg, $origin = '', $offendingFile = '')
{
    die($msg);
} // fatalError
