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

class InsurancesRenewalsReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithEvents, WithColumnFormatting
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
        return 'รายงานประกัน';
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
            'วันที่เริ่มการคุ้มครอง',
            'วันที่สิ้นสุดการคุ้มครอง',
            'เลขกรมธรรม์',
        ];
    }

    public function array(): array
    {
        $out = [];
        $i = 1;

        foreach ($this->rows as $r) {
            $out[] = [
                $i++,
                $r->no ?: '-',
                $this->thai($r->date) ?: '-',
                $r->license_plate ?: '-',
                $r->customer_phone_number ?: '-',
                ($r->name ?? '-') ?: '-', // insurance_names.name
                $this->thai($r->date_start) ?: '-',
                $this->thai($r->date_end) ?: '-',
                $r->policy_number ?: '-',
            ];
        }

        if (empty($out)) {
            $out[] = array_fill(0, 9, '');
        }

        return $out;
    }

    private function thai($value): ?string
    {
        if (empty($value)) return null;
        $d = Carbon::parse($value);
        return $d->format('d/m/') . ($d->year + 543);
    }

    public function columnFormats(): array
    {
        // เก็บเบอร์โทร/เลขกรมธรรม์เป็น "ข้อความ" กันศูนย์นำหน้า/เลขยาวเพี้ยน
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

                // แทรกหัวรายงานด้านบน + merge
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:I1');

                $title = 'รายงานประกัน วันที่ ';
                $title .= $this->startDate
                    ? $this->startDate->format('d/m/') . ($this->startDate->year + 543)
                    : '-';
                $title .= ' ถึง ';
                $title .= $this->endDate
                    ? $this->endDate->format('d/m/') . ($this->endDate->year + 543)
                    : '-';

                $sheet->setCellValue('A1', $title);

                // สไตล์หัวเรื่อง + หัวคอลัมน์
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

                $sheet->getStyle('A2:I2')->getFont()->setBold(true);
                $sheet->getStyle('A2:I2')->getAlignment()->setHorizontal('center');

                // เส้นกรอบตาราง (หัว + ข้อมูล)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A2:I{$highestRow}")
                      ->getBorders()->getAllBorders()
                      ->setBorderStyle(Border::BORDER_THIN);

                // จัดกึ่งกลางบางคอลัมน์ให้เหมือน PDF
                $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal('center'); // ลำดับ
                $sheet->getStyle("B3:B{$highestRow}")->getAlignment()->setHorizontal('center'); // เลขบิล
                $sheet->getStyle("C3:C{$highestRow}")->getAlignment()->setHorizontal('center'); // วันที่
                $sheet->getStyle("D3:D{$highestRow}")->getAlignment()->setHorizontal('center'); // ทะเบียน
                $sheet->getStyle("G3:H{$highestRow}")->getAlignment()->setHorizontal('center'); // ช่วงคุ้มครอง
                $sheet->getStyle("I3:I{$highestRow}")->getAlignment()->setHorizontal('center'); // เลขกรมธรรม์

                // Freeze header
                $sheet->freezePane('A3');

                // กำหนดความกว้างโดยประมาณ
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(16);
                $sheet->getColumnDimension('F')->setWidth(26);
                $sheet->getColumnDimension('I')->setWidth(22);
            },
        ];
    }
}
