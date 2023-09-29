<?php

namespace PgFactory\PageFactory;

class Extensions
{
    public static array $availableExtensions = [];
    public static array $loadedExtensions = [];
    public static array $loadedExtensionObjects = [];


    public static function findExtensions()
    {
        $extensions = getDir(rtrim(PFY_BASE_PATH, '/').'-*');
        foreach ($extensions as $extension) {
            $extensionName = rtrim(substr($extension, 25), '/');
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
                // === load index.php now:
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
                $obj = new $extensionClass();
                self::$loadedExtensionObjects[] = $obj;

                // check for and load extension's asset-definitions:
                if (method_exists($obj, 'getAssetDefs')) {
                    $newAssets = $obj->getAssetDefs();
                    Page::$definitions = array_merge_recursive(Page::$definitions, ['assets' => $newAssets]);
                }

                // check for and load extension's asset-definitions:
                if (method_exists($obj, 'getAssetGroups')) {
                    $newAssetGroupss = $obj->getAssetGroups();
                    PageFactory::$assets->addAssetGroups($newAssetGroupss);
                }

                // load extension's variables:
                $files = getDir($extPath.'variables/');
                if (is_array($files)) {
                    foreach ($files as $file) {
                        TransVars::loadVariables($file, doTranslate: true);
                    }
                }

                // check for and load extension's url-request handlers:
                if (method_exists($obj, 'handleUrlRequests')) {
                    $obj->handleUrlRequests();
                }
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

} // Extensions