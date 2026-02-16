<?php

namespace PgFactory\PageFactory;

use Error;
use Kirby\Data\Data;
//use Kirby\Data\Yaml as Yaml;

 // meta keys:
if (!defined('DATAREC_TIMESTAMP')) {
    define('DATAREC_TIMESTAMP', '_timestamp');
}
if (!defined('DATAREC_RECKEY')) {
    define('DATAREC_RECKEY', '_reckey');
}
if (!defined('PFY_DB_METAREC_KEY')) {
    define('PFY_DB_METAREC_KEY', '_META');
}
if (!defined('SUPPORTED_FILE_TYPES')) {
    define('SUPPORTED_FILE_TYPES', 'yaml,json,csv,txt');
}
//const SUPPORTED_FILE_TYPES = 'yaml,json,csv,txt';

 // timings:
const PFY_DEFAULT_MAX_REC_LOCK_TIME     = 600; // sec
const PFY_DEFAULT_MAX_REC_BLOCKING_TIME = 2; // sec
const PFY_DEFAULT_KEEP_DATA_DURATION    = 12; // month
const PFY_MAX_DB_FILE_SIZE              = 10485760; // 10MB
const PFY_DB_FILE_BLOCKING_MAX_TIME     = 2000; // 500; //ms
const PFY_DB_FILE_BLOCKING_CYCLE_TIME   = 1000; //us
const PFY_DB_FILE_BLOCKING_CYCLES       = PFY_DB_FILE_BLOCKING_MAX_TIME / PFY_DB_FILE_BLOCKING_CYCLE_TIME;

 // data archive:
const PFY_ARCHIVE_SUBPATH               = '-archive/';

const PFY_DATASTORE_DEFAULT_REC = [
    PFY_DB_METAREC_KEY => [
        DATAREC_TIMESTAMP       => 0,
        DATAREC_RECKEY          => '',
        '_lock'                 => false,
        '_lockedBy'             => false,
        'maxRecLockTime'        => 0,
    ]
];

class DataStore
{
    protected $name;
    protected $file;
    protected $cacheFile;
    protected $obfuscateRecKeys;
    protected $options;
    protected $includeMeta;
    protected array $data = [];
    protected $masterFileRecKeyType;    // rec key type as to appear externally (e.g. in Yaml file)
    protected $recKeyType;              // rec key type used internally, default: hash
    protected $nRows;
    protected $lastModified = 0;
    protected $maxRecLockTime;
    protected $maxRecBlockingTime;
    protected $avoidDuplicates;
    protected static bool|null $dev = false;
    protected int $keepDataThreshold = 0; // unix-time
    protected string|false $keepDataOnField; // field-name
    private string $lastCreatedRecKey = '';


    /**
     * Constructor: makes data available
     * @param string $file
     * @param array $options :
     *     'includeMeta'
     *     'readWriteMode'
     *     'maxRecLockTime'
     *     'maxRecBlockingTime'
     *     'recKeyType'
     *     'recKeySort'
     *     'recKeySortOnElement'
     *     'recKeyExcludeUid'
     *     'masterFileRecKeys'
     *     'keepDataDuration'
     * @throws \Exception
     */
    public function __construct(string $file, array $options = [])
    {
        $this->parseOptions($file, $options);
    } // __construct


    /**
     * Returns the actual data without administrative properties
     * @param mixed $includeMetaFields
     * @return array
     * @throws \Exception
     */
    public function data(mixed $includeMetaFields = false, string|null $recKeyType = null): array
    {
        if ($this->data) {
            $data = $this->data;
            if ($includeMetaFields === true) {
                $includeMetaFields = '_reckey,_timestamp';
            }
            if (is_string($includeMetaFields)) {
                $includeMetaFields = strtolower($includeMetaFields);
                if (str_contains($includeMetaFields, 'reckey')) {
                    foreach ($data as $key => $dataRec) {
                        $data[$key][DATAREC_RECKEY] = $dataRec[PFY_DB_METAREC_KEY][DATAREC_RECKEY];
                    }
                }
                if (str_contains($includeMetaFields, 'timestamp')) {
                    foreach ($data as $key => $dataRec) {
                        $data[$key][DATAREC_TIMESTAMP] = $dataRec[PFY_DB_METAREC_KEY][DATAREC_TIMESTAMP];
                    }
                }
            }
            if ($recKeyType !== null) {
                if ($recKeyType === 'index') {
                    $data = array_values($data);
                }
            }
            foreach ($data as $key => $dataRec) {
                unset($data[$key][PFY_DB_METAREC_KEY]);
            }
        } else {
            $data = [];
        }
        return $data;
    } // data


    public function write(array $data, bool $flush = true): object
    {
        $this->convertToInternalFormat($data);

        if ($flush) {
            $this->flush();
        }
        $this->nRows = sizeof($this->data);
        return $this;
    } // write


