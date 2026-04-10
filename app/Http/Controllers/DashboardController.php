<?php

namespace App\Http\Controllers;

use App\Models\Inspections;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function Inspections_per_month(Request $request)
    {
        $selectedDate = Carbon::parse($request->input('day'));

        $year = $selectedDate->year;
        $month = $selectedDate->month;
        $day = $selectedDate->day;

        // Validate the provided date
        if (!checkdate($month, $day, $year)) {
            return $this->returnSuccess('Invalid date provided.', []);
        }

        // Create the start and end of the day
        $startOfDay = Carbon::create($year, $month, $day)->startOfDay();

        // Get inspections for the selected day
        $find_day = Inspections::whereDate('date', $startOfDay)->get();

        // Get inspections for the selected month
        $find_month = Inspections::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        // Get inspections for the selected year
        $find_year = Inspections::whereYear('date', $year)->get();

        $services_countday = $find_day->count();
        $services_countmonth = $find_month->count();
        $services_countyear = $find_year->count();

        $overdue_day = 0;
        $overdue_month = 0;
        $overdue_year = 0;

        $succeed_day = 0;
        $succeed_month = 0;
        $succeed_year = 0;

        $cancel_day = 0;
        $cancel_month = 0;
        $cancel_year = 0;

        foreach ($find_day as $item) {
            if ($item->status == 'overdue') {
                ++$overdue_day;
            } elseif ($item->status == 'unpaid') {
                ++$overdue_day;
            } elseif ($item->status == 'finish') {
                ++$succeed_day;
            } elseif ($item->status == 'cancel') {
                ++$cancel_day;
            }
        }

        foreach ($find_month as $item) {
            if ($item->status == 'overdue') {
                ++$overdue_month;
            } elseif ($item->status == 'unpaid') {
                ++$overdue_month;
            } elseif ($item->status == 'finish') {
                ++$succeed_month;
            } elseif ($item->status == 'cancel') {
                ++$cancel_month;
            }
        }

        foreach ($find_year as $item) {
            if ($item->status == 'overdue') {
                ++$overdue_year;
            } elseif ($item->status == 'unpaid') {
                ++$overdue_year;
            } elseif ($item->status == 'finish') {
                ++$succeed_year;
            } elseif ($item->status == 'cancel') {
                ++$cancel_year;
            }
        }

        // Count inspections per day in the selected month
        $inspectionsPerDayRaw = Inspections::selectRaw('DATE(date) as date, COUNT(*) as count')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Get the number of days in the selected month
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        // Initialize inspectionsPerDay array with 0 for each day of the month
        $inspectionsPerDay = array_fill(1, $daysInMonth, 0);

        // Update the inspectionsPerDay array with counts from the query result
        foreach ($inspectionsPerDayRaw as $date => $data) {
            $day = Carbon::parse($date)->day;
            $inspectionsPerDay[$day] = $data['count'];
        }

        // Count inspections per month in the selected year
        $inspectionsPerMonthRaw = Inspections::selectRaw('MONTH(date) as month, COUNT(*) as count')
            ->whereYear('date', $year)
            ->groupBy('month')
            ->get()
            ->keyBy('month')
            ->toArray();

        // Initialize inspectionsPerMonth array with 0 for each month of the year
        $inspectionsPerMonth = array_fill(1, 12, 0);

        // Update the inspectionsPerMonth array with counts from the query result
        foreach ($inspectionsPerMonthRaw as $monthNum => $data) {
            $inspectionsPerMonth[$monthNum] = $data['count'];
        }

        // Return the result along with debugging info
        // $data = [
        //     'Year' => $services_countyear,
        //     'complete_bill_year' => $services_countyear - $overdue_year,
        //     'overdue_year' => $overdue_year,
        //     'Month' => $services_countmonth,
        //     'complete_bill_month' => $services_countmonth - $overdue_month,
        //     'overdue_month' => $overdue_month,
        //     'Day' => $services_countday,
        //     'complete_bill_day' => $services_countday - $overdue_day,
        //     'overdue_day' => $overdue_day,
        //     'inspections_per_day' => array_values($inspectionsPerDay), // Only count values
        // ];
        $data = [
            'Day' => [
                'total_inspections' => $services_countday,
                'overdue' => $overdue_day,
                'succeed' => $succeed_day,
                'cancel' => $cancel_day,
            ],
            'Month' => [
                'total_inspections' => $services_countmonth,
                'overdue' => $overdue_month,
                'succeed' => $succeed_month,
                'cancel' => $cancel_month,
                'inspections_per_day' => array_values($inspectionsPerDay),
            ],
            'Year' => [
                'total_inspections' => $services_countyear,
                'overdue' => $overdue_year,
                'succeed' => $succeed_year,
                'cancel' => $cancel_year,
                'inspections_per_month' => array_values($inspectionsPerMonth), 
            ],
        ];

        return $this->returnSuccess('Bill', $data);
    }
}
