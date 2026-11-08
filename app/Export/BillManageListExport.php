<?php

namespace App\Export;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BillManageListExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'จัดการบิล';
    }

    public function headings(): array
    {
        return [
            'เลขที่',
            'วันที่',
            'ทะเบียน',
            'ชื่อลูกค้า',
            'ตรวจ',
            'ภาษี',
            'พ.ร.บ.',
            'ประกัน',
            'แก๊ส',
            'หนังสือรับรองวิศวกร',
            'ขนส่ง',
            'อื่นๆ',
            'EMS',
            'ส่วนลด',
            'สุทธิ',
            'สถานะ',
        ];
    }

    public function array(): array
    {
        $out = [];

        foreach ($this->rows as $item) {
            $vehicle = $item->InspectionVehicles->first();
            $transactions = $vehicle?->vehicleServiceTransaction ?? collect();
            if (is_array($transactions)) {
                $transactions = collect($transactions);
            }

            $discount = (float) ($item->discount ?? 0)
                + (float) ($item->discount_before_vat ?? 0)
                + (float) ($item->discount_after_vat ?? 0);

            if ($item->status === 'open') {
                $net = (float) ($item->total ?? 0);
            } else {
                $net = (float) ($item->total_vatcal ?? 0) + (float) ($item->total_nonvatcal ?? 0);
            }

            $out[] = [
                $item->no ?? '-',
                $this->thaiDate($item->date ?? null),
                $vehicle->license_plate ?? '-',
                $this->customerName($item->Customers),
                $this->money($this->servicePrice($transactions, 1)),
                $this->money($this->sumServiceIds($transactions, [3, 4, 5, 6])),
                $this->money($this->servicePrice($transactions, 2)),
                $this->money($this->servicePrice($transactions, 7)),
                $this->money($this->sumServiceIds($transactions, [8, 9, 10, 11, 12])),
                $this->money($this->servicePrice($transactions, 13)),
                $this->money($this->servicePrice($transactions, 14)),
                $this->money($this->servicePrice($transactions, 15)),
                $this->money($this->servicePrice($transactions, 16)),
                $this->money($discount),
                $this->money($net),
                $this->statusLabel($item->status),
            ];
        }

        return $out;
    }

    private function customerName($customer): string
    {
        if (! $customer) {
            return '-';
        }
        $full = trim(trim($customer->name ?? '') . ' ' . trim($customer->lname ?? ''));

        return $full !== '' ? $full : '-';
    }

    private function servicePrice($transactions, int $serviceId): float
    {
        foreach ($transactions as $t) {
            if ((int) ($t->service_id ?? 0) === $serviceId) {
                return (float) ($t->service_price ?? 0);
            }
        }

        return 0;
    }

    private function sumServiceIds($transactions, array $ids): float
    {
        $sum = 0;
        foreach ($ids as $id) {
            $sum += $this->servicePrice($transactions, $id);
        }

        return $sum;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'open' => 'เปิด',
            'finish' => 'สำเร็จ',
            'overdue' => 'แบ่งจ่าย',
            'unpaid' => 'ค้างจ่าย',
            'cancel' => 'ยกเลิก',
            default => $status ?? '-',
        };
    }

    private function thaiDate($value): string
    {
        if (empty($value)) {
            return '-';
        }
        $d = Carbon::parse($value);

        return $d->format('d/m/') . ($d->year + 543);
    }

    /** ช่องเงิน — ว่างให้เป็น 0.00 */
    private function money($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
