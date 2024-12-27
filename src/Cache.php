<?php

namespace PgFactory\PageFactory;


const CACHE_PATH = 'site/cache/';
const PFY_CACHE_PATH = CACHE_PATH.'pagefactory/';
const LAST_CACHE_UPDATE_FILE = PFY_CACHE_PATH . 'last-cache-update.txt';
const PFY_PAGE_CACHE_PATH = PFY_CACHE_PATH . 'page-cache/';

class Cache
{
    public static bool $pageCacheable = true; // used by Cache
    public static bool $cacheUpdateNecessary = false;
    public static int $maxCacheDuration = 86400; // 24h


    public static function checkPageCache(): array
    {
        $cacheFile = self::getCacheFile();
        if ($_REQUEST || !self::$pageCacheable) {
            unlink($cacheFile);
            return [];
        }
        if (!file_exists($cacheFile)) {
            return [];
        }
        $pageFields = unserialize(file_get_contents($cacheFile));
        if (($pageFields['validUntil']??0) < time()) {
            return [];
        }
        $pageFields['headTitle'] = "*" . $pageFields['headTitle'];
        $pageFields['headInjections'] .= "  <!-- cached content -->\n";
        return $pageFields;
    } // checkPageCache


    public static function updatePageCache(array $pageFields): void
    {
        if (!self::$pageCacheable) {
            return;
        }
        
        $cacheFile = self::getCacheFile();
        $pageFields['validUntil'] = time() + Cache::$maxCacheDuration;
        writeFile($cacheFile, serialize($pageFields));
    } // updatePageCache


    private static function getCacheFile(): string
    {
        $pageId = str_replace('/', '_', page()->id());
        $cacheFile = PFY_PAGE_CACHE_PATH . PageFactory::$lang . '/' . $pageId . '.dat';
        return $cacheFile;
    } // getCacheFile


    /**
     * Clears entire cache folder, alse clears media/ folder
     * @return void
     */
    public static function flushAll(): void
    {
        rrmdir(CACHE_PATH);
        rrmdir('media');
    } // flushAll


    /**
     * @return void
     */
    public static function flush(): void
    {
        self::updateCacheFlag(0);
    } // flush


    /**
     * @return void
     */
    public static function clearKirbyCache(): void
    {
        foreach (glob(CACHE_PATH.'*') as $item) {
            if (!str_contains($item, '/pagefactory')) {
                rrmdir($item);
            }
        }
    } // clearKirbyCache


    /**
     * @return void
     */
    public static function preparePath()
    {
        if (!is_dir(PFY_CACHE_PATH)) {
            mkdir(PFY_CACHE_PATH, recursive: true);
        }
    } // preparePath()


    /**
     * @param $t
     * @return void
     */
    public static function updateCacheFlag($t = null)
    {
        self::preparePath();
        if ($t === null) {
            $t = time();
        }
        touch(LAST_CACHE_UPDATE_FILE, $t);
        self::$cacheUpdateNecessary = true;
    } // updateCacheFlag

} // Cache
