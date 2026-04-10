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

class PrbReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithEvents, WithColumnFormatting
{
    protected Collection $rows;
    protected ?Carbon $startDate;
    protected ?Carbon $endDate;

    public function __construct(Collection $rows, ?Carbon $startDate, ?Carbon $endDate)
    {
        $this->rows = $rows;
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function title(): string
    {
        return 'รายงาน พ.ร.บ.';
    }

    public function headings(): array
    {
        return [
            'ลำดับ',
            'เลขบิล',
            'วันที่',
            'ทะเบียน',
            'เบอร์โทร',
            'ประกันภัย',
            'วันที่เริ่มคุ้มครอง',
            'วันที่สิ้นสุดคุ้มครอง',
            'เลขกรมธรรม์',
        ];
    }

    public function array(): array
    {
        // จัดเรียงข้อมูล + ทำ index + ฟอร์แมตวันที่แบบ พ.ศ.
        $rows = [];
        $i = 1;

        foreach ($this->rows as $r) {
            $date      = $this->thaiDate($r->date);
            $dateStart = $this->thaiDate($r->date_start);
            $dateEnd   = $this->thaiDate($r->date_end);

            $rows[] = [
                $i++,
                $r->bill_number ?: '-',
                $date ?: '-',
                $r->license_plate ?: '-',
                $r->customer_phone_number ?: '-',
                $r->insurance_name ?: '-',
                $dateStart ?: '-',
                $dateEnd ?: '-',
                $r->policy_number ?: '-',
            ];
        }

        // ถ้าไม่มีข้อมูล ให้แถวเปล่าเพื่อให้ไฟล์เปิดได้
        if (empty($rows)) {
            $rows[] = array_fill(0, 9, '');
        }

        return $rows;
    }

    private function thaiDate($value): ?string
    {
        if (empty($value)) return null;
        $d = Carbon::parse($value);
        return $d->format('d/m/') . ($d->year + 543);
    }

    public function columnFormats(): array
    {
        // นำเบอร์โทร/เลขกรมธรรม์เก็บเป็น "ข้อความ" ป้องกันศูนย์หาย
        return [
            'E' => NumberFormat::FORMAT_TEXT, // เบอร์โทร
            'I' => NumberFormat::FORMAT_TEXT, // เลขกรมธรรม์
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // แทรกแถวหัวรายงานด้านบน
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:I1');

                // ข้อความหัวรายงาน (ใช้ปีของ start/end ตามจริง)
                $title = 'รายงาน พ.ร.บ. วันที่ ';
                $title .= $this->startDate
                    ? $this->startDate->format('d/m/') . ($this->startDate->year + 543)
                    : '-';
                $title .= ' ถึง ';
                $title .= $this->endDate
                    ? $this->endDate->format('d/m/') . ($this->endDate->year + 543)
                    : '-';

                $sheet->setCellValue('A1', $title);

                // สไตล์หัวรายงาน + หัวคอลัมน์
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

                $sheet->getStyle('A2:I2')->getFont()->setBold(true);
                $sheet->getStyle('A2:I2')->getAlignment()->setHorizontal('center');

                // เส้นกรอบตารางทั้งหมด
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A2:I{$highestRow}")
                      ->getBorders()->getAllBorders()
                      ->setBorderStyle(Border::BORDER_THIN);

                // จัดกึ่งกลางคอลัมน์บางส่วนให้เหมือน PDF
                $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal('center'); // ลำดับ
                $sheet->getStyle("B3:B{$highestRow}")->getAlignment()->setHorizontal('center'); // เลขบิล
                $sheet->getStyle("C3:C{$highestRow}")->getAlignment()->setHorizontal('center'); // วันที่
                $sheet->getStyle("D3:D{$highestRow}")->getAlignment()->setHorizontal('center'); // ทะเบียน
                $sheet->getStyle("G3:H{$highestRow}")->getAlignment()->setHorizontal('center'); // ช่วงคุ้มครอง
                $sheet->getStyle("I3:I{$highestRow}")->getAlignment()->setHorizontal('center'); // เลขกรมธรรม์

                // Freeze header
                $sheet->freezePane('A3');

                // กำหนดความกว้างคร่าว ๆ
                $sheet->getColumnDimension('C')->setWidth(14); // วันที่
                $sheet->getColumnDimension('E')->setWidth(16); // เบอร์โทร
                $sheet->getColumnDimension('F')->setWidth(24); // ประกันภัย
                $sheet->getColumnDimension('I')->setWidth(22); // เลขกรมธรรม์
            },
        ];
    }
}
