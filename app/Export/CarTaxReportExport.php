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

class CarTaxReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithColumnFormatting, WithEvents
{
    protected Collection $rows;
    protected Carbon $startDate;
    protected Carbon $endDate;

    public function __construct($collection, Carbon $startDate, Carbon $endDate)
    {
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
        return [
            'วันที่ยื่นภาษี',
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

    /** วันที่ยื่นภาษี (tax_renewal_date) */
    private function formatReportRowDate($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse($value)->format('d/m/') . (Carbon::parse($value)->year + 543);
    }

    private function formatMoney($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        $f = (float) $value;

        return $f == 0.0 ? '-' : (string) $f;
    }

    public function array(): array
    {
        $data = [];
        $index = 1;

        foreach ($this->rows->values() as $r) {
            $data[] = [
                $this->formatReportRowDate($r->tax_renewal_date ?? null),
                $index++,
                $r->no ?? '-',
                $r->license_plate ?? '-',
                $r->phone_number ?? '-',
                $r->brand_name ?? '-',
                $this->formatMoney($r->billed ?? null),
                $this->formatMoney($r->paytrue ?? null),
                $this->formatMoney($r->refund ?? null),
                $this->formatMoney($r->extra ?? null),
                $r->remark ?? '-',
            ];
        }

        if (empty($data)) {
            $data[] = array_fill(0, 11, '');
        }

        return $data;
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->insertNewRowBefore(1, 1);
                $event->sheet->mergeCells('A1:K1');
                $title = sprintf(
                    'รายงานภาษีรถ %s ถึง %s',
                    $this->startDate->format('d/m/') . ($this->startDate->year + 543),
                    $this->endDate->format('d/m/') . ($this->endDate->year + 543)
                );
                $event->sheet->setCellValue('A1', $title);
                $style = $event->sheet->getStyle('A1');
                $style->getFont()->setBold(true)->setSize(16);
                $style->getAlignment()->setHorizontal('center');
                $event->sheet->getStyle('A2:K2')->getFont()->setBold(true);
                $event->sheet->freezePane('A3');
            },
        ];
    }
}
