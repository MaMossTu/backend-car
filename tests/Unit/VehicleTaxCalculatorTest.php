<?php

namespace Tests\Unit;

use App\Support\VehicleTaxCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class VehicleTaxCalculatorTest extends TestCase
{
    private VehicleTaxCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new VehicleTaxCalculator();
    }

    public function test_early_renewal_within_90_days_charges_one_year_without_fine(): void
    {
        $due = Carbon::parse('2025-03-01');
        $renewal = Carbon::parse('2025-02-01');

        $periods = $this->calculator->buildTaxPeriods($due, $renewal);

        $this->assertCount(1, $periods);
        $this->assertSame(2026, $periods[0]['fiscal_year_ce']);
        $this->assertSame(0, $periods[0]['fine_months']);
    }

    public function test_early_renewal_beyond_90_days_returns_no_periods(): void
    {
        $due = Carbon::parse('2025-03-01');
        $renewal = Carbon::parse('2024-11-01');

        $periods = $this->calculator->buildTaxPeriods($due, $renewal);

        $this->assertSame([], $periods);
    }

    public function test_late_renewal_caps_back_tax_at_four_years(): void
    {
        $due = Carbon::parse('2020-03-01');
        $renewal = Carbon::parse('2026-03-01');

        $periods = $this->calculator->buildTaxPeriods($due, $renewal);

        $this->assertCount(4, $periods);
    }

    public function test_fine_months_capped_at_36(): void
    {
        $due = Carbon::parse('2020-03-01');
        $renewal = Carbon::parse('2026-03-01');

        $periods = $this->calculator->buildTaxPeriods($due, $renewal);

        foreach ($periods as $period) {
            $this->assertLessThanOrEqual(36, $period['fine_months']);
        }
    }
}
