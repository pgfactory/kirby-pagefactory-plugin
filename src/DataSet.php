<?php

namespace Usility\PageFactory;

use Kirby\Filesystem\F;
use Kirby\Data\Yaml as Yaml;

// meta keys:
const DATAREC_KEY = '_origRecKey';
const DATAREC_UID = '_uid';
const DATAREC_TIMESTAMP = '_timestamp';

// timings:
const DEFAULT_MAX_DB_LOCK_TIME      = 600; // sec
const DEFAULT_MAX_DB_BLOCKING_TIME  = 500; // ms
const DEFAULT_MAX_REC_LOCK_TIME     = 20; // sec
const DEFAULT_MAX_REC_BLOCKING_TIME = 5; // sec


class DataSet
{
    protected $name;
    protected $file;
    protected $type;
    protected $cacheFile;
    protected $lockFile;
    protected $blocking = 0;
    protected $readWriteMode;
    protected $elementLabels = [];
    protected $options;
    protected $includeMeta;
    protected $data;
    protected $nCols;
    protected $nRows;
    protected $lastModified = 0;
    protected $maxRecLockTime;
    protected $maxRecBlockingTime;


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
     * @throws \Exception
     */
    public function __construct(string $file, array $options = [])
    {
        $this->includeMeta =            $options['includeMeta'] ?? false;
        $this->readWriteMode =          $options['readWriteMode'] ?? true;
        $this->maxRecLockTime =         $options['maxRecLockTime'] ?? DEFAULT_MAX_REC_LOCK_TIME;
        $this->maxRecBlockingTime =     $options['maxRecBlockingTime'] ?? DEFAULT_MAX_REC_BLOCKING_TIME;

        if (isset($options['blocking'])) {
            if (is_int($options['blocking'])) {
                $this->blocking = $options['blocking'];
            } elseif ($options['blocking']) {
                $this->blocking = DEFAULT_MAX_DB_BLOCKING_TIME;
            }
        }
        $this->options = $options;

        // access data file:
        if ($file) {
            if (!file_exists(PFY_CACHE_PATH . 'data')) {
                preparePath(PFY_CACHE_PATH . 'data/');
            }
            $file = resolvePath($file);
            $this->name = base_name($file, false);
            $this->type = fileExt($file);
            $this->file = $file;
            $dataFile = str_replace('/', '_', dirname($file)) . '_' . base_name($file, false);
            $this->cacheFile = PFY_CACHE_PATH . "data/$dataFile.cache.dat";
            $this->lockFile = PFY_CACHE_PATH . "data/$dataFile.lock";
            if (!file_exists($file)) {
                touch($file);
                if (file_exists($this->cacheFile)) {
                    unlink($this->cacheFile);
                }
                if (file_exists($this->lockFile)) {
                    unlink($this->lockFile);
                }
            }
            if ($this->readWriteMode) {
                $this->lockDatasource();
            }
        }
        $this->initData();
    } // __construct


    /**
     * Returns the actual data without administrative properties
     * @param mixed $includeMetaFields
     * @return array
     * @throws \Exception
     */
    public function data(bool $includeMetaFields = false): array
    {
        $out = [];
        if ($this->data) {
            try {
                foreach ($this->data as $rec) {
                    if (is_string($rec)) {
                        continue;
                    }
                    $out[$rec->_uid] = $rec->data($includeMetaFields);
                }
            } catch (\Exception $e) {
                $this->resetCache();
                throw new \Exception("Error in data(): ".$e->getMessage());
            }
        }
        return $out;
    } // data


    /**
     * Returns data as an array (synonym for method data() )
     * @param bool $includeMetaFields
     * @return array
     * @throws \Exception
     */
    public function read(bool $includeMetaFields = false): array
    {
        return $this->data($includeMetaFields);
    } // read


