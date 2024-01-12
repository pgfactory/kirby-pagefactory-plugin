<?php

namespace PgFactory\PageFactory;

class Maintenance
{
    private static array $jobs = [];

    private static bool $secondRunPending = false;

    /**
     * @param $callback
     * @return void
     */
    public static function register($callback): void
    {
        self::$jobs[] = $callback;
    } // register


    /**
     * @param int $run
     * @return void
     * @throws \Exception
     */
    public static function trigger(int $run): void
    {
        // first run:
        if ($run === 1) {
            // In debug mode: inhibit rendering from cache. Moreover, force Kirby to rebuild cache:
            if (PageFactory::$debug) {
                // ToDo: optimize, i.e. clear KirbyCache for current page only.
                Cache::clearKirbyCache(); // clears entire cache, good enough for now...
                Cache::updateCacheFlag(0);
                self::$secondRunPending = true;
                return;
            }

            // If not in debug mode: monitor caching time
            // -> force rebuild at beginning of each caching period, e.g. on first request in the morning:
            Cache::preparePath();
            $lastCacheRefresh = file_exists(LAST_CACHE_UPDATE_FILE) ? filemtime(LAST_CACHE_UPDATE_FILE) : 0;
            $maxCacheAge = PageFactory::$config['maxCacheAge'] ?? 86400;
            $t1 = intval($lastCacheRefresh / $maxCacheAge);
            $t2 = intval(time() / $maxCacheAge);
            $resetKirbyCache = ($t1 !== $t2);
            if ($resetKirbyCache) {
                Cache::clearKirbyCache(); // clear entire cache
                self::$secondRunPending = true;
            }
            Cache::updateCacheFlag();

        // second run:
        } elseif (self::$secondRunPending) {
            self::execute();
        }
    } // trigger


    /**
     * @return void
     * @throws \Exception
     */
    private static function execute(): void
    {
        foreach (self::$jobs as $function) {
            if (($success = $function()) !== true) {
                throw new \Exception("Maintenance Error: $success");
            }
        }
    } // execute

} // Maintenance