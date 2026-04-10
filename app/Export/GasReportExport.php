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

class GasReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithEvents, WithColumnFormatting
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
        return 'รายงานก๊าซ';
    }

    public function headings(): array
    {
        // ให้ตรงกับ PDF/Query ชุดใหม่
        return [
            'รหัสวิศวกร',      // engineer_no
            'เลขที่ลูกค้า',     // customer_no
            'ชื่อลูกค้า',       // customer_name
            'ให้บริการ',        // service_or
            'วันที่',           // date
            'เวลา',            // time
            'ทะเบียน',          // license_plate
            'จังหวัด',          // provinces
            'ยี่ห้อรถ',         // vehicle_brand
            'ยี่ห้อถังแก๊ส',    // brand_gas
            'เลขที่ติดตั้ง',     // install_no
            'เลขที่ CHA',       // cha_no_group
        ];
    }

    public function array(): array
    {
        return $this->rows->map(function ($r) {
            // วันที่ (d/m/พ.ศ.)
            $dateStr = '';
            if (!empty($r->date)) {
                $d = Carbon::parse($r->date);
                $dateStr = $d->format('d/m/') . ($d->year + 543);
            }
            // เวลา (ให้แสดงตามค่าเดิม ถ้าเป็น string HH:MM)
            $timeStr = $r->time ?? '';

            return [
                $r->engineer_no ?? '-',       // รหัสวิศวกร
                $r->customer_no ?? '-',       // เลขที่ลูกค้า
                $r->customer_name ?? '-',     // ชื่อลูกค้า
                $r->service_or ?? '-',        // ให้บริการ
                $dateStr,                     // วันที่
                $timeStr,                     // เวลา
                $r->license_plate ?? '-',     // ทะเบียน
                $r->provinces ?? '-',         // จังหวัด
                $r->vehicle_brand ?? '-',     // ยี่ห้อรถ
                $r->brand_gas ?? '-',         // ยี่ห้อถังแก๊ส
                $r->install_no ?? '-',        // เลขที่ติดตั้ง
                $r->cha_no_group ?? '-',      // เลขที่ CHA (group concat)
            ];
        })->all();
    }

    public function columnFormats(): array
    {
        // ตั้งรูปแบบตามความเหมาะสม (เลข/ข้อความ)
        return [
            // ตัวอย่าง: 'E' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // 1) แทรกแถวหัวรายงาน
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:L1');

                $title = 'รายงานก๊าซ ';
                if ($this->startDate && $this->endDate) {
                    $sd = $this->startDate->format('d/m/') . ($this->startDate->year + 543);
                    $ed = $this->endDate->format('d/m/') . ($this->endDate->year + 543);
                    $title .= "{$sd} ถึง {$ed}";
                }
                $sheet->setCellValue('A1', $title);

                // 2) สไตล์หัวรายงาน + หัวคอลัมน์
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

                $sheet->getStyle('A2:L2')->getFont()->setBold(true);
                $sheet->getStyle('A2:L2')->getAlignment()->setHorizontal('center');

                // 3) ใส่กรอบตารางทั้งหมด
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A2:L{$highestRow}")
                      ->getBorders()->getAllBorders()
                      ->setBorderStyle(Border::BORDER_THIN);

                // 4) Freeze header
                $sheet->freezePane('A3');

                // 5) จัด alignment เพิ่มเติมให้ใกล้เคียง PDF
                $sheet->getStyle("E3:E{$highestRow}")->getAlignment()->setHorizontal('center'); // วันที่
                $sheet->getStyle("F3:F{$highestRow}")->getAlignment()->setHorizontal('center'); // เวลา
                $sheet->getStyle("G3:G{$highestRow}")->getAlignment()->setHorizontal('center'); // ทะเบียน
                $sheet->getStyle("K3:K{$highestRow}")->getAlignment()->setHorizontal('center'); // เลขที่ติดตั้ง
                $sheet->getStyle("L3:L{$highestRow}")->getAlignment()->setHorizontal('center'); // CHA

                // 6) ปรับความกว้างบางคอลัมน์
                $sheet->getColumnDimension('C')->setWidth(28); // ชื่อลูกค้า
                $sheet->getColumnDimension('E')->setWidth(14); // วันที่
                $sheet->getColumnDimension('F')->setWidth(10); // เวลา
                $sheet->getColumnDimension('G')->setWidth(16); // ทะเบียน
                $sheet->getColumnDimension('H')->setWidth(12); // จังหวัด
                $sheet->getColumnDimension('I')->setWidth(12); // ยี่ห้อรถ
            },
        ];
    }
}

