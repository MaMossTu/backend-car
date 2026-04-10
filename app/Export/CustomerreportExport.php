<?php

namespace App\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class CustomerreportExport implements FromView,WithColumnFormatting
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function view(): View
    {

        $formattedData = array_map(function ($customer) {
            // แปลง tax_id ให้เป็นข้อความจริงๆ โดยใช้ strval()
            $customer[0]['tax_id'] = strval($customer[0]['tax_id']);
            return $customer;
        }, $this->data);

        return view('export.CustomerreportExport', [
            'data' => $formattedData,
        ]);
    }
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT, // รหัสลูกค้า
            'C' => NumberFormat::FORMAT_TEXT, // ชื่อลูกค้า
            'E' => NumberFormat::FORMAT_TEXT, // อีเมล
            'F' => NumberFormat::FORMAT_NUMBER, // เบอร์โทร
            'G' => NumberFormat::FORMAT_NUMBER, // เลขที่ผู้เสียภาษี
        ];
    }
}
