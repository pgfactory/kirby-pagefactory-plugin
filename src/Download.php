<?php

namespace PgFactory\PageFactory;

use ZipArchive;

const PFY_MIME_TYPES = [
    // images
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    // misc
    'txt' => 'text/plain',
    'html' => 'text/html',
    'csv' => 'text/csv',
    'zip' => 'application/zip',
    //'json' => 'application/json',
    // docs
    'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'odt' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ott' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    // spreadsheets
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ods' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ots' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    // presentations
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'odp' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'otp' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    // audio
    'mp4' => 'video/mp4',
];

if (!defined('PFY_TEMP_DOWNLOAD_PATH')) {
    define('PFY_TEMP_DOWNLOAD_PATH', '~/tmp/download/');
}

class Download
{
    /**
     * Downloads are restricted to either PFY_TEMP_DOWNLOAD_PATH or a path specified in the session var pfy.permittedDownloadPath.
     * Moreover, download is checked for permission defined in session var pfy.downloadPermission or default 'localhost|loggedin'.
     * @param string $path
     * @return bool
     */
    public static function handler(): void
    {
        if (!($_GET['download']??false)) {
            return;
        }
        // handle download requests:
        $permittedPath = kirby()->session()->get('pfy.permittedDownloadPath', PFY_TEMP_DOWNLOAD_PATH);
        if (!is_dir($permittedPath)) {
            return;
        }
        $downloadPermission = kirby()->session()->get('pfy.downloadPermission', 'localhost|loggedin');
        $file = urldecode($_GET['download']);
        $files = getDirDeep($permittedPath, assoc:true);
        if (in_array(basename($file), array_keys($files))) {
            $file = $permittedPath . $file;
            if (is_dir($file)) {
                self::zipFolder($file, $file . '.zip');
            }
            Download::initiateDownload($file, $downloadPermission);
        }
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
     * @return void
     */
    public static function setupDownloadFolder(string $path = PFY_TEMP_DOWNLOAD_PATH): void
    {
        $path = Utils::resolvePath($path);
        $path = dir_name($path);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            file_put_contents("$path.htaccess", "Deny from all\n");
        }
    } // setupDownloadFolder


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


    /**
     * @param $file
     * @param $accessCritearia
     * @return void
     */
    public static function initiateDownload($file, $accessCritearia = 'loggedin|localhost')
    {
        $file = urldecode($file);
        if (($file[0]??'') === '~') {
            $file = Utils::resolvePath($file);
        }
        if (!is_file($file)) {
            http_response_code(404);
            exit('File not found');
        }
        if (!\PgFactory\MarkdownPlus\Permission::evaluate($accessCritearia)) {
            http_response_code(403);
            exit('Insuffucient permissions to access this file.');
        }

        $path_parts = pathinfo($file);
        $ext = strtolower($path_parts['extension']);
        $mimeType = PFY_MIME_TYPES[$ext] ?? false;
        if (!$mimeType) {
            http_response_code(404);
            exit('File format error');
        }

        // prepare HTTP header:
        if ($ext === 'pdf') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            header('Accept-Ranges: bytes');

        } elseif ($ext === 'txt') {
            header('Content-Type: text/plain');
            header('Content-Disposition: inline; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            header('Accept-Ranges: bytes');

        } else {

            header('Content-Description: File Transfer');
            header("Content-Type: $mimeType");
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
        }

        ob_end_flush();
        exit(file_get_contents($file));
    } // initiateDownload

} // Download
