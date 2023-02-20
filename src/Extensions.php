<?php

namespace Usility\PageFactory;

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
//        if (PageFactory::$availableExtensions) {
//            foreach (PageFactory::$availableExtensions as $extPath) {
                // look for 'src/index.php' within the extension:
                $indexFile = "{$extPath}src/index.php";
                if (!file_exists($indexFile)) {
                    return;
                }
                // === load index.php now:
                $extensionClassName = require_once $indexFile;
                self::$loadedExtensions[$extensionClassName] = $extPath;
//                PageFactory::$loadedExtensions[$extensionClassName] = $extPath;

                // instantiate extension object:
                $extensionClass = "Usility\\PageFactoryElements\\$extensionClassName";
                $obj = new $extensionClass();
                self::$loadedExtensionObjects[] = $obj;
//                $obj = new $extensionClass($this->pfy);

                // check for and load extension's asset-definitions:
                if (method_exists($obj, 'getAssetDefs')) {
                    $newAssets = $obj->getAssetDefs();
                    Page::$definitions = array_merge_recursive(Page::$definitions, ['assets' => $newAssets]);
//                    self::$definitions = array_merge_recursive(self::$definitions, ['assets' => $newAssets]);
                }

                // check for and load extension's asset-definitions:
                if (method_exists($obj, 'getAssetGroups')) {
                    $newAssetGroupss = $obj->getAssetGroups();
//                    Page::$assets->addAssetGroups($newAssetGroupss);
                    PageFactory::$assets->addAssetGroups($newAssetGroupss);
                }

//                $file = $extPath.'src/_postProcessing.php';
//                if (file_exists($file)) {
//                    require_once $file;
//                }

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
//        foreach (PageFactory::$loadedExtensions as $path) {
            $file = $path.'src/_finalCode.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    } // extensionsFinalCode



//    public static function postProcessMd(string $html, $inx = null, $wrapperTag = null, $wrapperClass = null): array
//    {
//        $topWrapperClass = '';
//        foreach (self::$loadedExtensionObjects as $obj) {
//            if (method_exists($obj, 'postProcessMd')) {
//                list($html, $cls, $inx) = $obj::postProcessMd($html, $inx, $wrapperTag, $wrapperClass);
//                $topWrapperClass = trim("$wrapperClass $cls");
//            }
//        }
//        return [$html, $topWrapperClass, $inx];
//    } // extensionsFinalCode

} // Extensions