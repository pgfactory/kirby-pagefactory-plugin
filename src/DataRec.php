<?php


namespace Usility\PageFactory;

const REC_LOCK_AWAIT_CYCLE_TIME = 20000; // 20ms


class DataRec
{
    public $parent;
    public $recData;
    public $_timestamp = 0;
    public $_reckey;
    public $_lock = false;
    public $_lockedBy = false;
    public $_origRecKey;
    public $maxRecLockTime;

    public function __construct($recKey, $recData, $parent = null)
    {
        $this->_origRecKey = $recKey;
        $this->recData = &$recData;
        $this->parent = $parent;
        if (isset($recData['_origRecKey'])) {
            $this->_origRecKey = $recData['_origRecKey'];
            unset($recData['_origRecKey']);
        }
        if (isset($recData['_reckey'])) {
            $this->_reckey = $recData['_reckey'];
        } else {
            $this->_reckey = createHash();
        }
        if (isset($recData[DATAREC_TIMESTAMP])) {
            $this->_timestamp = $recData[DATAREC_TIMESTAMP];
        } else {
            $this->_timestamp = time();
        }

        $i = 0;
        foreach ($recData as $k => $v) {
            $elemKeyNormalized = translateToIdentifier($k, toLowerCase:false);

            // add key to list of elementKeys (unless it starts with _)
            if ($k[0] !== '_') {
                $parent->elementKeys[$elemKeyNormalized] = $k;
                // if key and normalized key differ, swap:
                if ($k !== $elemKeyNormalized) {
                    array_splice_assoc($recData, $i, 1, [$elemKeyNormalized => $v]);
                }
            }
            $i++;
        }
        $this->maxRecLockTime = $this->parent->get('maxRecLockTime');
    } // __construct


    /**
     * Returns the DataRec's payload, i.e. $this->recData, includes/excludes meta data
     * @param bool $includeMetaFields
     * @return mixed
     */
    public function data(bool $includeMetaFields = false): array
    {
        $recData = $this->recData;
        if ($includeMetaFields) {
            $recData['_origRecKey'] = $this->_origRecKey;
            $recData['_reckey'] = $this->_reckey;
            $recData[DATAREC_TIMESTAMP] = $this->_timestamp;
        } else {
            if (isset($recData['_origRecKey'])) {
                unset($recData['_origRecKey']);
            }
            if (isset($recData['_reckey'])) {
                unset($recData['_reckey']);
            }
            if (isset($recData[DATAREC_TIMESTAMP])) {
                unset($recData[DATAREC_TIMESTAMP]);
            }
        }
        return $recData;
    } // data


