<?php

namespace App\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class CostdayExport implements FromView,WithColumnFormatting
{
    protected array $data;
    protected array $costs;
    protected array $invoices1;
    protected string $startDate;
    protected string $endDate;

    public function __construct(array $data = [], $costs = [],$invoices1 = [], $startDate, $endDate)
    {
        $this->data = $data;
        $this->costs = $costs;
        $this->invoices1 = $invoices1;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        // Apply decimal formatting to data and costs
        // $formattedData = array_map(function($row) {
        //     if (isset($row['amount']) && is_numeric($row['amount'])) {
        //         $row['amount'] = number_format($row['amount'], 2); // จัดรูปแบบเป็นทศนิยม 2 ตำแหน่ง
        //     }
        //     return $row; // คืนค่าแถวที่แก้ไขแล้ว
        // }, $this->data);

        // $formattedCosts = array_map(function($value) {
        //     return is_numeric($value) ? number_format($value, 2) : $value; // เช็คว่าเป็นตัวเลขหรือไม่
        // }, $this->costs);

        return view('export.CostdayExport', [
            'data' => $this->data,
            'costs' => $this->costs,
            'invoices1' => $this->invoices1,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER_00,
            'I' => NumberFormat::FORMAT_NUMBER_00,
            'J' => NumberFormat::FORMAT_NUMBER_00,
            'K' => NumberFormat::FORMAT_NUMBER_00,
            'L' => NumberFormat::FORMAT_NUMBER_00,
            'M' => NumberFormat::FORMAT_NUMBER_00,
            'N' => NumberFormat::FORMAT_NUMBER_00,
            'O' => NumberFormat::FORMAT_NUMBER_00,
            'P' => NumberFormat::FORMAT_NUMBER_00,
            'Q' => NumberFormat::FORMAT_NUMBER_00,
            'R' => NumberFormat::FORMAT_NUMBER_00,
            'S' => NumberFormat::FORMAT_NUMBER_00,
            'T' => NumberFormat::FORMAT_NUMBER_00,
            'U' => NumberFormat::FORMAT_NUMBER_00,
            'V' => NumberFormat::FORMAT_NUMBER_00,
            'W' => NumberFormat::FORMAT_NUMBER_00,
            'X' => NumberFormat::FORMAT_NUMBER_00,
            'Y' => NumberFormat::FORMAT_NUMBER_00,
            'Z' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
// {
    /**
    // * @return \Illuminate\Support\Collection
    */
//     public function collection()
//     {
//         //
//     }
// }
