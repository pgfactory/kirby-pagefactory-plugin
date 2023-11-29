<?php

namespace PgFactory\PageFactory;

use Kirby\Filesystem\F;
use Kirby\Data\Yaml as Yaml;

 // meta keys:
const DATAREC_TIMESTAMP = '_timestamp';
const SUPPORTED_FILE_TYPES = 'yaml,json,csv';

 // timings:
const DEFAULT_MAX_DB_LOCK_TIME      = 60; // sec
const DEFAULT_MAX_DB_BLOCKING_TIME  = 5; // ms
const DEFAULT_MAX_REC_LOCK_TIME     = 600; // sec
const DEFAULT_MAX_REC_BLOCKING_TIME = 2; // sec



class DataSet
{
    protected $name;
    protected $file;
    protected $type;
    protected $cacheFile;
    protected $lockFile;
    protected $blocking = 0;
    protected $readWriteMode;
    protected $obfuscateRecKeys;
    public    $elementKeys;
    protected $options;
    protected $includeMeta;
    protected $data;
    protected $data2DNormalized;
    protected $masterFileRecKeyType;    // rec key type as to appear externally (e.g. in Yaml file)
    protected $recKeyType;              // rec key type used internally, default: hash
    public static $officeFormatAvailable;
    protected $officeDoc = false;
    protected $downloadFilename;
    protected $nCols;
    protected $nRows;
    protected $lastModified = 0;
    protected $maxRecLockTime;
    protected $maxRecBlockingTime;
    protected $avoidDuplicates;
    protected $debug;
    protected static $sessionId = false;


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
        $this->includeMeta =            $options['includeMeta'] ?? null;
        $this->readWriteMode =          $options['readWriteMode'] ?? true;
        $this->obfuscateRecKeys =       $options['obfuscateRecKeys'] ?? false;
        $this->maxRecLockTime =         (isset($options['maxRecLockTime']) && $options['maxRecLockTime']) ?
                                            $options['maxRecLockTime']: DEFAULT_MAX_REC_LOCK_TIME;
        $this->maxRecBlockingTime =     (isset($options['maxRecBlockingTime']) && $options['maxRecBlockingTime'])
                                            ?$options['maxRecBlockingTime'] : DEFAULT_MAX_REC_BLOCKING_TIME;
        $this->downloadFilename =       $options['downloadFilename'] ?? false;
        $this->avoidDuplicates =        $options['avoidDuplicates'] ?? true;
        $this->recKeyType =             $options['recKeyType'] ?? 'hash';
        $this->masterFileRecKeyType =   $options['masterFileRecKeyType'] ?? 'hash';

        if (isset($options['blocking'])) {
            if (is_int($options['blocking'])) {
                $this->blocking = $options['blocking'];
            } elseif ($options['blocking']) {
                $this->blocking = DEFAULT_MAX_DB_BLOCKING_TIME;
            }
        }
        $this->options = $options;
        $this->debug = PageFactory::$debug ?? Utils::determineDebugState();

