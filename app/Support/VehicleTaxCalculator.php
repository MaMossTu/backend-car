<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * กฎคำนวณภาษีรถประจำปี (ชีตแถว 7–10): ต่อล่วงหน้า 90 วัน, ย้อนหลังสูงสุด 4 ปี, ค่าปรับ cap 3 ปี
 */
class VehicleTaxCalculator
{
    public const EARLY_RENEWAL_DAYS = 90;

    public const MAX_BACK_TAX_YEARS = 4;

    public const MAX_FINE_YEARS = 3;

    public const MAX_FINE_MONTHS = 36;

    /**
     * @return list<array{fiscal_year_ce: int, fine_months: int}>
     */
    public function buildTaxPeriods(Carbon $taxDueDate, Carbon $taxRenewalDate): array
    {
        $due = $taxDueDate->copy()->startOfDay();
        $renewal = $taxRenewalDate->copy()->startOfDay();

        if ($renewal->lt($due)) {
            $daysBeforeDue = $renewal->diffInDays($due);
            if ($daysBeforeDue > self::EARLY_RENEWAL_DAYS) {
                return [];
            }

            return [
                [
                    'fiscal_year_ce' => $due->year + 1,
                    'fine_months'    => 0,
                ],
            ];
        }

        $periods = [];
        $periodDue = $due->copy();
        $iterations = 0;

        while ($periodDue->lte($renewal) && $iterations < self::MAX_BACK_TAX_YEARS) {
            $fiscalYear = $periodDue->year + 1;
            $nextDue = $periodDue->copy()->addYear();

            if ($renewal->lt($nextDue)) {
                $fineMonths = (int) $periodDue->diffInMonths($renewal);
            } else {
                $fineMonths = (int) $periodDue->diffInMonths($nextDue);
            }

            if ($fineMonths > 0) {
                $fineMonths = min($fineMonths, self::MAX_FINE_MONTHS);
            }

            $periods[] = [
                'fiscal_year_ce' => $fiscalYear,
                'fine_months'    => $fineMonths,
            ];

            $periodDue->addYear();
            $iterations++;
        }

        return $periods;
    }

    public static function applyTaxModifiers(float $total, $isCorporate, ?string $fuelType): float
    {
        if ((int) $isCorporate === 1) {
            $total *= 2;
        }
        if ($fuelType === 'gas') {
            $total *= 0.5;
        }
        if ($fuelType === 'Hybrid') {
            $total *= 0.75;
        }

        return $total;
    }
}
