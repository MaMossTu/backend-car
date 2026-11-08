<?php

namespace App\Http\Controllers;

use App\Models\Branches;
use App\Models\Commission;
use App\Models\Employees;
use App\Models\Inspections;
use App\Models\VechicleServicesTransactions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Export\CommissionCommissExport;

class CommissionController extends Controller
{
    public function commission_cal(Request $request)
    {
        $id = $request->id;
        $type = $request->type;

        if (!is_numeric($id)) {
            return $this->returnError('Invalid branch ID', 400);
        }

        if (!in_array($type, ['back', 'front'])) {
            return $this->returnError('Invalid type', 400);
        }

        // Find branch
        $branch = Branches::find($id);
        if (!$branch) {
            return $this->returnError('Branch not found', 404);
        }

        // Count employees by branch and type
        $numberOfEmployees = Employees::where('branch_id', $id)
            ->where('type', $type)
            ->count();

        $start_date = Carbon::parse($request->input('start_date'));
        $end_date = Carbon::parse($request->input('end_date'));

        // Join inspections, service_transactions, and services
        $inspections = DB::table('inspections')
            ->join('branches', 'inspections.branch_id', '=', 'branches.id')
            ->leftJoin('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->leftJoin('services', 'service_transactions.service_id', '=', 'services.id')
            ->where('branches.id', $id)
            ->whereBetween('inspections.date', [$start_date, $end_date])
            ->select(
                'inspections.id as inspection_id',
                'services.id as service_id',
                'services.name as service_name',
                'branches.cal_com'
            )
            ->get();

        // Check if inspections are empty
        if ($inspections->isEmpty()) {
            return $this->returnSuccess('No inspections found for this branch', [
                'branch_id' => $id,
                'items' => [],
            ]);
        }

        // Collect serviceIds from inspections
        $serviceIds = $inspections->pluck('service_id')->unique()->filter()->toArray();

        // Fetch commissions based on serviceIds and type
        $commissions = DB::table('commissions')
            ->whereIn('service_id', $serviceIds)
            ->where('type', $type)
            ->get();

        $commissionPrices = [];
        foreach ($commissions as $commission) {
            $commissionPrices[$commission->service_id] = $commission->price;
        }

        $serviceCounts = [];
        $totalCommissions = 0;

        // Calculate service counts and commissions
        foreach ($inspections as $inspection) {
            if ($inspection->cal_com == 'all') {
                if (!isset($serviceCounts[$inspection->service_id])) {
                    $serviceCounts[$inspection->service_id] = [
                        'name' => $inspection->service_name,
                        'count' => 0,
                    ];
                }
                $serviceCounts[$inspection->service_id]['count']++;
            } elseif ($inspection->cal_com == 'only') {
                // Process 'only' cases if necessary
            }
        }

        // Calculate total commissions
        foreach ($serviceCounts as $serviceId => &$serviceData) {
            $price = $commissionPrices[$serviceId] ?? 0;
            $serviceData['price'] = $price;
            $serviceData['total_price'] = $serviceData['count'] * $price;
            $totalCommissions += $serviceData['total_price'];
        }

        // Calculate commission per employee
        $commissionPerEmployee = $numberOfEmployees > 0 ? $totalCommissions / $numberOfEmployees : 0;

        return $this->returnSuccess('Success', [
            'service_counts' => $serviceCounts,
            'total_commissions' => $totalCommissions,
            'commission_per_employee' => $commissionPerEmployee,
        ]);
    }

    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $Item = new commission();
            $Item->service_id = $request->service_id;
            $Item->type = $request->type;
            $Item->price = $request->price;

            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    public function count_commissions(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId = $request->input('branch_id');

        if ($startDate && $endDate && $endDate->lt($startDate)) {
            return response()->json(['error' => 'End date must be greater than or equal to start date'], 400);
        }

        $query = Employees::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $employees = $query->with([
            'inspections' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                }
                $query->with('serviceTransactions.service');
            },
        ])->get();

        $serviceCounts = $employees->mapWithKeys(function ($employee) {
            $employeeName = $employee->first_name . ' ' . $employee->last_name;

            return [$employeeName => $employee->inspections->flatMap(function ($inspection) {
                return $inspection->serviceTransactions->map(function ($transaction) {
                    return $transaction->service->name;
                });
            })->countBy()];
        });