    /**
     * Adds a data record (not DataRec) to datasource.
     * Optionally, if $recKeyToUse is given, overwrites a possibly existing record with same key.
     * @param array $rec
     * @param bool $flush
     * @param $recKeyToUse
     * @return object|string
     * @throws \Exception
     */
    public function addRec(array $rec, bool $flush = true, $recKeyToUse = false): object|string
    {
        if ($recKeyToUse) {
            if ($this->obfuscateRecKeys) {
                $recKey = $this->deObfuscateRecKey($recKeyToUse);
            } else {
                $recKey = $recKeyToUse;
            }
        } else {
            $recKey = createHash();
        }
        $origRec = $this->data[$recKey] ?? [];
        if ($origRec) {
            $this->data[$recKey] = $rec + $origRec;
        } else {
            if ($this->avoidDuplicates) {
                $k = $this->recExists($rec);
                if ($k) {
                    $this->lastCreatedRecKey = $k; // -> to be picked up by consecutive ->recId() call
                    return $this;
                }
            }
            if (!isset($rec[PFY_DB_METAREC_KEY])) {
                $rec[PFY_DB_METAREC_KEY] = [];
            }
            $rec[PFY_DB_METAREC_KEY][DATAREC_RECKEY] = $recKey;
            $rec[PFY_DB_METAREC_KEY][DATAREC_TIMESTAMP] = time();
            $rec[PFY_DB_METAREC_KEY]['_lock'] = false;
            $rec[PFY_DB_METAREC_KEY]['_lockedBy'] = '';
            $rec[PFY_DB_METAREC_KEY] += PFY_DATASTORE_DEFAULT_REC[PFY_DB_METAREC_KEY];
            $this->data[$recKey] = $rec;
        }
        $this->lastCreatedRecKey = $recKey; // -> to be picked up by consecutive ->recId() call

        if ($flush) {
            $this->flush();
        }
        $this->nRows = sizeof($this->data);
        return $this;
    } // addRec


    public function updateRec(array $rec, $recKey, bool $flush = true): bool
    {
        return (bool) $this->addRec($rec, $flush, $recKey);
    } // updateRec


    public function getRec(string $recKey, bool $includeMeta = false): array|null
    {
        $rec = $this->data[$recKey] ?? null;
        if ($rec && !$includeMeta) {
            unset($rec[PFY_DB_METAREC_KEY]);
        }
        return $rec;
    } // getRec
    

    /**
     * @return string
     */
    public function recId(): string
    {
        return $this->lastCreatedRecKey;
    } // recId



    /**
     * @param $rec
     * @return bool
     */
    public function recExists(array $rec): bool
    {
        $dataStr = strtolower(json_encode($this->data()));
        $newRec = strtolower(rtrim(json_encode($rec), '}'));
        return str_contains($dataStr, $newRec);
    } // isDuplicate



    /**
     * Overwrites given elements of an existing record. Elements not contained in rec will be left untouched.
     * @param mixed $rec
     * @param bool $flush
     * @return object
     * @throws \Exception
     */
    public function update(array $data, bool $flush = false): object
    {
        foreach ($data as $key => $rec) {
            $rec += PFY_DATASTORE_DEFAULT_REC;
            $rec[PFY_DB_METAREC_KEY][DATAREC_RECKEY] = $key;
            $rec[PFY_DB_METAREC_KEY][DATAREC_TIMESTAMP] = time();
            $this->data[$key] = $rec;
        }
        if ($flush) {
            $this->flush();
        }
    } // update


    /**
     * @return void
     * @throws \Exception
     */
    public function purge(): void
    {
        $this->data = [];
        $this->flush();
    } // purge


    /**
     * Returns the size of the data (i.e. number of records) or false, if no data present
     * @return int|false
     */
    public function getSize(): int|false
    {
        return $this->nRows??0;
    } // getSize


    /**
     * Deletes a record from datasource.
     *    $ds->delete('ABCDEF');
     * @param mixed $key
     * @return object
     * @throws \Exception
     */
    public function deleteRec($key, bool $flush = false)
    {
        if ($this->obfuscateRecKeys) {
            $key = $this->deObfuscateRecKey($key);
        }
//        $key = $this->deObfuscateRecKey($key);
        mylog("DataSet: deleting dataRec $key from DB $this->file");
        if (isset($this->data[$key])) {
            unset($this->data[$key]);
        } else {
            $recKey = $this->findRecKeyOf($key);
            if (isset($recKey)) {
                unset($this->data[$recKey]);
            }
        }
        if ($flush) {
            $this->flush();
        }
        $this->nRows = sizeof($this->data);
        return $this;
    } // deleteRec


    /**
     * Returns an array containing all element keys found in all data records
     * @param bool $includeMeta
     * @return array
     */
    public function getElementKeys(bool $includeMeta = false): array
    {
        $elementKeys = [];
        foreach ($this->data as $rec) {
            foreach ($rec as $key => $value) {
                $elementKeys[$key] = true;
            }
        }
        $elementKeys = array_keys($elementKeys);
        if ($includeMeta) {
            $elementKeys[] = DATAREC_RECKEY;
            $elementKeys[] = DATAREC_TIMESTAMP;
        }
        return $elementKeys;
    } // getElementKeys


