<?php

namespace Usility\PageFactory;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Ods;

use cebe\markdown\MarkdownExtra;

class OfficeFormat
{
    private $spreadsheet;
    private $sheet;

    public function __construct($data)
    {
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
        $r = 1;
        foreach ($data as $rec) {
            $c = 0;
            foreach ($rec as $value) {
                $c1 = intval($c / 26);
                $c1 = $c1 ? chr( 65 + $c1 ) : '';
                $c2 = chr( 65 + $c % 26 );
                $cellId = "$c1$c2$r";
                $c++;
                $this->sheet->setCellValue($cellId, $value);
            }
            $r++;
        }
    } // __construct


    public function export(string $file): void
    {
        $this->exportToXlsx($file);
        //        $this->exportToOds($file);
    } // export


    public function exportToXlsx(string $file): void
    {
        $file = resolvePath($file);
        $file = fileExt($file, true).'.xlsx';
        preparePath($file);
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($file);
    } // exportToXlsx


    //    public function exportToOds(string $file): void
    //    {
    //        $file = resolvePath($file);
    //        $file = fileExt($file, true).'.ods';
    //        preparePath($file);
    //        $writer = new Ods($this->spreadsheet);
    //        $writer->save($file);
    //    } // exportToOds
} // OfficeFormat