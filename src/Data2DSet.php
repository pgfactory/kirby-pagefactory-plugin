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

if (!defined('DATAREC_TIMESTAMP')) {
    define('DATAREC_TIMESTAMP', '_timestamp');
}
if (!defined('DATAREC_RECKEY')) {
    define('DATAREC_RECKEY', '_reckey');
}

class Data2DSet
{
    private string $file = '';
    private string $downloadFilename = '';
    private ?OfficeFormat $officeDoc = null;
    private array $options = [];
    private array $data = [];
    private array $data2D = [];
    private array $colHeaders = [];  // recKey => Label
    private bool  $markLocked;
    private string|false $order;
    private array|false $filter;
    private ?DataStore $db = null;
    private int $nRows = 0;
    private string $placeholderForUndefined = '';
    public static bool $officeFormatAvailable = false;


    /**
     * @param string|array $file
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string|array $file, array $options = [])
    {
        $this->parseOptions($options);

        if (is_array($file)) {
            $this->data = $file;
        } elseif ($file === '' || $file === '1') {
            $this->data = [];
        } else {
            $this->file = $file;
            $this->db = new DataStore($file, $options);
            $this->data = $this->db->data(includeMetaFields: true);
            $this->checkDataIntegrity();
        }

        $this->normalizeData();
    } // __construct


    /**
     * @return void
     * @throws \Exception
     */
    private function checkDataIntegrity(): void
    {
        $needsUpdate = false;
        $data = $this->data;
        foreach ($data as $key => $rec) {
            if (!($rec[DATAREC_RECKEY] ?? false)) {
                $needsUpdate = true;
                $data[$key][DATAREC_RECKEY] = createHash();
            }
        }
        if ($needsUpdate) {
            $this->db->write($data);
            reloadAgent();
        }
    } // checkDataIntegrity


