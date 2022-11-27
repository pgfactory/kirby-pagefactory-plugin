<?php

namespace Usility\PageFactory;

use Usility\PageFactory\OfficeFormat;

const TABLE_SUM_SYMBOL = '%sum';
const TABLE_COUNT_SYMBOL = '%count';

if (!function_exists('array_is_list')) {
    function array_is_list($array) {
        $keys = array_keys($array);
        return $keys !== array_keys($keys);
    }
}


class DataTable extends DataSet
{
    private $pfy;
    private $tableData;
    private $tableHeaders;
    private $tableClass;
    private $tableWrapperClass;
    private $dataReference;
    private $tableButtons;
    private $tableButtonDelete = false;
    private $tableButtonDownload = false;

    /**
     * @param string $file
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string $file, array $options = [], $pfy = null)
    {
        $this->pfy = $pfy;
        parent::__construct($file, $options);

        if (($_POST['pfy-reckey']??false) && isset($_GET['delete'])) {
            $this->handleTableRequests();
        }

        $this->inx = $options['inx'] ?? '1';
        $this->tableId = isset($options['tableId']) && $options['tableId'] ? $options['tableId'] : "pfy-table-$this->inx";
        $this->tableClass = isset($options['tableClass']) && $options['tableClass'] ? $options['tableClass'] : 'pfy-table';
        $this->tableWrapperClass = isset($options['tableWrapperClass']) && $options['tableWrapperClass'] ? $options['tableWrapperClass'] : 'pfy-table-wrapper';
        $this->dataReference = isset($options['dataReference']) && $options['dataReference'] ? $options['dataReference'] : '';
        $this->footers = isset($options['footers']) && $options['footers'] ? $options['footers'] : false;
        $this->caption = isset($options['caption']) && $options['caption'] ? $options['caption'] : false;
        $captionPosition = isset($options['captionPosition']) && $options['captionPosition'] ? $options['captionPosition'] : 'b';
        $this->captionAbove = $captionPosition[0] === 'a';
        $this->tableButtons = isset($options['tableButtons']) ? $options['tableButtons'] : false;
        $this->downloadFilename = isset($options['downloadFilename']) && $options['downloadFilename'] ? $options['downloadFilename'] : base_name($file, false);
        $this->showRowNumbers = isset($options['showRowNumbers']) ? $options['showRowNumbers'] : false;
        $this->showRowSelectors = isset($options['showRowSelectors']) && $options['showRowSelectors'] ? $options['showRowSelectors'] : false;
        $this->sort = isset($options['sort']) ? $options['sort'] : false;
        $this->export = isset($options['export']) ? $options['export'] : false;

        if (isset($this->options['tableHeaders'])) {
            $this->options['tableHeaders'] = $this->options['headers'];
        } elseif (isset($this->options['headers'])) {
            $this->options['tableHeaders'] = $this->options['headers'];
        } else {
            $this->options['tableHeaders'] = [];
        }
        $this->parseArrayArg('tableHeaders'); // options['tableHeaders'] or options['headers']

        if ($this->tableButtons === true) {
            $this->tableButtonDelete = true;
            $this->tableButtonDownload = true;
            $this->dataReference = true;
        } elseif ($this->tableButtons) {
            $this->tableButtonDelete = (strpos('delete', $this->tableButtons) !== false);
            $this->tableButtonDownload = (strpos('download', $this->tableButtonDownload) !== false);
            $this->dataReference = true;
        }
        if ($this->dataReference && is_string($this->dataReference)) {
            $this->dataReference = " data-ref='$this->dataReference'";
        } else {
            $this->dataReference = '';
        }


        if ($this->sort) {
            $this->sort($this->sort);
        } else {
            // DataSet may consist of inconsistently structured records. Thus, we need to narmalize first:
            $this->tableData = $this->get2DNormalizedData();
        }
    } // __construct


    /**
     * Renders the HTML table
     * @return string
     */
    public function render(): string
    {
        if (!$this->tableData) {
            return ''; // done if no data available
        }

        // Option row numbers:
        if ($this->showRowNumbers !== false) {
            $this->injectColumn('%row-numbers', '{{^ pfy-row-number-header }}');
        }

        // Option row selectors:
        if ($this->showRowSelectors) {
            $this->injectColumn('%row-selectors');
        }

        // render table header tags:
        $out = $this->renderTableHead();

        // render data cells:
        $out .= $this->renderTableBody();

        // render table footer:
        $out .= $this->renderTableFooter();

        // render table end tags:
        $out .= $this->renderTableTail();
        return $out;
    } // render



    public function sort($sortArg, $sortFunction = false)
    {
        parent::sort($sortArg, $sortFunction);
        $this->tableData = $this->get2DNormalizedData();
        return $this;
    } // sort


