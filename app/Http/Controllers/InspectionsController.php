<?php

namespace App\Http\Controllers;

use App\Models\VehicleGas;
use Carbon\Carbon;
use App\Models\Doc;
use App\Models\ChaNo;
use App\Models\CheckGas;
use App\Models\Vehicles;
use App\Models\Customers;
use App\Models\Employees;
use App\Models\InstallNo;
use App\Models\Provinces;
use App\Models\AddressEms;
use App\Models\DocCustomer;
use App\Models\Inspections;
use App\Models\Installment;
use App\Models\PrbRenewals;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Save_document;
use App\Models\Doc_transaction;
use App\Models\InspectionVehicles;
use App\Models\InsurancesRenewals;
use App\Models\Services;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceTransactions;
use App\Models\Setting;
use App\Models\TaxInvoiceAllNos;
use Illuminate\Support\Facades\Log;
use App\Models\VehicleInspectionTypes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\VechicleServicesTransactions;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

class InspectionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = Inspections::all();
        return $Item;
    }

    public function index_vat()
    {
        $Item = Inspections::whereHas('InspectionVehicles.vehicleServiceTransaction', function ($query) {
            $query->where('is_vat', 1);
        })
        ->orderBy('id', 'desc')
        ->get();
        return $Item;
    }

    public function showID($id)
    {
        $Inspections = Inspections::where('id', $id)
            ->with('Customers.Customer_Addresses')
            ->with('InspectionVehicles.vehicle.VehicleInspectionTypes')
            ->with('InspectionVehicles.vehicleServiceTransaction.InsurancesRenewals')
            ->with('ServiceTransactions')
            ->with('save_document')
            ->with(['Installments' => function ($query) {
                $query->whereIn('type', ['vat', 'nonvat'])
                      ->orderBy('created_at', 'desc');
                    //   ->groupBy('type');
            }])
            ->with('Employees')
        // ->with('VehicleInspectionTypes')
            ->first();

        if (!$Inspections) {
            return $this->returnBadRequest('Inspections not found');
        } else {

            $groupedInstallments = $Inspections->Installments->groupBy('type')->map(function ($group) {
                return $group->first(); // ดึงรายการแรกที่จัดเรียงไว้แล้ว
            });

            // ตรวจสอบว่ามี Installments ประเภท vat
            if (!$groupedInstallments->has('vat')) {
                $groupedInstallments->put('vat', [
                    "inspection_id" => $Inspections->id,
                    "after_price" => $Inspections->total_vatcal ?? 0,
                    "type" => "vat"
                ]);
            }

            // ตรวจสอบว่ามี Installments ประเภท nonvat หรือไม่ ถ้าไม่มีให้สร้างใหม่
            if (!$groupedInstallments->has('nonvat')) {
                $groupedInstallments->put('nonvat', [
                    "inspection_id" => $Inspections->id,
                    "after_price" => $Inspections->total_nonvatcal ?? 0,
                    "type" => "nonvat"
                ]);
            }

            //================================== พรบ.
            $dateStartPrb = null;          // วันที่เริ่มคุ้มครอง พรบ.
            $dateEndPrb = null;            // วันที่สิ้นสุด พรบ.
            $insuranceNamesIdPrb = null;   // บริษัทประกัน
            $stampPrb = null;                        // อากรแสตมป์
            $taxPrb = null;                          // ภาษีมูลค่าเพิ่ม
            $amountPaidPrb = null;                   // เบี้ยพรบ. 600+3+42.21 = 645.21

            //================================== ประกัน
            $dateStart = null;             // วันที่เริ่มคุ้มครอง ประกัน
            $dateEnd = null;               // วันที่สิ้นสุด ประกัน
            $insuranceNamesId = null;                 // บริษัทประกัน
            $insuranceRenewalTypeId = null;           // id ระดับประกัน
            $amountPaid = null;                      // เบี้ยประกัน 600+3+42.21 = 645.21

            foreach ($Inspections->InspectionVehicles as $item) {
                foreach($item->vehicleServiceTransaction as $item2){
                    if($item2->service_id == 2){
                        $dateStartPrb = $item2->InsurancesRenewals[0]->date_start ?? null;
                        $dateEndPrb = $item2->InsurancesRenewals[0]->date_end ?? null;
                        $insuranceNamesIdPrb = $item2->InsurancesRenewals[0]->insurance_names_id ?? null;
                        $stampPrb = $item2->InsurancesRenewals[0]->stamp ?? null;
                        $taxPrb = $item2->InsurancesRenewals[0]->tax ?? null;
                        $amountPaidPrb = $item2->InsurancesRenewals[0]->amount_paid ?? null;
                    }
                    if($item2->service_id == 7){
                        $dateStart = $item2->InsurancesRenewals[0]->date_start ?? null;
                        $dateEnd = $item2->InsurancesRenewals[0]->date_end ?? null;
                        $insuranceNamesId = $item2->InsurancesRenewals[0]->insurance_names_id ?? null;
                        $insuranceRenewalTypeId = $item2->InsurancesRenewals[0]->insurance_renewal_type_id ?? null;
                        $amountPaid = $item2->InsurancesRenewals[0]->amount_paid ?? null;
                    }
                }
            }

            // แปลง Collection เป็น array และคืนค่า response
            $Inspections->Installments = $groupedInstallments->values();
            $Inspections->insurance_data = [
                'prb' => [
                    'date_start' => $dateStartPrb,
                    'date_end' => $dateEndPrb,
                    'insurance_names_id' => $insuranceNamesIdPrb,
                    'stamp' => $stampPrb,
                    'tax' => $taxPrb,
                    'amount_paid' => $amountPaidPrb
                ],
                'insurance' => [
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                    'insurance_names_id' => $insuranceNamesId,
                    'insurance_renewal_type_id' => $insuranceRenewalTypeId,
                    'amount_paid' => $amountPaid
                ]
            ];
            return $this->returnSuccess('Success', $Inspections);
        }
    }

    public function store_(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }
        $validator = Validator::make(request()->all(), [
            'appointment' => 'nullable|date',
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'remark' => 'nullable|string',
            'total' => 'required|numeric',
            'discount' => 'nullable|numeric',

            // ================================ รถ
            // 'vehicle' => 'required|array|min:1',
            // 'vehicle.*.vehicle_id' => 'nullable|integer',
            // 'vehicle.*.vehicle_inspection_types_id' => 'required|integer',
            // 'vehicle.*.insurance_types_id' => 'required|integer',
            // 'vehicle.*.license_plate' => 'required|string',
            // 'vehicle.*.province_id' => 'required|integer',
            // 'vehicle.*.regeister_date' => 'required|date',
            // 'vehicle.*.cc' => 'required|string',
            // 'vehicle.*.weight' => 'required|string',
            // 'vehicle.*.fuel_type' => 'required|in:oil,gas,Hybrid',
            // 'vehicle.*.type_document' => 'required|in:real,copy',
            'vehicle_id' => 'nullable|integer',
            'vehicle_inspection_types_id' => 'required|integer',
            'insurance_types_id' => 'required|integer',
            'license_plate' => 'required|string',
            'province_id' => 'required|integer',
            'regeister_date' => 'required|date',
            'cc' => 'required',
            'weight' => 'required',
            'fuel_type' => 'required|in:oil,gas,Hybrid',
            'type_document' => 'required|in:real,copy',

            // ================================ ลูกค้า
            'customer_id' => 'nullable|integer',
            'name' => 'required|string',
            'phone_number1' => 'required|string',
            'phone_number2' => 'nullable|string',
            'tax_id' => 'nullable|string',
            'type' => 'required|in:vendor,personal,agent',
            'is_headquarter' => 'required|boolean',

            //=================================== บริการ
            'List_service_tran' => 'required|array|min:1',
            'List_service_tran.*.service_id' => 'required|integer',
            'List_service_tran.*.service_price' => 'required|numeric',
            'List_service_tran.*.withholding_tax_percent'=> 'nullable|numeric|min:0|max:100',

        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            if ($request->customer_id) {
                $customer_id = $request->customer_id;
                $Customers = Customers::where('id', $request->customer_id)->first();
                $customer_no = $Customers->no;
                $customer_name = $Customers->name;
                $customer_email = $Customers->email;
                $customer_phone_number = $Customers->phone_number1;
                $customer_tax_id = $Customers->tax_id;
                $customer_type = $Customers->type;
                $customer_is_headquarter = $Customers->is_headquarter;
            } else {
                $Customers = Customers::where('name', $request->name)->first();
                if ($Customers) {
                    $customer_id = $Customers->id;
                    $customer_no = $Customers->no;
                    $customer_name = $Customers->name;
                    $customer_email = $Customers->email;
                    $customer_phone_number = $Customers->phone_number1;
                    $customer_tax_id = $Customers->tax_id;
                    $customer_type = $Customers->type;
                    $customer_is_headquarter = $Customers->is_headquarter;
                } else {
                    $prefix = "P-";
                    $no_cus = IdGenerator::generate(['table' => 'customers', 'field' => 'no', 'length' => 8, 'prefix' => $prefix]);
                    $Customers = new Customers();
                    $Customers->no = $no_cus;
                    $Customers->name = $request->name; //fix
                    $Customers->email = $request->email;
                    $Customers->phone_number1 = $request->phone_number1; //fix
                    $Customers->phone_number2 = $request->phone_number2;
                    $Customers->tax_id = $request->tax_id;
                    $Customers->type = $request->type; //fix
                    $Customers->is_headquarter = $request->is_headquarter;
                    $Customers->save();

                    $customer_id = $Customers->id;
                    $customer_no = $Customers->no;
                    $customer_name = $Customers->name;
                    $customer_email = $Customers->email;
                    $customer_phone_number = $Customers->phone_number1;
                    $customer_tax_id = $Customers->tax_id;
                    $customer_type = $Customers->type;
                    $customer_is_headquarter = $Customers->is_headquarter;
                }
            }

            $prefix = "NO-";
            $no = IdGenerator::generate(['table' => 'inspections', 'field' => 'no', 'length' => 8, 'prefix' => $prefix]);
            $Item = new Inspections();
            $Item->no = $no;
            $Item->appointment = $request->appointment; //วันนัดรับบิล
            $Item->status = "open"; //'open','overdue','unpaid','finish','cancel'
            $Item->employee_id = $request->employee_id;
            $Item->customer_id = $customer_id;
            $Item->date = $request->date; //วันที่ออกบิล
            $Item->remark = $request->remark;
            $Item->discount = $request->discount;
            $Item->total = $request->total;
            $Item->save();

            $inspectionId = $Item->id;
            $vehicle_arr = [
                [
                    'vehicle_id' => $request->vehicle_id,
                    'vehicle_inspection_types_id' => $request->vehicle_inspection_types_id,
                    'insurance_types_id' => $request->insurance_types_id,
                    'license_plate' => $request->license_plate,
                    'province_id' => $request->province_id,
                    'regeister_date' => $request->regeister_date,
                    'tax_renewal_date' => $request->tax_renewal_date,
                    'tax_due_date' => $request->tax_due_date,
                    'cc' => $request->cc,
                    'weight' => $request->weight,
                    'fuel_type' => $request->fuel_type,
                    'type_document' => $request->type_document,
                ]
            ];

            foreach ($vehicle_arr as $vehicle) {

                if (isset($vehicle['vehicle_id'])) {
                    $car_id = $vehicle['vehicle_id'];
                } else {
                    $car = Vehicles::where('license_plate', $vehicle['license_plate'])->first();
                    if ($car) {
                        $car_id = $car->id;
                    } else {
                        $car_new = new Vehicles();
                        $car_new->license_plate = $vehicle['license_plate'];
                        $car_new->customer_id = $customer_id;
                        $car_new->province_id = $vehicle['province_id'];
                        $car_new->vehicle_inspection_types_id = $vehicle['vehicle_inspection_types_id'];
                        $car_new->insurance_types_id = $vehicle['insurance_types_id'];
                        $car_new->cc = $vehicle['cc'];
                        $car_new->weight = $vehicle['weight'];
                        $car_new->fuel_type = $vehicle['fuel_type'];
                        $car_new->registration_date = $vehicle['regeister_date'];
                        $car_new->tax_renewal_date = $vehicle['tax_renewal_date'];
                        $car_new->notificate_tax = $vehicle['tax_due_date'];
                        $car_new->is_corporate = $vehicle['is_corporate'] ?? 0; // Default to 0 if not set
                        $car_new->type_document = $vehicle['type_document'];
                        $car_new->save();

                        $car_id = $car_new->id;
                    }
                }

                $inspection_vehicles = new InspectionVehicles();
                $inspection_vehicles->inspection_id = $inspectionId;
                $inspection_vehicles->vehicle_id = $car_id;

                $vehicle_inspection_types = VehicleInspectionTypes::where('id', $vehicle['vehicle_inspection_types_id'])->first();
                $inspection_vehicles->vehicle_inspection_types = $vehicle_inspection_types->name;
                $insurance_types = VehicleInspectionTypes::where('id', $vehicle['insurance_types_id'])->first();
                $inspection_vehicles->insurance_types = $insurance_types->name;
                $province = Provinces::where('id', $vehicle['province_id'])->first();
                $inspection_vehicles->provinces = $province->name_th;

                $inspection_vehicles->license_plate = $vehicle['license_plate'];
                $inspection_vehicles->registration_date = $vehicle['regeister_date'];
                $inspection_vehicles->cc = $vehicle['cc'];
                $inspection_vehicles->weight = $vehicle['weight'];
                $inspection_vehicles->type_document = $vehicle['type_document'];
                $inspection_vehicles->fuel_type = $vehicle['fuel_type'];
                $inspection_vehicles->no = $customer_no;
                $inspection_vehicles->name = $customer_name;
                $inspection_vehicles->email = $customer_email;
                $inspection_vehicles->phone_number = $customer_phone_number;
                $inspection_vehicles->tax_id = $customer_tax_id;
                $inspection_vehicles->type = $customer_type;
                $inspection_vehicles->is_headquarter =  $customer_is_headquarter;

                $inspection_vehicles->save();

                $List_service_tran = array_filter($request->List_service_tran, function($service) {
                    $status = $service['status'] ?? 1;
                    return $status === 1;
                });
                foreach ($List_service_tran as $item) {
                    $service_transaction = new VechicleServicesTransactions();
                    $service_transaction->inspection_vehicle_id = $inspection_vehicles->id;
                    $service_transaction->service_id = $item['service_id'];
                    $service_transaction->service_price = $item['service_price'];
                    // ตรวจสอบว่ามีการส่ง withholding_tax_percent มาหรือไม่
                    $taxPercent = $item['withholding_tax_percent'] ?? 0;
                    $withholdingTaxAmount = $item['service_price'] * ($taxPercent / 100);
                    $service_transaction->withholding_tax_percent = $taxPercent;
                    $service_transaction->withholding_tax_amount  = $withholdingTaxAmount;

                    $service_transaction->save();

                    if ($service_transaction->service_id == 1) { //ภาษี
                        $Item->tax_vehicle = "check";
                        $Item->save();
                    }

                    if ($service_transaction->service_id == 2) { //พรบ.
                        if (!$request->insurance_names_id_prb) {
                            return $this->returnErrorData('ต้องการข้อมูลบริษัทพรบ.', 404);
                        }

                        $existingPrb = InsurancesRenewals::where('vehicle_id', $car_id)
                            ->where('policy_status', '!=', 'expired')
                            ->first();
                        if ($existingPrb) {
                            $existingPrb->policy_status = 'expired';
                            $existingPrb->save();
                        }

                        $prb = new InsurancesRenewals();
                        $prb->vehicle_id = $car_id; //รถ
                        $prb->vechicle_services_transaction_id = $service_transaction->id; //ใบวางบิล
                        $prb->insurance_names_id = $request->insurance_names_id_prb; //บริษัทประกัน
                        $prb->date_start = $request->date_start_prb; //วันที่เริ่มคุ้มครอง พรบ.
                        $prb->date_end = $request->date_end_prb; //วันที่สิ้นสุด พรบ.
                        $prb->amount_paid = $request->amount_paid_prb; //เบี้ยพรบ
                        $prb->stamp = $request->stamp_prb; //อากรแสตมป์
                        $prb->tax = $request->tax_prb; //ภาษีมูลค่าเพิ่ม
                        $prb->type = 'prb';
                        $prb->save();
                    };
                    if ($service_transaction->service_id == 7) { //ประกัน.
                        if (!$request->insurance_names_id) {
                            return $this->returnErrorData('ต้องการข้อมูลบริษัทประกัน', 404);
                        }

                        $existingInsurance = InsurancesRenewals::where('vehicle_id', $car_id)
                            ->where('policy_status', '!=', 'expired')
                            ->first();

                        // ถ้ามีข้อมูลให้ทำการอัปเดต policy_status เป็น expired
                        if ($existingInsurance) {
                            $existingInsurance->policy_status = 'expired';
                            $existingInsurance->save();
                        }

                        $Insurances = new InsurancesRenewals();
                        $Insurances->vehicle_id = $car_id; //รถ
                        $Insurances->vechicle_services_transaction_id = $service_transaction->id; //ใบวางบิล
                        $Insurances->insurance_names_id = $request->insurance_names_id; //บริษัทประกัน
                        $Insurances->date_start = $request->date_start; //วันที่เริ่มคุ้มครอง พรบ.
                        $Insurances->date_end = $request->date_end; //วันที่สิ้นสุด พรบ.
                        $Insurances->amount_paid = $request->amount_paid; //เบี้ยพรบ
                        $Insurances->insurance_renewal_type_id = $request->insurance_renewal_type_id; //ประเภท ป1 ป2
                        $Insurances->type = 'insurances';
                        $Insurances->save();
                    };
                    if (in_array($service_transaction->service_id, [8, 9, 10, 11, 12])) { // แก๊ส
                        $checkgas = new CheckGas();
                        $checkgas->inspection_vehicle_id = $inspection_vehicles->id;
                        $checkgas->customer_id = $customer_id;
                        $checkgas->customer_no = $customer_no;
                        $checkgas->customer_name = $customer_name;
                    }

                }
            }

            if ($request->hasFile('List_save_document')) {
                foreach ($request->List_save_document as $file) {
                    $save_file = new Save_document();
                    $save_file->inspection_id = $inspectionId;
                    $name = str::random(8);
                    $save_file->name = $name;
                    $picname = $no;
                    $save_file->document = $this->save_Document($file, $name, $picname);
                    $save_file->save();
                }
            }
            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มข้อมูล สร้างงาน';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $Item->id;
            $this->Log($userId, $description, $type);


            DB::commit();
            return $this->returnSuccess('Data saved successfully', $Item);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('An error occurred while saving the data: ' . $e->getMessage(), 404);
        }
    }
    public function store(Request $request)
    {
        // ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        // ====================== Validator ======================
        $validator = Validator::make($request->all(), [
            'appointment' => 'nullable|date',
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'remark' => 'nullable|string',
            'total' => 'required|numeric',
            'discount' => 'nullable|numeric',

            // ข้อมูลรถ
            'vehicle_id' => 'nullable|integer',
            'vehicle_inspection_types_id' => 'required|integer',
            'insurance_types_id' => 'required|integer',
            'license_plate' => 'required|string',
            'province_id' => 'required|integer',
            'regeister_date' => 'required|date',
            'cc' => 'required',
            'weight' => 'required',
            'fuel_type' => 'required|in:oil,gas,Hybrid',
            'type_document' => 'required|in:real,copy',

            // ข้อมูลลูกค้า
            'customer_id' => 'nullable|integer',
            'name' => 'required|string',
            'phone_number1' => 'required|string',
            'phone_number2' => 'nullable|string',
            'tax_id' => 'nullable|string',
            'type' => 'required|in:vendor,personal,agent',
            'is_headquarter' => 'required|boolean',

            // บริการ
            'List_service_tran' => 'required|array|min:1',
            'List_service_tran.*.service_id' => 'required|integer',
            'List_service_tran.*.service_price' => 'required|numeric',
            'List_service_tran.*.withholding_tax_percent'=> 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง',
                'errors' => $validator->errors()
            ], 400);
        }

        DB::beginTransaction();
        try {
            // ====================== 1) จัดการข้อมูลลูกค้า ======================
            $customerData = $this->getOrCreateCustomer($request);
            // ====================== 2) สร้างข้อมูลใบตรวจสภาพ (Inspections) ======================
            // $prefix = "NO-";
            // $no = IdGenerator::generate([
            //     'table' => 'inspections',
            //     'field' => 'no',
            //     'length' => 8,
            //     'prefix' => $prefix
            // ]);
            // $lastInspection = Inspections::where('branch_id', $request->branch_id)
            //     ->orderBy('id', 'desc')
            //     ->first();
            // $lastNumber = $lastInspection ? (int)str_replace('NO-', '', $lastInspection->no) : 0;
            // $nextNumber = $lastNumber + 1;
            // $digits = max(5, strlen((string)$nextNumber)); // อย่างน้อย 5 หลัก หรือมากกว่าตามความจำเป็น
            // $newNumber = 'NO-' . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
            $no = $this->genInspectionNoByYear($request->branch_id, $request->date);

            $inspection = new Inspections();
            $inspection->no         = $no;
            $inspection->appointment= $request->appointment;
            $inspection->status     = "open";   // 'open','overdue','unpaid','finish','cancel'
            $inspection->employee_id= $request->employee_id;
            $inspection->customer_id= $customerData['customer_id'];
            $inspection->date       = $request->date; // วันที่ออกบิล
            $inspection->remark     = $request->remark;
            $inspection->remark_transport     = $request->remark_transport;
            $inspection->total      = $request->total;
            $inspection->discount   = $request->discount;
            $inspection->total_nonvat   = $request->total_nonvat;
            $inspection->total_vat      = $request->total_vat;
            $inspection->total_nonvatcal   = $request->total_nonvatcal;
            $inspection->total_vatcal      = $request->total_vatcal;
            $inspection->discount_before_vat     = $request->discount_before_vat;
            $inspection->discount_after_vat      = $request->discount_after_vat;
            $inspection->branch_id  = $request->branch_id;
            $inspection->save();

            // เก็บค่าไว้ใช้งานต่อ
            $inspectionId = $inspection->id;

            // ====================== 3) จัดการข้อมูลรถ (Vehicle) ======================
            // ปรับให้ใช้ array เดียวเก็บข้อมูลรถ ในกรณีที่อนาคตต้องเพิ่มหลายคัน
            $vehicleDataArr = [
                [
                    'vehicle_id'                  => $request->vehicle_id,
                    'vehicle_inspection_types_id' => $request->vehicle_inspection_types_id,
                    'insurance_types_id'          => $request->insurance_types_id,
                    'license_plate'               => $request->license_plate,
                    'province_id'                 => $request->province_id,
                    'regeister_date'              => $request->regeister_date,
                    'tax_renewal_date'            => $request->tax_renewal_date,
                    'tax_due_date'                => $request->tax_due_date,
                    'cc'                          => $request->cc,
                    'weight'                      => $request->weight,
                    'fuel_type'                   => $request->fuel_type,
                    'type_document'               => $request->type_document,
                ]
            ];

            foreach ($vehicleDataArr as $vehicleData) {
                // 3.1) ดึงหรือสร้างข้อมูลรถในตาราง vehicles
                $car_id = $this->getOrCreateVehicle(
                    $vehicleData,
                    $customerData['customer_id']
                );

                // 3.2) จัดการตาราง inspection_vehicles
                $inspectionVehicle = $this->createInspectionVehicle(
                    $vehicleData,
                    $inspectionId,
                    $car_id,
                    $customerData
                );

                // ====================== 4) บันทึกบริการ (List_service_tran) ======================
                $this->createVechicleServicesTransaction(
                    $request,
                    $inspection,
                    $inspectionVehicle,
                    $car_id
                );

            }

            // ====================== 5) จัดการไฟล์แนบ (Save_document) ======================
            if ($request->hasFile('List_save_document')) {
                foreach ($request->List_save_document as $file) {
                    $save_file = new Save_document();
                    $save_file->inspection_id = $inspectionId;

                    $name = Str::random(8);
                    $save_file->name = $name;
                    $picname = $newNumber;
                    $save_file->document = $this->save_Document($file, $name, $picname);
                    $save_file->save();
                }
            }

            // ====================== 6) บันทึก LOG ======================
            $userId     = $loginBy->id;
            $type       = 'เพิ่มข้อมูล สร้างงาน';
            $description= 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $inspection->id;
            $this->Log($userId, $description, $type);

            $inspection->car_id = $car_id;

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $inspection);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData(
                'An error occurred while saving the data: ' . $e->getMessage(),404
            );
        }
    }
    public function update(Request $request, $id)
    {
        // ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        // ====================== Validator ======================
        $validator = Validator::make($request->all(), [
            'appointment' => 'nullable|date',
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'remark' => 'nullable|string',
            'total' => 'required|numeric',
            'discount' => 'nullable|numeric',

            // ข้อมูลรถ
            'vehicle_id' => 'nullable|integer',
            'vehicle_inspection_types_id' => 'required|integer',
            'insurance_types_id' => 'required|integer',
            'license_plate' => 'required|string',
            'province_id' => 'required|integer',
            'regeister_date' => 'required|date',
            'cc' => 'required',
            'weight' => 'required',
            'fuel_type' => 'required|in:oil,gas,Hybrid',
            'type_document' => 'required|in:real,copy',

            // ข้อมูลลูกค้า
            'customer_id' => 'nullable|integer',
            'name' => 'required|string',
            'phone_number1' => 'required|string',
            'phone_number2' => 'nullable|string',
            'tax_id' => 'nullable|string',
            'type' => 'required|in:vendor,personal,agent',
            'is_headquarter' => 'required|boolean',

            // บริการ
            'List_service_tran' => 'required|array|min:1',
            'List_service_tran.*.service_id' => 'required|integer',
            'List_service_tran.*.service_price' => 'required|numeric',
            'List_service_tran.*.withholding_tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง',
                'errors' => $validator->errors()
            ], 400);
        }

        DB::beginTransaction();
        try {
            // ====================== 1) จัดการข้อมูลลูกค้า ======================
            $customerData = $this->getOrCreateCustomer($request);


            // ====================== 2) สร้างข้อมูลใบตรวจสภาพ (Inspections) ======================
            // $prefix = "NO-";
            // $no = IdGenerator::generate([
            //     'table' => 'inspections',
            //     'field' => 'no',
            //     'length' => 8,
            //     'prefix' => $prefix
            // ]);

            $inspection = Inspections::where('id',$id)->with('InspectionVehicles')->first();
            if (!$inspection) {
                return $this->returnErrorData('Inspection not found', 404);
            }
            // $inspection->no               = $no;
            $inspection->appointment         = $request->appointment;
            $inspection->status              = "open"; // 'open','overdue','unpaid','finish','cancel'
            $inspection->employee_id         = $request->employee_id;
            $inspection->customer_id         = $customerData['customer_id'];
            $inspection->date                = $request->date; // วันที่ออกบิล
            $inspection->remark              = $request->remark;
            $inspection->discount            = $request->discount;
            $inspection->total               = $request->total;
            $inspection->total_nonvat        = $request->total_nonvat;
            $inspection->total_vat           = $request->total_vat;
            $inspection->discount_before_vat = $request->discount_before_vat;
            $inspection->discount_after_vat  = $request->discount_after_vat;
            $inspection->save();


            // เก็บค่าไว้ใช้งานต่อ
            $inspectionId = $inspection->id;
            // ====================== 3) จัดการข้อมูลรถ (Vehicle) ======================
            // ปรับให้ใช้ array เดียวเก็บข้อมูลรถ ในกรณีที่อนาคตต้องเพิ่มหลายคัน
            $vehicleDataArr = [
                [
                    'vehicle_id'                  => $request->vehicle_id,
                    'vehicle_inspection_types_id' => $request->vehicle_inspection_types_id,
                    'insurance_types_id'          => $request->insurance_types_id,
                    'license_plate'               => $request->license_plate,
                    'province_id'                 => $request->province_id,
                    'regeister_date'              => $request->regeister_date,
                    'tax_renewal_date'            => $request->tax_renewal_date,
                    'tax_due_date'                => $request->tax_due_date,
                    'cc'                          => $request->cc,
                    'weight'                      => $request->weight,
                    'fuel_type'                   => $request->fuel_type,
                    'type_document'               => $request->type_document,
                ]
            ];

            foreach ($vehicleDataArr as $vehicleData) {

                // 3.1) ดึงหรือสร้างข้อมูลรถในตาราง vehicles

                $car_id = $this->getOrCreateVehicle(
                    $vehicleData,
                    $customerData['customer_id']
                );
                if(count($inspection->InspectionVehicles) > 0){

                    $car = Vehicles::where('license_plate', $vehicleData['license_plate'])->first();
                    if ($car) {
                        $car->tax_renewal_date = $vehicleData['tax_renewal_date'];
                        $car->save();
                    }

                    $inspectionVehicleID = $inspection->InspectionVehicles[0]->id;
                    $data = VechicleServicesTransactions::where('inspection_vehicle_id',$inspectionVehicleID)->get();
                    foreach($data as $dataloop){
                        if($dataloop->service_id == 2 || $dataloop->service_id == 7){
                            InsurancesRenewals::where('vechicle_services_transaction_id', $dataloop->id)->forceDelete();
                        }
                        if(in_array($dataloop->service_id, [8, 9, 10, 11, 12])){
                            CheckGas::where('inspection_vehicle_id', $inspectionVehicleID)->forceDelete();
                        }
                    }
                }
                InspectionVehicles::where('inspection_id', $inspectionId)->forceDelete();
                VechicleServicesTransactions::where('inspection_vehicle_id', $inspectionVehicleID)->forceDelete();

                // 3.2) จัดการตาราง inspection_vehicles
                $inspectionVehicle = $this->createInspectionVehicle(
                    $vehicleData,
                    $inspectionId,
                    $car_id,
                    $customerData
                );

                // ====================== 4) บันทึกบริการ (List_service_tran) ======================
                $this->createVechicleServicesTransaction(
                    $request,
                    $inspection,
                    $inspectionVehicle,
                    $car_id,
                    false
                );
            }

            // ====================== 5) จัดการไฟล์แนบ (Save_document) ======================
            // if ($request->hasFile('List_save_document')) {
            //     foreach ($request->List_save_document as $file) {
            //         $save_file = new Save_document();
            //         $save_file->inspection_id = $inspectionId;

            //         $name = Str::random(8);
            //         $save_file->name = $name;
            //         $picname = $no;
            //         $save_file->document = $this->save_Document($file, $name, $picname);
            //         $save_file->save();
            //     }
            // }

            // ====================== 6) บันทึก LOG ======================
            $userId     = $loginBy->id;
            $type       = 'เพิ่มข้อมูล สร้างงาน';
            $description= 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $inspection->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $inspection);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData(
                'An error occurred while saving the data: ' . $e->getMessage(),
                404
            );
        }
    }

    /**
     * #####################################################################################
     * ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓  ส่วนฟังก์ชันช่วย (Helper Methods) ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
     * #####################################################################################
     */

    /**
     * ตรวจสอบว่ามีลูกค้าในระบบหรือไม่
     */
    private function getOrCreateCustomer($request)
    {
        if ($request->customer_id) {
            $customer = Customers::find($request->customer_id);
            if ($customer) {

                $customer->name          = $request->name ?? $customer->name;
                $customer->lname          = $request->lname ?? $customer->lname;
                $customer->email         = $request->email ?? $customer->email;
                $customer->phone_number1 = $request->phone_number1 ?? $customer->phone_number1;
                $customer->phone_number2 = $request->phone_number2 ?? $customer->phone_number2;
                $customer->tax_id        = $request->tax_id ?? $customer->tax_id;
                $customer->type          = $request->type ?? $customer->type;
                $customer->is_headquarter= $request->is_headquarter ?? $customer->is_headquarter;
                $customer->note          = $request->note ?? $customer->note;
                $customer->save();
    
                return [
                    'customer_id'         => $customer->id,
                    'customer_no'         => $customer->no,
                    'customer_name'       => $customer->name . ' ' . $customer->lname,
                    'customer_email'      => $customer->email,
                    'customer_phone_number'=> $customer->phone_number1,
                    'customer_tax_id'     => $customer->tax_id,
                    'customer_type'       => $customer->type,
                    'customer_is_headquarter' => $customer->is_headquarter,
                    'customer_note' => $customer->note,
                ];
            } 
        }

        $prefix = "";
        if ($request->type == "personal") {
            $prefix = "P-";
        } else if ($request->type == "vendor") {
            $prefix = "CS-";
        } else if ($request->type == "agent") {
            $prefix = "A-";
        }
        $no_cus= IdGenerator::generate([
            'table' => 'customers',
            'field' => 'no',
            'length' => 8,
            'prefix' => $prefix,
            'reset_on_prefix_change' => true
        ]);

        $newCustomer = new Customers();
        $newCustomer->no            = $no_cus;
        $newCustomer->name          = $request->name;
        $newCustomer->lname          = $request->lname;
        $newCustomer->email         = $request->email;
        $newCustomer->phone_number1 = $request->phone_number1;
        $newCustomer->phone_number2 = $request->phone_number2;
        $newCustomer->tax_id        = $request->tax_id;
        $newCustomer->type          = $request->type;
        $newCustomer->is_headquarter= $request->is_headquarter;
        $newCustomer->note          = $request->note;
        $newCustomer->save();

        return [
            'customer_id'         => $newCustomer->id,
            'customer_no'         => $newCustomer->no,
            'customer_name'       => $newCustomer->name . ' ' . $newCustomer->lname,
            'customer_email'      => $newCustomer->email,
            'customer_phone_number'=> $newCustomer->phone_number1,
            'customer_tax_id'     => $newCustomer->tax_id,
            'customer_type'       => $newCustomer->type,
            'customer_is_headquarter' => $newCustomer->is_headquarter,
            'customer_note'         => $newCustomer->note,
        ];
    }

    private function getOrCreateVehicle(array $vehicleData, $customerId)
    {
        if (isset($vehicleData['vehicle_id'])) {
            return $vehicleData['vehicle_id'];
        }

        // ถ้าไม่มี vehicle_id ให้ลองหาจากป้ายทะเบียน
        $car = Vehicles::where('license_plate', $vehicleData['license_plate'])->first();
        if ($car) {
            if($car->customer_id != $customerId){
                $car->customer_id = $customerId;
                $car->save();
            }
            return $car->id;
        }

        // สร้างรถใหม่
        $car_new = new Vehicles();
        $car_new->license_plate                = $vehicleData['license_plate'];
        $car_new->customer_id                  = $customerId;
        $car_new->province_id                  = $vehicleData['province_id'];
        $car_new->vehicle_inspection_types_id  = $vehicleData['vehicle_inspection_types_id'];
        $car_new->insurance_types_id           = $vehicleData['insurance_types_id'];
        $car_new->cc                           = $vehicleData['cc'];
        $car_new->weight                       = $vehicleData['weight'];
        $car_new->fuel_type                    = $vehicleData['fuel_type'];
        $car_new->registration_date            = $vehicleData['regeister_date'];
        $car_new->tax_renewal_date             = $vehicleData['tax_renewal_date'];
        $car_new->notificate_tax               = $vehicleData['tax_due_date'];
        $car_new->is_corporate                 = $vehicleData['is_corporate'] ?? 0;
        $car_new->type_document                = $vehicleData['type_document'];
        $car_new->save();

        return $car_new->id;
    }

    /**
     * สร้างข้อมูลในตาราง inspection_vehicles
     */
    private function createInspectionVehicle(array $vehicleData, $inspectionId, $car_id, array $customerData)
    {
        $inspectionVehicle = new InspectionVehicles();
        $inspectionVehicle->inspection_id = $inspectionId;
        $inspectionVehicle->vehicle_id    = $car_id;

        // ดึงข้อมูลเพื่อบันทึกเป็น text
        $vehicle_inspection_types = VehicleInspectionTypes::find($vehicleData['vehicle_inspection_types_id']);
        $inspectionVehicle->vehicle_inspection_types = $vehicle_inspection_types ? $vehicle_inspection_types->name : '';

        $insurance_types = VehicleInspectionTypes::find($vehicleData['insurance_types_id']);
        $inspectionVehicle->insurance_types = $insurance_types ? $insurance_types->name : '';

        $province = Provinces::find($vehicleData['province_id']);
        $inspectionVehicle->provinces = $province ? $province->name_th : '';

        // ใส่รายละเอียดรถ
        $inspectionVehicle->license_plate     = $vehicleData['license_plate'];
        $inspectionVehicle->registration_date = $vehicleData['regeister_date'];
        $inspectionVehicle->cc               = $vehicleData['cc'];
        $inspectionVehicle->weight           = $vehicleData['weight'];
        $inspectionVehicle->type_document    = $vehicleData['type_document'];
        $inspectionVehicle->fuel_type        = $vehicleData['fuel_type'];

        // ใส่รายละเอียดลูกค้า
        $inspectionVehicle->no               = $customerData['customer_no'];
        $inspectionVehicle->name             = $customerData['customer_name'];
        $inspectionVehicle->email            = $customerData['customer_email'];
        $inspectionVehicle->phone_number     = $customerData['customer_phone_number'];
        $inspectionVehicle->tax_id           = $customerData['customer_tax_id'];
        $inspectionVehicle->type             = $customerData['customer_type'];
        $inspectionVehicle->is_headquarter   = $customerData['customer_is_headquarter'];

        $inspectionVehicle->save();

        return $inspectionVehicle;
    }

    /**
     * จัดการการบันทึกข้อมูล service ในตาราง VechicleServicesTransactions
     * รวมถึงการสร้างข้อมูลพรบ./ประกัน/ตรวจแก๊ส
     */
    private function createVechicleServicesTransaction(
        Request $request,
        Inspections $inspection,
        InspectionVehicles $inspectionVehicle,
        $car_id,
        $shouldWithdrawDocument = true
    ) {
        $List_service_tran = array_filter($request->List_service_tran, function ($service) {
            return ($service['status'] ?? 1) === 1;
        });
        $checkGasCreated = false;
        foreach ($List_service_tran as $item) {
            $service_transaction = new VechicleServicesTransactions();
            $service_transaction->inspection_vehicle_id = $inspectionVehicle->id;
            $service_transaction->service_id            = $item['service_id'];
            $service_transaction->service_price         = $item['service_price'];
            $service_transaction->is_vat                = $item['is_vat'] ?? 0;

            // ตรวจสอบว่ามีการส่ง withholding_tax_percent มาหรือไม่
            $taxPercent = $item['withholding_tax_percent'] ?? 0;
            $withholdingTaxAmount = $item['service_price'] * ($taxPercent / 100);
            $service_transaction->withholding_tax_percent = $taxPercent;
            $service_transaction->withholding_tax_amount  = $withholdingTaxAmount;

            $service_transaction->save();

            // ========== กรณีเป็น "ภาษี" ==========
            if ($service_transaction->service_id == 3) {
                $inspection->tax_vehicle = "check";
                $inspection->save();
            }

            // ========== กรณีเป็น "พรบ." ==========
            if ($service_transaction->service_id == 2) {
                if (!$request->insurance_names_id_prb) {
                    throw new \Exception('ต้องการข้อมูลบริษัทพรบ.');
                }
                // set existing พรบ. ให้ expired
                $existingPrb = InsurancesRenewals::where('vehicle_id', $car_id)
                    ->where('policy_status', '!=', 'expired')
                    ->first();

                if ($existingPrb) {
                    $existingPrb->policy_status = 'expired';
                    $existingPrb->save();
                }

                // สร้างพรบ.ใหม่
                $prb = new InsurancesRenewals();
                $prb->vehicle_id                   = $car_id;
                $prb->vechicle_services_transaction_id = $service_transaction->id;
                $prb->insurance_names_id           = $request->insurance_names_id_prb;
                $prb->date_start                   = $request->date_start_prb;
                $prb->date_end                     = $request->date_end_prb;
                $prb->amount_paid                  = $request->amount_paid_prb;
                $prb->stamp                        = $request->stamp_prb;
                $prb->tax                          = $request->tax_prb;
                $prb->type                         = 'prb';
                $prb->save();

                // ====================== ตัดเอกสาร ======================
                if($shouldWithdrawDocument){
                    $type_doc = 'prb';
                    $doc = Doc::where('type_doc', $type_doc)
                            ->where('insurance_names_id', $request->insurance_names_id_prb)
                            ->first();
                    if($doc){
                        $stock = 0;
                        $doc_stock = Doc_transaction::where('doc_id', $doc->id)
                            ->where('branch_id', $inspection->branch_id)
                            ->latest('created_at')->first();;
                        if($doc_stock != null){
                            $stock = $doc_stock->balance;
                        }
                        $doc_transaction = new Doc_transaction();
                        $doc_transaction->doc_id = $doc->id;
                        $doc_transaction->branch_id = $inspection->branch_id;
                        $doc_transaction->inspection_id = $inspection->id;
                        $doc_transaction->transaction_type = 'Withdraw';
                        $doc_transaction->date = carbon::now();
                        $doc_transaction->stock =  $stock;
                        $doc_transaction->quantity =  1;
                        $doc_transaction->balance = $stock - 1;
                        $doc_transaction->save();
                    }
                }
            }

            // ========== กรณีเป็น "ประกัน" ==========
            if ($service_transaction->service_id == 7) {
                if (!$request->insurance_names_id) {
                    throw new \Exception('ต้องการข้อมูลบริษัทประกัน');
                }

                // set existing ประกัน ให้ expired
                $existingInsurance = InsurancesRenewals::where('vehicle_id', $car_id)
                    ->where('policy_status', '!=', 'expired')
                    ->first();
                if ($existingInsurance) {
                    $existingInsurance->policy_status = 'expired';
                    $existingInsurance->save();
                }

                // สร้างประกันใหม่
                $Insurances = new InsurancesRenewals();
                $Insurances->vehicle_id                   = $car_id;
                $Insurances->vechicle_services_transaction_id = $service_transaction->id;
                $Insurances->insurance_names_id           = $request->insurance_names_id;
                $Insurances->date_start                   = $request->date_start;
                $Insurances->date_end                     = $request->date_end;
                $Insurances->amount_paid                  = $request->amount_paid;
                $Insurances->insurance_renewal_type_id    = $request->insurance_renewal_type_id;
                $Insurances->type                         = 'insurances';
                $Insurances->save();

                // ====================== ตัดเอกสาร ======================
                if($shouldWithdrawDocument){
                    $type_doc = 'insu';
                    $doc = Doc::where('type_doc', $type_doc)
                            ->where('insurance_names_id', $request->insurance_names_id_prb)
                            ->first();
                    if($doc){
                        $stock = 0;
                        $doc_stock = Doc_transaction::where('doc_id', $doc->id)
                            ->where('branch_id', $inspection->branch_id)
                            ->latest()->first();
                        if($doc_stock != null){
                            $stock = $doc_stock->balance;
                        }
                        $doc_transaction = new Doc_transaction();
                        $doc_transaction->doc_id = $doc->id;
                        $doc_transaction->branch_id = $inspection->branch_id;
                        $doc_transaction->inspection_id = $inspection->id;
                        $doc_transaction->transaction_type = 'Withdraw';
                        $doc_transaction->date = carbon::now();
                        $doc_transaction->stock =  $stock;
                        $doc_transaction->quantity =  1;
                        $doc_transaction->balance = $stock - 1;
                        $doc_transaction->save();
                        // dd($doc_transaction);
                    }
                }
            }

            // ========== กรณีเป็น "ตรวจแก๊ส" ==========
            if (in_array($service_transaction->service_id, [8, 9, 10, 11, 12])&& !$checkGasCreated) {
                $checkgas = new CheckGas();
                $checkgas->inspection_vehicle_id  = $inspectionVehicle->id;
                $checkgas->customer_id            = $inspection->customer_id;
                $checkgas->customer_no            = $inspectionVehicle->no;
                $checkgas->customer_name          = $inspectionVehicle->name;
                $checkgas->save();
                $checkGasCreated = true;
            }

            // ========== กรณีเป็น "EMS" ==========
            if ($service_transaction->service_id == 16){
                $address_ems = new AddressEms();
                $address_ems->inspection_id = $inspection->id;
                $address_ems->province_id = $request->ems_province_id;
                $address_ems->district_id = $request->ems_district_id;
                $address_ems->subdistrict_id = $request->ems_subdistrict_id;
                $address_ems->address = $request->ems_address;
                $address_ems->name = $request->ems_name;

                $path = $this->saveImage_AddressEms($request, 'photo', $inspection->id);
                $address_ems->photo = $path;
                $address_ems->save();
            }
        }
    }

    public function update_(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $license_plate = $request->license_plate;
            $car = Vehicles::where('license_plate', $license_plate)
                ->first();

            if ($car) {
                $car_id = $car->id;
                $car->vehicle_inspection_types_id = $request->vehicle_inspection_types_id;
                $car->cc = $request->cc;
                $car->weight = $request->weight;
                $car->fuel_type = $request->fuel_type;
                $car->tax_renewal_date = $request->tax_renewal_date;
                $car->notificate_tax = $request->tax_due_date;
                $car->is_corporate = $request->is_corporate; //1,0 ว่าเป็นนิติบุคคล
                $car->save();
            } else {
                $car_new = new Vehicles();
                $car_new->license_plate = $request->license_plate;
                $car_new->province_id = $request->province_id;
                $car_new->vehicle_inspection_types_id = $request->vehicle_inspection_types_id;
                $car_new->cc = $request->cc;
                $car_new->weight = $request->weight;
                $car_new->fuel_type = $request->fuel_type;
                $car_new->registration_date = $request->regeister_date;
                $car_new->notificate_tax = $request->tax_due_date;
                $car_new->tax_renewal_date = $request->tax_renewal_date;
                $car_new->is_corporate = $request->is_corporate; //1,0 ว่าเป็นนิติบุคคล
                $car_new->save();

                $car_id = $car_new->id;
            }
            $customer_id = null;
            if ($request->name) {
                $Customers = Customers::where('name', $request->name)
                    ->first();
                if ($Customers) {
                    $customer_id = $Customers->id;
                } else {
                    if ($request->type === 'vendor') {
                        $prefix = "V-";
                    } else {
                        $prefix = "P-";
                    }
                    $no_cus = IdGenerator::generate(['table' => 'customers', 'field' => 'no', 'length' => 8, 'prefix' => $prefix]);
                    $Customers = new Customers();
                    $Customers->no = $no_cus;
                    $Customers->name = $request->name; //fix
                    $Customers->email = $request->email;
                    $Customers->phone_number1 = $request->phone_number1; //fix
                    $Customers->phone_number2 = $request->phone_number2;
                    $Customers->tax_id = $request->tax_id;
                    $Customers->type = $request->type; //fix
                    $Customers->is_headquarter = $request->is_headquarter;
                    $Customers->save();

                    $customer_id = $Customers->id;
                }
            }

            $Item = Inspections::findOrFail($id);
            // $Item->vehicle_inspection_types_id = $request->vehicle_inspection_types_id;
            $Item->appointment = $request->appointment;
            // $Item->tax = $request->tax;
            // $Item->type_document = $request->type_document;
            $Item->status = $request->status;
            $Item->tax_vehicle = $request->tax_vehicle; //ไว้แจ้งเตือนสถานะเมื่อมีการนำเข้าภาษี
            $Item->vehicle_id = $car_id;
            $Item->employee_id = $request->employee_id;
            $Item->customer_id = $customer_id;
            // $Item->EMS = $request->EMS;
            $Item->date = $request->date;
            $Item->result = $request->result;
            $Item->comments = $request->comments;
            $Item->discount = $request->discount;
            $Item->total = $request->total;

            $Item->save();

            ServiceTransactions::where('inspections_id', $id)->delete();

            $inspectionId = $Item->id;

            foreach ($request->List_service_tran as $item) {
                $service_transaction = new ServiceTransactions();
                $service_transaction->inspections_id = $inspectionId;
                $service_transaction->service_id = $item['service_id'];
                $service_transaction->service_price = $item['service_price'];
                $service_transaction->status = $item['status'];
                $service_transaction->save();
            }

            // save_document::where('inspection_id', $id)->each(function ($doc) {
            //     if (Storage::disk('public')->exists($doc->document)) {
            //         Storage::disk('public')->delete($doc->document);
            //     }
            //     $doc->delete();
            // });

            if ($request->hasFile('List_save_document')) {
                foreach ($request->file('List_save_document') as $file) {
                    $save_file = new Save_document();
                    $save_file->inspection_id = $id;
                    $no = $item->no;
                    $name = str::random(8);
                    $save_file->name = $name;
                    $picname = $item->no;
                    $save_file->document = $this->save_Document($file, $name, $picname, $no);
                    $save_file->save();
                }
            }

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $Item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('System error please try again ' . $e->getMessage(), 404);
        }
    }


    public function destroy($id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $Item = Inspections::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลการตรวจสภาพที่ต้องการลบ', 404);
            }

            ServiceTransactions::where('inspections_id', $id)->delete();
            Save_document::where('inspection_id', $id)->delete();
            $Item->delete();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' การตรวจสภาพที่มี id ' . $id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('ลบข้อมูลสำเร็จ', $Item);
        } catch (\Throwable $e) {
            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    // public function getPage(Request $request)
    // {
    //     $length = $request->length;
    //     $order = $request->order;
    //     $search = $request->search;
    //     $start = intval($request->start / $length) + 1;
    //     $page = $start;

    //     $orderColumn = [
    //         'no',
    //         'vehicle_inspection_types_id',
    //         'insurance_types_id',
    //         'cc',
    //         'weight',
    //         'tax_due_date',
    //         'status',
    //         'tax_renewal_date',
    //         'fuel_type',
    //         'appointment',
    //         'tax',
    //         'type_document',
    //         'date',
    //         'vehicles.license_plate',
    //         'services.name',
    //     ];

    //     $query = Inspections::with(['vehicles.customers', 'serviceTransactions.service'])
    //         ->whereHas('vehicles.customers', function ($query) use ($search) {
    //             $query->where('name', 'like', '%' . $search['value'] . '%');
    //         })
    //         ->whereHas('vehicles', function ($query) use ($search) {
    //             $query->where('license_plate', 'like', '%' . $search['value'] . '%');
    //         })
    //         ->whereHas('serviceTransactions', function ($query) use ($search) {
    //             $query->whereHas('service', function ($query) use ($search) {
    //                 $query->where('name', 'like', '%' . $search['value'] . '%');
    //             });
    //         })
    //         ->where(function ($query) use ($search) {
    //             $query->where('no', 'like', '%' . $search['value'] . '%')
    //                 ->orWhere('date', 'like', '%' . $search['value'] . '%');
    //         });

    //     $orderBy = $orderColumn[$order[0]['column']] ?? 'inspections.id';
    //     $query->orderBy($orderBy, $order[0]['dir']);

    //     // Paginate results
    //     $items = $query->paginate($length, ['*'], 'page', $page);

    //     if ($items->isNotEmpty()) {
    //         $No = (($page - 1) * $length);
    //         foreach ($items as $item) {
    //             $No++;
    //             $item->No = $No;
    //         }
    //     }
    //     return $items;
    //     return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    // }

    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $status = $request->status;
        $customer_id = $request->customer_id;
        $date_start = $request->date_start;
        $date_end = $request->date_end;
        $branch_id = $request->branch_id;

        $columns = [
            'id',
            'no',
            'appointment',
            'status',
            'tax_vehicle',
            'employee_id',
            'customer_id',
            'EMS',
            'date',
            'result',
            'remark',
            'total',
            'discount',
            'discount_before_vat',
            'discount_after_vat',
            'total_vatcal',
            'total_nonvatcal',
        ];

        $query = Inspections::with(['Customers', 'serviceTransactions', 'Customers', 'InspectionVehicles.vehicleServiceTransaction'])
            ->when($request->has('branch_id'), function ($query) use ($branch_id) {
                return $query->where('branch_id', $branch_id);
            })
            ->select($columns);

        if($date_start && $date_end){
            $query->whereBetween('date', [$date_start, $date_end]);
        }
        if (!empty($status)) {
            $allowedStatuses = ['open', 'overdue', 'unpaid', 'finish', 'cancel'];
            if (in_array($status, $allowedStatuses)) {
                $query->where('status', $status);
            }else if($status == 'remain'){
                $query->whereIn('status', ['overdue', 'unpaid']);
            }else {
                return $this->returnErrorData('สถานะที่ส่งมาไม่ถูกต้อง', 404);
            }

        }

        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);
        // $query->orderBy('created_at', 'desc');

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search) {
                $query->where('no', 'like', '%' . $search['value'] . '%')
                      ->orWhere('status', 'like', '%' . $search['value'] . '%')
                      ->orWhere('date', 'like', '%' . $search['value'] . '%');

                // ค้นหา license_plate จากตาราง InspectionVehicles
                $query->orWhereHas('InspectionVehicles', function ($q) use ($search) {
                    $q->where('license_plate', 'like', '%' . $search['value'] . '%');
                });
            });
        }

        // return $customer_id;
        if (!empty($customer_id)) {
            $query->where('customer_id', $customer_id);
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

    public function calTotal(Request $request)
    {
        $cc = $request->cc;
        $regeister_date = $request->regeister_date;
        $date = $request->date;
        $registerdate = Carbon::parse($regeister_date);
        $currentdate = Carbon::parse($date);
        $year = abs($currentdate->diffInYears($regeister_date));

        $price_total = $request->price;
        $totals = 0;

        foreach ($price_total as $cal) {
            $totals += $cal;
        }
        // dd($totals);

        if ($cc <= 600) {
            $cc_case = $cc * 0.5;
        }
        if ($cc >= 601 && $cc <= 1800) {
            $cc_case = (($cc - 600) * 1.5) + 300;
        }
        if ($cc > 1800) {
            $cc_case = (($cc - 1800) * 4) + 2100;
            // dd($cc);
        }

        if ($year > 5) {
            if ($year == 6) {
                $discount = 0.1;
            }
            if ($year == 7) {
                $discount = 0.2;
            }
            if ($year == 8) {
                $discount = 0.3;
            }
            if ($year == 9) {
                $discount = 0.4;
            }
            if ($year == 10) {
                $discount = 0.5;
            }
        }

        $total = 0;
        $total = ($total + $cc_case + $totals) * (1 - $discount);

        return $total;
    }

    public function calgreenweight(Request $request)
    {
        $discount = 0;
        $total = 0;
        $weight = $request->weight;
        $regeister_date = $request->regeister_date;
        $date = $request->date;
        $registerdate = Carbon::parse($regeister_date);
        $currentdate = Carbon::parse($date);
        $year = abs($currentdate->diffInYears($regeister_date));
        // dd($year);

        if ($weight > 0 && $weight <= 500) {
            $weight_price = 300;
        }
        if ($weight > 500 && $weight <= 750) {
            $weight_price = 450;
        }
        if ($weight > 750 && $weight <= 1000) {
            $weight_price = 600;
        }
        if ($weight > 1001 && $weight <= 1250) {
            $weight_price = 750;
        }
        if ($weight > 1251 && $weight <= 1500) {
            $weight_price = 900;
        }
        if ($weight > 1501 && $weight <= 1750) {
            $weight_price = 1050;
        }
        if ($weight > 1751 && $weight <= 2000) {
            $weight_price = 1350;
        }
        if ($weight > 2001 && $weight <= 2500) {
            $weight_price = 1650;
        }
        if ($weight > 2501 && $weight <= 3000) {
            $weight_price = 1950;
        }

        if ($year > 5) {
            if ($year == 6) {
                $discount = 10;
            }
            if ($year == 7) {
                $discount = 20;
            }
            if ($year == 8) {
                $discount = 30;
            }
            if ($year == 9) {
                $discount = 40;
            }
            if ($year == 10) {
                $discount = 50;
            }
        }
        $total = (($total + $weight_price) * (100 - $discount)) / 100;

        return $total;
    }

    public function calbluenweight(Request $request)
    {
        $weight = $request->weight;
        $regeister_date = $request->regeister_date;
        $date = $request->date;
        $registerdate = Carbon::parse($regeister_date);
        $currentdate = Carbon::parse($date);
        $year = abs($currentdate->diffInYears($regeister_date));

        if ($weight <= 1800) {
            $weight_price = 1300;
        }
        if ($weight > 1800) {
            $weight_price = 1600;
        }

        if ($year > 5) {
            if ($year == 6) {
                $discount = 0.1;
            }
            if ($year == 7) {
                $discount = 0.2;
            }
            if ($year == 8) {
                $discount = 0.3;
            }
            if ($year == 9) {
                $discount = 0.4;
            }
            if ($year == 10) {
                $discount = 0.5;
            }
        }
        $total = 0;
        $total = ($total + $weight_price) * (1 - $discount);

        return $total;
    }

    public function notifications(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $columns = [
            'vehicles.id',
            'vehicles.license_plate',
            'customer_name',
            'province_name',
            'phone_number',
            'vehicle_gases.gas_expire',
            'vehicles.notificate_insurance',
            'vehicles.notificate_sub_insurance',
            'vehicles.notificate_tax',
        ];

        $searchColumns = [
            'vehicles.license_plate',
            'customer_name',
            'phone_number',
        ];

        $today = Carbon::now();
        $ninetyDay = $today->copy()->addDays(90);

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = Vehicles::select(
            'vehicles.id',
            'vehicles.license_plate',
            DB::raw("COALESCE(customers.name, 'null') as customer_name"),
            DB::raw("COALESCE(provinces.name_th, 'null') as province_name"),
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

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);
        $id = ($page - 1) * $length;

        foreach ($items as $item) {
            $item->id = ++$id;

            if (empty($item->status)) {
                unset($item->status);
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    // public function notifications(Request $request)
    // {
    //     $length = $request->length;
    //     $order = $request->order;
    //     $search = $request->search;
    //     $start = $request->start;
    //     $page = $start / $length + 1;

    //     $vehicles = DB::table('vehicles as v')
    //         ->leftJoin('prb_renewals as pr', function ($join) {
    //             $join->on('v.id', '=', 'pr.vehicle_id')
    //                 ->where('pr.policy_status', '=', 'active');
    //         })
    //         ->leftJoin('insurances_renewals as ir', function ($join) {
    //             $join->on('v.id', '=', 'ir.vehicle_id')
    //                 ->where('ir.policy_status', '=', 'active');
    //         })
    //         ->select(
    //             'v.id as vehicle_id',
    //             'v.license_plate',
    //             'v.notificate_tax as tax_expiry_date',
    //             DB::raw('MAX(pr.date_end) as prb_expiry_date'),
    //             DB::raw('MAX(ir.date_end) as insurance_expiry_date'),
    //             DB::raw('(v.notificate_tax BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)) as tax_expiry_status'),
    //             DB::raw('(
    //                 SELECT MAX(pr_sub.date_end)
    //                 FROM prb_renewals as pr_sub
    //                 WHERE pr_sub.vehicle_id = v.id AND pr_sub.policy_status = "active"
    //             ) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) as prb_expiry_status'),
    //             DB::raw('(
    //                 SELECT MAX(ir_sub.date_end)
    //                 FROM insurances_renewals as ir_sub
    //                 WHERE ir_sub.vehicle_id = v.id AND ir_sub.policy_status = "active"
    //             ) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) as insurance_expiry_status')
    //         )
    //         ->groupBy('v.id', 'v.license_plate', 'v.notificate_tax')
    //         ->havingRaw('tax_expiry_status = 1 OR prb_expiry_status = 1 OR insurance_expiry_status = 1')
    //         ->get();

    //     return response()->json($vehicles);
    // }

    public function calculate(Request $request)
    {
        $total = 0;

        foreach ($request->input('items', []) as $item) {
            $total += $item['price'];
        }

        return $total;
    }

    public function updateStatus_discount(Request $request, $id)
    {

        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }
        DB::beginTransaction();

        try {
            $inspection_status = Inspections::find($id);
            if (!$inspection_status) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }
            $inspection_status->status      = $request->status;
            $inspection_status->discount    = $request->discount;
            $inspection_status->total       = $request->total;
            $inspection_status->total_nonvat   = $request->total_nonvat;
            $inspection_status->total_vat      = $request->total_vat;
            $inspection_status->total_nonvatcal   = $request->total_nonvatcal;
            $inspection_status->total_vatcal      = $request->total_vatcal;
            $inspection_status->discount_before_vat     = $request->discount_before_vat;
            $inspection_status->discount_after_vat      = $request->discount_after_vat;
            $inspection_status->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $inspection_status->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $inspection_status);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('System error please try again ' . $e->getMessage(), 404);
        }
    }
    public function Getpage_history_paid(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $columns = [
            'id',
            'date',
            'total',
            'status',
            'employee_id',
            'comments',
        ];

        $searchcolumn = [
            'date',
            'status',
        ];
        $query = Inspections::select($columns);

        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (!empty($search['value'])) {
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

                if ($item->status == 'overdue') {
                    $installment = Installment::where('inspection_id', $item->id)->first();

                    if ($installment) {
                        $item->arrears = $installment->after_price;
                        $item->paid = $installment->paid_price;
                    } else {
                        $item->arrears = '-';
                        $item->paid = '-';
                    }
                } else {
                    $item->arrears = '-';
                    $item->paid = $item->total;
                }
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function Getpage_history_bill(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $columns = [
            'id',
            'date',
            'total',
            'status',
            'employee_id',
            'comments',
        ];

        $searchcolumn = [
            'date',
            'status',
        ];

        $query = Inspections::select($columns);

        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $id = $request->input('id');
        if ($id) {
            $query->where('id', $id);
            $item = $query->first();
            if (!$item) {
                return response()->json(['status' => 'error', 'message' => 'ไม่มีบิลนี้ในระบบ'], 404);
            }

            if ($item->status == 'overdue') {
                $installment = Installment::where('inspection_id', $item->id)->first();

                if ($installment) {
                    $item->arrears = $installment->after_price;
                    $item->paid = $installment->paid_price;
                } else {
                    $item->arrears = '-';
                    $item->paid = '-';
                }
            } else {
                $item->arrears = '-';
                $item->paid = $item->total;
            }
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);

            foreach ($items as $item) {
                $No++;
                $item->No = $No;

                if ($item->status == 'overdue') {
                    $installment = Installment::where('inspection_id', $item->id)->first();

                    if ($installment) {
                        $item->arrears = $installment->after_price;
                        $item->paid = $installment->paid_price;
                    } else {
                        $item->arrears = '-';
                        $item->paid = '-';
                    }
                } else {
                    $item->arrears = '-';
                    $item->paid = $item->total;
                }
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function cal_tax_(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_inspection_types_id' => 'required|integer',
            'regeister_date' => 'required|date', //วันที่ลงทะเบียนเพื่อไว้ในการลดภาษี
            'tax_due_date' => 'required|date', //วันครบกำหนดชำระ
            'tax_renewal_date' => 'required|date', //วันที่ต่อภาษี วันปัจจุบัน
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        try {
            $weight = $request->weight;
            $cc = $request->cc;
            $regeister_date = $request->regeister_date;
            $date = $request->date;
            $registerdate = Carbon::parse($regeister_date);
            $currentdate = Carbon::parse($date);
            $year = $currentdate->diffInYears($registerdate);

            $tax_due_date = Carbon::parse($request->tax_due_date);
            $tax_renewal_date = Carbon::parse($request->tax_renewal_date);
            $months_diff = $tax_due_date->diffInMonths($tax_renewal_date);

            $vehicle_inspection_types_id = $request->vehicle_inspection_types_id; // ประเภทรถ ดึง id จาก VehicleInspectionTypes เพื่อเช็คว่า จะต้องคำนวณแบบไหน
            $fuel_type = $request->fuel_type; // ประเภทเครื่องยนต์ ev กับ hybrid
            $total = 0;
            $tax_fines = 0;

            switch ($vehicle_inspection_types_id) {
                case 1: // อัตราภาษีสำหรับรถนั่งส่วนบุคคลไม่เกิน 7 คน
                    $total = $this->calculateBlackPlateTax($cc, $year);
                    break;

                case 2: // อัตราภาษีสำหรับรถนั่งส่วนบุคคลเกิน 7 คน
                case 12:
                    $total = $this->calculateBluePlateTax($weight);
                    break;

                case 3: // ในส่วนของรถยนต์บรรทุกส่วนบุคคล
                case 11:
                    $total = $this->calculateGreenPlateTax($weight);
                    break;

                case 4: //รถมอไซ
                    $total = 100;
                    break;

                case 5: //รถแทรกเตอร์
                    $total = 50;
                    break;

                case 6: //รถโดยสารประจำทาง
                    $total = $this->calculateBusTax($weight);
                    break;

                case 7: //รถโดยสารไม่ประจำทาง
                case 9:
                    $total = $this->calculateNonBusTax($weight);
                    break;

                case 8: //รถโดยสารส่วนบุคคล
                case 10:
                    $total = $this->calculatePrivateCarTax($weight);
                    break;

                // case 'โดยสารขนาดเล็ก':
                //     $total = $this->calculateSmallPassengerTax($weight);
                //     break;

                default:
                    $total = 0;
                    break;
            }

            // ตรวจสอบนิติบุคคล
            if ($request->is_corporate == 1) {
                $total *= 2; // เสียภาษี 2 เท่า
            }
            if ($fuel_type === 'gas') {
                $total *= 0.5;
            }
            if ($fuel_type === 'Hybrid') {
                $total *= 0.75;
            }
            // คำนวณค่าปรับ
            if ($months_diff > 0) {
                $tax_fines = $this->cal_TaxFines($total, $months_diff);
            }

            $tax = [
                'total' => number_format($total, 2),
                'tax_fines' => number_format($tax_fines, 2),
            ];
            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $tax);
        } catch (\Throwable $e) {
            return $this->returnErrorData('System error please try again ' . $e->getMessage(), 404);
        }
    }

    public function cal_tax(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_inspection_types_id' => 'required',
            'regeister_date' => 'required|date', //วันที่ลงทะเบียนเพื่อไว้ในการลดภาษี
            'tax_due_date' => 'required|date', //วันครบกำหนดชำระ
            'tax_renewal_date' => 'required|date', //วันที่ต่อภาษี วันปัจจุบัน
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        try {
            $weight = $request->weight;
            $cc = $request->cc;
            $regeister_date = Carbon::parse($request->regeister_date);
            $tax_due_date = Carbon::parse($request->tax_due_date);
            $tax_renewal_date = Carbon::parse($request->tax_renewal_date);

            $vehicle_inspection_types_id = $request->vehicle_inspection_types_id; // ประเภทรถ ดึง id จาก VehicleInspectionTypes เพื่อเช็คว่า จะต้องคำนวณแบบไหน
            $fuel_type = $request->fuel_type; // ประเภทเครื่องยนต์ ev กับ hybrid
            $is_corporate = $request->is_corporate;

            $tax_details = [];

            if ($tax_renewal_date->lessThan($tax_due_date)) {
                $year = $tax_due_date->year + 1;
                $total = $this->calculateTax($vehicle_inspection_types_id, $cc, $weight, $year - $regeister_date->year);

                // ตรวจสอบนิติบุคคล
                if ($is_corporate == 1) {
                    $total *= 2;
                }
                if ($fuel_type === 'gas') {
                    $total *= 0.5;
                }
                if ($fuel_type === 'Hybrid') {
                    $total *= 0.75;
                }

                $tax_details[] = [
                    'year' => $year + 543,
                    'total' => number_format($total, 2),
                    'tax_fines' => number_format(0, 2), // กรณีต่อก่อน ไม่ต้องเสียค่าปรับ
                ];
            }

            while ($tax_due_date->lessThanOrEqualTo($tax_renewal_date)) {
                $year = $tax_due_date->year + 1;

                $total = $this->calculateTax($vehicle_inspection_types_id, $cc, $weight, $year - $regeister_date->year);

                // ตรวจสอบนิติบุคคล
                if ($is_corporate == 1) {
                    $total *= 2;
                }
                if ($fuel_type === 'gas') {
                    $total *= 0.5;
                }
                if ($fuel_type === 'Hybrid') {
                    $total *= 0.75;
                }

                // คำนวณค่าปรับ
                $tax_fines = 0;
                $months_diff = $tax_due_date->diffInMonths($tax_renewal_date);
                if ($months_diff > 0) {
                    $tax_fines = $this->cal_TaxFines($total, $months_diff);
                }

                $tax_details[] = [
                    'year' => $year + 543,
                    'total' => number_format($total, 2),
                    'tax_fines' => number_format($tax_fines, 2),
                ];

                // เลื่อนปีครบกำหนดภาษีไปปีถัดไป
                $tax_due_date->addYear();
            }

            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $tax_details);
        } catch (\Throwable $e) {
            return $this->returnErrorData('System error please try again ' . $e->getMessage(), 404);
        }
    }

    private function calculateTax($vehicle_inspection_types_id, $cc, $weight, $year_diff)
    {
        switch ($vehicle_inspection_types_id) {
            case 1:
                return $this->calculateBlackPlateTax($cc, $year_diff);
            case 2:
            case 12:
                return $this->calculateBluePlateTax($weight);
            case 3:
            case 11:
                return $this->calculateGreenPlateTax($weight);
            case 4:
                return 100;
            case 5:
                return 50;
            case 6:
                return $this->calculateBusTax($weight);
            case 7:
            case 9:
                return $this->calculateNonBusTax($weight);
            case 8:
            case 10:
                return $this->calculatePrivateCarTax($weight);
            default:
                return 0;
        }
    }

    private function cal_TaxFines($total, $months_diff)
    {
        $fines = 0;
        $base_fine_rate = 0.01;
        for ($i = 0; $i <= $months_diff; $i++) {
            $year_factor = ($i >= 12) ? 1 + intdiv($i - 1, 12) : 1;
            $fines += $total * $base_fine_rate * $year_factor;
        }
        return $fines;
    }

    private function calculateBlackPlateTax($cc, $year)
    {
        $total = 0;
        if ($cc > 1800) {
            $total += (600 * 0.5) + (1200 * 1.5) + (($cc - 1800) * 4);
        } elseif ($cc > 600) {
            $total += (600 * 0.5) + (($cc - 600) * 1.5);
        } else {
            $total += $cc * 0.5;
        }

        if ($year >= 6 && $year <= 10) {
            $discount_percen = ($year - 5) * 10;
            $total -= ($total * ($discount_percen / 100));
        } elseif ($year > 10) {
            $total -= ($total * 0.5); // เกิน 10 ปีลด 50%
        }

        return $total;
    }

    private function calculateGreenPlateTax($weight)
    {
        if ($weight <= 500) {
            return 300;
        } elseif ($weight <= 750) {
            return 450;
        } elseif ($weight <= 1000) {
            return 600;
        } elseif ($weight <= 1250) {
            return 750;
        } elseif ($weight <= 1500) {
            return 900;
        } elseif ($weight <= 1750) {
            return 1050;
        } elseif ($weight <= 2000) {
            return 1350;
        } elseif ($weight <= 2500) {
            return 1650;
        } elseif ($weight <= 3000) {
            return 1950;
        } elseif ($weight <= 3500) {
            return 2250;
        } elseif ($weight <= 4000) {
            return 2550;
        } elseif ($weight <= 4500) {
            return 2850;
        } elseif ($weight <= 5000) {
            return 3150;
        } elseif ($weight <= 6000) {
            return 3450;
        } elseif ($weight <= 7000) {
            return 3750;
        } else {
            return 4050;
        }
    }

    private function calculateBluePlateTax($weight)
    {
        if ($weight <= 500) {
            return 150;
        } elseif ($weight <= 750) {
            return 300;
        } elseif ($weight <= 1000) {
            return 450;
        } elseif ($weight <= 1250) {
            return 800;
        } elseif ($weight <= 1500) {
            return 1000;
        } elseif ($weight <= 1750) {
            return 1300;
        } elseif ($weight <= 2000) {
            return 1600;
        } elseif ($weight <= 2500) {
            return 1900;
        } elseif ($weight <= 3000) {
            return 2200;
        } elseif ($weight <= 3500) {
            return 2400;
        } elseif ($weight <= 4000) {
            return 2600;
        } elseif ($weight <= 4500) {
            return 2800;
        } elseif ($weight <= 5000) {
            return 3000;
        } elseif ($weight <= 6000) {
            return 3200;
        } elseif ($weight <= 7000) {
            return 3400;
        } else {
            return 3600;
        }
    }

    private function calculateElectricCarTax($weight)
    {
        if ($weight <= 500) {
            return 150;
        } elseif ($weight <= 750) {
            return 300;
        } elseif ($weight <= 1000) {
            return 450;
        } elseif ($weight <= 1250) {
            return 800;
        } elseif ($weight <= 1500) {
            return 1000;
        } elseif ($weight <= 1750) {
            return 1300;
        } elseif ($weight <= 2000) {
            return 1600;
        } elseif ($weight <= 2500) {
            return 1900;
        } elseif ($weight <= 3000) {
            return 2200;
        } elseif ($weight <= 3500) {
            return 2400;
        } elseif ($weight <= 4000) {
            return 2600;
        } elseif ($weight <= 4500) {
            return 2800;
        } elseif ($weight <= 5000) {
            return 3000;
        } elseif ($weight <= 6000) {
            return 3200;
        } elseif ($weight <= 7000) {
            return 3400;
        } else {
            return 3600;
        }
    }

    private function calculateElectricCar2Tax($weight)
    {
        if ($weight <= 500) {
            return 75;
        } elseif ($weight <= 750) {
            return 150;
        } elseif ($weight <= 1000) {
            return 225;
        } elseif ($weight <= 1250) {
            return 400;
        } elseif ($weight <= 1500) {
            return 500;
        } elseif ($weight <= 1750) {
            return 650;
        } elseif ($weight <= 2000) {
            return 800;
        } elseif ($weight <= 2500) {
            return 950;
        } elseif ($weight <= 3000) {
            return 1100;
        } elseif ($weight <= 3500) {
            return 1200;
        } elseif ($weight <= 4000) {
            return 1300;
        } elseif ($weight <= 4500) {
            return 1400;
        } elseif ($weight <= 5000) {
            return 1500;
        } elseif ($weight <= 6000) {
            return 1600;
        } elseif ($weight <= 7000) {
            return 1700;
        } else {
            return 1800;
        }
    }

    private function calculateBusTax($weight)
    {
        if ($weight <= 500) {
            return 300;
        } elseif ($weight <= 750) {
            return 400;
        } elseif ($weight <= 1000) {
            return 500;
        } elseif ($weight <= 1250) {
            return 600;
        } elseif ($weight <= 1500) {
            return 700;
        } elseif ($weight <= 1750) {
            return 900;
        } elseif ($weight <= 2000) {
            return 1100;
        } elseif ($weight <= 2500) {
            return 1300;
        } elseif ($weight <= 3000) {
            return 1500;
        } elseif ($weight <= 3500) {
            return 1700;
        } elseif ($weight <= 4000) {
            return 1900;
        } elseif ($weight <= 4500) {
            return 2100;
        } elseif ($weight <= 5000) {
            return 2300;
        } elseif ($weight <= 6000) {
            return 2500;
        } elseif ($weight <= 7000) {
            return 2700;
        } else {
            return 2900;
        }
    }

    private function calculateNonBusTax($weight)
    {
        if ($weight <= 500) {
            return 450;
        } elseif ($weight <= 750) {
            return 600;
        } elseif ($weight <= 1000) {
            return 750;
        } elseif ($weight <= 1250) {
            return 900;
        } elseif ($weight <= 1500) {
            return 1050;
        } elseif ($weight <= 1750) {
            return 1350;
        } elseif ($weight <= 2000) {
            return 1650;
        } elseif ($weight <= 2500) {
            return 1950;
        } elseif ($weight <= 3000) {
            return 2250;
        } elseif ($weight <= 3500) {
            return 2540;
        } elseif ($weight <= 4000) {
            return 2850;
        } elseif ($weight <= 4500) {
            return 3150;
        } elseif ($weight <= 5000) {
            return 3450;
        } elseif ($weight <= 6000) {
            return 3750;
        } elseif ($weight <= 7000) {
            return 4050;
        } else {
            return 4350;
        }
    }

    private function calculateSmallPassengerTax($weight)
    {
        if ($weight <= 500) {
            return 300;
        } elseif ($weight <= 750) {
            return 400;
        } elseif ($weight <= 1000) {
            return 500;
        } elseif ($weight <= 1250) {
            return 600;
        } elseif ($weight <= 1500) {
            return 700;
        } elseif ($weight <= 1750) {
            return 900;
        } elseif ($weight <= 2000) {
            return 1100;
        } elseif ($weight <= 2500) {
            return 1300;
        } elseif ($weight <= 3000) {
            return 1500;
        } else {
            return 0;
        }
    }

    private function calculatePrivateCarTax($weight)
    {
        if ($weight <= 500) {
            return 150;
        } elseif ($weight <= 750) {
            return 300;
        } elseif ($weight <= 1000) {
            return 450;
        } elseif ($weight <= 1250) {
            return 800;
        } elseif ($weight <= 1500) {
            return 1000;
        } elseif ($weight <= 1750) {
            return 1300;
        } elseif ($weight <= 2000) {
            return 1600;
        } elseif ($weight <= 2500) {
            return 1900;
        } elseif ($weight <= 3000) {
            return 2200;
        } elseif ($weight <= 3500) {
            return 2400;
        } elseif ($weight <= 4000) {
            return 2600;
        } elseif ($weight <= 4500) {
            return 2800;
        } elseif ($weight <= 5000) {
            return 3000;
        } elseif ($weight <= 6000) {
            return 3200;
        } elseif ($weight <= 7000) {
            return 3400;
        } else {
            return 3600;
        }
    }

    public function update_vehicles_status(request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $Item = Inspections::find($id);

            if (!$Item) {
                throw new \Exception('ไม่พบข้อมูลรายการ');
            }

            $Item->tax_vehicle = $request->input('tax_vehicle');

            $Item->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $Item->operation;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function update_vehicles_type_document(request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $Item = Inspections::where('id',$id)->with('InspectionVehicles')->first();

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
            }

            if (isset($Item->InspectionVehicles[0])) {
                $Item->InspectionVehicles[0]->type_document = $request->input('type_document');
                $Item->InspectionVehicles[0]->save();
            }

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $Item->operation;
            $this->Log($userId, $description, $type);
            DB::commit();
            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }
    public function update_vehicles_status_array(request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            foreach ($request->ids as $id) {
                $Item = Inspections::find($id);

                if (!$Item) {
                    return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
                }

                $Item->tax_vehicle = $request->input('tax_vehicle');

                $Item->save();

                $userId = $loginBy->id;
                $type = 'แก้ไขข้อมูล';
                $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $Item->operation;
                $this->Log($userId, $description, $type);
            }

            DB::commit();
            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function update_vehicles_paytrue(request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $Item = Inspections::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
            }

            $Item->paytrue = $request->input('paytrue');
            $Item->commission = $request->input('commission');
            $Item->result = $request->input('result');

            $taxprices = $Item->InspectionVehicles()->with('vehicleServiceTransaction')->get();

            $servicePrice = null;
            foreach ($taxprices as $taxprice) {
                foreach ($taxprice->vehicleServiceTransaction as $transaction) {
                    if ($transaction->service_id == 3) {
                        $servicePrice = $transaction->service_price;
                        break 2; // หยุดการค้นหาเมื่อเจอ service_id = 3
                    }
                }
            }
            if ($servicePrice === null) {
                return $this->returnErrorData('ไม่พบข้อมูล service_id = 3', 404);
            }

            if ($servicePrice == $Item->paytrue) {
                $Item->transaction_status = null; // จ่ายเท่ากัน
            } elseif ($servicePrice < $Item->paytrue) {
                $Item->transaction_status = 'adddebt'; // จ่ายเกิน เพิ่มหนี้ เก็บเพิ่ม
            } else {
                $Item->transaction_status = 'reducedebt'; // จ่ายขาด ลดหนี้ คืนเงิน
            }

            $Item->save();
            DB::commit();

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $Item->operation;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function update_gas(request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $Item = Inspections::where('id', $id)
                ->with('Employees')
                ->with('ServiceTransactions')
                ->with('Vehicles')
                ->with('InstallNo')
                ->first();
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
            }
            $Item->engineer_id = $request->engineer_id;
            $Item->service_or = $request->service_or;
            $Item->CHA_NO = $request->CHA_NO;
            if ($Item->photo_vehicle_gas) {
                Storage::delete($Item->photo_vehicle_gas);
            }
            $path = $this->saveImage_gas($request, 'photo', $id);
            $Item->photo_vehicle_gas = $path;
            $Item->save();

            $install_no = InstallNo::where('inspection_id',$id)->delete();
            foreach($request->install_no as $install){
                $install_no = new InstallNo();
                $install_no->inspection_id = $id;
                $install_no->install_no = $install;
                $install_no->save();
            }

            // $customer_id = $Item->customer_id;
            // $engineer_id = $Item->engineer_id;

            // foreach ($Item->serviceTransactions as $service_transaction) {  //ส่วนเอกสาร
            //     if ($service_transaction->status != 1) {
            //         continue;
            //     }

            //     $document_type_id = $this->getDocumentTypeId(
            //         $service_transaction->service_id,
            //         $Item->Vehicles->VehicleInspectionTypes->type,
            //         $request->weight
            //     );

            //     if (!$document_type_id) {
            //         continue;
            //     }

            //     $error = $this->processDocumentTransaction(
            //         $document_type_id,
            //         $engineer_id,
            //         $customer_id,
            //         $Item,
            //         $request
            //     );

            //     if ($error) {
            //         return $this->returnErrorData($error, 404);
            //     }
            // }

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $Item->operation;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    private function getDocumentTypeId($service_id, $vehicle_type, $weight)
    {
        $documentTypeMap = [
            8 => [
                "รย." => 1,
                "ขส." => $weight > 2200 ? 3 : 2,
            ],
            9 => [
                "รย." => 6,
                "ขส." => 7,
            ],
            10 => [
                "รย." => 4,
                "ขส." => 5,
            ],
            11 => [
                "รย." => 10,
                "ขส." => $weight > 2200 ? 11 : 12,
            ],
            12 => [
                "รย." => 8,
                "ขส." => 9,
            ],
        ];

        return $documentTypeMap[$service_id][$vehicle_type] ?? null;
    }

    private function processDocumentTransaction($document_type_id, $engineer_id, $customer_id, $Item, $request)
    {

        if (in_array($document_type_id, [6, 7, 8, 9])) {
            $doc = Doc::where('document_type_id', $document_type_id)->first();
        } else {
            $doc = Doc::where('document_type_id', $document_type_id)
                ->where('engineer_id', $engineer_id)
                ->first();
        }

        if (!$doc) {
            return 'ไม่พบเอกสารที่ต้องการ';
        }

        $existingTransaction = Doc_transaction::where('inspection_id', $Item->id)
            ->whereHas('docCustomers', function ($query) use ($doc, $customer_id) {
                $query->where('docs_id', $doc->id)
                    ->where('customer_id', $customer_id);
            })
            ->first();

        if ($existingTransaction) {
            return null;
        }

        $docCustomer = DocCustomer::where('customer_id', $customer_id)
            ->where('docs_id', $doc->id)
            ->first();

        if (!$docCustomer) {
            return 'ไม่พบข้อมูลลูกค้าเอกสาร';
        }

        if ($docCustomer->total_qty < 1) {
            return 'เอกสารไม่เพียงพอสำหรับการเบิก';
        }

        $newBalance = $docCustomer->total_qty - 1;

        $transaction = new Doc_transaction();
        $transaction->doc_customers_id = $docCustomer->id;
        $transaction->inspection_id = $Item->id;
        $transaction->date = $request->date;
        $transaction->transaction_type = 'Withdraw';
        $transaction->stock = $docCustomer->total_qty;
        $transaction->quantity = 1;
        $transaction->balance = $newBalance;
        $transaction->remark = $request->remark;
        $transaction->save();

        $docCustomer->total_qty = $newBalance;

        return null;
    }

    public function gas_cal(request $request)
    {
        $vehicleinspectiontypes = VehicleInspectionTypes::find($request->vehicleinspectiontypes);
        if (!$vehicleinspectiontypes) {
            return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
        }
        $weight = $request->weight;
        $item = new \stdClass();
        if ($vehicleinspectiontypes->type == 'รย.') {
            $item->service_8 = 500;
            $item->service_9 = 500;
            $item->service_11 = 500;
            $item->service_12 = 1000;
        } elseif ($vehicleinspectiontypes->type == 'ขส.') {
            if ($weight <= 2200) {
                $item->service_8 = 500;
                $item->service_9 = 1000;
                $item->service_11 = 500;
                $item->service_12 = 1500;
            } else {
                $item->service_8 = 1000;
                $item->service_9 = 1500;
                $item->service_11 = 1000;
                $item->service_12 = 1500;
            }
        }
        return $this->returnSuccess('Success', $item);
    }
    public function gas_cal_(Request $request)
    {
        $vehicleinspectiontypes = VehicleInspectionTypes::find($request->vehicleinspectiontypes);

        if (!$vehicleinspectiontypes) {
            return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
        }
        $id = $vehicleinspectiontypes->id;
        $weight = $request->weight;
        // return $id;

        $item = new \stdClass();
        // ฟังก์ชันสำหรับดึงราคา โดยเช็คใน settings ก่อน
        $getPrice = function ($serviceId) use ($vehicleinspectiontypes) {
            $setting = Setting::where('service_id', $serviceId)
                            ->whereNULL('weight')
                            ->first();
            return $setting ? $setting->price : (Services::find($serviceId)->price ?? 0);
        };
        $getPrice_code = function ($serviceId,$id) use ($vehicleinspectiontypes) {
            $setting = Setting::where('service_id', $serviceId)
                            ->when($id, function ($query, $id) {
                                return $query->where('vehicle_inspection_type_id', $id);
                            })
                            ->whereNULL('weight')
                            ->first();
            return $setting ? $setting->price : (Services::find($serviceId)->price ?? 0);
        };

        // ฟังก์ชันสำหรับดึงราคาโดยเช็ค weight
        $getPriceWithWeight = function ($serviceId) use ($vehicleinspectiontypes, $weight) {
            $setting = Setting::where('service_id', $serviceId)
                            ->where('weight', '>=', $weight)
                            ->orderBy('weight', 'asc')
                            ->first();
            return $setting ? $setting->price : (Services::find($serviceId)->price ?? 0);
        };
        // return $vehicleinspectiontypes;
        if ($vehicleinspectiontypes->type == 'รย.' && $id) {
            $item->service_8 = $getPrice_code(8, $id);
            $item->service_9 = $getPrice_code(9, $id);
            $item->service_11 = $getPrice_code(11, $id);
            $item->service_12 = $getPrice_code(12, $id);
        } elseif ($vehicleinspectiontypes->type == 'ขส.') {
            if ($weight <= 2200) {
                $item->service_8 = $getPriceWithWeight(8);
                $item->service_9 = $getPriceWithWeight(9);
                $item->service_11 = $getPriceWithWeight(11);
                $item->service_12 = $getPriceWithWeight(12);
            } else {
                $item->service_8 = $getPriceWithWeight(8);
                $item->service_9 = $getPriceWithWeight(9);
                $item->service_11 = $getPriceWithWeight(11);
                $item->service_12 = $getPriceWithWeight(12);
            }
        }

        return $this->returnSuccess('Success', $item);
    }

    public function updateInspection_Result(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            // ใช้ findOrFail เพื่อให้แน่ใจว่า ID มีอยู่
            $inspection_result = Inspections::findOrFail($id);

            if (!$inspection_result) {
                throw new \Exception('ไม่พบข้อมูลรายการ');
            }

            // ตั้งค่าสถานะใหม่
            $inspection_result->result = $request->result;
            $inspection_result->is_pass = $request->is_pass;
            $inspection_result->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $inspection_result->result;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $inspection_result);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function checkcar_cal_(request $request)
    {
        $vehicleinspectiontypes = VehicleInspectionTypes::find($request->vehicleinspectiontypes);
        if (!$vehicleinspectiontypes) {
            return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
        }
        $weight = $request->weight;
        $item = new \stdClass();
        if ($vehicleinspectiontypes->id == 4) {
            $item->price = 60;
        } else {
            if ($weight <= 2000) {
                $item->price = 200;
            } else {
                $item->price = 300;
            }
        }
        return $this->returnSuccess('Success', $item);
    }
    public function checkcar_cal(request $request)
    {
        $vehicleinspectiontypes = VehicleInspectionTypes::find($request->vehicleinspectiontypes);
        if (!$vehicleinspectiontypes) {
            return $this->returnErrorData('ไม่พบข้อมูลประเภทรถ', 404);
        }
        $vehicleinspectiontypes = $request->vehicleinspectiontypes;
        $weight = $request->weight;
        $item = new \stdClass();
        // dd($vehicleinspectiontypes);
        $price = Setting::when($vehicleinspectiontypes, function ($query, $vehicleinspectiontypes){
                return $query->where('vehicle_inspection_type_id', $vehicleinspectiontypes);
            })
            ->when($weight, function ($query, $weight){
                return $query->where('weight', '>=', $weight);
            })
            ->where('service_id', 1)
            ->first();
        if(!$price){
            $price = Services::where('id',1)->first();
        }
        $item->price = $price->price;
        return $this->returnSuccess('Success', $item);
        // return $this->returnSuccess('Success', $item);
    }
    public function setting_cal(request $request)
    {
        $vehicleinspectiontypes = VehicleInspectionTypes::find($request->vehicleinspectiontypes);
        if (!$vehicleinspectiontypes) {
            return $this->returnErrorData('ไม่พบข้อมูลประเภทรถ', 404);
        }
        $vehicleinspectiontypes = $request->vehicleinspectiontypes;
        $weight = $request->weight;
        $service_id = $request->service_id;
        $type = $request->type;
        $item = new \stdClass();

        $price = Setting::when($vehicleinspectiontypes, function ($query, $vehicleinspectiontypes){
                return $query->where('vehicle_inspection_type_id', $vehicleinspectiontypes);
            })
            ->when($weight, function ($query, $weight){
                return $query->where('weight', '>=', $weight);
            })
            ->when($service_id, function ($query, $service_id){
                return $query->where('service_id', $service_id);
            })
            ->when($type, function ($query, $type){
                return $query->where('type', $type);
            })
            ->with('service')
            ->first();

        if(!$price && !$service_id){
            $price = Services::where('id',$service_id)->first();
        }
        if($price){
            $item->price = $price->price;
        }

        return $this->returnSuccess('Success', $item);
        // return $this->returnSuccess('Success', $item);
    }

    public function getPage_report(Request $request)
    {
        $perPage = $request->input('length', 10);
        $page = $request->input('start', 0);
        $search = $request->input('search.value', "");
        $order = $request->input('order', []);
        $columns = $request->input('columns', []);

        $status = $request->status;
        $date_start = $request->date_start;
        $date_end = $request->date_end;

        $searchable = [
            'inspections.no',
            'vehicles.license_plate',
            'customers.name'
        ];

        $querys = DB::table('inspection_vehicles')
            ->join('inspections', 'inspection_vehicles.inspection_id', '=', 'inspections.id')
            ->join('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', function ($join) {
                $join->on('vechicle_services_transactions.inspection_vehicle_id', '=', 'inspection_vehicles.id')
                    ->whereIn('vechicle_services_transactions.service_id', [3, 4]);
            })
            ->wherenotnull('inspections.transaction_status')
            ->select(
                'inspections.id',
                'inspections.paid_type_debt',
                'inspections.paid_date',
                'inspections.no as bill_number',
                'inspections.date',
                'vehicles.license_plate',
                'vehicles.registration_date',
                DB::raw("TRIM(CONCAT(COALESCE(customers.name,''), ' ', COALESCE(customers.lname,''))) as customer_name"),
                'inspections.transaction_status',
                DB::raw('SUM(vechicle_services_transactions.service_price) as service_price'),
                'inspections.paytrue',
                DB::raw("
                    CASE
                        WHEN inspections.transaction_status = 'reducedebt'
                            THEN CONCAT(
                                'ลด ',
                                FORMAT(ABS(inspections.paytrue - SUM(vechicle_services_transactions.service_price)), 0),
                                ' บาท'
                            )

                        WHEN (inspections.paytrue - SUM(vechicle_services_transactions.service_price)) < 0
                            THEN CONCAT(
                                'เพิ่ม ',
                                FORMAT(ABS(inspections.paytrue - SUM(vechicle_services_transactions.service_price)), 0),
                                ' บาท'
                            )

                        ELSE CONCAT(
                            'คืน ',
                            FORMAT(inspections.paytrue - SUM(vechicle_services_transactions.service_price), 0),
                            ' บาท'
                        )
                    END as payextra
                ")
            )
            ->groupBy(
                'inspections.id',
                'inspections.paid_type_debt',
                'inspections.paid_date',
                'inspections.no',
                'inspections.date',
                'vehicles.license_plate',
                'vehicles.registration_date',
                'customers.name',
                'customers.lname',
                'inspections.transaction_status',
                'inspections.paytrue'
            );

        // Updated the whereRaw to use the sum of service prices
        $querys->havingRaw('inspections.paytrue - SUM(vechicle_services_transactions.service_price) != 0');

        if (!empty($status)) {
            $querys->where('inspections.transaction_status', $status);
        }

        if (!empty($date_start) && !empty($date_end)) {
            $querys->whereBetween('inspections.date', [$date_start, $date_end]);
        }

        if (!empty($search)) {
            $querys->where(function ($q) use ($search, $searchable) {
                foreach ($searchable as $field) {
                    $q->orWhere($field, 'LIKE', '%' . $search . '%');
                }
            });
        }

        $query = $querys
            ->orderBy($columns[$order[0]['column']]['data'], $order[0]['dir'])
            ->paginate($perPage, ['*'], 'page', ($page / $perPage) + 1);

        return $this->returnSuccess('Success' ,$query);
    }
    public function Update_status(Request $request)
    {
        $loginBy = Auth()->user();
        if (!$loginBy) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $id = $request->id;
        $transactionStatus = $request->transaction_status;
        $paid_type_debt = $request->paid_type_debt;
        $paid_date = $request->paid_date;

        if (!$id || !$transactionStatus) {
            return response()->json(['message' => 'Both id and transaction status are required'], 400);
        }
        DB::beginTransaction();

        try {
            $inspection = DB::table('inspections')->where('id', $id)->first();

            if (!$inspection) {
                return response()->json(['message' => 'Inspection not found'], 404);
            }
            $validStatuses = ['adddebt', 'reducedebt', 'closedebt'];
            if (!in_array($transactionStatus, $validStatuses)) {
                return response()->json(['message' => 'Invalid transaction status'], 400);
            }
            DB::table('inspections')
                ->where('id', $id)
                ->update([
                    'transaction_status' => $transactionStatus,
                    'paid_type_debt' => $paid_type_debt,
                    'paid_date' => $paid_date
                ]);

            $updatedInspection = DB::table('inspections')->where('id', $id)->first();
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูลสถานะหนี้';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $transactionStatus;
            $this->Log($userId, $description, $type);
            DB::commit();
            return $this->returnSuccess('Success', $updatedInspection);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function update_gas2(request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $check_gas = CheckGas::with('inspectionVehicle.vehicle.VehicleInspectionTypes')
                ->with('inspectionVehicle.vehicleServiceTransaction')
                ->with('inspectionVehicle.inspections')
                ->where('id',$id)
                ->first();

            if (!$check_gas) {
                return $this->returnErrorData('ไม่พบข้อมูลแก๊ส', 404);
            }
            $engineer = Employees::where('id', $request->engineer_id)->first();
            // $check_gas = new CheckGas();

            $check_gas->engineer_id = $engineer->id;
            $check_gas->engineer_no = $engineer->code;
            $check_gas->engineer_name = $engineer->first_name.' '.$engineer->last_name;
            $check_gas->install_no = $request->install_no;
            $check_gas->service_or = $request->service_or;
            $check_gas->date = $request->date;
            $timeString = str_pad($request->time, 4, '0', STR_PAD_RIGHT);
            $check_gas->time = Carbon::createFromFormat('Hi', $timeString)->format('H:i:s');
            $check_gas->no_gas = $request->no_gas;
            $check_gas->brand_gas = $request->brand_gas;

            if ($check_gas->photo) {
                Storage::delete($check_gas->photo_vehicle_gas);
            }
            $path = $this->saveImage_gas($request, 'photo', $id);
            $check_gas->photo_vehicle_gas = $path;
            $check_gas->save();

            //========== ตัดเอกสาร ==============
            foreach ($check_gas->inspectionVehicle->vehicleServiceTransaction as $service_transaction) {
                if (in_array($service_transaction->service_id, [8, 9, 10])) {
                    $type_gas = 'LPG';
                } else {
                    $type_gas = 'NGV';
                }

                if(in_array($service_transaction->service_id, [9, 12]))
                {
                    if($check_gas->is_stock_install == 0){
                        $typeVehicle = null;
                        if ($check_gas &&
                            $check_gas->inspectionVehicle &&
                            $check_gas->inspectionVehicle->vehicle &&
                            $check_gas->inspectionVehicle->vehicle->VehicleInspectionTypes) {
                            $typeVehicle = $check_gas->inspectionVehicle->vehicle->VehicleInspectionTypes->type;
                        }

                        $doc = doc::where('type_doc', 'gas')
                            ->where('engineer_id', $check_gas->engineer_id)
                            ->where('type_job', 'install')
                            ->where('type_vehicle', $typeVehicle)   // รย. หรือ ขส.
                            ->where('type_gas', $type_gas)  // แก๊ส LPG หรือ NGV
                            ->first();
                        if ($doc) {
                            $inspection = $check_gas->inspectionVehicle->inspections;
                            $docId = $doc->id;
                            // $doc_stock = Doc_transaction::where('doc_id', $doc->id)
                            //     ->where('branch_id', $inspection->branch_id)
                            //     ->latest('created_at')->first();
                            // if($doc_stock != null){
                            //     $stock = $doc_stock->balance;
                            // }
                            $stock = Doc_transaction::where('branch_id', $inspection->branch_id)
                                ->where(function($query) {
                                    $query->where('deleted_at', null);
                                })
                                ->where(function($query) use ($docId) {
                                    $query->where('doc_id', $docId);
                                })
                                ->selectRaw('
                                    SUM(CASE
                                        WHEN transaction_type = "Deposit" THEN quantity
                                        WHEN transaction_type = "Withdraw" THEN -quantity
                                        ELSE 0
                                    END) as total_stock
                                ')
                                ->value('total_stock');
                            if($stock == null){
                                $stock = 0;
                            }
                            $docTransaction = new Doc_transaction();
                            $docTransaction->doc_id = $doc->id;
                            $docTransaction->branch_id = $inspection->branch_id;
                            $docTransaction->inspection_id = $inspection->id;
                            $docTransaction->date = $request->date;
                            $docTransaction->transaction_type = 'Withdraw';
                            $docTransaction->stock = $stock;
                            $docTransaction->quantity = 1;
                            $docTransaction->balance = $stock - 1;
                            $docTransaction->remark = 'ตัดเอกสารแก๊ส ติดตั้ง';
                            $docTransaction->save();

                            $check_gas->is_stock_install = 1;
                            $check_gas->save();
                        }
                    }
                }elseif(in_array($service_transaction->service_id, [8, 11]))
                {
                    if($check_gas->is_stock_service == 0){
                        $typeVehicle = null;
                        if ($check_gas &&
                            $check_gas->inspectionVehicle &&
                            $check_gas->inspectionVehicle->vehicle &&
                            $check_gas->inspectionVehicle->vehicle->VehicleInspectionTypes) {
                            $typeVehicle = $check_gas->inspectionVehicle->vehicle->VehicleInspectionTypes->type;
                        }
                        $doc = doc::where('type_doc', 'gas')
                            ->where('engineer_id', $check_gas->engineer_id)
                            ->where('type_job', 'check')
                            ->where('type_vehicle', $typeVehicle)   // รย. หรือ ขส.
                            ->where('type_gas', $type_gas)  // แก๊ส LPG หรือ NGV
                            ->first();
                        if ($doc) {
                            $inspection = $check_gas->inspectionVehicle->inspections;
                            $docId = $doc->id;

                            // $doc_stock = Doc_transaction::where('doc_id', $doc->id)
                            //     ->where('branch_id', $inspection->branch_id)
                            //     ->latest('created_at')->first();;
                            // if($doc_stock != null){
                            //     $stock = $doc_stock->balance;
                            // }

                            $stock = Doc_transaction::where('branch_id', $inspection->branch_id)
                                ->where(function($query) {
                                    $query->where('deleted_at', null);
                                })
                                ->where(function($query) use ($docId) {
                                    $query->where('doc_id', $docId);
                                })
                                ->selectRaw('
                                    SUM(CASE
                                        WHEN transaction_type = "Deposit" THEN quantity
                                        WHEN transaction_type = "Withdraw" THEN -quantity
                                        ELSE 0
                                    END) as total_stock
                                ')
                                ->value('total_stock');
                            if($stock == null){
                                $stock = 0;
                            }
                            $docTransaction = new Doc_transaction();
                            $docTransaction->doc_id = $doc->id;
                            $docTransaction->branch_id = $inspection->branch_id;
                            $docTransaction->inspection_id = $inspection->id;
                            $docTransaction->date = $request->date;
                            $docTransaction->transaction_type = 'Withdraw';
                            $docTransaction->stock = $stock;
                            $docTransaction->quantity = 1;
                            $docTransaction->balance = $stock - 1;
                            $docTransaction->remark = 'ตัดเอกสารแก๊ส ตรวจ';
                            $docTransaction->save();

                            $check_gas->is_stock_service = 1;
                            $check_gas->save();
                        }
                    }
                }
            }

            if($request->cha_no){
                $qty = 0;
                ChaNo::where('check_gas_id',$id)->delete();
                foreach($request->cha_no as $Cha_no){
                    $qty += 1;
                    $ChaNo = new ChaNo();
                    $ChaNo->check_gas_id = $check_gas->id;
                    $ChaNo->cha_no = $Cha_no['cha_no'];
                    $ChaNo->vehicle_gas_id = $Cha_no['vehicle_gas_id'];
                    $ChaNo->save();
                }

                // ========== ตัดเอกสาร ==============
                $inspection = $check_gas->inspectionVehicle->inspections;
                $typeVehicle = null;
                if ($check_gas &&
                    $check_gas->inspectionVehicle &&
                    $check_gas->inspectionVehicle->vehicle &&
                    $check_gas->inspectionVehicle->vehicle->VehicleInspectionTypes) {
                    $typeVehicle = $check_gas->inspectionVehicle->vehicle->VehicleInspectionTypes->type;
                }
                $doc = doc::where('type_doc', 'gas')
                    ->where('engineer_id', $check_gas->engineer_id)
                    ->where('type_job', 'renew')
                    ->where('type_vehicle', $typeVehicle)   // รย. หรือ ขส.
                    ->where('type_gas', 'LPG')  // แก๊ส LPG หรือ NGV
                    ->first();

                Doc_transaction::where('doc_id',$doc->id )
                    ->where('branch_id', $inspection->branch_id)
                    ->where('inspection_id', $inspection->id)
                    ->delete();
                if ($doc) {
                    $docId = $doc->id;
                    $stock = 0;
                    // $doc_stock = Doc_transaction::where('doc_id', $doc->id)
                    //     ->where('branch_id', $inspection->branch_id)
                    //     ->latest('created_at')->first();
                    // if($doc_stock != null){
                    //     $stock = $doc_stock->balance;
                    // }
                    $stock = Doc_transaction::where('branch_id', $inspection->branch_id)
                        ->where(function($query) {
                            $query->where('deleted_at', null);
                        })
                        ->where(function($query) use ($docId) {
                            $query->where('doc_id', $docId);
                        })
                        ->selectRaw('
                            SUM(CASE
                                WHEN transaction_type = "Deposit" THEN quantity
                                WHEN transaction_type = "Withdraw" THEN -quantity
                                ELSE 0
                            END) as total_stock
                        ')
                        ->value('total_stock');
                    if($stock == null){
                        $stock = 0;
                    }
                    $docTransaction = new Doc_transaction();
                    $docTransaction->doc_id = $doc->id;
                    $docTransaction->branch_id = $inspection->branch_id;
                    $docTransaction->inspection_id = $inspection->id;
                    $docTransaction->date = $request->date;
                    $docTransaction->transaction_type = 'Withdraw';
                    $docTransaction->stock = $stock;
                    $docTransaction->quantity = $qty;
                    $docTransaction->balance = $stock - $qty;
                    $docTransaction->remark = 'ตัดเอกสารแก๊ส ต่อ';
                    $docTransaction->save();
                }

            }

            $userId = $loginBy->id;
            $type = 'อัปเดตข้อมูลแก็ส';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id ;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $check_gas);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }
    public function addgas_agent_(request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $InspectionVehicles = new InspectionVehicles();

            $car = Vehicles::where('license_plate', $request->license_plate)->first();
            if (!$car) {
                return $this->returnErrorData('ไม่พบข้อมูลรถ', 404);
            }
            $InspectionVehicles->vehicle_id = $car->id;
            $province = Provinces::where('id', $request->province_id)->first();
            $InspectionVehicles->provinces = $province->name_th;
            $customer = Customers::where('id', $request->customer_id)->first();
            if (!$customer) {
                return $this->returnErrorData('ไม่พบข้อมูลลูกค้า', 404);
            }
            $InspectionVehicles->no = $customer->no;
            $InspectionVehicles->name = $customer->name;
            $InspectionVehicles->license_plate = $car->license_plate;
            // return $customer;
            $engineer = Employees::where('id', $request->engineer_id)->first();
            if (!$customer) {
                return $this->returnErrorData('ไม่พบข้อมูลวิศวกร', 404);
            }
            $InspectionVehicles->save();

            $check_gas = new CheckGas();
            $check_gas->inspection_vehicle_id = $InspectionVehicles->id;
            $check_gas->customer_id = $customer->id;
            $check_gas->customer_no = $customer->no;
            $check_gas->customer_name = $customer->name;

            $check_gas->engineer_id = $engineer->id;
            $check_gas->engineer_no = $engineer->code;
            $check_gas->engineer_name = $engineer->first_name.' '.$engineer->last_name;
            $check_gas->install_no = $request->install_no;
            $check_gas->service_or = $request->service_or;
            $check_gas->date = $request->date;
            $check_gas->time = $request->time;
            if ($check_gas->photo) {
                Storage::delete($check_gas->photo_vehicle_gas);
            }
            $path = $this->saveImage_gas($request, 'photo', $check_gas->id);
            $check_gas->photo_vehicle_gas = $path;
            $check_gas->save();

            ChaNo::where('check_gas_id',$check_gas->id)->delete();
            foreach($request->cha_no as $Cha_no){
                $ChaNo = new ChaNo();
                $ChaNo->check_gas_id = $check_gas->id;
                $ChaNo->cha_no = $Cha_no;
                $ChaNo->save();
            }

            $userId = $loginBy->id;
            $type = 'สร้างข้อมูลแก็สตัวแทน';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type ;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $check_gas);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }
    public function addgas_agent(request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        $data_return = [];
        try {
                $InspectionVehicles = new InspectionVehicles();

                // return $vehicles;
                $InspectionVehicles->license_plate = $request->license_plate;
                $InspectionVehicles->provinces = $request->Provinces;

                $customer = Customers::where('id', $request->customer_id)->first();
                if (!$customer) {
                    return $this->returnErrorData('ไม่พบข้อมูลลูกค้า', 404);
                }
                // return $customer;
                $InspectionVehicles->no = $customer->no;
                $InspectionVehicles->name = $customer->name;
                $InspectionVehicles->email = $customer->email;
                $InspectionVehicles->phone_number = $customer->phone_number1;
                $InspectionVehicles->tax_id = $customer->tax_id;
                $InspectionVehicles->type = $customer->type;
                $InspectionVehicles->remark = $customer->note;
                $InspectionVehicles->save();

                $engineer = Employees::where('id', $request->engineer_id)->first();
                if (!$customer) {
                    return $this->returnErrorData('ไม่พบข้อมูลวิศวกร', 404);
                }
                // return $engineer;

                $check_gas = new CheckGas();
                $check_gas->inspection_vehicle_id = $InspectionVehicles->id;
                $check_gas->customer_id = $customer->id;
                $check_gas->customer_no = $customer->no;
                $check_gas->customer_name = $customer->name;
                $check_gas->engineer_id = $engineer->id;
                $check_gas->engineer_no = $engineer->code;
                $check_gas->engineer_name = $engineer->first_name.' '.$engineer->last_name;
                $check_gas->service_or = $request->service_or;  //เลขที่ตรวจในใบออกแก๊ส
                $check_gas->install_no = $request->install_no;  //เลขใบติดตั้ง
                $check_gas->date = $request->date;
                $check_gas->time = $request->time;
                $check_gas->brand_gas = $request->brand_gas;
                $check_gas->no_gas = $request->no_gas;
                $check_gas->type = 'agent';
                $check_gas->branch_id = $request->branch_id;

                if ($check_gas->photo) {
                    Storage::delete($check_gas->photo_vehicle_gas);
                }
                $path = $this->saveImage_gas($request, 'photo', $check_gas->id);
                $check_gas->photo_vehicle_gas = $path;
                $check_gas->save();

                $qty = 0;
                ChaNo::where('check_gas_id',$check_gas->id)->delete();
                if($request->cha_no){
                    foreach($request->cha_no as $Cha_no){
                        $vehicle_gases = new VehicleGas();
                        $vehicle_gases->gas_type = $Cha_no['gas_type'];
                        $gas_prefix = strtoupper(substr($Cha_no['gas_position'], 0, 3));
                        $vehicle_gases->gas_position = $Cha_no['gas_position'];
                        if ($gas_prefix === 'LPG') {
                            $gas_category = 'LPG';
                        } else {
                            $gas_category = 'NGV';
                        }

                        $vehicle_gases->gas_category = $gas_category;
                        $vehicle_gases->gas_brand = $Cha_no['gas_brand'];
                        $vehicle_gases->gas_number = $Cha_no['gas_number'];
                        $vehicle_gases->gas_weight = $Cha_no['gas_weight'];
                        $vehicle_gases->gas_thick = $Cha_no['gas_thick'];
                        $vehicle_gases->gas_capacity = $Cha_no['gas_capacity'];
                        $vehicle_gases->gas_create = $Cha_no['gas_create'];
                        $vehicle_gases->gas_expire = $Cha_no['gas_expire'];
                        $vehicle_gases->save();

                        //เลขใบต่อถัง
                        $ChaNo = new ChaNo();
                        $ChaNo->check_gas_id = $check_gas->id;
                        $ChaNo->cha_no = $Cha_no['cha_no'];
                        $ChaNo->vehicle_gas_id = $vehicle_gases->id;
                        $ChaNo->save();
                        $qty += 1;
                    }
                }

                // ========== ตัดเอกสาร ==============

                if($request->service_or != null){
                    $doc = doc::where('type_doc', 'gas')
                        ->where('engineer_id', $check_gas->engineer_id)
                        ->where('type_job', 'check')
                        ->where('type_vehicle', $request->typeVehicle)   // รย. หรือ ขส.
                        ->where('type_gas', $request->type_gas)  // แก๊ส LPG หรือ NGV
                        ->first();
                    if(!$doc){
                        return $this->returnErrorData('ไม่พบข้อมูลเอกสาร', 404);
                    }
                    if ($doc) {
                        $doc_customer = DocCustomer::where('docs_id', $doc->id)
                            ->where('customer_id', $request->customer_id)
                            ->first();
                        if(!$doc_customer){
                            return $this->returnErrorData('ไม่พบข้อมูลเอกสาร ' . $doc->name . ' ของตัวแทน รายนี้', 404);
                        }
                        $docTransaction = new Doc_transaction();
                        $docTransaction->doc_customer_id = $doc_customer->id;
                        $docTransaction->date = $request->date;
                        $docTransaction->transaction_type = 'Withdraw';
                        $docTransaction->stock = $doc_customer->total_qty;
                        $docTransaction->quantity = 1;
                        $docTransaction->balance = ($doc_customer->total_qty) - 1;
                        $docTransaction->remark = 'ตัดเอกสารแก๊ส ตรวจ ตัวแทน';
                        $docTransaction->save();

                        $doc_customer->total_qty -= 1;
                        $doc_customer->save();
                    }
                }

                if($request->install_no != null){
                    $doc = doc::where('type_doc', 'gas')
                        ->where('engineer_id', $check_gas->engineer_id)
                        ->where('type_job', 'Install')
                        ->where('type_vehicle', $request->typeVehicle)   // รย. หรือ ขส.
                        ->where('type_gas', $request->type_gas)  // แก๊ส LPG หรือ NGV
                        ->first();
                    if(!$doc){
                        return $this->returnErrorData('ไม่พบข้อมูลเอกสาร', 404);
                    }
                    if ($doc) {
                        $doc_customer = DocCustomer::where('docs_id', $doc->id)
                            ->where('customer_id', $request->customer_id)
                            ->first();
                        if(!$doc_customer){
                            return $this->returnErrorData('ไม่พบข้อมูลเอกสาร ' . $doc->name . ' ของตัวแทน รายนี้', 404);
                        }
                        $docTransaction = new Doc_transaction();
                        $docTransaction->doc_customer_id = $doc_customer->id;
                        $docTransaction->date = $request->date;
                        $docTransaction->transaction_type = 'Withdraw';
                        $docTransaction->stock = $doc_customer->total_qty;
                        $docTransaction->quantity = 1;
                        $docTransaction->balance = ($doc_customer->total_qty) - 1;
                        $docTransaction->remark = 'ตัดเอกสารแก๊ส ติดตั้ง ตัวแทน';
                        $docTransaction->save();

                        $doc_customer->total_qty -= 1;
                        $doc_customer->save();
                    }
                }

                if($request->cha_no != null){
                    $doc = doc::where('type_doc', 'gas')
                        ->where('engineer_id', $check_gas->engineer_id)
                        ->where('type_job', 'renew')
                        ->where('type_vehicle', $request->typeVehicle)   // รย. หรือ ขส.
                        ->where('type_gas', $request->type_gas)  // แก๊ส LPG หรือ NGV
                        ->first();
                    if(!$doc){
                        return $this->returnErrorData('ไม่พบข้อมูลเอกสาร', 404);
                    }
                    if ($doc) {
                        $doc_customer = DocCustomer::where('docs_id', $doc->id)
                            ->where('customer_id', $request->customer_id)
                            ->first();
                        if(!$doc_customer){
                            return $this->returnErrorData('ไม่พบข้อมูลเอกสาร ' . $doc->name . ' ของตัวแทน รายนี้', 404);
                        }
                        $docTransaction = new Doc_transaction();
                        $docTransaction->doc_customer_id = $doc_customer->id;
                        $docTransaction->date = $request->date;
                        $docTransaction->transaction_type = 'Withdraw';
                        $docTransaction->stock = $doc_customer->total_qty;
                        $docTransaction->quantity = $qty;
                        $docTransaction->balance = ($doc_customer->total_qty) - $qty;
                        $docTransaction->remark = 'ตัดเอกสารแก๊ส ติดตั้ง ตัวแทน';
                        $docTransaction->save();

                        $doc_customer->total_qty -= $qty;
                        $doc_customer->save();
                    }

                }

                $userId = $loginBy->id;
                $type = 'สร้างข้อมูลแก็สตัวแทน';
                $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type ;
                $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $data_return);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }
    public function updateStatus(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $inspection = Inspections::find($id);
            if (!$inspection) {
                return $this->returnErrorData('ไม่พบข้อมูลการตรวจสภาพ', 404);
            }

            $inspection->status = $request->status;
            $inspection->save();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขสถานะ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $request->status;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('อัปเดตสถานะสำเร็จ', $inspection);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function vehicles_gas_by_checkgas_id(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $check_gas = CheckGas::with('inspectionVehicle.vehicle.VehicleGas')
            ->where('id',$id)
            ->first();

        if (!$check_gas) {
            return $this->returnErrorData('ไม่พบข้อมูลแก๊ส', 404);
        }

        return $this->returnSuccess('Success', $check_gas);
    }

    private function genInspectionNoByYear(int $branchId, $billDate = null): string
    {
        $billDate = $billDate ? Carbon::parse($billDate) : now();

        // ปี พ.ศ. 2 หลักท้าย เช่น 2569 => "69"
        $beYear = $billDate->year + 543;
        $yy = substr((string)$beYear, -2);

        // รูปแบบ: NO-690001 (NO- + yy + running 4 หลัก)
        $prefix = "NO-{$yy}";
        $likePrefix = $prefix . '%';

        // ดึง running ล่าสุดของ "ปีนี้ + สาขานี้"
        // IMPORTANT: order ตาม running (ไม่ใช่ id) เพื่อให้ถูกแม้ข้อมูล insert สลับ
        $lastRun = Inspections::where('branch_id', $branchId)
            ->where('no', 'like', $likePrefix)
            ->whereNotNull('no')
            ->selectRaw("CAST(SUBSTRING(no, 6) AS UNSIGNED) AS run") // SUBSTRING(no,6) -> หลัง 'NO-69'
            ->orderByDesc('run')
            ->lockForUpdate()
            ->value('run');

        $nextRun = ((int) $lastRun) + 1;

        return $prefix . str_pad((string)$nextRun, 4, '0', STR_PAD_LEFT);
    }
}
