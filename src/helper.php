<?php

namespace Usility\PageFactory;

 // Use helper functions with prefix '\Usility\PageFactory\'
use \Kirby\Data\Yaml as Yaml;
use \Kirby\Data\Json as Json;
use Exception;


 /**
  * Checks whether agent is in the same subnet as IP 192.x.x.x
  * @return bool
  */
 function isLocalhost(): bool
{
    $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
    return (($remoteAddress == 'localhost') || (strpos($remoteAddress, '192.') === 0) || ($remoteAddress == '::1'));
} // isLocalhost


 /**
  * Checks whether visitor is logged in.
  * @return bool
  */
 function isLoggedIn(): bool
{
    return (kirby()->user() !== null);
} // isLoggedin


 /**
  * Checks whether visitor is logged with role=admin
  * @return bool
  */
 function isAdmin(): bool
{
    $user = kirby()->user();
    if ($user !== null) {
        $role = (string)$user->role();
        if ($role === 'admin') {
            return true;
        }
    }
    return false;
} // isAdmin


 /**
  * Appends string to a file.
  * @param string $file
  * @param string $str
  * @param string $headerIfEmpty    If the file is empty, $headerIfEmpty will be written at the top.
  * @throws Exception
  */
 function appendFile(string $file, string $str, string $headerIfEmpty = ''): void
{
    if (!$file || !is_string($file)) {
        return;
    }

    $file = resolvePath($file);
    preparePath($file);
    $data = @file_get_contents($file);
    if (!$data) {
        file_put_contents($file, $headerIfEmpty . $str);

    } elseif (($p = strpos($data, '__END__')) === false) {
        file_put_contents($file, $str, FILE_APPEND);

    } else {
        $str = substr($data, 0, $p) . $str . substr($data, $p);
        file_put_contents($file, $str);
    }
} // appendFile


 /**
  * Loads content of file, applies some cleanup on demand: remove comments, zap end after __END__.
  * If file extension is yaml, csv or json, data is decoded and returned as a data structure.
  * @param string $file
  * @param bool $removeComments Possible values:
  *         true    -> zap END
  *         'hash'  -> #...
  *         'empty' -> remove empty lines
  *         'cStyle' -> // or /*
  * @param bool $useCaching     In case of yaml files caching can be activated
  * @return array|mixed|string|string[]
  * @throws \Kirby\Exception\InvalidArgumentException
  */
 function loadFile(string $file, $removeComments = true, bool $useCaching = false)
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

    // remove BOM
    $data = str_replace("\xEF\xBB\xBF", '', $data);

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


 /**
  * Loads multiple files and returns combined output (string or array).
  * Note: files of type string and structured data must not be mixed (first file wins).
  * @param $files
  * @param bool $removeComments
  * @param bool $useCaching
  * @return array|mixed|string|null
  * @throws \Kirby\Exception\InvalidArgumentException
  */
 function loadFiles($files, $removeComments = true, bool $useCaching = false) {
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


 /**
  * Checks whether cache contains valid data (used for yaml-cache)
  * @param $file
  * @return mixed|null
  */
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


 /**
  * Writes data to the (yaml-)cache
  * @param string $file
  * @param $data
  * @param string $tag
  * @throws Exception
  */
 function updateDataCache(string $file, $data, string $tag = '')
{
    $raw = serialize($data);
    $cacheFile = cacheFileName($file, $tag);
    preparePath($cacheFile);
    file_put_contents($cacheFile, $raw);
} // updateDataCache


 /**
  * Returns the name of the (yaml-)cache file.
  * @param string $file
  * @param string $tag
  * @return string
  */
 function cacheFileName(string $file, string $tag = ''): string
{
    $cacheFile = localPath($file);
    $cacheFile = str_replace('/', '_', $cacheFile);
    return PFY_CACHE_PATH . $cacheFile . $tag .'.cache';
} // cacheFileName


 /**
  * Returns file extension of a filename.
  * @param string $file0
  * @param bool $reverse    Returns path&filename without extension
  * @return string
  */
 function fileExt(string $file0, bool $reverse = false): string
{
    $file = basename($file0);
    //$file = preg_replace(['|^\w{1,6}://|', '/[#?&:].*/'], '', $file); // If ever needed for URLs as well
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


 /**
  * Returns the filename part of a file-path, optionally without extension, optionally removed URL-args (?...)
  * @param string $file
  * @param bool $incl_ext
  * @param bool $incl_args
  * @return string
  */
 function base_name(string $file, bool $incl_ext = true, bool $incl_args = false): string
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


 /**
  * Converts a absolute path to one starting at app-root.
  * @param string $absPath
  * @return string
  */
 function localPath(string $absPath): string
{
    if (@$absPath[0] === '/') {
        return substr($absPath, strlen(PageFactory::$absAppRoot));
    } else {
        return $absPath;
    }
} // localPath


 /**
  * Returns the dirname of file.
  *     Note: within PageFactory all paths always end with '/'.
  * @param string $path
  * @return string
  */
 function dir_name(string $path): string
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


 /**
  * Appends a trailing '/' if it's missing.
  *     Note: within PageFactory all paths always end with '/'.
  * @param string $path
  * @return string
  */
 function fixPath(string $path): string
{
    if ($path) {
        $path = rtrim($path, '/').'/';
    }
    return $path;
} // fixPath


 /**
  * Zaps rest of file following pattern \n__END__
  * @param string $str
  * @return string
  */
 function zapFileEND(string $str): string
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


 /**
  * Removes empty lines from a string.
  * @param string $str
  * @return string
  */
 function removeEmptyLines(string $str): string
{
    $lines = explode(PHP_EOL, $str);
    foreach ($lines as $i => $l) {
        if (!$l) {
            unset($lines[$i]);
        }
    }
    return implode("\n", $lines);
} // removeEmptyLines


 /**
  * Removes hash-type comments from a string, e.g. \n#...
  * @param string $str
  * @return string
  */
 function removeHashTypeComments(string $str): string
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


 /**
  * Removes c-style comments from a string, e.g. // or /*
  * @param string $str
  * @return string
  */
 function removeCStyleComments(string $str): string
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


 /**
  * Reads content of a directory. Automatically ignores any filenames starting with '#'.
  * @param string $pat  Optional glob-style pattern
  * @return array
  */
 function getDir(string $pat): array
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


 /**
  * Reads a directory recursively. Path can end with a glob-style pattern, e.g. '{*.js,*.css}'
  * @param string $path
  * @param bool $onlyDir    Returns only directories
  * @param bool $assoc      Returns an associative array basename => path
  * @param bool $returnAll  Returns all elements, including those starting with '#'
  * @return array
  */
 function getDirDeep(string $path, bool $onlyDir = false, bool $assoc = false, bool $returnAll = false): array
{
    $files = [];
    $inclPat = base_name($path);
    if ($inclPat && ($inclPat !== '*')) {
        $inclPat = str_replace(['{',',','}','.','*','[!','-','/'],['(','|',')','\\.','.*','[^','\\-','\\/'], $inclPat);
        $inclPat = "/^$inclPat$/";
        $path = dirname($path);
    } else {
        $path = rtrim($path, ' *');
        $inclPat = false;
    }

    $it = new \RecursiveDirectoryIterator($path);
    foreach (new \RecursiveIteratorIterator($it) as $fileRec) {
        $f = $fileRec->getFilename();
        $p = $fileRec->getPathname();
        if ($onlyDir) {
            if (($f === '.') && !preg_match('|/#|', $p)) {
                $files[] = rtrim($p, '.');
            }
            continue;
        }

        // exclude hidden/commented files, unless returnAll:
        if (!$returnAll) {
            if (preg_match('|/[.#]|', $p)) {
                continue;
            }
            // if inclPat is set, exclude everything that doesn't match:
            if ($inclPat && !preg_match($inclPat, $f)) {
                continue;
            }
        }

        if ($assoc) {
            $files[$f] = $p;
        } else {
            $files[] = $p;
        }
    }
    return $files;
} // getDirDeep


 /**
  * Finds the file last modified and returns its modification time.
  * @param string $paths
  * @param bool $recursive  Deep dives into sub-folders
  * @return int
  */
 function lastModified($paths, bool $recursive = true): int
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


 /**
  * Deletes all files in given array in one go (use carefully...)
  * @param $files
  */
 function deleteFiles($files): void
{
    if (is_string($files)) {
        @unlink($files);
    } else {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
} // deleteFiles


 /**
  * Looks for special path patterns starting with '~'. If found replaces them with propre values.
  *   Supported path patterns: ~/, ~page/, ~pagefactory/, ~assets/, ~data/
  * @param string $path
  * @param bool $absPath
  * @return string
  */
 function resolvePath(string $path, bool $absPath = false): string
{
    if (@$path[0] !== '~') {
        return $path;
    }
    
    // resolve PFY's specific folders:
    if ($absPath) {
        $appRoot = PageFactory::$absAppRoot;
    } else {
        $appRoot = '';
    }
    $pageRoot = PageFactory::$pageRoot;
    $pathPatterns = [
        '~/' =>             $appRoot,
        '~page/' =>         $pageRoot,
        '~pagefactory/' =>  $appRoot . 'site/plugins/pagefactory/',
        '~assets/' =>       $appRoot . PFY_USER_ASSETS_PATH,
        '~data/' =>         $appRoot . 'site/data/',
    ];
    $path = str_replace(array_keys($pathPatterns), array_values($pathPatterns), $path);

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


 /**
  * Runs an array of paths through resolvePath()
  * @param $paths
  * @return mixed|string
  */
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


 /**
  * Returns the current git-tag of PageFactory (requires shell_exec permission)
  * @param bool $shortForm
  * @return string
  */
 function getGitTag(bool $shortForm = true): string
{
    $str = shell_exec('cd site/plugins/pagefactory/; /usr/local/bin/git describe --tags --abbrev=0; git log --pretty="%ci" -n1 HEAD');
    if ($str) {
        $str = trim($str, "\n");
    }
    if ($shortForm) {
        return preg_replace("/\n.*/", '', $str);
    } else {
        return str_replace("\n", ' ', $str);
    }
} // getGitTag


 /**
  * Writes a string to a file.
  * @param string $file
  * @param string $content
  * @param int $args        e.e. FILE_APPEND
  * @throws Exception
  */
 function writeFile(string $file, string $content, int $args = 0): void
{
    $file = resolvePath($file);
    preparePath($file);
    file_put_contents($file, $content, $args);
} // writeFile


 /**
  * Takes a path and creates corresponding folders/subfolders if they don't exist. Applies access writes if given.
  * @param string $path0
  * @param false $accessRights
  * @throws Exception
  */
 function preparePath(string $path0, $accessRights = false): void
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
            throw new Exception("Error: failed to create folder '$path'");
        }
    }

    if ($accessRights) {
        $path1 = '';
        foreach (explode('/', $path) as $p) {
            $path1 .= "$p/";
            try {
                chmod($path1, $accessRights);
            } catch (Exception $e) {
                throw new Exception("Error: failed to create folder '$path'");
            }
        }
    }

    if ($path0 && !file_exists($path0)) {
        try {
            touch($path0);
        } catch (Exception $e) {
            throw new Exception("Error: failed to create folder '$path'");
        }
    }
} // preparePath


 /**
  * If within a path pattern '../' appears, replaces it with a direct path.
  * @param string $path
  * @return string
  */
 function normalizePath(string $path): string
{
     $hdr = '';
     if (preg_match('|^ ((\.\./)+) (.*)|x', $path, $m)) {
         $hdr = $m[1];
         $path = $m[3];
     }
     while ($path && preg_match('|(.*?) ([^/.]+/\.\./) (.*)|x', $path, $m)) {
         $path = $m[1] . $m[3];
     }
     if (strpos($path, '//')) {
         $x = true;
     }
     $path = str_replace('/./', '/', $path);
     $path = preg_replace('|(?<!:)//|', '/', $path);
     return $hdr.$path;
} // normalizePath


 /**
  * Simple log function for quick&dirty testing
  * @param string $str
  * @throws Exception
  */
 function mylog(string $str): void
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


 /**
  * Returns a timestamp string of type '2021-12-07'
  * @param bool $short
  * @return string
  */
 function timestampStr(bool $short = false): string
{
    if (!$short) {
        return date('Y-m-d H:i:s');
    } else {
        return date('Y-m-d');
    }
} // timestampStr


 /**
  * Indents every line within a given string.
  * @param string $str
  * @param int $width
  * @return string
  */
 function indentLines(string $str, int $width = 4): string
{
    $str1 = '';
    $indent = str_pad('', $width, ' ');
    foreach (explode("\n", $str) as $l) {
        $str1 .= "$indent$l\n";
    }
    return rtrim($str1, "\n\t ");
} // indentLines


 /**
  * Parses a string to extract structured data of relaxed Yaml syntax:
  *     First arguments may omit key, then they get keys 0,1,...
  *     Superbrackts may be used to shield value contents: e.g. '!!', '%%' (as used by macros)
  *     Example: key: !! x:('") !!
  * @param string $str
  * @param string $delim
  * @param false $superBrackets
  * @return array
  * @throws \Kirby\Exception\InvalidArgumentException
  */
 function parseArgumentStr(string $str, string $delim = ',', $superBrackets = false): array
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

    $rest = superBracketsEncode($rest, $superBrackets);
    if (preg_match('/^(.*?) \)\s*}}/msx', $rest, $mm)) {
        $rest = rtrim($mm[1], " \t\n");
    }

    $out = '';
    $counter = 100;
    while ($rest && ($counter-- > 0)) {
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
        throw new Exception($e);
    }
    if ($superBrackets) {
        $options = superBracketsDecode($options);
    }
    return $options;
} // parseArgumentStr


 /**
  * Shield content of superbrackets (i.e. base64_encode)
  * @param string $str
  * @param $superBrackets
  * @return array|mixed|string|string[]
  */
 function superBracketsEncode(string $str, $superBrackets)
{
    if ($superBrackets) {
        if (is_string($superBrackets)) {
            $superBrackets = [$superBrackets];
        }
        foreach ($superBrackets as $bracket) {
            $pattern = "/ (?<!\\\\) $bracket (.*?) (?<!\\\\) $bracket/xms";
            if (preg_match_all($pattern, $str, $m)) {
                foreach ($m[1] as $i => $value) {
                    $value = base64_encode($value);
                    $str = str_replace($m[0][$i], "'@@b64@$value@b64@@'", $str);
                }
            }
        }
    }
    return $str;
} // superBracketsEncode


 /**
  * Decode shielded string
  * @param $item
  * @return array|mixed|string|string[]
  */
 function superBracketsDecode($item)
{
    if (is_string($item)) {
        if (preg_match_all('/@@b64@ (.*?) @b64@@/xms', $item, $m)) {
            foreach ($m[1] as $i => $value) {
                $value = base64_decode($value);
                $item = str_replace($m[0][$i], $value, $item);
            }
        }

    } elseif (is_array($item)) {
        foreach ($item as $key => $value) {
            if (is_string($value) && preg_match_all('/@@b64@ (.*?) @b64@@/xms', $value, $m)) {
                foreach ($m[1] as $i => $v) {
                    $v = base64_decode($v);
                    $item[$key] = str_replace($m[0][$i], $v, $value);
                }
            }
        }
    }
    return $item;
} // superBracketsEncode


 /**
  * Parses the key part of 'key: value'
  * @param string $rest
  * @param string $delim
  * @return string
  */
 function parseArgKey(string &$rest, string $delim): string
{
    $lead = '';
    $key = '';
    if (preg_match('/^(\s*)(.*)/', $rest, $m)) {
        $lead = $m[1];
        $rest = substr($rest, strlen($lead));
    }
    // case quoted key or value:
    if ((($ch1 = @$rest[0]) === '"') || ($ch1 === "'")) {
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


 /**
  * Parses the value part of 'key: value'
  * @param string $rest
  * @param string $delim
  * @return string
  */
 function parseArgValue(string &$rest, string $delim): string
{
    // case quoted key or value:
    $value = '';
    $ch1 = ltrim($rest);
    $ch1 = @$ch1[0];
    if (($ch1 === '"') || ($ch1 === "'")) {
        $rest = ltrim($rest);
        $pattern = "$ch1 (.*?) $ch1";
        // case 'value' without key:
        if (preg_match("/^ ($pattern) (.*)/xms", $rest, $m)) {
            $value = $m[1];
            $rest = ltrim($m[3], ', ');
        }

        // case naked key or value:
    } else {
        // case value without key:
        $pattern = "[^$delim\n]+";
        if (preg_match("/^ ($pattern) (.*) /xms", $rest, $m)) {
            $value = $m[1];
            $rest = ltrim($m[2], ', ');
        }
    }
    $pattern = "^[$delim\n]+";
    $rest = preg_replace("/$pattern/", '', $rest);
    return $value;
} // parseArgValue


 /**
  * Returns positions of opening and closing patterns, ignoring shielded patters (e.g. \{{ )
  * @param string $str
  * @param int $p0
  * @param string $pat1
  * @param string $pat2
  * @return array|false[]
  * @throws Exception
  */
 function strPosMatching(string $str, int $p0 = 0, string $pat1 = '{{', string $pat2 = '}}'): array
 {

     if (!$str) {
         return [false, false];
     }
     checkBracesBalance($str, $p0, $pat1, $pat2);

     $d = strlen($pat2);
     if ((strlen($str) < 4) || ($p0 > strlen($str))) {
         return [false, false];
     }

     if (!checkNesting($str, $pat1, $pat2)) {
         return [false, false];
     }

     $p1 = $p0 = findNextPattern($str, $pat1, $p0);
     $cnt = 0;
     do {
         $p3 = findNextPattern($str, $pat1, $p1+$d); // next opening pat
         $p2 = findNextPattern($str, $pat2, $p1+$d); // next closing pat
         if ($p2 === false) { // no more closing pat
             return [false, false];
         }
         if ($cnt === 0) {	// not in nexted structure
             if ($p3 === false) {	// no more opening pat
                 return [$p0, $p2];
             }
             if ($p2 < $p3) { // no more opening patterns or closing before next opening
                 return [$p0, $p2];
             } else {
                 $cnt++;
                 $p1 = $p3;
             }
         } else {	// within nexted structure
             if ($p3 === false) {	// no more opening pat
                 $cnt--;
                 $p1 = $p2;
             } else {
                 if ($p2 < $p3) { // no more opening patterns or closing before next opening
                     $cnt--;
                     $p1 = $p2;
                 } else {
                     $cnt++;
                     $p1 = $p3;
                 }
             }
         }
     } while (true);
 } // strPosMatching


 /**
  * Helper for strPosMatching()
  * @param string $str
  * @param int $p0
  * @param string $pat1
  * @param string $pat2
  * @throws Exception
  */
 function checkBracesBalance(string $str, int $p0 = 0, string $pat1 = '{{', string $pat2 = '}}'): void
 {
     $shieldedOpening = substr_count($str, '\\' . $pat1, $p0);
     $opening = substr_count($str, $pat1, $p0) - $shieldedOpening;
     $shieldedClosing = substr_count($str, '\\' . $pat2, $p0);
     $closing = substr_count($str, $pat2, $p0) - $shieldedClosing;
     if ($opening > $closing) {
         throw new Exception("Error in source: unbalanced number of &#123;&#123; resp }}");
     }
 } // checkBracesBalance


 /**
  * Helper for strPosMatching()
  * @param string $str
  * @param string $pat1
  * @param string $pat2
  * @return int
  * @throws Exception
  */
 function checkNesting(string $str, string $pat1, string $pat2): int
 {
     $n1 = substr_count($str, $pat1);
     $n2 = substr_count($str, $pat2);
     if ($n1 > $n2) {
         throw new Exception("Nesting Error in string '$str'");
     }
     return $n1;
 } // checkNesting


 /**
  * Finds the next position of unshielded pattern
  * @param string $str
  * @param string $pat
  * @param int $p1
  * @return false|int
  */
function findNextPattern(string $str, string $pat, $p1 = 0)
{
    while (($p1 = strpos($str, $pat, $p1)) !== false) {
        if (($p1 === 0) || (substr($str, $p1 - 1, 1) !== '\\')) {
            break;
        }
        $p1 += strlen($pat);
    }
    return $p1;
} // findNextPattern


 /**
  * Parses a string to retrieve HTML/CSS attributes
  *  Patterns:
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
  * @param string $str
  * @return array
  */
function parseInlineBlockArguments(string $str): array
{
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


 /**
  * Helper for parseInlineBlockArguments() -> identifies extended commands (starting with '!')
  * @param string $arg
  * @param string $lang
  * @param string $literal
  * @param bool $mdCompile
  * @param string $style
  * @param string $tag
  */
 function _parseMetaCmds(string $arg, string &$lang, string &$literal, bool &$mdCompile, string &$style, string &$tag): void
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


 /**
  * Helper for parseInlineBlockArguments()
  * @param string $id
  * @param string $class
  * @param string $style
  * @param $attr
  * @return array
  */
 function _assembleHtmlAttrs(string $id, string $class, string $style, $attr): array
{
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


 /**
  * Splits a string and trims each element. Optionally removes empty elements.
  * @param string $sep
  * @param string $str
  * @param bool $excludeEmptyElems
  * @return array
  */
 function explodeTrim(string $sep, string $str, bool $excludeEmptyElems = false): array
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


 /**
  * Shorthand to run a string through the 'MarkdownPlus' compiler
  * @param string $mdStr
  * @return string
  */
 function compileMarkdown(string $mdStr): string
{
    return kirby()->markdown($mdStr);
} // compileMarkdown


 /**
  * Shields a string from the markdown compiler, optionally instructing the unshielder to run the result through
  * the md-compiler separately.
  * @param string $str
  * @param string $type
  * @return string
  */
 function shieldStr(string $str, $type = 'raw'): string
{
    if ($type === 'md') {
        return '{md{' . base64_encode($str) . '}md}';
    } else {
        return '{raw{' . base64_encode($str) . '}raw}';
    }
} // shieldStr


 /**
  * Un-shields shielded strings, optionally running the result through the md-compiler
  * @param string $str
  * @return bool
  */
 function unshieldStr(string &$str): bool
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


 /**
  * Helper for translateToFilename()
  * @param string $str
  * @return string
  */
 function strToASCII(string $str): string
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


 /**
  * Translates a given string to a legal filename
  * @param string $str
  * @param bool $appendExt
  * @return string
  */
 function translateToFilename(string $str, $appendExt = true): string
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


 /**
  * Translates a given string to a legal identifier
  * @param string $str
  * @param bool $removeDashes
  * @param bool $removeNonAlpha
  * @param bool $toLowerCase
  * @return string
  */
 function translateToIdentifier(string $str, bool $removeDashes = false, bool $removeNonAlpha = false,
                                bool   $toLowerCase = true): string
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


 /**
  * Translates a given string to a legal class name or id
  * @param string $str
  * @return string
  */
 function translateToClassName(string $str): string
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


 /**
  * Converts an array or object to a more or less readable string.
  * @param $var
  * @param string $varName
  * @param bool $flat
  * @return string
  */
 function var_r($var, string $varName = '', bool $flat = false): string
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


 /**
  * Forces the agent (broser) to reload the page.
  * @param false $target
  * @param false $getArg
  */
 function reloadAgent($target = false, $getArg = false): void
{
    if (!$target) {
        $target = page()->url();
    }
    header("Location: $target");
    exit;
} // reloadAgent


