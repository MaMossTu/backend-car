<?php

namespace App\Export;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TaxInvoiceListExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'ใบกำกับภาษี';
    }

    public function headings(): array
    {
        return [
            'เลขที่',
            'ชื่อ',
            'วันที่',
            'จากบิล',
            'สถานะ',
            'ยอดรวมสุทธิ',
            'หักภาษี ณ ที่จ่าย',
            '% ภาษี',
            'ยอดหัก',
            'ยอดชำระเงิน',
            'เงื่อนไขการชำระเงิน',
        ];
    }

    public function array(): array
    {
        $out = [];

        foreach ($this->rows as $item) {
            $inspection = $item->Inspection;
            $taxInvoice = $item->TaxInvoice;

            if ($inspection) {
                $name = trim(($inspection->Customers->name ?? '') . ' ' . ($inspection->Customers->lname ?? ''));
                $fromBill = $inspection->no ?? '-';
                $status = $this->inspectionStatus($inspection->status ?? '');
                $net = (float) ($inspection->total_vatcal ?? 0);
                $whtLabel = '-';
                $whtPercent = 0;
                $whtAmount = 0;
                $payable = $net;
                $paidType = $this->paidType($inspection->paid_type ?? null);
            } elseif ($taxInvoice) {
                $name = $taxInvoice->customer_name ?? '-';
                $fromBill = $taxInvoice->Invoice->no
                    ?? $taxInvoice->WorkOrder->no
                    ?? '-';
                $status = $this->taxInvoiceStatus($taxInvoice->status ?? '');
                $net = (float) ($taxInvoice->total_amount ?? 0);
                $whtLabel = $taxInvoice->withholding_tax === 'Y'
                    ? 'หักภาษี ณ ที่จ่าย'
                    : ($taxInvoice->withholding_tax === 'N' ? 'ไม่หักภาษี ณ ที่จ่าย' : '-');
                [$base, $vat] = $this->baseAndVat($taxInvoice);
                if ($taxInvoice->withholding_tax === 'Y') {
                    $pct = $taxInvoice->withholding_tax_percent ?? 3;
                    $whtPercent = (float) $pct;
                    $whtAmount = $base * $pct / 100;
                    $payable = ($base + $vat) - $whtAmount;
                } else {
                    $whtPercent = 0;
                    $whtAmount = 0;
                    $payable = $base + $vat ?: $net;
                }
                $paidType = $this->paidType($taxInvoice->paid_type ?? null);
            } else {
                continue;
            }

            $out[] = [
                $item->no ?? '-',
                $name ?: '-',
                $this->thaiDate($item->date ?? null),
                $fromBill,
                $status,
                number_format($net, 2, '.', ''),
                $whtLabel,
                $whtPercent,
                number_format($whtAmount, 2, '.', ''),
                number_format($payable, 2, '.', ''),
                $paidType,
            ];
        }

        return $out;
    }

    protected function baseAndVat($taxInvoice): array
    {
        $vat = isset($taxInvoice->tax) ? (float) $taxInvoice->tax : 0.0;
        if (isset($taxInvoice->total) && $taxInvoice->total !== '') {
            return [(float) $taxInvoice->total, $vat];
        }
        $totalAmount = (float) ($taxInvoice->total_amount ?? 0);

        return [$totalAmount - $vat, $vat];
    }

    protected function thaiDate($date): string
    {
        if (!$date) {
            return '-';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }

    protected function inspectionStatus(string $status): string
    {
        return match ($status) {
            'open' => 'เปิด',
            'overdue' => 'แบ่งจ่าย',
            'unpaid' => 'ค้างจ่าย',
            'finish' => 'เสร็จสิ้น',
            'cancel' => 'ยกเลิก',
            default => $status ?: '-',
        };
    }

    protected function taxInvoiceStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'ร่าง',
            'sent' => 'รอตรวจสอบ',
            'approved' => 'อนุมัติ',
            'rejected' => 'ยกเลิก',
            default => $status ?: '-',
        };
    }

    protected function paidType(?string $type): string
    {
        return match ($type) {
            'paid_cash' => 'เงินสด',
            'paid_tran' => 'โอนเงิน',
            'credit' => 'เครดิต',
            default => '-',
        };
    }
}
