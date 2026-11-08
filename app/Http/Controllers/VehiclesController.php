<?php

namespace App\Http\Controllers;

use App\Models\VehicleGas;
use App\Models\Vehicles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class VehiclesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // ใช้โหลดเบา — หน้าสร้างบิล/autocomplete ต้องการแค่ฟิลด์หลัก (เดิม eager load ทั้ง VehicleGas ทุกคันทำให้ 500/timeout)
            return Vehicles::query()
                ->select([
                    'id',
                    'customer_id',
                    'license_plate',
                    'province_id',
                    'registration_date',
                    'vehicle_inspection_types_id',
                    'insurance_types_id',
                    'brand_id',
                    'model_id',
                    'chassis_number',
                    'engine_number',
                    'fuel_type',
                    'fuel',
                    'cc',
                    'weight',
                    'weight_loaded',
                    'seat_count',
                    'description',
                    'notificate_tax',
                    'tax_renewal_date',
                    'mileage',
                    'is_corporate',
                ])
                ->whereNotNull('license_plate')
                ->where('license_plate', '!=', '')
                ->orderBy('license_plate')
                ->get()
                ->each(function (Vehicles $vehicle) {
                    $vehicle->setAppends([]);
                    $vehicle->makeHidden(['file']);
                });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'ไม่สามารถโหลดรายการรถได้',
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            'registration_date' => 'required|date',
            'license_plate' => 'required|string',
            'province_id' => 'required|integer|exists:provinces,id',
            'vehicle_inspection_types_id' => 'required|integer|exists:vehicle_inspection_types,id',
            'cc' => 'required|integer',
            'fuel_type' => 'required|string|in:oil,gas,Hybrid',
            'weight' => 'required|numeric',
        ], [
            'registration_date.required' => 'กรุณาระบุวันที่จดทะเบียน',
            'registration_date.date' => 'วันที่จดทะเบียนไม่ถูกต้อง',
            'license_plate.required' => 'กรุณาระบุเลขทะเบียนรถ',
            'province_id.required' => 'กรุณาระบุจังหวัด',
            'province_id.integer' => 'รหัสจังหวัดต้องเป็นตัวเลข',
            'province_id.exists' => 'รหัสจังหวัดไม่ถูกต้อง',
            'vehicle_inspection_types_id.required' => 'กรุณาระบุประเภทการตรวจสภาพรถ',
            'vehicle_inspection_types_id.exists' => 'ประเภทการตรวจสภาพรถไม่ถูกต้อง',
            'cc.required' => 'กรุณาระบุขนาดเครื่องยนต์',
            'cc.integer' => 'ขนาดเครื่องยนต์ต้องเป็นตัวเลข',
            'fuel_type.required' => 'กรุณาระบุประเภทเชื้อเพลิง',
            'fuel_type.in' => 'ประเภทเชื้อเพลิงไม่ถูกต้อง',
            'weight.required' => 'กรุณาระบุน้ำหนักรถ',
            'weight.numeric' => 'น้ำหนักรถต้องเป็นตัวเลข',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        try {
            $Item = new Vehicles();
            $Item->customer_id = $request->customer_id;
            $Item->file = $request->file;

            $Item->registration_date = $request->registration_date;
            $Item->license_plate = $request->license_plate;
            $Item->province_id = $request->province_id;
            $Item->vehicle_inspection_types_id = $request->vehicle_inspection_types_id;
            $Item->insurance_types_id = $request->insurance_types_id;
            $Item->brand_id = $request->brand_id;
            $Item->model_id = $request->model_id;
            $Item->chassis_number = $request->chassis_number;
            $Item->engine_number = $request->engine_number;
            $Item->fuel_type = $request->fuel_type;
            $Item->fuel = $request->fuel;
            $Item->cc = $request->cc;
            $Item->weight = $request->weight;
            $Item->weight_loaded = $request->weight_loaded;
            $Item->weight_all = $request->weight_all;
            $Item->seat_count = $request->seat_count;
            $Item->description = $request->description;
            $Item->notificate_tax = $request->notificate_tax;
            $Item->is_corporate = $request->is_corporate;
            $Item->tax_renewal_date = $request->tax_renewal_date;
            $Item->mileage = $request->mileage;
            $Item->manual_number = $request->manual_number;
            $Item->manual_date = $request->manual_date;

            $Item->save();

            if($request->gas){
                foreach ($request->gas as $gasItem) {
                    $vehicleGas = new VehicleGas();
                    $vehicleGas->vehicle_id = $Item->id;
                    $vehicleGas->gas_type = $gasItem['gas_type']; // ชนิดถัง
                    $vehicleGas->gas_position = $gasItem['gas_position']; // ตำแหน่งที่ติดตั้ง
                    $vehicleGas->gas_brand = $gasItem['gas_brand']; // ยี่ห้อของถัง
                    $vehicleGas->gas_number = $gasItem['gas_number']; // เลขของถัง
                    $vehicleGas->gas_weight = $gasItem['gas_weight']; // มวลของตัวถัง
                    $vehicleGas->gas_thick = $gasItem['gas_thick']; // ความหนาของถัง
                    $vehicleGas->gas_capacity = $gasItem['gas_capacity']; // ปริมาณความจุของถัง
                    $vehicleGas->gas_create = $gasItem['gas_create']; // วันที่ผลิตถัง
                    $vehicleGas->gas_expire = $gasItem['gas_expire']; // วันหมดอายุถัง
                    $vehicleGas->save();
                }
            }
            $Item->load(['Provinces', 'Branches', 'VehicleModels','VehicleBrands','VehicleInspectionTypes','InsuranceTypes','VehicleGas','customers']);
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
    public function showID($id)
    {
        $Vehicles = Vehicles::where('id', $id)
            ->with('customers')
            ->with('VehicleInspectionTypes')
            ->with('InsuranceTypes')
            ->with('VehicleGas')
            ->with('Provinces')
            ->with('VehicleBrands')
            ->first();

        if (!$Vehicles) {
            return $this->returnBadRequest('Vehicles not found');
        } else {
            return $this->returnSuccess('Success', $Vehicles);
        }
    }

    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            'registration_date' => 'required|date',
            'license_plate' => 'required|string',
            'province_id' => 'required|integer|exists:provinces,id',
            'vehicle_inspection_types_id' => 'required|integer|exists:vehicle_inspection_types,id',
            'cc' => 'required|integer',
            'fuel_type' => 'required|string|in:oil,gas,Hybrid',
            'weight' => 'required|numeric',
        ], [
            'registration_date.required' => 'กรุณาระบุวันที่จดทะเบียน',
            'registration_date.date' => 'วันที่จดทะเบียนไม่ถูกต้อง',
            'license_plate.required' => 'กรุณาระบุเลขทะเบียนรถ',
            'province_id.required' => 'กรุณาระบุจังหวัด',
            'province_id.integer' => 'รหัสจังหวัดต้องเป็นตัวเลข',
            'province_id.exists' => 'รหัสจังหวัดไม่ถูกต้อง',
            'vehicle_inspection_types_id.required' => 'กรุณาระบุประเภทการตรวจสภาพรถ',
            'vehicle_inspection_types_id.exists' => 'ประเภทการตรวจสภาพรถไม่ถูกต้อง',
            'cc.required' => 'กรุณาระบุขนาดเครื่องยนต์',
            'cc.integer' => 'ขนาดเครื่องยนต์ต้องเป็นตัวเลข',
            'fuel_type.required' => 'กรุณาระบุประเภทเชื้อเพลิง',
            'fuel_type.in' => 'ประเภทเชื้อเพลิงไม่ถูกต้อง',
            'weight.required' => 'กรุณาระบุน้ำหนักรถ',
            'weight.numeric' => 'น้ำหนักรถต้องเป็นตัวเลข',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        try {
            $Item = Vehicles::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลรถยนต์ที่ต้องการแก้ไข', 404);
            }
            // อัปเดตข้อมูล
            $Item->customer_id = $request->input('customer_id', $Item->customer_id);
            $Item->file = $request->input('customer_id', $Item->file);

            $Item->registration_date = $request->registration_date;
            $Item->license_plate = $request->license_plate;
            $Item->province_id = $request->province_id;
            $Item->vehicle_inspection_types_id = $request->vehicle_inspection_types_id;
            $Item->brand_id = $request->brand_id;
            $Item->model_id = $request->model_id;
            $Item->chassis_number = $request->chassis_number;
            $Item->engine_number = $request->engine_number;
            $Item->fuel_type = $request->fuel_type;
            $Item->fuel = $request->fuel;
            $Item->cc = $request->cc;
            $Item->weight = $request->weight;
            $Item->weight_loaded = $request->weight_loaded;
            $Item->weight_all = $request->weight_all;
            $Item->seat_count = $request->seat_count;
            $Item->description = $request->description;
            if(isset($request->notificate_tax)){
                $Item->notificate_tax = $request->notificate_tax;
            }
            if(isset($request->tax_renewal_date)){
                $Item->tax_renewal_date = $request->tax_renewal_date;
            }
            $Item->is_corporate = $request->is_corporate;
            $Item->mileage = $request->mileage;
            $Item->manual_number = $request->manual_number;
            $Item->manual_date = $request->manual_date;
            $Item->save();
            // ลบข้อมูลแก๊สเก่าออก (ถ้ามี) และเพิ่มใหม่
            if($request->gas){
                VehicleGas::where('vehicle_id', $Item->id)->delete();
                foreach ($request->gas as $gasItem) {
                    $vehicleGas = new VehicleGas();
                    $vehicleGas->vehicle_id = $Item->id;
                    $vehicleGas->gas_type = $gasItem['gas_type'];       // ชนิดถัง
                    $vehicleGas->gas_position = $gasItem['gas_position'];   // ตำแหน่งที่ติดตั้ง
                    $vehicleGas->gas_brand = $gasItem['gas_brand'];     // ยี่ห้อของถัง
                    $vehicleGas->gas_number = $gasItem['gas_number'];   // เลขของถัง
                    $vehicleGas->gas_weight = $gasItem['gas_weight'];   // มวลของตัวถัง
                    $vehicleGas->gas_thick = $gasItem['gas_thick'];     // ความหนาของถัง
                    $vehicleGas->gas_capacity = $gasItem['gas_capacity'];   // ปริมาณความจุของถัง
                    $vehicleGas->gas_create = $gasItem['gas_create'];   // วันที่ผลิตถัง
                    $vehicleGas->gas_expire = $gasItem['gas_expire'];   // วันหมดอายุถัง
                    $vehicleGas->save();
                }
            }
            $Item->load(['Provinces', 'Branches', 'VehicleModels','VehicleBrands','VehicleInspectionTypes','InsuranceTypes','VehicleGas','customers']);

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Update Data Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
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
            $Item = Vehicles::find($id);
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }
    public function getPageOLD(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;
        $customer_id = $request->customer_id;

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
            'weight_all',
            'seat_count',
            'description',
            'notificate_tax',
            'is_corporate',
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

        if ($customer_id) {
            $query->where('customer_id', $customer_id);
        }

        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);
        // $query->orderBy('created_at', 'desc');

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);


        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;
        $customer_id = $request->customer_id;

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
            'weight_all',
            'seat_count',
            'description',
            'notificate_tax',
            'is_corporate',
            'tax_renewal_date',
            'mileage',
            'manual_number',
            'manual_date',
            'created_at',
            'updated_at',
        ];

        $query = Vehicles::select($columns)
            ->with('VehicleInspectionTypes')
            ->with('InsuranceTypes')
            ->with('VehicleGas')
            ->with('VehicleBrands')
            ->with('VehicleModels')
            ->with('Provinces')
            ->with('Branches')
            ->with('customers');

        if ($customer_id) {
            $query->where('customer_id', $customer_id);
        }

        // order
        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        // search (license_plate + ชื่อลูกค้า)
        $term = trim((string)($search['value'] ?? ''));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                // ค้นหาใน vehicle
                $q->orWhere('license_plate', 'like', "%{$term}%");

                // ค้นหาในตารางลูกค้า (relation customers)
                $q->orWhereHas('customers', function ($cq) use ($term) {
                    $cq->where('name', 'like', "%{$term}%")
                    ->orWhere('lname', 'like', "%{$term}%")
                    // ค้นหาแบบ "ชื่อ เว้นวรรค นามสกุล"
                    ->orWhereRaw("CONCAT(name,' ',lname) LIKE ?", ["%{$term}%"]);
                    // ถ้าจะให้ค้นหาเพิ่ม (เช่น เบอร์/รหัสลูกค้า) ก็เติมได้:
                    // ->orWhere('phone', 'like', "%{$term}%")
                    // ->orWhere('no', 'like', "%{$term}%");
                });
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }


    public function update_call_log($id,Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $Item = Vehicles::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลรถยนต์ที่ต้องการแก้ไข', 404);
            }
            // อัปเดตข้อมูล
            $Item->call_log = $request->call_log;
            $Item->call_log_details = $request->call_log_details;
            $Item->save();

            DB::commit();

            return $this->returnSuccess('Update Data Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    public function updateTaxRenewalDate(Request $request, $vehicleId)
    {
        $request->validate([
            'tax_renewal_date' => 'nullable|date'
        ]);

        $v = Vehicles::find($vehicleId);
        if(!$v) return $this->returnErrorData('Vehicle not found', 404);

        $v->tax_renewal_date = $request->tax_renewal_date;
        $v->save();

        return $this->returnSuccess('บันทึกสำเร็จ', $v);
    }
}
