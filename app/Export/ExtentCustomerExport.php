<?php

namespace App\Export;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ExtentCustomerExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'ติดตามลูกค้า';
    }

    public function headings(): array
    {
        return [
            'ลำดับ',
            'ทะเบียน',
            'ชื่อลูกค้า',
            'เบอร์โทร',
            'วันหมดภาษี',
            'วันหมดอายุ พ.ร.บ.',
            'วันหมดอายุประกัน',
            'วันเวลาล่าสุดที่โทร',
            'รายละเอียดการโทร',
        ];
    }

    public function array(): array
    {
        $out = [];
        $i   = 1;

        foreach ($this->rows as $row) {
            $out[] = [
                $i++,
                $row->license_plate ?? '-',
                $row->customer_name ?? '-',
                $row->phone_number ?? '-',
                $this->thaiDate($row->notificate_tax ?? null),
                $this->thaiDate($row->prb_expiry_date ?? null),
                $this->thaiDate($row->insurance_expiry_date ?? null),
                $this->thaiDateTime($row->call_log ?? null),
                $row->call_log_details ?? '-',
            ];
        }

        return $out;
    }

    private function thaiDate($value): string
    {
        if (empty($value)) {
            return '-';
        }
        $d = Carbon::parse($value);

        return $d->format('d/m/') . ($d->year + 543);
    }

    private function thaiDateTime($value): string
    {
        if (empty($value)) {
            return '-';
        }
        $d = Carbon::parse($value);

        return $d->format('d/m/') . ($d->year + 543) . ' ' . $d->format('H:i:s');
    }
}