    /**
     * Writes data to the datasource.
     *    $ds->write($data); // $data = ['bob' => [...], ]
     *    $ds->write('tom', $rec);  // $rec = ['name' => ...,]
     *    $ds->write($ds1);  // $ds1 = modified datasource
     * @param ...$args
     * @return $this
     * @throws \Exception
     */
    public function write(...$args)
    {
        $flush = $args['flush']??true;
        if (!$this->readWriteMode) {
            throw new \Exception("Datasource '$this->name' is in read-only mode, unable to write data");
        }
        if (!$args) {
            return $this; // nothing to do
        }
        $arg1 = @array_shift($args);
        $type = gettype($arg1);
        // payload is array -> convert to DataRecs
        if ($type === 'array') {
            $this->data = [];
            foreach ($arg1 as $key => $rec) {
                if (is_int($key)) {
                    $key = false;
                }
                $this->add($key, $rec);
            }

        // Payload is already of type DataSet -> just replace :
        } elseif (is_a($arg1, '\Usility\PageFactory\DataSet')) {
            foreach ($arg1 as $key => $value) {
                $this->set($key, $value);
            }

        // Payload is already of type DataRec -> just store:
        } elseif (is_a($arg1, '\Usility\PageFactory\DataRec')) {
            $this->data = $arg1;

        // key is scalar, arg2 is payload:
        } elseif (is_scalar($arg1) && isset($args[0])) {
            $rec = $args[0];

            // case array of key-value pairs:
            if (!$arg1) {
                $this->data = [];
                foreach ($rec as $k => $r) {
                    $this->data[] = new DataRec($k, $r);
                }

            //
            } elseif (is_string($arg1)) {
                if (is_a($rec, '\Usility\PageFactory\DataRec')) {
                    $this->data[$arg1] = $rec;
                } elseif ($arg1) {
                    $inx = $this->findRecKeyOf($arg1);
                    if ($inx !== null) {
                        $this->data[$inx] = new DataRec($arg1, $rec);
                    } else {
                        $this->data[] = new DataRec($arg1, $rec);
                    }
                }
            }
        } else {
            throw new \Exception("Supplied data is of unsupported type.");
        }
        $this->elementLabels = false;
        if ($flush) {
            $this->flush();
        }
        return $this;
    } // write


    /**
     * Adds a data record to datasource. Or overwrites an existing record with same key.
     *    $ds->add('max', ['email' => 'max@site.com',]);
     *    $ds->add(new DataRec('john', ['email' => 'john@site.com',]));
     *    $ds->add(['hugo' => ['email' => 'hugo@site.com',]]);
     * Note: if rec with same key exists, it gets overwritten
     * @param mixed $key
     * @param mixed|null $rec
     * @param bool $flush
     * @param mixed $recKeyToUse
     * @return object
     * @throws \Exception
     */
    public function add(mixed $key, mixed $rec = null, bool $flush = false, $recKeyToUse = false): object
    {
        if (!$this->readWriteMode) {
            throw new \Exception("Datasource '$this->name' is in read-only mode, unable to add data");
        }

        if ($recKeyToUse) {
            $dr = new DataRec($key, $rec, $this);
            $dr->set('_uid', $recKeyToUse);
            $this->data[$recKeyToUse] = $dr;

        } else {
            if (is_int($key)) {
                if ($rec[DATAREC_UID] ?? false) {
                    $key = $rec[DATAREC_UID];
                }
            }
            if (is_a($key, '\Usility\PageFactory\DataRec') && ($rec === null)) {
                $uid = $key->get('_uid');
                $this->data[$uid] = $key;

            } elseif (is_a($rec, '\Usility\PageFactory\DataRec')) {
                $uid = $rec->get('_uid');
                $this->data[$uid] = $rec;

            } else {
                $dr = new DataRec($key, $rec, $this);
                $uid = $dr->get('_uid');
                $this->data[$uid] = $dr;
            }
        }
        $this->elementLabels = false;
        if ($flush) {
            $this->flush();
        }
        return $this;
    } // add