    /*
     * Updates the DataRec's payload data, overwrites only supplied elements
     * @param $recKey       element-name or array of key->value tuples
     * @param $value
     * @param bool $flush
     * @return mixed
     * @throws \Exception
     */
    public function update($recKey, $value = null, bool $flush = false): mixed
    {
        if ($this->isLocked()) {
            throw new \Exception("DataRec '".$this->_origRecKey."' is locked");
        }
        if ($value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $this->setRecData($k, $v);
                }
            } else {
                $this->setRecData($recKey, $value);
            }

        } elseif (is_array($recKey)) {
            foreach ($recKey as $k => $v) {
                $this->setRecData($k, $v);
            }
        }
        $this->_timestamp = time();
        if ($flush) {
            $this->parent->flush();
        }
        return $this;
    } // update


    /**
     * Returns the DataRec's _reckey
     * @return bool|mixed
     */
    public function dataRecKey(): string
    {
        return $this->_reckey;
    } // dataRecKey


    /**
     * @param bool $flush
     * @return void
     * @throws \Exception
     */
    public function delete(bool $flush = false): void
    {
        $this->remove($flush);
    } // delete


    /**
     * Deletes this data record
     * @param bool $flush
     * @return void
     * @throws \Exception
     */
    public function remove(bool $flush = false): object
    {
        if ($this->isLocked()) {
            throw new \Exception("DataRec '".$this->_origRecKey."' is locked");
        }
        $this->parent->remove($this->_reckey);
        if ($flush) {
            $this->parent->flush();
        }
        return $this->parent;
    } // remove


    /**
     * @param bool $byMeIncluded
     * @return bool
     */
    public function isLocked(bool $byMeIncluded = false): bool
    {
        if (!$this->_lock) {
            return false;
        }
        // check whether lock timed out:
        if ((int)$this->_lock < (time() - $this->maxRecLockTime)) {
            $this->_lock = false;
            $this->_lockedBy = false;
            return false;
        }
        // lock by self or somebody else:
        if ($this->_lockedBy !== getSessionId()) {
            return true;
        } else {
            return $byMeIncluded;
        }
    } // isLocked


    /**
     * @param bool $blocking
     * @return bool
     * @throws \Exception
     */
    public function lock(bool $blocking = false): bool
    {
        if (!$this->isLocked()) {
            $this->set('_lock', time(), flush: true, ignoreLock: true);
            $this->set('_lockedBy', getSessionId(), flush: true, ignoreLock: true);
            return true;

        } elseif ($blocking) {
            $tMax = time() + $this->parent->get('maxRecBlockingTime');
            while (time() < $tMax) {
                usleep(REC_LOCK_AWAIT_CYCLE_TIME);
                if (!$this->get('_lock')) {
                    $this->set('_lock', time(), flush: true, ignoreLock: true);
                    $this->set('_lockedBy', getSessionId(), flush: true, ignoreLock: true);
                    return true;
                }
            }
            throw new \Exception("Timeout while waiting for DataRec '".$this->_origRecKey."' to be unlocked");
        } else {
            throw new \Exception("DataRec '".$this->_origRecKey."' is locked");
        }
    } // lock


    /**
     * @param bool $force
     * @return bool
     */
    public function unlock(bool $force = false): bool
    {
        // if force unlock or locked by self or lock timed out:
        if ($force ||
                ($this->_lockedBy === getSessionId()) ||
                ($this->_lock < (time() - $this->maxRecLockTime))) {
            $this->set('_lock', false, flush: true, ignoreLock: true);
            $this->set('_lockedBy', false, flush: true, ignoreLock: true);
            return true;
        } else {
            return false;
        }
    } // unlock


    /**
     * @return int
     */
    public function lastModified(): int
    {
        return $this->_timestamp;
    } // lastModified


    /**
     * @param string $key
     * @param mixed $value
     * @param bool $flush
     * @param bool $toParent
     * @return false|void
     */
    public function set(string $key, mixed $value, bool $flush = false, bool $toParent = true, $ignoreLock = false)
    {
        if ($this->isLocked() && !$ignoreLock) {
            return false;
        }
        if ($toParent) {
            $this->parent->setData($this->_reckey, $key, $value, flush: $flush);
        }
        $this->$key = $value;
    } // set


    /**
     * @param string $key
     * @param mixed $value
     * @param bool $flush
     * @param bool $toParent
     * @return false|void
     */
    public function setRecData(string $key, mixed $value, bool $flush = false, bool $toParent = true)
    {
        if ($this->isLocked()) {
            return false;
        }
        if ($toParent) {
            $this->parent->setRecData($this->_reckey, $key, $value, flush: $flush);
        }
        $this->recData[$key] = $value;
    } // setRecData


    /**
     * @param string $key
     * @return false
     */
    public function get(string $key)
    {
        return $this->$key??false;
    } // get


    /**
     * @param string $key
     * @return false|mixed
     */
    public function getRecData(string $key)
    {
        return $this->recData[$key]??false;
    } // getRecData


    /**
     * @param bool $asHtml
     * @return string
     */
    public function debugDump(bool $asHtml = true)
    {
        $str = '';
        $str .= "parent: [OMITTED]\n";
        $str .= "key: ".$this->_origRecKey."\n";
        $str .= "_timestamp: $this->_timestamp\n";
        $str .= "_reckey: $this->_reckey\n";
        $str .= "_lock: " . ($this->_lock ? 'true' : 'false') . "\n";
        $str .= "_lockedBy: $this->_lockedBy\n";
        $str .= "\nDATA:\n";
        foreach ($this->recData as $k => $v) {
            $str .= "    $k: $v\n";
        }
        if ($asHtml) {
            return "<pre>$str</pre>\n";
        }
        return $str;
    } // debugDump
} // DataRec