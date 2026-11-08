<?php
namespace App\Http\Controllers;

use App\Export\CarCheckExport;
use App\Export\CarreportExport;
use App\Export\CarTaxExport;
use App\Export\CommissionEmployeeExport;
use App\Export\CostdayExport;
use App\Export\CustomerreportExport;
use App\Export\DailyReportExport;
use App\Export\DayExport;
use App\Export\ExpenseExport;
use App\Export\ExpenseReportExport;
use App\Export\ExtentCustomerExport;
use App\Export\GasCheckExport;
use App\Export\Gasreport;
use App\Export\InsuranceExport;
use App\Export\InsuranceReportExport;
use App\Export\MonthExport;
use App\Export\ProfitReportExport;
use App\Export\ReportCarTaxVehicleExport;
use App\Export\SparepartreportExport;
use App\Export\CarTaxReportExport;
use App\Export\GasReportExport;
use App\Models\AddressEms;
use App\Models\Branches;
use App\Models\CheckGas;
use App\Models\Cost;
use App\Models\Customers;
use App\Models\Employees;
use App\Models\Inspections;
use App\Models\InspectionVehicles;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Quotation;
use App\Models\Services;
use App\Models\ServiceTransactions;
use App\Models\ServiceType;
use App\Models\TaxInvoice;
use App\Models\VehicleGas;
use App\Models\Vehicles;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Format;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

class ReportController extends Controller
{
    public function reportExportPDFday(Request $request)
    {
        $branchId = $request->input('branch_id', null);
        $startRaw = $request->input('start_date') ?: $request->input('date');
        $endRaw   = $request->input('end_date') ?: $startRaw;

        if (! $startRaw) {
            return $this->returnError('Date parameter is missing');
        }

        $startDate = Carbon::parse($startRaw)->toDateString();
        $endDate   = Carbon::parse($endRaw)->toDateString();

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        $query = Inspections::whereBetween('inspections.date', [$startDate, $endDate]);

        if ($employeeIds->isNotEmpty()) {
            $query->whereIn('inspections.employee_id', $employeeIds);
        }

        $items = $query->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $totalCash     = 0;
        $totalTransfer = 0;
        $totalDebt     = 0;
        $totalCredito  = 0;

        $html = '
        <thead>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <tr>
                    <td colspan="7" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        สรุปประจำวัน
                    </td>
                </tr>
                <tr>
                    <th>วันที่</th>
                    <th>บิล</th>
                    <th>เงินสด</th>
                    <th>โอน</th>
                    <th>เครดิต</th>
                    <th>ค้างชำระ</th>
                    <th>หมายเหตุ</th>
                </tr>';

        foreach ($items as $item) {
            if (! in_array($item->status, ['finish', 'overdue'], true)) {
                continue;
            }

            $installments = Installment::where('inspection_id', $item->id)->orderBy('id')->get();
            $cash         = 0;
            $transfer     = 0;
            $credit       = 0;
            $debt         = 0;
            $lastIndex    = $installments->count() - 1;

            foreach ($installments as $index => $installment) {
                if ($installment->paid_type == 'paid_cash') {
                    $cash += (float) $installment->paid_price;
                } elseif ($installment->paid_type == 'paid_tran') {
                    $transfer += (float) $installment->paid_price;
                } elseif ($installment->paid_type == 'credit') {
                    $credit += (float) $installment->paid_price;
                }
                if ($index === $lastIndex) {
                    $debt = (float) ($installment->after_price ?? 0);
                }
            }

            $totalCash += $cash;
            $totalTransfer += $transfer;
            $totalCredito += $credit;
            $totalDebt += $debt;

            $html .= '
                            <tr>
                                <td align="center">' . $item->date . '</td>
                                <td align="center">' . $item->no . '</td>
                                <td align="center">' . number_format($cash, 2) . '</td>
                                <td align="center">' . number_format($transfer, 2) . '</td>
                                <td align="center">' . number_format($credit, 2) . '</td>
                                <td align="center">' . number_format($debt, 2) . '</td>
                                <td>' . e($item->remark ?? '') . '</td>
                            </tr>';
        }

        $html .= '
            <tr>
                <td colspan="2" align="right"><strong>รวม</strong></td>
                <td align="center"><strong>' . number_format($totalCash, 2) . '</strong></td>
                <td align="center"><strong>' . number_format($totalTransfer, 2) . '</strong></td>
                <td align="center"><strong>' . number_format($totalCredito, 2) . '</strong></td>
                <td align="center"><strong>' . number_format($totalDebt, 2) . '</strong></td>
                <td align="center"><strong>' . '' . '</strong></td>

            </tr>';

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }
    public function reportExportPDFmonth(Request $request)
    {
        $branchId   = $request->input('branch_id', null);
        $start_date = $request->input('start_date');
        $end_date   = $request->input('end_date');
        $validator  = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $start_date = Carbon::parse($start_date);
        $end_date   = Carbon::parse($end_date);

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');
        $query       = Inspections::whereBetween('date', [$start_date, $end_date])->with('Installments');

        if ($employeeIds->isNotEmpty()) {
            $query->whereIn('employee_id', $employeeIds);
        }

        $items = $query->get();

        $costQuery = Cost::whereBetween('date', [$start_date, $end_date]);

        if (! empty($branchId)) {
            $costQuery->where('branch_id', $branchId);
        }

        $cost_cal = $costQuery->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Monthly Report');
        $mpdf->AddPage();

        $dailyTotals = [];
        foreach ($items as $item) {
            if (! in_array($item->status, ['finish', 'overdue'], true)) {
                continue;
            }

            $dateKey = $item->date;

            if (! isset($dailyTotals[$dateKey])) {
                $dailyTotals[$dateKey] = [
                    'cash'     => 0,
                    'transfer' => 0,
                    'credit'   => 0,
                    'debt'     => 0,
                    'total'    => 0,
                ];
            }
            foreach ($item->Installments as $index => $installment) {
                if ($installment->paid_type == 'paid_cash') {
                    $dailyTotals[$dateKey]['cash'] += $installment->paid_price;
                } elseif ($installment->paid_type == 'paid_tran') {
                    $dailyTotals[$dateKey]['transfer'] += $installment->paid_price;
                } elseif ($installment->paid_type == 'credit') {
                    $dailyTotals[$dateKey]['credit'] += $installment->paid_price;
                }
                if ($index === count($item->Installments) - 1) {
                    $dailyTotals[$dateKey]['debt'] += $installment->after_price;
                }
            }
            $dailyTotals[$dateKey]['total'] += $item->total;
        }

        $html = '
        <style>
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #333;
                padding: 5px;
                text-align: center;
                font-size: 12px;
            }
            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            h2 {
                font-size: 16px;
                margin: 0;
            }
            .text-left {
                text-align: left;
            }
        </style>

    <thead>
        <table>
            <tr>
                <td colspan="10" align="center" style="font-size: 22px; font-weight: bol">
                    เดือน ' . $start_date->addYears(543)->locale('th')->translatedFormat('F Y') . '
                </td>
            </tr>
            <tr>
                <th>วันที่</th>
                <th>รายการ</th>
                <th>เงินสด</th>
                <th>โอน</th>
                <th>เครดิต</th>
                <th>ค้างชำระ</th>
                <th>รายได้รวม</th>
                <th>รายจ่าย</th>
                <th>คงเหลือ</th>
                <th>หมายเหตุ</th>
            </tr>';

        $totalCash       = 0;
        $totalTransfer   = 0;
        $totalCredit     = 0;
        $totalDebt       = 0;
        $totalOverall    = 0;
        $totalCosts      = 0;
        $previousBalance = 0;

        foreach ($dailyTotals as $date => $totals) {

            $currentBalance = $previousBalance + $totals['total'];
            $html .= '
            <tr>
                <td>' . Carbon::parse($date)->addYears(543)->format('d/m/Y') . '</td>
                <td>สรุปประจำวัน</td>
                <td>' . ($totals['cash'] > 0 ? number_format($totals['cash'], 2) : '&nbsp;') . '</td>
                <td>' . ($totals['transfer'] > 0 ? number_format($totals['transfer'], 2) : '&nbsp;') . '</td>
                <td>' . ($totals['credit'] > 0 ? number_format($totals['credit'], 2) : '&nbsp;') . '</td>
                <td>' . ($totals['debt'] > 0 ? number_format($totals['debt'], 2) : '&nbsp;') . '</td>
                <td>' . ($totals['total'] > 0 ? number_format($totals['total'], 2) : '&nbsp;') . '</td>
                <td></td>
                <td>' . number_format($currentBalance, 2) . '</td>
                <td></td>
            </tr>';

            $totalCash += $totals['cash'];
            $totalTransfer += $totals['transfer'];
            $totalCredit += $totals['credit'];
            $totalDebt += $totals['debt'];
            $totalOverall += $totals['total'];
            $previousBalance = $currentBalance;
        }

        foreach ($cost_cal as $cost) {
            $currentBalanceAfterCost = $previousBalance - $cost->price; // คำนวณคงเหลือหลังจากลบรายจ่าย
            $html .= '
            <tr>
                <td>' . '' . '</td>
                <td class="text-left">' . $cost->name . '</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>' . number_format($cost->price, 2) . '</td>
                <td>' . number_format($currentBalanceAfterCost, 2) . '</td>
                <td></td>
            </tr>';
            $totalCosts += $cost->price;
            $previousBalance = $currentBalanceAfterCost;
        }

        $finalBalance = $totalOverall - $totalCosts;
        $html .= '
        <tr>
            <td colspan="2" align="center"><strong>รวม</strong></td>
            <td><strong>' . number_format($totalCash, 2) . '</strong></td>
            <td><strong>' . number_format($totalTransfer, 2) . '</strong></td>
            <td><strong>' . number_format($totalCredit, 2) . '</strong></td>
            <td><strong>' . number_format($totalDebt, 2) . '</strong></td>
            <td><strong>' . number_format($totalOverall, 2) . '</strong></td>
            <td><strong>' . number_format($totalCosts, 2) . '</strong></td>
            <td><strong>' . number_format($finalBalance, 2) . '</strong></td>
            <td><strong>' . '' . '</strong></td>
        </tr>';

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDF_gas(Request $request)
    {

        $branchId  = trim($request->input('branch_id', null));
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        $query = DB::table('inspections')
            ->join('customers', 'customers.id', '=', 'inspections.customer_id')
            ->join('employees', 'employees.id', '=', 'inspections.employee_id')
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->leftJoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->leftJoin('vehicle_gases', 'vehicle_gases.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->select(
                'employees.code',
                'customers.no',
                'customers.name',
                'inspections.service_or',
                'inspections.date as day',
                'vehicles.license_plate',
                'provinces.name_th_prefix as province_name',
                'vehicle_brands.name as brand_name',
                'inspections.install_no',
                'inspections.CHA_no'
            )
            ->whereIn('service_transactions.service_id', [2, 8])
            ->when($employeeIds->isNotEmpty(), function ($query) use ($employeeIds) {
                return $query->whereIn('inspections.employee_id', $employeeIds);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [base_path() . '/custom/font/directory']),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Inspection Report');
        $mpdf->AddPage();

        $html = '
            <style>
                .no-data {
                    text-align: center;
                    color: #888;
                    padding: 20px 0;
                }
                th, td {
                    padding: 5px;
                    text-align: center;
                }
            </style>
            <thead>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <tr>
                    <td colspan="10" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รายงานสภาพแก๊สรถยนต์
                    </td>
                </tr>
                <tr>
                    <th style="width: 5%;">รหัสร้าน</th>
                    <th style="width: 15%;">ชื่อลูกค้า</th>
                    <th style="width: 10%;">เลขที่ตรวจ</th>
                    <th style="width: 5%;">วันที่</th>
                    <th style="width: 7%;">เลขทะเบียนวิศวกร</th>
                    <th style="width: 10%;">หมายเลขทะเบียนรถ</th>
                    <th style="width: 10%;">จังหวัด</th>
                    <th style="width: 10%;">ยี่ห้อรถ</th>
                    <th style="width: 5%;">เลขใบติดตั้ง</th>
                    <th style="width: 5%;">เลขต่อถัง</th>
                </tr>';

        if ($query->isEmpty()) {
            $html .= '<tr><td colspan="10" class="no-data">ไม่มีรายการ</td></tr>';
        } else {

            foreach ($query as $row) {
                $html .= '
                    <tr>
                        <td>' . $row->no . '</td>
                        <td>' . $row->name . '</td>
                        <td>' . $row->service_or . '</td>
                        <td>' . $row->day . '</td>
                        <td>' . $row->code . '</td>
                        <td>' . $row->license_plate . '</td>
                        <td>' . $row->province_name . '</td>
                        <td>' . $row->brand_name . '</td>
                        <td>' . $row->install_no . '</td>
                        <td>' . $row->CHA_no . '</td>
                    </tr>';
            }
        }

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFexpense(Request $request)
    {

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Expense Report');
        $mpdf->AddPage();

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        $query = Cost::select('date', 'name', 'price', 'detail');
        $this->applyCostBranchFilter($query, $branchId);
        $query->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });

        $costs = $query->get();

        $totalAmount = 0;

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
            }
        </style>
        <thead>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <tr>
                <td colspan="5" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                    รวมค่าใช้จ่าย
                </td>
            </tr>
            <tr>
                <th>#</th>
                <th>วันที่</th>
                <th>รายการ</th>
                <th>ยอดเงิน</th>
                <th>จัดการ</th>
            </tr>';

        foreach ($costs as $index => $cost) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $cost->date . '</td>';
            $html .= '<td>' . $cost->name . '</td>';
            $html .= '<td>' . number_format($cost->price, 2) . '</td>';
            $html .= '<td>' . $cost->detail . '</td>';
            $html .= '</tr>';

            $totalAmount += $cost->price;
        }

        if ($costs->isEmpty()) {
            $html .= '<tr>
                <td colspan="5" class="no-data">ไม่มีรายการ</td>
            </tr>';
        }

        // Add total row
        $html .= '<tr>
            <td colspan="3" style="text-align: right; font-weight: bold;">รวม:</td>
            <td style="font-weight: bold;">' . number_format($totalAmount, 2) . '</td>
        </tr>';

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFcheck_gas(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        $employeeIds = [];
        if (! is_null($branchId)) {
            $employeeIds = Employees::where('branch_id', $branchId)->pluck('id')->toArray();
        }

        $query = Inspections::query()
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->join('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->join('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->join('services', 'service_transactions.service_id', '=', 'services.id')
            ->select(
                'inspections.no',
                'vehicles.license_plate',
                'provinces.name_th_prefix',
                'customers.name',
                'vehicles.registration_date',
                'inspections.date',
                'services.id as service_id',
                'inspections.service_or',
                'inspections.result',
                'inspections.status',
                'service_transactions.service_price'
            )
            ->whereIn('service_transactions.service_id', [9, 10]);

        $query->whereBetween('inspections.date', [$startDate, $endDate]);

        if (! empty($employeeIds)) {
            $query->whereIn('inspections.employee_id', $employeeIds);
        }

        $results = $query->get();

        $uniqueResults = $results->unique(function ($item) {
            return $item->no . $item->license_plate . $item->date;
        });
        // return $uniqueResults;

        // Configure mPDF
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Gas Inspection Report');
        $mpdf->AddPage();

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #333;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
        </style>
            <thead>
            <table>
                <tr>
                    <td colspan="12" align="center" style="font-size: 22px; font-weight: bold;">
                        รายการตรวจแก๊ส LPG/NGV ' . $startDate->format('d/m/Y') . ' ถึง ' . $endDate->format('d/m/Y') . '
                    </td>
                </tr>
                <tr>
                    <th>ลำดับ</th>
                    <th>เลขที่</th>
                    <th>ทะเบียน</th>
                    <th>จังหวัด</th>
                    <th>ชื่อลูกค้า</th>
                    <th>วันที่จดทะเบียน</th>
                    <th>วันที่ตรวจ</th>
                    <th>ชนิด</th>
                    <th>เลขใบตรวจ</th>
                    <th>ผลตรวจ</th>
                    <th>ค่าตรวจ</th>
                    <th>ค้าง</th>
                </tr>';

        if ($uniqueResults->isEmpty()) {
            $html .= '<tr><td colspan="12" class="no-data">ไม่มีรายการ</td></tr>';
        } else {
            $index = 1;
            foreach ($uniqueResults as $result) {
                $serviceName = ($result->service_id == 9) ? 'LPG' : (($result->service_id == 10) ? 'NGV' : '');
                $html .= '<tr>
                <td>' . $index++ . '</td>
                <td>' . htmlspecialchars($result->no) . '</td>
                <td>' . htmlspecialchars($result->license_plate) . '</td>
                <td>' . htmlspecialchars($result->name_th_prefix) . '</td>
                <td>' . htmlspecialchars($result->name) . '</td>
                <td>' . htmlspecialchars($result->registration_date) . '</td>
                <td>' . htmlspecialchars($result->date) . '</td>
                <td>' . htmlspecialchars($serviceName) . '</td>
                <td>' . htmlspecialchars($result->service_or) . '</td>
                <td>' . htmlspecialchars($result->result) . '</td>
                <td>' . htmlspecialchars($result->service_price) . '</td>
                <td>' . ($result->status === 'overdue' ? 'ค้างชำระ' : '-') . '</td>
            </tr>';
            }
        }

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }
    public function reportExportPDFdaily_report(Request $request)
    {

        $check_date = $request->input('check_date') ? Carbon::parse($request->input('check_date')) : null;
        $validator  = Validator::make($request->all(), [
            'check_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบหรือรูปแบบไม่ถูกต้อง');
        }
        $branchId = $request->input('branch_id', null);

        $employeeIds = [];
        if ($branchId) {
            $employeeIds = Employees::where('branch_id', $branchId)->pluck('id')->toArray();
        }

        $selectColumns = [
            'inspections.no AS inspection_no',
            'vehicles.license_plate',
            'customers.name AS customer_name',
            'inspections.discount',
            'inspections.total',
            'service_transactions.service_price AS service_price_tran',
            'services.name AS service_name',
            'services.price AS service_price',
            'services.id AS service_id',
        ];

        $query = Inspections::select($selectColumns)
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('customers', 'customers.id', '=', 'vehicles.customer_id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->leftJoin('services', 'services.id', '=', 'service_transactions.service_id');

        if ($check_date) {
            $query->whereDate('inspections.date', $check_date);
        }

        if (! empty($employeeIds)) {
            $query->whereIn('inspections.employee_id', $employeeIds);
        }

        $items = $query->get();

        $groupedItems = $items->groupBy('inspection_no')->map(function ($group) {
            $firstItem           = $group->first();
            $serviceTransactions = $group->map(function ($item) {
                return [
                    'service_id'         => $item->service_id,
                    'service_name'       => $item->service_name,
                    'service_price'      => $item->service_price,
                    'service_price_tran' => $item->service_price_tran,
                ];
            });

            $aggregatedServices = $serviceTransactions->groupBy('service_name')->map(function ($group) {
                return [
                    'service_name'       => $group->first()['service_name'],
                    'service_price'      => $group->sum('service_price'),
                    'service_price_tran' => $group->sum('service_price_tran'),
                ];
            });

            $specifiedServices = $aggregatedServices->only(['ตรวจสภาพ', 'พ.ร.บ.', 'ภาษีประจำปี', 'ประกัน']);
            $otherServices     = $aggregatedServices->filter(function ($item) {
                return ! in_array($item['service_name'], ['ตรวจสภาพ', 'พ.ร.บ.', 'ภาษีประจำปี', 'ประกัน']);
            });

            if ($otherServices->isNotEmpty()) {
                $specifiedServices->put('Others', [
                    'service_name'       => 'Others',
                    'service_price'      => $otherServices->sum('service_price'),
                    'service_price_tran' => $otherServices->sum('service_price_tran'),
                ]);
            }

            return [
                'inspection_details'   => [
                    'inspection_no' => $firstItem->inspection_no,
                    'license_plate' => $firstItem->license_plate,
                    'customer_name' => $firstItem->customer_name,
                    'discount'      => $firstItem->discount,
                    'total'         => $firstItem->total,
                ],
                'service_transactions' => $specifiedServices,
            ];
        });

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $html = '
            <style>
                .no-data {
                    text-align: center;
                    color: #888;
                    padding: 20px 0;
                }
            </style>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th rowspan="2">ลำดับ</th>
                        <th rowspan="2">เลขที่</th>
                        <th rowspan="2">ทะเบียน</th>
                        <th rowspan="2">ชื่อลูกค้า</th>
                        <th colspan="5">บริการ</th>
                        <th colspan="3">ค่าธรรมเนียม</th>
                        <th rowspan="2">ส่วนลด</th>
                        <th rowspan="2">สุทธิ</th>
                    </tr>
                    <tr>
                        <th>ตรวจสภาพ</th>
                        <th>พ.ร.บ.</th>
                        <th>ภาษีประจำปี</th>
                        <th>ประกัน</th>
                        <th>อื่น ๆ</th>
                        <th>พ.ร.บ.</th>
                        <th>ภาษีประจำปี</th>
                    <th>อื่น ๆ</th>
                    </tr>
                </thead>
                <tbody>';

        $No = 1;
        foreach ($groupedItems as $item) {
            $services = [
                'ตรวจสภาพ'    => ['service_price_tran' => 0, 'service_price' => 0],
                'พ.ร.บ.'      => ['service_price_tran' => 0, 'service_price' => 0],
                'ภาษีประจำปี' => ['service_price_tran' => 0, 'service_price' => 0],
                'ประกัน'      => ['service_price_tran' => 0, 'service_price' => 0],
                'Others'      => ['service_price_tran' => 0, 'service_price' => 0],
            ];

            foreach ($item['service_transactions'] as $service) {
                if (isset($services[$service['service_name']])) {
                    $services[$service['service_name']]['service_price_tran'] += $service['service_price_tran'];
                    $services[$service['service_name']]['service_price'] += $service['service_price'];
                } else {
                    $services['Others']['service_price_tran'] += $service['service_price_tran'];
                    $services['Others']['service_price'] += $service['service_price'];
                }
            }

            $html .= '
                <tr>
                    <td>' . $No++ . '</td>
                    <td>' . $item['inspection_details']['inspection_no'] . '</td>
                    <td>' . $item['inspection_details']['license_plate'] . '</td>
                    <td>' . $item['inspection_details']['customer_name'] . '</td>
                    <td>' . $services['ตรวจสภาพ']['service_price_tran'] . '</td>
                    <td>' . $services['พ.ร.บ.']['service_price_tran'] . '</td>
                    <td>' . $services['ภาษีประจำปี']['service_price_tran'] . '</td>
                    <td>' . $services['ประกัน']['service_price_tran'] . '</td>
                    <td>' . $services['Others']['service_price_tran'] . '</td>
                    <td>' . $services['ภาษีประจำปี']['service_price'] . '</td>
                    <td>' . $services['ประกัน']['service_price'] . '</td>
                    <td>' . $services['Others']['service_price'] . '</td>
                    <td>' . $item['inspection_details']['discount'] . '</td>
                    <td>' . $item['inspection_details']['total'] . '</td>
                </tr>';
        }

        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('report.pdf');
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFinsurance_report(Request $request)
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4-l',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);
        $mpdf->SetTitle('Insurance Report');
        $mpdf->AddPage();

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = trim($request->input('branch_id', null));

        // $employeeIds = [];
        // if ($branchId) {
        //     $employeeIds = Employees::where('branch_id', $branchId)->pluck('id')->toArray();
        // }

        $data = Inspections::select([
            'inspections.*',
            'customers.*',
            'vehicles.*',
            'prb_renewals.policy_number',
            'prb_renewals.date_start',
            'prb_renewals.date_end',
            'prb_renewals.amount_paid',
            'prb_renewals.remark',
            'prb_renewals.stamp',
            'prb_renewals.tax',
            'service_transactions.*',
            'services.price as service_price',
            'vehicle_inspection_types.name as vehicle_type_name',
        ])
            ->join('customers', 'customers.id', '=', 'inspections.customer_id')
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->leftJoin('vehicle_inspection_types', 'vehicle_inspection_types.id', '=', 'vehicles.vehicle_inspection_types_id')
            ->join('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->join('services', 'services.id', '=', 'service_transactions.service_id')
            ->leftJoin('prb_renewals', 'prb_renewals.inspection_id', '=', 'inspections.id')
            ->where('service_transactions.service_id', 2)
            ->where('service_transactions.status', 1)
            ->get();

        // return $data;

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
            }
        </style>
        <thead>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <tr>
                <td colspan="13" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                    รายการออก พ.ร.บ - แยกตามบริษัทประกันภัย วันที่ขาย ' . now()->format('d/m/Y') . '
                </td>
            </tr>
            <tr>
                <th>#</th>
                <th>เลขใบวางบิล</th>
                <th>ชื่อลูกค้า</th>
                <th>ทะเบียน</th>
                <th>ประเภท</th>
                <th>เลขกรรมธรรม์</th> -->
                <th>วันที่เริ่มต้น</th>
                <th>วันที่สิ้นสุด</th>
                <th>อากร</th>
                <th>vat</th>
                <th>สุทธิ</th>
                <th>รับเงิน</th>
                <th>หมายเหตุ</th> -->
            </tr>';

        foreach ($data as $index => $customer) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $customer->no ?? null . '</td>';
            $html .= '<td>' . $customer->name ?? null . '</td>';
            $html .= '<td>' . $customer->license_plate ?? null . '</td>';
            $html .= '<td>' . $customer->vehicle_type_name ?? null . '</td>';
            $html .= '<td>' . $customer->policy_number ?? null . '</td>';
            $html .= '<td>' . $customer->date_start ?? null . '</td>';
            $html .= '<td>' . $customer->date_end ?? null . '</td>';
            $html .= '<td>' . $customer->stamp . '</td>';
            $html .= '<td>' . $customer->tax . '</td>';
            $html .= '<td>' . $customer->total . '</td>';
            $html .= '<td>' . $customer->service_price . '</td>';
            $html .= '<td>' . $customer->remark . '</td>';
            $html .= '</tr>';
        }

        if ($data->isEmpty()) {
            $html .= '<tr>
                <td colspan="13" class="no-data">ไม่มีรายการ</td>
            </tr>';
        }

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFinsurance(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = trim($request->input('branch_id', null));
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $data = Inspections::select([
            'inspections.*',
            'customers.*',
            'insurances_renewals.policy_number',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.amount_paid',
            'insurances_renewals.remark',
            'insurance_names.name as insurance_name',
            'vehicles.*',
            'service_transactions.*',
            'services.price as service_price',
        ])
            ->join('customers', 'customers.id', '=', 'inspections.customer_id')
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->join('services', 'services.id', '=', 'service_transactions.service_id')
            ->join('insurances_renewals', 'insurances_renewals.inspection_id', '=', 'inspections.id')
            ->join('insurance_names', 'insurance_names.id', '=', 'insurances_renewals.insurance_names_id')
            ->where('service_transactions.service_id', 6)
            ->where('service_transactions.status', 1)
            ->get();

        // return $data;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4-l',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Insurance Report');
        $mpdf->AddPage();

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
            }
        </style>
        <thead>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <tr>
                <td colspan="12" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                    รายการออกประกันภัย - แยกตามบริษัทประกันภัย  วันที่ขาย ' . now()->format('d/m/Y') . '
                </td>
            </tr>
            <tr>
                <th>#</th>
                <th>เลขใบวางบิล</th>
                <th>ชื่อลูกค้า</th>
                <th>ทะเบียน</th>
                <th>ประกันภัย</th>
                <th>เลขกรรมธรรม์</th>
                <th>วันที่เริ่มต้น</th>
                <th>วันที่สิ้นสุด</th>
                <th>เบี้ย</th>
                <th>สุทธิ</th>
                <th>ค่าบริการ</th>
                <th>หมายเหตุ</th>
            </tr>';

        foreach ($data as $index => $customer) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $customer->no ?? null . '</td>';
            $html .= '<td>' . $customer->name ?? null . '</td>';
            $html .= '<td>' . $customer->license_plate ?? null . '</td>';
            $html .= '<td>' . $customer->insurance_name ?? null . '</td>';
            $html .= '<td>' . $customer->policy_number ?? null . '</td>';
            $html .= '<td>' . $customer->date_start ?? null . '</td>';
            $html .= '<td>' . $customer->date_end ?? null . '</td>';
            $html .= '<td>' . $customer->amount_paid ?? null . '</td>';
            $html .= '<td>' . $customer->total . '</td>';
            $html .= '<td>' . $customer->service_price ?? null . '</td>';
            $html .= '<td>' . $customer->remark ?? null . '</td>';
            $html .= '</tr>';
        }

        if ($data->isEmpty()) {
            $html .= '
            <tr>
                <td colspan="12" class="no-data">ไม่มีรายการ</td>
            </tr>';
        }

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFreport_car_tax(Request $request)
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);
        $startDate = $startDate ? $startDate->copy()->startOfDay() : null;
        $endDate   = $endDate ? $endDate->copy()->endOfDay() : null;

        $taxVehicle = ($request->has('tax_vehicle') && $request->input('tax_vehicle') !== null)
            ? $request->input('tax_vehicle')
            : null;

        $inspections = $this->enrichCarTaxMainReportRows(
            $this->buildCarTaxReportInspectionQuery($startDate, $endDate, $branchId, $taxVehicle)->get()
        );

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            th, td {
                padding: 8px;
                text-align: center;
            }
        </style>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <td colspan="11" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รายงานภาษีรถ ' . $this->convertDateFormat($startDate ?? '') . ' ถึง ' . $this->convertDateFormat($endDate ?? '') . '
                    </td>
                </tr>
                <tr>
                    <th style="width:12%;">วันที่ยื่นภาษี</th>
                    <th style="width:8%;">ลำดับ</th>
                    <th style="width:10%;">เลขที่</th>
                    <th style="width:10%;">ทะเบียน</th>
                    <th style="width:12%;">เบอร์โทร</th>
                    <th style="width:8%;">ยี่ห้อ</th>
                    <th style="width:8%;">เรียกเก็บ</th>
                    <th style="width:8%;">ยอดจริง</th>
                    <th style="width:7%;">คืน</th>
                    <th style="width:7%;">เก็บเพิ่ม</th>
                    <th style="width:10%;">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>';
        if ($inspections->isEmpty()) {
            $html .= '<tr><td colspan="11" class="no-data">ไม่มีรายการ</td></tr>';
        } else {
            $index = 1;
            foreach ($inspections as $inspection) {
                $rowDate = $inspection->tax_renewal_date
                    ? $this->convertDateFormat($inspection->tax_renewal_date)
                    : '-';
                $html .= '
                    <tr>
                        <td>' . $rowDate . '</td>
                        <td>' . $index++ . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->no ?? null) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->license_plate ?? null) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->phone_number ?? null) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->brand_name ?? null) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->billed ?? null, true) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->paytrue ?? null, true) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->refund ?? null, true) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->extra ?? null, true) . '</td>
                        <td>' . $this->formatCarTaxReportCell($inspection->remark ?? null) . '</td>
                    </tr>';
            }
        }
        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportExcel_report_car_tax(Request $request)
    {
        // ✅ ตรวจสอบวันที่
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();
        $branchId  = $request->input('branch_id');

        $taxVehicle = ($request->has('tax_vehicle') && $request->input('tax_vehicle') !== null)
            ? $request->input('tax_vehicle')
            : null;

        $inspections = $this->enrichCarTaxMainReportRows(
            $this->buildCarTaxReportInspectionQuery($startDate, $endDate, $branchId, $taxVehicle)->get()
        );

        // ✅ ส่งออก Excel (ใช้ Export class ด้านล่าง)
        $fileName = sprintf(
            'รายงานภาษีรถ.xlsx',
            $startDate->format('Ymd'),
            $endDate->format('Ymd')
        );

        return Excel::download(
            new CarTaxReportExport($inspections, $startDate, $endDate),
            $fileName
        );

    }

    public function reportExportPDFexpense_report(Request $request)
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Expense Report');
        $mpdf->AddPage();

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $branchId = trim($request->input('branch_id', null));

        $query = Cost::select('date', 'name', 'price')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });

        $costs = $query->get();

        $totalAmount = 0;

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
            }
        </style>
        <thead>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <tr>
                <td colspan="4" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                    รวมค่าใช้จ่าย
                </td>
            </tr>
            <tr>
                <th>#</th>
                <th>วันที่</th>
                <th>รายการ</th>
                <th>ยอดเงิน</th>
            </tr>';

        foreach ($costs as $index => $cost) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $cost->date . '</td>';
            $html .= '<td>' . $cost->name . '</td>';
            $html .= '<td>' . number_format($cost->price, 2) . '</td>';
            $html .= '</tr>';

            $totalAmount += $cost->price;
        }

        if ($costs->isEmpty()) {
            $html .= '
            <tr>
                <td colspan="4" class="no-data">ไม่มีรายการ</td>
            </tr>';
        }

        $html .= '
        <tr>
            <td colspan="3" style="text-align: right; font-weight: bold;">รวม:</td>
            <td style="font-weight: bold;">' . number_format($totalAmount, 2) . '</td>
        </tr>';

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFlist_check_gas(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $branchId = $request->input('branch_id', null);

        $query = Inspections::query()
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->join('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->join('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->join('services', 'service_transactions.service_id', '=', 'services.id')
            ->select(
                'inspections.no',
                'vehicles.license_plate',
                'provinces.name_th_prefix',
                'customers.name',
                'vehicles.registration_date',
                'inspections.date',
                'services.id as service_id',
                'inspections.service_or',
                'inspections.result',
                'inspections.status',
                'service_transactions.service_price'

            )
            ->whereIn('service_transactions.service_id', [9, 10]);

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! is_null($branchId)) {
            $query->where('inspections.branch_id', $branchId);
        }

        $results = $query->get();

        // Remove duplicates based on unique combination of fields
        $uniqueResults = $results->unique(function ($item) {
            return $item->no . $item->license_plate . $item->date;
        });

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Gas Inspection Report');
        $mpdf->AddPage();

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #333;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
        </style>
        <thead>
        <table>
            <tr>
                <td colspan="12" align="center" style="font-size: 22px; font-weight: bold;">
                    รายการตรวจแก๊ส LPG/NGV ' . $startDate->format('d/m/Y') . ' ถึง ' . $endDate->format('d/m/Y') . '
                </td>
            </tr>
            <tr>
                <th>ลำดับ</th>
                <th>เลขที่</th>
                <th>ทะเบียน</th>
                <th>จังหวัด</th>
                <th>ชื่อลูกค้า</th>
                <th>วันที่จดทะเบียน</th>
                <th>วันที่ตรวจ</th>
                <th>ชนิด</th>
                <th>เลขใบตรวจ</th>
                <th>ผลตรวจ</th>
                <th>ค่าตรวจ</th>
                <th>ค้าง</th>
            </tr>';

        if ($uniqueResults->isEmpty()) {
            $html .= '<tr><td colspan="12" class="no-data">ไม่มีรายการ</td></tr>';
        } else {
            $index = 1;
            foreach ($uniqueResults as $result) {
                $serviceName = ($result->service_id == 9) ? 'LPG' : (($result->service_id == 10) ? 'NGV' : '');
                $html .= '<tr>
                    <td>' . $index++ . '</td>
                    <td>' . htmlspecialchars($result->no) . '</td>
                    <td>' . htmlspecialchars($result->license_plate) . '</td>
                    <td>' . htmlspecialchars($result->name_th_prefix) . '</td>
                    <td>' . htmlspecialchars($result->name) . '</td>
                    <td>' . htmlspecialchars($result->registration_date) . '</td>
                    <td>' . htmlspecialchars($result->date) . '</td>
                    <td>' . htmlspecialchars($serviceName) . '</td>
                    <td>' . htmlspecialchars($result->service_or) . '</td>
                    <td>' . htmlspecialchars($result->result) . '</td>
                    <td>' . htmlspecialchars($result->service_price) . '</td>
                    <td>' . $result->status === 'overdue' ? 'ค้างชำระ' : ($result->status === 'open' ? 'รอชำระ' : '-') . '</td>
                </tr>';
            }
        }

        $html .= '</table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFcommission_employee(Request $request)
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Commission Employee Report');
        $mpdf->AddPage();

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $branchId = trim($request->input('branch_id', null));

        $query = Employees::query()
            ->with([
                'inspections' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('date', [$startDate, $endDate]);
                    }
                    $query->with('serviceTransactions.service');
                },
            ]);

        if (! is_null($branchId) && $branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        $employees = $query->get();

        $serviceNames = [
            1  => 'ตรวจสภาพ',
            2  => 'พ.ร.บ.',
            3  => 'ภาษีประจำปี',
            4  => 'ค่าปรับภาษี',
            5  => 'บริการต่อภาษี',
            6  => 'ค่าบริการออกพ.ร.บ.',
            7  => 'ประกัน',
            8  => 'ขนส่ง',
            9  => 'ตรวจแก๊ส LPG',
            10 => 'ตรวจแก๊ส NGV ',
            11 => 'หนังสือรับรองวิศวกร',
            12 => 'อื่นๆ',
            13 => 'EMS',
        ];

        $serviceCounts = [];
        foreach ($employees as $employee) {
            $employeeName                 = $employee->first_name . ' ' . $employee->last_name;
            $serviceCounts[$employeeName] = $employee->inspections->flatMap(function ($inspection) {
                return $inspection->serviceTransactions->map(function ($transaction) {
                    return $transaction->service->id;
                });
            })->countBy();
        }

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
            }
        </style>
        <thead>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th rowspan="2">#</th>
                    <th rowspan="2">ชื่อ</th>
                    <th rowspan="2">นามสกุล</th>
                    <th rowspan="2">อีเมล</th>
                    <th rowspan="2">เบอร์โทร</th>
                    <th colspan="13">บริการ</th>
                </tr>
                <tr>';

        foreach ($serviceNames as $serviceName) {
            $html .= '<th>' . $serviceName . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        // Add employee data to HTML
        foreach ($employees as $index => $employee) {
            $employeeName = $employee->first_name . ' ' . $employee->last_name;
            $services     = $serviceCounts[$employeeName] ?? [];

            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $employee->first_name . '</td>';
            $html .= '<td>' . $employee->last_name . '</td>';
            $html .= '<td>' . $employee->email . '</td>';
            $html .= '<td>' . $employee->phone_number . '</td>';

            foreach ($serviceNames as $serviceId => $serviceName) {
                $html .= '<td>' . ($services[$serviceId] ?? 0) . '</td>';
            }

            $html .= '</tr>';
        }

        if ($employees->isEmpty()) {
            $html .= '<tr>
                <td colspan="' . (count($serviceNames) + 5) . '" class="no-data">ไม่มีรายการ</td>
            </tr>';
        }

        $html .= '</tbody></table></thead>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFprofit_report(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        $servicePrices = Services::pluck('price', 'id')->toArray();

        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'vehicles.license_plate',
            'customers.name',
        ];

        $query = Inspections::select($columns)
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('customers', 'customers.id', '=', 'vehicles.customer_id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->leftJoin('services', 'services.id', '=', 'service_transactions.service_id')
            ->groupBy('inspections.id', 'inspections.no', 'inspections.date', 'vehicles.license_plate', 'customers.name')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->when($branchId && $employeeIds->isNotEmpty(), function ($query) use ($employeeIds) {
                $query->whereIn('inspections.employee_id', $employeeIds);
            });

        $items = $query->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
        </style>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="width: 3%;" rowspan="2">#</th>
                    <th style="width: 10%;" rowspan="2">เลขที่</th>
                    <th style="width: 10%;" rowspan="2">วันที่</th>
                    <th style="width: 10%;" rowspan="2">ทะเบียน</th>
                    <th style="width: 15%;" rowspan="2">ชื่อลูกค้า</th>
                    <th colspan="13">บริการ</th>
                    <th style="width: 10%;"rowspan="2">สุทธิ</th>
                </tr>
                <tr>
                    <th style="width: 10%;">ตรวจสภาพ</th>
                    <th style="width: 10%;">พ.ร.บ LPG</th>
                    <th style="width: 10%;">ภาษีประจำปี</th>
                    <th style="width: 10%;">ค่าปรับภาษี</th>
                    <th style="width: 10%;">บริการต่อภาษี</th>
                    <th style="width: 10%;">ค่าบริการออกพ.ร.บ.</th>
                    <th style="width: 10%;">ประกัน</th>
                    <th style="width: 10%;">ขนส่ง</th>
                    <th style="width: 10%;">LPG</th>
                    <th style="width: 10%;">NGV</th>
                    <th style="width: 10%;">หนังสือรับรองวิศวกร</th>
                    <th style="width: 10%;">อื่นๆ</th>
                    <th style="width: 10%;">EMS</th>
                </tr>
            </thead>
            <tbody>';

        $No = 1;
        foreach ($items as $item) {
            $serviceTotals = [
                1  => 0,
                2  => 0,
                3  => 0,
                4  => 0,
                5  => 0,
                6  => 0,
                7  => 0,
                8  => 0,
                9  => 0,
                10 => 0,
                11 => 0,
                12 => 0,
                13 => 0,
            ];

            foreach ($item->serviceTransactions as $transaction) {
                $serviceId = $transaction->service_id;
                if (isset($servicePrices[$serviceId])) {
                    $serviceTotals[$serviceId] = $servicePrices[$serviceId];
                }
            }

            $html .= '
            <tr >
                <td style="text-align: center; width: ">' . $No++ . '</td>
                <td style="text-align: center;">' . htmlspecialchars($item->no) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($item->date) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($item->license_plate) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($item->name) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[1] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[2] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[3] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[4] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[5] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[6] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[7] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[8] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[9] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[10] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[11] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[12] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($serviceTotals[13] ?? ' ') . '</td>
                <td style="text-align: center;">' . htmlspecialchars(array_sum($serviceTotals)) . '</td>
            </tr>';
        }

        if ($No === 1) {
            $html .= '
            <tr>
                <td colspan="14" class="no-data">ไม่มีรายการ</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportExportPDFcheck_car_report(Request $request)
    {
        // Handle input dates
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $branchId = $request->input('branch_id', null);

        // Step 1: Get employee IDs from the given branch
        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        // Step 2: Fetch inspections associated with those employees
        $items = Inspections::select(
            'inspections.no',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.registration_date',
            'inspection_vehicles.provinces',
            'inspection_vehicles.vehicle_inspection_types',
            'customers.name',
            'inspections.date',
            DB::raw("CASE WHEN inspections.is_pass = 1 THEN 'ผ่าน' WHEN inspections.is_pass = 0 THEN 'ไม่ผ่าน' ELSE '-' END as is_pass"),
            DB::raw('SUM(CASE WHEN services.id = 1 THEN services.price ELSE 0 END) as total_price'),
            'inspections.result'
        )
            ->leftjoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions as vehicleserviceTransactions', 'inspection_vehicles.id', '=', 'vehicleserviceTransactions.inspection_vehicle_id')
            ->leftJoin('services', 'vehicleserviceTransactions.service_id', '=', 'services.id')
            ->where('vehicleserviceTransactions.service_id', 1)
            ->where('inspections.branch_id', $branchId)
            ->whereIn('inspections.status', ['open', 'finish', 'overdue', 'unpaid'])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->groupBy(
                'inspections.no',
                'inspection_vehicles.license_plate',
                'inspection_vehicles.registration_date',
                'inspection_vehicles.provinces',
                'inspection_vehicles.vehicle_inspection_types',
                'customers.name',
                'inspections.date',
                'inspections.result',
                'inspections.is_pass'
            )
            ->get();
        // return $items;
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        // Initialize mPDF with Thai Sarabun fonts
        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4-l',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [base_path() . '/custom/font/directory']),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        // Step 3: Build the HTML content
        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
        </style>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <td colspan="11" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รายการตรวจสภาพรถ ' . $startDate->addYears(543)->format('d/m/Y') . ' ถึง ' . $endDate->addYears(543)->format('d/m/Y') . '
                    </td>
                </tr>
                <tr>
                    <th>ลำดับ</th>
                    <th>เลขที่</th>
                    <th>ทะเบียน</th>
                    <th>จังหวัด</th>
                    <th>ประเภท</th>
                    <th>ชื่อลูกค้า</th>
                    <th>วันที่จดทะเบียน</th>
                    <th>วันที่ตรวจ</th>
                    <th>ผลตรวจ</th>
                    <th>ค่าตรวจ</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>';

        if ($items->isEmpty()) {
            $html .= '
            <tr>
                <td colspan="11" class="no-data">ไม่มีรายการ</td>
            </tr>';
        } else {
            $No = 1;
            foreach ($items as $item) {
                $registrationDate = ! empty($item->registration_date)
                ? Carbon::parse($item->registration_date)->addYears(543)->format('d/m/Y')
                : '-';

                $date = ! empty($item->date)
                ? Carbon::parse($item->date)->addYears(543)->format('d/m/Y')
                : '-';
                // $statusText = match ($item->status) {
                //     'finish' => 'สำเร็จ',
                //     'open' => 'เปิดบิล',
                //     'overdue' => 'ผ่อนจ่าย',
                //     'unpaid' => 'ค้างจ่าย',
                //     'cancel' => 'ยกเลิก',
                //     default => $item->status,
                // };
                $html .= '
                <tr>
                    <td style="text-align: center;">' . $No++ . '</td>
                    <td style="text-align: center;">' . ($item->no) . '</td>
                    <td style="text-align: center;">' . ($item->license_plate) . '</td>
                    <td style="text-align: center;">' . ($item->provinces ?? '-') . '</td>
                    <td style="text-align: center;">' . ($item->vehicle_inspection_types ?? '-') . '</td>
                    <td style="text-align: center;">' . ($item->name) . '</td>
                    <td style="text-align: center;">' . $registrationDate . '</td>
                    <td style="text-align: center;">' . $date . '</td>
                    <td style="text-align: center;">' . ($item->is_pass) . '</td>
                    <td style="text-align: center;">' . ($item->total_price) . '</td>
                    <td style="text-align: center;">' . ($item->result ?? '-') . '</td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';

        // Step 4: Write the HTML content to PDF and output
        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=รายการตรวจสภาพรถ.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function downloadExcelCheckCarReport(Request $request)
    {
        // Validate
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        // (ถ้าต้องใช้) ดึงพนักงานตามสาขา
        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        // Query เดิม (ปรับเป็น collection → array พร้อมฟอร์แมตวันที่ พ.ศ.)
        $items = Inspections::select(
                'inspections.no',
                'inspection_vehicles.license_plate',
                'inspection_vehicles.registration_date',
                'inspection_vehicles.provinces',
                'inspection_vehicles.vehicle_inspection_types',
                'customers.name',
                'inspections.date',
                DB::raw("CASE WHEN inspections.is_pass = 1 THEN 'ผ่าน' WHEN inspections.is_pass = 0 THEN 'ไม่ผ่าน' ELSE '-' END as is_pass"),
                DB::raw('SUM(CASE WHEN services.id = 1 THEN services.price ELSE 0 END) as total_price'),
                'inspections.result'
            )
            ->leftjoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions as vehicleserviceTransactions', 'inspection_vehicles.id', '=', 'vehicleserviceTransactions.inspection_vehicle_id')
            ->leftJoin('services', 'vehicleserviceTransactions.service_id', '=', 'services.id')
            ->where('vehicleserviceTransactions.service_id', 1)
            ->when($branchId, fn($q) => $q->where('inspections.branch_id', $branchId))
            ->whereIn('inspections.status', ['open', 'finish', 'overdue', 'unpaid'])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->groupBy(
                'inspections.no',
                'inspection_vehicles.license_plate',
                'inspection_vehicles.registration_date',
                'inspection_vehicles.provinces',
                'inspection_vehicles.vehicle_inspection_types',
                'customers.name',
                'inspections.date',
                'inspections.result',
                'inspections.is_pass'
            )
            ->get()
            ->map(function ($row) {
                // แปลงวันที่ → พ.ศ. (string) ตามรายงานเดิม
                $reg = !empty($row->registration_date)
                    ? Carbon::parse($row->registration_date)->addYears(543)->format('d/m/Y')
                    : '-';
                $date = !empty($row->date)
                    ? Carbon::parse($row->date)->addYears(543)->format('d/m/Y')
                    : '-';

                return [
                    'no'                        => $row->no,
                    'license_plate'             => $row->license_plate,
                    'provinces'                 => $row->provinces,
                    'vehicle_inspection_types'  => $row->vehicle_inspection_types,
                    'name'                      => $row->name,
                    'registration_date'         => $reg,
                    'date'                      => $date,
                    'is_pass'                   => $row->is_pass,
                    'total_price'               => (float) $row->total_price,
                    'result'                    => $row->result,
                ];
            })
            ->toArray();

        try {
            $result = new CarCheckExport($items, $startDate, $endDate);
            return Excel::download($result, 'รายการตรวจสภาพรถ.xlsx');
        } catch (\Exception $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelcar_check(request $request)
    {
        // Handle input dates
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        // Step 1: Get employee IDs from the given branch
        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        // Step 2: Fetch inspections associated with those employees
        $items = Inspections::select(
            'inspections.no',
            'vehicles.license_plate',
            'vehicles.registration_date',
            'provinces.name_th_prefix as province_name',
            'vehicleInspectionTypes.name as inspection_type_name',
            'customers.name',
            'inspections.date',
            DB::raw("CASE WHEN inspections.is_pass = 1 THEN 'ผ่าน' WHEN inspections.is_pass = 0 THEN 'ไม่ผ่าน' ELSE '-' END as is_pass"),
            DB::raw('SUM(CASE WHEN services.id = 1 THEN services.price ELSE 0 END) as total_price'),
            'inspections.result'
        )
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('service_transactions as serviceTransactions', 'inspections.id', '=', 'serviceTransactions.inspections_id')
            ->leftJoin('services', 'serviceTransactions.service_id', '=', 'services.id')
            ->leftJoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->leftJoin('vehicle_inspection_types as vehicleInspectionTypes', 'vehicles.vehicle_inspection_types_id', '=', 'vehicleInspectionTypes.id')
            ->when($employeeIds->isNotEmpty(), function ($query) use ($employeeIds) {
                $query->whereIn('inspections.employee_id', $employeeIds);
            })
            ->whereHas('serviceTransactions', function ($query) {
                $query->where('service_id', 1);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->groupBy(
                'inspections.no',
                'vehicles.license_plate',
                'vehicles.registration_date',
                'provinces.name_th_prefix',
                'vehicleInspectionTypes.name',
                'customers.name',
                'inspections.date',
                'inspections.result',
                'inspections.is_pass'
            )
            ->get()->toArray();

        try {
            $result = new CarCheckExport($items, $startDate, $endDate);
            return Excel::download($result, 'Carcheck.xlsx');
        } catch (\Exception $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelcar_tax(request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        // Step 1: Get employees in the specified branch
        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        // Step 2: Query inspections related to those employees
        $query = Inspections::select(
            'inspections.no',
            'inspections.date',
            'vehicles.license_plate',
            'inspections.status',
            'customers.name AS customer_name',
            'provinces.name_th_prefix AS province_name',
            'vehicles.notificate_tax',
            'inspections.type_document',
            'vehicles.registration_date',
            'vehicle_inspection_types.name AS inspection_type_name'
        )
            ->leftJoin('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('vehicle_inspection_types', 'vehicles.vehicle_inspection_types_id', '=', 'vehicle_inspection_types.id')
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->when($employeeIds->isNotEmpty(), function ($query) use ($employeeIds) {
                return $query->whereIn('inspections.employee_id', $employeeIds);
            })
            ->whereHas('serviceTransactions', function ($query) {
                $query->where('service_id', 3);
            });

        // Adjust branch filtering according to your schema
        if ($branchId) {
            // Assuming employees have a relation to inspections
            $query->whereHas('employees', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });
        }

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        $inspections = $query->get()->toarray();

        try {
            $result = new CarTaxExport($inspections);
            return Excel::download($result, 'Cartaxdata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelcommission_employee(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $branchId = trim($request->input('branch_id', null));

        $query = Employees::query()
            ->with([
                'inspections' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('date', [$startDate, $endDate]);
                    }
                    $query->with('serviceTransactions.service');
                },
            ]);

        if (! is_null($branchId) && $branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        $employees = $query->get();

        $serviceNames = [
            1  => 'ตรวจสภาพ',
            2  => 'พ.ร.บ.',
            3  => 'ภาษีประจำปี',
            4  => 'ค่าปรับภาษี',
            5  => 'บริการต่อภาษี',
            6  => 'ค่าบริการออกพ.ร.บ.',
            7  => 'ประกัน',
            8  => 'ขนส่ง',
            9  => 'ตรวจแก๊ส LPG',
            10 => 'ตรวจแก๊ส NGV ',
            11 => 'หนังสือรับรองวิศวกร',
            12 => 'อื่นๆ',
            13 => 'EMS',
        ];

        $serviceCounts = [];
        foreach ($employees as $employee) {
            $employeeName                 = $employee->first_name . ' ' . $employee->last_name;
            $serviceCounts[$employeeName] = $employee->inspections->flatMap(function ($inspection) {
                return $inspection->serviceTransactions->map(function ($transaction) {
                    return $transaction->service->id;
                });
            })->countBy();
        }

        try {
            $result = new CommissionEmployeeExport($employees, $serviceCounts, $serviceNames);
            return Excel::download($result, 'CommissionEmployeedata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExceldaily_report(Request $request)
    {
        $check_date = $request->input('check_date') ? Carbon::parse($request->input('check_date')) : null;
        $validator  = Validator::make($request->all(), [
            'check_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $branchId = $request->input('branch_id', null);

        $employeeIds = [];
        if ($branchId) {
            $employeeIds = Employees::where('branch_id', $branchId)->pluck('id')->toArray();
        }

        $selectColumns = [
            'inspections.no AS inspection_no',
            'vehicles.license_plate',
            'customers.name AS customer_name',
            'inspections.discount',
            'inspections.total',
            'service_transactions.service_price AS service_price_tran',
            'services.name AS service_name',
            'services.price AS service_price',
            'services.id AS service_id',
        ];

        $query = Inspections::select($selectColumns)
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('customers', 'customers.id', '=', 'vehicles.customer_id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->leftJoin('services', 'services.id', '=', 'service_transactions.service_id');

        if ($check_date) {
            $query->whereDate('inspections.date', $check_date);
        }

        if (! empty($employeeIds)) {
            $query->whereIn('inspections.employee_id', $employeeIds);
        }

        $items = $query->get();

        $groupedItems = $items->groupBy('inspection_no')->map(function ($group) {
            $firstItem           = $group->first();
            $serviceTransactions = $group->map(function ($item) {
                return [
                    'service_id'         => $item->service_id,
                    'service_name'       => $item->service_name,
                    'service_price'      => $item->service_price,
                    'service_price_tran' => $item->service_price_tran,
                ];
            });

            $aggregatedServices = $serviceTransactions->groupBy('service_name')->map(function ($group) {
                return [
                    'service_name'       => $group->first()['service_name'],
                    'service_price'      => $group->sum('service_price'),
                    'service_price_tran' => $group->sum('service_price_tran'),
                ];
            });

            $specifiedServices = $aggregatedServices->only(['ตรวจสภาพ', 'พ.ร.บ.', 'ภาษีประจำปี', 'ประกัน']);
            $otherServices     = $aggregatedServices->filter(function ($item) {
                return ! in_array($item['service_name'], ['ตรวจสภาพ', 'พ.ร.บ.', 'ภาษีประจำปี', 'ประกัน']);
            });

            if ($otherServices->isNotEmpty()) {
                $specifiedServices->put('Others', [
                    'service_name'       => 'Others',
                    'service_price'      => $otherServices->sum('service_price'),
                    'service_price_tran' => $otherServices->sum('service_price_tran'),
                ]);
            }

            return [
                'inspection_details'   => [
                    'inspection_no' => $firstItem->inspection_no,
                    'license_plate' => $firstItem->license_plate,
                    'customer_name' => $firstItem->customer_name,
                    'discount'      => $firstItem->discount,
                    'total'         => $firstItem->total,
                ],
                'service_transactions' => $specifiedServices,
            ];
        })->toArray();

        try {
            $result = new DailyReportExport($groupedItems);
            return Excel::download($result, 'DailyReportdata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelexpense(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        $query = Cost::select('date', 'name', 'price', 'detail');
        $this->applyCostBranchFilter($query, $branchId);
        $query->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });

        $items = $query->get()->toArray();

        try {
            $result = new ExpenseExport($items);
            return Excel::download($result, 'Expensedata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelexpensereport(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $branchId = trim($request->input('branch_id', null));

        $query = Cost::select('date', 'name', 'price')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });

        $items = $query->get()->toArray();

        try {
            $result = new ExpenseReportExport($items);
            return Excel::download($result, 'Expensereportdata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelgas_check(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        $employeeIds = [];
        if (! is_null($branchId)) {
            $employeeIds = Employees::where('branch_id', $branchId)->pluck('id')->toArray();
        }

        $query = Inspections::query()
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->join('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->join('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->join('services', 'service_transactions.service_id', '=', 'services.id')
            ->select(
                'inspections.no',
                'vehicles.license_plate',
                'provinces.name_th_prefix',
                'customers.name',
                'vehicles.registration_date',
                'inspections.date',
                'services.id as service_id',
                'inspections.service_or',
                'inspections.result',
                'inspections.status',
                'service_transactions.service_price'
            )
            ->whereIn('service_transactions.service_id', [9, 10]);

        $query->whereBetween('inspections.date', [$startDate, $endDate]);

        if (! empty($employeeIds)) {
            $query->whereIn('inspections.employee_id', $employeeIds);
        }

        $results = $query->get();

        $uniqueResults = $results->unique(function ($item) {
            return $item->no . $item->license_plate . $item->date;
        })->toarray();

        try {
            $result = new GasCheckExport($uniqueResults);
            return Excel::download($result, 'Gascheckdata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelgas_check_list(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $query     = Inspections::query()
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->join('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->join('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->join('services', 'service_transactions.service_id', '=', 'services.id')
            ->select(
                'inspections.no',
                'vehicles.license_plate',
                'provinces.name_th_prefix',
                'customers.name',
                'vehicles.registration_date',
                'inspections.date',
                'services.id as service_id',
                'inspections.service_or',
                'inspections.result',
                'inspections.status',
                'service_transactions.service_price'

            )
            ->whereIn('service_transactions.service_id', [9, 10]);

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! is_null($branchId)) {
            $query->where('inspections.branch_id', $branchId);
        }

        $Item = $query->get()->toArray();
        try {
            $result = new GasCheckExport($Item);
            return Excel::download($result, 'Gascheck_list_data.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelgas_reportExport(Request $request)
    {
        $branchId  = trim($request->input('branch_id', null));
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        $query = DB::table('inspections')
            ->join('customers', 'customers.id', '=', 'inspections.customer_id')
            ->join('employees', 'employees.id', '=', 'inspections.employee_id')
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->leftJoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->leftJoin('vehicle_gases', 'vehicle_gases.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->select(
                'employees.code',
                'customers.no',
                'customers.name',
                'inspections.service_or',
                'inspections.date as day',
                'vehicles.license_plate',
                'provinces.name_th_prefix as province_name',
                'vehicle_brands.name as brand_name',
                'inspections.install_no',
                'inspections.CHA_no'
            )
            ->whereIn('service_transactions.service_id', [2, 8])
            ->where('inspections.branch_id',$branchId) //bird
            ->when($employeeIds->isNotEmpty(), function ($query) use ($employeeIds) {
                return $query->whereIn('inspections.employee_id', $employeeIds);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->get()->toarray();

        try {
            $result = new Gasreport($query);
            return Excel::download($result, 'รายงานตรวจแก๊ซ.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExportExcelgas_report(Request $request)
    {
        $branchId  = trim($request->input('branch_id', null));
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $baseQuery = DB::table('check_gases')
            ->selectRaw('
            DISTINCT check_gases.id,
            check_gases.customer_no,
            check_gases.customer_name,
            check_gases.date,
            check_gases.time,
            check_gases.engineer_no,
            inspection_vehicles.license_plate,
            inspection_vehicles.provinces,
            check_gases.no_gas,
            check_gases.brand_gas,
            check_gases.install_no,
            check_gases.service_or,
            vehicle_brands.name as vehicle_brand,
            inspections.branch_id,
            GROUP_CONCAT(DISTINCT cha_nos.cha_no) as cha_no_group
        ')
            ->leftJoin('inspection_vehicles', 'check_gases.inspection_vehicle_id', '=', 'inspection_vehicles.id')
            ->leftJoin('inspections', 'inspection_vehicles.inspection_id', '=', 'inspections.id') // เพิ่ม join
            ->leftJoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id') // Corrected here
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->leftJoin('cha_nos', 'check_gases.id', '=', 'cha_nos.check_gas_id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->where('inspections.branch_id',$branchId)
            ->whereNull('check_gases.deleted_at')
            ->whereBetween('check_gases.date', [$startDate, $endDate])
            ->groupBy(
                'check_gases.id',
                'check_gases.customer_no',
                'check_gases.customer_name',
                'check_gases.date',
                'check_gases.time',
                'check_gases.engineer_no',
                'inspection_vehicles.license_plate',
                'inspection_vehicles.provinces',
                'check_gases.no_gas',
                'check_gases.brand_gas',
                'check_gases.install_no',
                'check_gases.service_or',
                'vehicle_brands.name',
                'inspections.branch_id'
            )
            ->get();

        // (ออปชัน) ถ้ากังวลว่า service_id อาจไม่ใช่ [2,8] ให้ลอง log สักแถวดู
        // if ($rows->isEmpty()) { \Log::info('gas-report empty', request()->all()); }

        // ป้องกัน toArray ตัวพิมพ์เล็ก (ต้องใช้ T ใหญ่)
        $rows = $baseQuery->toArray();

        if (empty($rows)) {
            // ส่งไฟล์หัวตารางเปล่า หรือจบด้วยข้อความก็ได้
            // return $this->returnBadRequest([], 'ไม่พบข้อมูลตามเงื่อนไข');
        }

        return Excel::download(
            new GasReportExport(collect($rows), $startDate, $endDate),
            'รายงานตรวจแก๊ซ.xlsx'
        );
    }

    public function downloadExcelInsuranceReportExport(request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = trim($request->input('branch_id', null));

        $data = Inspections::select([
            'inspections.*',
            'customers.*',
            'vehicles.*',
            'prb_renewals.policy_number',
            'prb_renewals.date_start',
            'prb_renewals.date_end',
            'prb_renewals.amount_paid',
            'prb_renewals.remark',
            'prb_renewals.stamp',
            'prb_renewals.tax',
            'service_transactions.*',
            'services.price as service_price',
            'vehicle_inspection_types.name as vehicle_type_name',
        ])
            ->join('customers', 'customers.id', '=', 'inspections.customer_id')
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->leftJoin('vehicle_inspection_types', 'vehicle_inspection_types.id', '=', 'vehicles.vehicle_inspection_types_id')
            ->join('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->join('services', 'services.id', '=', 'service_transactions.service_id')
            ->leftJoin('prb_renewals', 'prb_renewals.inspection_id', '=', 'inspections.id')
            ->where('service_transactions.service_id', 2)
            ->where('service_transactions.status', 1)
            ->get()->toArray();

        try {
            $result = new InsuranceReportExport($data);
            return Excel::download($result, 'Insurancereportdata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelInsuranceExport(request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = trim($request->input('branch_id', null));
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $data = Inspections::select([
            'inspections.*',
            'customers.*',
            'insurances_renewals.policy_number',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.amount_paid',
            'insurances_renewals.remark',
            'insurance_names.name as insurance_name',
            'vehicles.*',
            'service_transactions.*',
            'services.price as service_price',
        ])
            ->join('customers', 'customers.id', '=', 'inspections.customer_id')
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->join('services', 'services.id', '=', 'service_transactions.service_id')
            ->join('insurances_renewals', 'insurances_renewals.inspection_id', '=', 'inspections.id')
            ->join('insurance_names', 'insurance_names.id', '=', 'insurances_renewals.insurance_names_id')
            ->where('service_transactions.service_id', 6)
            ->where('service_transactions.status', 1)
            ->get()->toArray();

        try {
            $result = new InsuranceExport($data);
            return Excel::download($result, 'Insurancedata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }
    public function downloadExcelDay(request $request)
    {
        $branchId = $request->input('branch_id', null);
        $startRaw = $request->input('start_date') ?: $request->input('date');
        $endRaw   = $request->input('end_date') ?: $startRaw;

        if (! $startRaw) {
            return $this->returnError('Date parameter is missing');
        }

        $startDate = Carbon::parse($startRaw)->toDateString();
        $endDate   = Carbon::parse($endRaw)->toDateString();

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        $query = Inspections::whereBetween('inspections.date', [$startDate, $endDate]);

        if ($employeeIds->isNotEmpty()) {
            $query->whereIn('inspections.employee_id', $employeeIds);
        }

        $items = $query->with(['Installments'])->get()->toArray();

        try {
            $result = new DayExport($items);
            return Excel::download($result, 'Day.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelmonth(Request $request)
    {
        $branchId   = $request->input('branch_id', null);
        $start_date = $request->input('start_date');
        $end_date   = $request->input('end_date');
        $validator  = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $start_date = Carbon::parse($start_date);
        $end_date   = Carbon::parse($end_date);

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        $query = Inspections::whereBetween('date', [$start_date, $end_date])->with('Installments');

        if ($employeeIds->isNotEmpty()) {
            $query->whereIn('employee_id', $employeeIds);
        }

        $items = $query->get();

        $costQuery = Cost::whereBetween('date', [$start_date, $end_date]);

        if (! empty($branchId)) {
            $costQuery->where('branch_id', $branchId);
        }

        $cost_cal = $costQuery->get();

        try {
            $result = new MonthExport($items, $cost_cal);
            return Excel::download($result, 'MonthExport.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function downloadExcelProfitReportExport(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        $employeeIds = Employees::where('branch_id', $branchId)->pluck('id');

        $servicePrices = Services::pluck('price', 'id')->toArray();

        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'vehicles.license_plate',
            'customers.name',
        ];

        $query = Inspections::select($columns)
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('customers', 'customers.id', '=', 'vehicles.customer_id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->leftJoin('services', 'services.id', '=', 'service_transactions.service_id')
            ->groupBy('inspections.id', 'inspections.no', 'inspections.date', 'vehicles.license_plate', 'customers.name')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->when($branchId && $employeeIds->isNotEmpty(), function ($query) use ($employeeIds) {
                $query->whereIn('inspections.employee_id', $employeeIds);
            });

        $items = $query->get();

        try {
            $result = new ProfitReportExport($items, $servicePrices); // Pass servicePrices if needed in export
            return Excel::download($result, 'Profitdata.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest('Error downloading file', $e->getMessage());
        }
    }

    public function billserviceExportPDF($id)
    {

        // ดึงข้อมูลการตรวจสอบ พร้อมข้อมูลที่เกี่ยวข้อง
        $data = Inspections::with([
            'serviceTransactions.service',
            'Vehicles.Customers',
            'Vehicles.VehicleInspectionTypes',
            'InspectionVehicles.vehicle.Customers',
            'InspectionVehicles.vehicle.VehicleInspectionTypes',
            'InspectionVehicles.vehicleServiceTransaction.service',
            'Employees.Branches',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnBadRequest('Data Not Found', 404);
        }

        // ดึงข้อมูลลูกค้าและรถยนต์
        $vehicle       = $data->InspectionVehicles[0]->vehicle ?? null;
        $customer      = $vehicle->Customers ?? null;
        $customerName  = $customer->name ?? null;
        $vehiclePlate  = $vehicle->license_plate ?? null;
        $phoneNumber   = $customer->phone_number1 ?? null;
        $vehicleType   = $vehicle->VehicleInspectionTypes->name ?? null;
        $branches_name = $data->employees->Branches->business_name;

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $round       = 0;

        foreach ($data->InspectionVehicles[0]->vehicleServiceTransaction as $transaction) {

            $bill_serviceName  = $transaction->service_name ?? $transaction->service->name ?? null;
            $bill_servicePrice = $transaction->service_price ?? null;
            $round += 1;
            $totalAmount += $bill_servicePrice;

            $serviceRows .= '
                <tr>
                    <td>' . $bill_serviceName . '</td>
                    <td class="amount">' . number_format($bill_servicePrice, 2) . '</td>
                </tr>';
        }
        for ($i = $round; $i < 7; $i++) {
            $serviceRows .= '
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>';
        }
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
                                              // 'format' => 'A4',
                                              // 'default_font_size' => 14,
            'format'            => [80, 190], // Width x Height in mm
            'default_font_size' => 10,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 0,
            'margin_footer'     => 0,
        ]);

        $mpdf->SetTitle('Bill Service');
        $mpdf->AddPage();

        $html = '
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: "th-sarabun", Arial, sans-serif;
                    font-size: 10pt;
                    margin: 0;
                }
                .receipt {
                    width: 100%;
                }
                .header {
                    text-align: center;
                    margin-bottom: 5px;
                }
                .header h1, .header h2, .header h3 {
                    margin: 2px 0;
                }
                .header h1 {
                    font-size: 12pt;
                    font-weight: bold;
                }
                .header h2 {
                    font-size: 10pt;
                    font-weight: bold;
                }
                .header h3 {
                    font-size: 9pt;
                }
                table {
                    width: 100%;
                    margin-top: 5px;
                    border-collapse: collapse;
                }
                th, td {
                    padding: 2px;
                    text-align: left;
                }
                .amount {
                    text-align: right;
                }
                .total {
                    font-size: 12pt;
                    font-weight: bold;
                    text-align: right;
                }
                .footer {
                    text-align: center;
                    margin-top: 5px;
                }
                .footer p {
                    margin: 2px 0;
                    font-size: 9pt;
                }
                hr {
                    border: none;
                    border-top: 1px dashed #000;
                    margin: 5px 0;
                }
            </style>
        </head>
        <body>
        <div class="receipt">
            <div class="header">
                <h1>' . $branches_name . '</h1>';

        if ($customerName) {
            $html .= '
            <h3>ลูกค้า: ' . $customerName . '</h3>
            <h3>โทร: ' . $phoneNumber . '</h3>
            ';
        }

        $html .= '
                <h2>ทะเบียน: ' . $vehiclePlate . '</h2>
                <h3>ประเภท: ' . $vehicleType . '</h3>
                </div>

                    <hr>

                    <table>
                        ' . $serviceRows . '
                    </table>

                    <hr>

                    <table>
                        <tr>
                            <td><strong>ยอดรวม</strong></td>
                            <td class="amount"><strong>' . number_format($totalAmount, 2) . '</strong></td>
                        </tr>
                        <tr>
                            <td>ส่วนลด</td>
                            <td class="amount">' . number_format($data->discount, 2) . '</td>
                        </tr>
                        <tr>
                            <td><strong>ยอดสุทธิ</strong></td>
                            <td class="amount"><strong>' . number_format($totalAmount - $data->discount, 2) . '</strong></td>
                        </tr>
                    </table>

                    <hr>

                    <div class="footer">
                        <p>พนักงาน: ____________________</p>
                        <p>(ลายเซ็น)</p>
                        <p>ขอบคุณที่ใช้บริการ</p>
                    </div>
                </div>
            </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('receipt.pdf', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function billquotation($id)
    {

        $data = Inspections::with([
            'Branches',
            'Vehicles.VehicleBrands',
            'Employees.Branches',
            'Employees.Branches.Provinces',
            'Employees.Branches.Districts',
            'Employees.Branches.SubDistricts',
            'InspectionVehicles.vehicle.VehicleInspectionTypes',
            // 'InspectionVehicles.vehicle.Customers.Customer_Addresses.Provinces',
            // 'InspectionVehicles.vehicle.Customers.Customer_Addresses.Districts',
            // 'InspectionVehicles.vehicle.Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.Provinces',
            'Customers.Customer_Addresses.Districts',
            'Customers.Customer_Addresses.SubDistricts',
            'InspectionVehicles.vehicleServiceTransaction.service',
        ])->where('id', $id)->first();
        // return $data;

        if (! $data) {
            return $this->returnError('Data Not Found');
        }

        // Pull data branch from table employee
        $employee_branchAddress  = $data->Employees->Branches->address ?? null;
        $employee_branchProvince = $data->Employees->Branches->Provinces->name_th_prefix ?? null;
        $employee_branchDistrict = $data->Employees->Branches->Districts->name_th_prefix ?? null;
        $employee_branchSubdrict = $data->Employees->Branches->SubDistricts->name_th_prefix ?? null;
        $time_open_close         = $data->Employees->Branches->open_close_time ?? null;
        $open_date               = $data->Employees->Branches->open_date ?? null;
        $phone                   = $data->Employees->Branches->phone ?? null;

        // ตรวจสอบและจัดการข้อมูลที่เป็น null
        $vehicle  = $data->Vehicles ?? null;
        $order_no = $data->no ?? null;
        // $customer = $vehicle ? $vehicle->Customers : null;
        $customer     = $data ? $data->Customers : null;
        $customerName = $customer->name ?? null;
        $vehiclePlate = $vehicle ? $vehicle->license_plate : null;
        $phoneNumber  = $customer ? $customer->phone_number1 : null;
        $vehicleType  = $data->Vehicles->VehicleInspectionTypes ? $data->Vehicles->VehicleInspectionTypes->name : null;
        $vehicleBrand = $vehicle && $vehicle->VehicleBrands ? $vehicle->VehicleBrands->name : null;

        // Pull Data Customer Address
        $address         = $data->Vehicles->Customers->Customer_Addresses[0]->address ?? null;
        $provinceName    = $data->Vehicles->Customers->Customer_Addresses[0]->provinces->name_th_prefix ?? null;
        $districtName    = $data->Vehicles->Customers->Customer_Addresses[0]->districts->name_th_prefix ?? null;
        $subdistrictName = $data->Vehicles->Customers->Customer_Addresses[0]->SubDistricts->name_th_prefix ?? null;
        $postalcode      = $data->Vehicles->Customers->Customer_Addresses[0]->zip_code ?? null;

        //Formatted Date
        $date          = $data->date;
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $year          = substr($formattedDate, -4);
        $formattedDate = str_replace($year, $year + 543, $formattedDate);
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $id_gen      = 0;
        $round       = 0;
        foreach ($data->serviceTransactions as $transaction) {
            if ($transaction->status == 1) {
                $serviceName  = $transaction->service_name ?? null;
                $servicePrice = $transaction->service_price ?? null;
                $totalAmount += $servicePrice;
                $id_gen += 1;
                $round += 1;
                $serviceRows .= '
                    <tr>
                        <td>' . $id_gen . '</td>
                        <td style="text-align: left">' . $serviceName . '</td>
                        <td style="text-align: right">' . number_format($servicePrice, 2) . '</td>
                    </tr>';
            }
        }
        for ($i = $round; $i < 7; $i++) {
            $serviceRows .= '
            <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
            </tr>';
        }
        // return $data;
        $branche_image = $data->Employees->Branches->logo_url ?? null;
        $branche_name  = $data->Employees->Branches->business_name ?? null;

        $total    = floor($totalAmount);
        $totalcal = $totalAmount - $total;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 20,
            'margin_right'      => 20,
            'margin_top'        => 20,
            'margin_bottom'     => 20,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบเสร็จ');
        $mpdf->AddPage();

        $html = '
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <title>ใบวางบิล/ใบแจ้งหนี้</title>
            <style>
                body {
                    font-family: "th-sarabun", Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #fff;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    padding: 20px;
                }
                .header-left {
                    text-align: left;
                }
                .header-left1 {
                    text-align: left;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 200px;
                    left: 470px;
                }
                .header-left h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .header-left p {
                    margin: 2px 0;
                    font-size: 18px;
                }
                .header-right {
                    text-align: right;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 77px;
                    left: 585px;
                }
                .header-right2 {
                    text-align: right;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 192px;
                    left: 470px;
                }
                .content {
                    padding: 20px;
                    display: flex;
                    justify-content: space-between;
                }
                .content-left {
                    text-align: left;
                    font-size: 18px;
                }
                .content-right {
                    text-align: right;
                    font-size: 18px;
                }
                .table-container {
                    margin-top: 5px;
                    width: 100%;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 4px;
                    text-align: center;
                    font-size: 18px;ก
                }
                th {
                    background-color: #f2f2f2;
                }
                .footer {
                    margin-top: 5px;
                    text-align: left;
                    font-size: 18px;
                }
                .footer table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .footer td {
                    border: none;
                    text-align: left;
                    padding: 8px;
                    font-size: 18px;
                }
                .signature-section {
                    margin-top: 50px;
                    display: flex;
                    justify-content: space-between;
                    font-size: 18px;
                }
                .signature-section1 {
                    width: 100%;
                    text-align: center;
                }
                .signature-section2 {
                    width: 100%;
                    text-align: center;
                }
                .container {
                    display: flex;
                    align-items: flex-start;
                    text-align: left;
                    font-size: 20px;
                    position: absolute;
                    width: 50px;
                    top: 130px;
                    left: 80px;
                }
                .container img {
                    margin-right: 20px; /* Adjust spacing as needed */
                    width: 100px; /* Adjust image size as needed */
                }
                .text-content {
                    max-width: 300px; /* Adjust as needed */
                }
            </style>
        </head>
        <body>
            <table style="width: 100%;">
                    <tr>
                        <td style="width: 5%; border: none; text-align: left;">
                            <img src="' . $branche_image . '" alt="FIE" align="left" style="width: 100px">
                        </td>
                        <td style="width: 60%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                            <h2>' . $branche_name . '</h2>
                            <p>' . $employee_branchAddress . ' ' . $employee_branchSubdrict . ' ' . $employee_branchDistrict . ' ' . $employee_branchProvince . '</p>
                            <p>' . $time_open_close . ' น. (' . $open_date . ')</p>
                            <p>' . $phone . '</p>
                        </td>
                        <td style="width: 40%; vertical-align: text-top; border: none; text-size: 20px; text-align: right;">
                            <p style="font-size: 20px"><strong>ใบวางบิล/ใบแจ้งหนี้</strong></p>
                            <p>ต้นฉบับ</p>
                        </td>
                    </tr>
            </table>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 70%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                        <p>ลูกค้า: ' . $customerName . '</p>
                        <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . '</p>
                        <p>โทร.: ' . $phoneNumber . '</p>
                        <p>ทะเบียน: ' . $vehiclePlate . ' [' . $vehicleBrand . ']</p>
                    </td>
                    <td style="width: 30%; border: none; text-align: left; vertical-align: text-top;">
                        <p>เลขที่: ' . $order_no . '</p>
                        <p>วันที่: ' . $formattedDate . '</p>
                        <p>ประเภท: ' . $vehicleType . ' </p>
                    </td>
                </tr>
            </table>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%;">ลำดับ</th>
                            <th style="width: 70%;">รายการ</th>
                            <th style="width: 20%;">จำนวนเงิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                        ' . $serviceRows . '
                        </tr>
                    </tbody>
                </table>
            <div class="footer">
                <table>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ยอดรวม: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($totalAmount, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ส่วนลดรวม: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($totalcal, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ยอดสุทธิ: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($total, 2) . '</td>
                    </tr>
                </table>
            </div>
            </div>
            <div style="
            position: fixed; bottom: 0; width: 100%; padding: 0 20px; margin-top: 20px; overflow: hidden;">
                <div style="float: left; width: 45%;  text-align: center;">
                    <p>จัดเตรียมโดย</p>
                    <p>..................................</p>
                    <p>(..................................)</p>
                    <p>......../......../........</p>
                </div>
                <div style="float: right; width: 45%;  text-align: center;">
                    <p>ยอมรับใบเสนอราคา</p>
                    <p>..................................</p>
                    <p>(..................................)</p>
                    <p>......../......../........</p>
                </div>
            </div>
        </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('receipt.pdf', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function receipt(Request $request, $id)
    {
        $installments_id = $request->installments_id;
        if (! $installments_id) {
            return $this->returnErrordata('installments_id Not Found', 404);
        }
        $data = Inspections::with([
            'Vehicles.VehicleBrands',
            'Vehicles.VehicleInspectionTypes',
            'Vehicles.Customers.Customer_Addresses.Provinces',
            'Vehicles.Customers.Customer_Addresses.Districts',
            'Vehicles.Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.SubDistricts',
            'Employees.Branches.Provinces',
            'Employees.Branches.Districts',
            'Employees.Branches.SubDistricts',
            'serviceTransactions.service',
            'Installments',
        ])->where('id', $id)->first();
        // return $data;
        if (! $data) {
            return $this->returnErrordata('Data Not Found', 404);
        }
        // return $data->Customers;
        // ตรวจสอบและจัดการข้อมูลที่เป็น null
        $vehicle      = $data->Vehicles ?? null;
        $order_no     = $data->no ?? null;
        $customer     = $data ? $data->Customers : null;
        $customerName = $customer ? $customer->name : null;
        $vehiclePlate = $vehicle ? $vehicle->license_plate : null;
        $phoneNumber  = $customer ? $customer->phone_number1 : null;
        $vehicleType  = $vehicle ? $vehicle->VehicleInspectionTypes->name : null;
        $vehicleBrand = $vehicle && $vehicle->VehicleBrands ? $vehicle->VehicleBrands->name : null;

        // ดึงข้อมูลที่อยู่และตรวจสอบค่าที่อาจไม่มี
        $address         = $customer->Customer_Addresses[0]->address ?? null;
        $provinceName    = $customer->Customer_Addresses[0]->provinces->name_th_prefix ?? null;
        $districtName    = $customer->Customer_Addresses[0]->districts->name_th_prefix ?? null;
        $subdistrictName = $customer->Customer_Addresses[0]->SubDistricts->name_th_prefix ?? null;
        $postalcode      = $customer->Customer_Addresses[0]->zip_code ?? null;

        // Pull data branch
        $branche_address          = $data->Employees->Branches->address ?? null;
        $branche_province_name    = $data->Employees->Branches->provinces->name_th_prefix ?? null;
        $branche_district_name    = $data->Employees->Branches->districts->name_th_prefix ?? null;
        $branche_subdistrict_name = $data->Employees->Branches->SubDistricts->name_th_prefix ?? null;
        $branche_postal_code      = $data->Employees->Branches->postal_code ?? null;
        $branche_phone            = $data->Employees->Branches->phone ?? null;
        $branche_open_date_time   = $data->Employees->Branches->open_close_time ?? null;
        $branche_open_date        = $data->Employees->Branches->open_date ?? null;
        $branche_logo             = $data->Employees->Branches->business_name ?? null;
        $branche_logo1            = $data->Employees->Branches->logo_url ?? null;
        // dd($branche_logo1);

        if ($branche_logo1 != null) {
            $branche_image = '<td style="width: 8%; border: none; text-align: left; vertical-align: middle;" colspan="2"><img src="' . $branche_logo1 . '" alt="FIE" align="left" style="width: 90px;"></td>';
        } else {
            $branche_image = 'Not Found';
        }
        // $branche_image = '<td style="width: 8%; border: none; text-align: left; vertical-align: middle;" colspan="2"><img src="https://127.0.0.1:8000/storage/Branches/branchs_FIE_fds_.png" alt="FIE" align="left" style="width: 90px;"></td>';

        // Formatted Date
        $date          = $data->date;
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $yeardate      = substr($formattedDate, -4);
        //$formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);
        $formattedDate = str_replace($yeardate, $yeardate + 543, $formattedDate);

        // Format appointment date
        $appointment       = $data->appointment;
        $formatappointment = Carbon::parse($appointment)->locale('th')->translatedFormat('j M y');
        $year              = substr($formatappointment, -2);
        $formatappointment = str_replace($year, $year + 43, $formatappointment);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $id_gen      = 0;
        $round       = 0;
        // ตรวจสอบว่า $data มี property ServiceTransactions และเป็น object
        if (isset($data->serviceTransactions) && is_iterable($data->serviceTransactions)) {
            $customerType       = $customer->type ?? null; // ดึงข้อมูลประเภทลูกค้า
            $excludedServiceIds = [8, 9, 10, 11, 12];      // รายการ id ของบริการที่ต้องไม่แสดงสำหรับ vendor

            foreach ($data->serviceTransactions as $transaction) {
                // เงื่อนไขกรณีที่เป็น personal แสดง service ทั้งหมด
                if ($customerType === 'personal') {
                    if ($transaction->status === 1) {
                        $serviceName  = $transaction->service_name ?? null;
                        $servicePrice = $transaction->service_price ?? null;
                        $totalAmount += $servicePrice;
                        $id_gen++;
                        $round += 1;
                        $serviceRows .= '
                            <tr>
                                <td style="border: 1px solid black; border-collapse: collapse; text-align: center;">' . $id_gen . '</td>
                                <td colspan="4" style="text-align: left; border: 1px solid black; border-collapse: collapse;">' . $serviceName . '</td>
                                <td style="text-align: right; border: 1px solid black; border-collapse: collapse;">' . number_format($servicePrice, 2) . '</td>
                            </tr>';
                    }
                }
                // เงื่อนไขกรณีที่เป็น vendor แสดง service ยกเว้น id ใน excludedServiceIds
                elseif ($customerType === 'vendor' && ! in_array($transaction->service_id, $excludedServiceIds)) {
                    if ($transaction->status === 1) {
                        $serviceName  = $transaction->service_name ?? null;
                        $servicePrice = $transaction->service_price ?? null;
                        $totalAmount += $servicePrice;
                        $id_gen++;
                        $round += 1;
                        $serviceRows .= '
                            <tr>
                                <td style="border: 1px solid black; border-collapse: collapse; text-align: center;">' . $id_gen . '</td>
                                <td colspan="4" style="text-align: left; border: 1px solid black; border-collapse: collapse;">' . $serviceName . '</td>
                                <td style="text-align: right; border: 1px solid black; border-collapse: collapse;">' . number_format($servicePrice, 2) . '</td>
                            </tr>';
                    }
                }
            }
        }
        for ($i = $round; $i < 7; $i++) {
            $serviceRows .= '
            <tr>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td colspan="4" style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
            </tr>';
        }

        $status_paid = '';

        foreach ($data->installments as $installments) {
            // dd($installments);
            if ($installments['id'] == $installments_id) {

                $cash_input = $installments['paid_price'];
                // $change = abs($installments['after_price']);
                if ($installments['after_price'] > 0) {
                    $change = 0;
                } else {
                    $change = $installments['after_price'];
                }

                $statusMessages = [
                    'paid_cash' => 'เงินสด : ',
                    'paid_tran' => 'เงินโอน : ',
                    'credit'    => 'บัตรเครดิต : ',
                ];
                if (array_key_exists($installments['paid_type'], $statusMessages)) {
                    $status_paid = '<p>' . $statusMessages[$installments->paid_type] . number_format($data->total, 2) . '</p>';
                } else {
                    $status_paid = '<p>สถานะไม่รู้จัก</p>';
                }

                $total_paid_price = $installments['paid_price'];
                $change_bef       = $installments['before_price'];
                if ($installments['after_price'] < 0) {
                    $total_after_price = 0;
                } else {
                    $total_after_price = $installments['after_price'];
                }
            }
        }
        $totalServicePrice = 0;
        foreach ($data->serviceTransactions as $service_total) {
            if (isset($service_total->service_price) && $service_total->status === 1) {
                $totalServicePrice += $service_total->service_price;
            }
        }

        $totalAmount       = $totalServicePrice;
        $totaldiscount     = $data->discount;
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4-l',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 15,
            'margin_right'      => 15,
            'margin_top'        => 15,
            'margin_bottom'     => 15,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบสำคัญรับเงิน');
        $mpdf->AddPage();

        $html = '
        <body>
            <div style="width: 49%; float: left; border-right: 1px dashed black; padding-left: 15px;">

                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <h2>' . $branche_logo . '</h2>
                                    <p>' . $branche_address . ' ' . $branche_subdistrict_name . ' ' . $branche_district_name . ' ' . $branche_province_name . ' ' . $branche_postal_code . '</p>
                                    <p>' . $branche_open_date_time . ' น. (' . $branche_open_date . ')</p>
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 25px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>สำเนา</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <p>ลูกค้า: ' . $customerName . ' </p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' [' . $vehicleBrand . '] </p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $vehicleType . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    ' . $serviceRows . '
                                </tr>
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>1. เงินสด: ' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>_____________</p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p><strong>ส่วนลด</strong></p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totaldiscount, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>(กชมน รุ่งเรือง)</p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                       <p><strong>ยอดสุทธิ</strong></p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($change_bef, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>ชำระแล้ว</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($total_paid_price, 2);
        if (($total_paid_price - $change_bef) > 0) {
            $html .= ' ทอน ' . number_format($total_paid_price - $change_bef, 2) . '</p>';
        }
        $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
            </div>

            <div style="width: 49%; float: right;">
                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <h2>' . $branche_logo . '</h2>
                                    <p>' . $branche_address . ' ' . $branche_subdistrict_name . ' ' . $branche_district_name . ' ' . $branche_province_name . ' ' . $branche_postal_code . '</p>
                                    <p>' . $branche_open_date_time . ' น. (' . $branche_open_date . ')</p>
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 25px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>ต้นฉบับ</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <p>ลูกค้า: ' . $customerName . ' </p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' [' . $vehicleBrand . '] </p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $vehicleType . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    ' . $serviceRows . '
                                </tr>
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>1. เงินสด: ' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>_____________</p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p><strong>ส่วนลด</strong></p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totaldiscount, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>(กชมน รุ่งเรือง)</p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                       <p><strong>ยอดสุทธิ</strong></p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($change_bef, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>ชำระแล้ว</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($total_paid_price, 2);
        if (($total_paid_price - $change_bef) > 0) {
            $html .= ' ทอน ' . number_format($total_paid_price - $change_bef, 2) . '</p>';
        }
        $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
            </div>
        </body>
        </html>';
        // return $html;
        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function billtax_invioce($id)
    {
        $data = Inspections::with([
            'Vehicles.VehicleBrands',
            'Vehicles.Customers.Customer_Addresses.Provinces',
            'Vehicles.Customers.Customer_Addresses.Districts',
            'Vehicles.Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.Provinces',
            'Customers.Customer_Addresses.Districts',
            'Customers.Customer_Addresses.SubDistricts',
            'Employees.Branches.Provinces',
            'Employees.Branches.Districts',
            'Employees.Branches.SubDistricts',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnErrordata('Data Not Found', 404);
        }
        $customer = $data->Customers;

        if (! $customer) {
            return $this->returnErrordata('ต้องการข้อมูลลูกค้า', 404);
        }

        // ตรวจสอบและจัดการข้อมูลที่เป็น null
        $vehicle              = $data->Vehicles ?? null;
        $customerNo           = $customer ? $customer->no : null;
        $customer_name        = $customer ? $customer->name : null;
        $vehiclePlate         = $vehicle ? $vehicle->license_plate : null;
        $customer_phoneNumber = $customer ? $customer->phone_number1 : null;
        $customer_taxID       = $customer->tax_id ?? null;
        $inspection_no        = $data->no ?? null;

        // ดึงข้อมูลที่อยู่และตรวจสอบค่าที่อาจไม่มี
        $customerAddress          = $customer->Customer_Addresses[0] ?? null;
        $customer_address         = $customerAddress ? $customerAddress->address : null;
        $customer_provinceName    = $customerAddress && $customerAddress->provinces ? $customerAddress->provinces->name_th_prefix : null;
        $customer_districtName    = $customerAddress && $customerAddress->districts ? $customerAddress->districts->name_th_prefix : null;
        $customer_subdistrictName = $customerAddress && $customerAddress->SubDistricts ? $customerAddress->SubDistricts->name_th_prefix : null;
        $customer_postalcode      = $customerAddress ? $customerAddress->postal_code : null;

        // Pull data branche
        $branche                 = $data->Employees->Branches ?? null;
        $branche_name            = $branche->business_name ?? null;
        $branche_address         = $branche->address ?? null;
        $branche_provinceName    = $branche && $branche->provinces ? $branche->provinces->name_th_prefix : null;
        $branche_districtName    = $branche && $branche->districts ? $branche->districts->name_th_prefix : null;
        $branche_subdistrictName = $branche && $branche->SubDistricts ? $branche->SubDistricts->name_th_prefix : null;
        $branche_postalcode      = $branche ? $branche->postal_code : null;
        $branche_phone           = $branche ? $branche->phone : null;
        $branche_tax_id          = $branche->Tax_id ?? null;
        // dd($branche_tax_id);

        //Formatted Date
        $date          = $data->date;
        $formattedDate = Carbon::parse($date)->format('d/m/') . (Carbon::parse($date)->year + 543 - 2500);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $id_gen      = 0;
        $round       = 0;

        $checkbox_null  = '<img src="../image/checkbox.png" alt="checkbox" style="width: 12px;">';
        $checkbox_value = '<img src="../image/checkbox-true.png" alt="checkbox" style="width: 12px;">';

        if ($customer->is_headquarter == 1) {
            $call_checkbox = '<td style="padding-left: 10px;">เลขประจำตัวผู้เสียภาษี ' . $customer_taxID . '   ' . $checkbox_value . '<span>&nbsp;สำนักงานใหญ่</span></td>';
        } else {
            $call_checkbox = '<td style="padding-left: 10px;">เลขประจำตัวผู้เสียภาษี ' . $customer_taxID . '   ' . $checkbox_null . '<span>&nbsp;สำนักงานใหญ่</span></td>';
        }

        foreach ($data->ServiceTransactions as $transaction) {
            if ($transaction->status == 1) {
                $serviceName  = $transaction->service_name ?? null;
                $servicePrice = $transaction->service_price ?? null;
                $totalAmount += $servicePrice;
                $id_gen += 1;
                $round += 1;
                $serviceRows .= '
                    <tr>
                        <td style="border: 1px solid black; border-collapse: collapse;">' . $id_gen . '</td>
                        <td style="border: 1px solid black; text-align: left; border-collapse: collapse;">' . $serviceName . '</td>
                        <td style="border: 1px solid black; border-collapse: collapse;">' . $id_gen . '</td>
                        <td style="border: 1px solid black; border-collapse: collapse;">' . $id_gen . '</td>
                        <td style="border: 1px solid black; text-align: left; border-collapse: collapse;">' . $serviceName . '</td>
                        <td style="border: 1px solid black; text-align: right; border-collapse: collapse;">' . number_format($servicePrice, 2) . '</td>
                        <td style="border: 1px solid black; text-align: right; border-collapse: collapse;">' . number_format($servicePrice, 2) . '</td>
                    </tr>';
            }
        }

        for ($i = $round; $i < 6; $i++) {
            $serviceRows .= '
            <tr>
                <td style="border: 1px solid black; border-top: none; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; text-align: left; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; text-align: right; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; text-align: left; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; text-align: right; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; text-align: right; border-collapse: collapse;">&nbsp;</td>
            </tr>';
        }
        $totalServicePrice = 0;
        $dis               = $data->discount;
        foreach ($data->serviceTransactions as $service_total) {
            if (isset($service_total->service_price) && $service_total->status === 1) {
                $totalServicePrice += $service_total->service_price;
            }
        }

        $total    = floor($totalAmount);
        $totalcal = $totalAmount - $total;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 15,
            'margin_right'      => 20,
            'margin_top'        => 20,
            'margin_bottom'     => 20,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบกำกับภาษี');
        $mpdf->AddPage();

        $html = '
            <style>
                .border {
                    border: 1px solid black;
                    border-top-left-radius: 0.5em;
                    border-top-right-radius: 0.5em;
                    border-bottom-right-radius: 0.5em;
                    border-bottom-left-radius: 0.5em;
                }
                .border1 {
                    border: 1px solid black;
                    border-top-left-radius: 0.5em;
                    border-top-right-radius: 0.5em;
                    border-bottom-right-radius: 0.5em;
                    border-bottom-left-radius: 0.5em;
                }
                .container {
                    border: 1px solid black;
                }
                .one {
                    display: inline-block;
                    line-height: 0.1px;
                    font-size: 8px;
                    vertical-align: top;
                }
                .two {
                    display: table-cell;
                    height: 30px;
                    vertical-align: bottom;
                    font-size: 10px;
                    background-color: red;
                }
                .grid-container {
                    display: grid;
                    column-gap: 5px;
                    grid-template-columns: auto auto;
                    padding: 10px;
                }
                .grid-item {
                    border: 1px solid rgba(0, 0, 0, 0.8);
                    padding: 20px;
                }
                .under{
                    vertical-align: text
                }
            </style>
                <body>
                    <div style="position:absolute; top:235px; left:69px; text-align:left; font-size: 10px;">
                        Customer
                    </div>
                    <div style="position:absolute; top:362px; left:69px; text-align:left; font-size: 10px;">
                        Tax ID
                    </div>
                    <div style="position:absolute; top:387px; left:69px; text-align:left; font-size: 10px;">
                        Reference
                    </div>
                    <div style="position:absolute; top:233px; left:400px; text-align: left; font-size: 10px; width: 50px;">
                        No.
                    </div>
                    <div style="position:absolute; top:257px; left:400px; text-align:left; font-size: 10px;">
                        Date
                    </div>
                    <div style="position:absolute; top:335px; left:400px; text-align:left; font-size: 10px;">
                        Sales Order No.
                    </div>
                    <div style="position:absolute; top:362px; left:400px; text-align:left; font-size: 10px;">
                        Salesman
                    </div>
                    <div style="position:absolute; top:387px; left:400px; text-align:left; font-size: 10px;">
                        Territory
                    </div>
                    <div style="position:absolute; top:421px; left:69px; text-align: left; font-size: 10px; width: 50px;">
                        No.
                    </div>
                    <div style="position:absolute; top:421px; left:162px; text-align: left; font-size: 10px; width: 100%;">
                        Code/Descriptions
                    </div>
                    <div style="position:absolute; top:421px; left:310px; text-align: left; font-size: 10px;">
                        Loc.
                    </div>
                    <div style="position:absolute; top:421px; left:378px; text-align: left; font-size: 10px;">
                        Quantity
                    </div>
                    <div style="position:absolute; top:421px; left:467px; text-align: left; font-size: 10px;">
                        Unit Price
                    </div>
                    <div style="position:absolute; top:421px; left:544px; text-align: left; font-size: 10px;">
                        Discount
                    </div>
                    <div style="position:absolute; top:421px; left:640px; text-align: left; font-size: 10px;">
                        Amount
                    </div>
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <tr>
                            <td style="width: 12%; vertical-align: text-top;" rowspan="5"><img src="' . $branche->logo_url . '" alt="FIE" align="left" style="width: 70px; height: 70px;"></td>
                            <td style="font-size: 28px; vertical-align: text-top; width: 58%;"><strong>' . $branche_name . '</strong></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_address . ' ' . $branche_subdistrictName . ' ' . $branche_districtName . ' ' . $branche_provinceName . ' ' . $branche_postalcode . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_phone . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">เลขประจำตัวผู้เสียภาษี/Tax ID ' . $branche_tax_id . ' สำนักงานใหญ่</td>
                            <td style="text-align: right; font-size: 20px; background-color: #ff8000;"><strong>ใบเสร็จรับเงิน/ใบกำกับภาษี</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right; font-size: 20px;"><strong>สำเนา (สำหรับฝ่ายบัญชี)</strong></td>
                        </tr>
                    </table>
                    <div style="width: 100%;">
                        <div class="border" style="float: left; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr>
                                    <td style="padding-left: 10px;">ลูกค้า ' . (! empty($customerNo) ? $customerNo : '&nbsp;') . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">' . (! empty($customer_name) ? $customer_name : '&nbsp;') . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">' . (! empty($customer_address) ? $customer_address : '&nbsp;') . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">' . (! empty($customer_subdistrictName) ? $customer_subdistrictName : '&nbsp;') . ' '
        . (! empty($customer_districtName) ? $customer_districtName : '&nbsp;') . ' '
        . (! empty($customer_provinceName) ? $customer_provinceName : '&nbsp;') . ' '
        . (! empty($customer_postalcode) ? $customer_postalcode : '&nbsp;') . '
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">โทร ' . (! empty($customer_phoneNumber) ? $customer_phoneNumber : '&nbsp;') . '</td>
                                </tr>
                                <tr>
                                    ' . (! empty($call_checkbox) ? $call_checkbox : '&nbsp;') . '
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">อ้างอิง</td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr>
                                    <td style="padding-left: 10px;">เลขที่ ' . $inspection_no . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">วันที่ ' . $formattedDate . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">เลขที่ใบสั่งขาย</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">พนักงานขาย</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">เขตการขาย</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                        <div style="width: 100%;">
                            <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin-top: 5px; table-layout: fixed;">
                                <tr style="width: 100%">
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 5%;">ลำดับ</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 30%;">รหัสสินค้า/รายละเอียด</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 9%;">คลัง</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 14%;">จำนวน</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 14%;">หน่วยละ</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 9%;">ส่วนลด</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 20%;">จำนวนเงิน</th>
                                </tr>
                                <tbody>
                                    <tr>
                                        ' . $serviceRows . '
                                    </tr>
                                </tbody>
                            <div style="width: 100%;">
                                <tr>
                                    <td>&nbsp;</td>
                                    <td style="text-align: left; width: 100%; padding: 10px;" colspan="3">
                                        <p style="font-size: 16px;"><strong>ทะเบียนรถ ' . $vehiclePlate . ' ' . $customer_provinceName . '</strong></p>
                                    </td>
                                    <td style="text-align: left; vertical-align: top; border: 1px solid black; border-bottom: none; width: 32%;" colspan="2" rowspan="2">
                                        <p>รวมเป็นเงิน</p>
                                        <p>หักส่วนลด</p>
                                        <p>ยอดหลังหักส่วนลด</p>
                                        <p>หักเงินมัดจำ</p>
                                        <p>ยอดหลังหักเงินมัดจำ</p>
                                        <p>จำนวนภาษีมูลค่าเพิ่ม   7.00%</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top; width: 15%;" colspan="1" >
                                        <p>' . number_format($totalServicePrice, 2) . '</p>
                                        <p>' . number_format($dis, 2) . '</p>
                                        <p>' . number_format($totalServicePrice - $dis, 2) . '</p>
                                        <p>0.00</p>
                                        <p>500.00</p>
                                        <p>35.00</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; "colspan="4">
                                        <p style="font-size: 10px;">ผิด ตก ยกเว้น E.&O.E. *** สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน***</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background-color: #ff8000; text-align: center; width: 53%; border: 1px solid black; font-size: 15px;" colspan="4">
                                        <p><strong>(ห้าร้อยสามสิบห้าบาทถ้วน)</strong></p>
                                    </td>
                                    <td style="border: none;" colspan="2">
                                        <p>จำนวนเงินรวมทั้งสิ้น</p>
                                    </td>
                                    <td style="background-color: #ff8000; text-align: right; vertical-align: top; width: 15%; border: 1px solid black;" colspan="1">
                                        <p><strong>535.00</strong></p>
                                    </td>
                                </tr>
                            </div>
                        </table>
                        <div class="border1" style="width: 100%; margin-top: 5px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr>
                                    <td style="border: none; width: 30%; font-size: 16px; text-align: center; vertical-align: text-top;">
                                        <p><strong>ได้รับสินค้าตามรายการถูกต้องแล้ว</strong></p>
                                        <p>________________ <span>____/____/____</span></p>
                                        <p class="main" style="font-size: 10px;">ผู้รับสินค้า/Goods Received by <span>วันที่/Date</span></p>
                                        <p>________________ <span>____/____/____</span></p>
                                        <p class="main" style="font-size: 10px;">ผู้ส่งสินค้า/Delivery by <span>วันที่/Date</span></p>
                                    </td>
                                    <td style="padding-left: 5px; text-align: center; border: 1px solid black; border-top: none; border-bottom: none; width: 35%; font-size: 14px; vertical-align: text-top;">
                                        <p><strong>ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../image/checkbox.png" alt="FIE" align="center" style="width: 10px"></strong><span>&nbsp;เงินสด&nbsp;&nbsp;<img src="../image/checkbox.png" alt="FIE" align="center" style="width: 10px"></span><span>&nbsp;เช็ค</span></p>
                                        <p><strong>เช็คธนาคาร___________</strong><span>สาขา___________</span></p>
                                        <p><strong>เลขที่_____________</strong><span>วันที่___/____/______</span></p>
                                        <p><strong>จำนวนเงิน__________________________</p>
                                        <p><strong>ภาษีหัก ณ ที่จ่าย______________________</p>
                                        <p>________________ <span>____/____/____</span></p>
                                        <p style="font-size: 10px;">ผู้รับเงิน/Collector <span>วันที่/Date</span></p>
                                    </td>
                                    <td style="vertical-align: text-top; border: 1px solid black; border-top: none; border-bottom: none; border-right: none; width: 35%; font-size: 16px; text-align: center;">
                                        <p><strong>บริษัท เฟิร์สอินสเปคเตอร์เอ็นจิเนียร์ จำกัด</strong></p>
                                        <p>__________________________</p>
                                        <p style="font-size: 10px;">ผู้รับมอบอำนาจ/Authorized Signature</p>
                                        <p>__________________________</p>
                                        <p style="font-size: 10px;">ผู้ตรวจเช็คสินค้า/Approved by</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function receiptRepaire(Request $request, $id)
    {
        $cash_input = $request->cash_input;
        $data       = WorkOrder::with([
            'Vehicles.Customers.Customer_Addresses',
            'Vehicles.VehicleBrands',
            'Vehicles.VehicleInspectionTypes',
            'Vehicles.Customers.Customer_Addresses.Provinces',
            'Vehicles.Customers.Customer_Addresses.Districts',
            'Vehicles.Customers.Customer_Addresses.SubDistricts',
            'WorkOrderServices.repair_service',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnError('Data Not Found');
        }

        // Pull Data Vehicle
        $vehicle              = $data->Vehicles ?? null;
        $vehicle_licencePlate = $vehicle->license_plate ?? null;
        $vehicle_brand        = $vehicle->VehicleBrands->name ?? null;
        $vehicleType          = $data->Vehicles->VehicleInspectionTypes->name ?? null;

        //Pull Data WorkOrder
        $order_gen = $data->no;

        //Pull Data Customer
        $customer             = $vehicle->customers ?? null;
        $customer_name        = $customer->name ?? null;
        $customer_phone       = $customer->phone_number1 ?? null;
        $customer_postalcode  = $customer->Customer_Addresses[0]->postal_code ?? null;
        $customer_address     = $customer->Customer_Addresses[0]->address ?? null;
        $customer_subdistrict = $customer->Customer_Addresses[0]->SubDistricts->name_th_prefix ?? null;
        $customer_district    = $customer->Customer_Addresses[0]->Districts->name_th_prefix ?? null;
        $customer_province    = $customer->Customer_Addresses[0]->Provinces->name_th_prefix ?? null;

        // Pull data branch
        $branche_address          = $data->Branches->address ?? null;
        $branche_province_name    = $data->Branches->provinces->name_th_prefix ?? null;
        $branche_district_name    = $data->Branches->districts->name_th_prefix ?? null;
        $branche_subdistrict_name = $data->Branches->SubDistricts->name_th_prefix ?? null;
        $branche_postal_code      = $data->Branches->postal_code ?? null;
        $branche_phone            = $data->Branches->phone ?? null;
        $branche_open_date_time   = $data->Branches->open_close_time ?? null;
        $branche_open_date        = $data->Branches->open_date ?? null;
        $branche_logo             = $data->Branches->business_name ?? null;
        $branche_logo1            = $data->Branches->logo ?? null;

        // if ($branche_logo1 != null) {
        //     $branche_image = '<td style="width: 5%; border: none; text-align: left;"><img src="../public/' . $branche_logo1 . '" alt="FIE" align="left" style="width: 80px"></td>';
        // } else {
        //     $branche_image = 'Not Found';
        // }
        $branche_image = null;

        // Formatted Date
        $date          = $data->date;
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $id_gen      = 0;
        $round       = 0;

        // ตรวจสอบว่า $data มี property ServiceTransactions และเป็น object
        if (isset($data->WorkOrderServices) && is_iterable($data->WorkOrderServices)) {
            foreach ($data->WorkOrderServices as $orderservice) {
                $order_service      = $orderservice ?? null;
                $order_serviceName  = $order_service->repair_service->name ?? null;
                $order_servicePrice = $order_service->price ?? null;
                $totalAmount += $order_servicePrice;
                $id_gen++;
                $round += 1;
                $serviceRows .= '
                    <tr>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center;">' . $id_gen . '</td>
                        <td style="text-align: left; border: 1px solid black; border-collapse: collapse;" colspan="4">' . $order_serviceName . '</td>
                        <td style="text-align: right; border: 1px solid black; border-collapse: collapse;">' . number_format($order_servicePrice, 2) . '</td>
                    </tr>';
            }
        }
        for ($i = $round; $i < 6; $i++) {
            $serviceRows .= '
            <tr>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;" colspan="4">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
            </tr>';
        }
        $total    = floor($totalAmount);
        $totalcal = $totalAmount - $total;

        if ($cash_input == 0) {
            $change     = null;
            $cash_input = null;
        } else {
            $change = $cash_input - $total;
        }
        $status_order_paid = '';

        // ตรวจสอบค่า $data->status โดยตรง
        if (isset($data->status_paid)) {
            $statusMessages = [
                'paid_cash'        => 'เงินสด : ',
                'paid_tran'        => 'เงินโอน : ',
                'overdue'          => 'ลูกหนี้ : ค้างชำระ',
                'overdue(success)' => 'ลูกหนี้ : จ่ายแล้ว',
                'credit'           => 'บัตรเครดิต : ',
                'cancel'           => 'ยกเลิก : ',
            ];

            if (array_key_exists($data->status_paid, $statusMessages)) {
                $status_order_paid = '<p>' . $statusMessages[$data->status_paid] . number_format($total, 2) . '</p>';
            } else {
                $status_order_paid = '<p>สถานะไม่รู้จัก</p>';
            }
        } else {
            $status_order_paid = '<p>สถานะไม่ถูกต้อง</p>';
        }

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4-l',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบเสร็จ');
        $mpdf->AddPage();

        $html = '
        <body>
            <div style="width: 49%; float: left; border-right: 1px dashed black; padding-left: 15px;">
                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <h2>' . $branche_logo . '</h2>
                                    <p>' . $branche_address . ' ' . $branche_province_name . ' ' . $branche_district_name . ' ' . $branche_subdistrict_name . ' ' . $branche_postal_code . '</p>
                                    <p>' . $branche_open_date_time . ' น. (' . $branche_open_date . ')</p>
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 25px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>สำเนา</p>
                                    <p style="border: 3px double red;">นัดรับ 14 ส.ค. 67</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <p>ลูกค้า: ' . $customer_name . ' </p>
                                    <p>ที่อยู่: ' . $branche_address . ' ' . $branche_province_name . ' ' . $branche_district_name . ' ' . $branche_subdistrict_name . ' ' . $branche_postal_code . ' </p>
                                    <p>โทร.: ' . $customer_phone . ' </p>
                                    <p>ทะเบียน: ' . $vehicle_licencePlate . ' [' . $vehicle_brand . '] </p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top;">
                                    <p>เลขที่: ' . $order_gen . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $vehicleType . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    ' . $serviceRows . '
                                </tr>
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>1. เงินสด: ' . number_format($totalAmount, 2) . '</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>_____________</p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p><strong>ส่วนลด</strong></p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totalcal, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>(กชมน รุ่งเรือง)</p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                       <p><strong>ยอดสุทธิ</strong></p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totalAmount - $totalcal, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>ชำระแล้ว</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>' . number_format($totalAmount - $totalcal, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($totalAmount, 2) . ' ทอน ' . number_format($totalAmount, 2) . '</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($totalAmount, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
            </div>

            <div style="width: 49%; float: right;">
                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <h2>' . $branche_logo . '</h2>
                                    <p>' . $branche_address . ' ' . $branche_province_name . ' ' . $branche_district_name . ' ' . $branche_subdistrict_name . ' ' . $branche_postal_code . '</p>
                                    <p>' . $branche_open_date_time . ' น. (' . $branche_open_date . ')</p>
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 25px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>ต้นฉบับ</p>
                                    <p style="border: 3px double red;">นัดรับ 14 ส.ค. 67</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                                    <p>ลูกค้า: ' . $customer_name . ' </p>
                                    <p>ที่อยู่: ' . $branche_address . ' ' . $branche_province_name . ' ' . $branche_district_name . ' ' . $branche_subdistrict_name . ' ' . $branche_postal_code . ' </p>
                                    <p>โทร.: ' . $customer_phone . ' </p>
                                    <p>ทะเบียน: ' . $vehicle_licencePlate . ' [' . $vehicle_brand . '] </p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top;">
                                    <p>เลขที่: ' . $order_gen . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $vehicleType . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    ' . $serviceRows . '
                                </tr>
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>1. เงินสด: ' . number_format($totalAmount, 2) . '</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>_____________</p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p><strong>ส่วนลด</strong></p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totalcal, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>(กชมน รุ่งเรือง)</p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                       <p><strong>ยอดสุทธิ</strong></p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totalAmount - $totalcal, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>ชำระแล้ว</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>' . number_format($totalAmount - $totalcal, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($totalAmount, 2) . ' ทอน ' . number_format($totalAmount, 2) . '</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($totalAmount, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
            </div>
        </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function billquotationRepaire($id)
    {
        $data = WorkOrder::with([
            'Vehicles.Customers.Customer_Addresses',
            'Vehicles.VehicleBrands',
            'Vehicles.VehicleInspectionTypes',
            'Vehicles.Customers.Customer_Addresses.Provinces',
            'Vehicles.Customers.Customer_Addresses.Districts',
            'Vehicles.Customers.Customer_Addresses.SubDistricts',
            'WorkOrderServices.repair_service',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnError('Data Not Found');
        }

        // Pull Data Vehicle
        $vehicle              = $data->Vehicles ?? null;
        $vehicle_licensePlate = $vehicle->license_plate ?? null;
        $vehicle_Brand        = $vehicle->VehicleBrands->name ?? null;
        $vehicleType          = $vehicle->VehicleInspectionTypes->name ?? null;

        //Pull Data WorkOrder
        $order_gen = $data->no;

        //Pull Data Customer
        $customer             = $vehicle->customers ?? null;
        $customer_name        = $customer->name ?? null;
        $customer_phone       = $customer->phone_number1 ?? null;
        $customer_postalcode  = $customer->Customer_Addresses[0]->postal_code ?? null;
        $customer_address     = $customer->Customer_Addresses[0]->address ?? null;
        $customer_subdistrict = $customer->Customer_Addresses[0]->SubDistricts->name_th_prefix ?? null;
        $customer_district    = $customer->Customer_Addresses[0]->Districts->name_th_prefix ?? null;
        $customer_province    = $customer->Customer_Addresses[0]->Provinces->name_th_prefix ?? null;

        // Pull data branch
        $branche_address          = $data->Branches->address ?? null;
        $branche_province_name    = $data->Branches->provinces->name_th_prefix ?? null;
        $branche_district_name    = $data->Branches->districts->name_th_prefix ?? null;
        $branche_subdistrict_name = $data->Branches->SubDistricts->name_th_prefix ?? null;
        $branche_postal_code      = $data->Branches->postal_code ?? null;
        $branche_phone            = $data->Branches->phone ?? null;
        $branche_open_date_time   = $data->Branches->open_close_time ?? null;
        $branche_open_date        = $data->Branches->open_date ?? null;
        $branche_logo             = $data->Branches->business_name ?? null;
        $branche_logo1            = $data->Branches->logo ?? null;

        if ($branche_logo1 != null) {
            $branche_image = '<td style="width: 5%; border: none; text-align: left;"><img src="../public/' . $branche_logo1 . '" alt="FIE" align="left" style="width: 80px"></td>';
        } else {
            $branche_image = 'Not Found';
        }
        // $branche_image = null;

        //Formatted Date
        $date          = $data->date;
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $id_gen      = 0;
        $round       = 0;

        if (isset($data->WorkOrderServices) && is_iterable($data->WorkOrderServices)) {
            foreach ($data->WorkOrderServices as $orderservice) {
                $order_service      = $orderservice ?? null;
                $order_serviceName  = $order_service->repair_service->name ?? '';
                $order_servicePrice = $order_service->price ?? 0;
                $totalAmount += $order_servicePrice;
                $id_gen++;
                $round += 1;
                $serviceRows .= '
                <tr>
                    <td>' . $id_gen . '</td>
                    <td style="text-align: left">' . $order_serviceName . '</td>
                    <td style="text-align: right">' . number_format($order_servicePrice, 2) . '</td>
                </tr>';
            }
        }
        for ($i = $round; $i < 7; $i++) {
            $serviceRows .= '
            <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
            </tr>';
        }

        $total    = floor($totalAmount);
        $totalcal = $totalAmount - $total;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 20,
            'margin_right'      => 20,
            'margin_top'        => 20,
            'margin_bottom'     => 20,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบเสร็จ');
        $mpdf->AddPage();

        $html = '
        <!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <title>ใบวางบิล/ใบแจ้งหนี้</title>
            <style>
                body {
                    font-family: "th-sarabun", Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #fff;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    padding: 20px;
                }
                .header-left {
                    text-align: left;
                }
                .header-left1 {
                    text-align: left;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 200px;
                    left: 470px;
                }
                .header-left h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .header-left p {
                    margin: 2px 0;
                    font-size: 18px;
                }
                .header-right {
                    text-align: right;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 77px;
                    left: 585px;
                }
                .header-right2 {
                    text-align: right;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 192px;
                    left: 470px;
                }
                .content {
                    padding: 20px;
                    display: flex;
                    justify-content: space-between;
                }
                .content-left {
                    text-align: left;
                    font-size: 18px;
                }
                .content-right {
                    text-align: right;
                    font-size: 18px;
                }
                .table-container {
                    margin-top: 5px;
                    width: 100%;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 8px;
                    text-align: center;
                    font-size: 18px;ก
                }
                th {
                    background-color: #f2f2f2;
                }
                .footer {
                    margin-top: 5px;
                    text-align: left;
                    font-size: 18px;
                }
                .footer table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .footer td {
                    border: none;
                    text-align: left;
                    padding: 8px;
                    font-size: 18px;
                }
                .signature-section {
                    margin-top: 50px;
                    display: flex;
                    justify-content: space-between;
                    font-size: 18px;
                }
                .signature-section1 {
                    width: 100%;
                    text-align: center;
                }
                .signature-section2 {
                    width: 100%;
                    text-align: center;
                }
                .container {
                    display: flex;
                    align-items: flex-start;
                    text-align: left;
                    font-size: 20px;
                    position: absolute;
                    width: 50px;
                    top: 130px;
                    left: 80px;
                }
                .container img {
                    margin-right: 20px; /* Adjust spacing as needed */
                    width: 100px; /* Adjust image size as needed */
                }
                .text-content {
                    max-width: 300px; /* Adjust as needed */
                }
            </style>
        </head>
        <body>
            <table style="width: 100%;">
                    <tr>
                        <td style="width: 5%; border: none; text-align: left;">
                            ' . $branche_image . '
                        </td>
                        <td style="width: 60%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                            <h2>' . $branche_logo . '</h2>
                            <p>' . $branche_address . ' ' . $branche_province_name . ' ' . $branche_district_name . ' ' . $branche_subdistrict_name . ' ' . $branche_postal_code . '</p>
                            <p>' . $branche_open_date_time . ' น. (' . $branche_open_date . ')</p>
                            <p>' . $branche_phone . '</p>
                        </td>
                        <td style="width: 40%; vertical-align: text-top; border: none; text-size: 20px; text-align: right;">
                            <p style="font-size: 20px"><strong>ใบวางบิล/ใบแจ้งหนี้</strong></p>
                            <p>ต้นฉบับ</p>
                        </td>
                    </tr>
            </table>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 70%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                        <p>ลูกค้า: ' . $customer_name . '</p>
                        <p>ที่อยู่: ' . $customer_address . ' ' . $customer_subdistrict . ' ' . $customer_district . ' ' . $customer_province . ' ' . $customer_postalcode . '</p>
                        <p>โทร.: ' . $customer_phone . '</p>
                        <p>ทะเบียน: ' . $vehicle_licensePlate . ' [' . $vehicle_Brand . ']</p>
                    </td>
                    <td style="width: 30%; border: none; text-align: left; vertical-align: text-top;">
                        <p>เลขที่: ' . $order_gen . '</p>
                        <p>วันที่: ' . $formattedDate . '</p>
                        <p>ประเภท: ' . $vehicleType . ' </p>
                    </td>
                </tr>
            </table>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%;">ลำดับ</th>
                            <th style="width: 70%;">รายการ</th>
                            <th style="width: 20%;">จำนวนเงิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                        ' . $serviceRows . '
                        </tr>
                    </tbody>
                </table>
            <div class="footer">
                <table>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ยอดรวม: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($totalAmount, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ส่วนลดรวม: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($totalcal, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ยอดสุทธิ: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($total, 2) . '</td>
                    </tr>
                </table>
            </div>
            </div>
            <div style="overflow: hidden; padding: 0 20px; margin-top: 60px">
                <div style="float: left; width: 45%; line-height: 0.2; text-align: center;">
                    <p>จัดเตรียมโดย</p>
                    <p>..................................</p>
                    <p>(..................................)</p>
                    <p>13/08/2567</p>
                </div>
                <div style="float: right; width: 45%; line-height: 0.2; text-align: center;">
                    <p>ยอมรับใบเสนอราคา</p>
                    <p>..................................</p>
                    <p>(..................................)</p>
                    <p>......../......../........</p>
                </div>
            </div>
        </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function billserviceRepaire($id)
    {
        // ดึงข้อมูลการตรวจสอบ พร้อมข้อมูลที่เกี่ยวข้อง
        $data = WorkOrder::with([
            'Vehicles.Customers.Customer_Addresses',
            'Vehicles.VehicleBrands',
            'Vehicles.VehicleInspectionTypes',
            'Vehicles.Customers.Customer_Addresses.Provinces',
            'Vehicles.Customers.Customer_Addresses.Districts',
            'Vehicles.Customers.Customer_Addresses.SubDistricts',
            'WorkOrderServices.repair_service',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnError('Data Not Found');
        }

        // Pull Data Vehicle
        $vehicle              = $data->Vehicles ?? null;
        $vehicle_licensePlate = $vehicle->license_plate ?? null;
        $vehicleType          = $vehicle->VehicleInspectionTypes->name ?? null;

        //Pull Data Customer
        $customer       = $vehicle->customers ?? null;
        $customer_name  = $customer->name ?? null;
        $customer_phone = $customer->phone_number1 ?? null;

        // Pull data branch
        $branche_name = $data->Branches->business_name ?? null;

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $round       = 0;

        if (isset($data->WorkOrderServices) && is_iterable($data->WorkOrderServices)) {
            foreach ($data->WorkOrderServices as $orderservice) {
                $order_service      = $orderservice ?? null;
                $order_serviceName  = $order_service->repair_service->name ?? null;
                $order_servicePrice = $order_service->price ?? null;
                $totalAmount += $order_servicePrice;
                $round += 1;
                $serviceRows .= '
                <tr>
                    <td>' . $order_serviceName . '</td>
                    <td class="amount">' . $order_servicePrice . '</td>
                </tr>';
            }
        }
        for ($i = $round; $i < 7; $i++) {
            $serviceRows .= '
            <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
            </tr>';
        }
        $total    = floor($totalAmount);
        $totalcal = $totalAmount - $total;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 30,
            'margin_right'      => 30,
            'margin_top'        => 30,
            'margin_bottom'     => 30,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบเสร็จ');
        $mpdf->AddPage();

        $html = '
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: "th-sarabun", Arial, sans-serif;
                    line-height: 1.6;
                    margin: 20px;
                }
                .receipt {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .header h1, .header h2, .header h3 {
                    margin: 5px 0;
                }
                .header h1 {
                    font-size: 24px;
                    font-weight: bold;
                }
                .header h2 {
                    font-size: 20px;
                    font-weight: bold;
                }
                .header h3 {
                    font-size: 16px;
                }
                table {
                    width: 100%;
                    margin-top: 20px;
                    border-collapse: collapse;
                }
                table, th, td {
                    border: none;
                }
                th, td {
                    padding: 8px;
                    text-align: left;
                }
                .amount {
                    text-align: right;
                }
                .total {
                    font-size: 24px;
                    font-weight: bold;
                    text-align: right;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                }
                .footer p {
                    margin: 5px 0;
                }
            </style>
        </head>
        <body>
        <div class="receipt">
            <div class="header">
                <h2>' . $branche_name . '</h2>
                <h3>' . $customer_name . '</h3>
                <h3>' . $customer_phone . '</h3>
                <h2>' . $vehicle_licensePlate . '</h2>
                <h3>ประเภท: ' . $vehicleType . '</h3>
            </div>

            <table>
                <tr>
                    <td colspan="2" style="border-bottom: 3px solid #000;"></td>
                </tr>
                ' . $serviceRows . '
                <tr>
                    <td colspan="2" style="border-bottom: 1px solid #000;"></td>
                </tr>
                <tr>
                    <td><strong>ยอดรวม</strong></td>
                    <td class="amount"><strong>' . number_format($totalAmount, 2) . '</strong></td>
                </tr>
                <tr>
                    <td>ส่วนลด</td>
                    <td class="amount">' . number_format($totalcal, 2) . '</td>
                </tr>
                <tr>
                    <td class="total"><strong>ยอดสุทธิ</strong></td>
                    <td class="total"><strong>' . number_format($total, 2) . '</strong></td>
                </tr>
                <tr>
                    <td colspan="2" style="border-bottom: 3px solid #000;"></td>
                </tr>
            </table>

            <div class="footer">
                <p>พนักงาน</p>
                <p>____________________</p>
                <p>(ลายเซ็น)</p>
            </div>
        </div>
        </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function billtax_invioceRepaire($id)
    {
        $data = WorkOrder::with([
            'Vehicles.Customers.Customer_Addresses',
            'Vehicles.VehicleBrands',
            'Vehicles.VehicleInspectionTypes',
            'Vehicles.Customers.Customer_Addresses.Provinces',
            'Vehicles.Customers.Customer_Addresses.Districts',
            'Vehicles.Customers.Customer_Addresses.SubDistricts',
            'WorkOrderServices.repair_service',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnError('Data Not Found');
        }

        // Pull Data Vehicle
        $vehicle              = $data->Vehicles ?? null;
        $vehicle_licencePlate = $vehicle->license_plate ?? null;
        $vehicle_brand        = $vehicle->VehicleBrands->name ?? null;
        $vehicleType          = $vehicle->VehicleInspectionTypes->name ?? null;

        //Pull Data WorkOrder
        $order_gen = $data->no;

        //Pull Data Customer
        $customer             = $vehicle->customers ?? null;
        $customer_name        = $customer->name ?? null;
        $customerNo           = $customer ? $customer->no : null;
        $customer_phone       = $customer->phone_number1 ?? null;
        $customer_taxID       = $customer->tax_id ?? null;
        $customer_postalcode  = $customer->Customer_Addresses[0]->postal_code ?? null;
        $customer_address     = $customer->Customer_Addresses[0]->address ?? null;
        $customer_subdistrict = $customer->Customer_Addresses[0]->SubDistricts->name_th_prefix ?? null;
        $customer_district    = $customer->Customer_Addresses[0]->Districts->name_th_prefix ?? null;
        $customer_province    = $customer->Customer_Addresses[0]->Provinces->name_th_prefix ?? null;

        // Pull data branch
        $branche_address          = $data->Branches->address ?? null;
        $branche_province_name    = $data->Branches->provinces->name_th_prefix ?? null;
        $branche_district_name    = $data->Branches->districts->name_th_prefix ?? null;
        $branche_subdistrict_name = $data->Branches->SubDistricts->name_th_prefix ?? null;
        $branche_postal_code      = $data->Branches->postal_code ?? null;
        $branche_phone            = $data->Branches->phone ?? null;
        $branche_open_date_time   = $data->Branches->open_close_time ?? null;
        $branche_open_date        = $data->Branches->open_date ?? null;
        $branche_name             = $data->Branches->business_name ?? null;
        $branche_logo1            = $data->Branches->logo_url ?? null;
        $branche_tax_id           = $branche->Tax_id ?? null;

        $branche_image = '<td style="width: 12%; vertical-align: text-top;" rowspan="5"><img src="' . $branche_logo1 . '" alt="FIE" align="left" style="width: 70px; height: 70px;"></td>';

        // $branche_image = null;

        //Formatted Date
        $date          = $data->date;
        $formattedDate = Carbon::parse($date)->format('d/m/') . (Carbon::parse($date)->year + 543 - 2500);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $id_gen      = 0;
        $round       = 0;

        // $checkbox_null = '<img src="../image/checkbox.png" alt="checkbox" style="width: 12px;">';
        // $checkbox_value = '<img src="../image/checkbox-true.png" alt="checkbox" style="width: 12px;">';
        $checkbox_null  = '<img src="../image/checkbox.png" alt="checkbox" style="width: 12px;">';
        $checkbox_value = '<img src="../image/checkbox.png" alt="checkbox" style="width: 12px;">';

        if ($customer->is_headquarter == 1) {
            $call_checkbox = '<td style="padding-left: 10px;">เลขประจำตัวผู้เสียภาษี ' . $customer_taxID . '   ' . $checkbox_value . '<span>&nbsp;สำนักงานใหญ่</span></td>';
        } else {
            $call_checkbox = '<td style="padding-left: 10px;">เลขประจำตัวผู้เสียภาษี ' . $customer_taxID . '   ' . $checkbox_null . '<span>&nbsp;สำนักงานใหญ่</span></td>';
        }

        if (isset($data->WorkOrderServices) && is_iterable($data->WorkOrderServices)) {
            foreach ($data->WorkOrderServices as $orderservice) {
                $order_service      = $orderservice ?? null;
                $order_serviceName  = $order_service->repair_service->name ?? null;
                $order_servicePrice = $order_service->price ?? null;
                $totalAmount += $order_servicePrice;
                $id_gen += 1;
                $round += 1;
                $serviceRows .= '
                <tr>
                    <td style="border: 1px solid black; border-collapse: collapse;">' . $id_gen . '</td>
                    <td style="border: 1px solid black; text-align: left; border-collapse: collapse;">' . $order_serviceName . '</td>
                    <td style="border: 1px solid black; border-collapse: collapse;">' . $id_gen . '</td>
                    <td style="border: 1px solid black; border-collapse: collapse;">' . $id_gen . '</td>
                    <td style="border: 1px solid black; text-align: left; border-collapse: collapse;">' . $order_serviceName . '</td>
                    <td style="border: 1px solid black; text-align: right; border-collapse: collapse;">' . number_format($order_servicePrice, 2) . '</td>
                    <td style="border: 1px solid black; text-align: right; border-collapse: collapse;">' . number_format($order_servicePrice, 2) . '</td>
                </tr>';
            }
        }
        for ($i = $round; $i < 6; $i++) {
            $serviceRows .= '
            <tr>
                <td style="border: 1px solid black; border-bottom: none; border-top: none; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-bottom: none; border-top: none; text-align: left; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-bottom: none; border-top: none; text-align: right; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-bottom: none; border-top: none; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-bottom: none; border-top: none; text-align: left; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-bottom: none; border-top: none; text-align: right; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-bottom: none; border-top: none; text-align: right; border-collapse: collapse;">&nbsp;</td>
            </tr>';
        }

        $total    = floor($totalAmount);
        $totalcal = $totalAmount - $total;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 20,
            'margin_right'      => 20,
            'margin_top'        => 20,
            'margin_bottom'     => 20,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบกำกับภาษี');
        $mpdf->AddPage();

        $html = '
            <style>
                .border {
                    border: 1px solid black;
                    border-top-left-radius: 0.5em;
                    border-top-right-radius: 0.5em;
                    border-bottom-right-radius: 0.5em;
                    border-bottom-left-radius: 0.5em;
                }
                .border1 {
                    border: 1px solid black;
                    border-top-left-radius: 0.5em;
                    border-top-right-radius: 0.5em;
                    border-bottom-right-radius: 0.5em;
                    border-bottom-left-radius: 0.5em;
                }
                .container {
                    border: 1px solid black;
                }
                .one {
                    display: inline-block;
                    line-height: 0.1px;
                    font-size: 8px;
                    vertical-align: top;
                }
                .two {
                    display: table-cell;
                    height: 30px;
                    vertical-align: bottom;
                    font-size: 10px;
                    background-color: red;
                }
                .grid-container {
                    display: grid;
                    column-gap: 5px;
                    grid-template-columns: auto auto;
                    padding: 10px;
                }
                .grid-item {
                    border: 1px solid rgba(0, 0, 0, 0.8);
                    padding: 20px;
                }
                .under{
                    vertical-align: text
                }
            </style>
                <body>
                    <div style="position:absolute; top:235px; left:87px; text-align:left; font-size: 10px;">
                        Customer
                    </div>
                    <div style="position:absolute; top:362px; left:87px; text-align:left; font-size: 10px;">
                        Tax ID
                    </div>
                    <div style="position:absolute; top:387px; left:87px; text-align:left; font-size: 10px;">
                        Reference
                    </div>
                    <div style="position:absolute; top:233px; left:410px; text-align: left; font-size: 10px; width: 50px;">
                        No.
                    </div>
                    <div style="position:absolute; top:257px; left:410px; text-align:left; font-size: 10px;">
                        Date
                    </div>
                    <div style="position:absolute; top:335px; left:410px; text-align:left; font-size: 10px;">
                        Sales Order No.
                    </div>
                    <div style="position:absolute; top:362px; left:410px; text-align:left; font-size: 10px;">
                        Salesman
                    </div>
                    <div style="position:absolute; top:387px; left:410px; text-align:left; font-size: 10px;">
                        Territory
                    </div>
                    <div style="position:absolute; top:421px; left:87px; text-align: left; font-size: 10px; width: 50px;">
                        No.
                    </div>
                    <div style="position:absolute; top:421px; left:175px; text-align: left; font-size: 10px; width: 100%;">
                        Code/Descriptions
                    </div>
                    <div style="position:absolute; top:421px; left:323px; text-align: left; font-size: 10px;">
                        Loc.
                    </div>
                    <div style="position:absolute; top:421px; left:388px; text-align: left; font-size: 10px;">
                        Quantity
                    </div>
                    <div style="position:absolute; top:421px; left:475px; text-align: left; font-size: 10px;">
                        Unit Price
                    </div>
                    <div style="position:absolute; top:421px; left:550px; text-align: left; font-size: 10px;">
                        Discount
                    </div>
                    <div style="position:absolute; top:421px; left:640px; text-align: left; font-size: 10px;">
                        Amount
                    </div>
                    <div style="position:absolute; top:714px; left:175px; text-align: left; font-size: 10px;">
                        ผิด ตก ยกเว้น E.&O.E. *** สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน***
                    </div>
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <tr>
                            ' . $branche_image . '
                            <td style="font-size: 28px; vertical-align: text-top; width: 58%;"><strong>' . $branche_name . '</strong></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_address . ' ' . $branche_province_name . ' ' . $branche_district_name . ' ' . $branche_subdistrict_name . ' ' . $branche_postal_code . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_phone . ',' . $branche_phone . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">เลขประจำตัวผู้เสียภาษี/Tax ID ' . $branche_tax_id . ' สำนักงานใหญ่</td>
                            <td style="text-align: right; font-size: 20px; background-color: #ff8000;"><strong>ใบเสร็จรับเงิน/ใบกำกับภาษี</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right; font-size: 20px;"><strong>สำเนา (สำหรับฝ่ายบัญชี)</strong></td>
                        </tr>
                    </table>
                    <div style="width: 100%;">
                        <div class="border" style="float: left; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr>
                                    <td style="padding-left: 10px;">ลูกค้า ' . $customerNo . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">' . $customer_name . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">' . $customer_address . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">' . $customer_subdistrict . ' ' . $customer_district . ' ' . $customer_province . ' ' . $customer_postalcode . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">โทร ' . $customer_phone . '</td>
                                </tr>
                                <tr>
                                    ' . $call_checkbox . '
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">อ้างอิง</td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr>
                                    <td style="padding-left: 10px;">เลขที่ ' . $order_gen . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">วันที่ ' . $formattedDate . '</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">เลขที่ใบสั่งขาย</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">พนักงานขาย</td>
                                </tr>
                                <tr>
                                    <td style="padding-left: 10px;">เขตการขาย</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                        <div style="width: 100%;">
                            <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin-top: 5px; table-layout: fixed;">
                                <tr style="width: 100%">
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 5%;">ลำดับ</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 30%;">รหัสสินค้า/รายละเอียด</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 9%;">คลัง</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 14%;">จำนวน</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 14%;">หน่วยละ</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 9%;">ส่วนลด</th>
                                    <th style="border: 1px solid black; background-color: #ff8000; width: 20%;">จำนวนเงิน</th>
                                </tr>
                                <tbody>
                                    <tr>
                                        ' . $serviceRows . '
                                    </tr>
                                </tbody>
                            <div style="width: 100%;">
                                <tr>
                                    <td style="text-align: left; width: 53%; padding: 10px; border: 1px solid black;" colspan="4">
                                        <p style="font-size: 16px;"><strong>ทะเบียนรถ ' . $vehicle_licencePlate . ' ' . $customer_province . '</strong></p>
                                    </td>
                                    <td style="text-align: left; vertical-align: top; border: 1px solid black; width: 32%;" colspan="2" rowspan="8">
                                        <p>รวมเป็นเงิน</p>
                                        <p>หักส่วนลด</p>
                                        <p>ยอดหลังหักส่วนลด</p>
                                        <p>หักเงินมัดจำ</p>
                                        <p>ยอดหลังหักเงินมัดจำ</p>
                                        <p>จำนวนภาษีมูลค่าเพิ่ม   7.00%</p>
                                        <p>จำนวนเงินรวมทั้งสิ้น</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top; width: 15%; border: 1px solid black;" colspan="1">
                                        <p>500.00</p>
                                        <p>0.00</p>
                                        <p>500.00</p>
                                        <p>0.00</p>
                                        <p>500.00</p>
                                        <p>35.00</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background-color: #ff8000; text-align: center; width: 53%; border: 1px solid black; font-size: 15px;" colspan="4">
                                        <p><strong>(ห้าร้อยสามสิบห้าบาทถ้วน)</strong></p>
                                    </td>
                                    <td style="background-color: #ff8000; text-align: right; vertical-align: top; width: 25%; border: 1px solid black;" colspan="2">
                                        <p><strong>535.00</strong></p>
                                    </td>
                                </tr>
                            </div>
                        </table>
                        <div class="border1" style="width: 100%; margin-top: 5px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr>
                                    <td style="border: none; width: 30%; font-size: 16px; text-align: center; vertical-align: text-top;">
                                        <p><strong>ได้รับสินค้าตามรายการถูกต้องแล้ว</strong></p>
                                        <p>________________ <span>____/____/____</span></p>
                                        <p class="main" style="font-size: 10px;">ผู้รับสินค้า/Goods Received by <span>วันที่/Date</span></p>
                                        <p>________________ <span>____/____/____</span></p>
                                        <p class="main" style="font-size: 10px;">ผู้ส่งสินค้า/Delivery by <span>วันที่/Date</span></p>
                                    </td>
                                    <td style="padding-left: 5px; text-align: center; border: 1px solid black; border-top: none; border-bottom: none; width: 35%; font-size: 14px; vertical-align: text-top;">
                                        <p><strong>ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../image/checkbox.png" alt="FIE" align="center" style="width: 10px"></strong><span>&nbsp;เงินสด&nbsp;&nbsp;<img src="../image/checkbox.png" alt="FIE" align="center" style="width: 10px"></span><span>&nbsp;เช็ค</span></p>
                                        <p><strong>เช็คธนาคาร___________</strong><span>สาขา___________</span></p>
                                        <p><strong>เลขที่_____________</strong><span>วันที่___/____/______</span></p>
                                        <p><strong>จำนวนเงิน__________________________</p>
                                        <p><strong>ภาษีหัก ณ ที่จ่าย______________________</p>
                                        <p>________________ <span>____/____/____</span></p>
                                        <p style="font-size: 10px;">ผู้รับเงิน/Collector <span>วันที่/Date</span></p>
                                    </td>
                                    <td style="vertical-align: text-top; border: 1px solid black; border-top: none; border-bottom: none; border-right: none; width: 35%; font-size: 16px; text-align: center;">
                                        <p><strong>บริษัท เฟิร์สอินสเปคเตอร์เอ็นจิเนียร์ จำกัด</strong></p>
                                        <p>__________________________</p>
                                        <p style="font-size: 10px;">ผู้รับมอบอำนาจ/Authorized Signature</p>
                                        <p>__________________________</p>
                                        <p style="font-size: 10px;">ผู้ตรวจเช็คสินค้า/Approved by</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function Getpage_Extent_customer_(Request $request) //notifications
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $columns = [
            'vehicles.id',
            'vehicles.license_plate',
            'customer_name',
            'province_name',
            'phone_number',
            'vehicle_gases.gas_expire',
            'vehicles.notificate_tax',
        ];

        $searchColumns = [
            'vehicles.license_plate',
            'customer_name',
            'phone_number',
        ];

        $today     = Carbon::now();
        $ninetyDay = $today->copy()->addDays(90);

        $startDate = $request->start_date;
        $endDate   = $request->end_date;

        $query = Vehicles::select(
            'vehicles.id',
            'vehicles.license_plate',
            DB::raw("CONCAT(customers.name, ' ', customers.lname) as customer_name"),
            DB::raw("COALESCE(provinces.name_th_prefix, 'null') as province_name"),
            'customers.phone_number1 as phone_number',
            'vehicles.notificate_insurance',
            'vehicles.notificate_sub_insurance',
            'vehicles.notificate_tax',
            'vehicle_gases.gas_expire',
            DB::raw("CASE
                        WHEN vehicles.notificate_insurance < '$today' THEN 'พรบ หมดอายุ'
                        WHEN vehicles.notificate_sub_insurance < '$today' THEN 'ประกัน หมดอายุ'
                        WHEN vehicles.notificate_tax < '$today' THEN 'ภาษี หมดอายุ'
                        ELSE NULL
                     END as status")
        )
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('customer_addresses', 'customers.id', '=', 'customer_addresses.customer_id')
            ->leftJoin('provinces', 'customer_addresses.province_id', '=', 'provinces.id')
            ->leftJoin('inspections', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->leftJoin('vehicle_gases', 'vehicles.id', '=', 'vehicle_gases.vehicle_id')
            ->where(function ($query) use ($today, $ninetyDay, $startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('vehicles.notificate_insurance', [$startDate, $endDate])
                        ->orWhereBetween('vehicles.notificate_sub_insurance', [$startDate, $endDate])
                        ->orWhereBetween('vehicles.notificate_tax', [$startDate, $endDate]);
                } else {
                    $query->whereBetween('vehicles.notificate_insurance', [$today, $ninetyDay])
                        ->orWhereBetween('vehicles.notificate_sub_insurance', [$today, $ninetyDay])
                        ->orWhereBetween('vehicles.notificate_tax', [$today, $ninetyDay])
                        ->orWhere('vehicles.notificate_insurance', '<', $today)
                        ->orWhere('vehicles.notificate_sub_insurance', '<', $today)
                        ->orWhere('vehicles.notificate_tax', '<', $today);
                }
            });

        $orderBy = $columns[$order[0]['column']] ?? 'vehicles.id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);
        $id    = ($page - 1) * $length;

        foreach ($items as $item) {
            $item->id = ++$id;

            if (empty($item->status)) {
                unset($item->status);
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function Getpage_Extent_customer_2(Request $request) // notifications
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $columns = [
            'vehicles.id',
            'vehicles.license_plate',
            'customer_name',
            'province_name',
            'phone_number',
            'vehicles.notificate_tax',
            'prb_renewals.date_end as prb_expiry_date',
            'insurances_renewals.date_end as insurance_expiry_date',
        ];

        $searchColumns = [
            'vehicles.license_plate',
            'customers.name',
        ];

        $today     = Carbon::now();
        $ninetyDay = $today->copy()->addDays(90);

        $query = Vehicles::select(
            'vehicles.id',
            'vehicles.license_plate',
            DB::raw("CONCAT(customers.name, ' ', customers.lname) as customer_name"),
            'customers.phone_number1 as phone_number',
            'vehicles.notificate_tax',
            DB::raw("(SELECT MAX(date_end) FROM prb_renewals WHERE prb_renewals.vehicle_id = vehicles.id) as prb_expiry_date"),
            DB::raw("(SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id) as insurance_expiry_date"),
            DB::raw("CASE WHEN vehicles.notificate_tax BETWEEN '$today' AND '$ninetyDay' THEN true ELSE false END as notificate_tax_status"),
            DB::raw("CASE WHEN (SELECT MAX(date_end) FROM prb_renewals WHERE prb_renewals.vehicle_id = vehicles.id) BETWEEN '$today' AND '$ninetyDay' THEN true ELSE false END as prb_expiry_date_status"),
            DB::raw("CASE WHEN (SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id) BETWEEN '$today' AND '$ninetyDay' THEN true ELSE false END as insurance_expiry_date_status")
        )
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('prb_renewals', 'vehicles.id', '=', 'prb_renewals.vehicle_id')
            ->leftJoin('insurances_renewals', 'vehicles.id', '=', 'insurances_renewals.vehicle_id')
            ->where(function ($query) use ($today, $ninetyDay) {
                $query->whereBetween('vehicles.notificate_tax', [$today, $ninetyDay])
                    ->orWhereBetween('prb_renewals.date_end', [$today, $ninetyDay])
                    ->orWhereBetween('insurances_renewals.date_end', [$today, $ninetyDay]);
            })
            ->havingRaw('notificate_tax_status = true OR prb_expiry_date_status = true OR insurance_expiry_date_status = true')
            ->distinct();

        $orderBy = $columns[$order[0]['column']] ?? 'vehicles.id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
    protected function extentCustomerNotificationsQuery(Carbon $today, Carbon $ninetyDay, ?string $searchValue = null, $branchId = null)
    {
        $todayStr     = $today->toDateTimeString();
        $ninetyDayStr = $ninetyDay->toDateTimeString();

        $customerNameSql = "TRIM(CONCAT(COALESCE(customers.name, ''), ' ', COALESCE(customers.lname, '')))";

        $searchColumns = [
            'vehicles.license_plate',
            'customers.name',
            'customers.lname',
        ];

        $query = Vehicles::select(
            'vehicles.id',
            'vehicles.license_plate',
            'vehicles.call_log',
            DB::raw("CASE WHEN vehicles.call_log BETWEEN '{$todayStr}' AND '{$ninetyDayStr}' THEN vehicles.call_log_details ELSE NULL END as call_log_details"),
            DB::raw("{$customerNameSql} as customer_name"),
            'customers.phone_number1 as phone_number',
            'vehicles.notificate_tax',
            DB::raw("(SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id AND type = 'prb') as prb_expiry_date"),
            DB::raw("(SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id AND type = 'insurances') as insurance_expiry_date"),
            DB::raw("CASE WHEN vehicles.notificate_tax BETWEEN '{$todayStr}' AND '{$ninetyDayStr}' THEN true ELSE false END as notificate_tax_status"),
            DB::raw("CASE WHEN (SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id AND type = 'prb') BETWEEN '{$todayStr}' AND '{$ninetyDayStr}' THEN true ELSE false END as prb_expiry_date_status"),
            DB::raw("CASE WHEN (SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id AND type = 'insurances') BETWEEN '{$todayStr}' AND '{$ninetyDayStr}' THEN true ELSE false END as insurance_expiry_date_status"),
            DB::raw("CASE WHEN vehicles.call_log BETWEEN '{$todayStr}' AND '{$ninetyDayStr}' THEN true ELSE false END as call_log_status")
        )
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('insurances_renewals', 'vehicles.id', '=', 'insurances_renewals.vehicle_id')
            ->where(function ($query) use ($todayStr, $ninetyDayStr) {
                $query->whereBetween('vehicles.notificate_tax', [$todayStr, $ninetyDayStr])
                    ->orWhere(function ($q) use ($todayStr, $ninetyDayStr) {
                        $q->where('insurances_renewals.type', 'prb')
                            ->whereBetween('insurances_renewals.date_end', [$todayStr, $ninetyDayStr]);
                    })
                    ->orWhere(function ($q) use ($todayStr, $ninetyDayStr) {
                        $q->where('insurances_renewals.type', 'insurances')
                            ->whereBetween('insurances_renewals.date_end', [$todayStr, $ninetyDayStr]);
                    });
            })
            ->havingRaw('notificate_tax_status = true OR prb_expiry_date_status = true OR insurance_expiry_date_status = true')
            ->distinct();

        if (! empty($searchValue)) {
            $term = '%' . $searchValue . '%';
            $query->where(function ($query) use ($searchColumns, $customerNameSql, $term) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', $term);
                }
                $query->orWhereRaw("{$customerNameSql} LIKE ?", [$term]);
            });
        }

        $branchId = trim((string) ($branchId ?? ''));
        if ($branchId !== '') {
            $query->where(function ($w) use ($branchId) {
                $w->whereExists(function ($sub) use ($branchId) {
                    $sub->select(DB::raw(1))
                        ->from('inspection_vehicles')
                        ->join('inspections', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
                        ->whereColumn('inspection_vehicles.vehicle_id', 'vehicles.id')
                        ->where('inspections.branch_id', $branchId)
                        ->whereNull('inspection_vehicles.deleted_at')
                        ->whereNull('inspections.deleted_at');
                })->orWhereExists(function ($sub) use ($branchId) {
                    $sub->select(DB::raw(1))
                        ->from('inspections')
                        ->whereColumn('inspections.vehicle_id', 'vehicles.id')
                        ->where('inspections.branch_id', $branchId)
                        ->whereNull('inspections.deleted_at');
                });
            });
        }

        return $query;
    }

    public function Getpage_Extent_customer(Request $request) // notifications
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $columns = [
            'vehicles.id',
            'vehicles.license_plate',
            'customer_name',
            'province_name',
            'phone_number',
            'vehicles.notificate_tax',
            'prb_expiry_date',
            'insurance_expiry_date',
        ];

        $today     = Carbon::now();
        $ninetyDay = $today->copy()->addDays(90);
        if ($request->input('start_date') && $request->input('end_date')) {
            $today     = Carbon::parse($request->input('start_date'));
            $ninetyDay = Carbon::parse($request->input('end_date'));
        }

        $branchId = $request->input('branch_id', null);
        $query = $this->extentCustomerNotificationsQuery($today, $ninetyDay, $search['value'] ?? null, $branchId);

        $orderBy = $columns[$order[0]['column']] ?? 'vehicles.id';
        $query->orderBy($orderBy, $order[0]['dir']);

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function downloadExcel_extent_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $today     = Carbon::parse($request->input('start_date'));
        $ninetyDay = Carbon::parse($request->input('end_date'));

        $branchId = $request->input('branch_id', null);
        $rows = $this->extentCustomerNotificationsQuery($today, $ninetyDay, null, $branchId)
            ->orderBy('vehicles.license_plate')
            ->get();

        try {
            return Excel::download(new ExtentCustomerExport($rows), 'ติดตามลูกค้า.xlsx');
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function reportExportPDFextent_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $today     = Carbon::parse($request->input('start_date'));
        $ninetyDay = Carbon::parse($request->input('end_date'));

        $branchId = $request->input('branch_id', null);
        $rows = $this->extentCustomerNotificationsQuery($today, $ninetyDay, null, $branchId)
            ->orderBy('vehicles.license_plate')
            ->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4-l',
            'default_font_size' => 12,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
        ]);

        $mpdf->SetTitle('ติดตามลูกค้า');
        $mpdf->AddPage();

        $rangeText = $today->copy()->addYears(543)->format('d/m/Y') . ' - ' . $ninetyDay->copy()->addYears(543)->format('d/m/Y');
        $html      = '<h3 style="text-align:center;">รายงานติดตามลูกค้า (เตือนต่ออายุ)</h3>';
        $html .= '<p style="text-align:center;">ช่วงวันที่ ' . $rangeText . '</p>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:12px;">';
        $html .= '<thead><tr>
            <th>ทะเบียน</th><th>ชื่อลูกค้า</th><th>เบอร์โทร</th>
            <th>วันหมดภาษี</th><th>วันหมด พ.ร.บ.</th><th>วันหมดประกัน</th><th>โทรล่าสุด</th>
        </tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . e($row->license_plate ?? '-') . '</td>';
            $html .= '<td>' . e($row->customer_name ?? '-') . '</td>';
            $html .= '<td>' . e($row->phone_number ?? '-') . '</td>';
            $html .= '<td>' . e($row->notificate_tax ?? '-') . '</td>';
            $html .= '<td>' . e($row->prb_expiry_date ?? '-') . '</td>';
            $html .= '<td>' . e($row->insurance_expiry_date ?? '-') . '</td>';
            $html .= '<td>' . e($row->call_log ?? '-') . '</td>';
            $html .= '</tr>';
        }

        if ($rows->isEmpty()) {
            $html .= '<tr><td colspan="7" style="text-align:center;">ไม่มีข้อมูล</td></tr>';
        }

        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');
        $headers    = [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="extent_customer.pdf"',
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function GetpageCar_tax_(Request $request) // * okay
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $tax_vehicle = $request->input('tax_vehicle', null);
        $startDate   = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate     = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId    = $request->input('branch_id', null);

        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspections.tax_vehicle',
            'inspections.paytrue',
            'inspections.result',
            'inspections.status',
            'inspection_vehicles.type_document',
            'vehicles.license_plate',
            'provinces.name_th_prefix',
            'customers.name',
            'vehicles.notificate_tax',
            'vehicles.registration_date',
            'vehicle_inspection_types.name as inspection_type_name',
            'vehicle_inspection_types.code as inspection_type_code',
            'vehicle_inspection_types.type as inspection_type_type',
        ];

        $searchColumns = [
            'inspections.no',
        ];

        $query = Inspections::select($columns)
            ->leftJoin('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('vehicle_inspection_types', 'vehicles.vehicle_inspection_types_id', '=', 'vehicle_inspection_types.id')
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->when($branchId || $tax_vehicle !== null, function ($query) use ($branchId, $tax_vehicle) {
                if ($branchId) {
                    $query->where('inspections.branch_id', $branchId);
                }
                if ($tax_vehicle !== null) {
                    $query->where('inspections.tax_vehicle', $tax_vehicle);
                }
            })
            ->whereHas('serviceTransactions', function ($query) {
                $query->where('service_id', 3);
                $query->where('status', true);
            })
            ->with(['serviceTransactions' => function ($query) {
                $query->select('inspections_id', 'service_id', 'service_price')
                    ->whereIn('service_id', [3, 4, 6])
                    ->with('service:id,name');
            }]);

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere(DB::raw($column), 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderByColumn  = $columns[$order[0]['column']] ?? 'inspections.no';
        $orderDirection = $order[0]['dir'] ?? 'asc';
        $query->orderBy(DB::raw($orderByColumn), $orderDirection);

        $items = $query->paginate($length, ['*'], 'page', $page);

        $items->getCollection()->transform(function ($item) {
            $totalServicePrice = $item->serviceTransactions->sum('service_price');
            $item->serviceTransactions->each(function ($transaction) {
                $transaction->service_name = $transaction->service->name;
            });
            $item->total_service_tax = number_format($totalServicePrice, 0);
            $item->paytrue           = number_format($item->paytrue, 0);
            return $item;
        });

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageCar_tax(Request $request) // * okay
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $tax_vehicle = $request->input('tax_vehicle', null);
        $startDate   = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate     = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId    = $request->input('branch_id', null);

        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspections.tax_vehicle',
            'inspections.paytrue',
            'inspections.result',
            'inspections.status',
            'inspections.commission',
            'inspections.transaction_status',
            'inspection_vehicles.type_document',
            'vehicles.license_plate',
            'provinces.name_th_prefix',
            'provinces.name_th',
            DB::raw("TRIM(CONCAT(COALESCE(customers.name,''), ' ', COALESCE(customers.lname,''))) as customer_name"),
            'vehicles.id as vehicle_id',
            'vehicles.notificate_tax',
            'vehicles.registration_date',
            'vehicles.tax_renewal_date',
            'vehicle_inspection_types.code',
            'vehicle_inspection_types.type',
            'vehicle_inspection_types.name as inspection_type_name',
            // 'vechicle_services_transactions.service_price as service_price',
            DB::raw('SUM(vechicle_services_transactions.service_price) as service_price'),
        ];
        $searchable = [
            'inspections.no',
            'vehicles.license_plate',
        ];

        $query = DB::table('inspections')
            ->select($columns)
            ->leftjoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
        // ->leftjoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')->where('service_id', 3)
            ->join('vechicle_services_transactions', function ($join) {
                $join->on('inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
                    ->whereIn('vechicle_services_transactions.service_id', [3, 4]);
            })
            ->leftjoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->leftjoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->leftjoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftjoin('vehicle_inspection_types', 'vehicles.vehicle_inspection_types_id', '=', 'vehicle_inspection_types.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('vehicles.deleted_at')
            ->whereNull('provinces.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereNull('vehicle_inspection_types.deleted_at')
            ->where('inspections.status', '!=', 'cancel')
            ->whereNull('inspections.deleted_at')
        // ->whereNotNull('vechicle_services_transactions.id')
            ->groupBy(
                'inspections.id',
                'inspections.no',
                'inspections.date',
                'inspections.tax_vehicle',
                'inspections.paytrue',
                'inspections.result',
                'inspections.status',
                'inspections.commission',
                'inspections.transaction_status',
                'inspection_vehicles.type_document',
                'vehicles.license_plate',
                'provinces.name_th_prefix',
                'provinces.name_th',
                'customers.name',
                'customers.lname',
                'vehicles.notificate_tax',
                'vehicles.registration_date',
                'vehicles.tax_renewal_date',
                'vehicle_inspection_types.code',
                'vehicle_inspection_types.type',
                'vehicle_inspection_types.name',
                'vehicles.id',
            );

        if ($branchId) {
            $query->where('inspections.branch_id', $branchId);
        }
        if ($tax_vehicle !== null) {
            $query->where('inspections.tax_vehicle', $tax_vehicle);
        }
        if ($startDate && $endDate) {
            $query->whereBetween('vehicles.tax_renewal_date', [$startDate, $endDate]);
        }

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchable) {
                foreach ($searchable as $column) {
                    $query->orWhere(DB::raw($column), 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query
            ->orderBy($columns[$order[0]['column']], $order[0]['dir'])
            ->paginate($length, ['*'], 'page', $page);

        $this->enrichCarTaxManagePageRows($items);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageEMSa(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $ems       = $request->ems;

        $columns = [
            'inspections.no',
            'inspections.EMS',
            'vehicles.license_plate',
            'vehicle_inspection_types.name as inspection_type_name',
            'customers.name as customer_name',
            'customer_addresses.address',
            'provinces.name_th_prefix as province_name',
            'districts.name_th_prefix as district_name',
            'sub_districts.name_th_prefix as subdistrict_name',
            'customers.phone_number1',
        ];
        $searchcolumns = [
            'inspections.no',
            'inspections.EMS',
            'vehicles.license_plate',
            'vehicle_inspection_types.name as inspection_type_name',
            'customers.name as customer_name',
            'customer_addresses.address',
            'customers.phone_number1',
        ];

        $query = Inspections::select(
            'inspections.no',
            'inspections.EMS',
            'vehicles.license_plate',
            'vehicle_inspection_types.name as inspection_type_name',
            'customers.name as customer_name',
            'customer_addresses.address',
            'provinces.name_th_prefix as province_name',
            'districts.name_th_prefix as district_name',
            'sub_districts.name_th_prefix as subdistrict_name',
            'customers.phone_number1'
        )
            ->leftJoin('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('vehicle_inspection_types', 'vehicles.vehicle_inspection_types_id', '=', 'vehicle_inspection_types.id')
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('customer_addresses', 'customers.id', '=', 'customer_addresses.customer_id')
            ->leftJoin('provinces', 'customer_addresses.province_id', '=', 'provinces.id')
            ->leftJoin('districts', 'customer_addresses.district_id', '=', 'districts.id')
            ->leftJoin('sub_districts', 'customer_addresses.subdistrict_id', '=', 'sub_districts.id')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('inspections.branch_id', $branchId);
            })
            ->whereHas('serviceTransactions', function ($query) {
                $query->where('service_id', 15)
                    ->where('status', true);
            });

        if ($ems !== null) {
            if ($ems == 1) {
                $query->whereNotNull('inspections.EMS')
                    ->where('inspections.EMS', '!=', '');
            } elseif ($ems == 0) {
                $query->where(function ($query) {
                    $query->whereNull('inspections.EMS')
                        ->orWhere('inspections.EMS', '');
                });
            }
        }

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        $orderBy = $columns[$order[0]['column']] ?? 'inspections.no';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumns) {
                foreach ($searchcolumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        $id = ($page - 1) * $length + 1;
        foreach ($items as $item) {
            $item->address = trim("{$item->address} {$item->province_name} {$item->district_name} {$item->subdistrict_name}");
            unset($item->province_name, $item->district_name, $item->subdistrict_name);

            $item->id = $id++;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetPageEMSb(Request $request) // * วิธี
    {
        $length = $request->input('length', 10);
        $order  = $request->input('order', [['column' => 0, 'dir' => 'asc']]);
        $search = $request->input('search', ['value' => '']);
        $start  = $request->input('start', 0);
        $page   = intval($start / $length) + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $ems       = $request->input('ems'); // Should be 0 or 1

        $columns = [
            'id',
            'no',
            'EMS',
            'license_plate',
            'inspection_type_name',
            'customer_name',
            'address',
            'phone_number1',
        ];

        $searchColumns = [
            'no',
            'EMS',
            'license_plate',
            'inspection_type_name',
            'customer_name',
            'address',
            'phone_number1',
        ];

        $query = Inspections::with([
            'vehicles'                                           => function ($q) {
                $q->select('id', 'license_plate', 'vehicle_inspection_types_id', 'customer_id');
            },
            'vehicles.vehicleInspectionTypes'                    => function ($q) {
                $q->select('id', 'name');
            },
            'vehicles.customers'                                 => function ($q) {
                $q->select('id', 'name', 'phone_number1');
            },
            'vehicles.customers.Customer_Addresses'              => function ($q) {
                $q->select('customer_id', 'address', 'province_id', 'district_id', 'subdistrict_id');
            },
            'vehicles.customers.Customer_Addresses.provinces'    => function ($q) {
                $q->select('id', 'name_th_prefix');
            },
            'vehicles.customers.Customer_Addresses.districts'    => function ($q) {
                $q->select('id', 'name_th_prefix');
            },
            'vehicles.customers.Customer_Addresses.subDistricts' => function ($q) {
                $q->select('id', 'name_th_prefix');
            },
        ])
            ->select('id', 'no', 'EMS', 'vehicle_id', 'date');

        if ($ems !== null) {
            if ($ems == 1) {
                $query->whereNotNull('EMS')
                    ->where('EMS', '!=', '');
            } elseif ($ems == 0) {
                $query->where(function ($query) {
                    $query->whereNull('EMS')
                        ->orWhere('EMS', '');
                });
            }
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if (! empty($search['value'])) {
            $query->where(function ($q) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }
        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        $items = $query->paginate($length, ['*'], 'page', $page);

        $id = ($page - 1) * $length + 1;
        foreach ($items as $item) {
            $addresses      = $item->vehicles->customers->Customer_Addresses;
            $addressStrings = [];
            foreach ($addresses as $address) {
                $fullAddress      = trim("{$address->address} {$address->provinces->name_th_prefix} {$address->districts->name_th_prefix} {$address->subDistricts->name_th_prefix}");
                $addressStrings[] = $fullAddress;
            }
            $item->address = implode(', ', $addressStrings);

            $item->license_plate        = $item->vehicles->license_plate;
            $item->inspection_type_name = $item->vehicle->vehicleInspectionType->name ?? '';
            $item->customer_name        = $item->vehicle->customer->name ?? '';
            $item->phone_number1        = $item->vehicle->customer->phone_number1 ?? '';

            $item->id = $id++;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetPageEMS(Request $request)
    {
        $length = $request->input('length', 10);
        $order  = $request->input('order', [['column' => 0, 'dir' => 'asc']]);
        $search = $request->input('search', ['value' => '']);
        $start  = $request->input('start', 0);
        $page   = intval($start / $length) + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $ems = $request->input('ems'); // Should be 0 or 1

        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no',
            'address_ems.address',
            'address_ems.name',
            'address_ems.photo',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.vehicle_inspection_types as vehicle_inspection_types',
            'customers.name',
            'customers.lname as lname',            // ⬅️ เพิ่มนามสกุลเพื่อเอามารวม
            'inspections.ems',
            'customers.phone_number1 as phone',
            'provinces.id as province_id',
            'provinces.name_th_prefix as province_name',
            'districts.id as district_id',
            'districts.name_th_prefix as district_name',
            'sub_districts.id as subdistrict_id',
            'sub_districts.name_th_prefix as sub_district_name',
            'sub_districts.zip_code as zip_code',
            'vechicle_services_transactions.service_id',
        ];

        $searchColumns = [
            'inspections.no',
            'inspections.ems',
            'inspection_vehicles.license_plate',
            'customers.phone_number1',
        ];
        // $query = Inspections::query()
        //     ->select($columns)
        //     ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
        //     ->leftJoin('address_ems', 'inspections.id', '=', 'address_ems.inspection_id')
        //     ->leftJoin('vehicle_inspection_types', 'vehicles.vehicle_inspection_types_id', '=', 'vehicle_inspection_types.id')
        //     ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
        //     ->leftJoin('provinces', 'address_ems.province_id', '=', 'provinces.id')
        //     ->leftJoin('districts', 'address_ems.district_id', '=', 'districts.id')
        //     ->leftJoin('sub_districts', 'address_ems.subdistrict_id', '=', 'sub_districts.id')
        //     ->Join('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
        //     ->Join('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
        //     // ->distinct()
        //     ->where('vechicle_services_transactions.service_id', 16);
        // ->where('vechicle_services_transactions.status', true);
        // ->distinct()
        // ->whereHas('serviceTransactions', function ($query) {
        //     $query->where('service_id', 16);
        //     $query->where('status', true);
        // });
        // หนึ่งบิลหนึ่งแถว: join หลายตารางเดิมทำให้ซ้ำ (หลายคัน / หลาย vst ที่แก้บิล / หลาย address_ems)
        $query = Inspections::query()
            ->select($columns)
            ->leftJoin('address_ems', function ($join) {
                $join->on('inspections.id', '=', 'address_ems.inspection_id')
                    ->whereNull('address_ems.deleted_at')
                    ->whereRaw('address_ems.id = (
                        SELECT MAX(ae.id) FROM address_ems ae
                        WHERE ae.inspection_id = inspections.id AND ae.deleted_at IS NULL
                    )');
            })
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('provinces', 'address_ems.province_id', '=', 'provinces.id')
            ->leftJoin('districts', 'address_ems.district_id', '=', 'districts.id')
            ->leftJoin('sub_districts', 'address_ems.subdistrict_id', '=', 'sub_districts.id')
            ->leftJoin('inspection_vehicles', function ($join) {
                $join->on('inspections.id', '=', 'inspection_vehicles.inspection_id')
                    ->whereRaw('inspection_vehicles.id = (
                        SELECT iv.id FROM inspection_vehicles iv
                        INNER JOIN vechicle_services_transactions vst ON iv.id = vst.inspection_vehicle_id
                        WHERE iv.inspection_id = inspections.id
                          AND vst.service_id = 16
                          AND vst.deleted_at IS NULL
                        ORDER BY iv.id ASC
                        LIMIT 1
                    )');
            })
            ->leftJoin('vechicle_services_transactions', function ($join) {
                $join->on('inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
                    ->where('vechicle_services_transactions.service_id', 16)
                    ->whereNull('vechicle_services_transactions.deleted_at')
                    ->whereRaw('vechicle_services_transactions.id = (
                        SELECT MAX(vst.id) FROM vechicle_services_transactions vst
                        WHERE vst.inspection_vehicle_id = inspection_vehicles.id
                          AND vst.service_id = 16
                          AND vst.deleted_at IS NULL
                    )');
            })
            ->where('inspections.status', '!=', 'cancel')
            ->whereNull('inspections.deleted_at')
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('inspection_vehicles as iv')
                    ->join('vechicle_services_transactions as vst', 'iv.id', '=', 'vst.inspection_vehicle_id')
                    ->whereColumn('iv.inspection_id', 'inspections.id')
                    ->where('vst.service_id', 16)
                    ->whereNull('vst.deleted_at');
            });

        if ($ems !== null) {
            if ($ems == 1) {
                $query->whereNotNull('inspections.EMS')
                    ->where('inspections.EMS', '!=', '');
            } elseif ($ems == 0) {
                $query->where(function ($query) {
                    $query->whereNull('inspections.EMS')
                        ->orWhere('inspections.EMS', '');
                });
            }
        }

        $branchId = trim((string) ($branchId ?? ''));
        if ($branchId !== '') {
            $query->where('inspections.branch_id', $branchId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! empty($search['value'])) {
            $query->where(function ($q) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderBy = $columns[$order[0]['column']] ?? 'inspections.id';
        $query->orderBy($orderBy, $order[0]['dir']);

        $items = $query->paginate($length, ['*'], 'page', $page);

        foreach ($items as $item) {
            $item->photo = $item->photo ? url($item->photo) : url('image/No_Image_Available.jpg');

            // ✅ รวม customers.name + customers.lname ไว้ใน name
            $item->name = trim(implode(' ', array_filter([$item->name ?? '', $item->lname ?? ''])));
            unset($item->lname); // เอาออกไม่ให้สับสน (ถ้าอยากคงไว้ก็ลบบรรทัดนี้)
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetPage_InsurancesRenewals_(Request $request)
    {
        $length = $request->input('length', 10);
        $order  = $request->input('order', [['column' => 0, 'dir' => 'asc']]);
        $search = $request->input('search', ['value' => '']);
        $start  = $request->input('start', 0);
        $page   = intval($start / $length) + 1;

        $startDate            = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate              = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $policy_number_filter = $request->input('policy_number'); // 0 or 1

        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no',
            'vehicles.license_plate',
            'insurance_names.name',
            'insurances_renewals.id as id_insuran',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.amount_paid',
            'insurances_renewals.policy_number',
            'insurances_renewals.insurance_renewal_type_id',
            'insurances_renewals.policy_status',
            'insurances_renewals.remark',
            'insurance_renewal_types.name as insurance_renewal_type_name',
            'customers.phone_number1 as customer_phone_number',
            'inspections.created_at',
            'inspections.updated_at',
        ];

        $searchColumns = [
            'insurances_renewals.policy_number',
            'vehicles.license_plate',
            'inspections.no',
            'customers.phone_number1',
        ];

        $query = Inspections::query()
            ->select($columns)
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('insurances_renewals', function ($join) {
                $join->on('vehicles.id', '=', 'insurances_renewals.vehicle_id')
                    ->where('insurances_renewals.id', '=', function ($subQuery) {
                        $subQuery->select('id')
                            ->from('insurances_renewals')
                            ->whereColumn('vehicle_id', 'vehicles.id')
                            ->orderBy('date_end', 'desc')
                            ->limit(1);
                    });
            })
            ->leftJoin('insurance_names', 'insurances_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->leftJoin('insurance_renewal_types', 'insurances_renewals.insurance_renewal_type_id', '=', 'insurance_renewal_types.id')
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->distinct();

        $query->whereHas('serviceTransactions', function ($q) {
            $q->where('service_id', 6)
                ->where('status', true);
        });

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if ($policy_number_filter !== null) {
            if ($policy_number_filter == 0) {
                $query->whereNull('insurances_renewals.policy_number');
            } elseif ($policy_number_filter == 1) {
                $query->whereNotNull('insurances_renewals.policy_number');
            }
        }

        if (! empty($search['value'])) {
            $query->where(function ($q) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderByColumnIndex = $order[0]['column'] ?? 0;
        $orderByDirection   = $order[0]['dir'] ?? 'asc';
        $orderBy            = $columns[$orderByColumnIndex] ?? 'inspections.id';
        $query->orderBy($orderBy, $orderByDirection);

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetPage_InsurancesRenewals(Request $request)
    {
        $length = $request->input('length', 10);
        $order  = $request->input('order', [['column' => 0, 'dir' => 'asc']]);
        $search = $request->input('search', ['value' => '']);
        $start  = $request->input('start', 0);
        $page   = intval($start / $length) + 1;

        $startDate            = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate              = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $policy_number_filter = $request->input('policy_number'); // 0 or 1
        $branchId  = $request->input('branch_id', null);
        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.id as inspection_vehicle_id',
            'vechicle_services_transactions.id as vechicle_services_transaction_id',
            'customers.phone_number1 as customer_phone_number',
            'insurance_renewal_types.name as insurance_renewal_type_name',
            'insurances_renewals.id as id_insuran',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.type',
            'insurances_renewals.amount_paid',
            'insurances_renewals.policy_number',
            'insurances_renewals.policy_status',
            'insurances_renewals.insurance_renewal_type_id',
            'insurances_renewals.remark',
            'insurances_renewals.insurance_names_id',
            'insurance_names.name',
        ];

        $searchColumns = [
            'inspection_vehicles.license_plate',
            'inspections.no',
            'insurance_names.name',
        ];

        $query = Inspections::query()
            ->select($columns)
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('insurances_renewals', 'vechicle_services_transactions.id', '=', 'insurances_renewals.vechicle_services_transaction_id')->where('service_id', 7)
            ->leftJoin('insurance_renewal_types', 'insurances_renewals.insurance_renewal_type_id', '=', 'insurance_renewal_types.id')
            ->leftJoin('insurance_names', 'insurances_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('insurances_renewals.deleted_at')
            ->whereNull('insurance_renewal_types.deleted_at')
            ->whereNull('insurance_names.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereNull('inspections.deleted_at')
            ->where('inspections.status', '!=', 'cancel')
            ->where('inspections.branch_id', $branchId)
            ->distinct();

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if ($policy_number_filter !== null) {
            if ($policy_number_filter == 0) {
                $query->whereNull('insurances_renewals.policy_number');
            } elseif ($policy_number_filter == 1) {
                $query->whereNotNull('insurances_renewals.policy_number');
            }
        }

        $insuranceName = $request->input('insurance_name');
        if ($insuranceName !== null && $insuranceName !== '') {
            $query->where('insurance_names.name', 'like', '%' . $insuranceName . '%');
        }

        if (! empty($search['value'])) {
            $query->where(function ($q) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderByColumnIndex = $order[0]['column'] ?? 0;
        $orderByDirection   = $order[0]['dir'] ?? 'asc';
        $orderBy            = $columns[$orderByColumnIndex] ?? 'inspections.id';
        $query->orderBy($orderBy, $orderByDirection);

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetPage_PrbRenewals_(Request $request)
    {
        $length = $request->input('length', 10);
        $order  = $request->input('order', [['column' => 0, 'dir' => 'asc']]);
        $search = $request->input('search', ['value' => '']);
        $start  = $request->input('start', 0);
        $page   = intval($start / $length) + 1;

        $startDate            = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate              = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $policy_number_filter = $request->input('policy_number'); // 0 or 1

        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no as bill_number',
            'vehicles.license_plate',
            'insurance_names.name as insurance_name',
            'prb_renewals.id as id_prb',
            'prb_renewals.date_start',
            'prb_renewals.date_end',
            'prb_renewals.amount_paid',
            'prb_renewals.stamp',
            'prb_renewals.tax',
            'prb_renewals.policy_number',
            'prb_renewals.policy_status',
            'prb_renewals.remark',
            'customers.phone_number1 as customer_phone_number',
            'inspections.created_at',
            'inspections.updated_at',
        ];

        $searchColumns = [
            'prb_renewals.policy_number',
            'vehicles.license_plate',
            'inspections.no',
            'customers.phone_number1',
        ];

        $query = Inspections::query()
            ->select($columns)
            ->leftjoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftjoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')->where('service_id', 2)
            ->leftjoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('prb_renewals', function ($join) {
                $join->on('vehicles.id', '=', 'prb_renewals.vehicle_id')
                    ->where('prb_renewals.id', '=', function ($subQuery) {
                        $subQuery->select('id')
                            ->from('prb_renewals')
                            ->whereColumn('vehicle_id', 'vehicles.id')
                            ->orderBy('date_end', 'desc')
                            ->limit(1);
                    });
            })
            ->leftJoin('insurance_names', 'prb_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->distinct();

        // Apply date filters based on the inspections.date field
        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        // Apply the policy_number_filter
        if ($policy_number_filter !== null) {
            if ($policy_number_filter == 0) {
                $query->whereNull('prb_renewals.policy_number');
            } elseif ($policy_number_filter == 1) {
                $query->whereNotNull('prb_renewals.policy_number');
            }
        }

        // Apply search filters if any
        if (! empty($search['value'])) {
            $query->where(function ($q) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        // Determine the column to sort by
        $orderByColumnIndex = $order[0]['column'] ?? 0;
        $orderByDirection   = $order[0]['dir'] ?? 'asc';
        $orderBy            = $columns[$orderByColumnIndex] ?? 'inspections.id';
        $query->orderBy($orderBy, $orderByDirection);

        // Paginate the results
        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetPage_PrbRenewals(Request $request)
    {
        $length = $request->input('length', 10);
        $order  = $request->input('order', [['column' => 0, 'dir' => 'asc']]);
        $search = $request->input('search', ['value' => '']);
        $start  = $request->input('start', 0);
        $page   = intval($start / $length) + 1;

        $startDate            = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate              = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $policy_number_filter = $request->input('policy_number'); // 0 or 1
        $branchId  = $request->input('branch_id', null);

        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no as bill_number',
            'inspection_vehicles.license_plate',
            'insurance_names.name as insurance_name',
            'insurances_renewals.id as id_prb',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.amount_paid',
            'insurances_renewals.stamp',
            'insurances_renewals.tax',
            'insurances_renewals.policy_number',
            'insurances_renewals.policy_status',
            'insurances_renewals.remark',
            'insurances_renewals.type',
            'insurances_renewals.insurance_names_id',
            'customers.phone_number1 as customer_phone_number',
            'inspections.created_at',
            'inspections.updated_at',
        ];

        $searchColumns = [
            'insurances_renewals.policy_number',
            'inspection_vehicles.license_plate',
            'inspections.no',
            'customers.phone_number1',
            'insurance_names.name',
        ];

        $query = Inspections::query()
            ->select($columns)
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('insurances_renewals', 'vechicle_services_transactions.id', '=', 'insurances_renewals.vechicle_services_transaction_id')->where('service_id', 2)
            ->leftJoin('insurance_renewal_types', 'insurances_renewals.insurance_renewal_type_id', '=', 'insurance_renewal_types.id')
            ->leftJoin('insurance_names', 'insurances_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('insurances_renewals.deleted_at')
            ->whereNull('insurance_renewal_types.deleted_at')
            ->whereNull('insurance_names.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereNull('inspections.deleted_at')
            ->where('inspections.status', '!=', 'cancel')
            ->where('inspections.branch_id', $branchId);

        $insurance_name = $request->input('insurance_name');

        if (!empty($insurance_name)) {
            $query->where('insurance_names.name', 'like', '%' . $insurance_name . '%');
        }

        // Apply date filters based on the inspections.date field
        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        // Apply the policy_number_filter
        if ($policy_number_filter !== null) {
            if ($policy_number_filter == 0) {
                $query->whereNull('insurances_renewals.policy_number');
            } elseif ($policy_number_filter == 1) {
                $query->whereNotNull('insurances_renewals.policy_number');
            }
        }

        // Apply search filters if any
        if (! empty($search['value'])) {
            $query->where(function ($q) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        // Determine the column to sort by
        $orderByColumnIndex = $order[0]['column'] ?? 0;
        $orderByDirection   = $order[0]['dir'] ?? 'asc';
        $orderBy            = $columns[$orderByColumnIndex] ?? 'inspections.id';
        $query->orderBy($orderBy, $orderByDirection);

        // Paginate the results
        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function Getpage_Expense_report(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $columns = [
            'date',
            'name',
            'price',
        ];

        $query = Cost::select('date', 'name', 'price')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderBy = $columns[$order[0]['column']] ?? 'date';
        $query->orderBy($orderBy, $order[0]['dir']);

        $items = $query->paginate($length, ['*'], 'page', $page);

        $id = ($page - 1) * $length + 1;
        foreach ($items as $item) {
            $item->id = $id++;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function Getpagecheck_gas_(Request $request)
    {
        $length   = $request->length;
        $order    = $request->order;
        $search   = $request->search;
        $start    = $request->start;
        $page     = ($start / $length) + 1;
        $data_gas = $request->data_gas; // 0 or 1

        $orderColumn = [
            'inspections.id',
            'inspections.no',
            'vehicles.license_plate',
            'provinces.name_th_prefix',
            'customers.name',
            'vehicles.registration_date',
            'inspections.date',
            'inspections.service_or',
            'inspections.install_no',
            'inspections.CHA_NO',
            'inspections.photo_vehicle_gas',
            'employees.first_name',
            'employees.last_name',
            'service_transactions.service_price',
            'inspections.result',
            'inspections.status',
        ];

        $searchColumn = [
            'vehicles.license_plate',
            // 'customers.name',
            // 'vehicles.registration_date',
            // 'inspections.date',
            // 'inspections.service_or',
            // 'inspections.result',
            // 'inspections.status',
        ];

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $baseQuery = Inspections::query()
            ->leftjoin('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->leftjoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftjoin('employees', 'inspections.engineer_id', '=', 'employees.id')
            ->leftjoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->leftjoin('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->leftjoin('services', 'service_transactions.service_id', '=', 'services.id')
            ->select($orderColumn);
        $query1 = (clone $baseQuery)->where('service_transactions.service_id', 8)
            ->where('service_transactions.status', true);

        $query2 = (clone $baseQuery)->where('service_transactions.service_id', 11)
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('service_transactions as st')
                    ->whereColumn('st.inspections_id', 'service_transactions.inspections_id')
                    ->where('st.service_id', 8)
                    ->where('st.status', true);
            });

        if ($data_gas !== null) {
            if ($data_gas == 1) {
                $query1->whereNotNull('inspections.service_or')->where('inspections.service_or', '!=', '');
                $query2->whereNotNull('inspections.service_or')->where('inspections.service_or', '!=', '');
            } elseif ($data_gas == 0) {
                $query1->where(function ($q) {
                    $q->whereNull('inspections.service_or')->orWhere('inspections.service_or', '');
                });
                $query2->where(function ($q) {
                    $q->whereNull('inspections.service_or')->orWhere('inspections.service_or', '');
                });
            }
        }
        $query = $query1->union($query2);

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! is_null($branchId)) {
            $query->where('inspections.branch_id', $branchId);
        }

        if (! empty($search['value'])) {
            $searchValue = $search['value'];
            $query->where(function ($query) use ($searchValue, $searchColumn) {
                foreach ($searchColumn as $column) {
                    $query->where($column, 'like', '%' . $searchValue . '%');
                }
            });
        }

        $orderBy = $Column[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        $items = $query->distinct()->paginate($length, ['*'], 'page', $page);

        $num = ($page - 1) * $length + 1;
        foreach ($items as $item) {
            $item->num = $num++;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function Getpagecheck_gas(Request $request)
    {
        $length   = $request->length;
        $order    = $request->order;
        $search   = $request->search;
        $start    = $request->start;
        $page     = ($start / $length) + 1;
        $data_gas = $request->data_gas; // 0 or 1

        $orderColumn = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspections.branch_id',
            'vehicles.license_plate',
            'provinces.name_th_prefix',
            'inspections.service_or',
            'inspections.CHA_NO',
            'inspections.photo_vehicle_gas',
            'employees.first_name',
            'employees.last_name',
            'customers.name as customer_name',
            'vehicles.registration_date',
            DB::raw('GROUP_CONCAT(DISTINCT install_nos.install_no) as install_no_group'),
        ];

        $searchColumn = [
            'vehicles.license_plate',
        ];

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        // สร้าง base query โดยไม่เรียก get() ก่อน
        $baseQuery = DB::table('inspections')
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->leftJoin('install_nos', 'inspections.id', '=', 'install_nos.inspection_id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('employees', 'inspections.engineer_id', '=', 'employees.id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->whereNull('inspections.deleted_at')
            ->whereNull('vehicles.deleted_at')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('install_nos.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('employees.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereIn('inspection_vehicles.id', function ($query) {
                $query->select('inspection_vehicle_id')
                    ->from('vechicle_services_transactions')
                    ->whereIn('service_id', [8, 9, 10, 11, 12]);
            })
            ->select($orderColumn)
            ->groupBy(
                'inspections.id',
                'inspections.no',
                'inspections.date',
                'inspections.branch_id',
                'vehicles.license_plate',
                'provinces.name_th_prefix',
                'inspections.service_or',
                'inspections.CHA_NO',
                'inspections.photo_vehicle_gas',
                'employees.first_name',
                'employees.last_name',
                'customers.name',
                'vehicles.registration_date',
            );

        // Filter data_gas
        if (! is_null($data_gas)) {
            if ($data_gas == 1) {
                $baseQuery->whereNotNull('inspections.service_or')->where('inspections.service_or', '!=', '');
            } elseif ($data_gas == 0) {
                $baseQuery->where(function ($q) {
                    $q->whereNull('inspections.service_or')
                        ->orWhere('inspections.service_or', '');
                });
            }
        }

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! is_null($branchId)) {
            $baseQuery->where('inspections.branch_id', $branchId);
        }

        if (! empty($search['value'])) {
            $searchValue = $search['value'];
            $baseQuery->where(function ($query) use ($searchValue, $searchColumn) {
                foreach ($searchColumn as $column) {
                    $query->where($column, 'like', '%' . $searchValue . '%');
                }
            });
        }

        $orderBy = $Column[$order[0]['column']] ?? 'id';
        $baseQuery->orderBy($orderBy, $order[0]['dir']);

        $items = $baseQuery->distinct()->paginate($length, ['*'], 'page', $page);

        $items->getCollection()->transform(function ($row) {
            $row->install_no = $row->install_no_group ? explode(',', $row->install_no_group) : [];
            unset($row->install_no_group);
            return $row;
        });
        foreach ($items as $item) {
            $item->photo_vehicle_gas = $item->photo_vehicle_gas ? url($item->photo_vehicle_gas) : null;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function Getpagecheck_gas2(Request $request)
    {
        $length   = $request->length;
        $order    = $request->order;
        $search   = $request->search;
        $start    = $request->start;
        $page     = ($start / $length) + 1;
        $data_gas = $request->data_gas; // 0 or 1

        $searchColumn = [
            'inspection_vehicles.license_plate',
            'inspections.no',
        ];

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $queryWithDuplicates = DB::table('check_gases')
            ->leftJoin('inspection_vehicles', 'check_gases.inspection_vehicle_id', '=', 'inspection_vehicles.id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('cha_nos', 'check_gases.id', '=', 'cha_nos.check_gas_id')
            ->leftJoin('inspections', 'inspection_vehicles.inspection_id', '=', 'inspections.id')
            ->leftJoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->where('inspections.branch_id',$branchId) //bird
            ->whereNull('check_gases.deleted_at')
            ->where(function ($query) {
                $query->whereNull('inspections.status')
                    ->orWhere('inspections.status', '!=', 'cancel')
                    ->orWhereNull('inspection_vehicles.inspection_id');
            })
            ->select([
                DB::raw('MIN(check_gases.id) as id'),
                DB::raw('MIN(check_gases.customer_no) as customer_no'),
                DB::raw('MIN(check_gases.customer_name) as customer_name'),
                DB::raw('MIN(check_gases.engineer_id) as engineer_id'),
                DB::raw('MIN(check_gases.engineer_no) as engineer_no'),
                DB::raw('MIN(check_gases.engineer_name) as engineer_name'),
                DB::raw('MIN(check_gases.service_or) as service_or'),
                DB::raw('MIN(check_gases.install_no) as install_no'),
                DB::raw('MIN(check_gases.date) as date'),
                DB::raw('MIN(check_gases.time) as time'),
                DB::raw('MIN(check_gases.photo_vehicle_gas) as photo_vehicle_gas'),
                DB::raw('MIN(check_gases.no_gas) as no_gas'),
                DB::raw('MIN(check_gases.brand_gas) as brand_gas'),
                DB::raw('MIN(inspection_vehicles.license_plate) as license_plate'),
                DB::raw('MIN(inspection_vehicles.provinces) as provinces'),
                DB::raw('MIN(inspections.no) as inspections_no'),
                DB::raw('MIN(vehicle_brands.name) as vehicle_brand_name'),
                DB::raw('MIN(vehicles.id) as vehicles_id'),
                DB::raw('GROUP_CONCAT(DISTINCT cha_nos.cha_no) as cha_no_group'),
            ])
            ->groupBy('check_gases.inspection_vehicle_id')
            ->orderBy('check_gases.date', 'DESC');

        // Filter data_gas
        if (! is_null($data_gas)) {
            if ($data_gas == 1) {
                $queryWithDuplicates->whereNotNull('check_gases.service_or')->where('check_gases.service_or', '!=', '');
            } elseif ($data_gas == 0) {
                $queryWithDuplicates->where(function ($q) {
                    $q->whereNull('check_gases.service_or')
                        ->orWhere('check_gases.service_or', '');
                });
            }
        }

        if ($startDate && $endDate) {
            $queryWithDuplicates->whereBetween('check_gases.date', [$startDate, $endDate]);
        }

        if (! empty($search['value'])) {
            $searchValue = $search['value'];
            $queryWithDuplicates->where(function ($query) use ($searchValue, $searchColumn) {
                foreach ($searchColumn as $column) {
                    $query->orWhere($column, 'like', '%' . $searchValue . '%');
                }
            });
        }

        $orderBy = $Column[$order[0]['column']] ?? 'id';
        $queryWithDuplicates->orderBy($orderBy, $order[0]['dir']);

        $items = $queryWithDuplicates->paginate($length, ['*'], 'page', $page);

        $items->getCollection()->transform(function ($row) {
            $row->cha_no = $row->cha_no_group ? explode(',', $row->cha_no_group) : [];
            unset($row->cha_no_group);
            return $row;
        });
        foreach ($items as $item) {
            $item->photo_vehicle_gas = $item->photo_vehicle_gas ? url($item->photo_vehicle_gas) : null;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageDaily_report(request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = ($start / $length) + 1;

        $orderColumn = [
            'inspections.no',
            'vehicles.license_plate',
            'customers.name',
            'services.name',
            'inspections.status',
        ];

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $query = ServiceTransactions::select(
            'inspections.no',
            'vehicles.license_plate',
            'customers.name as customer_name',
            DB::raw("CASE
                WHEN services.name LIKE '%LPG%' THEN 'LPG'
                WHEN services.name LIKE '%NGV%' THEN 'NGV'
                ELSE ''
            END AS service_name"),
            DB::raw("CASE
                WHEN inspections.status = 'open' THEN 'ค้างชำระ'
                ELSE 'ชำระแล้ว'
            END AS status")
        )
            ->join('inspections', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->join('services', 'service_transactions.service_id', '=', 'services.id')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('inspections.branch_id', $branchId);
            });
        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search) {
                $query->where('inspections.no', 'like', '%' . $search['value'] . '%')
                    ->orWhere('inspections.date', 'like', '%' . $search['value'] . '%')
                    ->orWhere('vehicles.license_plate', 'like', '%' . $search['value'] . '%')
                    ->orWhere('customers.name', 'like', '%' . $search['value'] . '%');
            });
        }

        $query->having('service_name', '!=', '');

        $orderBy = $orderColumn[$order[0]['column']] ?? 'inspections.no';
        $query->orderBy($orderBy, $order[0]['dir']);

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
                $No++;
                $item->No = $No;
            }
        }

        $formattedItems = $items->map(function ($item) {
            return [
                'No'            => $item->No,
                'no'            => $item->no,
                'license_plate' => $item->license_plate,
                'customer_name' => $item->customer_name,
                'service_name'  => $item->service_name,
                'status'        => $item->status,
            ];
        });

        $pagination = [
            'total'          => $items->total(),
            'per_page'       => $items->perPage(),
            'current_page'   => $items->currentPage(),
            'last_page'      => $items->lastPage(),
            'from'           => $items->firstItem(),
            'to'             => $items->lastItem(),
            'first_page_url' => $items->url(1),
            'last_page_url'  => $items->url($items->lastPage()),
            'prev_page_url'  => $items->previousPageUrl(),
            'next_page_url'  => $items->nextPageUrl(),
            'path'           => $items->path(),
        ];

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', ['data' => $formattedItems, 'pagination' => $pagination]);
    }

    public function GetpageInsurance_report(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $columns = [
            'customers.id',
            'customers.name',
            'vehicles.license_plate',
            'vehicles.vehicle_inspection_types_id',
            'vehicles.notificate_insurance',
            DB::raw('DATE_SUB(vehicles.notificate_insurance, INTERVAL 1 YEAR) as notificate_insurance_regis'),
        ];
        $searchColumn = ['customers.name', 'vehicles.license_plate'];

        $query = Customers::select($columns)
            ->join('vehicles', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('inspections', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('inspections.branch_id', $branchId);
            });

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        $orderBy = $columns[$order[0]['column']] ?? 'customers.id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumn) {
                foreach ($searchColumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
                $No++;
                $item->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageInsurance(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $columns = [
            'customers.id',
            'customers.name',
            'vehicles.license_plate',
            // 'inspections.insurance_link_phone_id',
            'insurance_names.name as insurance_name',
            'vehicles.notificate_sub_insurance',
            DB::raw('DATE_SUB(vehicles.notificate_sub_insurance, INTERVAL 1 YEAR) as notificate_sub_insurance_regis'),
        ];

        $searchcolumn = ['customers.name', 'vehicles.license_plate', 'insurance_names.name'];

        $query = Customers::select($columns)
            ->join('vehicles', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('inspections', 'inspections.vehicle_id', '=', 'vehicles.id')
        // ->leftJoin('insurance_names', 'insurance_names.id', '=', 'inspections.insurance_link_phone_id') // Join with insurance_names table
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('inspections.branch_id', $branchId);
            });

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        $orderBy = $columns[$order[0]['column']] ?? 'customers.id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
                $No++;
                $item->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    protected function applyCostBranchFilter($query, $branchId)
    {
        $branchId = trim((string) ($branchId ?? ''));
        if ($branchId === '') {
            return $query;
        }

        return $query->where(function ($q) use ($branchId) {
            $q->where('costs.branch_id', $branchId)
                ->orWhereHas('Employees', function ($eq) use ($branchId) {
                    $eq->where('branch_id', $branchId);
                });
        });
    }

    public function GetpageExpense(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $status = $request->input('status', null);

        $columns = [
            'date',
            'name',
            'price',
        ];

        $query = Cost::select('id', 'date', 'name', 'price', 'type', 'detail', 'created_at', 'updated_at', 'expense_type_id', 'paid_type', 'employees_id')
            ->with(['expenseType', 'Employees']);
        $this->applyCostBranchFilter($query, $branchId);
        $query->when($status, function ($query) use ($status) {
                $query->where('type', $status);
            });

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderBy = $columns[$order[0]['column']] ?? 'date';
        $query->orderBy($orderBy, $order[0]['dir']);

        $items = $query->paginate($length, ['*'], 'page', $page);

        $id = ($page - 1) * $length + 1;
        foreach ($items as $item) {
            $item->no = $id++;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageList_check_gas(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = ($start / $length) + 1;

        $orderColumn = [
            'inspections.no',
            'vehicles.license_plate',
            'provinces.name_th_prefix',
            'customers.name',
            'vehicles.registration_date',
            'inspections.date',
            'services.name',
            'inspections.service_or',
            'inspections.result',
            'inspections.status',
        ];

        $searchColumn = [
            'vehicles.license_plate',
            'customers.name',
            'vehicles.registration_date',
            'inspections.date',
            'services.name',
            'inspections.service_or',
            'inspections.result',
            'inspections.status',
        ];

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $query = Inspections::query()
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->join('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->join('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->join('services', 'service_transactions.service_id', '=', 'services.id')
            ->select(
                'inspections.no',
                'vehicles.license_plate',
                'provinces.name_th_prefix',
                'customers.name',
                'vehicles.registration_date',
                'inspections.date',
                'service_transactions.service_id',
                'inspections.service_or',
                'inspections.result',
                'inspections.status'
            )
            ->whereIn('service_transactions.service_id', [2, 8]);

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! is_null($branchId)) {
            $query->where('inspections.branch_id', $branchId);
        }

        if (! empty($search['value'])) {
            $searchValue = $search['value'];
            $query->where(function ($query) use ($searchValue, $searchColumn) {
                foreach ($searchColumn as $column) {
                    $query->orWhere($column, 'like', "%$searchValue%");
                }
            });
        }

        if (isset($order['column']) && isset($order['dir']) && array_key_exists($order['column'], $orderColumn)) {
            $query->orderBy($orderColumn[$order['column']], $order['dir']);
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        foreach ($items as $item) {
            $item->service_name = $item->service_id == 9 ? 'LPG' : ($item->service_id == 10 ? 'NGV' : 'Unknown');
        }

        $id = ($page - 1) * $length + 1;
        foreach ($items as $item) {
            $item->id = $id++;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageCommission_employee(request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $branchId    = trim($request->input('branch_id', null));
        $startDate   = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate     = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $searchValue = $search['value'] ?? '';

        $query = Employees::query()
            ->with([
                'inspections' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('date', [$startDate, $endDate]);
                    }
                    $query->with('serviceTransactions.service');
                },
            ]);

        if (! is_null($branchId) && $branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        if (! empty($searchValue)) {
            $query->where(function ($query) use ($searchValue) {
                $query->orWhere('first_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('last_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('phone_number', 'like', '%' . $searchValue . '%');
            });
        }

        $employees = $query->get();

        $serviceCounts = [];
        foreach ($employees as $employee) {
            $employeeName                 = $employee->first_name . ' ' . $employee->last_name;
            $serviceCounts[$employeeName] = $employee->inspections->flatMap(function ($inspection) {
                return $inspection->serviceTransactions->map(function ($transaction) {
                    return $transaction->service->name;
                });
            })->countBy();
        }

        $result = $employees->map(function ($employee) use ($serviceCounts) {
            $employeeName = $employee->first_name . ' ' . $employee->last_name;
            return [
                'first_name'     => $employee->first_name,
                'last_name'      => $employee->last_name,
                'email'          => $employee->email,
                'phone_number'   => $employee->phone_number,
                'branch_id'      => $employee->branch_id,
                'service_counts' => $serviceCounts[$employeeName] ?? [],
            ];
        });

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $result);
    }

    public function GetpageProfit_report(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'vehicles.license_plate',
            'customers.name',
        ];

        $searchColumns = [
            'inspections.no',
            'inspections.status',
            'inspections.date',
            'vehicles.license_plate',
            'customers.name',
        ];

        $query = Inspections::select($columns)
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('customers', 'customers.id', '=', 'vehicles.customer_id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->leftJoin('services', 'services.id', '=', 'service_transactions.service_id')
            ->groupBy('inspections.id', 'inspections.no', 'inspections.date', 'vehicles.license_plate', 'customers.name')
            ->orderBy($columns[$order[0]['column']] ?? 'inspections.id', $order[0]['dir'])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('inspections.branch_id', $branchId);
            });
        // Search filter
        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        // Paginate results
        $items = $query->paginate($length, ['*'], 'page', $page);

        // Add "No" field and aggregate services
        $items->getCollection()->transform(function ($item, $key) use ($start) {
            $item->No = $start + $key + 1;

            $services = ServiceTransactions::select('services.id', 'services.name', 'services.price', 'services.description')
                ->join('services', 'services.id', '=', 'service_transactions.service_id')
                ->where('service_transactions.inspections_id', $item->id)
                ->get();

            $item->services = $services;

            return $item;
        });

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageCheck_car_report_(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $status    = $request->input('status', null);

        $columns = [
            'inspections.no',
            'inspections.id',
            'vehicles.license_plate',
            'vehicles.registration_date',
            'customers.name',
            'inspections.date',
            'inspections.result',
            'total_price',
        ];

        $searchColumn = [
            'inspections.no',
            'vehicles.license_plate',
            'vehicles.registration_date',
            'customers.name',
            'inspections.date',
        ];

        // การสร้าง Query
        $query = Inspections::select(
            'inspections.no',
            'inspections.id',
            'vehicles.license_plate',
            'vehicles.registration_date',
            'customers.name',
            'inspections.date',
            'inspections.result',
            DB::raw('SUM(CASE WHEN services.id = 1 THEN services.price ELSE 0 END) as total_price')
        )
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('employees', 'inspections.employee_id', '=', 'employees.id') // Join กับ employees
            ->leftJoin('service_transactions as serviceTransactions', 'inspections.id', '=', 'serviceTransactions.inspections_id')
            ->leftJoin('services', 'serviceTransactions.service_id', '=', 'services.id')
            ->whereHas('serviceTransactions', function ($query) {
                $query->where('service_id', 1)
                    ->where('status', true);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
        // แก้เงื่อนไขการกรอง branch_id จาก employees แทน
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('employees.branch_id', $branchId);
            });

        if ($status !== null) {
            if ($status == 0) {
                $query->whereNull('inspections.result');
            } elseif ($status == 1) {
                $query->whereNotNull('inspections.result');
            }
        }

        // การค้นหา
        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumn) {
                foreach ($searchColumn as $column) {
                    $tableColumn = explode('.', $column);
                    if (count($tableColumn) == 2) {
                        $query->orWhere($tableColumn[0] . '.' . $tableColumn[1], 'like', '%' . $search['value'] . '%');
                    } else {
                        $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                    }
                }
            });
        }

        // การจัดเรียงข้อมูล
        $orderBy  = $columns[$order[0]['column']] ?? 'inspections.no';
        $orderDir = $order[0]['dir'] ?? 'asc';
        $query->orderBy($orderBy, $orderDir);

        // การแบ่งหน้า
        $items = $query->groupBy(
            'inspections.no',
            'inspections.id',
            'vehicles.license_plate',
            'vehicles.registration_date',
            'customers.name',
            'inspections.date',
            'inspections.result'
        )->paginate($length, ['*'], 'page', $page);

        // การตั้งค่า No
        if ($items->isNotEmpty()) {
            $num = (($page - 1) * $length);
            foreach ($items as $item) {
                $num++;
                $item->num = $num;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function GetpageCheck_car_report(Request $request)
    {
        $perPage   = $request->input('length', 10);
        $page      = $request->input('start', 0);
        $search    = $request->input('search.value', "");
        $sortField = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', 1);

        $search_status = $request->input('search_status', "");
        $customer_id   = $request->input('customer_id', "");
        $branche_id    = $request->input('branche_id', "");
        $start_date    = $request->input('start_date', "");
        $end_date      = $request->input('end_date', "");
        $statusFilter  = $request->input('status', null);

        $branch_id    = $request->input('branch_id', ""); //bird

        $direction = 'DESC';
        if ($sortOrder == -1) {
            $direction = 'ASC';
        }

        // $service_id = $request->input('service_id', "");
        $service_id = 1;

        $seaclable = ['no', 'InspectionVehicles.license_plate'];

        $inspections = inspections::with(
            'InspectionVehicles.vehicleServiceTransaction',
            'Customers',
            'Branches'
        )
            ->where('inspections.status', '!=', 'cancel')
            ->where('inspections.branch_id',$branch_id) //bird
            ->whereNull('inspections.deleted_at')
            ->where(function ($query) use ($search, $seaclable, $search_status, $customer_id, $start_date, $end_date, $branche_id) {
                if ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery->orWhere('inspections.no', 'LIKE', '%' . $search . '%')
                            ->orWhereHas('InspectionVehicles', function ($q) use ($search) {
                                $q->where('license_plate', 'LIKE', '%' . $search . '%');
                            });
                    });
                }

                if (! empty($search_status)) {
                    $query->where('status', $search_status);
                }
                if (! empty($customer_id)) {
                    $query->whereHas('Customers', function ($subQuery) use ($customer_id) {
                        $subQuery->where('id', $customer_id);
                    });
                }
                if (! empty($branche_id)) {
                    $query->whereHas('Branches', function ($subQuery) use ($branche_id) {
                        $subQuery->where('id', $branche_id);
                    });
                }
                if (! empty($start_date) && ! empty($end_date)) {
                    $query->whereBetween('date', [$start_date, $end_date]);
                }
            });
        if (! empty($service_id)) {
            $inspections->whereHas('InspectionVehicles.vehicleServiceTransaction', function ($q) use ($service_id) {
                $q->where('service_id', $service_id);
            });
        }

        if (! is_null($statusFilter)) {
            if ($statusFilter == 0) {
                $inspections->whereNull('is_pass');
            } elseif ($statusFilter == 1) {
                $inspections->whereNotNull('is_pass');
            }
        }

        $inspections = $inspections
            ->orderBy($sortField, $direction)
            ->paginate($perPage, ['*'], 'page', ($page / $perPage) + 1);

        // ✅ รวมชื่อ-นามสกุลไว้ในฟิลด์ name ของ Customers
        $collection = $inspections->getCollection()->map(function ($row) {
            if ($row->Customers) {
                $full = trim(implode(' ', array_filter([
                    $row->Customers->name ?? '',
                    $row->Customers->lname ?? '',
                ])));
                $row->Customers->name = $full;  // ← อยากให้ “อยู่ใน name”
            }
            return $row;
        });
        $inspections->setCollection($collection); 

        return $this->returnSuccess('Success', $inspections);
    }

    public function reportExportPDF_gas_report_getpage(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $branchId = $request->input('branch_id', null);

        $columns = [
            'customers.no',
            'inspections.date',
            'vehicles.license_plate',
            'vehicles.vehicle_inspection_types_id',
            'vehicles.registration_date',
            'inspections.comments',
            'inspections.employee_id',
        ];
        $searchcolumns = [
            'customers.no',
            'inspections.date',
            'vehicles.license_plate',
            'vehicles.registration_date',
            'inspections.comments',
        ];

        $query = DB::table('inspections')
            ->join('customers', 'customers.id', '=', 'inspections.customer_id')
            ->join('employees', 'employees.id', '=', 'inspections.employee_id')
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->leftJoin('provinces', 'vehicles.province_id', '=', 'provinces.id')
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->select(
                'customers.no',
                DB::raw('MAX(inspections.date) as date'),
                'vehicles.license_plate',
                'vehicles.vehicle_inspection_types_id',
                'vehicles.registration_date',
                DB::raw('GROUP_CONCAT(DISTINCT inspections.comments) as comment'),
                DB::raw('GROUP_CONCAT(DISTINCT inspections.employee_id) as employee_id')
            )
            ->groupBy('customers.no', 'vehicles.license_plate', 'vehicles.vehicle_inspection_types_id', 'vehicles.registration_date');

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! is_null($branchId)) {
            $query->where('inspections.branch_id', $branchId);
        }

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumns) {
                foreach ($searchcolumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        if (! empty($order)) {
            $orderByColumn  = $columns[$order[0]['column']] ?? 'inspections.date';
            $orderDirection = $order[0]['dir'] ?? 'asc';
            $query->orderBy($orderByColumn, $orderDirection);
        } else {
            $query->orderBy('inspections.date', 'desc');
        }

        $results = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $results);
    }

    public function ListPDFcheck_testLPG2(Request $request, $id)
    {
        $data = Inspections::with([
            'Vehicles.VehicleGas',
            'Vehicles.Provinces',
            'Branches',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        ])->where('id', $id)->first();
        // return $data;
        if (! $data) {
            return $this->returnError('Data Not Found');
        }

        //Pull Data Branche
        $branche_Name        = $data->Branches->business_name;
        $branche_Address     = $data->Branches->address ?? null;
        $branche_SubDistrict = $data->Branches->SubDistricts->name_th_prefix ?? null;
        $branche_District    = $data->Branches->Districts->name_th_prefix ?? null;
        $branche_Province    = $data->Branches->Provinces->name_th_prefix ?? null;
        $branche_PostalCode  = $data->Branches->postal_code ?? null;
        $branche_Phone       = $data->Branches->phone ?? null;

        //Pull Data Vehicle
        $vehicle_LicensePlate = $data->Vehicles->license_plate ?? null;
        $vehicle_Province     = $data->Vehicles->Provinces->name_th_prefix ?? null;
        $vehicle_GasBrand     = $data->Vehicles->VehicleGas[0]->gas_brand ?? null;
        $vehicle_GasModelGas  = $data->Vehicles->VehicleGas[0]->gas_model ?? null;
        // dd($vehicle_GasModelGas);
        $vehicle_GasNumber   = $data->Vehicles->VehicleGas[0]->gas_number ?? null;
        $vehicle_WeightGas   = $data->Vehicles->VehicleGas[0]->gas_weight ?? null;
        $vehicle_ThickGas    = $data->Vehicles->VehicleGas[0]->gas_thick ?? null;
        $vehicle_CapacityGas = $data->Vehicles->VehicleGas[0]->gas_capacity ?? null;

        // Formatted Date
        $gascheck_date = $data->date;
        $formattedDate = Carbon::parse($gascheck_date)->locale('th')->translatedFormat('j F Y');
        $formattedDate = str_replace(date('Y'), (date('Y') + 543), $formattedDate);

        //Cal Capacity
        $total = 0;
        $total = (float) $vehicle_CapacityGas * 1000 / 5000;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('รายการตรวจและทดสอบถัง LPG');
        $mpdf->AddPage();

        $html = '

        <style>
            .fz{
                font-size: 16px;
            }
        </style>
        <div style="width: 100%; text-align: center; position: relative; top: 5px;">
            <strong>รายการตรวจและทดสอบถัง LPG</strong>
        </div>
            <table style="width: 100%; border: 1px solid black; margin-top: -5px;">
                <tr>
                    <td style="font-size: 18px;">ชื่อผู้ตรวจและทดสอบ</td>
                    <td style="font-size: 18px;">' . $branche_Name . '</td>
                    <td style="font-size: 18px;">หนังสือรับรองเลขที่ </td>
                    <td style="font-size: 18px; text-align: right; padding-right: 30px;" colspan="2">0/67</td>
                </tr>
                <tr>
                    <td style="font-size: 18px;">วันที่ตรวจและทดสอบ</td>
                    <td style="font-size: 18px;">' . $formattedDate . '</td>
                    <td style="font-size: 18px;">หมายเลขทะเบียน</td>
                    <td style="font-size: 18px;">' . $vehicle_LicensePlate . '</td>
                    <td style="font-size: 18px;">' . $vehicle_Province . '</td>
                </tr>
                <tr>
                    <td style="font-size: 18px;">สถานที่ตรวจและทดสอบ</td>
                    <td colspan="5">' . $branche_Address . ' ' . $branche_SubDistrict . ' ' . $branche_District . ' ' . $branche_Province . ' ' . $branche_PostalCode . ' ' . $branche_Phone . '</td>
                </tr>
            </table>
            <table border="1" style="width:100%; border-collapse: collapse; margin-top: 20;">
                <tr>
                    <th style="width:5%;" rowspan="2">ที่</th>
                    <th style="width:75%;" rowspan="2">รายการ</th>
                    <th style="width:20%;" colspan="2">ผลการตรวจ</th>
                </tr>
                <tr>
                    <th style="width:5%;">ผ่าน</th>
                    <th style="width:5%;">ไม่ผ่าน</th>
                </tr>
                <tr>
                    <td style="width:5%; border-right: none;"></td>
                    <td class="fz" style="text-align: left; border-left: none; border-right: none;">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ถังยี่ห้อ&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        รุ่น&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        มาตรฐาน มอก.370
                    </td>
                    <td style="width:5%; border-left: none; border-right: none;"></td>
                    <td style="width:5%; border-left: none;"></td>
                </tr>
                <tr style="height: 1px;">
                    <td></td>
                    <td colspan="3"></td>
                <tr>
                    <td class="fz" style="text-align: center;">1</td>
                    <td class="fz" colspan="3">ตรวจพินิจภายนอกถัง 14 รายการ</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.1</td>
                    <td class="fz">ถังถูกไฟไหม้</td>
                    <td style="text-align: center;">/</td>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.2</td>
                    <td class="fz">มีหลุมโดดเดี่ยวลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                    <td style="text-align: center;">/</td>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.3</td>
                    <td class="fz">มีรอยผุกร่อนเป็นแนวยาวตั้งแต่ 75 มิลลิเมตรขึ้นไป และลึกเกินร้อยละ 25 ของความหนาผนังถังเดิม หรือมีรอยผุกร่อนเป็นแนวยาวน้อยกว่า 75 มิลลิเมตร และลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.4</td>
                    <td class="fz">มีรอยผุกร่อนทั่วไปลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.5</td>
                    <td class="fz">มีรอบบุบเว้าที่รอยเชื่อมลึกเกิน 6 มิลลิเมตร</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.6</td>
                    <td class="fz">มีรอบบุบเว้าที่บริเวณอื่นลึกเกินร้อยละ 10 ของความกว้างเฉลี่ยของรอยบุบเว้า</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.7</td>
                    <td class="fz">มีรอยบาด รอยขูดขีด หรือทิ่มแทง ลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม หรือมีรอยดังกล่าวยาว ตั้งแต่ 75 มิลลิเมตรขึ้นไป และลึกเกินร้อยละ 25 ของความหนาผนังถังเดิม</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.8</td>
                    <td class="fz">มีรอยรั่ว ซึม รอยร้าว รอยหักพับ หรือบวม</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.9</td>
                    <td class="fz">จำนวนเกลียวของลิ้นถังที่กินกันลดลงจากที่ระบุไว้ หรือเมื่อขันเกลียวแน่นแล้วก๊าซรั่วซึม</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.10</td>
                    <td class="fz">ลิ้นเอียงจนเห็นได้ชัดเจน</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.11</td>
                    <td class="fz">โกร่งกำบังหรือฝาครอบเครื่องอุปกรณ์ที่ติดตั้งบนถังเสียรูป หลวม หรือมีรอยเชื่อมชำรุดหรือฐานถังชำรุดทำให้ถังเอียงจนเห็นได้ชัดเจน</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.12</td>
                    <td class="fz">ถังบิดเบี้ยวไม่ได้รูปทรง หรือมีการดัดแปลงต่อเติมถัง</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.13</td>
                    <td class="fz">เครื่องหมายประจำถังลบเลือนจนอ่านได้ไม่ชัดเจน มีการแก้ไขโดยไมถูกต้องหรือมีข้อความไม่ถูกตอง ตาม มอก.370-2525</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">1.14</td>
                    <td class="fz">อุปกรณ์ระบายความดันเสียหาย หรือเสียรูป หรือไม่ตรงตามที่ผู้ผลิตกำหนด</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">2</td>
                    <td class="fz">ตรวจสอบน้ำหนักถัง</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">2.1</td>
                    <td class="fz">ตรวจสอบมวลของถังเปล่าต้องมีมวลไม่น้อยกว่าร้อยละ 95 ของมวลถังเดิม มวลของถังเดิม ' . $vehicle_WeightGas . ' กิโลกรัม มวลของถังที่วัดได้ ' . $vehicle_WeightGas . ' กิโลกรัม</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">3</td>
                    <td class="fz">ตรวจสอบความหนาของถัง โดยการสุ่มวัดบริเวณที่สงสัย</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">3.1</td>
                    <td class="fz">ตรวจสอบความหนาของถัง ' . $vehicle_ThickGas . ' มิลลิเมตร</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center;">4</td>
                    <td class="fz">ทดสอบถังด้วยความดันไฮดรอลิกขยายตัว (hydrostatic stretch test ) </td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
                <tr>
                    <td class="fz" style="text-align: center; vertical-align: top;">4.1</td>
                    <td class="fz" style="line-height: 1.5;">ปริมาณความจุของถัง. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $vehicle_CapacityGas . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ลิตร หรือ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CC<br>
                    การขยายตัวถาวรของถังต้องไม่เกิน 1 ใน 5000 ส่วน ขยายได้ไม่เกิน &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . number_format($total, 1) . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CC<br>
                    จากการทดสอบปริมาณการขยายตัวเท่ากับ  CC</td>
                    <td style="text-align: center;">/</td>
                    <td class="fz">&nbsp;</td>
                </tr>
            </table>
                <div style="position: absolute; top: 905px; left: 120px">
                    <img src="../image/checkbox-true.png" alt="FIE" align="left" style="width: 30px;">
                </div>
                <div style="position: absolute; top: 950px; left: 120px">
                    <img src="../image/checkbox_f.jpg" alt="FIE" align="left" style="width: 30px;">
                </div>
                <div style="position: absolute; top: 897px; left: 180px; font-size: 14px;">
                    <p>ผ่าน&nbsp;</p>
                </div>
                <div style="position: absolute; top: 943px; left: 180px; font-size: 14px;">
                    <p>ไม่ผ่าน</p>
                </div>
                <div style="position: absolute; top: 785px; left: 150px; font-size: 18px;">
                    <p><strong>__________</strong></p>
                </div>
                <div style="position: absolute; top: 785px; left: 260px; font-size: 18px;">
                    <p><strong>__________</strong></p>
                </div>
                <div style="position: absolute; top: 809px; left: 340px; font-size: 18px;">
                    <p><strong>__________</strong></p>
                </div>
                <div style="text-align:center; position: absolute; top: 182px; left: 108px; font-size: 18px; width: 19%;">
                    <p><strong>' . $vehicle_GasBrand . '</strong></p>
                </div>
                <div style="text-align:center; position: absolute; top: 182px; left: 270px; font-size: 18px; width: 20%;">
                    <p><strong>' . $vehicle_GasModelGas . '</strong></p>
                </div>
                <div style="text-align:center; position: absolute; top: 182px; left: 400px; font-size: 18px; width: 30%">
                    <p><strong>' . $vehicle_GasNumber . '</strong></p>
                </div>
                ';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }
    public function ListPDFcheck_testLPG(Request $request, $id)
    {
        $data = Inspections::with([
            'Branches',
            'InspectionVehicles.vehicle.VehicleInspectionTypes',
            'InspectionVehicles.CheckGas.Chano.VehicleGas',
            'InspectionVehicles.vehicleServiceTransaction',
            'Employees',
        ])->where('id', $id)->first();
        // return $data;
        if (! $data) {
            return $this->returnErrorData('Data Not Found', 404);
        }

        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->first();
            if (! $branche) {
                return $this->returnError('Branches Not Found');
            }
            $branche_Name    = $branche->business_name ?? null;
            $branche_address = $branche->address ?? null;
            $branche_phone   = $branche->phone ?? null;
        } else {
            $branche_Name    = $data->Branches->business_name ?? null;
            $branche_address = $data->Branches->address ?? null;
            $branche_phone   = $data->Branches->phone ?? null;
        }
        $is_lpg = false;
        $is_ry  = false;
        if ($data->InspectionVehicles[0]->vehicleServiceTransaction) {
            foreach ($data->InspectionVehicles[0]->vehicleServiceTransaction as $service) {
                if (in_array($service->service_id, [8, 9, 10])) {
                    $is_lpg = true;
                }
            }
        }
        if ($data->InspectionVehicles[0]->vehicle->VehicleInspectionTypes->type == 'รย.') {
            $is_ry = true;
        }

        $book_number = '';
        if ($is_lpg && $is_ry) {
            $book_number = $data->branches->book_number_ry_lpg;
        } else if ($is_lpg && ! $is_ry) {
            $book_number = $data->branches->book_number_ks_lpg;
        } else if (! $is_lpg && $is_ry) {
            $book_number = $data->branches->book_number_ry_ngv;
        } else if (! $is_lpg && ! $is_ry) {
            $book_number = $data->branches->book_number_ks_ngv;
        }

        //Pull Data Vehicle
        $vehicle_LicensePlate = isset($data->InspectionVehicles[0]) ? $data->InspectionVehicles[0]->license_plate : null;
        $vehicle_Province     = isset($data->InspectionVehicles[0]) ? $data->InspectionVehicles[0]->provinces : null;

        // Formatted Date
        if (
            isset($data->InspectionVehicles[0]) &&
            isset($data->InspectionVehicles[0]->CheckGas[0]) &&
            isset($data->InspectionVehicles[0]->CheckGas[0]->date)
        ) {
            $gascheck_date = $data->InspectionVehicles[0]->CheckGas[0]->date;
        }else{
            $gascheck_date = Carbon::now();
        }
        $formattedDate = Carbon::parse($gascheck_date)->locale('th')->translatedFormat('j F Y');
        $formattedDate = str_replace(date('Y'), (date('Y') + 543), $formattedDate);

        // Initializing PDF
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('รายการตรวจและทดสอบถัง LPG');
        if (isset($data->InspectionVehicles[0]->CheckGas[0]->Chano) && count($data->InspectionVehicles[0]->CheckGas[0]->Chano) > 0) {
            foreach ($data->InspectionVehicles[0]->CheckGas[0]->Chano as $index => $vehicleGas) {

                $vehicle_GasBrand    = $vehicleGas->VehicleGas->gas_brand ?? null;
                $vehicle_GasModelGas = $vehicleGas->VehicleGas->gas_model ?? null;
                $vehicle_GasNumber   = $vehicleGas->VehicleGas->gas_number ?? null;
                $vehicle_WeightGas   = $vehicleGas->VehicleGas->gas_weight ?? null;
                $vehicle_ThickGas    = $vehicleGas->VehicleGas->gas_thick ?? null;
                $vehicle_CapacityGas = $vehicleGas->VehicleGas->gas_capacity ?? null;
                $vehicle_CapacityGas = intval($vehicle_CapacityGas ?? 0);

                $total = (float) $vehicle_CapacityGas * (1 / 5);

                // Add a new page for each vehicle gas
                if ($index > 0) {
                    $mpdf->AddPage();
                }

                $html = '
                <style>
                    .fz{
                        font-size: 16px;
                    }
                    .long-underline {
                        display: inline-block;
                        padding-left: 30px;  /* ปรับขนาดตามที่ต้องการ */
                        padding-right: 30px; /* ปรับขนาดตามที่ต้องการ */
                        border-bottom: 1px solid black;
                    }
                </style>
                <div style="width: 100%; text-align: center; position: relative; top: 5px;">
                    <strong>รายการตรวจและทดสอบถัง LPG</strong>
                </div>
                <table style="width: 100%; border: 1px solid black; margin-top: -5px; border-collapse: collapse;">
                    <tr>
                        <td style="font-size: 16px;">ชื่อผู้ตรวจและทดสอบ</td>
                        <td style="font-size: 16px;">' . $branche_Name . '</td>
                        <td style="font-size: 16px;">หนังสือรับรองเลขที่ </td>
                        <td style="font-size: 16px;">' . $book_number . '</td>
                        <td style="font-size: 16px; text-align: right; padding-right: 30px;">' . $vehicleGas->cha_no . '/' . substr((Carbon::now()->year + 543), -2) . '</td>
                    </tr>
                    <tr>
                        <td style="font-size: 16px; width: 18%;">วันที่ตรวจและทดสอบ</td>
                        <td style="font-size: 16px;">' . $formattedDate . '</td>
                        <td style="font-size: 16px;">หมายเลขทะเบียน</td>
                        <td style="font-size: 16px;">' . $vehicle_LicensePlate . '</td>
                        <td style="font-size: 16px;">' . $vehicle_Province . '</td>
                    </tr>
                    <tr>
                        <td style="font-size: 16px;" >สถานที่ตรวจและทดสอบ</td>
                        <td style="font-size: 16px;" colspan="4">' . $branche_address . ' ' . $branche_phone . '</td>
                    </tr>
                </table>
                    <table border="1" style="width:100%; border-collapse: collapse; margin-top: 20;">
                        <tr>
                            <th style="width:5%;" rowspan="2">ที่</th>
                            <th style="width:70%;" rowspan="2" colspan="5">รายการ</th>
                            <th colspan="2">ผลการตรวจ</th>
                        </tr>
                        <tr>
                            <th style="width: 50px;">ผ่าน</th>
                            <th style="width: 50px;">ไม่ผ่าน</th>
                        </tr>
                        <tr>
                            <td style="border-right: none;"></td>
                            <td class="fz" style="text-align: left; border-left: none; border-right: none;" colspan="5">
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ถังยี่ห้อ&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                รุ่น&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                มาตรฐาน มอก.370
                            </td>
                            <td style=" border-left: none; border-right: none;"></td>
                            <td style=" border-left: none;"></td>
                        </tr>
                        <tr style="height: 1px;">
                            <td></td>
                            <td colspan="7"></td>
                        <tr>
                            <td class="fz" style="text-align: center;">1</td>
                            <td class="fz" colspan="7">ตรวจพินิจภายนอกถัง 14 รายการ</td>

                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.1</td>
                            <td class="fz" colspan="5">ถังถูกไฟไหม้</td>
                            <td style="text-align: center;">/</td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.2</td>
                            <td class="fz" colspan="5">มีหลุมโดดเดี่ยวลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                            <td style="text-align: center;">/</td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.3</td>
                            <td class="fz" colspan="5">มีรอยผุกร่อนเป็นแนวยาวตั้งแต่ 75 มิลลิเมตรขึ้นไป และลึกเกินร้อยละ 25 ของความหนาผนังถังเดิม หรือมีรอยผุกร่อนเป็นแนวยาวน้อยกว่า 75 มิลลิเมตร และลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.4</td>
                            <td class="fz" colspan="5">มีรอยผุกร่อนทั่วไปลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.5</td>
                            <td class="fz" colspan="5">มีรอบบุบเว้าที่รอยเชื่อมลึกเกิน 6 มิลลิเมตร</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.6</td>
                            <td class="fz" colspan="5">มีรอบบุบเว้าที่บริเวณอื่นลึกเกินร้อยละ 10 ของความกว้างเฉลี่ยของรอยบุบเว้า</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.7</td>
                            <td class="fz" colspan="5">มีรอยบาด รอยขูดขีด หรือทิ่มแทง ลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม หรือมีรอยดังกล่าวยาว ตั้งแต่ 75 มิลลิเมตรขึ้นไป และลึกเกินร้อยละ 25 ของความหนาผนังถังเดิม</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.8</td>
                            <td class="fz" colspan="5">มีรอยรั่ว ซึม รอยร้าว รอยหักพับ หรือบวม</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.9</td>
                            <td class="fz" colspan="5">จำนวนเกลียวของลิ้นถังที่กินกันลดลงจากที่ระบุไว้ หรือเมื่อขันเกลียวแน่นแล้วก๊าซรั่วซึม</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.10</td>
                            <td class="fz" colspan="5">ลิ้นเอียงจนเห็นได้ชัดเจน</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.11</td>
                            <td class="fz" colspan="5">โกร่งกำบังหรือฝาครอบเครื่องอุปกรณ์ที่ติดตั้งบนถังเสียรูป หลวม หรือมีรอยเชื่อมชำรุดหรือฐานถังชำรุดทำให้ถังเอียงจนเห็นได้ชัดเจน</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.12</td>
                            <td class="fz" colspan="5">ถังบิดเบี้ยวไม่ได้รูปทรง หรือมีการดัดแปลงต่อเติมถัง</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.13</td>
                            <td class="fz" colspan="5">เครื่องหมายประจำถังลบเลือนจนอ่านได้ไม่ชัดเจน มีการแก้ไขโดยไมถูกต้องหรือมีข้อความไม่ถูกตอง ตาม มอก.370-2525</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">1.14</td>
                            <td class="fz" colspan="5">อุปกรณ์ระบายความดันเสียหาย หรือเสียรูป หรือไม่ตรงตามที่ผู้ผลิตกำหนด</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">2</td>
                            <td class="fz" colspan="5">ตรวจสอบน้ำหนักถัง</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">2.1</td>
                            <td class="fz" colspan="5">ตรวจสอบมวลของถังเปล่าต้องมีมวลไม่น้อยกว่าร้อยละ 95 ของมวลถังเดิม มวลของถังเดิม ' . $vehicle_WeightGas . ' กิโลกรัม มวลของถังที่วัดได้ ' . $vehicle_WeightGas . ' กิโลกรัม</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">3</td>
                            <td class="fz" colspan="5">ตรวจสอบความหนาของถัง โดยการสุ่มวัดบริเวณที่สงสัย</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">3.1</td>
                            <td class="fz" colspan="5">ตรวจสอบความหนาของถัง ' . $vehicle_ThickGas . ' มิลลิเมตร</td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center;">4</td>
                            <td class="fz" colspan="5">ทดสอบถังด้วยความดันไฮดรอลิกขยายตัว (hydrostatic stretch test ) </td>
                            <td style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="text-align: center; vertical-align: top;" rowspan="3">4.1</td>
                            <td class="fz" style="border: none; ">ปริมาณความจุของถัง</td>
                            <td style="border: none;">' . $vehicle_CapacityGas . '</td>
                            <td class="fz" style="border: none;">ลิตรหรือ</td>
                            <td class="fz" style="border: none;">-</td>
                            <td style="border: none;">CC</td>
                            <td class="fz" style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                            <tr>
                                <td colspan="2" class="fz" style="border: none; width: 40%;">การขยายตัวถาวรของถังต้องไม่เกิน 1 ใน 5000 ส่วน ขยายได้ไม่เกิน</td>
                                <td style="border: none;">' . number_format($total, 1) . ' &nbsp;&nbsp;CC</td>
                                <td style="border: none;">&nbsp;</td>
                                <td style="border: none;">&nbsp;</td>
                                <td class="fz" style="text-align: center;">/</td>
                                <td class="fz">&nbsp;</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="fz" style="border-top: none; border-left: none; border-right: none;">จากการทดสอบปริมาณการขยายตัวเท่ากับ &nbsp;&nbsp;&nbsp;
                                    <span class="long-underline"></span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;0&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CC
                                </td>
                                <td style="border-top: none; border-left: none; border-right: none;"></td>
                                <td colspan="2" style="border-top: none; border-left: none; border-right: none;"></td>
                                <td class="fz" style="text-align: center;">/</td>
                                <td class="fz">&nbsp;</td>
                            </tr>
                    </table>
                        <div style="position: absolute; top: 950px; left: 120px">
                        </div>
                        <div style="position: absolute; top: 995px; left: 120px">
                        </div>
                        <div style="position: absolute; top: 923px; left: 180px; font-size: 14px; width: 300px; ">
                            <img src="../image/checkbox-true.png" alt="FIE" align="left" style="width: 30px;">
                            <span>&nbsp;&nbsp;&nbsp;ผ่าน</span>
                        </div>
                        <div style="position: absolute; top: 967px; left: 180px; font-size: 14px; width: 300px; ">
                            <img src="../image/checkbox.png" alt="FIE" align="left" style="width: 30px;">
                            <span>&nbsp;&nbsp;&nbsp;ไม่ผ่าน</span>
                        </div>


                        <div style="text-align:center; position: absolute; top: 185px; left: 108px; font-size: 18px; width: 19%;">
                            <strong>' . $vehicle_GasBrand . '&nbsp;</strong>
                        </div>
                        <div style="text-align:center; position: absolute; top: 185px; left: 270px; font-size: 18px; width: 20%;">
                            <strong>' . $vehicle_GasModelGas . '&nbsp;</strong>
                        </div>
                        <div style="text-align:center; position: absolute; top: 185px; left: 430px; font-size: 18px; width: 19%;">
                            <strong>' . $vehicle_GasNumber . '&nbsp;</strong>
                        </div>
                ';

                $mpdf->WriteHTML($html);
            }
        } else {
            $mpdf->AddPage();

            $html1 = '
            <style>
                .fz{
                    font-size: 16px;
                }
                .long-underline {
                    display: inline-block;
                    padding-left: 30px;  /* ปรับขนาดตามที่ต้องการ */
                    padding-right: 30px; /* ปรับขนาดตามที่ต้องการ */
                    border-bottom: 1px solid black;
                }
            </style>
            <div style="width: 100%; text-align: center; position: relative; top: 5px;">
                <strong>รายการตรวจและทดสอบถัง LPG</strong>
            </div>
                <table style="width: 100%; border: 1px solid black; margin-top: -5px;">
                    <tr>
                        <td style="font-size: 18px;">ชื่อผู้ตรวจและทดสอบ</td>
                        <td style="font-size: 18px;">&nbsp;</td>
                        <td style="font-size: 18px;">หนังสือรับรองเลขที่ </td>
                        <td style="font-size: 18px; text-align: right; padding-right: 30px;" colspan="2">0/67</td>
                    </tr>
                    <tr>
                        <td style="font-size: 18px;">วันที่ตรวจและทดสอบ</td>
                        <td style="font-size: 18px;">&nbsp;</td>
                        <td style="font-size: 18px;">หมายเลขทะเบียน</td>
                        <td style="font-size: 18px;">&nbsp;</td>
                        <td style="font-size: 18px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="font-size: 18px;">สถานที่ตรวจและทดสอบ</td>
                        <td colspan="5">&nbsp;</td>
                    </tr>
                </table>
                <table border="1" style="width:100%; border-collapse: collapse; margin-top: 20;">
                    <tr>
                        <th style="width:5%;" rowspan="2">ที่</th>
                        <th style="width:70%;" rowspan="2" colspan="5">รายการ</th>
                        <th style="width:25%;" colspan="2">ผลการตรวจ</th>
                    </tr>
                    <tr>
                        <th >ผ่าน</th>
                        <th >ไม่ผ่าน</th>
                    </tr>
                    <tr>
                        <td style="border-right: none;"></td>
                        <td class="fz" style="text-align: left; border-left: none; border-right: none;" colspan="5">
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ถังยี่ห้อ&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            รุ่น&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            มาตรฐาน มอก.370
                        </td>
                        <td style=" border-left: none; border-right: none;"></td>
                        <td style=" border-left: none;"></td>
                    </tr>
                    <tr style="height: 1px;">
                        <td></td>
                        <td colspan="7"></td>
                    <tr>
                        <td class="fz" style="text-align: center;">1</td>
                        <td class="fz" colspan="7">ตรวจพินิจภายนอกถัง 14 รายการ</td>

                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.1</td>
                        <td class="fz" colspan="5">ถังถูกไฟไหม้</td>
                        <td style="text-align: center;">/</td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.2</td>
                        <td class="fz" colspan="5">มีหลุมโดดเดี่ยวลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                        <td style="text-align: center;">/</td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.3</td>
                        <td class="fz" colspan="5">มีรอยผุกร่อนเป็นแนวยาวตั้งแต่ 75 มิลลิเมตรขึ้นไป และลึกเกินร้อยละ 25 ของความหนาผนังถังเดิม หรือมีรอยผุกร่อนเป็นแนวยาวน้อยกว่า 75 มิลลิเมตร และลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.4</td>
                        <td class="fz" colspan="5">มีรอยผุกร่อนทั่วไปลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.5</td>
                        <td class="fz" colspan="5">มีรอบบุบเว้าที่รอยเชื่อมลึกเกิน 6 มิลลิเมตร</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.6</td>
                        <td class="fz" colspan="5">มีรอบบุบเว้าที่บริเวณอื่นลึกเกินร้อยละ 10 ของความกว้างเฉลี่ยของรอยบุบเว้า</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.7</td>
                        <td class="fz" colspan="5">มีรอยบาด รอยขูดขีด หรือทิ่มแทง ลึกเกินร้อยละ 50 ของความหนาผนังถังเดิม หรือมีรอยดังกล่าวยาว ตั้งแต่ 75 มิลลิเมตรขึ้นไป และลึกเกินร้อยละ 25 ของความหนาผนังถังเดิม</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.8</td>
                        <td class="fz" colspan="5">มีรอยรั่ว ซึม รอยร้าว รอยหักพับ หรือบวม</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.9</td>
                        <td class="fz" colspan="5">จำนวนเกลียวของลิ้นถังที่กินกันลดลงจากที่ระบุไว้ หรือเมื่อขันเกลียวแน่นแล้วก๊าซรั่วซึม</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.10</td>
                        <td class="fz" colspan="5">ลิ้นเอียงจนเห็นได้ชัดเจน</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.11</td>
                        <td class="fz" colspan="5">โกร่งกำบังหรือฝาครอบเครื่องอุปกรณ์ที่ติดตั้งบนถังเสียรูป หลวม หรือมีรอยเชื่อมชำรุดหรือฐานถังชำรุดทำให้ถังเอียงจนเห็นได้ชัดเจน</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.12</td>
                        <td class="fz" colspan="5">ถังบิดเบี้ยวไม่ได้รูปทรง หรือมีการดัดแปลงต่อเติมถัง</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.13</td>
                        <td class="fz" colspan="5">เครื่องหมายประจำถังลบเลือนจนอ่านได้ไม่ชัดเจน มีการแก้ไขโดยไมถูกต้องหรือมีข้อความไม่ถูกตอง ตาม มอก.370-2525</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">1.14</td>
                        <td class="fz" colspan="5">อุปกรณ์ระบายความดันเสียหาย หรือเสียรูป หรือไม่ตรงตามที่ผู้ผลิตกำหนด</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">2</td>
                        <td class="fz" colspan="5">ตรวจสอบน้ำหนักถัง</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">2.1</td>
                        <td class="fz" colspan="5">ตรวจสอบมวลของถังเปล่าต้องมีมวลไม่น้อยกว่าร้อยละ 95 ของมวลถังเดิม มวลของถังเดิม &nbsp; กิโลกรัม มวลของถังที่วัดได้ &nbsp; กิโลกรัม</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">3</td>
                        <td class="fz" colspan="5">ตรวจสอบความหนาของถัง โดยการสุ่มวัดบริเวณที่สงสัย</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">3.1</td>
                        <td class="fz" colspan="5">ตรวจสอบความหนาของถัง &nbsp; มิลลิเมตร</td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center;">4</td>
                        <td class="fz" colspan="5">ทดสอบถังด้วยความดันไฮดรอลิกขยายตัว (hydrostatic stretch test ) </td>
                        <td style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="fz" style="text-align: center; vertical-align: top;" rowspan="3">4.1</td>
                        <td class="fz" style="border: none;">ปริมาณความจุของถัง</td>
                        <td class="long-underline" style="border: none; text-align: left;">&nbsp;</td>
                        <td class="fz" style="border: none;">ลิตรหรือ</td>
                        <td class="fz" style="border: none;">-</td>
                        <td style="border: none;">CC</td>
                        <td class="fz" style="text-align: center;">/</td>
                        <td class="fz">&nbsp;</td>
                    </tr>
                        <tr>
                            <td class="fz" style="border: none; width: 40%;">การขยายตัวถาวรของถังต้องไม่เกิน 1 ใน 5000 ส่วน ขยายได้ไม่เกิน</td>
                            <td style="border: none;">&nbsp;</td>
                            <td style="border: none;">CC</td>
                            <td colspan="2" style="border: none;"></td>
                            <td class="fz" style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="fz" style="border-top: none; border-left: none; border-right: none;">จากการทดสอบปริมาณการขยายตัวเท่ากับ &nbsp;&nbsp;&nbsp;
                                <span class="long-underline"></span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CC
                            </td>
                            <td style="border-top: none; border-left: none; border-right: none;"></td>
                            <td style="border-top: none; border-left: none; border-right: none;"></td>
                            <td colspan="2" style="border-top: none; border-left: none; border-right: none;"></td>
                            <td class="fz" style="text-align: center;">/</td>
                            <td class="fz">&nbsp;</td>
                        </tr>
                </table>
                    <div style="position: absolute; top: 950px; left: 120px">
                    </div>
                    <div style="position: absolute; top: 995px; left: 120px">
                    </div>
                    <div style="position: absolute; top: 943px; left: 180px; font-size: 14px;">
                        <p>ผ่าน&nbsp;</p>
                    </div>
                    <div style="position: absolute; top: 987px; left: 180px; font-size: 14px;">
                        <p>ไม่ผ่าน</p>
                    </div>';
            $mpdf->WriteHTML($html1);
        }

        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function daily_report_getpage(Request $request)
    {
        $length = $request->length;
        $order  = $request->order;
        $search = $request->search;
        $start  = $request->start;
        $page   = $start / $length + 1;

        $check_date = $request->input('check_date') ? Carbon::parse($request->input('check_date')) : null;
        $branchId   = $request->input('branch_id', null);

        $selectColumns = [
            'inspections.no AS inspection_no',
            'vehicles.license_plate',
            'customers.name AS customer_name',
            'inspections.discount',
            'inspections.total',
            'service_transactions.service_price AS service_price_tran',
            'services.name AS service_name',
            'services.price AS service_price',
            'services.id AS service_id',
        ];

        $searchColumns = [
            'inspections.no',
            'vehicles.license_plate',
            'customers.name',
            'inspections.discount',
            'inspections.total',
        ];

        $query = Inspections::select($selectColumns)
            ->join('vehicles', 'vehicles.id', '=', 'inspections.vehicle_id')
            ->join('customers', 'customers.id', '=', 'vehicles.customer_id')
            ->leftJoin('service_transactions', 'service_transactions.inspections_id', '=', 'inspections.id')
            ->leftJoin('services', 'services.id', '=', 'service_transactions.service_id');

        if ($check_date) {
            $query->whereDate('inspections.date', $check_date);
        }

        if ($branchId) {
            $query->where('inspections.branch_id', $branchId);
        }

        $orderColumn = $searchColumns[$order[0]['column']] ?? 'inspections.no';
        $query->orderBy($orderColumn, $order[0]['dir']);

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->get();

        $groupedItems = $items->groupBy('inspection_no')->map(function ($group) {
            $firstItem           = $group->first();
            $serviceTransactions = $group->map(function ($item) {
                return [
                    'service_id'         => $item->service_id,
                    'service_name'       => $item->service_name,
                    'service_price'      => $item->service_price,
                    'service_price_tran' => $item->service_price_tran,
                ];
            });

            $filteredServices = $serviceTransactions->filter(function ($item) {
                return in_array($item['service_id'], [1, 2, 3, 7]);
            });

            $otherServices = $serviceTransactions->filter(function ($item) {
                return ! in_array($item['service_id'], [1, 2, 3, 7]);
            });

            if ($otherServices->isNotEmpty()) {
                $filteredServices->push([
                    'service_name'       => 'Others',
                    'service_price'      => $otherServices->sum('service_price'),
                    'service_price_tran' => $otherServices->sum('service_price_tran'),
                ]);
            }

            $aggregatedServices = $filteredServices->groupBy('service_name')->map(function ($group) {
                return [
                    'service_name'       => $group->first()['service_name'],
                    'service_price'      => $group->sum('service_price'),
                    'service_price_tran' => $group->sum('service_price_tran'),
                ];
            });

            return [
                'inspection_details'   => [
                    'inspection_no' => $firstItem->inspection_no,
                    'license_plate' => $firstItem->license_plate,
                    'customer_name' => $firstItem->customer_name,
                    'discount'      => $firstItem->discount,
                    'total'         => $firstItem->total,
                ],
                'service_transactions' => $aggregatedServices,
            ];
        });

        $items = $groupedItems->values()->forPage($page, $length);

        $No = (($page - 1) * $length);
        foreach ($items as &$item) {
            $No++;
            $item['No'] = $No;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function license($id)
    {
        $data = Vehicles::with([
            'Provinces',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnErrorData('Data Not Found', 404);
        }

        //Pull Data Vehicle
        $vehicle_LicensePlate = $data->license_plate ?? null;
        $vehicle_Province     = $data->Provinces->name_th ?? null;

        // กำหนดค่าเริ่มต้นของ mPDF
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบอนุญาต');
        $mpdf->AddPage();

        $html = '
            <div style="position: absolute; top:97px; left: 478px;">
                <p style="font-size: 20px;"><b>' . $vehicle_LicensePlate . ' ' . $vehicle_Province . '</b></p>
            </div>
        </html>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function license_image($id)
    {
        // $data = Vehicles::with([
        //     'Provinces',
        // ])->where('id', $id)->first();
        $data = CheckGas::with([
            'inspectionVehicle',
        ])->where('id', $id)->first();

        if (! $data) {
            return $this->returnErrorData('Data Not Found', 404);
        }

        //Pull Data Vehicle
        $vehicle_LicensePlate = $data->inspectionVehicle->license_plate ?? null;
        $vehicle_Province     = $data->inspectionVehicle->provinces ?? null;

        // กำหนดค่าเริ่มต้นของ mPDF
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบอนุญาต');
        $mpdf->AddPage();

        $html = '
            <div style="width: 100%; text-align: center; padding-top: 50;">
                <p style="font-size: 30px; margin: 0;"><b>ทะเบียน &nbsp;' . $vehicle_LicensePlate . ' &nbsp;' . $vehicle_Province . '</b></p>
            </div>
            <div style="width: 100%; text-align: center;">
                <img src="' . $data->photo_vehicle_gas_url . '" alt="Car-image" style="width: 250px;">
            </div>
        </html>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function report_cng(Request $request, $id)
    {
        $data = Inspections::with([
            'Branches',
            'InspectionVehicles.vehicle.VehicleInspectionTypes',
            'InspectionVehicles.CheckGas',
            'InspectionVehicles.vehicleServiceTransaction',
            // 'Vehicles.VehicleGas',
            // 'Vehicles.Provinces',
            'Employees',
        ])->where('id', $id)->first();
        // กำหนดค่าเริ่มต้นของ mPDF
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $round         = 0;
        $no            = 1;
        $serviceRows   = '';
        $totalgas      = 0;
        $totalgas_item = 0;

        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->first();
            if (! $branche) {
                return $this->returnError('Branches Not Found');
            }
            $branche_Name    = $branche->business_name ?? null;
            $branche_address = $branche->address ?? null;
            $branche_phone   = $branche->phone ?? null;
        } else {
            $branche_Name    = $data->Branches->business_name ?? null;
            $branche_address = $data->Branches->address ?? null;
            $branche_phone   = $data->Branches->phone ?? null;
        }

        if (isset($data->InspectionVehicles[0]->vehicle->VehicleGas) && is_iterable($data->InspectionVehicles[0]->vehicle->VehicleGas)) {
            foreach ($data->InspectionVehicles[0]->vehicle->VehicleGas as $vehicle) {
                $vehiclegas_type     = $vehicle->gas_type ?? null;
                $vehiclegas_brand    = $vehicle->gas_brand ?? null;
                $vehiclegas_capacity = $vehicle->gas_capacity ?? null;
                $vehiclegas_number   = $vehicle->gas_number ?? null;
                $vehiclegas_weight   = intval($vehicle->gas_weight ?? 0);
                $totalgas += $vehiclegas_weight;
                $round += 1;
                $arabic     = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                $thai       = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
                $thaiNumber = str_replace($arabic, $thai, $no++);
                $serviceRows .= '
                <tr>
                    <td style="text-align: center; border-collapse: collapse; border: 1px solid black; width: 5%;">' . $thaiNumber . '</td>
                    <td style="border-collapse: collapse; border: 1px solid black; width: 15%;">' . $vehiclegas_type . '</td>
                    <td style="border-collapse: collapse; border: 1px solid black; width: 20%;">' . $vehiclegas_brand . '</td>
                    <td style="border-collapse: collapse; border: 1px solid black; width: 10%;">' . $vehiclegas_capacity . '</td>
                    <td style="border-collapse: collapse; border: 1px solid black; width: 20%;">' . $vehiclegas_number . '</td>
                    <td style="border-collapse: collapse; border: 1px solid black; width: 15%">' . $vehiclegas_weight . '</td>
                </tr>';
            }
        }
        $totalgas_item = $totalgas * 0.2;
        $alltotal      = $totalgas + $totalgas_item;

        for ($i = $round; $i < 6; $i++) {
            $serviceRows .= '
            <tr>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
            </tr>';
        }
        // return $data;

        //Pull Data Vehicle
        $vehicle_LicensePlate  = isset($data->InspectionVehicles[0]) ? $data->InspectionVehicles[0]->license_plate : null;
        $vehicle_Province      = isset($data->InspectionVehicles[0]) ? $data->InspectionVehicles[0]->provinces : null;
        $vehicle_Chassis       = isset($data->InspectionVehicles[0]->vehicle) ? $data->InspectionVehicles[0]->vehicle->chassis_number : null;
        $vehicle_manual_number = isset($data->InspectionVehicles[0]->vehicle) ? $data->InspectionVehicles[0]->vehicle->manual_number : null;
        $vehicle_manual_date   = isset($data->InspectionVehicles[0]->vehicle) ? $data->InspectionVehicles[0]->vehicle->manual_date : null;
        $vehicle_Seat          = isset($data->InspectionVehicles[0]->vehicle) ? $data->InspectionVehicles[0]->vehicle->seat_count : null;
        $vehicle_Weight        = isset($data->InspectionVehicles[0]->vehicle) ? $data->InspectionVehicles[0]->vehicle->weight : null;
        $vehicle_weight_all    = isset($data->InspectionVehicles[0]->vehicle) ? $data->InspectionVehicles[0]->vehicle->weight_all : null;
        $vehicle_weight_loaded = isset($data->InspectionVehicles[0]->vehicle) ? $data->InspectionVehicles[0]->vehicle->weight_loaded : null;

        $checkgas_date = $data->InspectionVehicles[0]->CheckGas[0]->date;

        $is_lpg = false;
        $is_ry  = false;
        if ($data->InspectionVehicles[0]->vehicleServiceTransaction) {
            foreach ($data->InspectionVehicles[0]->vehicleServiceTransaction as $service) {
                if (in_array($service->service_id, [8, 9, 10])) {
                    $is_lpg = true;
                }
            }
        }
        if ($data->InspectionVehicles[0]->vehicle->VehicleInspectionTypes->type == 'รย.') {
            $is_ry = true;
        }

        $book_number = '';
        $book_date   = '';
        if ($is_lpg && $is_ry) {
            $book_number = $data->branches->book_number_ry_lpg;
            $book_date   = $data->branches->expired_ry_lpg;
        } else if ($is_lpg && ! $is_ry) {
            $book_number = $data->branches->book_number_ks_lpg;
            $book_date   = $data->branches->expired_ks_lpg;
        } else if (! $is_lpg && $is_ry) {
            $book_number = $data->branches->book_number_ry_ngv;
            $book_date   = $data->branches->expired_ry_ngv;
        } else if (! $is_lpg && ! $is_ry) {
            $book_number = $data->branches->book_number_ks_ngv;
            $book_date   = $data->branches->expired_ks_ngv;
        }

        $customer_name  = $data->InspectionVehicles[0]->name ?? null;
        $customer_phone = $data->InspectionVehicles[0]->phone_number ?? null;

        $totalSeat       = (int) ($stand_count ?? 0) + (int) ($vehicle_Seat ?? 0);
        $totalSeatWeight = $totalSeat * 55;

        $allweight = $vehicle_Weight + $vehicle_weight_loaded + $totalSeatWeight + $alltotal;

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 15,
            'margin_right'      => 15,
            'margin_top'        => 5,
            'margin_bottom'     => 15,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('CNG');
        $mpdf->AddPage();

        $html = '
        <style>
            .fontset {
                text-align: center;
            }
        </style>
        <body>
            <p style="font-size: 20px; text-align: center; line-height: 1.1;"><b>แบบประเมินน้ำหนักของรถก่อนการติดตั้งถัง เครื่องอุปกรณ์ส่วนควบ<br>ของรถที่ใช้ในการขนส่งที่ใช้ก๊าซธรรมชาติอัด (CNG) เป็นเชื้อเพลิง</br></p>
        <div style="width: 100%; line-height: 0.2;">
            <p>วันที่ประเมิน............................................ชื่อสถานที่ติดตั้ง......................................................................................สาขา.............................</p>
            <p>ที่ตั้ง........................................................................................................................................................โทรศัพท์.........................................</p>
            <p>ได้รับความเห็นชอบตามหนังสือเลขที่.............................................................สิ้นอายุวันที่............................................................................</p>
            <p>ชื่อผู้นำรถเข้ารับบริการ........................................................................................โทรศัพท์............................................................................</p>
        </div>
        <div style="width: 100%; line-height: 0.2;">
            <p><b>ข้อมูลตามเอกสารการจดทะเบียน(หรือหนังสือให้ความเห็นชอบรถ)</b></p>
            <p>หมายเลขทะเบียนรถ..................................................เลขคัสซี.....................................................................................................................</p>
            <p>หนังสือให้ความเห็นชอบรถเลขที่...........................................................ลงวันที่............................................................................................</p>
            <p>จำนวนผู้โดยสาร นั่ง...................คน&nbsp;&nbsp;&nbsp;ยืน...................คน&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;จำนวนผู้โดยสารโดยรวม...................คน</p>
            <p>(๑) น้ำหนักรวม/น้ำหนักรวมสูงสุด.............................กิโลกรัม</p>
            <p>(๒) น้ำหนักรถเปล่า.............................กิโลกรัม</p>
            <p>(๓) น้ำหนักบรรทุกหรือน้ำหนักโดยสาร แล้วแต่กรณี</p>
            <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(๓.๑) กรณีรถบรรทุก น้ำหนักบรรทุก.............................กิโลกรัม</p>
            <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(๓.๒) กรณีรถโดยสาร น้ำหนักผู้โดยสาร = จำนวนผู้โดยสาร............คน x ๕๕ ............กิโลกรัม</p>
        </div>
        <div style="width: 100%; line-height: 0.2;">
            <p><b>น้ำหนักถัง เครื่องอุปกรณ์และส่วนควบ อุปกรณ์หรือเครื่องป้องกัน</b></p>
            <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ถังและน้ำหนักถัง</p>
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <th style="border: 1px solid black; border-collapse: collapse;">ลำดับ</th>
                <th style="border: 1px solid black; border-collapse: collapse;">ชนิดถัง</th>
                <th style="border: 1px solid black; border-collapse: collapse;">ยี่ห้อ/รุ่น</th>
                <th style="border: 1px solid black; border-collapse: collapse;">ขนาด(ลิตร)</th>
                <th style="border: 1px solid black; border-collapse: collapse;">หมายเลขถัง</th>
                <th style="border: 1px solid black; border-collapse: collapse;">น้ำหนักถัง(กิโลกรัม)</th>
            </tr>
            <tr>
                ' . $serviceRows . '
            </tr>
        </table>
        <div style="width: 100%; line-height: 0.2;">
            <p>(๔) รวมน้ำหนักถัง เครื่องอุปกรณ์และส่วนควบ อุปกรณ์หรือเครื่องป้องกัน = (๔.๑) + (๔.๒) + (๔.๓) = ...............กิโลกรัม</p>
            <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(๔.๑) รวมน้ำหนักถัง..................กิโลกรัม&nbsp;&nbsp;&nbsp;(๔.๒) รวมน้ำหนักเครื่องอุปกรณ์และส่วนควบ..............กิโลกรัม</p>
            <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(๔.๓) รวมน้ำหนักอุปกรณ์หรือเครื่องป้องกัน(ถ้ามี)..............กิโลกรัม</p>
            <p>(๕) ประเมินน้ำหนัก = (๒) + (๓) + (๔) = .......................กิโลกรัม&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../image/checkbox-true.png" alt="checkbox" style="width: 20px;">&nbsp;&nbsp;(๕)<=(๑) &nbsp;&nbsp;หรือ&nbsp;&nbsp; <img src="../image/checkbox.png" alt="checkbox" style="width: 20px;">&nbsp;&nbsp;(๕)>(๑) </p>
        </div>
        <div style="width: 100%; line-height: 0.2;">
            <p><b>ผลการพิจารณาการประเมินน้ำหนัก</b></p>
            <div style="width: 49%; float: left;">
                <table style="width: 100%;">
                    <tr>
                        <td style="line-height: 1; text-align: center;">
                            <img src="../image/checkbox-true.png" alt="checkbox" style="width: 20px; padding-left: 20px;"> ผ่าน (ไม่เกิน น้ำหนักรวม/น้ำหนักรวมสูงสุด)
                            <p >&nbsp;</p>
                            <p >&nbsp;</p>
                            <p class="fontset">ลงชื่อ..............................................</p>
                            <p >&nbsp;</p>
                            <p class="fontset">(.......................................................)</p>
                            <p>ผู้ประเมิน</p>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="width: 49%; float: left;">
                <table style="width: 100%;">
                    <tr>
                        <td style="line-height: 1; text-align: center;">
                            <img src="../image/checkbox.png" alt="checkbox" style="width: 20px; padding-left: 20px;"> ผ่าน (ไม่เกิน น้ำหนักรวม/น้ำหนักรวมสูงสุด)
                            <p >&nbsp;</p>
                            <p >&nbsp;</p>
                            <p class="fontset">ลงชื่อ..............................................</p>
                            <p >&nbsp;</p>
                            <p class="fontset">(.......................................................)</p>
                            <p class="fontset">ผู้ประเมิน</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div style="position: absolute; top: 92px; left: 115px; width: 140px; text-align: center; ">
            <strong>' . Carbon::parse($checkgas_date)->format('d/m/') . (Carbon::parse($checkgas_date)->year + 543) . '<strong>
        </div>

        <div style="position: absolute; top: 92px; left: 328px; width: 33%; text-align: center; ">
            <strong>' . $branche_Name . ' </strong>
        </div>

        <div style="position: absolute; top: 92px; left: 615px; width: 11%; text-align: center; ">
            <strong>' . '-' . ' </strong>
        </div>

        <div style="position: absolute; top: 98px; left: 77px; width: 58%; text-align: center; ">
            <p><strong>' . $branche_address . '</strong></p>
        </div>

        <div style="position: absolute; top: 98px; left: 580px; width: 15%; text-align: center; ">
            <p><strong>' . $branche_phone . ' </strong></p>
        </div>

        <div style="position: absolute; top: 124px; left: 234px; width: 23%; text-align: center; ">
            <p><strong>' . $book_number . ' </strong></p>
        </div>

        <div style="position: absolute; top: 124px; left: 470px; width: 30%; text-align: center; ">
            <p><strong>' . Carbon::parse($book_date)->format('d/m/') . (Carbon::parse($book_date)->year + 543) . ' </strong></p>
        </div>

        <div style="position: absolute; top: 150px; left: 170px; width: 33%; text-align: center;">
            <p><strong>' . $customer_name . ' </strong></p>
        </div>

        <div style="position: absolute; top: 150px; left: 480px; width: 28%; text-align: center; ">
            <p><strong>' . $customer_phone . ' </strong></p>
        </div>

        <div style="position: absolute; top: 225px; left: 156px; width: 19%; text-align: center;">
            <p><strong>' . $vehicle_LicensePlate . ' </strong></p>
        </div>

        <div style="position: absolute; top: 225px; left: 348px; width: 44%; text-align: center;">
            <p><strong>' . $vehicle_Chassis . ' </strong></p>
        </div>

        <div style="position: absolute; top: 272px; left: 210px; width: 23%; text-align: center; ">
            <strong>' . $vehicle_manual_number . ' </strong>
        </div>

        <div style="position: absolute; top: 272px; left: 425px; width: 34%; text-align: center; ">
            <strong>' . Carbon::parse($vehicle_manual_date)->format('d/m/') . (Carbon::parse($vehicle_manual_date)->year + 543) . ' </strong>
        </div>

        <div style="position: absolute; top: 300px; left: 153px; width: 7%; text-align: center; ">
            <strong>' . $vehicle_Seat . ' </strong>
        </div>

        <div style="position: absolute; top: 297px; left: 253px; width: 7%; text-align: center; ">
            <strong>' . '0' . ' </strong>
        </div>

        <div style="position: absolute; top: 279px; left: 500px; width: 7%; text-align: center; ">
            <p><strong>' . $totalSeat . ' </strong></p>
        </div>

        <div style="position: absolute; top: 326px; left: 220px; width: 11%; text-align: center; ">
            <strong>' . $vehicle_weight_all . ' </strong>
        </div>

        <div style="position: absolute; top: 352px; left: 150px; width: 11%; text-align: center; ">
            <strong>' . $vehicle_Weight . ' </strong>
        </div>

        <div style="position: absolute; top: 406px; left: 290px; width: 11%; text-align: center; ">
            <strong>' . $vehicle_weight_loaded . ' </strong>
        </div>

        <div style="position: absolute; top: 412px; left: 398px; width: 4.5%; text-align: center;">
            <p><strong>' . $totalSeat . ' </strong></p>
        </div>

        <div style="position: absolute; top: 412px; left: 485px; width: 4.5%; text-align: center;">
            <p><strong>' . $totalSeatWeight . ' </strong></p>
        </div>

        <div style="position: absolute; top: 773px; left: 212px; width: 7%; text-align: center;">
            <strong>' . $totalgas . ' </strong>
        </div>

        <div style="position: absolute; top: 773px; left: 535px; width: 6%; text-align: center;">
            <strong>' . $totalgas_item . ' </strong>
        </div>

        <div style="position: absolute; top: 797px; left: 350px; width: 6%; text-align: center;">
            <strong>' . '0' . ' </strong>
        </div>

        <div style="position: absolute; top: 746px; left: 552px; width: 6%; text-align: center;">
            <strong>' . $alltotal . ' </strong>
        </div>

        <div style="position: absolute; top: 840px; left: 260px; width: 9%; text-align: center;">
            <strong>' . $allweight . ' </strong>
        </div>

        </body>
        </html>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function certificate(Request $request, $id)
    {
        $data = Vehicles::with([
            'VehicleGas',
        ])->where('id', $id)->first();
        // return $data;

        $Rows = 0;
        $No   = 0;

        // if (! $data->VehicleGas || $data->VehicleGas->isEmpty()) {
        //     return $this->returnErrorData('No gas tank data found', 404);
        // }

        foreach ($data->VehicleGas as $vehicle) {
            $vehicleGasNumber = $vehicle->gas_number ?? 'Gas number not found';
            $No += 1;
            $Rows .= '
            <tr>
                <td style="font-size: 20px; padding-top: 10px;" colspan="2">
                    <p>ถังก๊าซใบที่ ' . $No . ' หมายเลขถัง: ' . $vehicleGasNumber . '</p>
                </td>
            </tr>
            <tr>
                <td style="border: 0.5px solid black; width: 20%; padding-left: 7px;">
                    <p style="font-size: 24px;"><strong>ลายหมายเลขถัง</strong></p>
                    <p style="font-size: 20px;"><strong>&nbsp;</strong></p>
                </td>
                <td style="border: 0.5px solid black; width: 80%; padding-top: 60px;">&nbsp;</td>
            </tr>
            ';
        }

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 15,
            'margin_bottom'     => 50,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);
        $mpdf->SetTitle('Certificate');
        $headerHTML = '<h3>หนังสือรับรองเลขที่ สธข 335..................................</h3>';
        $mpdf->SetHTMLHeader($headerHTML);
        $mpdf->AddPage();

        $html = '
        <!DOCTYPE html>
        <html lang="th">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>ถังก๊าซ สธข 335</title>
            </head>
            <body>
                <table style="width: 100%; border-collapse: collapse;">
                    ' . $Rows . '
                </table>
            </body>

        </html>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function billorder(Request $request, $id)
    {
        $number = $request->input('number');
        $data   = Branches::with([
            'Provinces',
            'Districts',
            'SubDistricts',
        ])->where('id', $id)->first();

        //Pull Data Brache
        $branche_businessName = $data->business_name ?? null;
        $branche_Address      = $data->address ?? null;
        $branche_SubDistrict  = $data->SubDistricts->name_th_prefix ?? null;
        $branche_District     = $data->Districts->name_th_prefix ?? null;
        $branche_Province     = $data->Provinces->name_th_prefix ?? null;
        $branche_PostalCode   = $data->postal_code ?? null;
        $branche_Phone        = $data->phone ?? null;
        $branche_Email        = $data->email ?? null;
        // return $data;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4-l',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 10,
            'margin_right'      => 10,
            'margin_top'        => 10,
            'margin_bottom'     => 10,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);
        $mpdf->SetTitle('ใบสั่งของ / ใบรับเงิน');
        $mpdf->AddPage();

        $html = '
        <body>
            <div style="width: 100%; border: 3px solid black;">
                <table style="width: 100%;">
                    <tr>
                        <td style="text-align: center; font-weight: bold; font-size: 22px;" colspan="3">ใบส่งของ / ใบรับเงิน</td>
                    </tr>
                    <tr>
                        <td style="width: 5%; font-size: 22px;">&nbsp;</td>
                        <td style="width: 75%; font-size: 22px;">' . $branche_businessName . '</td>
                        <td style="width: 20%; font-size: 22px;">เลขที่ ' . $number . '</td>
                    </tr>
                    <tr>
                        <td style="width: 5%; font-size: 22px;">&nbsp;</td>
                        <td style="width: 75%; font-size: 22px;">' . $branche_Address . ' ' . $branche_SubDistrict . ' ' . $branche_District . ' ' . $branche_Province . ' ' . $branche_PostalCode . '</td>
                        <td style="width: 10%; font-size: 22px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="width: 5%; font-size: 22px;">&nbsp;</td>
                        <td style="width: 75%; font-size: 22px;">' . $branche_Phone . ' ' . $branche_Email . '</td>
                        <td style="width: 10%; font-size: 22px;">&nbsp;</td>
                    </tr>
                </table>
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 70%; font-size: 26px;">&nbsp;</td>
                        <td style="width: 30%; font-size: 26px;">วันที่......................................................................................</td>
                    </tr>
                </table>
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 60%; font-size: 26px;">ชื่อลูกค้า.........................................................................................................................................................</td>
                        <td style="width: 40%; font-size: 26px;">เบอร์โทร................................................................................................</td>
                    </tr>
                </table>
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 60%; font-size: 26px;" colspan="2">ที่อยู่.............................................................................................................................................................................................................................................................</td>
                    </tr>
                </table>
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 30%; font-size: 26px;">ยี่ห้อรถ.............................................................................</td>
                        <td style="width: 70%; font-size: 26px;">ทะเบียน....................................................................................................................................................................</td>
                    </tr>
                </table>
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 100%; font-size: 26px;" colspan="2">.......................................................................................................................................................................................................................................................................</td>
                    </tr>
                </table>
                <table style="border: 1px solid black; width: 100%; border-collapse: collapse;">
                    <tr>
                        <th style="border: 1px solid black; width: 20%;" colspan="2">รายการ</th>
                        <th style="border: 1px solid black; width: 10%;">จำนวน</th>
                        <th style="border: 1px solid black; width: 20%;" colspan="2">จำนวนเงิน</th>
                        <th style="border: 1px solid black; width: 20%;" colspan="2">รายการ</th>
                        <th style="border: 1px solid black; width: 10%;">จำนวน</th>
                        <th style="border: 1px solid black; width: 20%;" colspan="2">จำนวนเงิน</th>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">NGV รถยนต์</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">รับรองความมั่นคง</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">NGV รถขนส่ง</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">ตรวจถังก๊าซ LPG</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">LPG รถยนต์</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">10*400</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">4000.00</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">พรบ./ประกัน</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">LPG รถขนส่ง</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">ค่าจัดส่ง</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">EMS</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">100.00</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">ใบอู่ NGV รถยนต์</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">อื่นๆ</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">ใบอู่ NGV รถขนส่ง</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">ใบอู่ LPG รถยนต์</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">ใบอู่ LPG รถขนส่ง</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                        <td style="border: 1px solid black; font-weight: bold; width: 15%;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 10%; text-align: center;">&nbsp;</td>
                        <td style="border: 1px solid black; width: 15%; text-align: right;">&nbsp;</td>s
                        <td style="border: 1px solid black; width: 5%;">&nbsp;</td>
                    </tr>
                </table>
                <div style="width: 100%;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 15%;">ผู้ส่งของ....................................</td>
                            <td>ผู้รับของ..............................................</td>
                            <td style="width: 5%;">&nbsp;</td>
                            <td style="width: 5%;"><img src="../image/checkbox.png" style="width: 15px; vertical-align: middle;"> สด</td>
                            <td style="width: 8%;"><img src="../image/checkbox.png" style="width: 15px; vertical-align: middle;"> บัตรเครดิต</td>
                            <td style="width: 5%;"><img src="../image/checkbox.png" style="width: 15px; vertical-align: middle;"> โอน</td>
                            <td><img src="../image/checkbox.png" style="width: 15px; vertical-align: middle;"> ลูกหนี้</td>
                        </tr>
                    </table>
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 15%;">จัดเอกสาร................................</td>
                            <td>วันที่........................................................</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td style="width: 17%;">ผู้รับเงิน.....................................</td>
                            <td style="width: 21%;" colspan="2">วันที่.....................................</td>
                            <td>&nbsp;</td>
                        </tr>
                    </table>
                </div>
            </div>
        </body>
        </html>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function Address_the_envelope(Request $request, $id)
    {
        $branch_id = $request->input('branch_id', null);

        $data = AddressEms::where('address_ems.inspection_id', $id)
            ->join('provinces', 'address_ems.province_id', '=', 'provinces.id')
            ->join('districts', 'address_ems.district_id', '=', 'districts.id')
            ->join('sub_districts', 'address_ems.subdistrict_id', '=', 'sub_districts.id')
            ->join('inspections', 'address_ems.inspection_id', '=', 'inspections.id')
        // ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('inspection_vehicles', 'inspection_vehicles.inspection_id', '=', 'inspections.id')
            ->join('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'inspections.customer_id', '=', 'customers.id')
            ->join('provinces as vehicle_provinces', 'vehicles.province_id', '=', 'vehicle_provinces.id')
            ->select(
                'address_ems.*',
                'provinces.name_th_prefix as province_name',
                'districts.name_th_prefix as district_name',
                'sub_districts.name_th_prefix as subdistrict_name',
                'inspections.*',
                'vehicles.license_plate as license_plate',
                'vehicle_provinces.name_th_prefix as vehicle_province_name',
                'customers.name as cus_name',
                'customers.phone_number1 as cus_phone'
            )
            ->first();
        // return $data;
        if (! $data) {
            return response()->json(['message' => 'ไม่พบข้อมูล ems นี้'], 404);
        }

        // return $data;
        //Pull Data Branch
        $branches = Branches::with([
            'Provinces',
            'Districts',
            'SubDistricts',
        ])->where('id', $request->branch_id)->first();

        $branche_nameBusiness = $branches->business_name ?? null;
        $branche_address      = $branches->address ?? null;
        $branche_subDistrict  = $branches->SubDistricts->name_th_prefix ?? null;
        $branche_district     = $branches->Districts->name_th_prefix ?? null;
        $branche_province     = $branches->Provinces->name_th_prefix ?? null;
        $branche_postal       = $branches->postal_code ?? null;
        $branche_phone        = $branches->phone ?? null;
        $branche_tax          = $branches->tax_id ?? null;

        //Pull Data AddressEms
        $addressEms_address     = $data->address ?? null;
        $addressEms_subDistrict = $data->SubDistricts->name_th_prefix ?? null;
        $addressEms_district    = $data->Districts->name_th_prefix ?? null;
        $addressEms_province    = $data->Provinces->name_th_prefix ?? null;
        // $addressEms_postal = $data->postal_code ?? null;
        // return $addressEms_address;
        $addressEms_zip_code  = $data->SubDistricts->zip_code ?? null;


        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            // 'format'            => 'A5-L',
            'format'            => 'A4',
            // 'default_font_size' => 14,
            // 'format' => [210, 148.5], // Width x Height in mm
            'default_font_size' => 10,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_header'     => 0,
            'margin_footer'     => 0,
        ]);
        $mpdf->SetTitle('ใบจ่ายหน้าซอง');
        $mpdf->AddPage();

        if ($data && $data->license_plate) {
            $html = '
        <body>
            <div style="width: 100%;">
                <div style="width: 100%; float: left; text-align: left;">
                    <table style="width: 100%; font-size: 18px">
                        <tr>
                            <td style="line-height: 1.2;">' . $branche_nameBusiness . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;">' . $branche_address . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;">โทร ' . $branche_phone . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;">เลขประจำตัวผู้เสียภาษี/Tax ID ' . $branche_tax . '</td>
                        </tr>
                    </table>
                    <div style="width: 100%; float: right; text-align: right; font-size: 20px; margin-top: 40px;padding-right:160px;">' . $data->license_plate . ' ' . $data->vehicle_province_name . '</div>
                </div>
                <div style="width: 55%; float: right; text-align: right;padding-right:160px;">
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid black; font-size: 22px;">
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">คุณ ' . $data->cus_name . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">ที่อยู่ ' . $addressEms_address . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $addressEms_subDistrict . ' ' . $addressEms_district . ' ' . $addressEms_province . ' ' . $branche_postal . ' '.$addressEms_zip_code.'</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">โทร. ' . $data->cus_phone . '</td>
                        </tr>
                    </table>
                </div>
            </div>
        </body>';
        } else {
            $html = '
        <body>
            <div style="width: 100%;">
                <div style="width: 100%; float: left; text-align: left;">
                    <table style="width: 100%; font-size: 18px">
                        <tr>
                            <td style="line-height: 1.2;">' . $branche_nameBusiness . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;">' . $branche_address . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;">' . $branche_subDistrict . ' ' . $branche_district . ' ' . $branche_province . ' ' . $branche_postal . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;">' . $branche_phone . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;">เลขประจำตัวผู้เสียภาษี/Tax ID  ' . $branche_tax . '</td>
                        </tr>
                    </table>
                    <div style="width: 100%; float: right; text-align: right; font-size: 20px; margin-top: 160px;">' . '&nbsp;' . '</div>
                </div>
                <div style="width: 45%; float: right; text-align: right;">
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid black; font-size: 18px">
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">คุณ ' . '&nbsp;' . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">ที่อยู่ ' . '&nbsp;' . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">&nbsp;&nbsp;&nbsp;&nbsp;' . '&nbsp;' . '</td>
                        </tr>
                        <tr>
                            <td style="line-height: 1.2;padding:3px;">โทร. ' . '&nbsp;' . '</td>
                        </tr>
                    </table>
                </div>
            </div>
        </body>';
        }
        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function pdfWorkOrder(Request $request, $id)
    {
        $data = WorkOrder::with([
            'Customer',
            'WorkOrderServices',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        ])->where('id', $id)->first();
        // return $data;

        if (! $data) {
            return $this->returnError('ไม่พบข้อมูล');
        }

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 20,
            'margin_right'      => 20,
            'margin_top'        => 20,
            'margin_bottom'     => 20,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('ใบแจ้งซ่อมรถ');
        $mpdf->AddPage();

        $html = view('export.WorkOrder', [
            'Branch'            => $data->Branches,
            'WorkOrder'         => $data,
            'WorkOrderServices' => $data->WorkOrderServices,
        ])->render();

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('receipt.pdf', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=receipt.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function gas_report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $baseQuery = DB::table('check_gases')
            ->selectRaw('
            DISTINCT check_gases.id,
            check_gases.customer_no,
            check_gases.customer_name,
            check_gases.date,
            check_gases.time,
            check_gases.engineer_no,
            inspection_vehicles.license_plate,
            inspection_vehicles.provinces,
            check_gases.no_gas,
            check_gases.brand_gas,
            check_gases.install_no,
            check_gases.service_or,
            vehicle_brands.name as vehicle_brand,
            inspections.branch_id,
            GROUP_CONCAT(DISTINCT cha_nos.cha_no) as cha_no_group
        ')
            ->leftJoin('inspection_vehicles', 'check_gases.inspection_vehicle_id', '=', 'inspection_vehicles.id')
            ->leftJoin('inspections', 'inspection_vehicles.inspection_id', '=', 'inspections.id') // เพิ่ม join
            ->leftJoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id') // Corrected here
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->leftJoin('cha_nos', 'check_gases.id', '=', 'cha_nos.check_gas_id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->where('inspections.branch_id',$request->branch_id)
            ->whereNull('check_gases.deleted_at')
            ->whereBetween('check_gases.date', [$request->start_date, $request->end_date])
            ->groupBy(
                'check_gases.id',
                'check_gases.customer_no',
                'check_gases.customer_name',
                'check_gases.date',
                'check_gases.time',
                'check_gases.engineer_no',
                'inspection_vehicles.license_plate',
                'inspection_vehicles.provinces',
                'check_gases.no_gas',
                'check_gases.brand_gas',
                'check_gases.install_no',
                'check_gases.service_or',
                'vehicle_brands.name',
                'inspections.branch_id'
            )
            ->get();

        $startDate = Carbon::parse($request->start_date);
        $endDate   = Carbon::parse($request->end_date);

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
        ]);

        $html = '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
        $html .= '<tr>
                <td colspan="14" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                    รายงาน ตรวจแก๊ส วันที่ ' . $startDate->format('d/m/') . ($startDate->year + 543) . ' ถึง ' . $endDate->format('d/m/') . ($endDate->year + 543) . '
                </td>
            </tr>';
        $html .= '<tr>
        <th>ลำดับ</th>
        <th>รหัสร้านค้า</th>
        <th>ชื่อลูกค้า</th>
        <th>เลขที่ตรวจ</th>
        <th>วันที่</th>
        <th>เวลา</th>
        <th>เลขทะเบียนวิศวกร</th>
        <th>เลขทะเบียนรถ</th>
        <th>จังหวัด</th>
        <th>ยี่ห้อรถ</th>
        <th>ยี่ห้อถังก๊าซ</th>
        <th>หมายเลขถังก๊าซ</th>
        <th>เลขใบติดตั้ง</th>
        <th>เลขต่อถัง</th>
        </tr>';

        if ($baseQuery->isEmpty()) {
            $html .= '<tr><td colspan="14" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
        } else {
            $index = 1;
            foreach ($baseQuery as $record) {
                $formattedDate = $record->date ? Carbon::parse($record->date)->format('d/m/') . (Carbon::parse($record->date)->year + 543) : '-';
                $formattedTime = $record->time ? Carbon::parse($record->time)->format('H:i') : '-';

                $html .= '
                    <tr>
                        <td align="center">' . $index . '</td>
                        <td align="center">' . ($record->customer_no ?? '-') . '</td>
                        <td align="center">' . ($record->customer_name ?? '-') . '</td>
                        <td align="center">' . ($record->service_or ?? '-') . '</td>
                        <td align="center">' . $formattedDate . '</td>
                        <td align="center">' . $formattedTime . '</td>
                        <td align="center">' . ($record->engineer_no ?? '-') . '</td>
                        <td align="center">' . ($record->license_plate ?? '-') . '</td>
                        <td align="center">' . ($record->provinces ?? '-') . '</td>
                        <td align="center">' . ($record->vehicle_brand ?? '-') . '</td>
                        <td align="center">' . ($record->brand_gas ?? '-') . '</td>
                        <td align="center">' . ($record->no_gas ?? '-') . '</td>
                        <td align="center">' . ($record->install_no ?? '-') . '</td>
                        <td align="center">' . ($record->cha_no_group ?? '-') . '</td>
                    </tr>';
                $index++;
            }
        }

        $html .= '</table>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=report.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function reportdaily(Request $request)
    {
        $branchId    = $request->input('branch_id', null);
        $stardate    = $request->input('stardate', Carbon::now()->toDateString());
        $enddate     = $request->input('enddate', Carbon::now()->toDateString());
        $customer_id = $request->input('customer_id', null);

        if (! $stardate && ! $enddate) {
            return $this->returnError('Date parameter is missing');
        }

        $serviceTemplate = Services::select('id', 'name')
            ->whereBetween('id', [1, 16])
            ->get();

        $InspectionVehicles = InspectionVehicles::with(['vehicleServiceTransaction', 'inspections'])
            ->whereHas('inspections', function ($query) use ($stardate, $enddate) {
                $query->whereBetween('created_at', [$stardate . ' 00:00:00', $enddate . ' 23:59:59']);
            })
            ->get();

        $data = [];

        foreach ($InspectionVehicles as $inspectionVehicle) {
            // สร้างสำเนา `$serviceTemplate` ใหม่เพื่อไม่ให้ค่าถูกแชร์ข้าม `inspections.no`
            $services = $serviceTemplate->map(function ($s) {
                return (object) [
                    'id'     => $s->id,
                    'name'   => $s->name,
                    'amount' => 0,
                ];
            });

            // คำนวณยอดเงินของ service เฉพาะของ inspection นี้
            foreach ($inspectionVehicle->vehicleServiceTransaction as $transaction) {
                foreach ($services as $service) {
                    if ($transaction->service_id == $service->id) {
                        $service->amount += $transaction->service_price;
                    }
                }
            }

            $data[] = [
                'no'                  => $inspectionVehicle->inspections->no,
                'date'                => $inspectionVehicle->inspections->date,
                'license_plate'       => $inspectionVehicle->license_plate,
                'name'                => $inspectionVehicle->name,
                'discount_before_vat' => $inspectionVehicle->inspections->discount_before_vat,
                'discount_after_vat'  => $inspectionVehicle->inspections->discount_after_vat,
                'total_vatcal'        => $inspectionVehicle->inspections->total_vatcal,
                'total_nonvatcal'     => $inspectionVehicle->inspections->total_nonvatcal,
                'services'            => $services,
            ];
        }

        return $this->returnSuccess('Success', $data);
    }

    public function reportPDFexpenseaday(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        $datas = Inspections::select('no', 'date', 'remark')
            ->selectRaw('SUM(total_vatcal + total_nonvatcal) as total_cost')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })->groupBy('no', 'date', 'remark');
        $data = $datas->get();

        $branchId = trim($request->input('branch_id', null));

        $query = Cost::select('date', 'name', 'price', 'detail')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });

        $costs = $query->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('DayExpense Report');
        $mpdf->AddPage();

        $totalAmount  = 0;
        $totalrows    = 0;
        $runningTotal = 0;
        $countTotal   = 0;
        $totalCount   = 0;

        $html = '
        <style>
            .no-data {
                text-align: center;
                color: #888;
                padding: 20px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
            }
        </style>

        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <td colspan="6" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รวมค่าใช้จ่าย
                    </td>
                </tr>
                <tr>
                    <th style="width: 5%;">ลำดับ</th>
                    <th style="width: 15%;">วันที่</th>
                    <th style="width: 20%;">รายการ</th>
                    <th style="width: 15%;">ยอดเงิน</th>
                    <th style="width: 15%;">ค่าใช้จ่าย</th>
                    <th style="width: 30%;">รายละเอียด</th>
                </tr>
            </thead>';
        if ($data->isEmpty()) {
            $html .= '<tr>
                        <td colspan="6" class="no-data">ไม่มีรายการ</td>
                    </tr>';
        } else {
            foreach ($data as $index => $row) { // เปลี่ยนตัวแปรจาก $data เป็น $row
                $totalrows += $row->total_cost;
                $runningTotal += $row->total_cost;
                $html .= '<tr>';
                $html .= '<td>' . ($index + 1) . '</td>';
                $html .= '<td>' . $row->date . '</td>';
                $html .= '<td>' . $row->no . '</td>';
                $html .= '<td>' . number_format($row->total_cost, 2) . '</td>';
                $html .= '<td>' . number_format($runningTotal, 2) . '</td>';
                $html .= '<td>' . ($row->remark ?? '-') . '</td>'; // ป้องกัน null
                $html .= '</tr>';
            }
        }

        foreach ($costs as $index => $cost) {
            $countTotal = $runningTotal - $cost->price;
            $totalCount = $countTotal;
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $cost->date . '</td>';
            $html .= '<td>' . $cost->name . '</td>';
            $html .= '<td>' . number_format($cost->price, 2) . '</td>';
            $html .= '<td>' . number_format($countTotal, 2) . '</td>';
            $html .= '<td>' . $cost->detail . '</td>';
            $html .= '</tr>';

            $totalAmount += $cost->price;
        }

        if ($costs->isEmpty()) {
            $html .= '<tr>
                <td colspan="6" class="no-data">ไม่มีรายการ</td>
            </tr>';
        }

        // Add total row
        $html .= '<tr>
            <td colspan="3" style="text-align: right; font-weight: bold;">รวม:</td>
            <td style="font-weight: bold;">' . number_format($totalCount, 2) . '</td>
            <td style="font-weight: bold;">' . number_format($countTotal, 2) . '</td>
            <td>&nbsp;</td>
        </tr>';

        $html .= '</table>';

        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

    public function report_aday_cal(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $data = Inspections::select(
            'inspections.no',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.registration_date',
            'inspection_vehicles.provinces',
            'customers.name',
            'inspections.date',
            'inspections.discount',
            'Installments.paid_price',
            'Installments.after_price',
            'Installments.paid_type',
            DB::raw("CASE WHEN inspections.is_pass = 1 THEN 'ผ่าน' WHEN inspections.is_pass = 0 THEN 'ไม่ผ่าน' ELSE '-' END as is_pass"),
            'inspections.result',
            DB::raw("CONCAT('[', GROUP_CONCAT(
                JSON_OBJECT('service_id', vehicleserviceTransactions.service_id,
                            'service_price', vehicleserviceTransactions.service_price)
                SEPARATOR ','), ']') as services")
        )
            ->leftjoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions as vehicleserviceTransactions', 'inspection_vehicles.id', '=', 'vehicleserviceTransactions.inspection_vehicle_id')
            ->leftJoin('services', 'vehicleserviceTransactions.service_id', '=', 'services.id')
            ->leftjoin('Installments', 'inspections.id', '=', 'Installments.inspection_id')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspections.date', [$startDate, $endDate]);
            })
            ->whereNull('inspections.deleted_at')
            ->whereNull('Installments.deleted_at')
            ->groupBy(
                'inspections.no',
                'inspection_vehicles.license_plate',
                'inspection_vehicles.registration_date',
                'inspection_vehicles.provinces',
                'customers.name',
                'inspections.date',
                'inspections.result',
                'inspections.discount',
                'inspections.is_pass',
                'Installments.paid_price',
                'Installments.after_price',
                'Installments.paid_type',
            )
            ->get();

        $groupedData = [];

        foreach ($data as $item) {
            $no = $item->no;

            // ถ้า `no` ยังไม่มีใน array ให้เพิ่มใหม่
            if (! isset($groupedData[$no])) {
                $groupedData[$no] = [
                    'no'                    => $item->no,
                    'license_plate'         => $item->license_plate,
                    'registration_date'     => $item->registration_date,
                    'provinces'             => $item->provinces,
                    'name'                  => $item->name,
                    'date'                  => $item->date,
                    'discount'              => $item->discount,
                    'paid_price'            => [], // เปลี่ยนเป็น array ก่อน sum
                    'after_price'           => null,
                    'paid_type'             => [],
                    'is_pass'               => $item->is_pass,
                    'result'                => $item->result,
                    'services'              => json_decode($item->services, true) ?? [], // ถ้าไม่มี services ให้เป็น []
                    'photo_vehicle_gas_url' => $item->photo_vehicle_gas_url,
                ];
            }
            $paidType = $item->paid_type;
            if (! isset($groupedData[$no]['paid_type'][$paidType])) {
                $groupedData[$no]['paid_type'][$paidType] = 0;
            }
            $groupedData[$no]['paid_type'][$paidType] += $item->paid_price;

            // เพิ่ม `paid_price`, `before_price`, `paid_type` เข้าไปใน array
            $groupedData[$no]['paid_price'][] = $item->paid_price;
            $groupedData[$no]['after_price']  = $item->after_price; // ใช้ค่าล่าสุดของ `after_price`
                                                                    // $groupedData[$no]['paid_type'][] = $item->paid_type;

            // รวม `services` โดยไม่ให้ค่าซ้ำ
            $existingServices = array_column($groupedData[$no]['services'], 'service_id');
            foreach (json_decode($item->services, true) as $service) {
                if (! in_array($service['service_id'], $existingServices)) {
                    $groupedData[$no]['services'][] = $service;
                }
            }
        }

        // แปลง `paid_price`, `before_price`, `after_price`, `paid_type` เป็น array ของ unique values
        foreach ($groupedData as &$group) {

            $totalPaidPrice = 0; // ผลรวมทั้งหมดของ paid_price
            foreach ($group['paid_price'] as $paidType => $prices) {

                if (! isset($group['paid_price']['paid_cash'])) {
                    $group['paid_price']['paid_cash'] = 0; // ถ้าไม่มี paid_cash ให้เป็น 0
                }
                if (! isset($group['paid_price']['paid_tran'])) {
                    $group['paid_price']['paid_tran'] = 0; // ถ้าไม่มี paid_tran ให้เป็น 0
                }
                if (! isset($group['paid_price']['credit'])) {
                    $group['paid_price']['credit'] = 0; // ถ้าไม่มี credit ให้เป็น 0
                }
                // ตรวจสอบว่า $prices เป็น array หรือไม่
                if (is_array($prices)) {
                    // ผลรวมของ paid_price สำหรับแต่ละ paid_type (กรณีที่เป็น array)
                    $group['paid_price'][$paidType] = array_sum($prices);
                } else {
                    // ถ้าไม่ใช่ array (กรณีที่เป็น float), เก็บค่าไว้เลย
                    $group['paid_price'][$paidType] = $prices;
                }

                // รวมผลทั้งหมดของ paid_price
                $totalPaidPrice += $group['paid_price'][$paidType];
            }

            $group['paid_price']  = array_sum($group['paid_price']); // คำนวณผลรวม paid_price
            $group['after_price'] = $group['after_price'];

            $group['paid_type'] = $group['paid_type'];

            $group['paid_type'] = array_merge($group['paid_type'], [
                'paid_cash' => isset($group['paid_type']['paid_cash']) ? $group['paid_type']['paid_cash'] : 0,
                'paid_tran' => isset($group['paid_type']['paid_tran']) ? $group['paid_type']['paid_tran'] : 0,
                'credit'    => isset($group['paid_type']['credit']) ? $group['paid_type']['credit'] : 0,
            ]);
        }

        // แปลงเป็น array เพื่อให้ JSON ออกมาเป็น array ไม่ใช่ object
        $groupedData = array_values($groupedData);

        // ถ้าต้องการแสดงผล
        // return response()->json($groupedData);

        $query = Invoice::select(
            'invoices.id',
            'invoices.date',
            'customers.name',
            'invoice_payments.paid_type',
            'invoice_payments.paid_price',
            'invoice_payments.after_price',
        )
            ->leftjoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('invoice_payments', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->groupBy(
                'invoices.id',
                'invoices.date',
                'customers.name',
                'invoice_payments.paid_type',
                'invoice_payments.paid_price',
                'invoice_payments.after_price',
            )
            ->get();
        $groupedQuery = [];
        foreach ($query as $item) {
            $no = $item->id;

            if (! isset($groupedQuery[$no])) {
                $groupedQuery[$no] = [
                    'id'          => $item->id,
                    'date'        => $item->date,
                    'name'        => $item->name,
                    'paid_type'   => [
                        'paid_cash' => 0,
                        'paid_tran' => 0,
                        'credit'    => 0,
                    ],                  // Initialize paid_type values as 0
                    'paid_price'  => 0, // Initialize paid_price as 0
                    'after_price' => null,
                ];
            }
            $paidType = $item->paid_type;

            if ($paidType && isset($groupedQuery[$no]['paid_type'][$paidType])) {
                // Sum the paid_price based on the paid_type
                $groupedQuery[$no]['paid_type'][$paidType] += $item->paid_price;
                $groupedQuery[$no]['paid_price'] += $item->paid_price; // Accumulate total paid_price
            } elseif ($paidType) {
                // Handle the case where paid_type is unexpected or invalid
                $groupedQuery[$no]['paid_type'][$paidType] = ($groupedQuery[$no]['paid_type'][$paidType] ?? 0) + $item->paid_price;
                  // $paidType = 'other'; // Assign a default 'other' or handle as necessary
                  // $groupedQuery[$no]['paid_type'][$paidType] = ($groupedQuery[$no]['paid_type'][$paidType] ?? 0) + $item->paid_price;
            } // Set the latest after_price
        }

        $formattedResult = [];
        foreach ($groupedQuery as $group) {
            $formattedResult[] = [
                'id'          => $group['id'],
                'date'        => $group['date'],
                'name'        => $group['name'],
                'paid_type'   => $group['paid_type'],
                'paid_price'  => $group['paid_price'],
                'after_price' => $group['after_price'],
            ];
        }

        $querycost = Cost::select('date', 'name', 'price', 'type', 'detail')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });

        $result = [
            'fie'  => $groupedData,
            'doc'  => $formattedResult,
            'cost' => $querycost->get(),
        ];

        return $result;

        // return $query;
    }

    public function exportExcel_reportcost(request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $branchId = $request->input('branch_id', null);

        $query = Cost::select('date', 'name', 'price', 'type', 'detail', 'paid_type')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });

        $cost = $query->get();
        // return $cost;

        $cashIncome      = [];
        $cashExpense     = [];
        $creditIncome    = [];
        $creditExpense   = [];
        $transferIncome  = [];
        $transferExpense = [];

        foreach ($cost as $item) {
            switch ($item['paid_type']) {
                case 'paid_cash': // เงินสด
                    if ($item['type'] === 'income') {
                        $cashIncome[] = $item;
                    } else {
                        $cashExpense[] = $item;
                    }
                    break;
                case 'credit': // บัตรเครดิต
                    if ($item['type'] === 'income') {
                        $creditIncome[] = $item;
                    } else {
                        $creditExpense[] = $item;
                    }
                    break;
                case 'paid_tran': // โอนเงิน
                    if ($item['type'] === 'income') {
                        $transferIncome[] = $item;
                    } else {
                        $transferExpense[] = $item;
                    }
                    break;
            }
        }

        // คืนค่าหรือใช้งานตัวแปรเหล่านี้ตามต้องการ
        $costs = [
            'cashIncome'      => $cashIncome,
            'cashExpense'     => $cashExpense,
            'creditIncome'    => $creditIncome,
            'creditExpense'   => $creditExpense,
            'transferIncome'  => $transferIncome,
            'transferExpense' => $transferExpense,
        ];
        // return $costs;

        $serviceTemplate = Services::select('id', 'name')
            ->whereBetween('id', [1, 16])
            ->get();

        $InspectionVehicles = InspectionVehicles::with(['vehicleServiceTransaction', 'inspections'])
            ->whereHas('inspections', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->join('installments', 'inspection_vehicles.inspection_id', '=', 'installments.inspection_id')
            ->select('inspection_vehicles.*', 'installments.after_price', 'installments.paid_type')
        // ->groupBy('inspection_vehicles.inspection_id')
            ->distinct('installments.inspection_id')
            ->get();

        $data  = [];
        $entry = [];
        // ตัวแปรแยกตาม paid_type
        $inspecpaidCash = [];
        $inspeccredit   = [];
        $inspecpaidTran = [];

        foreach ($InspectionVehicles as $inspectionVehicle) {
            // สร้างสำเนา `$serviceTemplate` ใหม่เพื่อไม่ให้ค่าถูกแชร์ข้าม `inspections.no`
            $services = $serviceTemplate->map(function ($s) {
                return (object) [
                    'id'     => $s->id,
                    'name'   => $s->name,
                    'amount' => 0,
                ];
            });

            // คำนวณยอดเงินของ service เฉพาะของ inspection นี้
            foreach ($inspectionVehicle->vehicleServiceTransaction as $transaction) {
                foreach ($services as $service) {
                    if ($transaction->service_id == $service->id) {
                        $service->amount += $transaction->service_price;
                    }
                }
            }

            $entry[] = [
                'no'                  => $inspectionVehicle->inspections->no,
                'date'                => $inspectionVehicle->inspections->date,
                'license_plate'       => $inspectionVehicle->license_plate,
                'name'                => $inspectionVehicle->name,
                'discount'            => $inspectionVehicle->discount,
                'discount_before_vat' => $inspectionVehicle->inspections->discount_before_vat,
                'discount_after_vat'  => $inspectionVehicle->inspections->discount_after_vat,
                'total_vatcal'        => $inspectionVehicle->inspections->total_vatcal,
                'total_nonvatcal'     => $inspectionVehicle->inspections->total_nonvatcal,
                'services'            => $services,
                'paid_type'           => $inspectionVehicle->paid_type,
            ];
        }
        // return $entry;

        // แยกข้อมูลตาม paid_type
        foreach ($entry as $inspectionVehicle) {
            if ($inspectionVehicle['paid_type'] == 'paid_cash') {
                $inspecpaidCash[] = $inspectionVehicle;
            } elseif ($inspectionVehicle['paid_type'] == 'credit') {
                $inspeccredit[] = $inspectionVehicle;
            } elseif ($inspectionVehicle['paid_type'] == 'paid_tran') {
                $inspecpaidTran[] = $inspectionVehicle;
            }
        }

        $data = [
            'inspecpaid_cash' => $inspecpaidCash,
            'inspeccredit'    => $inspeccredit,
            'inspecpaid_tran' => $inspecpaidTran,
        ];

        $Invoice = Invoice::with(
            'quotation',
            'invoiceServices',
            'invoicePayment'
        )
            ->where(function ($query) use ($startDate, $endDate) {
                // ตรวจสอบว่ามีค่า startDate และ endDate ก่อน
                if (! empty($startDate) && ! empty($endDate)) {
                    // กำหนดเงื่อนไข whereBetween เพื่อดึงข้อมูลในช่วงวันที่
                    $query->whereBetween('date', [$startDate, $endDate]);
                }
            })
            ->where('status', 'paid') // กรองเฉพาะ status = paid
            ->get();                  // ดึงข้อมูลทั้งหมดตามเงื่อนไข
                                  // $Invoiceservice = InvoicePayment::get();
                                  // return $Invoiceservice;

        foreach ($Invoice as $Invoices) {
            // สร้างสำเนา `$serviceTemplate` ใหม่เพื่อไม่ให้ค่าถูกแชร์ข้าม `inspections.no`
            $services1 = $serviceTemplate->map(function ($s) {
                return (object) [
                    'id'     => $s->id,
                    'name'   => $s->name,
                    'amount' => 0,
                ];
            });

            // คำนวณยอดเงินของ service เฉพาะของ inspection นี้
            foreach ($Invoices->invoiceServices as $transaction) {
                foreach ($services1 as $service1) {
                    if ($transaction->service_name == $service1->name) {
                        $service1->amount += $transaction->service_price;
                    }
                }
            }
            // $paidTypes = $Invoices->invoicePayment->pluck('paid_type')->toArray();
            $paidTypes1 = $Invoices->invoicePayment->map(function ($payment) {
                return [
                    'id'           => $payment->id,
                    'invoice_id'   => $payment->invoice_id,
                    'paid_type'    => $payment->paid_type,
                    'paid_price'   => $payment->paid_price,
                    'before_price' => $payment->before_price,
                    'after_price'  => $payment->after_price,
                    'description'  => $payment->description,
                    'created_at'   => $payment->created_at,
                ];
            });

            $entry1[] = [
                'no'           => $Invoices->no,
                'date'         => $Invoices->date,
                'name'         => $Invoices->customer_name,
                'discount'     => $Invoices->discount,
                'total'        => $Invoices->total,
                'tax'          => $Invoices->tax,
                'total_amount' => $Invoices->total_amount,
                'services'     => $services1,
                'paid_type'    => $paidTypes1,
                'paid_type1'   => [
                    'paid_cash' => $paidTypes1->filter(function ($payment) {
                        return $payment['paid_type'] === 'paid_cash';
                    }),
                    'credit'    => $paidTypes1->filter(function ($payment) {
                        return $payment['paid_type'] === 'credit';
                    }),
                    'paid_tran' => $paidTypes1->filter(function ($payment) {
                        return $payment['paid_type'] === 'paid_tran';
                    }),
                ],
            ];
        }
        // return $entry1;

        $InvoicepaidCash = [];
        $Invoicecredit   = [];
        $InvoicepaidTran = [];

        // แยกข้อมูลตาม paid_type
        foreach ($entry1 as $Invoicetrans) {
            foreach ($Invoicetrans['paid_type'] as $paidType1) {
                if ($paidType1['paid_type'] === 'paid_cash') {
                    $InvoicepaidCash[] = $Invoicetrans;
                } elseif ($paidType1['paid_type'] === 'credit') {
                    $Invoicecredit[] = $Invoicetrans;
                } elseif ($paidType1['paid_type'] === 'paid_tran') {
                    $InvoicepaidTran[] = $Invoicetrans;
                }
            }
        }
        // return [$InvoicepaidCash, $Invoicecredit,$InvoicepaidTran];
        $invoices1 = [
            'Invoicepaid_cash' => $InvoicepaidCash,
            'Invoicecredit'    => $Invoicecredit,
            'Invoicepaid_tran' => $InvoicepaidTran,
        ];
        // return $entry1;
        // return $Invoice;
        // return [$data, $costs, $startDate, $endDate];

        $result = new CostdayExport($data, $costs, $invoices1, $startDate, $endDate);
        // return Excel::download($result, 'Costday.xlsx');
        return Excel::download($result, 'Costday.xlsx');
    }

    public function query_income_old(request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $cost = Cost::with('ExpenseType')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->get();

        $Inspections = Inspections::with(['inspectionVehicles.vehicle', 'installments', 'Customers'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
           ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereHas('inspectionVehicles.vehicle', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('tax_renewal_date', [$startDate, $endDate]);
                });
            })
            ->where('status', ['finish', 'overdue'])
            ->get();

        $invoices = Invoice::with(['invoiceServices', 'invoicePayment'])
            ->leftJoin('tax_invoices', 'invoices.id', '=', 'tax_invoices.invoice_id')
            ->whereNull('tax_invoices.id')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('invoices.branche_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->where('invoices.status', 'paid')
            ->select('invoices.*')
            ->get();

        $tax_invoices = TaxInvoice::with(['Customers', 'taxInvoiceService', 'taxInvoiceAllNos'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branche_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->where('status', 'approved')
            ->get();

        $fix = WorkOrder::with(['WorkOrderServices', 'Customer'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branches_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->where('status', 'success')
            ->get();

        $inspections_debt = DB::table('inspection_vehicles')
            ->join('inspections', 'inspection_vehicles.inspection_id', '=', 'inspections.id')
            ->join('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', function ($join) {
                $join->on('vechicle_services_transactions.inspection_vehicle_id', '=', 'inspection_vehicles.id')
                    ->where('vechicle_services_transactions.service_id', '=', 3);
            })
            ->wherenotnull('inspections.transaction_status')
            ->where('inspections.transaction_status', '=', 'closedebt')
            ->whereRaw('inspections.paytrue - vechicle_services_transactions.service_price != 0')
            ->select(
                'inspections.*',
                'inspections.no as bill_number',
                'vehicles.license_plate',
                'vehicles.registration_date',
                DB::raw("CONCAT(customers.name, ' ', customers.lname) as customer_name"),
                'vechicle_services_transactions.service_price',
                DB::raw("(inspections.paytrue - vechicle_services_transactions.service_price) as payextra")
            );

        if (! empty($startDate) && ! empty($endDate)) {
            $inspections_debt->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! empty($branchId)) {
            $inspections_debt->where('inspections.branch_id', $branchId);
        }
        $inspections_debt = $inspections_debt->get();

        $reportData = [];

        $costdata                 = [];
        $costdata_cash            = [];
        $costdata_cash_income     = [];
        $costdata_cash_expenses   = [];
        $costdata_tran            = [];
        $costdata_tran_income     = [];
        $costdata_tran_expenses   = [];
        $costdata_cardit          = [];
        $costdata_cardit_income   = [];
        $costdata_cardit_expenses = [];

        // Calculate total commission from inspections
        $totalTax = 0;
        foreach ($Inspections as $inspection) {
            $totalTax += ($inspection->commission ?? 0) + ($inspection->paytrue ?? 0);
        }

        if ($totalTax > 0) {
            $commissionExpense = [
                'date'          => $startDate ? $startDate->format('Y-m-d') : date('Y-m-d'),
                'document_no'   => '-',
                'document_type' => 'Cost',
                'name'          => 'ค่าใช้จ่ายภาษี',
                'customer_name' => 'ค่าใช้จ่ายภาษี',
                'type'          => 'รายจ่าย',
                'category'      => 'ค่าใช้จ่ายภาษี',
                'amount'        => -1 * $totalTax, // Negative amount for expense
                'paid_type'     => 'paid_cash', // Add to cash expenses
                'branch_id'     => $branchId,
                'status'        => '-',
                'reference_id'  => 0,
            ];

            $reportData[] = $commissionExpense;
            $costdata[] = $commissionExpense;
            $costdata_cash[] = $commissionExpense;
            $costdata_cash_expenses[] = $commissionExpense;
        }

        foreach ($cost as $item) {
            $reportRow = [
                'date'          => $item->date,
                'document_no'   => '-',
                'document_type' => 'Cost',
                'name'          => $item->name,
                'customer_name' => $item->detail,
                'type'          => $item->type == 'income' ? 'รายรับ' : 'รายจ่าย',
                'category'      => $item->expense_type->name ?? '-',
                'amount'        => $item->type == 'income' ? $item->price : -1 * $item->price, // รายจ่ายติดลบ
                'paid_type'     => $item->paid_type,
                'branch_id'     => $item->branch_id,
                'status'        => '-',
                'reference_id'  => $item->id,
            ];
            $reportData[] = $reportRow;
            $costdata[]   = $reportRow;
            if ($item->paid_type == 'paid_cash') {
                $costdata_cash[] = $reportRow;
                if ($item->type == 'income') {
                    $costdata_cash_income[] = $reportRow;
                } else {
                    $costdata_cash_expenses[] = $reportRow;
                }
            } elseif ($item->paid_type == 'paid_tran') {
                $costdata_tran[] = $reportRow;
                if ($item->type == 'income') {
                    $costdata_tran_income[] = $reportRow;
                } else {
                    $costdata_tran_expenses[] = $reportRow;
                }
            } elseif ($item->paid_type == 'credit') {
                $costdata_cardit[] = $reportRow;
                if ($item->type == 'income') {
                    $costdata_cardit_income[] = $reportRow;
                } else {
                    $costdata_cardit_expenses[] = $reportRow;
                }
            }
        }

        $Inspectionsdata               = [];
        $Inspectionsdata_cash          = [];
        $Inspectionsdata_cash_vat      = [];
        $Inspectionsdata_cash_nonvat   = [];
        $Inspectionsdata_tran          = [];
        $Inspectionsdata_tran_vat      = [];
        $Inspectionsdata_tran_nonvat   = [];
        $Inspectionsdata_cardit        = [];
        $Inspectionsdata_cardit_vat    = [];
        $Inspectionsdata_cardit_nonvat = [];
        // Process inspections data
        foreach ($Inspections as $item) {
            if (! empty($item->installments)) {
                $paymentSummary = [];

                // วนลูปผ่านการชำระเงินแต่ละครั้ง
                foreach ($item->installments as $installment) {
                    $paidType = $installment->paid_type;
                    $vatType  = $installment->type;

                    if (! isset($paymentSummary[$paidType])) {
                        $paymentSummary[$paidType] = [
                            'vat'    => 0,
                            'nonvat' => 0,
                        ];
                    }

                    $paymentSummary[$paidType][$vatType] += $installment->paid_price;
                }

                // สร้างแถวข้อมูลสำหรับแต่ละประเภทการชำระ
                foreach ($paymentSummary as $paidType => $vatData) {
                    foreach ($vatData as $vatType => $amount) {
                        // Skip if amount is zero
                        if ($amount == 0) {
                            continue;
                        }

                        $reportRow = [
                            'date'          => $item->date,
                            'document_no'   => $item->no,
                            'document_type' => 'Inspection',
                            'name'          => 'ตรวจรถ',
                            'license_plate' => isset($item->inspectionVehicles[0]) ? $item->inspectionVehicles[0]->license_plate : '-',
                            'customer_name' => isset($item->customers) ? $item->customers->name : '-',
                            'type'          => 'รายรับ',
                            'category'      => 'ตรวจสภาพ',
                            'amount'        => $amount,
                            'paid_type'     => $paidType,
                            'vat_type'      => $vatType,
                            'branch_id'     => $item->branch_id,
                            'status'        => $item->status,
                            'reference_id'  => $item->id,
                        ];

                        $reportData[]      = $reportRow;
                        $Inspectionsdata[] = $reportRow;

                        // Categorize by payment type
                        if ($paidType == 'paid_cash') {
                            $Inspectionsdata_cash[] = $reportRow;

                            // Further categorize by VAT type
                            if ($vatType == 'vat') {
                                $Inspectionsdata_cash_vat[] = $reportRow;
                            } else if ($vatType == 'nonvat') {
                                $Inspectionsdata_cash_nonvat[] = $reportRow;
                            }
                        } elseif ($paidType == 'paid_tran') {
                            $Inspectionsdata_tran[] = $reportRow;

                            if ($vatType == 'vat') {
                                $Inspectionsdata_tran_vat[] = $reportRow;
                            } else if ($vatType == 'nonvat') {
                                $Inspectionsdata_tran_nonvat[] = $reportRow;
                            }
                        } elseif ($paidType == 'credit') {
                            $Inspectionsdata_cardit[] = $reportRow;

                            if ($vatType == 'vat') {
                                $Inspectionsdata_cardit_vat[] = $reportRow;
                            } else if ($vatType == 'nonvat') {
                                $Inspectionsdata_cardit_nonvat[] = $reportRow;
                            }
                        }
                    }
                }
            }
        }
        $Invoicesdata        = [];
        $Invoicesdata_cash   = [];
        $Invoicesdata_tran   = [];
        $Invoicesdata_cardit = [];

        // Process invoices data
        foreach ($invoices as $item) {
            $customerName = $item->customer_name ?? ($item->quotation->Customers->name ?? '-');

            if (! empty($item->invoicePayment)) {
                $paymentSummary = [];

                foreach ($item->invoicePayment as $payment) {
                    $paidType = $payment->paid_type;

                    if (! isset($paymentSummary[$paidType])) {
                        $paymentSummary[$paidType] = 0;
                    }

                    $paymentSummary[$paidType] += $payment->paid_price;
                }

                foreach ($paymentSummary as $type => $amount) {
                    $reportRow = [
                        'date'          => $item->date,
                        'document_no'   => $item->no,
                        'document_type' => 'Invoice',
                        'name'          => 'ใบแจ้งหนี้',
                        'customer_name' => $customerName,
                        'type'          => 'รายรับ',
                        'category'      => 'ใบแจ้งหนี้',
                        'amount'        => $amount,
                        'paid_type'     => $type,
                        'branch_id'     => $item->branche_id,
                        'status'        => $item->status,
                        'reference_id'  => $item->id,
                    ];
                    $reportData[]   = $reportRow;
                    $Invoicesdata[] = $reportRow;
                    if ($type == 'paid_cash') {
                        $Invoicesdata_cash[] = $reportRow;
                    } elseif ($type == 'paid_tran') {
                        $Invoicesdata_tran[] = $reportRow;
                    } elseif ($type == 'credit') {
                        $Invoicesdata_cardit[] = $reportRow;
                    }
                }
            }
        }

        $tax_invoicesdata        = [];
        $tax_invoicesdata_cash   = [];
        $tax_invoicesdata_tran   = [];
        $tax_invoicesdata_cardit = [];
        // Process tax invoices data
        foreach ($tax_invoices as $item) {
            $customerName = $item->customer_name ?? ($item->customers->name ?? '-');

            $reportRow = [
                'date'          => $item->date,
                'document_no'   => $item->no,
                'document_type' => 'Tax Invoice',
                'name'          => 'ใบกำกับภาษี',
                'customer_name' => $customerName,
                'type'          => 'รายรับ',
                'category'      => 'ใบกำกับภาษี',
                'amount'        => $item->total_amount,
                'paid_type'     => $item->paid_type,
                'branch_id'     => $item->branche_id,
                'status'        => $item->status,
                'reference_id'  => $item->id,
            ];
            $reportData[]       = $reportRow;
            $tax_invoicesdata[] = $reportRow;
            if ($item->paid_type == 'paid_cash') {
                $tax_invoicesdata_cash[] = $reportRow;
            } elseif ($item->paid_type == 'paid_tran') {
                $tax_invoicesdata_tran[] = $reportRow;
            } elseif ($item->paid_type == 'credit') {
                $tax_invoicesdata_cardit[] = $reportRow;
            }
        }

        $fixdata        = [];
        $fixdata_cash   = [];
        $fixdata_tran   = [];
        $fixdata_cardit = [];

        foreach ($fix as $item) {
            $reportRow = [
                'date'          => $item->date,
                'document_no'   => $item->no,
                'document_type' => 'Fix',
                'name'          => 'ใบซ่อม',
                'license_plate' => $item->license_plate ?? '-',
                'customer_name' => ($item->Customer->name ?? '-') . ' ' . ($item->Customer->lname ?? ''),
                'type'          => 'รายรับ',
                'category'      => 'ใบซ่อม',
                'amount'        => $item->total,
                'paid_type'     => $item->paid_type,
                'branch_id'     => $item->branches_id,
                'status'        => $item->status,
                'reference_id'  => $item->id,
            ];
            $reportData[] = $reportRow;
            $fixdata[]    = $reportRow;
            if ($item->paid_type == 'paid_cash') {
                $fixdata_cash[] = $reportRow;
            } elseif ($item->paid_type == 'paid_tran') {
                $fixdata_tran[] = $reportRow;
            } elseif ($item->paid_type == 'credit') {
                $fixdata_cardit[] = $reportRow;
            }
        }

        $inspections_debtdata        = [];
        $inspections_debtdata_cash   = [];
        $inspections_debtdata_tran   = [];
        $inspections_debtdata_cardit = [];

        // Process inspections debt data
        foreach ($inspections_debt as $item) {
            $reportRow = [
                'date'          => $item->date,
                'document_no'   => $item->bill_number,
                'document_type' => 'Inspection',
                'name'          => 'ลูกหนี้',
                'customer_name' => $item->customer_name,
                'type'          => 'รายจ่าย',
                'category'      => 'ตรวจสภาพ',
                'amount'        => $item->payextra,
                'paid_type'     => $item->paid_type_debt,
                'branch_id'     => $item->branch_id,
                'status'        => $item->transaction_status,
                'reference_id'  => $item->id,
            ];
            $reportData[]           = $reportRow;
            $inspections_debtdata[] = $reportRow;
            if ($item->paid_type_debt == 'paid_cash') {
                $inspections_debtdata_cash[] = $reportRow;
            } elseif ($item->paid_type_debt == 'paid_tran') {
                $inspections_debtdata_tran[] = $reportRow;
            } elseif ($item->paid_type_debt == 'credit') {
                $inspections_debtdata_cardit[] = $reportRow;
            }
        }

        // Prepare data summaries by payment type
        $cash       = [];
        $credit     = [];
        $transfer   = [];
        $taxinvoice = [];

        foreach ($reportData as $row) {
            switch ($row['paid_type']) {
                case 'paid_cash':
                    $cash[] = $row;
                    break;
                case 'paid_tran':
                    $transfer[] = $row;
                    break;
                case 'credit':
                    $credit[] = $row;
                    break;
                case '-':
                    $taxinvoice[] = $row;
                    break;
            }
        }

        // Summary totals
        $summaryData = [
            'start_date'     => $startDate->format('Y-m-d'),
            'end_date'       => $endDate->format('Y-m-d'),
            'total_income'   => collect($reportData)->where('type', 'รายรับ')->sum('amount'),
            'total_expense'  => collect($reportData)->where('type', 'รายจ่าย')->sum('amount'),
            'total_cash'     => collect($cash)->sum('amount'),
            'total_credit'   => collect($credit)->sum('amount'),
            'total_transfer' => collect($transfer)->sum('amount'),
            'net_balance'    => collect($reportData)->where('type', 'รายรับ')->sum('amount') +
            collect($reportData)->where('type', 'รายจ่าย')->sum('amount'),
        ];

        // Group by payment type and then by type (income/expense)
        $groupedByPayment = [
            'cash'     => [
                'data'          => [
                    'inspection'             => [
                        'all'    => collect($Inspectionsdata_cash)->values()->all(),
                        'vat'    => collect($Inspectionsdata_cash_vat)->values()->all(),
                        'nonvat' => collect($Inspectionsdata_cash_nonvat)->values()->all(),
                    ],
                    'inspection_total'       => collect($Inspectionsdata_cash)->sum('amount'),
                    'invoice'                => collect($Invoicesdata_cash)->values()->all(),
                    'invoice_total'          => collect($Invoicesdata_cash)->sum('amount'),
                    'cost'                   => collect($costdata_cash)->values()->all(),
                    'cost_total'             => collect($costdata_cash)->sum('amount'),
                    'taxinvoice'             => collect($tax_invoicesdata_cash)->values()->all(),
                    'taxinvoice_total'       => collect($tax_invoicesdata_cash)->sum('amount'),
                    'fix'                    => collect($fixdata_cash)->values()->all(),
                    'fix_total'              => collect($fixdata_cash)->sum('amount'),
                    'inspections_debt'       => collect($inspections_debtdata_cash)->values()->all(),
                    'inspections_debt_total' => collect($inspections_debtdata_cash)->sum('amount'),
                    'cost_income'            => collect($costdata_cash_income)->values()->all(),
                    'cost_income_total'      => collect($costdata_cash_income)->sum('amount'),
                    'cost_expenses'          => collect($costdata_cash_expenses)->values()->all(),
                    'cost_expenses_total'    => collect($costdata_cash_expenses)->sum('amount'),
                ],
                'total_income'  => collect($cash)->where('type', 'รายรับ')->sum('amount'),
                'total_expense' => collect($cash)->where('type', 'รายจ่าย')->sum('amount'),
                'total'         => collect($cash)->sum('amount'),
            ],
            'credit'   => [
                'data'          => [
                    'inspection'             => [
                        'all'    => collect($Inspectionsdata_cardit)->values()->all(),
                        'vat'    => collect($Inspectionsdata_cardit_vat)->values()->all(),
                        'nonvat' => collect($Inspectionsdata_cardit_nonvat)->values()->all(),
                    ],
                    'inspection_total'       => collect($Inspectionsdata_cardit)->sum('amount'),
                    'invoice'                => collect($Invoicesdata_cardit)->values()->all(),
                    'invoice_total'          => collect($Invoicesdata_cardit)->sum('amount'),
                    'cost'                   => collect($costdata_cardit)->values()->all(),
                    'cost_total'             => collect($costdata_cardit)->sum('amount'),
                    'taxinvoice'             => collect($tax_invoicesdata_cardit)->values()->all(),
                    'taxinvoice_total'       => collect($tax_invoicesdata_cardit)->sum('amount'),
                    'fix'                    => collect($fixdata_cardit)->values()->all(),
                    'fix_total'              => collect($fixdata_cardit)->sum('amount'),
                    'inspections_debt'       => collect($inspections_debtdata_cardit)->values()->all(),
                    'inspections_debt_total' => collect($inspections_debtdata_cardit)->sum('amount'),
                    'cost_income'            => collect($costdata_cardit_income)->values()->all(),
                    'cost_income_total'      => collect($costdata_cardit_income)->sum('amount'),
                    'cost_expenses'          => collect($costdata_cardit_expenses)->values()->all(),
                    'cost_expenses_total'    => collect($costdata_cardit_expenses)->sum('amount'),
                ],
                'total_income'  => collect($credit)->where('type', 'รายรับ')->sum('amount'),
                'total_expense' => collect($credit)->where('type', 'รายจ่าย')->sum('amount'),
                'total'         => collect($credit)->sum('amount'),
            ],
            'transfer' => [
                'data'          => [
                    'inspection'             => [
                        'all'    => collect($Inspectionsdata_tran)->values()->all(),
                        'vat'    => collect($Inspectionsdata_tran_vat)->values()->all(),
                        'nonvat' => collect($Inspectionsdata_tran_nonvat)->values()->all(),
                    ],
                    'inspection_total'       => collect($Inspectionsdata_tran)->sum('amount'),
                    'invoice'                => collect($Invoicesdata_tran)->values()->all(),
                    'invoice_total'          => collect($Invoicesdata_tran)->sum('amount'),
                    'cost'                   => collect($costdata_tran)->values()->all(),
                    'cost_total'             => collect($costdata_tran)->sum('amount'),
                    'taxinvoice'             => collect($tax_invoicesdata_tran)->values()->all(),
                    'taxinvoice_total'       => collect($tax_invoicesdata_tran)->sum('amount'),
                    'fix'                    => collect($fixdata_tran)->values()->all(),
                    'fix_total'              => collect($fixdata_tran)->sum('amount'),
                    'inspections_debt'       => collect($inspections_debtdata_tran)->values()->all(),
                    'inspections_debt_total' => collect($inspections_debtdata_tran)->sum('amount'),
                    'cost_income'            => collect($costdata_tran_income)->values()->all(),
                    'cost_income_total'      => collect($costdata_tran_income)->sum('amount'),
                    'cost_expenses'          => collect($costdata_tran_expenses)->values()->all(),
                    'cost_expenses_total'    => collect($costdata_tran_expenses)->sum('amount'),
                ],
                'total_income'  => collect($transfer)->where('type', 'รายรับ')->sum('amount'),
                'total_expense' => collect($transfer)->where('type', 'รายจ่าย')->sum('amount'),
                'total'         => collect($transfer)->sum('amount'),
            ],
        ];
        $reportData = collect($reportData)->sortBy('date')->values()->all();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'all_data'        => $reportData,
                'summary'         => $summaryData,
                'by_payment_type' => $groupedByPayment,
                'taxinvoice'      => $taxinvoice,
            ],
        ]);
    }

    public function query_income(request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $cost = Cost::with('ExpenseType')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->get();

        $Inspections = Inspections::with(['inspectionVehicles.vehicle', 'installments', 'Customers'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
           ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]); // เปลี่ยนจาก tax_renewal_date เป็น date ของ inspection โดยตรง
            })
            ->where('status', ['finish', 'overdue'])
            ->get();

        $invoices = Invoice::with(['invoiceServices', 'invoicePayment'])
            ->leftJoin('tax_invoices', 'invoices.id', '=', 'tax_invoices.invoice_id')
            ->whereNull('tax_invoices.id')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('invoices.branche_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->where('invoices.status', 'paid')
            ->select('invoices.*')
            ->get();

        $tax_invoices = TaxInvoice::with(['Customers', 'taxInvoiceService', 'taxInvoiceAllNos'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branche_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->where('status', 'approved')
            ->get();

        // $fix = WorkOrder::with(['WorkOrderServices', 'Customer'])
        //     ->when($branchId, function ($query) use ($branchId) {
        //         $query->where('branches_id', $branchId);
        //     })
        //     ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
        //         $query->whereBetween('updated_at', [$startDate, $endDate]);
        //     })
        //     ->where('status', 'success')
        //     ->get();

        $fix = WorkOrder::with(['WorkOrderServices', 'Customer', 'WorkOrderPayment'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branches_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ]);
            })
            ->where('status', 'success')
            ->get();

        $inspections_debt = DB::table('inspection_vehicles')
            ->join('inspections', 'inspection_vehicles.inspection_id', '=', 'inspections.id')
            ->join('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', function ($join) {
                $join->on('vechicle_services_transactions.inspection_vehicle_id', '=', 'inspection_vehicles.id')
                    ->where('vechicle_services_transactions.service_id', '=', 3);
            })
            ->wherenotnull('inspections.transaction_status')
            ->whereIn('inspections.transaction_status', ['adddebt', 'closedebt', 'reducedebt'])
            ->whereRaw('inspections.paytrue - vechicle_services_transactions.service_price != 0')
            ->select(
                'inspections.*',
                'inspections.no as bill_number',
                'vehicles.license_plate',
                'vehicles.registration_date',
                DB::raw("CONCAT(customers.name, ' ', customers.lname) as customer_name"),
                'vechicle_services_transactions.service_price',
                DB::raw("(inspections.paytrue - vechicle_services_transactions.service_price) as payextra")
            );

        if (! empty($startDate) && ! empty($endDate)) {
            $inspections_debt->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if (! empty($branchId)) {
            $inspections_debt->where('inspections.branch_id', $branchId);
        }
        $inspections_debt = $inspections_debt->get();

        $reportData = [];

        $costdata                 = [];
        $costdata_cash            = [];
        $costdata_cash_income     = [];
        $costdata_cash_expenses   = [];
        $costdata_tran            = [];
        $costdata_tran_income     = [];
        $costdata_tran_expenses   = [];
        $costdata_cardit          = [];
        $costdata_cardit_income   = [];
        $costdata_cardit_expenses = [];

        // Calculate total commission from inspections
        $totalTax = 0;
        foreach ($Inspections as $inspection) {
            $totalTax += ($inspection->commission ?? 0) + ($inspection->paytrue ?? 0);
        }

        if ($totalTax > 0) {
            $commissionExpense = [
                'pay_date1'      => $startDate ? $startDate->format('Y-m-d') : date('Y-m-d'),
                'date'          => $startDate ? $startDate->format('Y-m-d') : date('Y-m-d'),
                'document_no'   => '-',
                'document_type' => 'Cost',
                'name'          => 'ค่าใช้จ่ายภาษี',
                'customer_name' => 'ค่าใช้จ่ายภาษี',
                'type'          => 'รายจ่าย',
                'category'      => 'ค่าใช้จ่ายภาษี',
                'amount'        => -1 * $totalTax, // Negative amount for expense
                'paid_type'     => 'paid_cash', // Add to cash expenses
                'branch_id'     => $branchId,
                'status'        => '-',
                'reference_id'  => 0,
            ];

            $reportData[] = $commissionExpense;
            $costdata[] = $commissionExpense;
            $costdata_cash[] = $commissionExpense;
            $costdata_cash_expenses[] = $commissionExpense;
        }

        foreach ($cost as $item) {
            $reportRow = [
                'pay_date2'     => $item->updated_at,
                'date'          => $item->date,
                'document_no'   => '-',
                'document_type' => 'Cost',
                'name'          => $item->name,
                'customer_name' => $item->detail,
                'type'          => $item->type == 'income' ? 'รายรับ' : 'รายจ่าย',
                'category'      => $item->expense_type->name ?? '-',
                'amount'        => $item->type == 'income' ? $item->price : -1 * $item->price, // รายจ่ายติดลบ
                'paid_type'     => $item->paid_type,
                'branch_id'     => $item->branch_id,
                'status'        => '-',
                'reference_id'  => $item->id,
            ];
            $reportData[] = $reportRow;
            $costdata[]   = $reportRow;
            if ($item->paid_type == 'paid_cash') {
                $costdata_cash[] = $reportRow;
                if ($item->type == 'income') {
                    $costdata_cash_income[] = $reportRow;
                } else {
                    $costdata_cash_expenses[] = $reportRow;
                }
            } elseif ($item->paid_type == 'paid_tran') {
                $costdata_tran[] = $reportRow;
                if ($item->type == 'income') {
                    $costdata_tran_income[] = $reportRow;
                } else {
                    $costdata_tran_expenses[] = $reportRow;
                }
            } elseif ($item->paid_type == 'credit') {
                $costdata_cardit[] = $reportRow;
                if ($item->type == 'income') {
                    $costdata_cardit_income[] = $reportRow;
                } else {
                    $costdata_cardit_expenses[] = $reportRow;
                }
            }
        }

        $Inspectionsdata               = [];
        $Inspectionsdata_cash          = [];
        $Inspectionsdata_cash_vat      = [];
        $Inspectionsdata_cash_nonvat   = [];
        $Inspectionsdata_tran          = [];
        $Inspectionsdata_tran_vat      = [];
        $Inspectionsdata_tran_nonvat   = [];
        $Inspectionsdata_cardit        = [];
        $Inspectionsdata_cardit_vat    = [];
        $Inspectionsdata_cardit_nonvat = [];
        // Process inspections data
        foreach ($Inspections as $item) {
            if (! empty($item->installments)) {
                $paymentSummary = [];

                // วนลูปผ่านการชำระเงินแต่ละครั้ง
                foreach ($item->installments as $installment) {
                    $paidType = $installment->paid_type;
                    $vatType  = $installment->type;

                    if (! isset($paymentSummary[$paidType])) {
                        $paymentSummary[$paidType] = [
                            'vat'    => 0,
                            'nonvat' => 0,
                        ];
                    }

                    $paymentSummary[$paidType][$vatType] += $installment->paid_price;
                }

                // สร้างแถวข้อมูลสำหรับแต่ละประเภทการชำระ
                foreach ($paymentSummary as $paidType => $vatData) {
                    foreach ($vatData as $vatType => $amount) {
                        // Skip if amount is zero
                        if ($amount == 0) {
                            continue;
                        }

                        $reportRow = [
                            'pay_date3'     => $item->date,
                            'date'          => $item->date,
                            'document_no'   => $item->no,
                            'document_type' => 'Inspection',
                            'name'          => 'ตรวจรถ',
                            'license_plate' => isset($item->inspectionVehicles[0]) ? $item->inspectionVehicles[0]->license_plate : '-',
                            'customer_name' => isset($item->customers) ? $item->customers->name : '-',
                            'type'          => 'รายรับ',
                            'category'      => 'ตรวจสภาพ',
                            'amount'        => $amount,
                            'paid_type'     => $paidType,
                            'vat_type'      => $vatType,
                            'branch_id'     => $item->branch_id,
                            'status'        => $item->status,
                            'reference_id'  => $item->id,
                        ];

                        $reportData[]      = $reportRow;
                        $Inspectionsdata[] = $reportRow;

                        // Categorize by payment type
                        if ($paidType == 'paid_cash') {
                            $Inspectionsdata_cash[] = $reportRow;

                            // Further categorize by VAT type
                            if ($vatType == 'vat') {
                                $Inspectionsdata_cash_vat[] = $reportRow;
                            } else if ($vatType == 'nonvat') {
                                $Inspectionsdata_cash_nonvat[] = $reportRow;
                            }
                        } elseif ($paidType == 'paid_tran') {
                            $Inspectionsdata_tran[] = $reportRow;

                            if ($vatType == 'vat') {
                                $Inspectionsdata_tran_vat[] = $reportRow;
                            } else if ($vatType == 'nonvat') {
                                $Inspectionsdata_tran_nonvat[] = $reportRow;
                            }
                        } elseif ($paidType == 'credit') {
                            $Inspectionsdata_cardit[] = $reportRow;

                            if ($vatType == 'vat') {
                                $Inspectionsdata_cardit_vat[] = $reportRow;
                            } else if ($vatType == 'nonvat') {
                                $Inspectionsdata_cardit_nonvat[] = $reportRow;
                            }
                        }
                    }
                }
            }
        }
        $Invoicesdata        = [];
        $Invoicesdata_cash   = [];
        $Invoicesdata_tran   = [];
        $Invoicesdata_cardit = [];

        // Process invoices data
        foreach ($invoices as $item) {
            $customerName = $item->customer_name ?? ($item->quotation->Customers->name ?? '-');

            if (! empty($item->invoicePayment)) {
                $paymentSummary = [];

                foreach ($item->invoicePayment as $payment) {
                    $paidType = $payment->paid_type;

                    if (! isset($paymentSummary[$paidType])) {
                        $paymentSummary[$paidType] = 0;
                    }

                    $paymentSummary[$paidType] += $payment->paid_price;
                }

                foreach ($paymentSummary as $type => $amount) {
                    $reportRow = [
                        'pay_date4'     => $item->date,
                        'date'          => $item->date,
                        'document_no'   => $item->no,
                        'document_type' => 'Invoice',
                        'name'          => 'ใบแจ้งหนี้',
                        'customer_name' => $customerName,
                        'type'          => 'รายรับ',
                        'category'      => 'ใบแจ้งหนี้',
                        'amount'        => $amount,
                        'paid_type'     => $type,
                        'branch_id'     => $item->branche_id,
                        'status'        => $item->status,
                        'reference_id'  => $item->id,
                    ];
                    $reportData[]   = $reportRow;
                    $Invoicesdata[] = $reportRow;
                    if ($type == 'paid_cash') {
                        $Invoicesdata_cash[] = $reportRow;
                    } elseif ($type == 'paid_tran') {
                        $Invoicesdata_tran[] = $reportRow;
                    } elseif ($type == 'credit') {
                        $Invoicesdata_cardit[] = $reportRow;
                    }
                }
            }
        }

        $tax_invoicesdata        = [];
        $tax_invoicesdata_cash   = [];
        $tax_invoicesdata_tran   = [];
        $tax_invoicesdata_cardit = [];
        // Process tax invoices data
        foreach ($tax_invoices as $item) {
            $customerName = $item->customer_name ?? ($item->customers->name ?? '-');

            $reportRow = [
                'pay_date5'     => $item->date,
                'date'          => $item->date,
                'document_no'   => $item->no,
                'document_type' => 'Tax Invoice',
                'name'          => 'ใบกำกับภาษี',
                'customer_name' => $customerName,
                'type'          => 'รายรับ',
                'category'      => 'ใบกำกับภาษี',
                'amount'        => $item->total_amount,
                'paid_type'     => $item->paid_type,
                'branch_id'     => $item->branche_id,
                'status'        => $item->status,
                'reference_id'  => $item->id,
            ];
            $reportData[]       = $reportRow;
            $tax_invoicesdata[] = $reportRow;
            if ($item->paid_type == 'paid_cash') {
                $tax_invoicesdata_cash[] = $reportRow;
            } elseif ($item->paid_type == 'paid_tran') {
                $tax_invoicesdata_tran[] = $reportRow;
            } elseif ($item->paid_type == 'credit') {
                $tax_invoicesdata_cardit[] = $reportRow;
            }
        }

        $fixdata        = [];
        $fixdata_cash   = [];
        $fixdata_tran   = [];
        $fixdata_cardit = [];

        foreach ($fix as $item) {
            $customerLabel = trim(
                ($item->Customer->name ?? $item->customer_name ?? '') . ' ' . ($item->Customer->lname ?? '')
            );
            foreach ($this->buildDailyReportFixRows($item, $customerLabel, $branchId) as $reportRow) {
                $reportData[] = $reportRow;
                $fixdata[]    = $reportRow;
                $paidType     = $reportRow['paid_type'];
                if ($paidType === 'paid_cash') {
                    $fixdata_cash[] = $reportRow;
                } elseif ($paidType === 'paid_tran') {
                    $fixdata_tran[] = $reportRow;
                } elseif ($paidType === 'credit') {
                    $fixdata_cardit[] = $reportRow;
                }
            }
        }

        $inspections_debtdata        = [];
        $inspections_debtdata_cash   = [];
        $inspections_debtdata_tran   = [];
        $inspections_debtdata_cardit = [];

        // Process inspections debt data
        foreach ($inspections_debt as $item) {
            $reportRow = [
                'pay_date7'     => $item->date,
                'date'          => $item->date,
                'document_no'   => $item->bill_number,
                'document_type' => 'Inspection',
                'name'          => 'ลูกหนี้',
                'customer_name' => $item->customer_name,
                'type'          => 'รายจ่าย',
                'category'      => 'ตรวจสภาพ',
                'amount'        => $item->payextra,
                'paid_type'     => $item->paid_type_debt,
                'branch_id'     => $item->branch_id,
                'status'        => $item->transaction_status,
                'reference_id'  => $item->id,
            ];
            $reportData[]           = $reportRow;
            $inspections_debtdata[] = $reportRow;
            if ($item->paid_type_debt == 'paid_cash' || $item->paid_type_debt == null && $item->transaction_status == 'closedebt') {
                $inspections_debtdata_cash[] = $reportRow;
            } elseif ($item->paid_type_debt == 'paid_tran') {
                $inspections_debtdata_tran[] = $reportRow;
            } elseif ($item->paid_type_debt == 'credit') {
                $inspections_debtdata_cardit[] = $reportRow;
            }
        }

        // Prepare data summaries by payment type
        $cash       = [];
        $credit     = [];
        $transfer   = [];
        $taxinvoice = [];

        foreach ($reportData as $row) {
            switch ($row['paid_type']) {
                case 'paid_cash':
                    $cash[] = $row;
                    break;
                case 'paid_tran':
                    $transfer[] = $row;
                    break;
                case 'credit':
                    $credit[] = $row;
                    break;
                case '-':
                    $taxinvoice[] = $row;
                    break;
            }
        }
        $totalIncome = collect($cash)->sum('amount');
        $totalExpense = collect($transfer)->sum('amount'); // จะเป็นค่าลบ
        $netBalance = $totalIncome + $totalExpense;
        // Summary totals
        $summaryData = [
            'start_date'     => $startDate->format('Y-m-d'),
            'end_date'       => $endDate->format('Y-m-d'),
            'total_income'   => collect($reportData)->where('type', 'รายรับ')->sum('amount'),
            'total_expense'  => collect($reportData)->where('type', 'รายจ่าย')->sum('amount'),
            'total_cash'     => collect($cash)->sum('amount'),
            'total_credit'   => collect($credit)->sum('amount'),
            'total_transfer' => collect($transfer)->sum('amount'),
            'net_balance' => $netBalance,
        ];

        // Group by payment type and then by type (income/expense)
        $groupedByPayment = [
            'cash'     => [
                'data'          => [
                    'inspection'             => [
                        'all'    => collect($Inspectionsdata_cash)->values()->all(),
                        'vat'    => collect($Inspectionsdata_cash_vat)->values()->all(),
                        'nonvat' => collect($Inspectionsdata_cash_nonvat)->values()->all(),
                    ],
                    'inspection_total'       => collect($Inspectionsdata_cash)->sum('amount'),
                    'invoice'                => collect($Invoicesdata_cash)->values()->all(),
                    'invoice_total'          => collect($Invoicesdata_cash)->sum('amount'),
                    'cost'                   => collect($costdata_cash)->values()->all(),
                    'cost_total'             => collect($costdata_cash)->sum('amount'),
                    'taxinvoice'             => collect($tax_invoicesdata_cash)->values()->all(),
                    'taxinvoice_total'       => collect($tax_invoicesdata_cash)->sum('amount'),
                    'fix'                    => collect($fixdata_cash)->values()->all(),
                    'fix_total'              => collect($fixdata_cash)->sum('amount'),
                    'inspections_debt'       => collect($inspections_debtdata_cash)->values()->all(),
                    'inspections_debt_total' => collect($inspections_debtdata_cash)->sum('amount'),
                    'cost_income'            => collect($costdata_cash_income)->values()->all(),
                    'cost_income_total'      => collect($costdata_cash_income)->sum('amount'),
                    'cost_expenses'          => collect($costdata_cash_expenses)->values()->all(),
                    'cost_expenses_total'    => collect($costdata_cash_expenses)->sum('amount'),
                ],
                'total_income'  => collect($cash)->where('type', 'รายรับ')->sum('amount'),
                'total_expense' => collect($cash)->where('type', 'รายจ่าย')->sum('amount'),
                'total'         => collect($cash)->sum('amount'),
            ],
            'credit'   => [
                'data'          => [
                    'inspection'             => [
                        'all'    => collect($Inspectionsdata_cardit)->values()->all(),
                        'vat'    => collect($Inspectionsdata_cardit_vat)->values()->all(),
                        'nonvat' => collect($Inspectionsdata_cardit_nonvat)->values()->all(),
                    ],
                    'inspection_total'       => collect($Inspectionsdata_cardit)->sum('amount'),
                    'invoice'                => collect($Invoicesdata_cardit)->values()->all(),
                    'invoice_total'          => collect($Invoicesdata_cardit)->sum('amount'),
                    'cost'                   => collect($costdata_cardit)->values()->all(),
                    'cost_total'             => collect($costdata_cardit)->sum('amount'),
                    'taxinvoice'             => collect($tax_invoicesdata_cardit)->values()->all(),
                    'taxinvoice_total'       => collect($tax_invoicesdata_cardit)->sum('amount'),
                    'fix'                    => collect($fixdata_cardit)->values()->all(),
                    'fix_total'              => collect($fixdata_cardit)->sum('amount'),
                    'inspections_debt'       => collect($inspections_debtdata_cardit)->values()->all(),
                    'inspections_debt_total' => collect($inspections_debtdata_cardit)->sum('amount'),
                    'cost_income'            => collect($costdata_cardit_income)->values()->all(),
                    'cost_income_total'      => collect($costdata_cardit_income)->sum('amount'),
                    'cost_expenses'          => collect($costdata_cardit_expenses)->values()->all(),
                    'cost_expenses_total'    => collect($costdata_cardit_expenses)->sum('amount'),
                ],
                'total_income'  => collect($credit)->where('type', 'รายรับ')->sum('amount'),
                'total_expense' => collect($credit)->where('type', 'รายจ่าย')->sum('amount'),
                'total'         => collect($credit)->sum('amount'),
            ],
            'transfer' => [
                'data'          => [
                    'inspection'             => [
                        'all'    => collect($Inspectionsdata_tran)->values()->all(),
                        'vat'    => collect($Inspectionsdata_tran_vat)->values()->all(),
                        'nonvat' => collect($Inspectionsdata_tran_nonvat)->values()->all(),
                    ],
                    'inspection_total'       => collect($Inspectionsdata_tran)->sum('amount'),
                    'invoice'                => collect($Invoicesdata_tran)->values()->all(),
                    'invoice_total'          => collect($Invoicesdata_tran)->sum('amount'),
                    'cost'                   => collect($costdata_tran)->values()->all(),
                    'cost_total'             => collect($costdata_tran)->sum('amount'),
                    'taxinvoice'             => collect($tax_invoicesdata_tran)->values()->all(),
                    'taxinvoice_total'       => collect($tax_invoicesdata_tran)->sum('amount'),
                    'fix'                    => collect($fixdata_tran)->values()->all(),
                    'fix_total'              => collect($fixdata_tran)->sum('amount'),
                    'inspections_debt'       => collect($inspections_debtdata_tran)->values()->all(),
                    'inspections_debt_total' => collect($inspections_debtdata_tran)->sum('amount'),
                    'cost_income'            => collect($costdata_tran_income)->values()->all(),
                    'cost_income_total'      => collect($costdata_tran_income)->sum('amount'),
                    'cost_expenses'          => collect($costdata_tran_expenses)->values()->all(),
                    'cost_expenses_total'    => collect($costdata_tran_expenses)->sum('amount'),
                ],
                'total_income'  => collect($transfer)->where('type', 'รายรับ')->sum('amount'),
                'total_expense' => collect($transfer)->where('type', 'รายจ่าย')->sum('amount'),
                'total'         => collect($transfer)->sum('amount'),
            ],
        ];
        $reportData = collect($reportData)->sortBy('date')->values()->all();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'all_data'        => $reportData,
                'summary'         => $summaryData,
                'by_payment_type' => $groupedByPayment,
                'taxinvoice'      => $taxinvoice,
            ],
        ]);
    }

    public function query_income_service(request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);

        $Inspections = Inspections::with(['inspectionVehicles.vehicleServiceTransaction', 'Customers'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->where('status', 'finish')
            ->get();

        // $invoices = Invoice::with(['invoiceServices', 'invoicePayment'])
        //     ->when($branchId, function ($query) use ($branchId) {
        //         $query->where('branche_id', $branchId);
        //     })
        //     ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
        //         $query->whereBetween('date', [$startDate, $endDate]);
        //     })
        //     ->where('status', 'paid')
        //     ->get();
        $invoices = Invoice::with(['invoiceServices', 'invoicePayment'])
            ->leftJoin('tax_invoices', 'invoices.id', '=', 'tax_invoices.invoice_id')
            ->whereNull('tax_invoices.id')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('invoices.branche_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->where('invoices.status', 'paid')
            ->select('invoices.*')
            ->get();

        $tax_invoices = TaxInvoice::with(['Customers', 'taxInvoiceService', 'taxInvoiceAllNos'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branche_id', $branchId);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->where('status', 'approved')
            ->get();

        $allData = [];
        $total   = 0;

        foreach ($Inspections as $item) {
            $allData[] = $item;
            $total += $item->total_vatcal + $item->total_nonvatcal;
        }
        foreach ($invoices as $item) {
            $allData[] = $item;
            $total += $item->total_amount;
        }
        foreach ($tax_invoices as $item) {
            $allData[] = $item;
            $total += $item->total_amount;
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'all_data'    => $allData,
                'Inspections' => $Inspections,
                'invoice'     => $invoices,
                'taxinvoice'  => $tax_invoices,
                'total'       => $total,
            ],
        ]);
    }
    public function query_income_byservice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $startDate = Carbon::parse($request->input('start_date'));
        $endDate   = Carbon::parse($request->input('end_date'));
        $branchId  = $request->input('branch_id', null);

        // ดึงประเภทของบริการทั้งหมด
        $service_types = ServiceType::get();

        // สร้าง array สำหรับสรุปยอด
        $summary                    = [];
        $inspections_summary_total  = [];
        $invoices_summary_total     = [];
        $tax_invoices_summary_total = [];
        foreach ($service_types as $type) {
            $summary[$type->id] = [
                'service_type_id'   => $type->id,
                'service_type_name' => $type->name,
                'total_income'      => 0,
            ];

            // สร้างโครงสร้างสรุปยอด
            $inspections_summary_total[$type->id]     = ['service_type_id' => $type->id, 'service_type_name' => $type->name, 'total_income' => 0];
            $inspections_summary_total_vat[$type->id] = ['service_type_id' => $type->id, 'service_type_name' => $type->name, 'total_income' => 0];
            $invoices_summary_total[$type->id]        = ['service_type_id' => $type->id, 'service_type_name' => $type->name, 'total_tax' => 0, 'total_income' => 0];
            $tax_invoices_summary_total[$type->id]    = ['service_type_id' => $type->id, 'service_type_name' => $type->name, 'total_tax' => 0, 'total_income' => 0];

        }
        // 1. ดึงข้อมูลจาก Inspections
        $inspections = Inspections::with(['inspectionVehicles.vehicleServiceTransaction.service.serviceType', 'Customers'])
            ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'finish')
            ->get();

        $inspections_array     = [];
        $inspections_array_vat = [];
        $inspections_total     = 0;
        $inspections_total_vat = 0;

        foreach ($inspections as $inspection) {
            $inspection_summary     = [];
            $inspection_summary_vat = [];
            $total_bill_income      = 0;

            foreach ($inspection->inspectionVehicles as $vehicle) {
                foreach ($vehicle->vehicleServiceTransaction as $transaction) {
                    if ($transaction->is_vat == 0) {
                        $serviceType = $transaction->service->serviceType ?? null;
                        $amount      = $transaction->service_price;
                        if ($serviceType) {
                            $summary[$serviceType->id]['total_income'] += $amount;
                            $inspections_summary_total[$serviceType->id]['total_income'] += $amount;

                            $inspection_summary[$serviceType->id]['service_type_id']   = $serviceType->id;
                            $inspection_summary[$serviceType->id]['service_type_name'] = $serviceType->name;
                            $inspection_summary[$serviceType->id]['income']            = ($inspection_summary[$serviceType->id]['income'] ?? 0) + $amount;

                            $total_bill_income += $amount;
                            $inspections_total += $amount;
                        }
                    } else {
                        $serviceType = $transaction->service->serviceType ?? null;
                        $amount      = $transaction->service_price;
                        if ($serviceType) {
                            $summary[$serviceType->id]['total_income'] += $amount;
                            $inspections_summary_total_vat[$serviceType->id]['total_income'] += $amount;

                            $inspection_summary_vat[$serviceType->id]['service_type_id']   = $serviceType->id;
                            $inspection_summary_vat[$serviceType->id]['service_type_name'] = $serviceType->name;
                            $inspection_summary_vat[$serviceType->id]['income']            = ($inspection_summary_vat[$serviceType->id]['income'] ?? 0) + $amount;

                            $total_bill_income += $amount;
                            $inspections_total_vat += $amount;
                        }

                    }

                }
            }

            if ($inspection_summary !== []) {
                $inspections_array[] = [
                    'no'            => $inspection->no,
                    'date'          => $inspection->date,
                    'license_plate' => optional($inspection->inspectionVehicles->first())->license_plate,
                    'customer_name' => optional($inspection->Customers)->name . ' ' . optional($inspection->Customers)->lname,
                    'total_income'  => $inspection->total_nonvatcal,
                    'summary'       => array_values($inspection_summary),
                ];
            }

            if ($inspection_summary_vat !== []) {
                $inspections_array_vat[] = [
                    'no'            => $inspection->no,
                    'date'          => $inspection->date,
                    'license_plate' => optional($inspection->inspectionVehicles->first())->license_plate,
                    'customer_name' => optional($inspection->Customers)->name . ' ' . optional($inspection->Customers)->lname,
                    'total_income'  => $inspection->total_vatcal,
                    'summary'       => array_values($inspection_summary_vat),
                ];
            }
        }
        $invoices_tax_total = 0;
        $invoices_total     = 0;

        // 2. ดึงข้อมูลจาก Invoices
        $invoices = Invoice::with(['invoiceServices.Services.serviceType', 'invoicePayment'])
            ->when($branchId, fn($query) => $query->where('branche_id', $branchId))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->get();

        $invoices_array = [];

        foreach ($invoices as $invoice) {
            $invoice_summary   = [];
            $total_bill_income = 0;

            foreach ($invoice->invoiceServices as $transaction) {
                $serviceType = $transaction->Services->serviceType ?? null;
                $amount      = $transaction->service_price;

                if ($serviceType) {
                    $summary[$serviceType->id]['total_income'] += $amount;
                    $invoices_summary_total[$serviceType->id]['total_income'] += $amount;

                    $invoice_summary[$serviceType->id]['service_type_id']   = $serviceType->id;
                    $invoice_summary[$serviceType->id]['service_type_name'] = $serviceType->name;
                    $invoice_summary[$serviceType->id]['income']            = ($invoice_summary[$serviceType->id]['income'] ?? 0) + $amount;

                    $total_bill_income += $amount;
                }
            }
            $invoices_tax_total += $invoice->tax;
            $invoices_total += $invoice['total_amount'];

            $invoices_array[] = [
                'no'            => $invoice->no,
                'date'          => $invoice->date,
                'tax'           => $invoice->tax,
                'customer_name' => optional($invoice->Customers)->name,
                'total_income'  => $invoice['total_amount'],
                'summary'       => array_values($invoice_summary),
            ];
        }

        $tax_invoices_tax_total = 0;
        $tax_invoices_total     = 0;
        // 3. ดึงข้อมูลจาก Tax Invoices
        $tax_invoices = TaxInvoice::with(['Customers', 'taxInvoiceService.Services.serviceType', 'taxInvoiceAllNos'])
            ->when($branchId, fn($query) => $query->where('branche_id', $branchId))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->get();

        $tax_invoices_array = [];

        foreach ($tax_invoices as $tax_invoice) {
            $tax_invoice_summary = [];
            $total_bill_income   = 0;

            foreach ($tax_invoice->taxInvoiceService as $transaction) {
                $serviceType = $transaction->Services->serviceType ?? null;
                $amount      = $transaction->service_price;

                if ($serviceType) {
                    $summary[$serviceType->id]['total_income'] += $amount;
                    $tax_invoices_summary_total[$serviceType->id]['total_income'] += $amount;

                    $tax_invoice_summary[$serviceType->id]['service_type_id']   = $serviceType->id;
                    $tax_invoice_summary[$serviceType->id]['service_type_name'] = $serviceType->name;
                    $tax_invoice_summary[$serviceType->id]['income']            = ($tax_invoice_summary[$serviceType->id]['income'] ?? 0) + $amount;

                    $total_bill_income += $amount;
                }
            }

            $tax_invoices_tax_total += $tax_invoice->tax;
            $tax_invoices_total += $tax_invoice['total_amount'];

            $tax_invoices_array[] = [
                'no'            => $tax_invoice->no,
                'date'          => $tax_invoice->date,
                'tax'           => $tax_invoice->tax,
                'customer_name' => optional($tax_invoice->Customers)->name,
                'total_income'  => $tax_invoice['total_amount'],
                'summary'       => array_values($tax_invoice_summary),
            ];
        }
        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary'                       => array_values($summary),
                'inspections'                   => $inspections_array,
                'inspections_vat'               => $inspections_array_vat,
                'inspections_summary_total'     => array_values($inspections_summary_total),
                'inspections_summary_total_vat' => array_values($inspections_summary_total_vat),
                'inspections_total'             => $inspections_total,
                'inspections_total_vat'         => $inspections_total_vat,
                'invoices'                      => $invoices_array,
                'invoices_summary_total'        => array_values($invoices_summary_total),
                'invoices_tax_total'            => $invoices_tax_total,
                'invoices_total'                => $invoices_total,
                'tax_invoices'                  => $tax_invoices_array,
                'tax_invoices_summary_total'    => array_values($tax_invoices_summary_total),
                'tax_invoices_tax_total'        => $tax_invoices_tax_total,
                'tax_invoices_total'            => $tax_invoices_total,
                'service_types'                 => $service_types,
                'total_tax_income'              => $invoices_tax_total + $tax_invoices_tax_total,
                'total_income'                  => $inspections_total + $inspections_total_vat + $invoices_total + $tax_invoices_total,

            ],
        ]);
    }

    public function exportExcel_customerreport(Request $request)
    {
        $type = $request->type;

        $columns = [
            'id',
            'no',
            'name',
            'lname',
            'email',
            'phone_number1',
            'phone_number2',
            'tax_id',
            DB::raw("CASE WHEN type = 'personal' THEN 'บุคคลธรรมดา'
                          WHEN type = 'vendor' THEN 'นิติบุคคล'
                          WHEN type = 'agent' THEN 'ตัวแทน'
                          ELSE 'ไม่ระบุ' END AS type"),
            DB::raw("CASE WHEN is_headquarter = 1 THEN 'สำนักงานใหญ่'
                          WHEN is_headquarter = 0 THEN ''
                          ELSE '' END AS is_headquarter"),
        ];

        $searchcolumn = [
            'name',
            'no',
            'email',
            'phone_number1',
            'type',
            'is_headquarter',
        ];
        $query = Customers::select($columns)
            ->with('Customer_Addresses.Provinces')
            ->with('Customer_Addresses.Districts')
            ->with('Customer_Addresses.SubDistricts')
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type);
            });

        $data[] = $query->get();

        $result = new CustomerreportExport($data);
        return Excel::download($result, 'CustomerreportExport.xlsx');
    }

    public function exportExcel_carreport(Request $request)
    {
        $search  = $request->search;
        $columns = [
            'id',
            'customer_id',
            'file',
            'registration_date',
            'license_plate',
            'province_id',
            'vehicle_inspection_types_id',
            'brand_id',
            'model_id',
            'chassis_number',
            'engine_number',
            'cc',
            'fuel_type',
            'fuel',
            'weight',
            'weight_loaded',
            'seat_count',
            'description',
            'notificate_tax',
            DB::raw("CASE WHEN is_corporate = 1 THEN 'นิติบุคคล'
                          WHEN is_corporate = 0 THEN 'ไม่เป็นนิติบุคคล'
                          ELSE '' END AS is_corporate"),
            'tax_renewal_date',
            'mileage',
            'manual_number',
            'manual_date',
            'created_at',
            'updated_at',
        ];
        $searchcolumn = ['license_plate'];

        $query = Vehicles::select($columns)
            ->with('VehicleInspectionTypes')
            ->with('InsuranceTypes')
            ->with('VehicleGas')
            ->with('VehicleBrands')
            ->with('VehicleModels')
            ->with('Provinces')
            ->with('Branches')
            ->with('customers');

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $query->orderBy('id', 'desc');
        $data[] = $query->get();

        $result = new CarreportExport($data);
        return Excel::download($result, 'CarreportExport.xlsx');
    }

    public function exportExcel_sparepartreport(Request $request)
    {
        $search          = $request->search;
        $product_type_id = $request->product_type_id;
        $columns         = [
            'id',
            'name',
            'description',
            'price',
            'type',
            'qty',
            'image',
            'doc_id',
            'product_type_id',
        ];
        $searchcolumn = [
            'name',
            'description',
            'price',
            'product_type_id',
        ];

        $query = Services::select($columns)->with('ProductType');

        $query->where('type', 'product');
        $query->when($product_type_id, function ($query) use ($product_type_id) {
            $query->where('product_type_id', $product_type_id);
        });

        if (! empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $query->orderBy('id', 'asc');
        $data[] = $query->get();

        $result = new SparepartreportExport($data);
        return Excel::download($result, 'SparepartreportExport.xlsx');
    }


    /**
     * รายงานภาษีรถ (PDF/Excel หลัก) — หนึ่งบิลหนึ่งแถว, บริการภาษี service_id = 3
     */
    private function buildCarTaxReportInspectionQuery(
        Carbon $startDate,
        Carbon $endDate,
        $branchId,
        ?string $taxVehicle = null
    ) {
        $taxServiceId = 3;

        return Inspections::select(
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspections.total',
            'inspections.paytrue',
            'inspections.tax_vehicle',
            'inspections.remark',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.provinces',
            'inspection_vehicles.name AS customer_name',
            'inspection_vehicles.phone_number',
            DB::raw('COALESCE(inspection_vehicles.registration_date, vehicles.registration_date) as registration_date'),
            'vehicles.tax_renewal_date',
            'vehicleserviceTransactions.service_price',
            'vehicle_brands.name as brand_name'
        )
            ->leftJoin('inspection_vehicles', function ($join) use ($taxServiceId) {
                $join->on('inspections.id', '=', 'inspection_vehicles.inspection_id')
                    ->whereRaw('inspection_vehicles.id = (
                        SELECT iv.id FROM inspection_vehicles iv
                        INNER JOIN vechicle_services_transactions vst ON iv.id = vst.inspection_vehicle_id
                        WHERE iv.inspection_id = inspections.id
                          AND vst.service_id = ?
                          AND vst.deleted_at IS NULL
                        ORDER BY iv.id ASC
                        LIMIT 1
                    )', [$taxServiceId]);
            })
            ->leftJoin('vechicle_services_transactions as vehicleserviceTransactions', function ($join) use ($taxServiceId) {
                $join->on('inspection_vehicles.id', '=', 'vehicleserviceTransactions.inspection_vehicle_id')
                    ->where('vehicleserviceTransactions.service_id', $taxServiceId)
                    ->whereNull('vehicleserviceTransactions.deleted_at')
                    ->whereRaw('vehicleserviceTransactions.id = (
                        SELECT MAX(vst.id) FROM vechicle_services_transactions vst
                        WHERE vst.inspection_vehicle_id = inspection_vehicles.id
                          AND vst.service_id = ?
                          AND vst.deleted_at IS NULL
                    )', [$taxServiceId]);
            })
            ->leftJoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->whereNull('inspections.deleted_at')
            ->where('inspections.status', '!=', 'cancel')
            ->when($branchId, fn ($q) => $q->where('inspections.branch_id', $branchId))
            ->when($taxVehicle !== null, fn ($q) => $q->where('inspections.tax_vehicle', $taxVehicle))
            ->whereExists(function ($sub) use ($taxServiceId) {
                $sub->selectRaw('1')
                    ->from('inspection_vehicles as iv')
                    ->join('vechicle_services_transactions as vst', 'iv.id', '=', 'vst.inspection_vehicle_id')
                    ->whereColumn('iv.inspection_id', 'inspections.id')
                    ->where('vst.service_id', $taxServiceId)
                    ->whereNull('vst.deleted_at');
            })
            ->whereBetween('vehicles.tax_renewal_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('vehicles.tax_renewal_date', 'asc')
            ->orderBy('inspections.date', 'asc')
            ->orderBy('inspections.id', 'asc');
    }

    /**
     * คันหลักสำหรับรายงานภาษี — คันแรกที่มีบริการภาษี (service 3)
     */
    private function primaryCarTaxVehicle(Inspections $ins): ?InspectionVehicles
    {
        $ins->loadMissing(['InspectionVehicles.vehicleServiceTransaction']);

        return $ins->InspectionVehicles->sortBy('id')->first(function ($iv) {
            return $iv->vehicleServiceTransaction->contains(fn ($t) => (int) $t->service_id === 3);
        }) ?? $ins->InspectionVehicles->sortBy('id')->first();
    }

    /** คอลัmn เพิ่m/คืn — ยอด 0 แสดง '-' เท่านั้น */
    private function formatCarTaxDebtPayextraLabel(float $refund, float $extra): string
    {
        if ($extra > 0) {
            return 'เพิ่ม ' . number_format($extra, 0) . ' บาท';
        }
        if ($refund > 0) {
            return 'คืน ' . number_format($refund, 0) . ' บาท';
        }

        return '-';
    }

    /** คอลัmn หนี้ — ไม่มีส่วนต่างเงิn = ไม่แสดง badge (null → FE เป็น '-') */
    private function carTaxDebtStatusForList(?string $transactionStatus, float $refund, float $extra): ?string
    {
        if ($transactionStatus === 'closedebt') {
            return 'closedebt';
        }
        if ($extra <= 0 && $refund <= 0) {
            return null;
        }

        return $transactionStatus;
    }

    /** หน้าจัดการภาษีรถ — ยอดเพิ่m/คืn ต่อแถว (batch โหลด inspection) */
    private function enrichCarTaxManagePageRows($paginator): void
    {
        $collection = $paginator->getCollection();
        if ($collection->isEmpty()) {
            return;
        }

        $inspections = Inspections::query()
            ->whereIn('id', $collection->pluck('id')->filter()->unique())
            ->with(['InspectionVehicles.vehicleServiceTransaction'])
            ->get()
            ->keyBy('id');

        $collection->transform(function ($row) use ($inspections) {
            $ins = $inspections->get($row->id);
            if (! $ins) {
                $row->payextra = '-';

                return $row;
            }

            $amounts = $this->carTaxServiceAmounts($ins);
            $row->transaction_status = $this->carTaxDebtStatusForList(
                $ins->transaction_status,
                $amounts['refund'],
                $amounts['extra']
            );
            $row->payextra = $this->formatCarTaxDebtPayextraLabel(
                $amounts['refund'],
                $amounts['extra']
            );

            return $row;
        });
    }

    /**
     * ยอดเรียกเก็บ/คืน/เพิ่mเงิn — ใช้แถวบริการล่าสุดต่อคัน (Eloquent กรอง soft-delete แล้ว)
     */
    private function carTaxServiceAmounts(Inspections $ins): array
    {
        $iv = $this->primaryCarTaxVehicle($ins);
        $tax   = 0.0;
        $taxUp = 0.0;

        if ($iv) {
            $txns  = $iv->vehicleServiceTransaction->sortByDesc('id');
            $tax   = (float) ($txns->first(fn ($t) => (int) $t->service_id === 3)?->service_price ?? 0);
            $taxUp = (float) ($txns->first(fn ($t) => (int) $t->service_id === 4)?->service_price ?? 0);
        }

        $billed = $tax + $taxUp;
        $refund = 0.0;
        $extra  = 0.0;
        $pay    = null;

        if ($ins->paytrue !== null && $ins->paytrue !== '') {
            $pay = (float) $ins->paytrue;
            if ($billed > $pay) {
                $refund = $billed - $pay;
            } elseif ($pay > $billed) {
                $extra = $pay - $billed;
            }
        }

        return [
            'vehicle' => $iv,
            'tax'     => $tax,
            'tax_up'  => $taxUp,
            'billed'  => $billed,
            'pay'     => $pay,
            'refund'  => $refund,
            'extra'   => $extra,
        ];
    }


    private function enrichCarTaxMainReportRows($rows)
    {
        $rows = collect($rows);
        if ($rows->isEmpty()) {
            return $rows;
        }

        $ids = $rows->pluck('id')->filter()->unique()->values();
        $inspections = Inspections::query()
            ->whereIn('id', $ids)
            ->with(['InspectionVehicles.vehicleServiceTransaction'])
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($inspections) {
            $ins = $inspections->get($row->id);
            if ($ins) {
                $amounts      = $this->carTaxServiceAmounts($ins);
                $row->billed  = $amounts['billed'];
                $row->refund  = $amounts['refund'];
                $row->extra   = $amounts['extra'];
                $row->paytrue = $amounts['pay'];
            } else {
                $row->billed = (float) ($row->service_price ?? 0);
                $row->refund = 0.0;
                $row->extra  = 0.0;
            }

            return $row;
        });
    }

    private function formatCarTaxReportCell($value, bool $money = false): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if ($money) {
            $f = (float) $value;

            return $f == 0.0 ? '-' : number_format($f, 0, '.', ',');
        }

        return e((string) $value);
    }

    /** ทะเบียนเดิมไม่ซ้ำข้ามบิล/ปี — เก็บบิลล่าสุดต่อทะเบียนในชุด export */
    private function dedupeCarTaxExportsByLicensePlate($collection)
    {
        $seen = [];
        $out  = collect();

        foreach ($collection->sortByDesc('id')->values() as $ins) {
            $plate = strtoupper(trim((string) ($this->primaryCarTaxVehicle($ins)?->license_plate ?? '')));
            $key   = $plate !== '' ? $plate : 'id:' . $ins->id;
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $out->push($ins);
            }
        }

        return $out->values();
    }

    private function prepareCarTaxStatusExportPayload($inspections): array
    {
        return $inspections->map(function (Inspections $ins) {
            $amounts = $this->carTaxServiceAmounts($ins);
            $arr     = $ins->toArray();
            $primary = $amounts['vehicle'];

            if ($primary) {
                $ivArr = $primary->toArray();
                $ivArr['vehicle_service_transaction'] = $primary->vehicleServiceTransaction
                    ->sortByDesc('id')
                    ->values()
                    ->toArray();
                $arr['inspection_vehicles'] = [$ivArr];
            }

            $arr['_tax']         = $amounts['tax'];
            $arr['_tax_up']      = $amounts['tax_up'];
            $arr['_tax_billed']  = $amounts['billed'];
            $arr['_pay_refund']  = $amounts['refund'];
            $arr['_pay_extra']   = $amounts['extra'];

            return $arr;
        })->values()->all();
    }

    public function exportExcel_tax_by_status(Request $request)
    {
        $tax_vehicle = $request->tax_vehicle;
        $branchId    = $request->input('branch_id', null);
        $start_date  = $request->input('start_date', null);
        $end_date    = $request->input('end_date', null);
        $no          = $request->input('no', null);

        $inspections = Inspections::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($no, fn ($q) => $q->where('no', $no))
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancel')
            ->whereHas('InspectionVehicles.vehicleServiceTransaction', fn ($q) => $q->where('service_id', 3))
            ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
                $q->whereHas('InspectionVehicles.vehicle', function ($v) use ($start_date, $end_date) {
                    $v->whereBetween('tax_renewal_date', [$start_date, $end_date]);
                });
            })
            ->with([
                'InspectionVehicles.vehicleServiceTransaction',
                'InspectionVehicles.vehicle',
            ])
            ->orderByDesc('id')
            ->get();

        $latestPerNo = $inspections
            ->groupBy(function ($ins) {
                $billNo = strtoupper(trim((string) ($ins->no ?? '')));

                return $billNo !== '' ? 'no:' . $billNo : 'id:' . $ins->id;
            })
            ->map(fn ($group) => $group->sortByDesc('id')->first())
            ->values();

        $filteredLatest = $latestPerNo;
        $status         = strtolower(trim((string) $tax_vehicle));

        if (in_array($status, ['check', 'wait', 'send', 'complete', 'success'], true)) {
            $filteredLatest = $latestPerNo->filter(
                fn ($ins) => strtolower(trim((string) ($ins->tax_vehicle ?? ''))) === $status
            )->values();
        }

        $filteredLatest = $this->dedupeCarTaxExportsByLicensePlate($filteredLatest);
        $exportData     = $this->prepareCarTaxStatusExportPayload($filteredLatest);

        $excel_name = 'รายงานภาษีรถ.xlsx';
        if ($status === 'send') {
            $excel_name = 'รายงานภาษีรถ_รายการนำส่ง.xlsx';
        } elseif ($status === 'complete') {
            $excel_name = 'รายงานภาษีรถ_รอรับป้าย.xlsx';
        } elseif ($status === 'success') {
            $excel_name = 'รายงานภาษีรถ_สำเร็จ.xlsx';
        }

        $sd_th = $start_date ? Carbon::parse($start_date)->format('d/m/') . (Carbon::parse($start_date)->year + 543) : null;
        $ed_th = $end_date ? Carbon::parse($end_date)->format('d/m/') . (Carbon::parse($end_date)->year + 543) : null;

        $result = new ReportCarTaxVehicleExport($exportData, $tax_vehicle, $sd_th, $ed_th);

        return Excel::download($result, $excel_name);
    }

    private function normalizeDailyReportPaidType(?string $paidType): ?string
    {
        if ($paidType === null || $paidType === '') {
            return null;
        }

        return $paidType === 'paid_credit' ? 'credit' : $paidType;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDailyReportFixRows(WorkOrder $item, string $customerLabel, $branchId): array
    {
        $base = [
            'date'          => $item->date,
            'document_no'   => $item->no,
            'document_type' => 'Fix',
            'name'          => 'ใบซ่อม',
            'license_plate' => $item->license_plate ?? '-',
            'customer_name' => $customerLabel !== '' ? $customerLabel : '-',
            'type'          => 'รายรับ',
            'category'      => 'ใบซ่อม',
            'branch_id'     => $item->branches_id ?? $branchId,
            'status'        => $item->status,
            'reference_id'  => $item->id,
        ];

        $rows     = [];
        $payments = $item->WorkOrderPayment ?? collect();

        if ($payments->isNotEmpty()) {
            $summary = [];
            foreach ($payments as $payment) {
                $paidType = $this->normalizeDailyReportPaidType($payment->paid_type) ?? 'paid_cash';
                $summary[$paidType] = ($summary[$paidType] ?? 0) + (float) $payment->paid_price;
            }
            foreach ($summary as $paidType => $amount) {
                if ((float) $amount === 0.0) {
                    continue;
                }
                $rows[] = array_merge($base, [
                    'pay_date6' => $item->date,
                    'amount'    => $amount,
                    'paid_type' => $paidType,
                ]);
            }

            return $rows;
        }

        $paidType = $this->normalizeDailyReportPaidType($item->paid_type) ?? 'paid_cash';
        $amount   = (float) ($item->total_amount ?? $item->total ?? 0);
        if ($amount !== 0.0) {
            $rows[] = array_merge($base, [
                'pay_date6' => $item->date,
                'amount'    => $amount,
                'paid_type' => $paidType,
            ]);
        }

        return $rows;
    }
}