    /**
     * @return array
     */
    public function data(): array
    {
        return $this->data2D;
    } // data


    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->nRows;
    } // getSize


    /**
     * @return array
     */
    public function getColHeaders(): array
    {
        return $this->colHeaders;
    } // getColHeaders


    /**
     * @param array $rec
     * @param bool $flush
     * @param string|false $recKeyToUse
     * @return bool
     * @throws \Exception
     */
    public function addRec(array $rec, bool $flush = true, string|false $recKeyToUse = false): bool
    {
        return $this->db->addRec($rec, $flush, $recKeyToUse);
    } // addRec


    /**
     * @param string $key
     * @return mixed
     * @throws \Exception
     */
    public function find(string $key): mixed
    {
        return $this->db->find($key);
    } // find


    /**
     * @param string $key
     * @return void
     * @throws \Exception
     */
    public function deleteRec(string $key): void
    {
        $this->db->deleteRec($key);
    } // deleteRec


    /**
     * @return void
     * @throws \Exception
     */
    public function flush(): void
    {
        $this->db->flush();
    } // flush


    /**
     * @return void
     * @throws \Exception
     */
    public function purge(): void
    {
        $this->db->purge();
    } // purge


    /**
     * @return array
     * @throws \Exception
     */
    public function normalizeData(): array
    {
        $this->determineColHeaders();

        $this->data2D = $this->doNormalizeData();

        $this->nRows = sizeof($this->data2D) - 1;

        $this->obfuscateRequestedColumns();

        if ($this->order) {
            $this->sortData();
        }
        if ($this->filter) {
            $this->filterData();
        }

        return $this->data2D;
    } // normalizeData


    /**
     * @return array
     */
    private function doNormalizeData(): array
    {
        $data = $this->data;

        // determine colHeaders:
        $colHeaders = $this->colHeaders;
        if (!array_is_list($colHeaders)) {
            $colHeaders = array_keys($colHeaders);
        }

        // assemble 2D data:
        $data2D = [];
        foreach ($data as $recKey => $rec) {
            $newRec = [];
            foreach ($colHeaders as $elemKey) {
                if (isset($rec[$elemKey])) {
                    $newRec[$elemKey] = $this->normalizeDataElement($elemKey, $rec[$elemKey]);

                } else {
                    // no elem found, check for indexed element of type 'a.b':
                    if (str_contains($elemKey, '.')) {
                        $indexes = explode('.', $elemKey);
                        $v = $rec;
                        foreach ($indexes as $index) {
                            if (isset($v[$index])) {
                                $v = $v[$index];
                            } elseif (is_scalar($v)) {
                                $v = ($v === $index);
                            } else {
                                $newRec[$elemKey] = $this->placeholderForUndefined;
                                continue 2;
                            }
                        }
                        $newRec[$elemKey] = $this->normalizeDataElement($elemKey, $v);

                    // check whether indirect data access via recLabels works:
                    } elseif (isset($rec[($colHeaders[$elemKey] ?? false)])) {
                        $newRec[$elemKey] = $this->normalizeDataElement($elemKey, $rec[$colHeaders[$elemKey]]);

                    // no matching data found -> mark as unknown
                    } else {
                        $newRec[$elemKey] = ($elemKey === '_locked') ? false : $this->placeholderForUndefined;
                    }
                }
            }
            $data2D[$recKey] = $newRec;
        }
        return $data2D;
    } // doNormalizeData


    /**
     * @param string $key
     * @param mixed $value
     * @return string
     */
    private function normalizeDataElement(string $key, mixed $value): string
    {
        $newValue = '';
        if ($key === DATAREC_TIMESTAMP) {
            $newValue = date('Y-m-d H:i', $value);
        } elseif (is_bool($value)) {
            $newValue = $value ? '1' : '0';
        } elseif (is_scalar($value)) {
            $newValue = (string)$value;
        } elseif (is_array($value) && isset($value['_'])) {
            $newValue = (string)$value['_'];
        } elseif (is_array($value)) {
            $newValue = json_encode($value) ?: '';
        }
        return $newValue;
    } // normalizeDataElement


    /**
     * @return void
     */
    private function sortData(): void
    {
        $sortElem = $this->order;
        $reversed = $this->options['reversed'] ?? false;
        $data = $this->data2D;
        uasort($data, function ($a, $b) use ($sortElem) {
            return strcmp($a[$sortElem] ?? '', $b[$sortElem] ?? '');
        });

        if ($reversed) {
            $data = array_reverse($data, true);
        }
        $this->data2D = $data;
    } // sortData


    /**
     * @return void
     */
    private function filterData(): void
    {
        $data = $this->data2D;

        $filterElem = $this->filter['name'] ?? false;
        $filterValue = $this->filter['value'] ?? false;
        if (!$filterElem || !$filterValue) {
            return;
        }
        $filterOp = $this->filter['op'] ?? '===';

        $data = array_filter($data, function ($rec) use ($filterElem, $filterValue, $filterOp) {
            $v = $rec[$filterElem] ?? '';
            return match ($filterOp) {
                '==' => $v == $filterValue,
                '!=' => $v != $filterValue,
                '!==' => $v !== $filterValue,
                '>' => $v > $filterValue,
                '>=' => $v >= $filterValue,
                '<' => $v < $filterValue,
                '<=' => $v <= $filterValue,
                default => $v === $filterValue,  // '===' and any unknown op
            };
        });

        $this->data2D = $data;
    } // filterData


    /**
     * @return void
     */
    private function obfuscateRequestedColumns(): void
    {
        $cols = $this->options['obfuscateCols'] ?? [];
        if (!$cols) {
            return;
        }
        foreach ($cols as $i => $patt) {
            if (preg_match('/^(.*?)\*.*/', $patt, $m)) {
                $patt = strtolower($m[1]);
                foreach (array_keys($this->colHeaders) as $key) {
                    if (str_starts_with(strtolower($key), $patt)) {
                        $cols[$i] = $key;
                    }
                }
            }
        }
        $data2D = &$this->data2D;
        foreach ($data2D as $row => $rec) {
            foreach ($rec as $key => $value) {
                if (in_array($key, $cols)) {
                    $data2D[$row][$key] = '*****';
                }
            }
        }
    } // obfuscateRequestedColumns




    //=== Table Export =============================
    /**
     * @param string $key
     * @return array|false
     */
    public function getRec(string $key): array|false
    {
        return ($this->data[$key] ?? false);
    } // getRec


    /**
     * General purpose export to file
     *    $ds->export('output/export.yaml');  -> to yaml file
     *    $ds->export('output/export.json');  -> to json file
     *    $ds->export('output/export1.csv');  -> to csv file *)
     *      *) before exporting to csv, data is 2D-normalized to fit in a rectangular table
     * @param string|false $targetFile
     * @param string|bool $fileType
     * @return string
     * @throws \Exception
     */
    public function export(string|false $targetFile = false, string|bool $fileType = false): string
    {
        if ($fileType === true || $fileType === 'office') {
            $fileType = self::$officeFormatAvailable ? 'office' : 'csv';
        }

        if (!$targetFile) {
            $targetFile = $this->getDownloadFilename();
        }
        if (!$fileType) {
            $fileType = fileExt($targetFile);
        }
        $toFile = Utils::resolvePath($targetFile);
        if ($toFile === $this->file) {
            throw new \Exception("Export to original data file '$toFile' is not allowed.");
        }
        preparePath($toFile, 0755);

        if (!$this->data2D) {
            return '';
        }

        if ($fileType === 'office') {
            $targetFile .= 'xlsx';
            $toFile .= 'xlsx';
            $this->exportToOfficeDoc($toFile);
        } elseif ($fileType === 'csv') {
            $targetFile .= 'csv';
            $toFile .= 'csv';
            $this->exportToCsv($toFile);
        } else {
            $data = $this->data;
            writeFileLocking($toFile, $data);
        }

        return Utils::resolveUrls($targetFile, forResoucres:true);
    } // export


    /**
     * Export to a csv file
     *    $ds->exportToCsv('output/export.csv');  -> to csv file
     * Before exporting, data is normalized to fit in a rectangular table
     * @param string $file
     * @return void
     * @throws \Exception
     */
    public function exportToCsv(string $file): void
    {
        $file = Utils::resolvePath($file);
        $fp = fopen($file, 'w');
        foreach ($this->data2D as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);
    } // exportToCsv


    /**
     * @param string $file
     * @return string
     * @throws \Exception
     */
    public function exportToOfficeDoc(string $file): string
    {
        if (!self::$officeFormatAvailable) {
            throw new \Exception("Support for Office Formats not available in this installation.");
        }
        if (!$this->officeDoc) {
            $data = $this->prependHeaderRow($this->data2D);
            $this->officeDoc = new OfficeFormat($data);
        }
        $this->officeDoc->export($file);
        return $file;
    } // exportToOfficeDoc


    /**
     * @param array $data
     * @return array|array[]
     */
    private function prependHeaderRow(array $data): array
    {
        $data = ['header' => $this->colHeaders] + $data;
        return $data;
    } // prependHeaderRow


    /**
     * @param string|false $basename
     * @return string
     * @throws \Exception
     */
    protected function getDownloadFilename(string|false $basename = false): string
    {
        // use name of master file
        $basename = $basename ?: basename($this->file);

        // determine download filename:
        if ($this->downloadFilename) {
            // basename can be overridden by option:
            $downloadFilename = base_name($this->downloadFilename, false);
        } elseif ($basename) {
            $downloadFilename = base_name($basename, false);
        } else {
            $downloadFilename = $this->options['tableName'] ?? 'download';
            $basename = base_name($downloadFilename, false);
        }
        // determine download path (i.e. random hash static per page):
        $dlLinkFile = Utils::resolvePath('~cache/links/'.str_replace('/','_', $basename)).'.txt';
        preparePath($dlLinkFile);
        if (file_exists($dlLinkFile) && filemtime($dlLinkFile) >= (time() - 600)) {
            $dlHash = file_get_contents($dlLinkFile);
        } else {
            $dlHash = createHash(8, type:'l');
            file_put_contents($dlLinkFile, $dlHash);
        }
        $file = PFY_TEMP_DOWNLOAD_PATH."$dlHash/$downloadFilename.";
        return $file;
    } // getDownloadFilename


    /**
     * @return void
     */
    private function determineColHeaders(): void
    {
        $headers = $this->options['headers'] ?? false;
        if ($headers) {
            if ($headers === true) {
                if ($this->data) {
                    $rec0 = reset($this->data);
                    $keys = array_keys($rec0);
                    $this->colHeaders = array_combine($keys, $keys);
                } else {
                    $this->colHeaders = [];
                }
            } elseif (is_string($headers)) {
                $keys = explodeTrim(',', $headers);
                $this->colHeaders = array_combine($keys, $keys);
            } else {
                $this->colHeaders = $headers;
            }
            return;
        }

        $data = $this->data;
        $colHeaders = [];
        foreach ($data as $rec) {
            foreach ($rec as $colKey => $col) {
                $colHeaders[$colKey] = $colKey;
            }
        }
        if ($this->markLocked) {
            $colHeaders['_locked'] = '_locked';
        }
        $this->colHeaders = $colHeaders;
    } // determineColHeaders


    /**
     * @param string $recKey
     * @return bool
     */
    public function isLocked(string $recKey): bool
    {
        if (!$this->db) {
            return false;
        }
        return $this->db->isRecLocked($recKey);
    } // isLocked


    /**
     * @return bool
     */
    public static function checkOfficeFormatIsAvailable(): bool
    {
        self::$officeFormatAvailable = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
        return self::$officeFormatAvailable;
    } // checkOfficeFormatIsAvailable


    /**
     * @param array $options
     * @return void
     */
    private function parseOptions(array $options): void
    {
        $this->options = $options;
        $this->markLocked = $options['markLocked'] ?? false;

        $unknown = $options['unknownValue'] ?? ($options['placeholderForUndefined'] ?? false);
        if ($unknown !== false) {
            $this->placeholderForUndefined = $unknown;
        }
        $this->order = ($options['order'] ?? false) ?: ($options['sort'] ?? false);
        $this->filter = $options['filter'] ?? false;
        $this->downloadFilename = $options['downloadFilename'] ?? '';

    } // parseOptions

} // Data2DSet
