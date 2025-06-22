<?php

namespace PgFactory\PageFactory;

use ZipArchive;

if (!defined('PFY_DOWNLOAD_PATH')) {
    define('PFY_DOWNLOAD_PATH', '~/download/');
}

class Download
{
    /**
     * @param string $path
     * @return bool
     */
    public static function handler(string $path): bool
    {
        $path1 = urldecode(substr($path, 9));
        $realLocations = kirby()->session()->get('pfy.realLocations');
        $downloadPermission = kirby()->session()->get('pfy.downloadPermission');
        if (!$downloadPermission) {
            return false;
        }
        if (!isset($realLocations[$path1])) {
            return false;
        }
        $path = $realLocations[$path1];

        if (is_dir($path)) {
            $basename = pathinfo($path, PATHINFO_BASENAME);
            $basename = self::translateToFilename($basename, false);
            $filename = "media/download/$basename.zip";
            $destFile = PFY_KIRBY_BASE_PATH. $filename;
            self::zipFolder($path, $destFile);
            $path = $destFile;
        }

        if (self::downloadFile($path)) {
            exit();
        }

        return false;
    } // handler


    /**
     * @param string $path
     * @param string $destFile
     * @return bool
     */
    private static function zipFolder(string $path, string $destFile): bool
    {
        if (file_exists($destFile)) {
            return true;
        }
        self::preparePath($destFile);
        $zip = new ZipArchive;
        if ($zip->open($destFile, ZipArchive::CREATE) === true) {
            $files = self::getDirDeep($path.'/*');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            return true;
        } else {
            return false;
        }
    } // zipFolder


    /**
     * @param $path
     * @return bool
     */
    private static function downloadFile($path): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            header('Content-type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header("Content-Length: " . filesize($path));
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');

        } elseif ($ext === 'txt') {
            header('Content-type: text/plain'); // works for txt only
        } else {
            header("Content-Disposition: attachment; filename=\"" . basename($path) . "\"");
            header('Content-type: application/'.$ext);
            header("Content-Length: " . filesize($path));
            header("Connection: close");
        }
        readfile($path);
        return true;
    } // downloadPDF


    /**
     * @param string $str
     * @param mixed $appendExt
     * @return string
     */
    private static function translateToFilename(string $str, mixed $appendExt = true): string
    {
        // translates special characters (such as , , ) into "filename-safe" non-special equivalents (a, o, U)
        $str = self::strToASCII(trim(mb_strtolower($str)));	// replace special chars
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
     * @param string $str
     * @return string
     */
    private static function strToASCII(string $str): string
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
     * @param string $path0
     * @return void
     */
    private static function preparePath(string $path0): void
    {
        $accessRights = 0755;
        // resolve path if necessary:
        if ($path0 && ($path0[0] === '~')) {
            $path0 = resolvePath($path0);
        }

        if (file_exists(dirname($path0))) {
            return; // nothing to do
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
        $path = substr($path, strlen(PFY_KIRBY_BASE_PATH));
        $path1 = PFY_KIRBY_BASE_PATH;
        foreach (explode('/', $path) as $p) {
            $path1 .= "$p/";
            try {
                chmod($path1, $accessRights);
            } catch (Exception $e) {
                throw new Exception("Error: failed to create folder '$path'");
            }
        }
    } // preparePath


    /**
     * @param string $path
     * @param bool $onlyDir
     * @param bool $assoc
     * @param bool $returnAll
     * @return array
     */
    private static function getDirDeep(string $path, bool $onlyDir = false, bool $assoc = false, bool $returnAll = false): array
    {
        $files = [];
        $inclPat = pathinfo($path, PATHINFO_BASENAME);
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
                    if ($assoc) {
                        $f = basename(rtrim($p, '/.'));
                        $files[$f] = rtrim($p, '.');
                    } else {
                        $files[] = rtrim($p, '.');
                    }
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
        if ($assoc) {
            ksort($files);
        } else {
            sort($files);
        }
        return $files;
    } // getDirDeep

} // Downloader