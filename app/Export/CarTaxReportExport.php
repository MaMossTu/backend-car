<?php

namespace App\Export;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class CarTaxReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithColumnFormatting,WithEvents
{
    protected Collection $rows;
    protected Carbon $startDate;
    protected Carbon $endDate;

    public function __construct($collection, Carbon $startDate, Carbon $endDate)
    {
        // ให้แน่ใจว่าเป็น Collection
        $this->rows = collect($collection);
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function title(): string
    {
        return 'รายงานภาษีรถ';
    }

    public function headings(): array
    {
        // ตามหัวตารางใน PDF เดิม
        return [
            'วันที่จดทะเบียน',
            'ลำดับ',
            'เลขที่',
            'ทะเบียน',
            'เบอร์โทร',
            'ยี่ห้อ',
            'เรียกเก็บ',
            'ยอดจริง',
            'คืน',
            'เก็บเพิ่ม',
            'หมายเหตุ',
        ];
    }

    public function array(): array
    {
        // เตรียมข้อมูล: จัดกลุ่มตามวันที่ (asc) และรีเซ็ตลำดับใหม่เมื่อเปลี่ยนวัน
        $sorted = $this->rows->sortBy('date')->values();

        $data = [];
        $lastDate = null;
        $index    = 1;

        foreach ($sorted as $r) {
            $dateStr = $r->date ? Carbon::parse($r->date)->format('d/m/Y') : '';

            if ($r->date !== $lastDate) {
                // วันที่ใหม่ → รีเซ็ตลำดับ
                // $index = 1;
                $lastDate = $r->date;
            }

            // คำนวณ คืน / เก็บเพิ่ม
            $service = floatval($r->service_price ?? 0);
            $pay     = floatval($r->paytrue ?? 0);

            $refund = null;   // คืน
            $extra  = null;   // เก็บเพิ่ม

            if ($pay > 0) {
                if ($service > $pay) {
                    $refund = $service - $pay;
                    $extra  = 0;
                } else {
                    $extra  = $pay - $service;
                    $refund = 0;
                }
            } else {
                $refund = 0;
                $extra  = 0;
            }

            $data[] = [
                $r->registration_date ? Carbon::parse($r->registration_date)->format('d/m/Y') : '',
                $index++,
                $r->no ?? '-',
                $r->license_plate ?? '-',
                $r->phone_number ?? '-',
                $r->name ?? '-', // ยี่ห้อ
                $service ?: 0,
                $pay ?: 0,
                $refund ?: 0,
                $extra ?: 0,
                $r->remark ?? '-',
            ];
        }

        // ถ้าไม่มีข้อมูล ให้แถวว่าง 1 แถวเพื่อให้โหลดไฟล์ได้
        if (empty($data)) {
            $data[] = array_fill(0, 11, '');
        }

        return $data;
    }

    public function columnFormats(): array
    {
        // เซ็ตคอลัมน์ตัวเลขให้เป็นเลข/ทศนิยมตามสมควร
        // A=วันที่จดทะเบียน, B=ลำดับ, C=เลขที่, D=ทะเบียน, E=เบอร์, F=ยี่ห้อ,
        // G=เรียกเก็บ, H=ยอดจริง, I=คืน, J=เก็บเพิ่ม, K=หมายเหตุ
        return [
            'B' => NumberFormat::FORMAT_NUMBER,         // ลำดับ
            'G' => NumberFormat::FORMAT_NUMBER_00,      // เรียกเก็บ
            'H' => NumberFormat::FORMAT_NUMBER_00,      // ยอดจริง
            'I' => NumberFormat::FORMAT_NUMBER_00,      // คืน
            'J' => NumberFormat::FORMAT_NUMBER_00,      // เก็บเพิ่ม
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // ✅ แทรกแถวว่างบนสุดเพื่อใส่หัวตารางรายงาน
                $event->sheet->insertNewRowBefore(1, 1);

                // ✅ รวมเซลล์ A1:K1 สำหรับหัวตาราง
                $event->sheet->mergeCells('A1:K1');

                // ✅ กำหนดข้อความหัวรายงาน พร้อมวันที่แบบ พ.ศ.
                $title = sprintf(
                    'รายงานภาษีรถ %s ถึง %s',
                    $this->startDate->format('d/m/') . ($this->startDate->year + 543),
                    $this->endDate->format('d/m/') . ($this->endDate->year + 543)
                );

                $event->sheet->setCellValue('A1', $title);

                // ✅ ตั้งค่ารูปแบบหัวตาราง
                $style = $event->sheet->getStyle('A1');
                $style->getFont()->setBold(true)->setSize(16);
                $style->getAlignment()->setHorizontal('center');

                // ✅ ทำหัวคอลัมน์แถวที่ 2 เป็นตัวหนา
                $event->sheet->getStyle('A2:K2')->getFont()->setBold(true);

                // ✅ ล็อกแถวหัวคอลัมน์ไว้ (scroll แล้วหัวไม่หาย)
                $event->sheet->freezePane('A3');
            },
        ];
    }
}
