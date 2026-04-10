<?php

namespace App\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class SparepartreportExport implements FromView,WithColumnFormatting
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('export.SparepartreportExport', [
            'data' => $this->data,
        ]);
    }
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT, //
            'C' => NumberFormat::FORMAT_TEXT, //
            'D' => NumberFormat::FORMAT_NUMBER_00, //
            'E' => NumberFormat::FORMAT_TEXT, // สี
            'F' => NumberFormat::FORMAT_TEXT, // ประเภทรถ
            'G' => NumberFormat::FORMAT_TEXT, // ประเภทเชื้อเพลิง
            'H' => NumberFormat::FORMAT_TEXT, // ประเภทรถ
            'I' => NumberFormat::FORMAT_TEXT, // ประเภทรถ
        ];
    }
}