    /**
     * Returns an array containing element-labels, taking arg. tableHeaders into account.
     *  -> permits to specify column order and translate column headers.
     * @param bool $includeMeta
     * @return array
     */
    public function getElementLabels(bool $includeMeta = false): array
    {
        $elmentLabels = parent::getElementLabels($includeMeta);
        if (!$elmentLabels) {
            return $elmentLabels; // no labels -> nothing to do
        }
        if (!$this->tableHeaders || !is_array($this->tableHeaders)) {
            // no tableHeaders defined -> copy from $elmentLabels
            $this->tableHeaders = array_combine($elmentLabels, $elmentLabels);

        } else {
            if (!array_is_list($this->tableHeaders)) {
                // tableHeaders is scalar array -> convert to assoc array:
                $this->tableHeaders = array_combine($this->tableHeaders, $this->tableHeaders);
            }
            $elmentLabels = $this->tableHeaders;
        }
        $this->elementLabels = $elmentLabels;
        return $elmentLabels;
    } // getElementLabels


    /**
     * Injects a new column of data into the array.
     * Examples:
     *     injectColumn('%row-numbers', '#')
     *     injectColumn('%row-selectors')
     *     injectColumn('const', 'hdr const', -1)
     *     injectColumn(col: 3)
     * @param int $col          target column
     * @param mixed $newElement new element (as comma-separated-list), default is checkbox
     * @return array
     */
    private function injectColumn(string $newElement = '', mixed $headElement = '', int $col = 0): void
    {
        $data = &$this->tableData;
        $newCol = [];
        $fillWith = '';
        // negative col -> count from right, -1 == last or append
        if ($col < 0) {
            $col = $this->nCols + $col + 1;
        }
        $newElemName = $headElement ?: "col-$col";
        if ($newElement === '%row-selectors') {
            $fillWith = '<input type="checkbox"%nameAttr>';
            $newElemName = 'row-selector';

        } elseif ($newElement === '%row-numbers') {
            $newElemName = 'row-number';
            $newCol = range(0, $this->nRows - 1);

        } elseif (is_string($newElement)) {
            $newCol = explodeTrim(',', ",$newElement");
        }
        $newCol[0] = $headElement ?: $fillWith;
        $newCol = array_pad($newCol, $this->nRows, $fillWith);
        $i = 0;

        // fix $this->elementLabels accordingly:
        $name = translateToIdentifier($headElement, removeNonAlpha: true);
        array_splice_assoc($this->elementLabels, $col, $col, [$name => $headElement]);

        foreach ($data as $key => $rec) {
            $newElem = str_replace('%nameAttr'," name='pfy-reckey[]' value='$key'", $newCol[$i]);
            $newElem = [$newElemName => $newElem];
            array_splice_assoc($rec, $col, 0, $newElem);
            $data[$key] = $rec;
            $i++;
        }

        $this->nCols++;
    } // injectColumn


    /**
     * Parses a comma-separated-list of scalar elements or tuples
     * @param $key
     * @return array|mixed
     */
    private function parseArrayArg($key)
    {
        $var = $this->options[$key] ?? [];
        if (is_string($var)) {
            $var = explodeTrim(',', $var);
            $isAssoc = false;
            foreach ($var as $value) {
                if (strpos($value, ':') !== false) {
                    $isAssoc = true;
                    break;
                }
            }
            if ($isAssoc) {
                $tmp = [];
                foreach ($var as $value) {
                    if (preg_match('/(.*):\s*(.*)/', $value, $m)) {
                        $tmp[$m[1]] = trim($m[2], '\'"');
                    } else {
                        $tmp[$value] = $value;
                    }
                }
                $var = $tmp;
            }
        }
        $this->$key = $var;
        return $var;
    } // parseArrayArg


    /**
     * Renders table wrapper, <table> and <thead> section
     * @return string
     */
    private function renderTableHead(): string
    {
        $data = &$this->tableData;
        $out = "\n<div class='$this->tableWrapperClass'$this->dataReference>\n";
        $out .= $this->renderTableButtons();

        $out .= "<table id='$this->tableId' class='$this->tableClass'>\n";

        // caption:
        if ($this->caption) {
            $style = $this->captionAbove? '': ' style="caption-side: bottom;"'; // use style to push caption below table
            $caption = str_replace('%#', $this->inx, $this->caption);
            $out .= "  <caption$style>$caption</caption>\n";
        }

        $out .= "  <thead>\n    <tr class='pfy-table-header pfy-row-0'>\n";
        $headerRow = array_shift($data);
        $i = 0;
        foreach ($headerRow as $key => $elem) {
            $i++;
            $class = 'td-'.translateToIdentifier($key, removeNonAlpha: true);
            $out .= "      <th class='pfy-col-$i $class'>$elem</th>\n";
        }
        $out .= "    </tr>\n  </thead>\n";
        return $out;
    } // renderTableHead


