<?php

namespace PgFactory\PageFactory;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OfficeFormat
{
    private Spreadsheet $spreadsheet;

    /**
     * @param array $data — 2D array of cell values
     */
    public function __construct(array $data)
    {
        $this->spreadsheet = new Spreadsheet();
        $sheet = $this->spreadsheet->getActiveSheet();
        $r = 1;
        foreach ($data as $rec) {
            $c = 0;
            foreach ($rec as $value) {
                $c1 = intval($c / 26);
                $c1 = $c1 ? chr(64 + $c1) : '';
                $c2 = chr(65 + $c % 26);
                $cellId = "$c1$c2$r";
                $c++;
                $sheet->setCellValue($cellId, $value);
            }
            $r++;
        }
    } // __construct


    /**
     * @param string $file
     * @return void
     */
    public function export(string $file): void
    {
        $this->exportToXlsx($file);
    } // export


    /**
     * @param string $file
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportToXlsx(string $file): void
    {
        $file = Utils::resolvePath($file);
        $file = fileExt($file, true) . '.xlsx';
        preparePath($file);
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($file);
    } // exportToXlsx
} // OfficeFormat
