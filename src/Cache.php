<?php

namespace PgFactory\PageFactory;


const CACHE_PATH = 'site/cache/';
const PFY_CACHE_PATH = CACHE_PATH.'pagefactory/';
const LAST_CACHE_UPDATE_FILE = PFY_CACHE_PATH . 'last-cache-update.txt';

class Cache
{
    /**
     * Clears entire cache folder, alse clears media/ folder
     * @return void
     */
    public static function flushAll(): void
    {
        rrmdir(CACHE_PATH);
        PageFactory::$assets->prepareAssets(); // -> recompile scss files while still privileged
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
    public static function superviseKirbyCache(): void
    {
        // In debug mode: inhibit rendering from cache. Moreover, force Kirby to rebuild cache:
        if (PageFactory::$debug) {
            // ToDo: optimize, i.e. clear KirbyCache for current page only.
            self::clearKirbyCache(); // clears entire cache, good enough for now...
            self::updateCacheFlag(0);
            return;
        }

        // If not in debug mode: monitor caching time
        // -> force rebuild at beginning of each caching period, e.g. on first request in the morning:
        self::preparePath();
        $lastCacheRefresh = file_exists(LAST_CACHE_UPDATE_FILE) ? filemtime(LAST_CACHE_UPDATE_FILE) : 0;
        $maxCacheAge = PageFactory::$config['maxCacheAge']??86400;
        $t1 = intval($lastCacheRefresh / $maxCacheAge);
        $t2 = intval(time() / $maxCacheAge);
        $resetKirbyCache = ($t1 !== $t2);
        if ($resetKirbyCache) {
            self::clearKirbyCache(); // clear entire cache
        }
        self::updateCacheFlag();
    } // superviseKirbyCache


    /**
     * @return void
     */
    private static function preparePath()
    {
        if (!is_dir(PFY_CACHE_PATH)) {
            mkdir(PFY_CACHE_PATH, recursive: true);
        }
    } // preparePath()


    /**
     * @param $t
     * @return void
     */
    private static function updateCacheFlag($t = null)
    {
        self::preparePath();
        if ($t === null) {
            $t = time();
        }
        touch(LAST_CACHE_UPDATE_FILE, $t);
    } // updateCacheFlag

} // Cache
