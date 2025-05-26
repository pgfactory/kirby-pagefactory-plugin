<?php

namespace PgFactory\PageFactory;


const CACHE_PATH = PFY_APP_BASE_PATH.'site/cache/';
const PFY_CACHE_PATH = CACHE_PATH.'pagefactory/';
const LAST_CACHE_UPDATE_FILE = PFY_CACHE_PATH . 'last-cache-update.txt';
const PFY_PAGE_CACHE_PATH = PFY_CACHE_PATH . 'page-cache/';

class Cache
{
    public static bool $pageCachingEnabled = true; // used by Cache
    public static bool $cacheUpdateNecessary = false;


    /**
     * @return void
     */
    public static function init(): void
    {
        self::$pageCachingEnabled = kirby()->option('pgfactory.pagefactory.enablePageCache') &&
            !kirby()->session()->pull('pfy.message');
        self::preparePath();
        $lastCacheRefresh = file_exists(LAST_CACHE_UPDATE_FILE) ? filemtime(LAST_CACHE_UPDATE_FILE) : 0;
        if (($lastCacheRefresh === 0) || PageFactory::$dev) {
            self::$pageCachingEnabled = false;
            self::$cacheUpdateNecessary = true;
            // ToDo: optimize, i.e. clear KirbyCache for current page only.
            self::clearKirbyCache(); // clears entire cache, good enough for now...
            self::flushPageCache();
            self::updateCacheFlag();
            return;
        }

        $resetKirbyCache = (strtotime('today') !== strtotime('today', $lastCacheRefresh));
        if ($resetKirbyCache) {
            self::clearKirbyCache(); // clear entire cache
            self::$pageCachingEnabled = false;
        }
        self::updateCacheFlag();
    } // init



    // === Page Cache ==========================================
    // Page Cache caches 'pageContent'

    /**
     * @param string $prefix
     * @return mixed
     */
    public static function checkPageCache(string $prefix = ''): mixed
    {
        $cacheFile = self::getPageCacheFileName($prefix);
        if ($_REQUEST || !self::$pageCachingEnabled) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            return false;
        }
        if (!file_exists($cacheFile)) {
            return false;
        }
        $rec = unserialize(file_get_contents($cacheFile));
        $payload = $rec['payload']??false;
        $validUntil = $rec['validUntil']??0;
        if ($validUntil < time()) {
            return false;
        }
        if (!$prefix && isset($payload)) {
            $payload['cacheIndicator'] = "\n<!-- cached pageContent -->";
        }
        return $payload;
    } // checkPageCache


    /**
     * @param mixed $payload
     * @param string $prefix
     * @return void
     * @throws \Exception
     */
    public static function updatePageCache(mixed $payload, string $prefix = ''): void
    {
        if (!self::$pageCachingEnabled) {
            return;
        }
        
        $cacheFile = self::getPageCacheFileName($prefix);
        $rec = [
            'payload' => $payload,
            'validUntil' => strtotime('today') + 86400, // next midnight
        ];
        writeFile($cacheFile, serialize($rec));
    } // updatePageCache


    /**
     * @param string $prefix
     * @return string
     */
    private static function getPageCacheFileName(string $prefix = ''): string
    {
        $pageId = str_replace('/', '_', page()->id());
        $prefix = $prefix ? '_' . $prefix : '';
        $cacheFile = PFY_PAGE_CACHE_PATH . PageFactory::$lang . "/$pageId$prefix.dat";
        return $cacheFile;
    } // getPageCacheFileName


    /**
     * @return void
     */
    private static function flushPageCache(): void
    {
        rrmdir(PFY_PAGE_CACHE_PATH);
    } // flushPageCache


    /**
     * Clears entire cache folder, alse clears media/ folder
     * @return void
     */
    public static function flushAll(): void
    {
        rrmdir(CACHE_PATH);
        rrmdir(PFY_APP_BASE_PATH.'media');
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
