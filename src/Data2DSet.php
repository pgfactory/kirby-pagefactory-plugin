<?php
/*
 * Data2DSet
 * Convention for data-elements containing arrays:
 *      elemKey => [
 *          '_'  => 'summary of options',
 *          'opt1' => bool,
 *          'opt2' => bool,
 *          ...
 *      ]
 */

namespace PgFactory\PageFactory;

use PgFactory\PageFactory\DataSet;

class Data2DSet extends DataSet
{
    private mixed $includeSystemElements;
    private array $data2D = [];
    private array $recElements = [];  // recKey:Label
    private array $recKeys = [];
    private bool  $markLocked = false;
    private string $placeholderForUndefined = '?';

    public function __construct(string $file, array $options = [])
    {
        $this->markLocked = $options['markLocked'] ?? false;
        parent::__construct($file, $options);
        $unknown = $options['unknownValue'] ?? ($options['placeholderForUndefined']??false);
        if ($unknown !== false) {
            $this->placeholderForUndefined = $unknown;
        }
        $this->includeSystemElements = $this->options['includeSystemElements']??false;
    } // __construct


    /**
     * @param $headerElems
     * @return array
     * @throws \Exception
     */
    public function getNormalized2Ddata($headerElems = true): array
    {
        $this->determineRecElements($headerElems);

        $data2D = $this->normalizeData();

        $this->nRows = sizeof($data2D)-1;
        $this->nCols = sizeof($this->recElements);

        if ($this->options['obfuscateRows']??false) {
            $this->obfuscateRows($this->options['obfuscateRows']);
        }

        if ($this->options['minRows']??false) {
            $this->addRows($this->options['minRows']);
        }

        return $this->data2D;
    } // getNormalized2Ddata


    /**
     * @param array|bool $headerElems
     * @return void
     */
    private function determineRecElements(array|bool $headerElems): void
    {
        if (!$this->data) {
            return ;
        }

        if ($headerElems) {
            if ($headerElems === true) {
                // derive headerElems from first data record:
                $rec0 = reset($this->data);
                $dataRec0 = $rec0->recData;
                $elementKeys = array_keys($dataRec0);
                if ($this->includeSystemElements) {
                    if (!in_array(DATAREC_TIMESTAMP, $elementKeys)) {
                        $elementKeys[] = DATAREC_TIMESTAMP;
                    }
                    if (!in_array('_reckey', $elementKeys)) {
                        $elementKeys[] = '_reckey';
                    }
                } else {
                    $elementKeys = array_filter($elementKeys, function ($e) {
                        return (($e[0]??'') !== '_');
                    });
                    $elementKeys = array_values($elementKeys);
                }
                $headerElems = array_combine($elementKeys, $elementKeys);

            } else {
                $elementKeys = array_keys($headerElems);
                if ($this->includeSystemElements) {
                    if (!in_array(DATAREC_TIMESTAMP, $elementKeys)) {
                        $headerElems[DATAREC_TIMESTAMP] = DATAREC_TIMESTAMP;
                    }
                    if (!in_array('_reckey', $elementKeys)) {
                        $headerElems['_reckey'] = '_reckey';
                    }
                } else {
                    $headerElems = array_filter($headerElems, function ($e) {
                        if (!$e) {
                            return false;
                        }
                        return (((string)$e)[0] !== '_');
                    });
                }
            }
            if ($this->markLocked) {
                $headerElems['_locked'] = '_locked';
            }
        } else {
            $headerElems = [];
        }

        $this->recElements = $headerElems;
        $this->recKeys = array_keys($headerElems);
    } // determineRecElements