        // access data file:
        if ($file) {
            if (!file_exists(PFY_CACHE_PATH . 'data')) {
                preparePath(PFY_CACHE_PATH . 'data/');
            }
            $file = resolvePath($file);
            $this->name = base_name($file, false);
            $this->type = fileExt($file);
            if (!str_contains(SUPPORTED_FILE_TYPES, $this->type)) {
                throw new \Exception("Error: DataSet invoked with unsupported file-type: '$this->type'");
            }
            $this->file = $file;
            $dataFile = str_replace('/', '_', dirname($file)) . '_' . base_name($file, false);
            $this->cacheFile = PFY_CACHE_PATH . "data/$dataFile.cache.dat";
            // lockFile needs to be absolute because it may be used by __destruct():
            $this->lockFile = kirby()->root().'/'.PFY_CACHE_PATH . "data/$dataFile.lock";

            // if data file doesn't exist, prepare it empty and make sure no old cache/lock-files exist.
            if (!is_file($file)) {
                preparePath($file);
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
            $this->initData();
        }

        self::$officeFormatAvailable = (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet'));
    } // __construct


    public function __destruct()
    {
        $this->unlockDatasource();
    } // __destruct


    /**
     * Returns the actual data without administrative properties
     * @param mixed $includeMetaFields
     * @return array
     * @throws \Exception
     */
    public function data(mixed $includeMetaFields = false, string $recKeyType = null): array
    {
        $out = [];
        if ($this->data) {
            try {
                foreach ($this->data as $key => $dataRec) {
                    $rec = $dataRec->data(true);
                    if ($recKeyType === 'index') {
                        $out[] = $rec;
                    } else {
                        if ($recKeyType === 'origKey') {
                            $key = $rec['_origRecKey'];
                        } elseif (($recKeyType[0]??'') === '.') {
                            $key = substr($recKeyType,1);
                            if (isset($rec[$key])) {
                                throw new \Exception("Data Error: '\$rec[$key]' not defined.");
                            }
                            $key = $rec[$key];
                        } else {
                            if ($this->obfuscateRecKeys) {
                                $key = $this->obfuscateRecKey($key);
                            }
                        }
                        $out[$key] = $rec;
                    }
                }
            } catch (\Exception $e) {
                $this->resetCache();
                throw new \Exception("Error in data(): ".$e->getMessage());
            }
        }
        if (!$includeMetaFields) {
            foreach ($out as $key => $rec) {
                unset($out[$key]['_timestamp']);
                unset($out[$key]['_reckey']);
                if (isset($rec['_origRecKey'])) {
                    unset($out[$key]['_origRecKey']);
                }
            }
        } elseif ($includeMetaFields === '_reckey') {
            foreach ($out as $key => $rec) {
                unset($out[$key]['_timestamp']);
                if (isset($rec['_origRecKey'])) {
                    unset($out[$key]['_origRecKey']);
                }
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
                $this->addRec($rec);
            }

        // Payload is already of type DataSet -> just replace :
        } elseif (is_a($arg1, '\PgFactory\PageFactory\DataSet')) {
            foreach ($arg1 as $key => $value) {
                $this->set($key, $value);
            }

        // Payload is already of type DataRec -> just store:
        } elseif (is_a($arg1, '\PgFactory\PageFactory\DataRec')) {
            $this->data = $arg1;

        // key is scalar, arg2 is payload:
        } elseif (is_scalar($arg1) && isset($args[0])) {
            $rec = $args[0];

            // case array of key-value pairs:
            if (!$arg1) {
                $this->data = [];
                foreach ($rec as $k => $r) {
                    $this->data[] = new DataRec($k, $r, $this);
                }

            //
            } elseif (is_string($arg1)) {
                if (is_a($rec, '\PgFactory\PageFactory\DataRec')) {
                    $this->data[$arg1] = $rec;
                } elseif ($arg1) {
                    $inx = $this->findRecKeyOf($arg1);
                    if ($inx !== null) {
                        $this->data[$inx] = new DataRec($arg1, $rec, $this);
                    } else {
                        $this->data[] = new DataRec($arg1, $rec, $this);
                    }
                }
            }
        } else {
            throw new \Exception("Supplied data is of unsupported type.");
        }
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
        if (!$this->readWriteMode) {
            throw new \Exception("Datasource '$this->name' is in read-only mode, unable to add data");
        }
        if ($recKeyToUse) {
            if ($this->obfuscateRecKeys) {
                $recKeyToUse = $this->deObfuscateRecKey($recKeyToUse);
            }
            $recKeyToUse = $this->deObfuscateRecKey($recKeyToUse);
            $dr = new DataRec($recKeyToUse, $rec, $this);
            $dr->set('_reckey', $recKeyToUse);
            $this->data[$recKeyToUse] = $dr;

        } else {
                if (!$this->avoidDuplicates || !$this->recExists($rec)) {
                    $dr = new DataRec(false, $rec, $this);
                    $uid = $dr->get('_reckey');
                    $this->data[$uid] = $dr;
                }
        }
        if ($flush) {
            $this->flush();
        }
        $this->nRows = sizeof($this->data);
        return $this;
    } // addRec



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
        if (is_a($key, '\PgFactory\PageFactory\DataRec')) {
            $inx = $key->dataRecKey();
            $this->data[$inx] = $key;
        } else {
            if (!$rec) {
                return $this;
            }
            // key contains uid:
            if ($this->obfuscateRecKeys) {
                $key = $this->deObfuscateRecKey($key);
            }
            if (isset($this->data[$key])) {
                $this->data[$key]->update($rec);
                return $this;
            }

            $inx = $this->findRecKeyOf($key);
            if ($inx === null) {
                // rec doesn't exist, create new one:
                return $this->addRec($rec, flush: $flush);

            } elseif (is_a($rec, '\PgFactory\PageFactory\DataRec')) {
                $this->data[$inx] = $rec;

            } else {
                $this->data[$inx]->update($rec);
            }
        }
        if ($flush) {
            $this->flush();
        }
        $this->nRows = sizeof($this->data);
        return $this;
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
    public function remove($key, bool $flush = false)
    {
        if ($this->obfuscateRecKeys) {
            $key = $this->deObfuscateRecKey($key);
        }
        $key = $this->deObfuscateRecKey($key);
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
     * @return void
     * @throws \Exception
     */
    public function unlockRecs()
    {
       foreach ($this->data as $rec) {
           $rec->unlock(flush: false);
       }
       $this->flush(cacheOnly: true);
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
        if ($sessionIdInFile && ($sessionIdInFile[0]??'') === '!') {
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
     * Returns a normalized data-array, i.e. makes sure that all records have the same structure (i.e. same set of elements)
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

        // check whether has been 2d-normalized before:
        if ($this->data2DNormalized[$includeHeader][$includeMeta]??false) {
            $data2D = $this->data2DNormalized[$includeHeader][$includeMeta];
            $this->nCols = sizeof(reset($data2D));
            return $data2D;
        }

        $data = $this->data($includeMeta);
        if (!$data) {
            return []; // nothing to do
        }
        $data2D = [];
        $elementKeys = $this->getElementKeys($includeMeta);

        if ($includeHeader) {
            $data2D['_hdr'] = array_combine($elementKeys, $elementKeys);
        }
        foreach ($data as $key => $rec) {
            $newRec = [];
            foreach ($elementKeys as $elemKey) {
                $value = $rec[$elemKey] ?? '';
                // if element contains an array, stringify it:
                if (is_array($value)) {
                    $value = var_r($value, false, true);
                    $value = trim($value, '[] ');
                }
                $newRec[$elemKey] = $value;
            }
            $data2D[$key] = $newRec;
        }
        $this->nCols = sizeof(reset($data2D));
        $this->data2DNormalized[$includeHeader][$includeMeta] = $data2D;
        return $data2D;
    } // get2DNormalizedData


    /**
     * Returns an array containing all element keys found in all data records
     * @param bool $includeMeta
     * @return array
     */
    public function getElementKeys(bool $includeMeta = false): array
    {
        if (!$this->elementKeys) {
            return [];
        }
        $elementKeys = array_values($this->elementKeys);
        if ($includeMeta || $this->includeMeta) {
            $elementKeys['_origRecKey'] = '_origRecKey';
            $elementKeys['_reckey'] = '_reckey';
            $elementKeys[DATAREC_TIMESTAMP] = DATAREC_TIMESTAMP;
        }
        return $elementKeys;
    } // getElementKeys


    /**
     * Returns the index of one or multiple record(s) that match description.
     *    $inx = $ds->findRecKeyOf('Bob'); // case insensitive
     *    $inx = $ds->findRecKeyOf('M40ED116'); // uid instead of key
     *    $inx = $ds->findRecKeyOf('M40ED116', '_reckey');
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
            // allow for 'uid' instead of internally used '_reckey':
            if ($attribute === 'reckey') {
                $attribute = '_reckey';
            }
            foreach ($this->data as $recUid => $elem) {
                // check whether matches with '_reckey'-property:
                if (($attribute === '_reckey') && strcasecmp($elem->_reckey, $key) === 0) {
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
                if (($key === $recUid) || ($key === $elem->_origRecKey)) {
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
     *   $dataRec = $ds->find('M40ED116', '_reckey'); // uid (internally used label)
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
                if ($this->obfuscateRecKeys) {
                    $key = $this->deObfuscateRecKey($key);
                }
                $key = $this->deObfuscateRecKey($key);
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
        return is_array($this->data) ? sizeof($this->data) : 0;
    } // count


    /**
     * Returns the sum
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
            $this->flush(cacheOnly: true);
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
                $this->flush(cacheOnly: true);
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
        foreach ($this as $key => $elem) {
            if ($key === 'data') {
                if (is_array($elem)) {
                    foreach ($elem as $k => $v) {
                        $new->data[$k] = clone $v;
                    }
                } else {
                    $new->set('data', []);
                }
            } else {
                $new->set($key, $elem);
            }
        }
        return $new;
    } // clone


    /**
     * Writes datasource a) to master-file and b) to cache file
     * @return void
     * @throws \Exception
     */
    public function flush($cacheOnly = false)
    {
        if ($this->file) {
            if (!$cacheOnly) {
                $this->exportToMasterFile();
            }
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
     * @param mixed $toFile
     * @param mixed $includeMeta
     * @param mixed|null $includeHeader
     * @param mixed|null $fileType
     * @return string
     * @throws \Exception
     */
    public function export(mixed $toFile = false,
                           bool  $includeMeta = false,
                           bool  $includeHeader = true,
                           mixed $fileType = false): string
    {
        if ($fileType === true || $fileType === 'office') {
            if (self::$officeFormatAvailable) {
                $fileType = 'office';
            } else {
                $fileType = 'csv';
            }
        }
        if (!$toFile) {
            $toFile = $this->getDownloadFilename();
        }
        if (!$fileType) {
            $fileType = fileExt($toFile);
        }
        $toFile = resolvePath($toFile);
        if ($toFile === $this->file) {
            throw new \Exception("Export to original data file '$toFile' is not allowed.");
        }
        preparePath($toFile);
        try {
            if ($fileType === 'office') {
                $toFile .= 'xlsx';
                $this->exportToOfficeDoc($toFile, $includeMeta, $includeHeader);
            } elseif ($fileType === 'csv') {
                $toFile .= 'csv';
                $this->exportToCsv($toFile, $includeMeta, $includeHeader);
            } else {
                $data = $this->data($includeMeta);
                writeFileLocking($toFile, $data);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        return $toFile;
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


    /**
     * @param string $file
     * @param bool $includeMeta
     * @param bool $includeHeader
     * @return string
     * @throws \Exception
     */
    public function exportToOfficeDoc(string $file,
                                      bool   $includeMeta = false,
                                      bool   $includeHeader = true): string
    {
        if (!self::$officeFormatAvailable) {
            throw new \Exception("Support for Office Formats not available in this installation.");
        }
        $data2D = $this->get2DNormalizedData($includeHeader, $includeMeta);

        if (!$this->officeDoc) {
            $this->officeDoc = new OfficeFormat($data2D);
        }
        $this->officeDoc->export($file);
        return $file;
    } // exportToOfficeDoc


    /**
     * @param mixed $basename
     * @return string
     * @throws \Exception
     */
    protected function getDownloadFilename(mixed $basename = false): string
    {
        // use name of master file 
        $basename = $basename ?: $this->file;
        
        // determine download filename:
        if ($this->downloadFilename) {
            // basename can be overridden by option:
            $downloadFilename = base_name($this->downloadFilename, false);
        } else {
            $downloadFilename = base_name($basename, false);
        }
        // determine download path (i.e. random hash static per page):
        $dlLinkFile = resolvePath('~cache/links/'.str_replace('/','_', $basename)).'.txt';
        preparePath($dlLinkFile);
        if (file_exists($dlLinkFile)) {
            $dlHash = file_get_contents($dlLinkFile);
            if (filemtime($dlLinkFile) < (time() - 600)) { // max file age: 10 min
                $dlHash = createHash(8, false, true);
                file_put_contents($dlLinkFile, $dlHash);
            }
        } else {
            $dlHash = createHash(8, false, true);
            file_put_contents($dlLinkFile, $dlHash);
        }
        $file = TEMP_DOWNLOAD_PATH."$dlHash/$downloadFilename.";
        return $file;
    } // getDownloadFilename



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
        if (file_exists($this->file)) {
            $textEncoding = $this->options['textEncoding'] ?? false;
            $modified = false;
            try {
                // get raw data:
                $data = readFileLocking($this->file, $this->type, textEncoding: $textEncoding);
                if (!$data) {
                    return;
                }
                $modified = $this->importData($data);
                if ($modified) {
                    $this->exportToMasterFile();
                }

            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        } else {
            touch($this->file);
        }
    } // importFromMasterFile


    /**
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    private function importData(array $data): bool
    {
        $modified = false;
        // loop over loaded data, fix recKey and convert it to a DataRec:
        foreach ($data as $key => $rec) {
            if (!is_array($rec)) {
                throw new \Exception("Incompatible data: file '$this->file' does not contain an array of records.");
            }
            if (isHash($key) && ($this->masterFileRecKeyType !== 'origKey')) {
                $recKeyToUse = $key;
            } else {
                $rec['_origRecKey'] = $key;
                if (!isset($rec['_reckey'])) {
                    $rec['_reckey'] = createHash();
                    $modified = true;
                }
                $recKeyToUse = $rec['_reckey'];
            }

            $this->addRec($rec, flush: false, recKeyToUse: $recKeyToUse);
        }
        return $modified;
    } // importData


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

        $masterFileRecKeyType =   $this->masterFileRecKeyType;
        $recKeySort =             $this->options['masterFileRecKeySort'] ?? false;
        $recKeySortOnElement =    $this->options['masterFileRecKeySortOnElement'] ?? false;

        // determine which data element shall be used as rec-keys in the master-file:
        // 'masterFileRecKeys' : 'index,sort:name'
        if ($masterFileRecKeyType) {
            $parts = explodeTrim(',', $masterFileRecKeyType);
            foreach ($parts as $el) {
                // determine recKey: recKey|index|uid|timestamp or a data-rec-element as 'rec.xy'
                if (preg_match('/(index|_origRecKey|_reckey)/', $el, $m)) {
                    $masterFileRecKeyType = $m[1];
                } else if ($el === 'uid') { // synonym for _reckey
                    $masterFileRecKeyType = '_reckey';
                } elseif (preg_match('/.*\.\s*(.+)/', $el, $m)) { // rec.xy
                    $masterFileRecKeyType = '.' . $m[1];

                // determine sorting instructions
                } elseif (preg_match('/(sort|asc.*|desc.*):\s*(.+)/', $el, $m)) {
                    $recKeySort = $m[1];
                    $recKeySortOnElement = $m[2];
                }
            }
        }
        if (!$masterFileRecKeyType && $this->type === 'csv') {
            $masterFileRecKeyType = 'index';
        }

        $includeMeta = ($this->includeMeta !== null) ? $this->includeMeta :'_reckey';
        try {
            // sort:
            if ($recKeySort) {
                if ($recKeySort === 'sort' || $recKeySort === 'asc' || $recKeySort === true) {
                    $recKeySort = false;
                } elseif ($recKeySort === 'desc') {
                    $recKeySort = 'arsort';
                }
                $data = $this->clone()->sort($recKeySortOnElement, $recKeySort)->data($includeMeta, recKeyType: $masterFileRecKeyType);
                // update internal data after sorting:
                $this->data = [];
                $this->importData($data);

            } else {
                $data = $this->data($includeMeta, recKeyType: $masterFileRecKeyType);
            }

            writeFileLocking($this->file, $data, blocking: true);
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
            $ds = $this->clone();
            $data = &$ds->data;
            if (is_array($data)) {
                foreach ($data as $key => $rec) {
                    $data[$key]->parent = null;
                }
            } else {
                $data = [];
            }
            $ds->officeDoc = [];
            writeFileLocking($this->cacheFile, serialize($ds), blocking: true);

            // export debug copy if debug enabled:
            if (PageFactory::$debug) {
                $ds->debugDump(false, $this->cacheFile);
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
        $obj = unserialize(readFileLocking($this->cacheFile));
        $obj->options = $this->options;
        if (is_object($obj)) {
            foreach ($obj as $key => $value) {
                if (!str_contains('sess,officeFormatAvailable', $key)) {
                    $this->$key = $value;
                }
            }
            foreach ($this->data as $key => $rec) {
                $this->data[$key]->parent = $this;
            }
        } else {
            $this->data = [];
        }
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
        $sessionId = $sidToWrite = self::$sessionId = self::$sessionId ?: getSessionId();
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
                writeFileLocking($this->lockFile, $sidToWrite, blocking: true);
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
     * Unlocks datasource locked by this session.
     * @param $absAppRoot
     * @return void
     */
    public function unlockDatasource(): void
    {
        if (file_exists($this->lockFile)) {
            $sessionId = self::$sessionId ?: getSessionId();
            $sid = file_get_contents($this->lockFile);
            if ($sid === $sessionId) {
                @unlink($this->lockFile);
            }
            $this->readWriteMode = false;
        }
    } // unlockDatasource


    /**
     * @param string $key
     * @return string
     * @throws \Exception
     */
    protected function obfuscateRecKey(string $key): string
    {
        $session = kirby()->session();
        $tableRecKeyTab = $session->get('obfuscatedKeys');
        if (!$tableRecKeyTab || !($obfuscatedKey = array_search($key, $tableRecKeyTab))) {
            $obfuscatedKey = \PgFactory\PageFactory\createHash();
        }
        $tableRecKeyTab[$obfuscatedKey] = $key;
        $session->set('obfuscatedKeys', $tableRecKeyTab);
        return $obfuscatedKey;
    } // deObfuscateRecKey


    /**
     * @param string $key
     * @return string
     */
    protected function deObfuscateRecKey(string $key): string
    {
        $tableRecKeyTab = kirby()->session()->get('obfuscatedKeys');
        if ($tableRecKeyTab && (isset($tableRecKeyTab[$key]))) {
            $key = $tableRecKeyTab[$key];
        }
        return $key;
    } // deObfuscateRecKey



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
        $str .= "elementKeys: ".var_r($this->elementKeys, '', true)."\n";
        $str .= "cacheFile: $this->cacheFile\n";
        $str .= "lockFile: $this->lockFile\n";
        $str .= "maxRecLockTime: $this->maxRecLockTime\n";
        $str .= "maxRecBlockingTime: $this->maxRecBlockingTime\n";
        $str .= "readWriteMode: " . ($this->readWriteMode ? 'true' : 'false') . "\n";
        $str .= "options:\n  " . rtrim(str_replace("\n", "\n  ", Yaml::encode($this->options)));
        $str .= "\nDATA-RECORDS:\n";
        foreach ($this->data as $k => $rec) {
            $str .= $rec->debugDump(false);
        }
        if ($cacheFile) {
            file_put_contents($cacheFile.'.txt', $str);
            return '';
        } else {
            if ($asHtml) {
                return "<pre>$str</pre>\n";
            }
            return $str;
        }
    } // debugDump

} // DataSet