    /**
     * Overwrites given elements of an existing record. Elements not contained in rec will be left untouched.
     *    $ds->update('max', ['email' => 'max@site.com','rand' => rand(1,99)]);
     * @param string $key
     * @param mixed $rec
     * @param bool $flush
     * @return object
     * @throws \Exception
     */
    public function update(mixed $key, mixed $rec = null, bool $flush = false): object
    {
        if (!$this->readWriteMode) {
            throw new \Exception("Datasource '$this->name' is in read-only mode, unable to update data");
        }
        if (is_a($key, '\Usility\PageFactory\DataRec')) {
            $inx = $key->dataRecKey();
            $this->data[$inx] = $key;
        } else {
            if (!$rec) {
                return $this;
            }
            // key contains uid:
            if (isset($this->data[$key])) {
                $this->data[$key]->update($rec);
                return $this;
            }

            $inx = $this->findRecKeyOf($key);
            if ($inx === null) {
                return $this->add($key, $rec, flush: $flush);

            } elseif (is_a($rec, '\Usility\PageFactory\DataRec')) {
                $this->data[$inx] = $rec;

            } else {
                $this->data[$inx]->update($rec);
            }
        }
        $this->elementLabels = false;
        if ($flush) {
            $this->flush();
        }
        return $this;
    } // update


    /**
     * Deletes one or multiple records from datasource.
     *    $ds->delete('max');
     *    $ds->delete('A', 'cat');
     *    $ds->delete('B', 'cat', 'all');
     *    $ds->delete(3);
     *    $ds->delete([2,4,7]);
     * @param mixed $key
     * @return object
     * @throws \Exception
     */
    public function remove($key, mixed $rec = null, bool $flush = false)
    {
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
        return $this;
    } // remove


    /**
     * Prepares the datasource for writing
     * @return void
     * @throws \Exception
     */
    public function makeWritable()
    {
        $this->readWriteMode = true;
        $this->lockDatasource();
    } // makeWritable


    /**
     * Reports whether datasource is locked and thus ready for writing
     * @return bool|mixed
     */
    public function isWritable()
    {
        return $this->readWriteMode;
    } // isWritable


    /**
     * Locks this datasource
     * @return void
     * @throws \Exception
     */
    public function lock()
    {
        $this->lockDatasource(true);
    } // lock


    /**
     * Unlocks this datasource
     * @return void
     */
    public function unlock()
    {
        $this->unlockDatasource();
    } // unlock