    /**
     * Renders <tbody> section
     * @return string
     */
    private function renderTableBody(): string
    {
        $data = &$this->tableData;
        $out = "  <tbody>\n";
        $r = 0;
        foreach ($data as $key => $rec) {
            if ($this->dataReference) {
                $key = " data-reckey='$key'";
            } else {
                $key = '';
            }
            $r++;
            $out .= "    <tr class='pfy-row-$r'$key>\n";
            $c = 0;
            foreach ($rec as $k => $v) {
                $class = 'td-'.translateToIdentifier($k, removeNonAlpha: true);
                if ($this->dataReference) {
                    $k = " data-elemkey='$k'";
                }
                $c++;
                $out .= "      <td class='pfy-col-$c $class'>$v</td>\n";
            }
            $out .= "    </tr>\n";
        }
        $out .= "  </tbody>\n";
        return $out;
    } // renderTableBody


    /**
     * Renders <tfoot> section
     * @return string
     */
    private function renderTableFooter(): string
    {
        $data = &$this->tableData;
        $out = '';
        if ($this->footers) {
            $footer = $this->parseArrayArg('footers');
            $nCols = sizeof($this->elementLabels);
            $counts = $sums = array_combine(array_keys($this->elementLabels), array_fill(0, $nCols, 0));
            foreach ($data as $rec) {
                $i = 0;
                foreach ($rec as $key => $value) {
                    if (isset($footer[$key])) {
                        if ($footer[$key] === TABLE_SUM_SYMBOL && is_numeric($value)) {
                            $sums[$key] += $value;
                        } elseif ($footer[$key] === TABLE_COUNT_SYMBOL) {
                            $counts[$key]++;
                        }
                    }
                    $i++;
                }
            }
            $out .= "  <tfoot>\n";
            $out .= "    <tr>\n";
            $c = 1;
            foreach (array_keys($this->elementLabels) as $key) {
                if (isset($footer[$key])) {
                    if ($footer[$key] === TABLE_SUM_SYMBOL) {
                        $val = $sums[$key];
                    } elseif ($footer[$key] === TABLE_COUNT_SYMBOL) {
                        $val = $counts[$key];
                    } else {
                        $val = $footer[$key];
                    }
                } else {
                    $val = '';
                }
                $out .= "      <td class='pfy-col-$c'>$val</td>\n";
                $c++;
            }
            $out .= "    </tr>\n";
            $out .= "  </tfoot>\n";
        }
        return $out;
    } // renderTableFooter


    /**
     * Renders closing tags
     * @return string
     */
    private function renderTableTail(): string
    {
        $out = '';
        $out .= "</table>\n";
        if ($this->tableButtons) {
            $out .= "  </form>\n";
        }
        $out .= "</div> <!-- table-wrapper $this->tableWrapperClass -->\n\n";
        return $out;
    } // renderTableTail


    private function renderTableButtons()
    {
        $out = '';
        if ($this->tableButtons) {
            $out .= "  <form method='post'>\n";
        }
        if ($this->tableButtonDelete) {
            $icon = renderIcon('trash');
            $out .= "<button id='pfy-table-delete-submit' class='pfy-button pfy-button-lean' type='button' title='{{ pfy-table-delete-recs-title }}'>$icon</button>\n";
            $this->pfy::$pg->addJs('const pfyTableDeletePopup = "{{ pfy-table-delete-recs-popup }}";');
        }
        if ($this->tableButtonDownload) {
            $file = $this->exportDownloadDocs();
            $icon = renderIcon('cloud_download_alt');
            $out .= "<button id='pfy-table-download-start' class='pfy-button pfy-button-lean' type='button' data-file='$file' title='{{ pfy-opens-download }}'>$icon</button>\n";
        }
        return $out;
    } // renderTableButtons


    /**
     * Handles requests to delete records
     * @return void
     * @throws \Exception
     */
    private function handleTableRequests()
    {
        $keysSelected = $_POST['pfy-reckey'];
        if ($keysSelected) {
            foreach ($keysSelected as $key) {
                $this->remove($key);
            }
            $this->flush();
        }
        unset($_POST['pfy-reckey']);
        $msg = $this->pfy::$trans->getVariable('pfy-form-rec-deleted');
        reloadAgent(message: $msg);
    } // handleTableRequests


    private function exportDownloadDocs(): string
    {
//        $file = $this->getDownloadFilename();
//        $file = $this->export($file, fileType: true);
        $file = $this->export(fileType: true);
        return $file;
    } // exportDownloadDocs

} // DataTable