    /**
     * Returns the index of one or multiple record(s) that match description.
     *    $inx = $ds->findRecKeyOf('Bob'); // case insensitive
     *    $inx = $ds->findRecKeyOf('M40ED116'); // uid instead of key
     *    $inx = $ds->findRecKeyOf('M40ED116', DATAREC_RECKEY);
     *    $inx = $ds->findRecKeyOf('123456', 'password'); // value and element-label
     *    $inx = $ds->findRecKeyOf('x', 'x'); // no match returns null
     *    $inx = $ds->findRecKeyOf(2); // error
     *    $inx = $ds->findRecKeyOf('A', 'cat', 'all'); // returns array of found matches
     * @param string $key
     * @param mixed $attribute
     * @param mixed $all
     * @return mixed
     */
    public function findRecKeyOf(string $key, mixed $attribute = false, bool $all = false): mixed
    {
        $found = [];
        if ($attribute) {
            // allow for 'uid' instead of internally used DATAREC_RECKEY:
            if ($attribute === 'reckey') {
                $attribute = DATAREC_RECKEY;
            }
            foreach ($this->data as $recUid => $elem) {
                // check whether matches with DATAREC_RECKEY-property:
                if (($attribute === DATAREC_RECKEY) && strcasecmp($elem->_reckey, $key) === 0) {
                    $found[] = $recUid;
                    if (!$all) { break; }

                // check whether matches with specified data element:
                } elseif (isset($elem->recData[$attribute]) && strcasecmp($elem->recData[$attribute], $key) === 0) {
                    $found[] = $recUid;
                    if (!$all) { break; }
                }
            }
        } else {
            // no attribute specified, check index, key and _reckey:
            foreach ($this->data as $recUid => $elem) {
                if ($key === $recUid) {
//                if (($key === $recUid) || ($key === $elem->_origRecKey)) {
                    $found[] = $recUid;
                    if (!$all) { break; }
                }
            }
        }
        if ($found) {
            if ($all) {
                return $found;
            } else {
                return $found[0];
            }
        } else {
            return null;
        }
    } // findIndexOf


    /**
     * Finds one or multiple records that match the description.
//     *   $dataRec = $ds->find(2); // index
//     *   $dataRec = $ds->find('M40ED116'); // uid
//     *   $dataRec = $ds->find('Bob'); // key
//     *   $dataRec = $ds->find('Bob@site.com', 'email'); // value and element-label
//     *   $dataRec = $ds->find('M40ED116', 'uid'); // uid
//     *   $dataRec = $ds->find('M40ED116', DATAREC_RECKEY); // uid (internally used label)
//     *   $dataRec = $ds->find('A', 'cat'); // finds first match
//     *   $dataSet = $ds->find('A', 'cat', 'all'); // returns a DataSet of all matching records
     * @param ...$keys
     * @return object
     * @throws \Exception
     */
    public function find(...$args): mixed
    {
        if ($args) {
            $key = array_shift($args);
            if ($this->data[$key]??false) {
                return $key;
            }

            $attribute = $args[0] ?? null;
            if (is_int($key) && ($attribute === null)) { // case: index supplied
                $rec = $this->nth($key);
                return $rec[PFY_DB_METAREC_KEY][DATAREC_RECKEY];

            } elseif (is_scalar($key)) {    // normal case: key and opt. attribute supplied
                $key = (string)$key;
                if ($this->obfuscateRecKeys) {
                    $key = $this->deObfuscateRecKey($key);
                }
                $key = $this->deObfuscateRecKey($key);
                $all = (bool)($args[1] ?? false);
                $recUid = $this->findRecKeyOf($key, $attribute, $all);

            } elseif (($key === null) && isset($args[0])) { // special case: invoked from read()
                $all = (bool)$args[1] ?? false;
                $args = $args[0];
                $key = $args[0] ?? false;
                $attribute = $args[1] ?? false;
                $recUid = $this->findRecKeyOf($key, $attribute, $all);

            } elseif (is_array($key)) {    // normal case: key and opt. attribute supplied
                $recUid = [];
                foreach ($key as $v) {
                    if (is_string($v)) {    // normal case: key and opt. attribute supplied
                        if ($this->obfuscateRecKeys) {
                            $v = $this->deObfuscateRecKey($v);
                        }
                        $recUid[] = $this->findRecKeyOf($v);
                    }
                }
            } else {
                $recUid = $this->findRecKeyOf($key, $args);
            }
        }
        return $recUid;
    } // find


