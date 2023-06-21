<?php
namespace Usility\PageFactory;

 // Use helper functions with prefix '\Usility\PageFactory\'
use Kirby\Data\Yaml as Yaml;
use Kirby\Data\Json as Json;
use Kirby\Data\Data;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Filesystem\F;
use Exception;
use Usility\MarkdownPlus\MarkdownPlus;

const FILE_BLOCKING_MAX_TIME = 500; //ms
const FILE_BLOCKING_CYCLE_TIME = 50; //ms

const UNAMBIGUOUS_CHARACTERS = '3479ACDEFHJKLMNPQRTUVWXY'; // -> excludes '0O2Z1I5S6G8B'

define('KIRBY_ROOTS',           kirby()->roots()->toArray());
define('KIRBY_ROOT_PATTERNS',   ','.implode(',', array_keys(KIRBY_ROOTS)).',');


 /**
  * Checks whether agent is in the same subnet as IP 192.x.x.x
  * @return bool
  */
function isLocalhost(): bool
{
    // url-arg ?localhost=false let's you mimick a remote host:
    if (($_GET['localhost']??'') === 'false') {
        return false;
    }
    $ip = kirby()->visitor()->ip();
    return ((str_starts_with($ip, '192.')) || ($ip === '::1'));
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
  * Checks whether visitor is admin or working on localhost
  * @return bool
  */
function isAdminOrLocalhost(): bool
{
    return isAdmin() || isLocalhost();
} // isAdminOrLocalhost


 /**
  * Checks whether visitor is logged in or working on localhost
  * @return bool
  */
function isLoggedinOrLocalhost(): bool
{
    return isLoggedIn() || isLocalhost();
} // isLoggedinOrLocalhost


 /**
  * Returns true, if request (probably) came from a bot
  * @return bool
  */
 function isBot(): bool {
     if ( preg_match('/abacho|accona|AddThis|AdsBot|ahoy|AhrefsBot|AISearchBot|alexa|altavista|anthill|'.
         'appie|applebot|arale|araneo|AraybOt|ariadne|arks|aspseek|ATN_Worldwide|Atomz|baiduspider|baidu|bbot|bingbot'.
         '|bing|Bjaaland|BlackWidow|BotLink|bot|boxseabot|bspider|calif|CCBot|ChinaClaw|christcrawler|CMC\/0\.01|'.
         'combine|confuzzledbot|contaxe|CoolBot|cosmos|crawler|crawlpaper|crawl|curl|cusco|cyberspyder|cydralspider|'.
         'dataprovider|digger|DIIbot|DotBot|downloadexpress|DragonBot|DuckDuckBot|dwcp|EasouSpider|ebiness|'.
         'ecollector|elfinbot|esculapio|ESI|esther|eStyle|Ezooms|facebookexternalhit|facebook|facebot|fastcrawler|'.
         'FatBot|FDSE|FELIX IDE|fetch|fido|find|Firefly|fouineur|Freecrawl|froogle|gammaSpider|gazz|gcreep|geona|'.
         'Getterrobo-Plus|get|girafabot|golem|googlebot|-google|grabber|GrabNet|griffon|Gromit|gulliver|gulper|'.
         'hambot|havIndex|hotwired|htdig|HTTrack|ia_archiver|iajabot|IDBot|Informant|InfoSeek|InfoSpiders|'.
         'INGRID\/0\.1|inktomi|inspectorwww|Internet Cruiser Robot|irobot|Iron33|JBot|jcrawler|Jeeves|jobo|'.
         'KDD-Explorer|KIT-Fireball|ko_yappo_robot|label-grabber|larbin|legs|libwww-perl|linkedin|Linkidator|'.
         'linkwalker|Lockon|logo_gif_crawler|Lycos|m2e|majesticsEO|marvin|mattie|mediafox|mediapartners|MerzScope|'.
         'MindCrawler|MJ12bot|mod_pagespeed|moget|Motor|msnbot|muncher|muninn|MuscatFerret|MwdSearch|'.
         'NationalDirectory|naverbot|NEC-MeshExplorer|NetcraftSurveyAgent|NetScoop|NetSeer|newscan-online|'.
         'nil|none|Nutch|ObjectsSearch|Occam|openstat.ru\/Bot|packrat|pageboy|ParaSite|patric|pegasus|'.
         'perlcrawler|phpdig|piltdownman|Pimptrain|pingdom|pinterest|pjspider|PlumtreeWebAccessor|'.
         'PortalBSpider|psbot|rambler|Raven|RHCS|RixBot|roadrunner|Robbie|robi|RoboCrawl|robofox|Scooter|'.
         'Scrubby|Search-AU|searchprocess|search|SemrushBot|Senrigan|seznambot|Shagseeker|sharp-info-agent|'.
         'sift|SimBot|Site Valet|SiteSucker|skymob|SLCrawler\/2\.0|slurp|snooper|solbot|speedy|spider_monkey|'.
         'SpiderBot\/1\.0|spiderline|spider|suke|tach_bw|TechBOT|TechnoratiSnoop|templeton|teoma|titin|topiclink|'.
         'twitterbot|twitter|UdmSearch|Ukonline|UnwindFetchor|URL_Spider_SQL|urlck|urlresolver|'.
         'Valkyrie libwww-perl|verticrawl|Victoria|void-bot|Voyager|VWbot_K|wapspider|WebBandit\/1\.0|'.
         'webcatcher|WebCopier|WebFindBot|WebLeacher|WebMechanic|WebMoose|webquest|webreaper|webspider|webs|'.
         'WebWalker|WebZip|wget|whowhere|winona|wlm|WOLP|woriobot|WWWC|XGET|xing|yahoo|YandexBot|YandexMobileBot|'.
         'yandex|yeti|Zeus/i', $_SERVER['HTTP_USER_AGENT'])) {
         return true; // 'Above given bots detected'
     }
     return false;
 } // isBot


 /**
  * Loads content of file, applies some cleanup on demand: remove comments, zap end after __END__.
  * If file extension is yaml, csv or json, data is decoded and returned as a data structure.
  * @param string $file
  * @param mixed $removeComments Possible values:
  *         true    -> zap END
  *         'hash'  -> #...
  *         'empty' -> remove empty lines
  *         'cStyle' -> // or /*
  * @param bool $useCaching In case of yaml files caching can be activated
  * @return string
  * @throws InvalidArgumentException
  */
function loadFile(string $file, mixed $removeComments = true, bool $useCaching = false): mixed
{
    if (!$file || !is_string($file)) {
        return '';
    }
    if ($useCaching) {
        $data = checkDataCache($file);
        if ($data !== false) {
            return $data;
        }
    }
    $data = getFile($file, $removeComments);

    // if it's data of a known format (i.e. yaml,json etc), decode it:
    $ext = fileExt($file);
    if (str_contains(',yaml,yml,json,csv', $ext)) {
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
  * @param mixed $files
  * @param mixed $removeComments
  * @param bool $useCaching
  * @return array|mixed|string|null
  * @throws InvalidArgumentException
  */
function loadFiles(mixed $files, mixed $removeComments = true, bool $useCaching = false): mixed
{
    if (!$files || !is_array($files)) {
        return false;
    }

    if ($useCaching) {
        if (($data = checkDataCache($files)) !== false) {
            return $data;
        }
    }

    $file1 = $files[0]??'';
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
  * Reads file safely, first resolving path, removing comments and zapping rest (after __END__) if requested
  * @param string $file
  * @param mixed $removeComments -> cstyle|hash|empty
  * @return array|false|string|string[]
  */
function getFile(string $file, mixed $removeComments = true)
 {
     if (!$file || !is_string($file)) {
         return '';
     }

     $file = resolvePath($file);

     $data = fileGetContents($file);
     if (!$data) {
         return '';
     }

     // remove BOM
     $data = str_replace("\xEF\xBB\xBF", '', $data);

     $data = removeComments($data, $removeComments);

     return $data;
 } // getFile


 /**
  * file_get_contents() replacement with file_exists check
  * @param $file
  * @return false|string
  */
 function fileGetContents(string $file): mixed
 {
     if (file_exists($file)) {
         return @file_get_contents($file);
     } else {
         return false;
     }
 } // fileGetContents


 /**
  * file_put_contents() replacement with is_writable check
  * @param string $file
  * @param string $str
  * @param int $flags
  * @return mixed
  */
 function filePutContents(string $file, string $str, int $flags = 0): mixed
 {
     if (is_writable($file) || is_writable(dirname($file))) {
         return file_put_contents($file, $str, $flags);
     } else {
         return false;
     }
 } // filePutContents


 /**
  * filemtime() replacement with file_exists check
  * @param string $file
  * @return int
  */
 function fileTime(string $file): int
 {
     if (file_exists($file)) {
         return (int)@filemtime($file);
     } else {
         return 0;
     }
 } // fileTime


 /**
  * Checks whether cache contains valid data (used for yaml-cache)
  * @param string|array $file
  * @return mixed|null
  */
function checkDataCache(mixed $file): mixed
{
    if (is_array($file)) {
        $file1 = $file[0]??'';
        $cacheFile = cacheFileName($file1, '.0');
        if (!file_exists($cacheFile)) {
            return false;
        }
        $tCache = fileTime($cacheFile);
        $tFiles = 0;
        foreach ($file as $f) {
            $tFiles = max($tFiles, fileTime($f));
        }
        if ($tFiles < $tCache) {
            $raw = file_get_contents($cacheFile);
            return unserialize($raw);
        }

    } else {
        $cacheFile = cacheFileName($file);
        if (file_exists($cacheFile)) {
            $tFile = fileTime($file);
            $tCache = fileTime($cacheFile);
            if ($tFile < $tCache) {
                $raw = file_get_contents($cacheFile);
                return unserialize($raw);
            }
        }
    }
    return false;
} // checkDataCache


 /**
  * Writes data to the (yaml-)cache
  * @param string $file
  * @param $data
  * @param string $tag
  * @throws Exception
  */
function updateDataCache(string $file, mixed $data, string $tag = '')
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
  * Parses a string of key:value pairs and returns it as an array if possible.
  * @param mixed $var
  * @return string
  */
 function parseArrayArg(mixed $var): mixed
 {
     if (is_string($var)) {
         $var = explodeTrim(',', $var);
         $isAssoc = false;
         foreach ($var as $value) {
             if (strpos($value, ':') !== false) {
                 $isAssoc = true;
                 break;
             }
         }
         if ($isAssoc) {
             $tmp = [];
             foreach ($var as $value) {
                 if (preg_match('/(.*):\s*(.*)/', $value, $m)) {
                     $tmp[$m[1]] = trim($m[2], '\'"');
                 } else {
                     $tmp[$value] = $value;
                 }
             }
             $var = $tmp;
         }
     }
     return $var;
} // parseArrayArg


 /**
  * Decodes Yaml to an array
  * @param string $str
  * @return array
  * @throws InvalidArgumentException
  */
function decodeYaml(string $str): array
 {
     return Yaml::decode($str);
 } // decodeYaml


 /**
  * Encodes data to Yaml
  * @param array $data
  * @return string
  */
 function encodeYaml(array $data): string
 {
     return Yaml::encode($data);
 } // encodeYaml


 /**
  * Parses a Kirby-frontmatter string, returns corresponding data array.
  * @param string $frontmatter
  * @return array
  */
function extractKirbyFrontmatter(string $frontmatter): array
 {
     if (!$frontmatter) {
         return [];
     }

     // explode all fields by the line separator
     $fields = preg_split('!\n----\s*\n*!', $frontmatter);
     $data = [];

     // loop through all fields and add them to the content
     foreach ($fields as $field) {
         $pos = strpos($field, ':');
         $key = camelCase(trim(substr($field, 0, $pos)));

         // Don't add fields with empty keys
         if (empty($key) === true) {
             continue;
         }

         $value = trim(substr($field, $pos + 1));

         // unescape escaped dividers within a field
         $data[$key] = preg_replace('!(?<=\n|^)\\\\----!', '----', $value);
     }

     return $data;
 } // extractKirbyFrontmatter


 /**
  * Returns file extension of a filename.
  * @param string $file0
  * @param bool $reverse       Returns path&filename without extension
  * @param bool $couldBeUrl    Handles case where URL may include args and/or #target
  * @return string
  */
function fileExt(string $file0, bool $reverse = false, $couldBeUrl = false): string
{
    if ($couldBeUrl) {
        $file = preg_replace(['|^\w{1,6}://|', '/[#?&:].*/'], '', $file0); // If ever needed for URLs as well
        $file = basename($file);
    } else {
        $file = basename($file0);
    }
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
    if (($absPath[0]??'') === '/') {
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
    if (!$path || str_starts_with($path, '/')) {
        return $path;
    }
    $path = preg_replace('/[#?*].*/', '', $path); //
    if (str_contains(basename($path), '.')) {  // if it contains a '.' we assume it's a file
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
  * @param string $str
  * @param mixed $removeComments
  * @return string
  */
 function removeComments(string $str, mixed $removeComments = true): string
{
    // special option 'zapped' -> return what would be zapped:
    if (str_contains((string)$removeComments, 'zapped')) {
        return zapFileEND($str, true);
    }

    // always zap, unless $removeComments === false:
    if ($removeComments) {
        $str = zapFileEND($str);
    }
    // default (== true):
    if ($removeComments === true) {
        $str = removeCStyleComments($str);
        $str = removeEmptyLines($str);

        // specific instructions:
    } elseif (is_string($removeComments)) {
        // extract first characters from comma-separated-list:
        $removeComments = implode('', array_map(function ($elem){
            return strtolower($elem[0]);
        }, explodeTrim(',',$removeComments)));

        if (str_contains($removeComments, 'c')) {    // c style
            $str = removeCStyleComments($str);
        }
        if (str_contains($removeComments, 'h')) {    // hash style
            $str = removeHashTypeComments($str);
        }
        if (str_contains($removeComments, 'e')) {    // empty lines
            $str = removeEmptyLines($str);
        }
    }
    return $str;
} // removeComments


 /**
  * Zaps rest of file following pattern \n__END__
  * @param string $str
  * @return string
  */
function zapFileEND(string $str, bool $reverse = false): string
{
    if (($p = str_starts_with($str, '__END__')? 0 : false) === false) {
        $p = strpos($str, "\n__END__");
    }
    // __END__ not found:
    if ($p === false) {
        if ($reverse) {
            return '';
        } else {
            return $str;
        }
    }

    // __END__ found:
    if ($reverse) {
        $str = substr($str, $p);
    } else {
        $str = substr($str, 0, $p);
    }
    return $str;
} // zapFileEND


 /**
  * Removes empty lines from a string.
  * @param string $str
  * @return string
  */
function removeEmptyLines(string $str, bool $leaveOne = true): string
{
    if ($leaveOne) {
        return preg_replace("/\n\s*\n+/", "\n\n", $str);
    } else {
        return preg_replace("/\n\s*\n+/", "\n", $str);
    }
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
        if ($p2 === false) {
            $str = substr($str, 0, $p); // case: end */ missing -> cut off all
            break;
        }
        $str = substr($str, 0, $p) . substr($str, $p2 + 2);
    }

    $p = 0;
    while (($p = strpos($str, '//', $p)) !== false) {        // // style comments

        if ($p && ($str[$p - 1] === ':')) {            // avoid http://
            $p += 2;
            continue;
        }

        if ($p && ($str[$p - 1] === '\\')) {                    // avoid shielded //
            $p += 3;
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
  * @param bool|string $associative  Return as associative array
  * @return array
  */
function getDir(string $pat, mixed $associative = false): array
{
    if (strpos($pat, '{') === false) {
        if (strpos($pat, '*') !== false) {
            $files = glob($pat);
        } else {
            $files = glob(fixPath($pat).'*', GLOB_BRACE);
        }
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
    if ($associative === 'name_only') {
        $filenames = array_map(function ($e) { return base_name($e, false); }, $files);
        $files = array_combine($filenames, $files);
    } elseif ($associative) {
        $filenames = array_map(function ($e) { return basename($e); }, $files);
        $files = array_combine($filenames, $files);
    }
    return $files;
} // getDir


 /**
  * For each pattern runs getDir() and returns combined output.
  * @param array $patterns
  * @return array
  */
function getDirs(array $patterns): array
 {
     $files = [];
     foreach ($patterns as $pattern) {
         $files = array_merge($files, getDir($pattern));
     }
     return $files;
 } // getDirs


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
    if (!$returnAll && $inclPat && ($inclPat !== '*')) {
        $inclPat = str_replace(['{',',','}','.','*','[!','-','/'],['(','|',')','\\.','.*','[^','\\-','\\/'], $inclPat);
        $inclPat = "/^$inclPat$/";
        $path = dirname($path);
    } else {
        $path = rtrim($path, ' *');
        $inclPat = false;
    }

    if (!is_dir($path)) {
        throw new Exception("Folder doesn't exist: '$path'");
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
        } elseif (($f === '.') || ($f === '..')) {
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


 /**
  * Finds the file last modified and returns its modification time.
  * @param mixed $paths
  * @param bool $recursive  Deep dives into sub-folders
  * @return int
  */
function lastModified(mixed $paths, bool $recursive = true): int
{
    $newest = 0;
    $paths = resolvePath($paths);

    if (is_string($paths)) {
        $paths = [$paths];
    }
    if ($recursive) {
        foreach ($paths as $path) {
            $path = './' . rtrim($path, '*');
            $it = new \RecursiveDirectoryIterator($path);
            foreach (new \RecursiveIteratorIterator($it) as $fileRec) {
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
function deleteFiles(mixed $files): void
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
  * Checks predefined paths to find available icons
  * Note: this does not include Kirby's own icons as they are not available for web-pages
  *  (https://forum.getkirby.com/t/panel-icons-but-how/15612/7)
  * @return array
  */
function findAvailableIcons(): array
 {
     $availableIcons = getDir(PFY_SVG_ICONS_PATH, 'name_only');
     $availableIcons = array_merge($availableIcons, getDir(PFY_ICONS_PATH, 'name_only'));
     return $availableIcons;
 } // findAvailableIcons


 /**
  * Looks for special path patterns starting with '~'. If found replaces them with propre values.
  *   Supported path patterns: ~/, ~page/, ~pagefactory/, ~media/, ~assets/, ~data/
  * @param string $path
  * @param bool $returnAbsPath
  * @param bool $relativeToPage
  * @return string
  */
function resolvePath(string $path, bool $returnAbsPath = false, bool $relativeToPage = false): string
{
    if (($path[0]??'') !== '~') {
        if ($relativeToPage) {
            if ($returnAbsPath) {
                $path = PageFactory::$absPageRoot.$path;
            } else {
                $path = PageFactory::$pageRoot.$path;
            }
        }
        return $path;
    }

    // first check for root-paths defined by kirby:
    if (($path[1]??'') !== '/') {
        $path1 = preg_replace('|/.*|', '', substr($path, 1));

        // '~assets/' is an exception: it shall point to 'content/assets/' rather than 'assets/':
        if (($path1 !== 'assets') && (strpos(KIRBY_ROOT_PATTERNS, ",$path1,") !== false)) {
            $path = KIRBY_ROOTS[$path1].substr($path, strlen($path1)+1);
            return $path;
        }
    }

    // resolve PFY's specific folders:
    if ($returnAbsPath) {
        $appRoot = PageFactory::$absAppRoot;
    } else {
        $appRoot = '';
    }
    // ~pages/ is special case -> use Kirby to determine actual path:
    if (str_starts_with($path, '~pages/')) {
        $filename = basename($path);
        $path = dirname(substr($path, 7));
        $pg = page($path);
        if ($pg) {
            $path = $pg->root().'/'.$filename;
        }

    // other patterns:
    } else {
        $pageRoot = PageFactory::$pageRoot;
        $pathPatterns = [
            '~/' => $appRoot,
            '~media/' => $appRoot . 'media/',
            '~assets/' => $appRoot . 'content/assets/',
            '~data/' => $appRoot . 'site/custom/data/',
            '~page/' => $pageRoot,
            '~pagefactory/' => $appRoot . 'site/plugins/pagefactory/assets/',
        ];
        $path = str_replace(array_keys($pathPatterns), array_values($pathPatterns), $path);
    }
    return $path;
} // resolvePath


 /**
  * Runs an array of paths through resolvePath()
  * @param mixed $paths
  * @return mixed|string
  */
function resolvePaths(mixed $paths): string
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
  * @param int $flags        e.g. FILE_APPEND
  * @throws Exception
  */
function writeFile(string $file, string $content, int $flags = 0): void
{
    $file = resolvePath($file);
    preparePath($file);
    if (file_put_contents($file, $content, $flags) === false) {
        $file = basename($file);
        throw new \Exception("Writing to file '$file' failed");
    }
} // writeFile


 /**
  * Writes to file locking it during file system access.
  * Converts data to type of file, i.e. yaml, json, csv if necessary.
  * @param string $file
  * @param mixed $content
  * @param string $type
  * @param string $blocking
  * @return void
  * @throws Exception
  */
 function writeFileLocking(string $file, mixed $content, string $type = '', bool $blocking = false): void
{
    if (!$type) {
        $type = strtolower(fileExt($file));
    }
    // encode data:
    if (strpos('yml,yaml,json', $type) !== false) {
        $content = Data::encode($content, $type);
    } elseif (is_object($content)) {
        $content = serialize($content);
    }

    // write data to file:
    $fp = fopen($file,"w");
    awaitFileLock($fp, true, $file, $blocking);

    if ($type === 'csv') {
        $rec1 = reset($content);
        fputcsv($fp, array_keys($rec1));
        foreach ($content as $line) {
            fputcsv($fp, $line);
        }
    } else {
        if (fwrite($fp, $content) === false) {
            throw new \Exception("Error writing file '$file'");
        }
    }
    if (flock($fp, LOCK_UN) === false) {
        throw new \Exception("Error unlocking file '$file'");
    }
    if (fclose($fp) === false) {
        throw new \Exception("Error closing file '$file'");
    }
} // writeFileLocking


 /**
  * Performs a read-modify-write operation during which the file is locked.
  * @param string $file
  * @param string $callback
  * @param bool $blocking
  * @return string
  * @throws Exception
  */
 function readModifyWrite(string $file, string $callback, bool $blocking = true): string
 {
     $fp = fopen($file, 'c+b');

     try {
         awaitFileLock($fp, true, $file, $blocking);

         try {
             $content = $callback(stream_get_contents($fp));
             ftruncate($fp, 0);
             rewind($fp);
             fwrite($fp, $content);
             fflush($fp);
         } finally {
             flock($fp, LOCK_UN);
         }
     } finally {
         fclose($fp);
     }

     return $content;
} // readModifyWrite


 /**
  * Reads a file, decodes content according to file type, i.e. yaml, json, csv
  * @param string $file
  * @param string $type
  * @param mixed $textEncoding
  * @return mixed
  */
 function readFile(string $file, string $type = '', mixed $textEncoding = false): mixed
{
    if (!file_exists($file)) {
        return '';
    }
    $str = file_get_contents($file);

    if (!$type) {
        $type = fileExt($file);
    }
    return decodeStr($str, $type, $textEncoding);
} // readFile


 /**
  * Awaits unlocking of file if necessary, then reads file, decodes content according to file type
  * @param string $file
  * @param string $type
  * @param bool $blocking
  * @param mixed $textEncoding
  * @return mixed
  * @throws Exception
  */
 function readFileLocking(string $file, string $type = '', bool $blocking = true, mixed $textEncoding = false): mixed
{
    $fp = fopen($file,"r");
    awaitFileLock($fp, false, $file, $blocking);
    $str = stream_get_contents($fp);
     if ($str === false) {
         throw new \Exception("Error reading file '$file'");
     }
     if (fclose($fp) === false) {
        throw new \Exception("Error closing file '$file'");
    }

    if (!$type) {
        $type = fileExt($file);
    }
    return decodeStr($str, $type, $textEncoding);
} // readFileLocking


 /**
  * Decodes a string according to data type (yaml, json, csv)
  * Attempts to cope with strings in various character encoding, including 'macintosh'.
  * @param string $str
  * @param string $type
  * @param mixed $textEncoding
  * @return mixed
  */
 function decodeStr(string $str, string $type = '', mixed $textEncoding = false): mixed
{
     if ($textEncoding) {
         if ($textEncoding !== true) {
             $str = iconv($textEncoding, 'UTF-8', $str);
         } else { // try to auto-detect:
             $encoding = mb_detect_encoding($str) ?: 'macintosh';
             if ($encoding !== 'UTF-8') {
                 $str = iconv($encoding, 'UTF-8', $str);
             }
         }
     }

     switch ($type) {
         case 'yml':
         case 'yaml':
         case 'json':
             if (!$str) {
                 return [];
             }
             return Data::decode($str, $type);

         case 'csv':
             if (!$str) {
                 return [];
             }
             $array = parseCsv($str);
             $array2 = [];
             $headers = array_shift($array);
             $nElems = sizeof($headers);
             foreach ($array as $rec) {
                 if (sizeof($rec) === $nElems) {
                     $array2[] = array_combine($headers, $rec);
                 }
             }
             return $array2;
     }
     return $str;
} // decodeStr



 if(!function_exists('mb_detect_encoding')) {
     /**
      * Detects character encoding, including 'macintosh'
      * @param string $string
      * @param mixed|null $enc
      * @return mixed
      */
     function mb_detect_encoding(string $string, mixed $enc=null): mixed
     {

         static $list = array('utf-8', 'iso-8859-1', 'windows-1251', 'macintosh');

         foreach ($list as $item) {
             $sample = iconv($item, $item, $string);
             if (md5($sample) == md5($string)) {
                 if ($enc == $item) { return true; }    else { return $item; }
             }
         }
         return null;
     }
 }


 /**
  * Tries to lock file, waits if it's locked (if $blocking = true)
  * @param $fp
  * @param bool $exclusive
  * @param string $filename
  * @param bool $blocking
  * @return void
  * @throws Exception
  */
 function awaitFileLock($fp, bool $exclusive = false, string $filename = '', bool $blocking = true): void
{
    $lockType = $exclusive? LOCK_EX:LOCK_SH;
    if ($blocking) {
        if ($blocking === true) {
            $blocking = FILE_BLOCKING_MAX_TIME / FILE_BLOCKING_CYCLE_TIME;
        }
        while (!flock($fp, $lockType) && ($blocking--)) {
            usleep(FILE_BLOCKING_CYCLE_TIME);
        }
        if (!$blocking) {
            throw new \Exception("Failed to lock '$filename'");
        }
    } else {
        if (!flock($fp, $lockType)) {
            throw new \Exception("Failed to lock '$filename'");
        }
    }
} // awaitFileLock


 /**
  * Waits until file gets released by other process.
  * @param mixed $fp
  * @param string $filename
  * @param mixed $blocking
  * @return bool
  * @throws Exception
  */
 function awaitFileUnlocked(mixed $fp, string $filename = '', mixed $blocking = true)
{
    if ($blocking) {
        if ($blocking === true) {
            $blocking = FILE_BLOCKING_MAX_TIME / FILE_BLOCKING_CYCLE_TIME;
        }
        while (isFileLocked($fp) && ($blocking--)) {
            usleep(FILE_BLOCKING_CYCLE_TIME);
        }
        if (!$blocking) {
            throw new \Exception("Waiting for '$filename' to be unlocked timed out");
        }
        return true;
    } else {
        return !isFileLocked($fp);
    }
} // awaitFileUnlocked


 /**
  * Checks whether file is locked.
  * @param $fp
  * @return bool
  */
 function isFileLocked(mixed $fp): bool
 {
    if (is_string($fp)) {
        if (!file_exists($fp)) {
            return false;
        }
        $fp = fopen($fp,"r");
        $locked = stream_get_meta_data($fp)['blocked'];
        fclose($fp);
        return (bool)$locked;
    } else {
        return (bool)stream_get_meta_data($fp)['blocked'];
    }
} // isFileLocked



 /**
  * Reads a csv-file and converts to an array.
  * @param string $file
  * @param bool $assoc
  * @return array
  */
 function readCsvFile(string $file, bool $assoc = false): array
{
    $array = [];
    if (file_exists($file)) {
        $str = F::read($file);
        $array = parseCsv($str);
    }
    if ($assoc && $array) {
        $array2 = [];
        $headers = array_shift($array);
        foreach ($array as $rec) {
            $array2[] = array_combine($headers, $rec);
        }
        return $array2;
    } else {
        return $array;
    }
} // readCsvFile


 /**
  * Converts a CSV-string into an array
  * @param string $csv_string
  * @param string $delimiter
  * @return array
  */
function parseCsv (string $csv_string, mixed $delimiter = false): array
{
    if (!$delimiter) {
        $delimiter = (substr_count($csv_string, ',') > substr_count($csv_string, ';')) ? ',' : ';';
        $delimiter = (substr_count($csv_string, $delimiter) > substr_count($csv_string, "\t")) ? $delimiter : "\t";
    }
    $enc = decodeCharset($csv_string);
    $lines = preg_split('/( *\R)+/s', $enc);
    $lines = array_filter($lines);
    return array_map(
        function ($line) use ($delimiter) {
            $fields = array_map('trim', explode($delimiter, $line));
            return array_map(
                function ($field) {
                    if (preg_match('/^ (["\']) (.*) \1 $/x', $field, $m)) {
                        $field = $m[2];
                    }
                    $field = preg_replace('/(?<!")""/', '"', $field);
                    return $field;
                },
                $fields
            );
        },
        $lines
    );
} // parseCsv


 /**
  * Re-codes given string to UTF-8. If $textEncoding is not defined, tries to guess the correct charset.
  * @param string $str
  * @param mixed $textEncoding
  * @return string
  */
 function decodeCharset(string $str, mixed $textEncoding = true): string
{
    if ($textEncoding) {
        if ($textEncoding !== true) {
            $str = iconv($textEncoding, 'UTF-8', $str);
        } else { // try to auto-detect:
            $encoding = mb_detect_encoding($str) ?: 'macintosh';
            if ($encoding !== 'UTF-8') {
                $str = iconv($encoding, 'UTF-8', $str);
            }
        }
    }
    return $str;
} // decodeCharset


 /**
  * Takes a path and creates corresponding folders/subfolders if they don't exist. Applies access writes if given.
  * @param string $path0
  * @param false $accessRights
  * @throws Exception
  */
function preparePath(string $path0, $accessRights = false): void
{
    // resolve path if necessary:
    if ($path0 && ($path0[0] === '~')) {
        $path0 = resolvePath($path0);
    }

    // check for inappropriate path, e.g. one attempting to point to an ancestor directory:
    if (strpos($path0, '../') !== false) {
        $path0 = normalizePath($path0);
        if (strpos($path0, '../') !== false) {
            mylog("=== Warning: preparePath() trying to access inappropriate location: '$path0'");
            return;
        }
    }

    // make folder(s) if necessary:
    $path = dirname($path0.'x');
    if (!file_exists($path)) {
        $accessRights1 = $accessRights ? $accessRights : PFY_MKDIR_MASK;
        try {
            mkdir($path, $accessRights1, true);
        } catch (Exception $e) {
            throw new Exception("Error: failed to create folder '$path'");
        }
    }

    // apply access rights if requested:
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
     $path = str_replace('/./', '/', $path);
     $path = preg_replace('|(?<!:)//|', '/', $path);
     return $hdr.$path;
} // normalizePath


 /**
  * Simple log function for quick&dirty testing
  * @param string $str
  * @throws Exception
  */
function mylog(string $str, mixed $filename = false): void
{
    $filename = $filename?: 'log.txt';

    if (!\Kirby\Toolkit\V::filename($filename)) {
        return;
    }
    $logFile = PFY_LOGS_PATH. $filename;
    $logMaxWidth = 60;

    if ((strlen($str) > $logMaxWidth) || (strpos($str, "\n") !== false)) {
        $str = log_wordwrap($str, $logMaxWidth);
    }
    $str = timestampStr()."  $str\n\n";
    writeFile($logFile, $str, FILE_APPEND);
} // mylog


 /**
  * @param string $string
  * @param int $width
  * @return string
  */
 function log_wordwrap(string $str, int $width=75): string
 {
     if (strlen($str) <= $width) {
         return $str;
     }

     $pattern = '/(.{1,'.$width.'})(?:[\s,])|(.{'.$width.'})(?!$)/uS';
     $str = preg_replace($pattern, "$1$2\n                     ", $str);
     return $str;
 } // log_wordwrap



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
  * @param mixed $superBrackets
  * @return array
  * @throws InvalidArgumentException
  */
function parseArgumentStr(string $str, string $delim = ',', mixed $superBrackets = false): array
{
    // terminate if string empty:
    if (!($str = trim($str))) {
        return [];
    }

    // skip '{{ ... }}' to avoid conflict with '{ ... }':
    if (preg_match('/^\s* {{ .* }} \s* $/x', $str)) {
        return [ $str ];
    }

    if (preg_match('/^\s* { .* } \s* $/x', $str, $m)) {
        $str = $m[1];
    }

    // otherwise, interpret as 'relaxed Yaml':
    // (meaning: first elements may come without key, then they are interpreted by position)
    $rest = ltrim($str, ", \n");
    $rest = rtrim($rest, ", \n)");

    if (preg_match('/^(.*?) \)\s*}}/msx', $rest, $mm)) {
        $rest = rtrim($mm[1], " \t\n");
    }

    $json = '';
    $counter = 100;
    $index = 0;
    while ($rest && ($counter-- > 0)) {
        $key = parseArgKey($rest, $delim);
        $ch = ltrim($rest);
        $ch = $ch[0]??'';
        if ($ch !== ':') {
            $json .= "\"$index\": $key,";
            $rest = ltrim($rest, " $delim\n");
        } else {
            $rest = ltrim(substr($rest, 1));
            $value = parseArgValue($rest, $delim);
            $json .= "$key: $value,";
        }
        $rest = ltrim($rest);
        $index++;
    }

    $json = rtrim($json, ',');
    $json = '{'.$json.'}';
    $options = json_decode($json, true);
    if ($options === null) {
        throw new Exception("Error in argument list: \"$str\"");
    }

    return $options;
} // parseArgumentStr


 /**
  * Parses the key part of 'key: value'
  * @param string $rest
  * @param string $delim
  * @return string
  */
function parseArgKey(string &$rest, string $delim): string
{
    $key = '';
    $rest = ltrim($rest);
    // case quoted key or value:
    if ((($ch1 = ($rest[0]??'')) === '"') || ($ch1 === "'")) {
        $pattern = "$ch1 (.*?) $ch1";
        // case 'value' without key:
        if (preg_match("/^ ($pattern) (.*)/xms", $rest, $m)) {
            $key = $m[2];
            $rest = $m[3];
        }

    // case naked key or value:
    } else {
        // case value without key:
        if (preg_match('|^(https?://\S*)(.*)|', $rest, $m)) {
            $key = $m[1];
            $rest = $m[2];
        } else {
            $pattern = "[^$delim\n:]+";
            if (preg_match("/^ ($pattern) (.*) /xms", $rest, $m)) {
                $key = $m[1];
                $rest = $m[2];
            }
        }
    }
    $key = preg_replace('/(?<!\\\)"/', '\\"', $key);
    return "\"$key\"";
} // parseArgKey

 /**
  * Parses the value part of 'key: value'
  * @param string $rest
  * @param string $delim
  * @return string
  */
function parseArgValue(string &$rest, string $delim): mixed
{
    // case quoted key or value:
    $value = '';
    $ch1 = ltrim($rest);
    $ch1 = $ch1[0]??'';
    if (($ch1 === '"') || ($ch1 === "'")) {
        $rest = ltrim($rest);
        $pattern = "$ch1 (.*?) (?<!\\\)$ch1";
        // case 'value' without key:
        if (preg_match("/^ ($pattern) (.*)/xms", $rest, $m)) {
            $value = $m[2];
            $rest = ltrim($m[3], ', ');
        }

    // case string wrapped in {} -> assume it's relaxed Json:
    } elseif ($ch1 === '{') {
        $p = strPosMatching($rest, 0, '{', '}');
        $value = substr($rest, $p[0]+1, $p[1]-$p[0]-1);
        $rest = ltrim(substr($rest, $p[1]+1), ",\n\t ");
        $value = json_encode(parseArgumentStr($value));
        return $value;

    } else {
        // case value without key:
        $pattern = "[^$delim\n]+";
        if (preg_match("/^ ($pattern) (.*) /xms", $rest, $m)) {
            $value = $m[1];
            $rest = ltrim($m[2], ', ');
        }
    }
    $value = fixDataType($value);
    if (is_string($value)) {
        // if value contains variable, translate it:
        if (str_contains($value, '{{')) {
            $value = TransVars::translate($value);
        }
        $value = preg_replace('/(?<!\\\)"/', '\\"', $value);
        $value = '"' . trim($value) . '"';
    } elseif (is_bool($value)) {
        $value = $value? 'true': 'false';
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
     if (!$str || ($p0 === null) || (strlen($str) < $p0)) {
         return [false, false];
     }

     // simple case: both patterns are equal -> no need to check for nested patterns:
     if ($pat1 === $pat2) {
         $p1 = strpos($str, $pat1);
         $p2 = strpos($str, $pat1, $p1+1);
         return [$p1, $p2];
     }

     // opening and closing patterns -> need to check for nested patterns:
     checkBracesBalance($str, $p0, $pat1, $pat2);

     $d = strlen($pat2);
     if ((strlen($str) < (strlen($pat1)+$d)) || ($p0 > strlen($str))) {
         return [false, false];
     }

     if (!checkNesting($str, $pat1, $pat2)) {
         return [false, false];
     }

     $p1 = $p0 = findNextPattern($str, $pat1, $p0);
     if ($p1 === false) {
         return [false, false];
     }
     $cnt = 0;
     do {
         $p3 = findNextPattern($str, $pat1, $p1+$d); // next opening pat
         $p2 = findNextPattern($str, $pat2, $p1+$d); // next closing pat
         if ($p2 === false) { // no more closing pat
             return [false, false];
         }
         if ($cnt === 0) {	// not in next structure
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
function findNextPattern(string $str, string $pat, mixed $p1 = 0): mixed
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
    $literal = $inline = 0;
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

    if (preg_match_all('/([=!\w-]+) : ([^\s;,]+) ;?/x', $str, $m)) {
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
                $tag = rtrim($arg1, '>');
                break;
            case '#':
                $id = $arg1;
                break;
            case '.':
                $arg1 = str_replace('.', ' ', $arg1);
                $class = $class? "$class $arg1" : $arg1;
                break;
            case '!':
                _parseMetaCmds($arg1, $lang, $literal, $inline, $style, $tag);
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
    if ($literal === 0) {
        $literal = null;
    }
    if ($inline === 0) {
        $inline = null;
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
        'inline' => $inline,
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
  * @param bool $inline
  * @param string $style
  * @param string $tag
  */
function _parseMetaCmds(string $arg, string &$lang, &$literal, &$inline, string &$style, string &$tag): void
{
    if (preg_match('/^([\w-]+) [=:]? (.*) /x', $arg, $m)) {
        $arg = strtolower($m[1]);
        $param = $m[2];
        if ($arg === 'literal') {
            $literal = true;
        } elseif ($arg === 'inline') {
            $inline = true;
        } elseif ($arg === 'lang') {
            $lang = $param;
            if (($lang === 'skip') || ($lang === 'none') || ($lang !== PageFactory::$langCode)) {
                $tag = 'skip';
                $style = $style? " $style display:none;" : 'display:none;';
            }
        } elseif (($arg === 'off') || (($arg === 'visible') && ($param !== 'true')))  {
            $style = $style? " $style display:none;" : 'display:none;';
        } elseif ($arg === 'showtill') {
            $t = strtotime($param) - time();
            if ($t < 0) {
                $lang = 'none';
                $tag = 'skip';
                $style = $style? " $style display:none;" : 'display:none;';
            }
        } elseif ($arg === 'showfrom') {
            $t = strtotime($param) - time();
            if ($t > 0) {
                $lang = 'none';
                $tag = 'skip';
                $style = $style? " $style display:none;" : 'display:none;';
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
function compileMarkdown(string $mdStr, bool $omitPWrapperTag = false): string
{
    return markdown($mdStr, $omitPWrapperTag);
} // compileMarkdown


 /**
  * @param string $mdStr
  * @param bool $omitPWrapperTag
  * @return string
  * @throws Exception
  */
 function markdown(string $mdStr, bool $omitPWrapperTag = false, $sectionIdentifier = '', $removeComments = false): string
{
    if ($mdStr) {
        if (class_exists('Usility\MarkdownPlus\MarkdownPlus')) {
            $md = new MarkdownPlus();
            return $md->compile($mdStr, $omitPWrapperTag, $sectionIdentifier, $removeComments);
        } else {
            return kirbytext($mdStr);
        }
    } else {
        return '';
    }
} // compileMarkdown


 /**
  * @param string $mdStr
  * @param bool $omitPWrapperTag
  * @return string
  * @throws Exception
  */
 function markdownParagrah(string $mdStr, bool $omitPWrapperTag = false): string
{
    if ($mdStr) {
        if (class_exists('Usility\MarkdownPlus\MarkdownPlus')) {
            $md = new MarkdownPlus();
            return $md->compileParagraph($mdStr, $omitPWrapperTag);
        } else {
            return kirbytextinline($mdStr);
        }
    } else {
        return '';
    }
} // compileMarkdown


/**
* Shields a string from the markdown compiler, optionally instructing the unshielder to run the result through
* the md-compiler separately.
* @param string $str
* @param bool $mdCompile
* @return string
*/
 function shieldStr(string $str, mixed $options = false): string
 {
     if (($options[0]??'') === 'm') {
         return '<'.MD_SHIELD.'>' . base64_encode($str) . '</'.MD_SHIELD.'>';
     } elseif (($options[0]??'') === 'i') {
         return '<'.INLINE_SHIELD.'>' . base64_encode($str) . '</'.INLINE_SHIELD.'>';
     } else {
         return '<'.BLOCK_SHIELD.'>' . base64_encode($str) . '</'.BLOCK_SHIELD.'>';
     }
 } // shieldStr



/**
* Un-shields shielded strings, optionally running the result through the md-compiler
* @param string $str
* @return string
*/
function unshieldStr(string $str, bool $unshieldLiteral = null): string
{
    // pseudo-tags <INLINE_SHIELD>,<BLOCK_SHIELD> and <MD_SHIELD> may be (partially) translated, fix them:
    $str = preg_replace('#(&lt;|<)(/?('.INLINE_SHIELD.'|'.BLOCK_SHIELD.'|'.MD_SHIELD.'))(&gt;|>)#', "<$2>", $str);

    if ($unshieldLiteral !== false) {
        // patters <INLINE_SHIELD>,<BLOCK_SHIELD>
        if (preg_match_all('/<('.INLINE_SHIELD.'|'.BLOCK_SHIELD.')>(.*?)<\/('.INLINE_SHIELD.'|'.BLOCK_SHIELD.')>/m', $str, $m)) {
            foreach ($m[2] as $i => $item) {
                $literal = base64_decode($m[2][$i]);
                $str = str_replace($m[0][$i], $literal, $str);
            }
        }
    }
    if (preg_match_all('|<'.MD_SHIELD.'>(.*?)</'.MD_SHIELD.'>|m', $str, $m)) {
        foreach ($m[1] as $i => $item) {
            $md = base64_decode($m[1][$i]);
            $html = compileMarkdown($md);
            $str = str_replace($m[0][$i], $html, $str);
        }
    }
    return $str;
} // unshieldStr



 /**
  * Un-shields shielded strings -> variant returning 'modified'
  * @param string $str
  * @return bool
  */
function _unshieldStr(string &$str, bool $unshieldLiteral = false): bool
{
    $str0 = $str;
    $str = unshieldStr($str, $unshieldLiteral);
    return ($str0 !== $str);
} // _unshieldStr



 /**
  * Converts character to HTML unicode representation, e.g. to hide it from markdown compilation
  * @param string $char
  * @return string
  */
function charToHtmlUnicode(string $char): string
{
    if (!$char) {
        return '';
    }
    return '&#'.ord($char[0]).substr($char,1).';';
} // charToHtmlUnicode


 /**
  * Converts HTML-encoded Unicode characters back to Unicode, e.g. '&%123;' -> '{'
  * @param string $str
  * @return array|string|string[]|null
  */
 function unshieldCharacters(string $str): string
 {
    $output = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
        }, $str);
    return $output;
} // unshieldCharacters


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
function translateToFilename(string $str, mixed $appendExt = true): string
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
  * @param bool $toCamelCase
  * @param bool $removeNonAlpha
  * @param bool $toLowerCase
  * @return string
  */
function translateToIdentifier(string $str, bool $toCamelCase = false, bool $removeNonAlpha = false,
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
    if ($toCamelCase) {
        $str = str_replace(['-','_'], '', ucwords($str, '-'));
        $str = lcfirst($str);
    }
    $str = trim($str, '_');
    return $str;
} // translateToIdentifier


/**
 * Translates a given string to a legal class name or id
 * @param string $str
 * @param bool $handleLeadingNonChar
 * @return string
 */
function translateToClassName(string $str, bool $handleLeadingNonChar = true): string
{
    $str = strip_tags($str);					// strip any html tags
    $str = strToASCII(mb_strtolower($str));		// replace special chars
    $str = preg_replace(['|[./]|', '/\s+/'], '-', $str);		// replace blanks, '.' and '/' with '-'
    $str = preg_replace("/[^[:alnum:]_-]/m", '', $str);	// remove any non-letters, except '_' and '-'
    if ($handleLeadingNonChar && !preg_match('/[a-z]/', ($str[0]??''))) { // prepend '_' if first char non-alpha
        $str = "_$str";
    }
    return $str;
} // translateToClassName


 /**
  * @param string $str
  * @return string
  */
 function camelCase(string $str): string
{
    $str = str_replace(['-','_'], '', ucwords(str_replace(' ','-', $str), '-'));
    return lcfirst($str);
}    // camelCase


 /**
  * Converts an array or object to a more or less readable string.
  * @param $var
  * @param string $varName
  * @param bool $flat
  * @return string
  */
function var_r($var, string $varName = '', bool $flat = false, bool $toHtml = false): string
{
    if (!$var) {
        return '';
    }
    if ($varName) {
        $varName .= ': ';
    }

    if (is_scalar($var)) {
        $out = "$varName$var";

    } else {
        if (is_object($var) && is_a($var, '\Usility\PageFactory\DataSet')) {
            $var = removeSelfReferences($var);
        }

        if ($flat) {
            $out = preg_replace("/" . PHP_EOL . "/", '', var_export($var, true));
            if (preg_match('/array \((.*),\)/', $out, $m)) {
                $out = "[{$m[1]} ]";
            }
            if ($varName) {
                $out = "$varName$out";
            }
        } else {
            $out = $varName . var_export($var, true);
            $out = str_replace(["array (\n", "),\n", ")\n", '=>'], ["[\n", "],\n", "]\n", '\=>'], $out);
            $out[strlen($out)-1] = ']';
        }
    }
    if ($toHtml) {
        $out = "<div><pre>$out\n</pre></div>\n";
    }
    return $out;
} // var_r


 /**
  * (Experimental) Removes refernce to self in a data structure.
  * Possibly used in DataSet
  * @param mixed $var
  * @param mixed $thisClass
  * @return mixed
  */
 function removeSelfReferences(mixed $var, mixed $thisClass = null): mixed
{
    if ($thisClass === null) {
        $thisClass = is_object($var) ? get_class($var) : false;
    }
    foreach ($var as $key => $value) {
        $className = is_object($value) ? get_class($value) : false;
        if ($className === $thisClass) {
            $var->$key = '[SELF]';
        } elseif (!is_scalar($value)) {
            if (is_object($var)) {
                $var->$key = removeSelfReferences($value, $thisClass);
            } else {
                $var[$key] = removeSelfReferences($value, $thisClass);
            }
        }
    }
    return $var;
} // removeSelfReferences


 /**
  * Forces the agent (browser) to reload the page, optionally setting up a message to be displayed on next view
  * @param mixed  $target
  * @param string  $message   if set, text will be briefly shown in message banner
  */
function reloadAgent(mixed $target = '', string $message = ''): void
{
    if (!$target) {
        $target = page()->url();
    }
    if ($message) {
        if (str_contains($message, '{{')) {
            $message = TransVars::translate($message);
        }
        $session = kirby()->session();
        $session->set('pfy.message', $message);
    }
    header("Location: $target");
    exit;
} // reloadAgent


 /**
  * Converts string to a pixel value, e.g. '1em' -> 12[px]
  * @param string $str
  * @return float
  */
function convertToPx(string $str): float
{
     $px = 0;
     if (preg_match('/([\d.]+)(\w*)/', $str, $m)) {
         $unit = $m[2];
         $value = floatval($m[1]);
         switch ($unit) {
             case 'in':
                $px = 96 * $value; break;
             case 'cm':
                $px = 37.7952755906 * $value; break;
             case 'mm':
                $px = 3.779527559 * $value; break;
             case 'em':
                $px = 12 * $value; break;
             case 'ch':
                $px = 6 * $value; break;
             case 'pt':
                $px = 1.3333333333 * $value; break;
             case 'px':
                $px = $value; break;
         }
     }
    return $px;
} // convertToPx


 /**
  * Clears PageFactory's cache folder, also delete Kirby's media/ folder.
  * @return void
  */
function clearCache(): void
{
    rrmdir(PFY_CACHE_PATH);
    rrmdir('media');
} // clearCache


 /**
  * Remove a folder recursively, even if not empty.
  * @param string $dir
  * @return bool
  */
 function rrmdir(string $dir): bool
{
    if (!file_exists($dir)) {
        return false;
    }
     $files = array_diff(scandir($dir), ['.','..']);
     foreach ($files as $file) {
         (is_dir("$dir/$file") &&
             !is_link($dir)) ? rrmdir("$dir/$file") : unlink("$dir/$file");
     }
     return rmdir($dir);
} // rrmdir


 /**
  * Returns the time since PageFactory started its execution.
  * @param bool $verbose
  * @return float
  */
 function readTimer(): float
 {
     return round(((microtime(true) - PageFactory::$timer)*1000000) / 1000 - 0.005, 1);
 } // readTimer


 /**
  * Just forwards to Exception - better to use "throw new Exception($str)" directly
  * @param string $str
  * @return void
  * @throws Exception
  */
function fatalError(string $str): void
{
    throw new Exception($str);
} // fatalError



 /**
  * Renders an icon
  * @param string $iconName
  * @param string $class
  * @return string
  */
function renderIcon(string $iconName, string $class = 'pfy-icon'): string
{
    $iconFile = PageFactory::$availableIcons[$iconName] ?? false;
    if (!$iconFile || !file_exists($iconFile)) {
        throw new \Exception("Error: icon '$iconName.svg' not found.");
    }

    if (fileExt($iconFile) === 'svg') {
        $icon = "<span class='$class'>".svg($iconFile).'</span>';
    } else {
        $icon = "<span class='$class'><img src='$iconFile' alt=''></span>";
    }
    return $icon;
} // renderIcon



 /**
  * Checks whether icon-name can be converted
  * @param string $iconName
  * @return bool
  */
function iconExists(string $iconName): bool
{
    $iconFile = PageFactory::$availableIcons[$iconName] ?? false;
    if (!$iconFile || !file_exists($iconFile)) {
        return false;
    }
    return true;
} // iconExists


 /**
  * Creates a new hash string of given length. First character always a letter.
  * @param int $hashSize
  * @param bool $unambiguous  -> if true, uses only letters that are unlikely to misread
  * @param bool $lowerCase
  * @return string
  * @throws Exception
  */
 function createHash(int $hashSize = 8, bool $unambiguous = false, bool $lowerCase = false ): string
 {
     if ($unambiguous) {
         $chars = UNAMBIGUOUS_CHARACTERS;
         $max = strlen(UNAMBIGUOUS_CHARACTERS) - 1;
         $hash = $chars[ random_int(4, $max) ];  // first always a letter
         for ($i=1; $i<$hashSize; $i++) {
             $hash .= $chars[ random_int(0, $max) ];
         }

     } else {
         $hash = chr(random_int(65, 90));  // first always a letter
         $hash .= strtoupper(substr(sha1(random_int(0, PHP_INT_MAX)), 0, $hashSize - 1));  // letters and digits
     }
     if ($lowerCase) {
         $hash = strtolower( $hash );
     }
     return $hash;
 } // createHash


function isHash(string $str): bool
{
     $isHash = preg_match('/[A-Z][A-Z0-9]{4,20}]/', $str);
     return $isHash;
} // isHash



 /**
  * Returns the current session ID
  * @return string
  */
 function getSessionId(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        $sessionId = session_id();
        session_abort();
    } else {
        $sessionId = session_id();
    }
    return $sessionId;
} // getSessionId


 /**
  * Variant of array_splice() which preserves keys of replacement array.
  * @param array $input
  * @param int $offset
  * @param int $length
  * @param array $replacement
  * @return void
  */
 function array_splice_assoc(array &$input, int $offset, int $length, array $replacement): void
 {
     $replacement = (array) $replacement;
     $keyIndices = array_flip(array_keys($input));
     if (isset($input[$offset]) && is_string($offset)) {
         $offset = $keyIndices[$offset];
     }
     if (isset($input[$length]) && is_string($length)) {
         $length = $keyIndices[$length] - $offset;
     }

     $input = array_slice($input, 0, $offset, TRUE)
         + $replacement
         + array_slice($input, $offset + $length, NULL, TRUE);
 } // array_splice_assoc


 /**
  * @param string $key
  * @param bool $asString
  * @return mixed
  */
 function getStaticUrlArg(string $key, bool $asString = false): mixed
{
    $value = null;
    if (isset($_GET[$key])) {
        $value = $_GET[$key];
        if (!$asString) {
            $value = ($value !== 'false');
        }
        PageFactory::$session->set("pfy.$key", $value);
    } else {
        $value = PageFactory::$session->get("pfy.$key");
        if ($value) {
            return $value;
        }
    }
    return $value;
} // getStaticUrlArg


 /**
  * @param string $value
  * @return mixed
  */
 function fixDataType(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }

    if ($value === '0') { // we must check this before empty because zero is empty
        return 0;
    }

    if (empty($value)) {
        return '';
    }

    if ($value === 'null') {
        return null;
    }

    if ($value === 'undefined') {
        return null;
    }

    if ($value === '1') {
        return 1;
    }

    if (!preg_match('/[^0-9.]+/', $value)) {
        if(preg_match('/[.]+/', $value)) {
            return (double)$value;
        }else{
            return (int)$value;
        }
    }

    if ($value == 'true') {
        return true;
    }

    if ($value == 'false') {
        return false;
    }

    return (string)$value;
} // fixDataType

