<?php

namespace PgFactory\PageFactory;


const CACHE_PATH = 'site/cache/';
const PFY_CACHE_PATH = CACHE_PATH.'pagefactory/';
const LAST_CACHE_UPDATE_FILE = PFY_CACHE_PATH . 'last-cache-update.txt';

class Cache
{
    public static bool $cacheUpdateNecessary = false;
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