    /**
     * Sorts the data records based on given criteria
     * @param $sortArg
     * @param $sortFunction
     * @return $this
     * @throws \Exception
     */
    public function sort($sortArg, $sortFunction = false)
    {
        if (!$this->data) {
            return $this;
        }
        $sortIndex = [];
        if (!$sortFunction) {
            $sortFunction = 'asort';
        } elseif ($sortFunction === 'reverse') {
            $sortFunction = 'arsort';
        }

        // case closure defining element to sort on:
        if ($sortArg instanceof \Closure) {
            try {
                foreach ($this->data as $key => $elem) {
                    $sortIndex[$key] = $sortArg($elem->recData, $elem);
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }

        // case string defining element to sort on:
        // special notation to access data sub-elements: a.b.c = [a][b][c]
        } elseif (is_string($sortArg)) {
            // sort on meta-data, e.g. '_origRecKey' or '_timestamp':
            if (str_starts_with($sortArg, '_')) {
                // element of first level -> access directly:
                foreach ($this->data as $key => $elem) {
                    $sortIndex[$key] = $elem->$sortArg ?? PHP_INT_MAX;
                }

            // sort on rec data, e.g. 'name' -> specified as 'name' or 'a.b':
            } else {
                // nested element:
                $keys = explode('.', $sortArg);
                foreach ($this->data as $key => $elem) {
                    $el = &$this->data[$key]->recData;
                    foreach ($keys as $k) {
                        if (isset($el[$k])) {
                            $el = &$el[$k];
                        } else {
                            throw new \Exception("Data '$sortArg' element missing in record '$key'.");
                        }
                    }
                    $sortIndex[$key] = $el;
                }
            }
        }

        if (!function_exists($sortFunction)) {
            throw new \Exception("Unknown sort function '$sortFunction'.");
        }

        // sort data:
        $sortFunction($sortIndex);
        $out = [];
        foreach (array_keys($sortIndex) as $key) {
            $out[$key] = $this->data[$key];
        }
        $this->data = $out;
        return $this;
    } // sort


    /**
     * Returns a cloned object with all records removed which do not fitting the filter criteria
     * @param $function
     * @return object
     * @throws \Exception
     */
    public function filter($function)
    {
        if (!$function instanceof \Closure) {
            throw new \Exception("filter requires a Closure as argument");
        }
//        $ds = $this->clone();
//        foreach ($ds->data as $key => $elem) {
//            if (!$function($key, $elem)) {
//                unset($ds->data[$key]);
//            }
//        }
//        return $ds;
    } // filter


    /**
     * Returns the n-th data record
     * @param mixed $n
     * @return mixed
     */
    public function nth(mixed $n): mixed
    {
        if (isset($this->data[$n])) {
            return $this->data[$n];
        }
        if (is_int($n)) {
            $uids = array_keys($this->data);
            $uid = $uids[$n] ?? false;
            if ($uid !== false) {
                return $this->data[$uid];
            }
        }
        return null;
    } // nth


    /**
     * Returns the number of data records
     * @return int
     */
    public function count(): int
    {
        return is_array($this->data) ? sizeof($this->data) : 0;
    } // count


    /**
     * Returns the sum of specified data element in all records
     * @param string|false $onField
     * @return int
     */
    public function sum(string|false $onField = false): int
    {
        if (!$onField) {
            return is_array($this->data) ? sizeof($this->data) : 0;
        } else {
            $count = 0;
            if (is_array($this->data)) {
                foreach ($this->data as $rec) {
                    if (isset($rec->recData[$onField])) {
                        $count += $rec->recData[$onField];
                    }
                }
            }
            return $count;
        }
    } // sum


    /**
     * Reports unixtime of last modification
     * @return int
     */
    public function lastModified(): int
    {
        return filemtime($this->file);
    } // lastModified



    /**
     * Writes datasource a) to master-file and b) to cache file
     * @return void
     * @throws \Exception
     */
    public function flush(bool $cacheOnly = false)
    {
        if ($this->file) {
            if (!$cacheOnly) {
                $this->exportToMasterFile();
            }
            $this->updateCacheFile();
            $this->lastModified = time();
        }
    } // flush





    // === protected methods ========================

    /**
     * Resets the cache by deleting the datasources cache file
     * @return void
     */
    protected function resetCache(): void
    {
        @unlink($this->cacheFile);
    } // resetCache


    /**
     * Gets data, either from cache if up to date or from master file
     * @return void
     * @throws \Exception
     */
    protected function initData(): void
    {
        if (!$this->file) {
            return;
        }
        $tCacheFile = fileTime($this->cacheFile);
        if (!$tCacheFile) { // no file:
            $this->importFromMasterFile();
            $this->updateCacheFile();

        } else {
            $tMasterFile = fileTime($this->file);
            // If MasterFile is older than CacheFile, latter is up to date, so import it:
            if ($tCacheFile < $tMasterFile) {
                $this->importFromMasterFile();
                $this->updateCacheFile();
            } else {
                $this->readCacheFile();
                if (!$this->data) { // just in case cacheFile was empty, e.g. due to a previously aborted run
                    $this->importFromMasterFile();
                }
            }
        }
        if (is_array($this->data)) {
            $this->nRows = sizeof($this->data);
        }
    } // initData


    /**
     * Imports data from master file trying to use previously used '_origRecKey'.
     * @return mixed
     * @throws \Exception
     */
    protected function importFromMasterFile(): void
    {
        $this->data = [];
        if (!file_exists($this->file)) {
            touch($this->file);
            return;
        }

        $data = $this->readFile($this->file);
        if (!$data) {
            return;
        }
        $modified = $this->convertToInternalFormat($data);
        if ($modified) {
            $this->exportToMasterFile();
        }
    } // importFromMasterFile


    /**
     * Exports data to master file
     * @return void
     * @throws \Exception
     */
    protected function exportToMasterFile(): void
    {
        $masterFileRecKeyType =   $this->masterFileRecKeyType;
        $recKeySort =             $this->options['masterFileRecKeySort'] ?? false;
        $recKeySortOnElement =    $this->options['masterFileRecKeySortOnElement'] ?? false;

        $data = $this->data(includeMetaFields: true, recKeyType: $this->masterFileRecKeyType);

        if ($recKeySort) {
            if ($recKeySort === 'sort' || $recKeySort === 'asc' || $recKeySort === true) {
                $recKeySort = false;
            } elseif ($recKeySort === 'desc') {
                $recKeySort = 'arsort';
            }
            //ToDo: sort
        }

        // remove old data records, move them to archive file:
        //$this->archiveOldData(); //???

        $this->writeDataFile($this->file, $data);
    } // exportToMasterFile
    

    /**
     * @param $file
     * @param $maxAgeInMonths
     * @return void
     */
    private function historyFileManager(string $file, int $maxAgeInMonths): void
    {
        $maxAge = strtotime("-$maxAgeInMonths months");
        if ($dir = getDir(dirname($file))) {
            foreach ($dir as $file) {
                if (filemtime($file) < $maxAge) {
                    unlink($file);
                }
            }
        }
    } // historyFileManager


    /**
     * Manages size and age of data-source files.
     * a) limits size of source-file as well as archive files to PFY_MAX_DB_FILE_SIZE
     * b) based on 'keepDataDuration' argument, extracts old records and moves them to archive file
     *      -> path/PFY_ARCHIVE_SUBPATH/file.ext
     * @return void
     */
    private function archiveOldData(): void
    {
        if (filesize($this->file) > PFY_MAX_DB_FILE_SIZE) {
            $archive = $this->reduceDbFileSize();
        } else {
            if (!$this->keepDataThreshold) {
                return; // nothing to do
            }
            $keepDataThreshold = $this->keepDataThreshold;
            $archive = [];
            foreach ($this->data as $i => $rec) {
                if ($this->keepDataOnField) {
                    $t = strtotime($rec->recData[$this->keepDataOnField] ?? '');
                } else {
                    $t = $rec->_timestamp;
                }
                if ($t < $keepDataThreshold) {
                    unset($this->data[$i]);
                    $archive[] = $rec->data(true);
                }
            }
        }

        if (!$archive) {
            return; // nothing to do
        }

        // append new recs to (possibly) existing archive:
        $destPath = dir_name($this->file).PFY_ARCHIVE_SUBPATH;
        $basename = basename($this->file);
        $timestamp = '';
        foreach (getDir($destPath) as $file) {
            if ($timestamp < ($ts = substr(basename($file), 0,11))) {
                $timestamp = $ts;
            }
        }
        if (!$timestamp) {
            $timestamp = date('Y-m-d_');
        }
        $archiveFile = "$destPath$timestamp$basename";
        if (file_exists($archiveFile) && filesize($archiveFile) > PFY_MAX_DB_FILE_SIZE) {
            $timestamp = date('Y-m-d_');
            $archiveFile = "$destPath$timestamp$basename";
        }

        mylog("DataSet: extracting data to archive file '$archiveFile'");
        preparePath($archiveFile);
        appendFile($archiveFile, $archive);
    } // archiveOldData


    /**
     * Extracts half of data records from $this->data, returns the extracted part.
     * @return array
     */
    private function reduceDbFileSize(): array
    {
        $n = intval(sizeof($this->data)/2);
        $tmp = array_splice($this->data, $n);
        $archive = array_map(function ($rec) {
            return $rec->data(true);
        }, $tmp);
        return array_values($archive);
    } // reduceDbFileSize


    /**
     * Writes data out to the cache file
     * @return void
     * @throws \Exception
     */
    protected function updateCacheFile(): void
    {
        try {
            if (PageFactory::$dev) {
                $this->writeFile($this->cacheFile, json_encode($this->data, JSON_PRETTY_PRINT));
            } else {
                $this->writeFile($this->cacheFile, json_encode($this->data));
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    } // updateCacheFile


    /**
     * Reads cache file and returns restored data-set.
     * @return void
     * @throws \Exception
     */
    protected function readCacheFile(): void
    {
        try {
            $json = file_exists($this->cacheFile) ? file_get_contents($this->cacheFile) : '';
            $data = json_decode($json, true);
        } catch (Error $e) {
            die("Internal data error - try to clear data cache (" . $e->getMessage() . ')');
        }
        if (!is_array($data)) {
            $data = [];
        }
        $this->data = $data;
    } // readCacheFile


    public function lockRec(string $recKey): bool
    {
        try {
            $this->readModifyWrite($this->cacheFile, function ($data) use ($recKey) {
                $sessionId = getSessionId();
                $lockSuccessfull = false;
                foreach ($data as $key => $rec) {
                    // unlock any recs locked by self or timed out:
                    if ($rec[PFY_DB_METAREC_KEY]['_lock']) {
                        $lockedBy = $rec[PFY_DB_METAREC_KEY]['_lockedBy'];
                        if ($lockedBy !== $sessionId) {
                            if ($rec[PFY_DB_METAREC_KEY]['_lock'] > time() - PFY_DEFAULT_MAX_REC_LOCK_TIME) {
                                continue;
                            }
                        }
                    }
                    // requested rec -> lock:
                    if ($key === $recKey) {
                        $rec[PFY_DB_METAREC_KEY]['_lock'] = time();
                        $rec[PFY_DB_METAREC_KEY]['_lockedBy'] = $sessionId;
                        $lockSuccessfull = true;
                    }
                    $data[$key] = $rec;
                }
                // finally check whether locking was successful:
                if (!$lockSuccessfull) {
                    throw new \Exception("Record locked.");
                }
                return $data;
            });
        } catch (\Exception $e) {
            return false;
        }
        return true;
    } // lockRec


    public function unlockRec(string $recKey): bool
    {
        try {
            $this->readModifyWrite($this->cacheFile, function ($data) use ($recKey) {
                if ($rec = ($data[$recKey] ?? false)) {
                    $sessionId = getSessionId();
                    if ($rec[PFY_DB_METAREC_KEY]['_lock']) {
                        $lockedBy = $rec[PFY_DB_METAREC_KEY]['_lockedBy'];
                        if ($lockedBy !== $sessionId) {
                            if ($rec[PFY_DB_METAREC_KEY]['_lock'] > time() - PFY_DEFAULT_MAX_REC_LOCK_TIME) {
                                return false;
                            }
                        }
                    }
                    $rec[PFY_DB_METAREC_KEY]['_lock'] = false;
                    $rec[PFY_DB_METAREC_KEY]['_lockedBy'] = '';
                    $data[$recKey] = $rec;
                    return $data;
                }
                return false;
            });
        } catch (\Exception $e) {
            return false;
        }
        return true;
    } // unlockRec


    /**
     * @param string $recKey
     * @return bool
     */
    public function isRecLocked(string $recKey): bool
    {
        $this->readCacheFile();
        if ($rec = $this->data[$recKey] ?? null) {
            if (($rec[PFY_DB_METAREC_KEY]??false) && ($rec[PFY_DB_METAREC_KEY]['_lock']??false)) {
                if (($rec[PFY_DB_METAREC_KEY]['_lockedBy']??false) !== getSessionId()) {
                    return true;
                }
            }
        }
        return false;
    } // isRecLocked


    /**
     * @param bool $force
     * @return void
     * @throws \Exception
     */
    public function unlockAllRecs(bool $force = false): void
    {
        try {
            $this->readModifyWrite($this->cacheFile, function ($data) use ($force) {
                foreach ($data as $recKey => $rec) {
                    if ($rec = ($data[$recKey] ?? false)) {
                        $sessionId = getSessionId();
                        if ($rec[PFY_DB_METAREC_KEY]['_lock']) {
                            $lockedBy = $rec[PFY_DB_METAREC_KEY]['_lockedBy'];
                            if (!$force && ($lockedBy !== $sessionId)) {
                                if ($rec[PFY_DB_METAREC_KEY]['_lock'] > time() - PFY_DEFAULT_MAX_REC_LOCK_TIME) {
                                    continue;
                                }
                            }
                        }
                        $rec[PFY_DB_METAREC_KEY]['_lock'] = false;
                        $rec[PFY_DB_METAREC_KEY]['_lockedBy'] = '';
                        $data[$recKey] = $rec;
                    }
                }
                return $data;
            });
        } catch (\Exception $e) {
            return;
        }
    } // unlockAllRecs


    /**
     * @param string $key
     * @return string
     * @throws \Exception
     */
    protected function obfuscateRecKey(string $key): string
    {
        $session = kirby()->session();
        $tableRecKeyTab = $session->get('pfy.obfuscatedKeys');
        if (!$tableRecKeyTab || !($obfuscatedKey = array_search($key, $tableRecKeyTab))) {
            $obfuscatedKey = \PgFactory\PageFactory\createHash();
        }
        $tableRecKeyTab[$obfuscatedKey] = $key;
        $session->set('pfy.obfuscatedKeys', $tableRecKeyTab);
        return $obfuscatedKey;
    } // deObfuscateRecKey


    /**
     * @param string $key
     * @return string
     */
    protected function deObfuscateRecKey(string $key): string
    {
        $tableRecKeyTab = kirby()->session()->get('pfy.obfuscatedKeys');
        if ($tableRecKeyTab && (isset($tableRecKeyTab[$key]))) {
            $key = $tableRecKeyTab[$key];
        }
        return $key;
    } // deObfuscateRecKey

    

    /**
     * @param array $options
     * @param string $file
     * @return void
     * @throws \Exception
     */
    private function parseOptions(string $file, array $options): void
    {
        $this->includeMeta = $options['includeMeta'] ?? null;
        $this->obfuscateRecKeys = $options['obfuscateRecKeys'] ?? false;
        $this->maxRecLockTime = (isset($options['maxRecLockTime']) && $options['maxRecLockTime']) ?
            $options['maxRecLockTime'] : PFY_DEFAULT_MAX_REC_LOCK_TIME;
        $this->maxRecBlockingTime = (isset($options['maxRecBlockingTime']) && $options['maxRecBlockingTime'])
            ? $options['maxRecBlockingTime'] : PFY_DEFAULT_MAX_REC_BLOCKING_TIME;
        $this->avoidDuplicates = $options['avoidDuplicates'] ?? true;
        $this->recKeyType = $options['recKeyType'] ?? 'hash';
        $this->masterFileRecKeyType = $options['masterFileRecKeyType'] ?? 'hash';

        if ($keepDataDuration = ($options['keepDataDuration'] ?? PFY_DEFAULT_KEEP_DATA_DURATION)) {
            $this->keepDataThreshold = strtotime("-$keepDataDuration months");
        }
        $this->keepDataOnField = $options['keepDataOnField'] ?? false; // false means '_timestamp'

        $this->options = $options;

        self::$dev = kirby()->session()->get('pfy.dev');

        if ($file) {
                // access data file:
            if (!file_exists(PFY_CACHE_PATH . 'data')) {
                preparePath(PFY_CACHE_PATH . 'data/');
            }
            $file = Utils::resolvePath($file);
            $this->checkAndFixDataFile($file); // migrate between json and yaml if necessary
            $this->name = base_name($file, false);
            $type = fileExt($file);
            if (!str_contains(SUPPORTED_FILE_TYPES, $type)) {
                throw new \Exception("Error: DataSet invoked with unsupported file-type: '$type'");
            }
            $this->file = $file;
            $p = substr(dirname($file), strlen(PFY_APP_BASE_PATH));
            $dataFile = str_replace('/', '_', $p) . '_' . base_name($file, false);
            $this->cacheFile = PFY_CACHE_PATH . "data/$dataFile.cache.json";
            preparePath($this->cacheFile);

            // if data file doesn't exist, prepare it empty and make sure no old cache/lock-files exist.
            if (!is_file($file)) {
                preparePath($file);
                touch($file);
                if (file_exists($this->cacheFile)) {
                    unlink($this->cacheFile);
                }
            }
            $this->initData();
        }
    } // parseOptions


    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    } // setOption


    /**
     * @param string $file
     * @return void
     * @throws \Exception
     */
    private function checkAndFixDataFile(string $file): void
    {
        if (!is_file($file) && fileExt($file) === 'json') {
            $yamlFile = fileExt($file, true).'.yaml';
            if (file_exists($yamlFile)) {
                $this->convertToJson($yamlFile);
                $renamedFile = dir_name($yamlFile).'#'.basename($yamlFile);
                rename($yamlFile, $renamedFile);
            }
        } elseif (fileExt($file) === 'yaml') {
            $jsonFile = fileExt($file, true).'.json';
            if (file_exists($jsonFile)) {
                $this->convertToYaml($jsonFile);
                $renamedFile = dir_name($jsonFile).'#'.basename($jsonFile);
                rename($jsonFile, $renamedFile);
            }
        }
    } // checkAndFixDataFile


    /**
     * @param string $file
     * @return void
     * @throws \Exception
     */
    public function convertToJson(string $file): void
    {
        $jsonFile = fileExt($file, true).'.json';
        if (file_exists($jsonFile)) {
            throw new \Exception("Error in convertToJson(): target file '$jsonFile' already exists.");
        }
        $data = readFile($file);
        $this->writeDataFile($jsonFile, $data);
    } // convertToJson


    /**
     * @param string $file
     * @return void
     * @throws \Exception
     */
    public function convertToYaml(string $file): void
    {
        $yamlFile = fileExt($file, true).'.yaml';
        if (file_exists($yamlFile)) {
            throw new \Exception("Error in convertToYaml(): target file '$yamlFile' already exists.");
        }
        $data = readFile($file);
        $this->writeDataFile($yamlFile, $data);
    } // convertToYaml
    
    
    private function convertToInternalFormat(array $data): bool
    {
        $this->data = [];
        $modified = false;
        // loop over loaded data, fix recKey and timestamp:
        foreach ($data as $rec) {
            if (!is_array($rec)) {
                throw new \Exception("Incompatible data: file '$this->file' does not contain an array of records.");
            }
            if ($rec[DATAREC_RECKEY]??false) {
                $recKey = $rec[DATAREC_RECKEY];
                unset($rec[DATAREC_RECKEY]);
            } else {
                $recKey = createHash();
                $modified = true;
            }
            if ($rec[DATAREC_TIMESTAMP]??false) {
                $timestamp = $rec[DATAREC_TIMESTAMP];
                unset($rec[DATAREC_TIMESTAMP]);
            } else {
                $timestamp = time();
                $modified = true;
            }
            $newRec = $rec + PFY_DATASTORE_DEFAULT_REC;
            $newRec[PFY_DB_METAREC_KEY][DATAREC_RECKEY] = $recKey;
            $newRec[PFY_DB_METAREC_KEY][DATAREC_TIMESTAMP] = $timestamp;

            $this->data[$recKey] = $newRec;
        }
        return $modified;
    } // convertToInternalFormat


    private function readFile(string $file): mixed
    {
        $fp = fopen($file, 'r');
        if(flock($fp, LOCK_SH)){ // will block execution until the write lock is released
            $str = stream_get_contents($fp);
            clearstatcache($file); // clear the file cache for the next function
        }
        fclose($fp);
        if (!$str) {
            return null;
        }
        $type = strtolower(fileExt($file));
        $data = Data::decode($str, $type);
        return $data;
    } // readFile


    private function readModifyWrite(string $file, callable $callback): void
    {
        $fp = fopen($file, 'c+b');
        if (!$fp) {
            throw new \Exception("Could not open file '$file'");
        }

        try {
            $this->awaitFileLock($fp, $file); // try to lock file, wait if necessary

            $oldContent = stream_get_contents($fp);
            $data = json_decode($oldContent, true);
            $data = $callback($data);
            if ($data !== false) {
                $newContent = $this->encodeData($data, 'json');
                ftruncate($fp, 0);
                rewind($fp);
                if (fwrite($fp, $newContent) === false) {
                    throw new \Exception("Error writing to file '$file'");
                }
                fflush($fp);
            }
        } finally {
            if (fclose($fp) === false) {
                throw new \Exception("Error closing file '$file'");
            }
        }
    } // readModifyWrite


    private function writeDataFile(string $file, mixed $data = null): void
    {
        $type = strtolower(fileExt($file));
        if ($data === null) {
            $content = $this->encodeData($this->data, $type);
        } else {
            $content = $this->encodeData($data, $type);
        }
        $this->writeFile($file, $content);
    } // writeDataFile
    
    
    private function writeFile(string $file, string $content): void 
    {
        // write data to file:
        // Use 'c' mode to prevent truncation before the lock is acquired
        $fp = fopen($file, "c");
        if (!$fp) {
            throw new \Exception("Could not open file '$file'");
        }

        try {
            
            $this->awaitFileLock($fp, $file); // try to lock file, wait if necessary

            // Truncate now that we own the lock
            ftruncate($fp, 0);
            rewind($fp);

            if (fwrite($fp, $content) === false) {
                throw new \Exception("Error writing file '$file'");
            }
            fflush($fp);

        } finally {
            if (fclose($fp) === false) {
                throw new \Exception("Error closing file '$file'");
            }
        }
    } // writeFile
    
    
    private  function awaitFileLock($fp, string $filename): void
    {
        $count = PFY_DB_FILE_BLOCKING_CYCLES;
        while (!flock($fp, LOCK_EX | LOCK_NB) && ($count--)) {
            usleep(PFY_DB_FILE_BLOCKING_CYCLE_TIME);
        }
        if (!$count) {
            throw new \Exception("Failed to lock '$filename'");
        }
    } // awaitFileLock


    private function encodeData(mixed $content, string|false $type): string
    {
        // encode data:
        if ($type && str_contains('yml,yaml', $type)) {
            $content = shieldNewlines($content);
            $content = Data::encode($content, $type);
            $content = prettifyYaml($content);

        } elseif ($type === 'json') {
            $content = json_encode($content, JSON_PRETTY_PRINT);

        } elseif ($type === 'csv' && $content && is_array($content)) {
            $header = array_keys(reset($content));
            array_unshift($content, $header);
            $fp = fopen('php://temp', 'r+');
            foreach ($content as $line) {
                fputcsv($fp, $line);
            }
            rewind($fp);
            $content = stream_get_contents($fp);
            fclose($fp);

        } elseif ($type === 'txt') {
            if (is_array($content)) {
                $tmp = '';
                foreach ($content as $rec) {
                    $tmp .= ($rec[0] ?? '') . "\n";
                }
                $content = $tmp;
            }

        } elseif (is_object($content)) {
            $content = serialize($content);
        }
        return $content;
    } // encodeData

} // DataSet