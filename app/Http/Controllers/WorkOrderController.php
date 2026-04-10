<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Item;
use App\Models\Services;
use App\Models\Vehicles;
use App\Models\Customers;
use App\Models\LineItems;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Repair_service;
use App\Models\WorkOrderServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Haruncpi\LaravelIdGenerator\IdGenerator;

class WorkOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = WorkOrder::all();
        return $Item;
    }

    public function index_paid()
    {
        $Item = WorkOrder::select('work_orders.*')
            // ->leftJoin('tax_invoices', 'work_orders.id', '=', 'tax_invoices.work_order_id')
            ->leftJoin('tax_invoices', function($join) {
                $join->on('work_orders.id', '=', 'tax_invoices.work_order_id')
                     ->where('tax_invoices.status', '!=', 'rejected');
            })
            ->whereNull('tax_invoices.id')
            ->where('work_orders.status', '!=', 'cancel')
            ->where('work_orders.type', '=', 'vat')
            ->where('work_orders.status_expend', '=', 'paid')
            ->orderBy('id', 'desc')
            ->get();
        return $Item;
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

        try {
            $license_plate = $request->license_plate;
            $car = Vehicles::where('license_plate', $license_plate)->first();

            if ($car) {
                $car_id = $car->id;
            } else {
                $car_new = new Vehicles();
                $car_new->customer_id = $request->customer_id;
                if ($request->hasFile('file')) {
                    $picname = str::random(8);
                    $name = $car_new->name;
                    $car_new->file = $this->save_file($request->file('file'), $picname, $name);
                }
                $car_new->license_plate = $request->license_plate;
                $car_new->vehicle_inspection_types_id = $request->vehicle_inspection_types_id;
                $car_new->description = $request->description;
                $car_new->brand_id = $request->brand_id;
                $car_new->province_id = $request->province_id;
                $car_new->model_id = $request->model_id;
                $car_new->chassis_number = $request->chassis_number;
                $car_new->engine_number = $request->engine_number;
                $car_new->cc = $request->cc;
                $car_new->weight = $request->weight;
                $car_new->weight_loaded = $request->weight_loaded;
                $car_new->seat_count = $request->seat_count;
                $car_new->registration_date = $request->registration_date;
                $car_new->notificate_insurance = $request->notificate_insurance;
                $car_new->notificate_sub_insurance = $request->notificate_sub_insurance;
                $car_new->notificate_tax = $request->notificate_tax;
                $car_new->save();
                $car_id = $car_new->id;
            }

            $prefix = "OR-";
            $no = IdGenerator::generate(['table' => 'work_orders', 'field' => 'no', 'length' => 8, 'prefix' => $prefix]);

            $work_item = new WorkOrder();
            $work_item->description = $request->description;
            $work_item->no = $no;
            $work_item->date = $request->date;
            $work_item->vehicle_id = $car_id;
            $work_item->status = $request->status;
            $work_item->customer_id = $request->customer_id;
            $work_item->total = $request->total;
            $work_item->branches_id = $request->branches_id;
            $work_item->status_paid = $request->status_paid;
            $work_item->paid_type = $request->paid_type;
            $work_item->type = $request->type;
            $work_item->discount = $request->discount;
            $work_item->tax = $request->tax;
            $work_item->total_amount = $request->total_amount;
            $work_item->save();

            $work_item_id = $work_item->id;

            foreach ($request->list_order as $item) {
                $repairService = Repair_service::where('name', $item['repair_service_name'])->first();

                if ($repairService) {
                    $repair_service_id = $repairService->id;
                } else {
                    $newRepairService = new Repair_service();
                    $newRepairService->name = $item['repair_service_name'];
                    $newRepairService->price = $item['repair_service_price'];
                    $newRepairService->save();
                    $repair_service_id = $newRepairService->id;
                }

                $WorkOrderServices = new WorkOrderServices();
                $WorkOrderServices->work_order_id = $work_item_id;
                $WorkOrderServices->repair_service_id = $repair_service_id;
                $WorkOrderServices->price = $item['price'];
                $WorkOrderServices->discount = $item['discount'];

                $WorkOrderServices->save();
            }

            foreach ($request->List_item as $Item) {
                $ItemCheck = Item::where('id', $Item['Item_id'])->first();

                if (!$ItemCheck) {
                    return response()->json(['error' => 'Item in line item not found.'], 404);
                } else {

                    $Line_item = new LineItems();
                    $Line_item->item_id = $ItemCheck->id;
                    $Line_item->work_order_id = $work_item_id;
                    $Line_item->employee_id = $request->employee_id;
                    $Line_item->date = $work_item->date;
                    $Line_item->stock = $Item['stock'];
                    $Line_item->QTY = $ItemCheck->QTY;
                    $Line_item->remark = $request->remark;
                    $Line_item->operation = 'finish';
                    $Line_item->type = 'Withdraw';
                    $Line_item->balance = $ItemCheck->QTY - $Item['stock'];
                    $ItemCheck->QTY = $Line_item->balance;
                    $Line_item->save();
                    $ItemCheck->save();
                }
            }

            $prefix = "CS-";
            $no = IdGenerator::generate(['table' => 'customers', 'field' => 'no', 'length' => 8, 'prefix' => $prefix]);

            $cus_item = new Customers();
            $cus_item->name = $request->name;
            $cus_item->no = $no;
            $cus_item->email = $request->email;
            $cus_item->phone_number1 = $request->phone_number1;
            $cus_item->phone_number2 = $request->phone_number2;
            $cus_item->tax_id = $request->tax_id;
            $cus_item->type = $request->type;
            $cus_item->is_headquarter = $request->is_headquarter;
            $cus_item->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'เพิ่มรายการข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $work_item, $cus_item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // ใช้ findOrFail เพื่อให้แน่ใจว่า ID มีอยู่
            $workorder_status = WorkOrder::findOrFail($id);

            // ตั้งค่าสถานะใหม่
            $workorder_status->status = $request->status;
            $workorder_status->save();

            DB::commit();
            return $this->returnSuccess('Success', $workorder_status);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // dd($request->all());
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }
        DB::beginTransaction();
        try {
            $work_item = WorkOrder::find($id);
            $work_item->description = $request->input('description');
            $work_item->date = $request->input('date');
            $work_item->vehicle_id = $request->input('vehicle_id');
            $work_item->status = $request->input('status');
            $work_item->customer_id = $request->input('customer_id');
            $work_item->branches_id = $request->input('branches_id');
            $work_item->status_paid = $request->input('status_paid');
            $work_item->paid_type = $request->paid_type;
            $work_item->type = $request->type;
            $work_item->discount = $request->discount;
            $work_item->tax = $request->tax;
            $work_item->total_amount = $request->total_amount;
            $work_item->save();

            $car_new = Vehicles::find($work_item->vehicle_id);
            // dd($car_new);
            $car_new->customer_id = $request->input('customer_id');

            if ($request->hasFile('file')) {
                $picname = str::random(8);
                $name = $car_new->name;
                $car_new->file = $this->save_file($request->file('file'), $picname, $name);
            }
            $car_new->license_plate = $request->input('license_plate');
            $car_new->vehicle_inspection_types_id = $request->input('vehicle_inspection_types_id');
            $car_new->description = $request->input('description');
            $car_new->brand_id = $request->input('brand_id');
            $car_new->province_id = $request->input('province_id');
            $car_new->model_id = $request->input('model_id');
            $car_new->chassis_number = $request->input('chassis_number');
            $car_new->engine_number = $request->input('engine_number');
            $car_new->cc = $request->input('cc');
            $car_new->weight = $request->input('weight');
            $car_new->weight_loaded = $request->input('weight_loaded');
            $car_new->seat_count = $request->input('seat_count');
            $car_new->registration_date = $request->input('registration_date');
            $car_new->notificate_insurance = $request->input('notificate_insurance');
            $car_new->notificate_sub_insurance = $request->input('notificate_sub_insurance');
            $car_new->notificate_tax = $request->input('notificate_tax');
            $car_new->save();

            $cus_item = Customers::findOrFail($car_new->customer_id);

            $cus_item->name = $request->input('name');
            $cus_item->email = $request->input('email');
            $cus_item->phone_number1 = $request->input('phone_number1');
            $cus_item->phone_number2 = $request->input('phone_number2');
            $cus_item->tax_id = $request->input('tax_id');
            $cus_item->type = $request->input('type');
            $cus_item->is_headquarter = $request->input('is_headquarter');

            $work_item->description = $request->input('description');
            $work_item->date = $request->input('date');
            $work_item->vehicle_id = $request->input('vehicle_id');
            $work_item->status = $request->input('status');
            $work_item->customer_id = $request->input('customer_id');
            $work_item->save();

            $cus_item->save();

            WorkOrderServices::where('work_order_id', $id)->delete();
            LineItems::where('work_order_id', $id)->delete();

            if ($request->has('list_order')) {
                foreach ($request->list_order as $item) {
                    $repairService = Repair_service::where('name', $item['repair_service_name'])->first();

                    if ($repairService) {
                        $repair_service_id = $repairService->id;
                    } else {
                        $newRepairService = new Repair_service();
                        $newRepairService->name = $item['repair_service_name'];
                        $newRepairService->price = $item['repair_service_price'];
                        $newRepairService->save();
                        $repair_service_id = $newRepairService->id;
                    }

                    $WorkOrderServices = new WorkOrderServices();
                    $WorkOrderServices->work_order_id = $work_item->id;
                    $WorkOrderServices->repair_service_id = $repair_service_id;
                    $WorkOrderServices->price = $item['price'];
                    $WorkOrderServices->save();
                }
            }

            foreach ($request->List_item as $Item) {
                $ItemCheck = Item::where('id', $Item['Item_id'])->first();

                if ($ItemCheck) {
                    $Line_item = new LineItems();
                    $Line_item->item_id = $ItemCheck->id;
                    $Line_item->work_order_id = $work_item->id;
                    $Line_item->employee_id = $request->input('employee_id');
                    $Line_item->date = $work_item->date;
                    $Line_item->stock = $Item['stock'];
                    $Line_item->QTY = $ItemCheck->QTY;
                    $Line_item->remark = $request->input('remark');
                    $Line_item->operation = 'finish';
                    $Line_item->type = 'Withdraw';
                    $Line_item->balance = $ItemCheck->QTY - $Item['stock'];
                    $ItemCheck->QTY = $Line_item->balance;
                    $Line_item->save();
                    $ItemCheck->save();
                } else {
                    return $this->returnErrorData('Item not found', 404);
                }
            }
            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', [
                'vehicle' => $car_new,
                'work_order' => $work_item,
                'customer' => $cus_item,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $Item = WorkOrder::find($id);

            if (!$Item) {
                return $this->returnError('ไม่พบข้อมูลที่ต้องการลบ');
            }

            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id: ' . $Item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('ลบข้อมูลสำเร็จ', $Item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }
    public function Getpage_info(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = intval($start / $length) + 1;

        $columns = [
            'id',
            'name',
            'email',
            'phone_number1',
            'phone_number2',
            'paid_type',
        ];

        $searchableColumns = [
            'customers.name',
            'vehicles.license_plate',
            'work_orders.no',
        ];

        $query = Customers::with([
            'vehicles.workOrders.lineItems.item',
            'vehicles.workOrders.WorkOrderServices.repair_service',
        ])
            ->whereHas('vehicles.workOrders');

        $orderByColumn = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderByColumn, $order[0]['dir']);

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $columns = [
            'id',
            'description',
            'total',
            'date',
            'no',
            'status',
        ];

        $searchcolumn = [
            'status',
        ];
        $query = WorkOrder::select($columns);

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
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    private function genWorkOrderNo(): string
    {
        $now = now();

        // ปี พ.ศ. 2 หลัก เช่น 2569 → 69
        $yy = substr((string)($now->year + 543), -2);

        // FIX-69
        $prefix = "FIX-{$yy}";

        // ดึงเลขล่าสุดของปีนี้
        $last = DB::table('work_orders')
            ->where('no', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $lastRun = 0;

        if ($last && preg_match('/^FIX-\d{2}(\d+)$/', $last->no, $m)) {
            $lastRun = (int) $m[1];
        }

        $nextRun = $lastRun + 1;

        // FIX-690001
        return $prefix . str_pad($nextRun, 4, '0', STR_PAD_LEFT);
    }

    public function AddWorkOrder(Request $request)
    {
        $loginBy = Auth()->user();
        if (!$loginBy) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        // $prefix = "FIX-";
        // $no = IdGenerator::generate(['table' => 'work_orders', 'field' => 'no', 'length' => 8, 'prefix' => $prefix]);
        $no = $this->genWorkOrderNo();
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'remark' => 'nullable|string',
                'total' => 'required|numeric',
                'date' => 'required|date',
                'vehicle_id' => 'required|integer',
                'branches_id' => 'required|integer',
                // 'status' => 'required|string|in:open,wait,repairing,success',
                // 'status_paid' => 'required|string|in:paid_cash,paid_tran,credit',
                // 'customer_id' => 'required|integer',
                // 'customer_no' => 'required|string',
                // 'customer_name' => 'required|string',
                // 'customer_address' => 'required|string',
                // 'customer_phone' => 'required|string',
                // 'customer_email' => 'nullable|string',
                // 'customer_tax_id' => 'nullable|string',
                // 'customer_is_headquarter' => 'nullable|boolean',
                // 'brand_name' => 'required|string',
                // 'model_name' => 'required|string',
                // 'license_plate' => 'required|string',
                // 'mileage' => 'nullable|integer',
                // 'chassis_number' => 'nullable|string',
                // 'engine_number' => 'nullable|string',
                'service' => 'required|array',
                'service.*.service_id' => 'required|integer',
                'service.*.qty' => 'required|integer',
                'service.*.service_name' => 'required|string',
                'service.*.service_price' => 'required|numeric',
                'service.*.unit_price' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $validatedData = $validator->validated();
            $customer = Customers::where('id', $request->customer_id)->first();
            $vehicle = Vehicles::where('id', $request->vehicle_id)->with('VehicleBrands', 'VehicleModels')->first();

            if (!$customer) {
                return $this->returnErrorData('ไม่พบข้อมูลลูกค้า', 404);
            }
            if (!$vehicle) {
                return $this->returnErrorData('ไม่พบข้อมูลรถ', 404);
            }

            $data = [
                'no' => $no,
                'customer_id' => $request->customer_id,
                'customer_no' => $request->customer_no,
                'customer_name' => $request->customer_name . $request->customer_lname,
                'customer_address' => $request->customer_address,
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email ?? null,
                'customer_tax_id' => $request->customer_tax_id ?? null,
                'customer_is_headquarter' => $request->customer_is_headquarter,

                'brand_name' => $request->brand_name ?? null,
                'model_name' => $request->model_name ?? null,
                'license_plate' => $request->license_plate ?? null,
                'mileage' => $request->mileage ?? null,
                'chassis_number' => $request->chassis_number ?? null,
                'engine_number' => $request->engine_number ?? null,
                'color' => $request->color ?? null,

                'branches_id' => $request->branches_id,
                'type' => $request->type,
                'discount' => $request->discount ?? 0,
                'tax' => $request->tax ?? 0,
                'total_amount' => $request->total_amount ?? 0,
            ];

            $WorkOrder = WorkOrder::create(array_merge($validatedData, $data));

            $workOrderServices = [];
            foreach ($validatedData['service'] as $service) {
                $workOrderServices[] = [
                    'work_order_id' => $WorkOrder->id,
                    'service_id' => $service['service_id'],
                    'qty' => $service['qty'],
                    'service_name' => $service['service_name'],
                    'service_price' => $service['service_price'],
                    'unit_price' => $service['unit_price'],
                    'discount' => $service['discount'] ?? 0
                ];
            }

            WorkOrderServices::insert($workOrderServices);
            DB::commit();
            $return = [
                'work_order' => $WorkOrder,
                'work_order_services' => $workOrderServices,
            ];
            return $this->returnSuccess('เพิ่มข้อมูลสำเร็จ', $return);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function UpdateWorkOrder(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!$loginBy) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'remark' => 'nullable|string',
                'total' => 'required|numeric',
                'date' => 'required|date',
                'vehicle_id' => 'required|integer',
                'branches_id' => 'required|integer',
                'status' => 'required|string|in:open,wait,repairing,success',
                'status_paid' => 'required|string|in:paid_cash,paid_tran,credit',
                // 'customer_id' => 'required|integer',
                // 'customer_no' => 'required|string',
                // 'customer_name' => 'required|string',
                // 'customer_address' => 'required|string',
                // 'customer_phone' => 'required|string',
                // 'customer_email' => 'nullable|string',
                // 'customer_tax_id' => 'nullable|string',
                // 'customer_is_headquarter' => 'nullable|boolean',
                // 'brand_name' => 'required|string',
                // 'model_name' => 'required|string',
                // 'license_plate' => 'required|string',
                // 'mileage' => 'nullable|integer',
                // 'chassis_number' => 'nullable|string',
                // 'engine_number' => 'nullable|string',
                'service' => 'required|array',
                'service.*.service_id' => 'required|integer',
                'service.*.qty' => 'required|integer',
                'service.*.service_name' => 'required|string',
                'service.*.service_price' => 'required|numeric',
                'service.*.unit_price' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'เกิดข้อผิดพลาด',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $validatedData = $validator->validated();

            $WorkOrder = WorkOrder::find($id);
            if (!$WorkOrder) {
                return $this->returnErrorData('ไม่พบข้อมูลงาน', 404);
            }

            $customer = Customers::where('id', $request->customer_id)->with('Customer_Addresses')->first();
            $vehicle = Vehicles::where('id', $request->vehicle_id)->with('VehicleBrands', 'VehicleModels')->first();

            if (!$customer) {
                return $this->returnErrorData('ไม่พบข้อมูลลูกค้า', 404);
            }
            if (!$vehicle) {
                return $this->returnErrorData('ไม่พบข้อมูลรถ', 404);
            }

            $data = [
                'customer_id' => $request->customer_id,
                'customer_no' => $request->customer_no,
                'customer_name' => $request->customer_name,
                'customer_address' => $request->customer_address,
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email ?? null,
                'customer_tax_id' => $request->customer_tax_id ?? null,
                'customer_is_headquarter' => $request->customer_is_headquarter,

                'brand_name' => $request->brand_name ?? null,
                'model_name' => $request->model_name ?? null,
                'license_plate' => $request->license_plate ?? null,
                'mileage' => $request->mileage ?? null,
                'chassis_number' => $request->chassis_number ?? null,
                'engine_number' => $request->engine_number ?? null,

                'branches_id' => $request->branches_id,
                'type' => $request->type,
                'discount' => $request->discount ?? 0,
                'tax' => $request->tax ?? 0,
                'total_amount' => $request->total_amount ?? 0,
            ];

            $WorkOrder->update(array_merge($validatedData, $data));

            WorkOrderServices::where('work_order_id', $id)->delete();
            $workOrderServices = [];
            foreach ($validatedData['service'] as $service) {
                $workOrderServices[] = [
                    'work_order_id' => $WorkOrder->id,
                    'service_id' => $service['service_id'],
                    'qty' => $service['qty'],
                    'service_name' => $service['service_name'],
                    'service_price' => $service['service_price'],
                    'unit_price' => $service['unit_price'],
                    'discount' => $service['discount'] ?? 0,
                    'created_at' => Carbon::now(),
                ];
            }
            WorkOrderServices::insert($workOrderServices);

            DB::commit();
            return response()->json([
                'message' => 'แก้ไขข้อมูลสำเร็จ',
                'data' => $WorkOrder,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }
    public function DeleteWorkOrder(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!$loginBy) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $workOrder = WorkOrder::findOrFail($id);

            WorkOrderServices::where('work_order_id', $id)->delete();

            $workOrder->delete();

            DB::commit();
            return response()->json([
                'massage' => 'ลบสำเร็จ',
                'data' => $workOrder,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'code' => '401',
                'status' => false,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
    public function update_status(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnErrorData('ไม่พบข้อมูลผู้ใช้งาน', 404);
        }

        $data = WorkOrder::where('id',$id)->with('WorkOrderServices.Service')->first();
        if (!$data) {
            return $this->returnErrordata('ไม่พบข้อมูล', 404);
        }

        try {
            $data->status = $request->status;
            $data->save();

            if($request->status == 'success'){
                $lineitem_data = LineItems::where('work_order_id',$data->id)->get();

                if ($lineitem_data) {
                    foreach ($lineitem_data as $item) {
                        $Services = Services::find($item->service_id);
                        $Services->increment('qty', $item->qty);
                    }
                    LineItems::where('work_order_id', $data->id)->delete();
                }

                foreach ($data->WorkOrderServices as $item) {
                    $lineitem = new LineItems();
                    $lineitem->work_order_id = $data->id; ;
                    $lineitem->service_id = $item['service_id'];
                    $lineitem->date =  carbon::now() ;
                    $lineitem->stock = $item['service']['qty'];
                    $lineitem->qty = $item['qty'];
                    $lineitem->balance = $item['service']['qty'] - $item['qty'];
                    $lineitem->type = 'Withdraw';
                    $lineitem->save();

                    $Services = Services::find($item['service_id']);
                    $Services->decrement('qty', $item['qty']);
                }
            }

            //Log
            $userId = $loginBy->id;
            $type = 'อัปเดตสถานะการซ่อม';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $data->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('อัปเดตสถานะการซ่อมสําเร็จ', $data);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    public function show_All_workorder()
    {
        $data = WorkOrder::with('Vehicles')->get();
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }

    public function show_workorderByID($id)
    {
        $data = WorkOrder::where('id', $id)
            ->with('Vehicles', 'Branches', 'WorkOrderServices','WorkOrderPayment')
            ->first();

        if (!$data) {
            return $this->returnErrordata('ไม่พบข้อมูล', 404);
        } else {
            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
        }
    }

    public function getpage_workorder(Request $request)
    {
        $perPage = (int) $request->input('length', 10);
        $start   = (int) $request->input('start', 0);
        $page    = (int) floor($start / max($perPage,1)) + 1;

        $search  = $request->input('search.value', "");
        $order   = $request->input('order', []);
        $columns = $request->input('columns', []);

        // ✅ กำหนด whitelist ฟิลด์ที่อนุญาตให้ sort ได้
        $sortable = ['date','license_plate','customer_name','status','total_amount','id'];

        // --- สร้าง query ---
        $datas = WorkOrder::with('Vehicles','Branches','WorkOrderServices','WorkOrderPayment');

        if ($search !== '') {
            $datas->where(function ($q) use ($search) {
                $q->orWhere('no', 'like', "%{$search}%")
                ->orWhere('license_plate', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $datas->where('status', $request->status);
        }
        if ($request->filled('date_start') && $request->filled('date_end')) {
            $datas->whereBetween('date', [$request->date_start, $request->date_end]);
        }

        // ✅ ปลอดภัยกับ index คอลัมน์ที่เพี้ยน/เกิน
        $orderIndex = (int) data_get($order, '0.column', -1);
        $orderDir   = strtolower(data_get($order, '0.dir', 'desc'));
        $orderDir   = in_array($orderDir, ['asc','desc'], true) ? $orderDir : 'desc';

        // พยายามดึงชื่อฟิลด์จาก columns -> data
        $requestedField = null;
        if ($orderIndex >= 0 && isset($columns[$orderIndex]['data'])) {
            $requestedField = $columns[$orderIndex]['data'];
        }

        // ใช้เฉพาะถ้าอยู่ใน whitelist ไม่งั้น fallback
        $sortField = in_array($requestedField, $sortable, true) ? $requestedField : 'id';

        $datas->orderBy($sortField, $orderDir);

        $data = $datas->paginate($perPage, ['*'], 'page', $page);

        // เติม customer_name (เหมือนเดิม)
        $collection  = $data->getCollection();
        $customerIds = $collection->pluck('customer_id')->filter()->unique()->values();

        if ($customerIds->isNotEmpty()) {
            $customerMap = Customers::whereIn('id', $customerIds)->get()
                ->mapWithKeys(function ($c) {
                    $full = trim(implode(' ', array_filter([$c->name ?? '', $c->lname ?? ''])));
                    return [$c->id => ($full ?: null)];
                });

            $collection = $collection->map(function ($wo) use ($customerMap) {
                $wo->customer_name = $customerMap[$wo->customer_id] ?? null;
                return $wo;
            });
            $data->setCollection($collection);
        } else {
            $data->setCollection($collection->map(function ($wo) {
                $wo->customer_name = null; return $wo;
            }));
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }


    public function reportWorkOrder(Request $request)
    {
        $customer_id    = $request->input('customer_id', '');
        $customer_phone = $request->input('customer_phone', '');
        $license_plate  = $request->input('license_plate', '');
        $start_date     = $request->input('start_date', '');
        $end_date       = $request->input('end_date', '');

        $query = Customers::query();

        // ----- กรองจาก work_orders -----
        $query->whereHas('WorkOrders', function ($q) use ($license_plate, $customer_phone, $start_date, $end_date) {

            if ($license_plate) {
                $q->where('license_plate', $license_plate);
            }

            if ($customer_phone) {
                $q->where('customer_phone', $customer_phone);
            }

            // กรองช่วงวันที่จากฟิลด์ work_orders.date
            if (!empty($start_date) && !empty($end_date)) {
                $q->whereBetween('date', [
                    $start_date . ' 00:00:00',
                    $end_date   . ' 23:59:59',
                ]);
            } elseif (!empty($start_date)) {
                $q->whereDate('date', '>=', $start_date);
            } elseif (!empty($end_date)) {
                $q->whereDate('date', '<=', $end_date);
            }
        });

        // ----- กรองฝั่ง customer เอง -----
        if ($customer_id) {
            $query->where('id', $customer_id);
        }

        if ($customer_phone) {
            $query->where(function ($q) use ($customer_phone) {
                $q->where('phone_number1', $customer_phone)
                ->orWhere('phone_number2', $customer_phone);
            });
        }

        // ----- โหลด work_orders ที่ “อยู่ในช่วงวันที่” เท่านั้น -----
        $result = $query->with(['WorkOrders' => function ($q) use ($license_plate, $start_date, $end_date) {

            if ($license_plate) {
                $q->where('license_plate', $license_plate);
            }

            // เงื่อนไขเดียวกับด้านบน
            if (!empty($start_date) && !empty($end_date)) {
                $q->whereBetween('date', [
                    $start_date . ' 00:00:00',
                    $end_date   . ' 23:59:59',
                ]);
            } elseif (!empty($start_date)) {
                $q->whereDate('date', '>=', $start_date);
            } elseif (!empty($end_date)) {
                $q->whereDate('date', '<=', $end_date);
            }

            // จะให้เรียงยังไงเพิ่มได้ เช่น
            $q->orderBy('date', 'asc');

        }])->get();

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $result);
    }


}
