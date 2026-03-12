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
        $path1 = urldecode(substr($path, strlen('download/')));
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
            $basename = self::translateToFilename(basename($path));
            $filename = "media/download/$basename.zip";
            $destFile = PFY_KIRBY_BASE_PATH . $filename;
            self::zipFolder($path, $destFile);
            $path = $destFile;
        }

        return self::downloadFile($path);
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
        if ($zip->open($destFile, ZipArchive::CREATE) !== true) {
            return false;
        }
        $basePath = rtrim($path, '/') . '/';
        $files = self::getFilesRecursive($basePath);
        foreach ($files as $file) {
            $relativePath = substr($file, strlen($basePath));
            $zip->addFile($file, $relativePath);
        }
        $zip->close();
        return true;
    } // zipFolder


    /**
     * @param string $path
     * @return bool
     */
    private static function downloadFile(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
        $filename = basename($path);
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-cache, must-revalidate');
        readfile($path);
        return true;
    } // downloadFile


    /**
     * Translates a string into a filesystem-safe filename.
     * @param string $str
     * @return string
     */
    private static function translateToFilename(string $str): string
    {
        $str = self::strToASCII(trim(mb_strtolower($str)));
        $str = strip_tags($str);
        $str = str_replace([' ', '-', '/'], '_', $str);
        $str = preg_replace('/[^a-z0-9._\-]/', '', $str);
        $str = preg_replace('/\.+/', '.', $str);
        return $str;
    } // translateToFilename


    /**
     * Transliterate special characters to ASCII equivalents.
     * @param string $str
     * @return string
     */
    private static function strToASCII(string $str): string
    {
        $specChars = ['ä','ö','ü','Ä','Ö','Ü','é','â','á','à',
            'ç','ñ','Ñ','Ç','É','Â','Á','À','ẞ','ß','ø','å'];
        $replacements = ['ae','oe','ue','Ae',
            'Oe','Ue','e','a','a','a','c',
            'n','N','C','E','A','A','A',
            'SS','ss','o','a'];
        return str_replace($specChars, $replacements, $str);
    } // strToASCII


    /**
     * Creates the directory structure for the given file path.
     * @param string $filePath
     * @return void
     */
    private static function preparePath(string $filePath): void
    {
        if ($filePath && $filePath[0] === '~') {
            $filePath = Utils::resolvePath($filePath);
        }
        $dir = dirname($filePath);
        if (file_exists($dir)) {
            return;
        }
        try {
            mkdir($dir, 0755, true);
        } catch (\Exception $e) {
            throw new \Exception("Error: failed to create folder '$dir'");
        }
    } // preparePath


    /**
     * Recursively collects all files in a directory, excluding hidden files.
     * @param string $basePath
     * @return array
     */
    private static function getFilesRecursive(string $basePath): array
    {
        $files = [];
        $it = new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($it) as $fileInfo) {
            $pathname = $fileInfo->getPathname();
            if (preg_match('|/\.|', $pathname)) {
                continue;
            }
            $files[] = $pathname;
        }
        sort($files);
        return $files;
    } // getFilesRecursive

} // Download