    /**
     * Reports whether datasource is locked.
     * @return bool
     */
    public function isLocked(): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }

        // lockfile exists:
        $sessionId = getSessionId();
        $sessionIdInFile = fileGetContents($this->lockFile);
        // check whether it's a permanent lock:
        if (($sessionIdInFile[0]??'') === '!') {
            // if it's locked by somebody else: quit immediately:
            if ($sessionId !== substr($sessionIdInFile, 1)) {
                return true;
            } else {
                // permanently locked by self: treat as unlocked
                return false;
            }
        }
        // if it's locked by somebody else, check whether lock timed out:
        if ($sessionId !== $sessionIdInFile) {
            // locked by somebody else
            $tLockFile = fileTime($this->lockFile);
            if ($tLockFile > (time() - DEFAULT_MAX_DB_LOCK_TIME)) {
                @unlink($this->lockFile);
                return false;
            }
            return true;

        } else { // locked by self: treat as unlocked
            return false;
        }
    } // isLocked


    /**
     * Reports whether datasource is permanently locked (ignoring my whom)
     * @return bool
     */
    public function isLockedPermanently(): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }

        // lockfile exists:
        $sessionIdInFile = fileGetContents($this->lockFile);
        return (($sessionIdInFile[0]??'') === '!');
    } // isLockedPermanently


    /**
     * Unlocks all datasource locked by this session.
     * @param $absAppRoot
     * @return void
     */
    public static function unlockDatasources($absAppRoot): void
    {
        $dataCachePath = $absAppRoot.PFY_CACHE_PATH.'data/';
        $sessionId = PageFactory::$phpSessionId;
        $lockFiles = getDir("$dataCachePath*.lock");
        foreach ($lockFiles as $lockFile) {
            $sid = fileGetContents($lockFile);
            if ($sid === $sessionId) {
                @unlink($lockFile);
            }
        }
    } // unlockDatasources


    /**
     * Returns a normalized data-array, i.e. makes sure that all records have all same elements
     * -> result is a rectangular table.
     * @param bool $includeHeader
     * @param bool $includeRecKeys
     * @param bool $includeMeta
     * @return array
     */
    public function get2DNormalizedData(bool $includeHeader = true, mixed $includeMeta = null): array
    {
        if ($includeMeta === null) {
            $includeMeta = $this->includeMeta;
        }
        $data = $this->data($includeMeta);
        $newData = [];
        $headers = $this->getElementLabels($includeMeta);

        if ($includeHeader) {
            $newData['_hdr'] = array_combine($headers, $headers);
        }
        foreach ($data as $key => $rec) {
            $newRec = [];
            foreach (array_keys($headers) as $k) {
                $newRec[$k] = $rec[$k] ?? '';
            }
            $newData[$key] = $newRec;
        }
        $this->nRows = sizeof($newData);
        $this->nCols = sizeof(reset($newData));
        return $newData;
    } // normalize2D


    /**
     * Returns an array containing all element labels found in all data records
     * @param bool $includeMeta
     * @return array
     */
    public function getElementLabels(bool $includeMeta = false): array
    {
        if ($this->elementLabels) {
            return $this->elementLabels;
        }
        $elmentLabels = [];
        if ($this->data) {
            foreach ($this->data as $rec) {
                foreach ($rec->recData as $k => $v) {
                    if (($k[0] !== '_') && !in_array($k, $elmentLabels)) {
                        $elmentLabels[$k] = $k;
                    }
                }
            }
        }
        if ($includeMeta || $this->includeMeta) {
            $elmentLabels[DATAREC_KEY] = DATAREC_KEY;
            $elmentLabels[DATAREC_UID] = DATAREC_UID;
            $elmentLabels[DATAREC_TIMESTAMP] = DATAREC_TIMESTAMP;
        }
        $this->elementLabels = $elmentLabels;
        return $elmentLabels;
    } // getElementLabels


    /**
     * Returns the index of one or multiple record(s) that match description.
     *    $inx = $ds->findRecKeyOf('Bob'); // case insensitive
     *    $inx = $ds->findRecKeyOf('M40ED116'); // uid instead of key
     *    $inx = $ds->findRecKeyOf('M40ED116', '_uid');
     *    $inx = $ds->findRecKeyOf('123456', 'password'); // value and element-label
     *    $inx = $ds->findRecKeyOf('x', 'x'); // no match returns null
     *    $inx = $ds->findRecKeyOf(2); // error
     *    $inx = $ds->findRecKeyOf('A', 'cat', 'all'); // returns array of found matches
     * @param string $key
     * @param mixed $attribute
     * @param mixed $all
     * @return mixed
     */
    public function findRecKeyOf(string $key, mixed $attribute = false, $all = false): mixed
    {
        $found = [];
        if ($attribute) {
            // allow for 'uid' instead of internally used '_uid':
            if ($attribute === 'uid') {
                $attribute = '_uid';
            }
            foreach ($this->data as $recUid => $elem) {
                // check whether matches with '_uid'-property:
                if (($attribute === '_uid') && strcasecmp($elem->_uid, $key) === 0) {
                    $found[] = $recUid;
                    if (!$all) { break; }

                // check whether matches with specified data element:
                } elseif (isset($elem->recData[$attribute]) && strcasecmp($elem->recData[$attribute], $key) === 0) {
                    $found[] = $recUid;
                    if (!$all) { break; }
                }
            }
        } else {
            // no attribute specified, check index, key and _uid:
            foreach ($this->data as $recUid => $elem) {
                if (($key === $recUid) || ($key === $elem->{DATAREC_KEY})) {
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
     *   $dataRec = $ds->find(2); // index
     *   $dataRec = $ds->find('M40ED116'); // uid
     *   $dataRec = $ds->find('Bob'); // key
     *   $dataRec = $ds->find('Bob@site.com', 'email'); // value and element-label
     *   $dataRec = $ds->find('M40ED116', 'uid'); // uid
     *   $dataRec = $ds->find('M40ED116', '_uid'); // uid (internally used label)
     *   $dataRec = $ds->find('A', 'cat'); // finds first match
     *   $dataSet = $ds->find('A', 'cat', 'all'); // returns a DataSet of all matching records
     * @param ...$keys
     * @return object
     * @throws \Exception
     */
    public function find(...$args): mixed
    {
        if ($args) {
            $key = array_shift($args);
            $attribute = $args[0] ?? null;
            if (is_int($key) && ($attribute === null)) { // case: index supplied
                return $this->nth($key);

            } elseif (is_scalar($key)) {    // normal case: key and opt. attribute supplied
                $key = (string)$key;
                $all = $args[1] ?? false;
                $recUid = $this->findRecKeyOf($key, $attribute, $all);

            } elseif (($key === null) && isset($args[0])) { // special case: invoked from read()
                $all = $args[1] ?? false;
                $args = $args[0];
                $key = $args[0] ?? false;
                $attribute = $args[1] ?? false;
                $recUid = $this->findRecKeyOf($key, $attribute, $all);

            } elseif (is_array($key)) {    // normal case: key and opt. attribute supplied
                $recUid = [];
                foreach ($key as $v) {
                    if (is_string($v)) {    // normal case: key and opt. attribute supplied
                        $recUid[] = $this->findRecKeyOf($v);
                    }
                }
            } else {
                $recUid = $this->findRecKeyOf($key, $args);
            }
        }

        if ($recUid === null) {
            throw new \Exception("Element '$key' not found in datasource '$this->name'");
        }
        if (is_array($recUid) && $recUid) {
            $ds = $this->clone();
            $ds->set('data', []);
            foreach ($recUid as $i) {
                $elem = $this->nth($i);
                $ds->add($elem);
            }
            return $ds;
        }
        return $this->nth($recUid);
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
            if (strpos($sortArg, '.') === false) {
                // element of first level -> access directly:
                foreach ($this->data as $key => $elem) {
                    $sortIndex[$key] = $elem->recData[$sortArg] ?? PHP_INT_MAX;
                }
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
        $ds = $this->clone();
        foreach ($ds->data as $key => $elem) {
            if (!$function($key, $elem)) {
                unset($ds->data[$key]);
            }
        }
        return $ds;
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
        return sizeof($this->data);
    } // count


    /**
     * Sets a property in the specified data record object
     * @param string $recUid
     * @param string $key
     * @param mixed $value
     * @param bool $flush
     * @return object
     * @throws \Exception
     */
    public function setData(string $recUid, string $key, mixed $value, bool $flush = false): object
    {
        if (isset($this->data[$recUid])) {
            $this->data[$recUid]->$key = $value;
        }
        if ($flush) {
            $this->flush();
        }
        return $this;
    } // setData


    /**
     * Sets a data element in the specified data record
     * @param string $recUid
     * @param string $key
     * @param mixed $value
     * @param bool $flush
     * @return object
     * @throws \Exception
     */
    public function setRecData(string $recUid, string $key, mixed $value, bool $flush = false): object
    {
        if (isset($this->data[$recUid])) {
            $this->data[$recUid]->recData[$key] = $value;
        }
        if ($flush) {
            $this->flush();
        }
        return $this;
    } // setRecData


    /**
     * Sets specified property
     * @param string $key
     * @param mixed $value
     * @param bool $flush
     * @return object
     * @throws \Exception
     */
    public function set(string $key, mixed $value, bool $flush = false): object
    {
        if (property_exists($this, $key)) {
            $this->$key = $value;
            if ($flush) {
                $this->flush();
            }
        }
        return $this;
    } // set


    /**
     * Returns specified property
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        if (isset($this->$key)) {
            return $this->$key;
        }
        return false;
    } // get


    /**
     * Returns a data element from specified data record
     * @param string $recUid
     * @param string $key
     * @return mixed
     */
    public function getRecData(string $recUid, string $key): mixed
    {
        return $this->data[$recUid]->$key??false;
    } // getRecData


    /**
     * Reports unixtime of last modification
     * @return int
     */
    public function lastModified(): int
    {
        return $this->lastModified;
    } // lastModified


    /**
     * Clones the object
     * @return object
     * @throws \Exception
     */
    public function clone(): object
    {
        $new = new DataSet(false);
        foreach ($this as $key => $value) {
            $new->set($key, $value);
        }
        return $new;
    } // clone


    /**
     * Writes datasource a) to master-file and b) to cache file
     * @return void
     * @throws \Exception
     */
    public function flush()
    {
        if ($this->file) {
            $this->exportToMasterFile();
            $this->updateCacheFile();
            $this->lastModified = time();
        }
    } // flush


    /**
     * General purpose export to file
     *    $ds->export('output/export.yaml');  -> to yaml file
     *    $ds->export('output/export.json');  -> to json file
     *    $ds->export('output/export1.csv');   -> to csv file *)
     *    $ds->export('output/export2.csv', includeMeta: true); -> includes meta-data
     *    $ds->export('output/export3.csv', includeHeader: false); -> includes meta-data and omits header-row
     *      *) before exporting to csv, data is 2D-normalized to fit in a rectangular table
     * @param string $toFile
     * @param mixed $includeMeta
     * @param mixed|null $includeHeader
     * @return void
     * @throws \Exception
     */
    public function export(string $toFile,
                           bool  $includeMeta = false,
                           bool  $includeHeader = true): void
    {
        $toFile = resolvePath($toFile);
        if ($toFile === $this->file) {
            throw new \Exception("Export to original data file '$toFile' is not allowed.");
        }
        $type = fileExt($toFile);
        try {
            if ($type === 'csv') {
                $this->exportToCsv($toFile, $includeHeader, $includeMeta);
            } else {
                $data = $this->data($includeMeta);
                writeFileLocking($toFile, $data);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    } // export


    /**
     * General purpose export to a csv file
     *    $ds->exportToCsv('output/export.csv');   -> to csv file
     *    $ds->exportToCsv('output/export2.csv', includeMeta: true); -> includes meta-data
     *    $ds->exportToCsv('output/export3.csv', includeHeader: false); -> includes meta-data and omits header-row
     * before exporting, data is normalized to fit in a rectangular table
     * @param string $file
     * @param mixed|null $includeHeader
     * @param mixed $includeMeta
     * @return void
     * @throws \Exception
     */
    public function exportToCsv(string $file,
                                bool  $includeMeta = false,
                                bool  $includeHeader = true): void
    {
        $data = $this->get2DNormalizedData($includeHeader, $includeMeta);
        $file = resolvePath($file);
        $fp = fopen($file, 'w');
        foreach ($data as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);
    } // exportToCsv



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
//                $this->data = unserialize(readFileLocking($this->cacheFile));
                $this->data = $this->readCacheFile();
                if (!$this->data) { // just in case cacheFile was empty, e.g. due to a previously aborted run
                    $this->importFromMasterFile();
                }
            }
        }
        $this->nRows = sizeof($this->data);
    } // initData


    /**
     * Imports data from master file trying to use previously used DATAREC_KEY.
     * @return mixed
     * @throws \Exception
     */
    protected function importFromMasterFile(): void
    {
        if (file_exists($this->file)) {
            $textEncoding = $this->options['textEncoding'] ?? false;
            try {
                $data = readFileLocking($this->file, $this->type, textEncoding: $textEncoding);
                $rec0 = reset($data);
                if (isset($rec0['_uid'])) {
                    $oldData = false;
                } else {
                    if (file_exists($this->cacheFile)) {
                        $oldData = unserialize(readFileLocking($this->cacheFile));
                    } else {
                        $oldData = [];
                    }
                }
                foreach ($data as $key => $rec) {
                    $recKeyToUse = false;
                    // if cache file existed, retrieve UIDs for re-use:
                    if ($oldData) {
                        foreach ($oldData as $oldElem) {
                            if ($oldElem->{DATAREC_KEY} === $key) {
                                $recKeyToUse = $oldElem->_uid;
                                break;
                            }
                        }
                    } else {
                        $recKeyToUse = $rec['_uid'] ?? false;
                    }
                    $this->add($key, $rec, recKeyToUse: $recKeyToUse);
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        } else {
            touch($this->file);
        }
    } // importFromMasterFile


    /**
     * Exports data to master file
     * @return void
     * @throws \Exception
     */
    protected function exportToMasterFile(): void
    {
        if (!$this->readWriteMode) {
            throw new \Exception("Error: DB modified while locked");
        }

        $recKeyType =             $this->options['masterFileRecKeyType'] ?? DATAREC_KEY; // default: re-use original rec key
        $recKeySort =             $this->options['masterFileRecKeySort'] ?? false;
        $recKeySortOnElement =    $this->options['masterFileRecKeySortOnElement'] ?? false;

        // determine which data element shall be used as rec-keys in the master-file:
        if ($keys =               $this->options['masterFileRecKeys'] ?? '') {
            $parts = explodeTrim(',', $keys);
            foreach ($parts as $el) {
                // determine recKey: recKey|index|uid|timestamp or a data-rec-element as 'rec.xy'
                if (preg_match('/(index|'.DATAREC_KEY.'|_uid)/', $el, $m)) {
                    $recKeyType = $m[1];
                } else if ($el === 'uid') { // synonym for _uid
                    $recKeyType = '_uid';
                } elseif (preg_match('/.*\.\s*(.+)/', $el, $m)) { // rec.xy
                    $recKeyType = '.' . $m[1];

                // determine sorting instructions
                } elseif (preg_match('/(sort|asc.*|desc.*):\s*(.+)/', $el, $m)) {
                    $recKeySort = $m[1];
                    $recKeySortOnElement = $m[2];
                }
            }
        }
        if (!$keys && $this->type === 'csv') {
            $recKeyType = 'index';
        }

        try {
            // sort:
            if ($recKeySort) {
                if ($recKeySort === 'sort' || $recKeySort === 'asc') {
                    $recKeySort = false;
                } elseif ($recKeySort === 'desc') {
                    $recKeySort = 'arsort';
                }
                $data = $this->clone()->sort($recKeySortOnElement, $recKeySort)->data();
            } else {
                $data = $this->data($this->includeMeta);
            }

            // create new data-set with requested rec-key (unless _uid selected):
            if ($recKeyType && $recKeyType !== '_uid') {
                $data1 = [];

                // case index:
                if ($recKeyType === 'index') {
                    foreach ($data as $k => $v) {
                        $v[DATAREC_UID] = $k;
                        $data1[] = $v;
                    }

                // case rec data element: dr->recData[xy]
                } elseif ($recKeyType[0] === '.') {
                    $recKeyType = substr($recKeyType, 1);
                    // loop over records and assemble new data set:
                    foreach ($data as $k => $v) {
                        $v[DATAREC_UID] = $k;
                        $k = $v[$recKeyType] ?? $k; // access rec-element, use key if that not exists
                        $data1[$k] = $v;
                    }

                // case rec object property: dr->xy
                } else {
                    foreach ($data as $k => $v) {
                        $v[DATAREC_UID] = $k;
                        $key = $this->data[$k]->$recKeyType ?? $k;
                        $data1[$key] = $v;
                    }
                }
                $data = $data1;
                unset($data1);
            }
            writeFileLocking($this->file, $data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    } // exportToMasterFile


    /**
     * Writes data out to the cache file
     * @return void
     * @throws \Exception
     */
    protected function updateCacheFile(): void
    {
        try {
            $data = [];
            // remove parent references as serializing that can crash
            foreach ($this->data as $key => $rec) {
                unset($rec->parent);
                $data[$key] = $rec;
            }
            writeFileLocking($this->cacheFile, serialize($data));

            // export debug copy if debug enabled:
            if (PageFactory::$debug) {
                $this->debugDump(false, $this->cacheFile);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    } // updateCacheFile


    /**
     * Reads cache file and returns restored data-set.
     * @return array
     * @throws \Exception
     */
    protected function readCacheFile(): array
    {
        $data = unserialize(readFileLocking($this->cacheFile));
        // restore parent references
        if ($data && is_array($data)) {
            foreach ($data as $key => $rec) {
                $data[$key]->parent = $this;
            }
        }
        return $data;
    } // readCacheFile


    /**
     * Locks the datasource.
     * -> Lock will be automatically removed upon termination of run, unless $permanently
     * @param bool $permanently
     * @return void
     * @throws \Exception
     */
    protected function lockDatasource(bool $permanently = false): void
    {
        $file = basename($this->file);
        $sessionId = $sidToWrite = getSessionId();
        if ($permanently) {
            $sidToWrite = "!$sessionId";
        }

        if (!file_exists($this->lockFile)) { // lock file does not exist yet, so lock it:
            writeFile($this->lockFile, $sidToWrite);
            return;
        }

        // lockfile exists:
        $sessionIdInFile = fileGetContents($this->lockFile); // locked by whom?

        // leading '!' means file is permanently locked:
        if (($sessionIdInFile[0]??'') === '!') {
            // if it's locked by somebody else: quit immediately:
            if ($sessionId !== substr($sessionIdInFile, 1)) {
                throw new \Exception("Data '$file' already locked");
            } else { // permanently locked by self: done
                return;
            }

        // if it's locked by somebody else, check whether lock timed out:
        } elseif ($sessionId !== $sessionIdInFile) {
            $tLockFile = fileTime($this->lockFile);
            if ($tLockFile < (time() - DEFAULT_MAX_DB_LOCK_TIME)) {
                // time out reached -> overwrite:
                writeFileLocking($this->lockFile, $sidToWrite);
                return;
            }

        // permanently locked by self
        } else {
            writeFile($this->lockFile, $sidToWrite); // renew lock's timestamp
            return;
        }

        // still locked, await change and give up after timeout:
        $n = 1;
        while ($n++ < $this->blocking) {
            if (!file_exists($this->lockFile)) { // lock disappeared
                writeFile($this->lockFile, $sidToWrite);
                return;
            }
            usleep(10000);
        }
        // timed out: give up
        $file = basename($this->file);
        throw new \Exception("Data '$file' already locked");

    } // lockDatasource


    /**
     * Unlocks the datasource (ignoring owner of lock)
     * @return void
     */
    protected function unlockDatasource(): void
    {
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
            $this->readWriteMode = false;
        }
    } // unlockDatasource


    /**
     * Returns internal structure of datasource in readable form.
     * If $cacheFile, writes result out to a .txt file instead.
     * @param bool $asHtml
     * @param mixed $cacheFile
     * @return mixed
     * @throws \Exception
     */
    public function debugDump(bool $asHtml = true, mixed $cacheFile = false): mixed
    {
        if (!$this->data) {
            return '';
        }
        if ($cacheFile) {
            $asHtml = false;
        }
        $str = '';
        $str .= "name: $this->name\n";
        $str .= "file: $this->file\n";
        $str .= "type: $this->type\n";
        $str .= "cacheFile: $this->cacheFile\n";
        $str .= "lockFile: $this->lockFile\n";
        $str .= "readWriteMode: " . ($this->readWriteMode ? 'true' : 'false') . "\n";
        $str .= "options:\n  " . rtrim(str_replace("\n", "\n  ", Yaml::encode($this->options)));
        $str .= "\nDB-lock: " . (file_exists($this->lockFile) ? 'true' : 'false') . "\n";
        $str .= "\nDATA:\n";
        foreach ($this->data as $k => $v) {
            $d = (array)$v;
            $dat = $d['recData'];
            unset($d['parent']);
            unset($d['recData']);
            $s = Yaml::encode($d);
            $s = str_replace("\n", "\n  ", $s);
            $s1 = Yaml::encode($dat);
            $s .= "DATA:\n    ";
            $s .= str_replace("\n", "\n    ", $s1);
            $str .= "$k:\n  $s\n";
        }
        if ($cacheFile) {
            F::write($cacheFile . '.txt', $str);
            return '';
        } else {
            if ($asHtml) {
                return "<pre>$str</pre>\n";
            }
            return $str;
        }
    } // debugDump

} // DataSet