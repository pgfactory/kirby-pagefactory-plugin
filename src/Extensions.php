<?php

namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\PageElements;

class Extensions
{
    public static array $availableExtensions = [];
    public static array $loadedExtensions = [];
    public static array $loadedExtensionObjects = [];


    /**
     * @return void
     */
    public static function findExtensions()
    {
        $extensions = getDir(rtrim(PFY_PAGEFACTORY_PATH, '/').'-*');
        foreach ($extensions as $extension) {
            $extensionName = rtrim(substr($extension, strlen(PFY_APP_BASE_PATH)+25), '/');
            self::$availableExtensions[$extensionName] = $extension;
        }
    } // findExtensions


    /**
     * Loads extensions, i.e. plugins with names "pagefactory-*":
     */
    public static function loadExtensions(): void {
        // check for and load extensions:
        if (self::$availableExtensions) {
            foreach (self::$availableExtensions as $extPath) {
                // look for 'src/index.php' within the extension:
                $indexFile = "{$extPath}src/index.php";
                if (!file_exists($indexFile)) {
                    return;
                }

                // === load index.php to get extension's class name:
                $extensionClassName = require_once $indexFile;
                if (!is_string($extensionClassName)) {
                    return;
                }
                self::$loadedExtensions[$extensionClassName] = $extPath;

                // instantiate extension object:
                $extensionClass = "PgFactory\\PageFactoryElements\\$extensionClassName";
                if (!class_exists($extensionClass)) {
                    return;
                }

                $obj = new $extensionClass(); // -> initialize extension
                self::$loadedExtensionObjects[] = $obj;
            }
        }
    } // loadExtensions


    /**
     * Checks loaded extensions whether they contain a special file 'src/_finalCode.php' and executes it.
     * @return void
     */
    public static function extensionsFinalCode(): void
    {
        foreach (self::$loadedExtensions as $path) {
            $file = $path.'src/_finalCode.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    } // extensionsFinalCode


    /**
     * @return string
     */
    public static function showHelp(): string
    {
        $str = '';
        foreach (self::$loadedExtensionObjects as $obj) {
            $str .= $obj->showHelp();
        }
        return $str;
    } // extensionsFinalCode


    /**
     * @return void
     * @throws \Exception
     */
    public static function reset(): void
    {
        foreach (self::$loadedExtensions as $path) {
            $files = getDirDeep($path . '-*.css');
            foreach ($files as $file) {
                if ((basename($file)[0]) === '-') {
                    unlink($file);
                }
            }
        }

        // invoke reset() method of all loaded extensions:
        foreach (self::$loadedExtensionObjects as $obj) {
            if (method_exists($obj, 'reset')) {
                $obj->reset();
            }
        }
    } // reset

} // Extensions