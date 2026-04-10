<?php

namespace App\Export;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EMSReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithEvents, WithColumnFormatting
{
    protected Collection $rows;
    protected ?Carbon $startDate;
    protected ?Carbon $endDate;

    public function __construct(Collection $rows, ?Carbon $startDate, ?Carbon $endDate)
    {
        $this->rows      = $rows;
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function title(): string
    {
        return 'รายงาน EMS';
    }

    public function headings(): array
    {
        // ให้หน้าตาเหมือน PDF
        return [
            'ลำดับ',         // 1
            'เลขบิล',        // inspections.no
            'วันที่',         // inspections.date (พ.ศ.)
            'ทะเบียน',        // inspection_vehicles.license_plate
            'ประเภท',        // inspection_vehicles.vehicle_inspection_types
            'ชื่อผู้รับ',     // address_ems.name
            'หมายเลขโทรศัพท์', // customers.phone_number1
            'หมายเลข EMS',   // inspections.ems
            'ที่อยู่ผู้รับ',   // address_ems.address (รวมไว้ช่องเดียว)
        ];
    }

    public function array(): array
    {
        $i = 1;
        $out = [];

        foreach ($this->rows as $r) {
            $dateStr = $r->date ? Carbon::parse($r->date)->format('d/m/').(Carbon::parse($r->date)->year + 543) : '-';

            $out[] = [
                $i++,
                $r->no ?: '-',
                $dateStr,
                $r->license_plate ?: '-',
                $r->vehicle_inspection_types ?: '-',
                // ใช้ชื่อผู้รับจาก address_ems.name ถ้าไม่มี fallback เป็น customers.name
                ($r->name ?: ($r->customer_name ?? '-')),
                $r->phone ?: '-',
                ($r->ems && strtolower($r->ems) !== 'null') ? $r->ems : '-', // ✅ ป้องกันทั้ง null และ string 'null'
                $r->address ?: '-', // ที่อยู่เต็มจาก address_ems
            ];
        }

        if (empty($out)) {
            $out[] = array_fill(0, 9, '');
        }

        return $out;
    }

    public function columnFormats(): array
    {
        // เก็บเบอร์โทร/เลข EMS เป็นข้อความ ป้องกันศูนย์หายและ format เพี้ยน
        return [
            'G' => NumberFormat::FORMAT_TEXT, // โทรศัพท์
            'H' => NumberFormat::FORMAT_TEXT, // EMS
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // 1) แทรกหัวรายงานแถวบนสุด + merge
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:I1');

                $title = 'รายงาน EMS ข้อมูลลูกค้า วันที่ ';
                $title .= $this->startDate
                    ? $this->startDate->format('d/m/') . ($this->startDate->year + 543)
                    : '-';
                $title .= ' ถึง ';
                $title .= $this->endDate
                    ? $this->endDate->format('d/m/') . ($this->endDate->year + 543)
                    : '-';

                $sheet->setCellValue('A1', $title);

                // 2) สไตล์หัวเรื่อง + หัวคอลัมน์
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

                $sheet->getStyle('A2:I2')->getFont()->setBold(true);
                $sheet->getStyle('A2:I2')->getAlignment()->setHorizontal('center');

                // 3) เส้นกรอบตารางทั้งหมด
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A2:I{$highestRow}")
                      ->getBorders()->getAllBorders()
                      ->setBorderStyle(Border::BORDER_THIN);

                // 4) จัดกึ่งกลางคอลัมน์บางส่วนให้เหมือน PDF
                $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal('center'); // ลำดับ
                $sheet->getStyle("B3:B{$highestRow}")->getAlignment()->setHorizontal('center'); // เลขบิล
                $sheet->getStyle("C3:C{$highestRow}")->getAlignment()->setHorizontal('center'); // วันที่
                $sheet->getStyle("D3:D{$highestRow}")->getAlignment()->setHorizontal('center'); // ทะเบียน
                $sheet->getStyle("E3:E{$highestRow}")->getAlignment()->setHorizontal('center'); // ประเภท
                $sheet->getStyle("G3:H{$highestRow}")->getAlignment()->setHorizontal('center'); // โทร/EMS

                // 5) Freeze header
                $sheet->freezePane('A3');

                // 6) ปรับความกว้างคอลัมน์หลัก
                $sheet->getColumnDimension('C')->setWidth(14); // วันที่
                $sheet->getColumnDimension('D')->setWidth(16); // ทะเบียน
                $sheet->getColumnDimension('E')->setWidth(14); // ประเภท
                $sheet->getColumnDimension('F')->setWidth(24); // ชื่อผู้รับ
                $sheet->getColumnDimension('G')->setWidth(16); // โทร
                $sheet->getColumnDimension('H')->setWidth(18); // EMS
                $sheet->getColumnDimension('I')->setWidth(60); // ที่อยู่
            },
        ];
    }
}