    /**
     * @return array
     * @throws \Exception
     */
    private function normalizeData(): array
    {
        $placeholderForUndefined = $this->placeholderForUndefined;
        if (!PageFactory::$debug) {
            $placeholderForUndefined = '';
        }
        $data2D = [];
        $data2D['_hrd'] = $this->recElements;
        foreach ($this->data(true) as $recKey => $rec) {
            $newRec = [];
            foreach ($this->recElements as $key => $value) {
                if (isset($rec[$key])) {
                    $val = $rec[$key];
                    if ($key === DATAREC_TIMESTAMP) {
                        $newRec[$key] = date('Y-m-d H:i', $val);
                    } elseif (is_bool($val)) {
                        $newRec[$key] = $val? '1':'0';
                    } elseif (is_scalar($val)) {
                        $newRec[$key] = $val;
                    } elseif (is_array($val) && isset($val['_'])) {
                        $newRec[$key] = $val['_'];
                    } elseif (is_array($val)) {
                        $newRec[$key] = json_encode($val);
                    }
                } else {
                    // no elem found, check for indexed element of type 'a.b':
                    if (str_contains($key, '.')) {
                        $indexes = explode('.', $key);
                        $v = $rec;
                        foreach ($indexes as $index) {
                            if (isset($v[$index])) {
                                $v = $v[$index];
                            } elseif (is_scalar($v)) {
                                $v = ($v === $value);
                            } else {
                                $newRec[$value] = $placeholderForUndefined;
                                continue 2;
                            }
                        }
                        $newRec[$key] = is_bool($v) ? ($v?'1':'0'): $v;

                        // check whether indirect data access via recLabels works:
                    } elseif (isset($rec[$this->recElements[$key]])) {
                        $newRec[$key] = $rec[$this->recElements[$key]];

                    // no matching data found -> mark as unknown
                    } else {
                        $newRec[$key] = ($key === '_locked')? false : $placeholderForUndefined;
                    }
                }
            }
            $data2D[$recKey] = $newRec;
        }
        $this->data2D = $data2D;
        return $data2D;
    } // normalizeData


    /**
     * @param array $keys
     * @param array $values
     * @return array
     */
    private function arrayCombine(array $keys, array $values): array
    {
        $nKeys = sizeof($keys);
        $nValues = sizeof($values);
        if ($nKeys < $nValues) {
            for ($i=$nKeys; $i<$nValues; $i++) {
                $keys[$i] = translateToIdentifier($values[$i]??'');
            }
        } elseif ($nKeys > $nValues) {
            for ($i=$nValues; $i<$nKeys; $i++) {
                $values[$i] = '';
                // originally: $values[$i] = str_replace('_', ' ', ($keys[$i]??''));
            }
        }
        return array_combine($keys, $values);
    } // arrayCombine


    /**
     * @param array $rows
     * @return void
     */
    private function obfuscateRows(array $rows): void
    {
        $data2D = &$this->data2D;
        foreach ($data2D as $row => $rec) {
            if ($row === '_hdr') {
                continue;
            }
            foreach ($rec as $key => $value) {
                if (in_array($key, $rows)) {
                    $data2D[$row][$key] = '*****';
                }
            }
        }
    } // obfuscateRows


    /**
     * @param int $minRows
     * @return void
     */
    private function addRows(int $minRows): void
    {
        if (($minRows) && ($minRows > $this->nRows)) {
            $data2D = &$this->data2D;
            $emptyRec = self::arrayCombine($this->recKeys, array_fill(0, $this->nCols, ''));
            $emptyRecs = array_fill(0, ($minRows - $this->nRows), $emptyRec);
            $data2D = array_merge_recursive($data2D, $emptyRecs);
            $this->nRows = sizeof($data2D)-1;
        }
    } // addRows


    //=== Table Export =============================
    /**
     * @param string $key
     * @return array|false
     */
    public function getRec(string $key): array|false
    {
        if ($rec = ($this->data[$key]??false)) {
            return $rec->recData;
        }
        return false;
    } // getRec


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
        preparePath($toFile, 0755);
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
     * @return void
     * @throws \Exception
     */
    public function exportToCsv(string $file): void
    {
        $file = resolvePath($file);
        $fp = fopen($file, 'w');
        foreach ($this->data2D as $fields) {
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
        if (!$this->officeDoc) {
            $this->officeDoc = new OfficeFormat($this->data2D);
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
                $dlHash = createHash(8, type:'l');
                file_put_contents($dlLinkFile, $dlHash);
            }
        } else {
            $dlHash = createHash(8, type:'l');
            file_put_contents($dlLinkFile, $dlHash);
        }
        $file = TEMP_DOWNLOAD_PATH."$dlHash/$downloadFilename.";
        return $file;
    } // getDownloadFilename

} // Data2DSet