        return response()->json($serviceCounts);
    }

    public function Getpage_employ_commiss(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $branchId = trim($request->input('branch_id', null));
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $columns = [
            'id',
            'first_name',
            'last_name',
            'email',
            'hire_day',
            'phone_number',
            'code',
            'type',
            'branch_id',
            'created_at',
            'updated_at',
        ];

        $searchcolumn = [
            'first_name',
            'last_name',
            'email',
            'phone_number',
        ];

        $query = Employees::query()
        ->with([
            'inspections' => function ($q) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                }
                $q->with([
                    'inspectionVehicles' => function ($q) {
                        $q->with([
                            'vehicleServiceTransaction' => function ($q) {
                                $q->with('service');
                            }
                        ]);
                    }
                ]);
            }
        ]);
        // return $query->get();

        if (!is_null($branchId) && $branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (!empty($search['value'])) {
            $query->where(function ($q) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $q->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
                $No++;
                $item->No = $No;

                $serviceCounts = [];

                // สร้าง mapping ใหม่สำหรับชื่อบริการภาษาไทย
                $serviceMapping = [
                    'ตรวจสภาพรถ' => 'vehicle_Inspection',
                    'พ.ร.บ.' => 'compulsory_Insurance',
                    'ภาษีประจำปี' => 'annual_tax',
                    'เงินเพิ่มภาษี' => 'tax_fines',
                    'บริการต่อภาษี' => 'tax_renewal_service',
                    'ค่าปรับ' => 'penalty',
                    'ประกัน' => 'insurance',
                    'ตรวจสภาพ LPG' => 'LPG_inspection',
                    'ตรวจติดตั้ง LPG' => 'LPG_installation',
                    'ตรวจต่ออายุ LPG' => 'LPG_renewal',
                    'ตรวจสภาพ NGV' => 'NGV_inspection',
                    'ตรวจติดตั้ง NGV' => 'NGV_installation',
                    'หนังสือรับรองวิศวกร' => 'engineer_certificate',
                    'ขนส่ง' => 'transportation',
                    'อื่นๆ' => 'other',
                    'EMS' => 'EMS',
                ];

                foreach ($item->inspections as $inspection) {
                    foreach ($inspection->InspectionVehicles as $vehicle) {
                        foreach ($vehicle->vehicleServiceTransaction as $transaction) {
                            $originalName = $transaction->service->name ?? 'ไม่ทราบ';
                            $serviceKey = $serviceMapping[$originalName] ?? 'unknown';

                            if (!isset($serviceCounts[$serviceKey])) {
                                $serviceCounts[$serviceKey] = 0;
                            }
                            $serviceCounts[$serviceKey]++;
                        }
                    }
                }

                $item->serviceSummary = $serviceCounts;
                unset($item->inspections);
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
    private function commissionThaiServiceHeaders(): array
    {
        return [
            'ตรวจสภาพรถ',
            'พ.ร.บ.',
            'ภาษีประจำปี',
            'เงินเพิ่มภาษี',
            'บริการต่อภาษี',
            'ค่าปรับ',
            'ประกัน',
            'ตรวจสภาพ LPG',
            'ตรวจติดตั้ง LPG',
            'ตรวจต่ออายุ LPG',
            'ตรวจสภาพ NGV',
            'ตรวจติดตั้ง NGV',
            'หนังสือรับรองวิศวกร',
            'ขนส่ง',
            'อื่นๆ',
            'EMS',
        ];
    }

    private function employeesForCommissionReport(Request $request)
    {
        $branchId = trim((string) $request->input('branch_id', ''));
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $query = Employees::query()
            ->with([
                'inspections' => function ($q) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $q->whereBetween('date', [$startDate, $endDate]);
                    }
                    $q->with(['InspectionVehicles.vehicleServiceTransaction.service']);
                },
            ]);

        if ($branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    private function buildCommissionReportRows($employees): array
    {
        $serviceNames = $this->commissionThaiServiceHeaders();
        $data = [];

        foreach ($employees as $item) {
            $serviceCounts = array_fill_keys($serviceNames, 0);

            foreach ($item->inspections as $inspection) {
                foreach ($inspection->InspectionVehicles as $vehicle) {
                    foreach ($vehicle->vehicleServiceTransaction as $transaction) {
                        $originalName = $transaction->service->name ?? null;
                        if ($originalName !== null && isset($serviceCounts[$originalName])) {
                            $serviceCounts[$originalName]++;
                        }
                    }
                }
            }

            $data[] = [
                'first_name' => $item->first_name,
                'last_name' => $item->last_name,
                'email' => $item->email,
                'phone_number' => $item->phone_number,
                'services' => $serviceCounts,
            ];
        }

        return [$data, $serviceNames];
    }

    public function commissExcel(Request $request)
    {
        $employees = $this->employeesForCommissionReport($request);
        [$data, $serviceNames] = $this->buildCommissionReportRows($employees);

        try {
            return Excel::download(
                new CommissionCommissExport($data, $serviceNames),
                'CommissionReport.xlsx'
            );
        } catch (\Throwable $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error downloading file');
        }
    }

    public function commissPDF(Request $request)
    {
        $employees = $this->employeesForCommissionReport($request);
        [$data, $serviceNames] = $this->buildCommissionReportRows($employees);

        // PDF configuration and creation
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [base_path() . '/custom/font/directory']),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);

        $mpdf->SetTitle('รายงานค่าคอมมิชชั่นพนักงาน');
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
        <thead>
            <tr>
                <th rowspan="2">#</th>
                <th rowspan="2">ชื่อ</th>
                <th rowspan="2">นามสกุล</th>
                <th rowspan="2">อีเมล</th>
                <th rowspan="2">เบอร์โทร</th>
                <th colspan="' . count($serviceNames) . '">บริการ</th>
            </tr>
            <tr>';

        // Add the service names as table headers
        foreach ($serviceNames as $serviceName) {
            $html .= '<th>' . $serviceName . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data as $index => $employeeData) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $employeeData['first_name'] . '</td>';
            $html .= '<td>' . $employeeData['last_name'] . '</td>';
            $html .= '<td>' . $employeeData['email'] . '</td>';
            $html .= '<td>' . $employeeData['phone_number'] . '</td>';

            // Output service counts for each employee
            foreach ($serviceNames as $serviceName) {
                $serviceCount = $employeeData['services'][$serviceName] ?? 0;
                $html .= '<td>' . $serviceCount . '</td>';
            }

            $html .= '</tr>';
        }
        if (empty($data)) {
            $html .= '<tr>
            <td colspan="' . (count($serviceNames) + 5) . '" class="no-data">ไม่มีข้อมูล</td>
        </tr>';
        }

        $html .= '</tbody></table></thead>';
        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers' => 'Accept-Ranges,range',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }

}
