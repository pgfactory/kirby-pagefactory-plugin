<?php

namespace PgFactory\PageFactory;


use Exception;

class Cache
{
    private const KIRBY_CACHE_PATH = PFY_KIRBY_BASE_PATH.'site/cache/';
    private const LAST_CACHE_UPDATE_FILE = PFY_CACHE_PATH . 'last-cache-update.txt';
    private const PFY_PAGE_CACHE_PATH = PFY_CACHE_PATH . 'page-cache/';
    private const GENERIC_URL_COMMANDS = [
        'gclid',
        'fbclid',
        'ref',
        'q',
        'query',
        'page',
        'next',
        'token',
        'affid',
    ];

    /**
     * @var bool
     */
    public static bool $pageCachingEnabled = true;
    /**
     * @var bool
     */
    public static bool $cacheUpdateNecessary = false;
    private static bool $pfyUrlCmdPresent = false;


    /**
     * @return void
     */
    public static function init(): void
    {
        self::$pfyUrlCmdPresent = self::checkUrlCmdPresent();
        self::$pageCachingEnabled = kirby()->option('pgfactory.pagefactory.enablePageCache') &&
            !kirby()->session()->pull('pfy.message');
        self::preparePath();
        $lastCacheRefresh = file_exists(self::LAST_CACHE_UPDATE_FILE) ? filemtime(self::LAST_CACHE_UPDATE_FILE) : 0;
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
        // any url cmds may potentially the page content, thus we force
        if (self::$pfyUrlCmdPresent || !self::$pageCachingEnabled) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            return false;
        }
        if (!file_exists($cacheFile)) {
            return false;
        }
        $data = file_get_contents($cacheFile);
        try {
            $rec = $data ? unserialize($data) : false;
            if (!is_array($rec)) {
                unlink($cacheFile);
                return false;
            }
        } catch (Exception) {
            unlink($cacheFile);
            return false;
        }
        $payload = $rec['payload'] ?? false;
        $validUntil = $rec['validUntil'] ?? 0;
        if ($validUntil < time()) {
            unlink($cacheFile);
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
            'validUntil' => strtotime('tomorrow'),
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
        return self::PFY_PAGE_CACHE_PATH . PageFactory::$lang . "/$pageId$prefix.dat";
    } // getPageCacheFileName


    /**
     * @return void
     */
    private static function flushPageCache(): void
    {
        rrmdir(self::PFY_PAGE_CACHE_PATH);
    } // flushPageCache


    /**
     * Clears entire cache folder, also clears media/ folder
     * @return void
     */
    public static function flushAll(): void
    {
        rrmdir(self::KIRBY_CACHE_PATH);
        rrmdir(PFY_KIRBY_BASE_PATH.'media');
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
        foreach (glob(self::KIRBY_CACHE_PATH.'*') as $item) {
            if (!str_contains($item, '/pagefactory')) {
                rrmdir($item);
            }
        }
    } // clearKirbyCache


    /**
     * @return void
     */
    public static function preparePath(): void
    {
        if (!is_dir(PFY_CACHE_PATH)) {
            mkdir(PFY_CACHE_PATH, recursive: true);
        }
    } // preparePath()


    /**
     * @param ?int $t
     * @return void
     */
    public static function updateCacheFlag(?int $t = null): void
    {
        self::preparePath();
        if ($t === null) {
            $t = time();
        }
        touch(self::LAST_CACHE_UPDATE_FILE, $t);
        self::$cacheUpdateNecessary = true;
    } // updateCacheFlag


    /**
     * @return bool
     */
    private static function checkUrlCmdPresent(): bool
    {
        if (!($_REQUEST ?? false)) {
            return false;
        }
        $pfyUrlCmdPresent = false;
        foreach ($_REQUEST as $key => $val) {
            if (!str_starts_with($key, 'utm') &&
                !in_array($key, self::GENERIC_URL_COMMANDS)) {
                $pfyUrlCmdPresent = true;
            }
        }
        return $pfyUrlCmdPresent;
    } // checkUrlCmdPresent

} // Cache
