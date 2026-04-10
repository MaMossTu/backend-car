<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\Services;
use App\Models\TaxInvoice;
use App\Models\Branches;
use App\Models\Invoice;
use App\Models\TaxInvoiceAllNos;
use App\Models\TaxInvoiceService;
use App\Models\WorkOrder;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class TaxInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $TaxInvoice = TaxInvoice::all();
        return $TaxInvoice;
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

        $validator = Validator::make(request()->all(), [
            'branche_id' => 'required|integer',
            'customer_id' => 'required|integer',

            'date' => 'required|date',
            'total' => 'required|numeric',
            'discount' => 'required|numeric',
            'tax' => 'required|numeric',
            'total_amount' => 'required|numeric',

            'service' => 'required|array|min:1',
            'service.*.service_id' => 'required|integer',
            'service.*.service_price' => 'required|numeric',
            'service.*.qty' => 'required|numeric',
            'service.*.discount' => 'required|numeric',

        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $count = TaxInvoice::whereYear('date', date('Y'))
                ->where('branche_id', $request->branche_id)
                ->whereMonth('date', date('m'))
                ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

            $no = sprintf("HS%s%s/%03d", $year, $month, $count);

            $TaxInvoice = new TaxInvoice();
            $TaxInvoice->branche_id = $request->branche_id;
            $TaxInvoice->customer_id = $request->customer_id;
            $TaxInvoice->no = $no;

            $customer = Customers::where('id', $request->customer_id)->with('Customer_Addresses')->first();

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address = $addressObj->address ?? null;
                $provinceName = $addressObj->provinces->name_th_prefix ?? null;
                $districtName = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode = $addressObj->zip_code ?? null;
            } else {
                $address = null;
                $provinceName = null;
                $districtName = null;
                $subdistrictName = null;
                $postalcode = null;
            }

            $TaxInvoice->customer_no = $customer->no;
            $TaxInvoice->customer_name = $customer->name . (isset($customer->lname) ? ' ' . $customer->lname : '');
            $TaxInvoice->customer_address = $address ?? '';
            $TaxInvoice->customer_address2 = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $TaxInvoice->customer_phone = $customer->phone_number1 ?? '';
            $TaxInvoice->customer_email = $customer->email ?? '';
            $TaxInvoice->customer_tax_id = $customer->tax_id ?? '';
            $TaxInvoice->customer_is_headquarter = $customer->is_headquarter;

            $TaxInvoice->date = $request->date;
            $TaxInvoice->remark = $request->remark;
            $TaxInvoice->total = $request->total;
            $TaxInvoice->discount = $request->discount;

            $TaxInvoice->tax = $request->tax;
            $TaxInvoice->total_amount = $request->total_amount;
            $TaxInvoice->paid_type = $request->payment_term;
            $TaxInvoice->withholding_tax = $request->withholding_tax;
            $TaxInvoice->withholding_tax_percent = $request->withholding_tax_percent;
            $TaxInvoice->save();

            foreach ($request->service as $service) {
                $taxInvoiceService = new TaxInvoiceService();
                $taxInvoiceService->tax_invoice_id = $TaxInvoice->id;

                $servicedata = Services::where('id', $service['service_id'])->first();
                $taxInvoiceService->service_id = $service['service_id'];
                $taxInvoiceService->service_name = $servicedata->name;
                $taxInvoiceService->qty = $service['qty'];
                $taxInvoiceService->discount = $service['discount'];
                $taxInvoiceService->service_price = $service['service_price'];
                $taxInvoiceService->unit_price = $service['unit_price'];
                $taxInvoiceService->save();
            }

            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $count = TaxInvoiceAllNos::whereYear('date', date('Y'))
                ->where('branche_id', $request->branche_id)
                ->whereMonth('date', date('m'))
                ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

            $no = sprintf("HS%s%s/%03d", $year, $month, $count);

            $TaxInvoiceAllNo =  new TaxInvoiceAllNos();
            $TaxInvoiceAllNo->tax_invoice_id = $TaxInvoice->id;
            $TaxInvoiceAllNo->branche_id = $request->branche_id;
            $TaxInvoiceAllNo->no = $no;
            $TaxInvoiceAllNo->date = Carbon::now();
            $TaxInvoiceAllNo->type = 'tax_invoice';
            $TaxInvoiceAllNo->save();

            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มข้อมูล ใบกำกับภาษี';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $TaxInvoice->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $TaxInvoice);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }
    public function store_by_invoice(Request $request)
    {
        $invoice_id = $request->invoice_id;

        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }
        $invoice = Invoice::with('invoiceServices')->find($invoice_id);
        if (!$invoice) {
            return $this->returnErrorData('ไม่พบข้อมูลใบแจ้งหนี้ กรุณาเข้าสู่ระบบใหม่อีกครั้ง',404);
        }
        $find_TaxInvoice = TaxInvoice::where('invoice_id', $invoice->id)->where('status', '!=','rejected')->first();
        if ($find_TaxInvoice) {
            return $this->returnErrorData('ไม่สามารถสร้างใบกำกับภาษีได้ เนื่องจากมีใบกำกับภาษีของใบแจ้งหนี้นี้อยู่แล้ว',404);
        }
        // return $invoice;

        DB::beginTransaction();
        try {
            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $count = TaxInvoice::whereYear('date', date('Y'))
                ->where('branche_id', $invoice->branche_id)
                ->whereMonth('date', date('m'))
                ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

            $no = sprintf("HS%s%s/%03d", $year, $month, $count);

            $TaxInvoice = new TaxInvoice();
            $TaxInvoice->branche_id = $invoice->branche_id;
            $TaxInvoice->customer_id = $invoice->customer_id;
            $TaxInvoice->invoice_id = $invoice->id;
            $TaxInvoice->no = $no;

            $customer = Customers::where('id', $invoice->customer_id)->with('Customer_Addresses')->first();

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address = $addressObj->address ?? null;
                $provinceName = $addressObj->provinces->name_th_prefix ?? null;
                $districtName = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode = $addressObj->zip_code ?? null;
            } else {
                $address = null;
                $provinceName = null;
                $districtName = null;
                $subdistrictName = null;
                $postalcode = null;
            }

            $TaxInvoice->customer_no = $customer->no;
            $TaxInvoice->customer_name = $customer->name . (isset($customer->lname) ? ' ' . $customer->lname : '');
            $TaxInvoice->customer_address = $address ?? '';
            $TaxInvoice->customer_address2 = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $TaxInvoice->customer_phone = $customer->phone_number1 ?? '';
            $TaxInvoice->customer_email = $customer->email ?? '';
            $TaxInvoice->customer_tax_id = $customer->tax_id ?? '';
            $TaxInvoice->customer_is_headquarter = $customer->is_headquarter;

            $TaxInvoice->date = $request->paid_date;
            $TaxInvoice->remark = $invoice->remark;
            $TaxInvoice->total = $invoice->total;
            $TaxInvoice->discount = $invoice->discount;

            $TaxInvoice->tax = $invoice->tax;
            $TaxInvoice->total_amount = $invoice->total_amount;
            $TaxInvoice->paid_type = $request->paid_type;
            $TaxInvoice->withholding_tax = $request->withholding_tax;
            $TaxInvoice->withholding_tax_percent = $request->withholding_tax_percent;
            $TaxInvoice->save();

            foreach ($invoice->invoiceServices as $service) {
                $taxInvoiceService = new TaxInvoiceService();
                $taxInvoiceService->tax_invoice_id = $TaxInvoice->id;
                $taxInvoiceService->service_name = $service['service_name'];
                $taxInvoiceService->qty = $service['qty'];
                $taxInvoiceService->discount = $service['discount'];
                $taxInvoiceService->service_price = $service['service_price'];
                $taxInvoiceService->unit_price = $service['unit_price'];
                $taxInvoiceService->save();
            }

            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $Y = date('Y'); // ปีปัจจุบัน
            $m = date('m'); // เดือนปัจจุบัน
            $paid_date = $request->paid_date;
            if($paid_date){
                $year = date('y', strtotime($paid_date)) + 43; // เลขท้ายปีพ.ศ.
                $month = date('m', strtotime($paid_date)); // เดือนปัจจุบัน
                $Y = date('Y', strtotime($paid_date));
                $m = date('m', strtotime($paid_date));
            }

            $count = TaxInvoiceAllNos::whereYear('date', $Y)
                ->where('branche_id', $invoice->branche_id)
                ->whereMonth('date', $m)
                ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

            $no = sprintf("HS%s%s/%03d", $year, $month, $count);

            $TaxInvoiceAllNo =  new TaxInvoiceAllNos();
            $TaxInvoiceAllNo->tax_invoice_id = $TaxInvoice->id;
            $TaxInvoiceAllNo->branche_id = $invoice->branche_id;
            $TaxInvoiceAllNo->no = $no;
            $TaxInvoiceAllNo->date = $paid_date;
            $TaxInvoiceAllNo->type = 'tax_invoice';
            $TaxInvoiceAllNo->save();

            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มข้อมูล ใบกำกับภาษี';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $TaxInvoice->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $TaxInvoice);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }
    public function store_by_work_order(Request $request)
    {
        $work_order_id = $request->work_order_id;

        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }
        $WorkOrder = WorkOrder::with('WorkOrderServices')->find($work_order_id);
        if (!$WorkOrder) {
            return $this->returnErrorData('ไม่พบข้อมูลงานซ่อม กรุณาเข้าสู่ระบบใหม่อีกครั้ง',404);
        }
        $find_TaxInvoice = TaxInvoice::where('work_order_id', $WorkOrder->id)->where('status', '!=','rejected')->first();
        if ($find_TaxInvoice) {
            return $this->returnErrorData('ไม่สามารถสร้างใบกำกับภาษีได้ เนื่องจากมีใบกำกับภาษีของใบซ่อมนี้อยู่แล้ว',404);
        }
        // return $WorkOrder;

        DB::beginTransaction();
        try {
            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $count = TaxInvoice::whereYear('date', date('Y'))
                ->where('branche_id', $WorkOrder->branches_id)
                ->whereMonth('date', date('m'))
                ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

            $no = sprintf("HS%s%s/%03d", $year, $month, $count);

            $TaxInvoice = new TaxInvoice();
            $TaxInvoice->branche_id = $WorkOrder->branches_id;
            $TaxInvoice->customer_id = $WorkOrder->customer_id;
            $TaxInvoice->work_order_id = $WorkOrder->id;
            $TaxInvoice->no = $no;

            $customer = Customers::where('id', $WorkOrder->customer_id)->with('Customer_Addresses')->first();

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address = $addressObj->address ?? null;
                $provinceName = $addressObj->provinces->name_th_prefix ?? null;
                $districtName = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode = $addressObj->zip_code ?? null;
            } else {
                $address = null;
                $provinceName = null;
                $districtName = null;
                $subdistrictName = null;
                $postalcode = null;
            }

            $TaxInvoice->customer_no = $customer->no;
            $TaxInvoice->customer_name = $customer->name . (isset($customer->lname) ? ' ' . $customer->lname : '');
            $TaxInvoice->customer_address = $address ?? '';
            $TaxInvoice->customer_address2 = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $TaxInvoice->customer_phone = $customer->phone_number1 ?? '';
            $TaxInvoice->customer_email = $customer->email ?? '';
            $TaxInvoice->customer_tax_id = $customer->tax_id ?? '';
            $TaxInvoice->customer_is_headquarter = $customer->is_headquarter;

            $TaxInvoice->date = $request->paid_date;
            $TaxInvoice->remark = $WorkOrder->remark;
            $TaxInvoice->total = $WorkOrder->total;
            $TaxInvoice->discount = $WorkOrder->discount;

            $TaxInvoice->tax = $WorkOrder->tax;
            $TaxInvoice->total_amount = $WorkOrder->total_amount;
            $TaxInvoice->paid_type = $request->paid_type;
            $TaxInvoice->withholding_tax = $request->withholding_tax;
            $TaxInvoice->withholding_tax_percent = $request->withholding_tax_percent;
            $TaxInvoice->save();

            foreach ($WorkOrder->WorkOrderServices as $service) {
                $taxInvoiceService = new TaxInvoiceService();
                $taxInvoiceService->tax_invoice_id = $TaxInvoice->id;
                $taxInvoiceService->service_name = $service['service_name'];
                $taxInvoiceService->qty = $service['qty'];
                $taxInvoiceService->discount = $service['discount'];
                $taxInvoiceService->service_price = $service['service_price'];
                $taxInvoiceService->unit_price = $service['unit_price'];
                $taxInvoiceService->save();
            }

            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $Y = date('Y'); // ปีปัจจุบัน
            $m = date('m'); // เดือนปัจจุบัน
            $paid_date = $request->paid_date;
            if($paid_date){
                $year = date('y', strtotime($paid_date)) + 43; // เลขท้ายปีพ.ศ.
                $month = date('m', strtotime($paid_date)); // เดือนปัจจุบัน
                $Y = date('Y', strtotime($paid_date));
                $m = date('m', strtotime($paid_date));
            }
            $count = TaxInvoiceAllNos::whereYear('date', $Y)
                ->where('branche_id', $WorkOrder->branches_id)
                ->whereMonth('date', $m)
                ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

            $no = sprintf("HS%s%s/%03d", $year, $month, $count);

            $TaxInvoiceAllNo =  new TaxInvoiceAllNos();
            $TaxInvoiceAllNo->tax_invoice_id = $TaxInvoice->id;
            $TaxInvoiceAllNo->branche_id = $WorkOrder->branches_id;
            $TaxInvoiceAllNo->no = $no;
            $TaxInvoiceAllNo->date = $paid_date;
            $TaxInvoiceAllNo->type = 'tax_invoice';
            $TaxInvoiceAllNo->save();

            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มข้อมูล ใบกำกับภาษี';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $TaxInvoice->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $TaxInvoice);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Display the specified resource.
     */
    public function showID($id)
    {
        $TaxInvoice = TaxInvoice::where('id', $id)->with('Branches', 'Customers', 'taxInvoiceService','taxInvoiceAllNos')->first();
        if (!$TaxInvoice) {
            return $this->returnBadRequest('ไม่พบข้อมูล');
        } else {
            return $this->returnSuccess('Success', $TaxInvoice);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make(request()->all(), [
            'branche_id' => 'required|integer',
            'customer_id' => 'required|integer',

            'date' => 'required|date',
            'total' => 'required|numeric',
            'discount' => 'required|numeric',
            'tax' => 'required|numeric',
            'total_amount' => 'required|numeric',

            'service' => 'required|array|min:1',
            'service.*.service_price' => 'required|numeric',
            'service.*.qty' => 'required|numeric',
            'service.*.discount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $TaxInvoice = TaxInvoice::find($id);
            if (!$TaxInvoice) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $TaxInvoice->status = 'rejected';
            $TaxInvoice->save();

            $existingBilling = TaxInvoice::where('no', 'like', $TaxInvoice->no . '%')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingBilling) {
                $parts = explode('/', $existingBilling->no);

                if (count($parts) == 2) {
                    // (BI6712/001 -> BI6712/001/01)
                    $newNo = $TaxInvoice->no . '/01';
                } else {
                    // (BI6712/001/01 -> BI6712/001/02)
                    $currentSubNumber = intval($parts[2]);
                    $newSubNumber = $currentSubNumber + 1;
                    $newNo = $parts[0] . '/' . $parts[1] . '/' . sprintf('%02d', $newSubNumber);
                }
            }

            $newTaxInvoice = new TaxInvoice();
            $newTaxInvoice->branche_id = $request->branche_id;
            $newTaxInvoice->customer_id = $request->customer_id;
            $newTaxInvoice->no = $newNo;

            $customer = Customers::where('id', $request->customer_id)->with('Customer_Addresses')->first();

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address = $addressObj->address ?? null;
                $provinceName = $addressObj->provinces->name_th_prefix ?? null;
                $districtName = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode = $addressObj->zip_code ?? null;
            } else {
                $address = null;
                $provinceName = null;
                $districtName = null;
                $subdistrictName = null;
                $postalcode = null;
            }

            $newTaxInvoice->customer_no = $customer->no;
            $newTaxInvoice->customer_name = $customer->name;
            $newTaxInvoice->customer_address = $address ?? '';
            $newTaxInvoice->customer_address2 = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $newTaxInvoice->customer_phone = $customer->phone_number1 ?? '';
            $newTaxInvoice->customer_email = $customer->email ?? '';
            $newTaxInvoice->customer_tax_id = $customer->tax_id ?? '';
            $newTaxInvoice->customer_is_headquarter = $customer->is_headquarter;

            $newTaxInvoice->date = $request->date;
            $newTaxInvoice->remark = $request->remark;
            $newTaxInvoice->total = $request->total;
            $newTaxInvoice->discount = $request->discount;
            $newTaxInvoice->tax = $request->tax;
            $newTaxInvoice->total_amount = $request->total_amount;
            $newTaxInvoice->paid_type = $request->paid_type;
            $newTaxInvoice->withholding_tax = $request->withholding_tax;
            $newTaxInvoice->withholding_tax_percent = $request->withholding_tax_percent;
            $newTaxInvoice->save();

            foreach ($request->service as $service) {
                $taxInvoiceService = new TaxInvoiceService();
                $taxInvoiceService->tax_invoice_id = $newTaxInvoice->id;

                $servicedata = Services::where('id', $service['service_id'])->first();
                $taxInvoiceService->service_id = $service['service_id'];
                $taxInvoiceService->service_name = $service['service_name'];
                $taxInvoiceService->qty = $service['qty'];
                $taxInvoiceService->discount = $service['discount'];
                $taxInvoiceService->service_price = $service['service_price'];
                $taxInvoiceService->unit_price = $service['unit_price'];
                $taxInvoiceService->save();
            }

            $TaxInvoiceAllNo = TaxInvoiceAllNos::where('tax_invoice_id', $TaxInvoice->id)->first();
            if($TaxInvoiceAllNo) {
                $newAllNo = $TaxInvoiceAllNo->no;
            } else {
                // กรณีไม่พบ ยังคงสร้างเลขใหม่ตามปกติ
                $year = date('y') + 43;
                $month = date('m');
                $count = TaxInvoiceAllNos::whereYear('date', date('Y'))
                    ->where('branche_id', $request->branche_id)
                    ->whereMonth('date', date('m'))
                    ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                    ->count() + 1;

                $newAllNo = sprintf("HS%s%s/%03d", $year, $month, $count);
            }

            $TaxInvoiceAllNo->tax_invoice_id = $newTaxInvoice->id;
            $TaxInvoiceAllNo->no = $newAllNo;
            $TaxInvoiceAllNo->date = carbon::now();
            $TaxInvoiceAllNo->type = 'tax_invoice';
            $TaxInvoiceAllNo->save();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล ใบกำกับภาษี';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $newTaxInvoice->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data updated successfully', $newTaxInvoice);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
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

        try {
            $TaxInvoice = TaxInvoice::find($id);
            if (!$TaxInvoice) {
                return $this->returnBadRequest('quotation not found');
            }
            $TaxInvoice->taxInvoiceService()->delete();
            $TaxInvoice->delete();

            $userId = $loginBy->id;
            $type = 'ลบข้อมูล ใบกำกับภาษี';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('ลบข้อมูลสำเร็จ', $TaxInvoice);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Get paginated results.
     */
    public function getPage(Request $request)
    {
        $data =

        $perPage = $request->input('length', 10);
        $page = $request->input('start', 0);
        $search = $request->input('search.value', "");
        $sortField = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', -1);

        $search_status = $request->input('search_status', "");
        $customer_id = $request->input('customer_id', "");
        $month = $request->input('month', "");
        $branche_id = $request->input('branche_id', "");


        $direction = 'ASC';
        if ($sortOrder == -1) {
            $direction = 'DESC';
        }

        $seaclable = ['no', 'customer_name'];

        $TaxInvoice = TaxInvoice::with(
            'Branches',
            'Customers',
            'taxInvoiceService',
        )
            ->where(
                function ($query) use ($search, $seaclable, $search_status, $customer_id, $month) {
                    if ($search) {
                        foreach ($seaclable as &$s) {
                            $query->orWhere($s, 'LIKE', '%' . $search . '%');
                        }
                    }
                    if (!empty($search_status)) {
                        $query->where('status', $search_status);
                    }
                    if (!empty($customer_id)) {
                        $query->whereHas('Customers', function ($subQuery) use ($customer_id) {
                            $subQuery->where('id', $customer_id);
                        });
                    }
                    if (!empty($branche_id)) {
                        $query->where('branche_id', $branche_id);
                    }
                    // if (!empty($month)) {
                    //     // สร้าง Carbon object สำหรับปีปัจจุบันและเดือนที่ระบุ
                    //     $currentYear = Carbon::now()->year;
                    //     $startOfMonth = Carbon::create($currentYear, $month, 1)->startOfMonth();
                    //     $endOfMonth = Carbon::create($currentYear, $month, 1)->endOfMonth();

                    //     // ค้นหา job ที่มี date อยู่ในเดือนที่ระบุ
                    //     $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
                    // }
                }
            );

        $TaxInvoice = $TaxInvoice
            ->orderBy($sortField, $direction)
            ->paginate($perPage, ['*'], 'page', ($page / $perPage) + 1);

        return $this->returnSuccess('Success', $TaxInvoice);
    }

    public function update_status(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $TaxInvoice = TaxInvoice::find($id);
        if (!$TaxInvoice) {
            return $this->returnBadRequest('ไม่พบข้อมูล');
        }
        try {
            $TaxInvoice->status = $request->status;
            $TaxInvoice->save();

            // Log
            $userId = $loginBy->id;
            $type = 'อัปเดตสถานะใบกำกับภาษี';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $TaxInvoice->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $TaxInvoice);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    // public function getPage_all(Request $request)
    // {
    //     $draw = $request->input('draw', 1);
    //     $start = $request->input('start', 0);
    //     $length = $request->input('length', 10);
    //     $search = $request->input('search.value', '');

    //     // Handle column sorting from DataTables
    //     $orderColumn = 0;
    //     $orderDirection = 'DESC';

    //     if ($request->has('order') && is_array($request->input('order'))) {
    //         $orderData = $request->input('order')[0] ?? null;
    //         if ($orderData && isset($orderData['column']) && isset($orderData['dir'])) {
    //             $orderColumn = $orderData['column'];
    //             $orderDirection = strtoupper($orderData['dir']);
    //         }
    //     }

    //     $columns = [
    //         0 => 'id',
    //         1 => 'no',
    //         2 => 'date',
    //         3 => 'type'
    //     ];

    //     $sortField = $columns[$orderColumn] ?? 'id';

    //     // Get additional filter parameters
    //     $search_status = $request->input('search_status', '');
    //     $customer_id = $request->input('customer_id', '');
    //     $month = $request->input('month', '');

    //     $query = TaxInvoiceAllNos::with([
    //         'Inspection.Customers',
    //         'TaxInvoice'
    //     ]);

    //     // Handle search
    //     if ($search) {
    //         $query->where(function ($query) use ($search) {
    //             // Search in TaxInvoiceAllNos fields
    //             $query->where('no', 'LIKE', '%' . $search . '%');

    //             // Search in related Inspection
    //             $query->orWhereHas('Inspection', function ($q) use ($search) {
    //                 $q->where('no', 'LIKE', '%' . $search . '%')
    //                   ->orWhereHas('Customers', function ($c) use ($search) {
    //                       $c->where('name', 'LIKE', '%' . $search . '%')
    //                         ->orWhere('phone_number1', 'LIKE', '%' . $search . '%');
    //                   });
    //             });

    //             // Search in related TaxInvoice
    //             $query->orWhereHas('TaxInvoice', function ($q) use ($search) {
    //                 $q->where('no', 'LIKE', '%' . $search . '%')
    //                   ->orWhere('customer_name', 'LIKE', '%' . $search . '%')
    //                   ->orWhere('customer_phone', 'LIKE', '%' . $search . '%');
    //             });
    //         });
    //     }

    //     // Apply status filter if provided
    //     if ($search_status) {
    //         $query->where(function ($q) use ($search_status) {
    //             $q->whereHas('Inspection', function ($subq) use ($search_status) {
    //                 $subq->where('status', $search_status);
    //             })
    //             ->orWhereHas('TaxInvoice', function ($subq) use ($search_status) {
    //                 $subq->where('status', $search_status);
    //             });
    //         });
    //     }

    //     // Apply customer filter if provided
    //     if ($customer_id) {
    //         $query->where(function ($q) use ($customer_id) {
    //             $q->whereHas('Inspection', function ($subq) use ($customer_id) {
    //                 $subq->where('customer_id', $customer_id);
    //             })
    //             ->orWhereHas('TaxInvoice', function ($subq) use ($customer_id) {
    //                 $subq->where('customer_id', $customer_id);
    //             });
    //         });
    //     }

    //     // Get total count before pagination
    //     $totalRecords = $query->count();
    //     $filteredRecords = $totalRecords; // This will be the same as total if no search is applied

    //     // Apply sorting and pagination
    //     $results = $query->orderBy($sortField, $orderDirection)
    //                     ->skip($start)
    //                     ->take($length)
    //                     ->get();

    //     // Format for DataTables response
    //     $response = [
    //         'draw' => intval($draw),
    //         'recordsTotal' => $totalRecords,
    //         'recordsFiltered' => $filteredRecords,
    //         'data' => $results
    //     ];

    //     return response()->json($response);
    // }

    public function getPage_all(Request $request)
    {
        $perPage = $request->input('length', 10);
        $page = $request->input('start', 0);
        $search = $request->input('search.value', "");
        $sortField = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', -1);

        $search_status = $request->input('search_status', "");
        $customer_id = $request->input('customer_id', "");
        $month = $request->input('month', "");
        $branche_id = $request->input('branche_id', "");

        $direction = 'ASC';
        if ($sortOrder == -1) {
            $direction = 'DESC';
        }

        $query = TaxInvoiceAllNos::with(
            'Inspection.Customers',
            'TaxInvoice.Invoice',
            'TaxInvoice.WorkOrder',
        );

        // If search term is provided
        if ($search) {
            $query->where(function ($query) use ($search) {
                // Search in TaxInvoiceAllNos fields
                $query->where('no', 'LIKE', '%' . $search . '%');

                // Search in related Inspection
                $query->orWhereHas('Inspection', function ($q) use ($search) {
                    $q->where('no', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('Customers', function ($c) use ($search) {
                        $c->where('name', 'LIKE', '%' . $search . '%')
                            ->orWhere('phone_number1', 'LIKE', '%' . $search . '%');
                    });
                });

                // Search in related TaxInvoice
                $query->orWhereHas('TaxInvoice', function ($q) use ($search) {
                    $q->where('no', 'LIKE', '%' . $search . '%')
                    ->orWhere('customer_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('customer_phone', 'LIKE', '%' . $search . '%');
                });
            });
        }

        // Apply other filters if needed
        if ($customer_id) {
            $query->where(function ($q) use ($customer_id) {
                $q->whereHas('Inspection', function ($q) use ($customer_id) {
                    $q->where('customer_id', $customer_id);
                })
                ->orWhereHas('TaxInvoice', function ($q) use ($customer_id) {
                    $q->where('customer_id', $customer_id);
                });
            });
        }

        if ($month) {
            $query->whereMonth('date', $month);
        }

        if ($search_status) {
            $query->where(function ($q) use ($search_status) {
                $q->whereHas('Inspection', function ($q) use ($search_status) {
                    $q->where('status', $search_status);
                })
                ->orWhereHas('TaxInvoice', function ($q) use ($search_status) {
                    $q->where('status', $search_status);
                });
            });
        }

        if ($branche_id) {
            $query->where(function ($q) use ($branche_id) {
                $q->whereHas('Inspection', function ($q) use ($branche_id) {
                    $q->where('branch_id', $branche_id);
                })
                ->orWhereHas('TaxInvoice', function ($q) use ($branche_id) {
                    $q->where('branche_id', $branche_id);
                });
            });
        }
        $results = $query->orderBy($sortField, $direction)
                        ->paginate($perPage, ['*'], 'page', ($page / $perPage) + 1);

        return $this->returnSuccess('Success', $results);
    }

    public function pdf(Request $request, $id)
    {
        $taxInvoice = TaxInvoice::with([
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
            'Customers',
            'taxInvoiceService',
            'TaxInvoiceAllNos',
        ])->where('id', $id)->first();

        if (!$taxInvoice) {
            return $this->returnError('Data Not Found');
        }

        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->first();
            if (!$branche) {
                return $this->returnError('Branches Not Found');
            }
            $branche_Name = $branche->business_name ?? null;
            $branche_address = $branche->address ?? null;
            $branche_open_date_time = $branche->open_close_time ?? null;
            $branche_open_date = $branche->open_date ?? null;
            $branche_phone = $branche->phone ?? null;
            $branche_image = $branche->logo ?? null;
            $branche_is_headquarter = $branche->is_headquarter ?? null;
            $branche_tax = $branche->tax_id ?? null;
        } else {
            $branche_Name = $taxInvoice->Branches->business_name ?? null;
            $branche_address = $taxInvoice->Branches->address ?? null;
            $branche_open_date_time = $taxInvoice->Branches->open_close_time ?? null;
            $branche_open_date = $taxInvoice->Branches->open_date ?? null;
            $branche_phone = $taxInvoice->Branches->phone ?? null;
            $branche_image = $taxInvoice->Branches->logo ?? null;
            $branche_is_headquarter = $taxInvoice->Branches->is_headquarter ?? null;
            $branche_tax = $taxInvoice->Branches->tax_id ?? null;
        }

        switch ($taxInvoice->customer_is_headquarter) {
            case 1:
                $headqurter = "สำนักงานใหญ่";
                $chack = "&#9745;";
                break;
            case 0;
                $headqurter = "";
                $chack = "&#9744;";
                break;
            default:
                $headqurter = "";
                $chack = "&#9744;";
                break;
        }

        switch ($branche_is_headquarter) {
            case 1:
                $Bheadqurter = "สำนักงานใหญ่";
                break;
            case 0;
                $Bheadqurter = "";
                break;
            default:
                $Bheadqurter = "";
                break;
        }

        $customer = $taxInvoice->customers ?? null;
        $customer_name = $customer->name ?? null;
        $customer_phone = $customer->phone_number1 ?? null;

        $remark = $taxInvoice->remark ?? "&nbsp;";

        // ตัดข้อความโดยใช้ substr หรือ mb_substr เพื่อรองรับ UTF-8
        $maxLength = 255; // จำนวนตัวอักษรสูงสุด
        if (mb_strlen($remark, 'UTF-8') > $maxLength) {
            $shortRemark = mb_substr($remark, 0, $maxLength, 'UTF-8') . '...';
        } else {
            $shortRemark = $remark;
        }

        $serviceRows = '';
        $counter = 1;
        $counters = 1;
        $i = 0;
        $plas = 0;
        // $lineindex = 0;
        $rowsPerPage = 9;
        $currentRow = 1;
        $grandTotal = $taxInvoice->total;
        $lessdicount = 0;
        $totaltaxInvoice = count($taxInvoice['taxInvoiceService']);
        $itemsPerPage = 8;
        $pages = ceil($totaltaxInvoice / $itemsPerPage);
        // dd($totaltaxInvoice);

        // return $data;
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 10,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);

        $date = $taxInvoice->date;
        $ndate = new DateTime($date);
        // dd ($taxInvoice->TaxInvoiceAllNos[count($taxInvoice->TaxInvoiceAllNos)-1]->no);

        $formatDate = $ndate->format('d/m/y');
        $totaltaxInvoice = count($taxInvoice['taxInvoiceService']);
        $itemsPerPage = 6;
        $pages = ceil($totaltaxInvoice / $itemsPerPage);

        $mpdf->SetTitle('ใบกำกับภาษี');
        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                $mpdf->AddPage();
            }

            $html = '
                <div style="position: absolute;top:40px;left:30px;">
                    <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
                </div>

                <div style="position: absolute;top:36px;left:150px;">
                    <h2>' . $branche_Name . '</h2>
                    <p style="font-size: 20px;">' . $branche_address . '</p>
                    <p style="font-size: 20px;">' . $branche_phone . '</p>
                    <p style="font-size: 20px;">เลขประจำตัวผู้เสียภาษี : &nbsp;&nbsp;' . $branche_tax . '&nbsp;&nbsp;&nbsp;&nbsp;' . $Bheadqurter . ' </p>
                </div>
                <div style="position: absolute;top:95px;right:40px;text-align:center;line-height: 1.7;">
                    <p style="font-size: 24px;background-color: #ffb428  ;text-align:center; font-weight: bold;padding:2px 30px -8px 30px;border-radius:20px;"><strong>ใบเสร็จรับเงิน/ใบกำกับภาษี</strong></p>
                    <p style="font-size: 20px;margin-top:3px;"><strong>ต้นฉบับ</strong></p>
                </div>

                <div style="position: absolute; left: 38px;top:193px; width: 42.6%;padding-left: 15px;line-height: 1; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
                    <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                        <p style="font-size: 18px;">ลูกค้า:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $taxInvoice->customer_no . '</p>
                        <p style="font-size: 12px;">customer</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;font-weight: bold;">
                        <p style="font-size: 16px;">' . $taxInvoice->customer_name . '</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;font-weight: bold; height: 50px;">
                        <p style="font-size: 16px;">' . $taxInvoice->customer_address . ' ' . $taxInvoice->customer_address2 . '
                        </p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">โทร. <strong>' . $taxInvoice->customer_phone . '</strong></p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">เลขประจำตัวผู้เสียภาษี &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $taxInvoice->customer_tax_id . '</strong></p>
                        <p style="font-size: 12px;">Tax ID</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">อ้างอิง</p>
                        <p style="font-size: 12px;">Reference</p>
                    </div>
                </div>
                <div style="position: absolute;top:295px;left:270px;font-size: 16px;">
                    <div>
                        <p style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $chack . '</p>
                    </div>
                </div>
                <div style="position: absolute;top:312px;left:295px;">
                    <label>สำนักงานใหญ่</label>
                </div>';
            $html .= '

                <div style="position: absolute; right: 38px;top:193px; width: 42.6%;padding-left: 20px;line-height: 0.7; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
                    <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                        <p style="font-size: 18px;">เลขที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . (isset($taxInvoice->TaxInvoiceAllNos) && count($taxInvoice->TaxInvoiceAllNos) > 0 ? $taxInvoice->TaxInvoiceAllNos[count($taxInvoice->TaxInvoiceAllNos)-1]->no : '') . '</strong> </p>
                        <p style="font-size: 12px;">No.</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . (isset($taxInvoice->TaxInvoiceAllNos) && count($taxInvoice->TaxInvoiceAllNos) > 0 ? $taxInvoice->TaxInvoiceAllNos[count($taxInvoice->TaxInvoiceAllNos)-1]->date : '') . '</strong></p>
                        <p style="font-size: 12px;">Date</p>
                    </div>

                    <div style="position: absolute;margin-top:30px;">
                        <p style="font-size: 16px;">เลขที่ใบสั่งขาย</p>
                        <p style="font-size: 12px;">Sales Order No.</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">พนักงานขาย &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-</p>
                        <p style="font-size: 12px;">Salesman</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">เขตการขาย</p>
                        <p style="font-size: 12px;">Territory</p>
                    </div>
                </div>

                <div style="position: absolute;top:720px;left:49px;width:405px;height:120px;">
                    <p style="margin: 0px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $shortRemark . '</p>
                </div>

                <div style="position: absolute;top:344px;margin-top:45px;left:39.5px;width:717px;border: 0.5px solid #000;border-top-left-radius: 7px;border-top-right-radius: 7px;background-color:#ffb428;"></div>
                <div style="position: absolute;top:836.7px;margin-top:28.7px;left:39.5px;width:422px;border-bottom-left-radius: 7px;background-color: #ffb428;"></div>
                <div style="position: absolute;top:836.7px;margin-top:28.7px;right:35.5px;width:125.4px;border-bottom-right-radius: 7px;background-color: #ffb428;"></div>
                <div style="position: absolute;margin-top:350px;left:39px;width:717px;border: 1px solid #000;border-radius: 7px; ">
                    <table style="width: 100%;border-collapse: collapse;">
                        <thead>
                            <tr>
                                <td style="width: 5%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-right: 10px;overflow: hidden;">ลำดับ<p style="font-size: 14px;">No.</p></td>
                                <td style="width: 40%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">รหัสสินค้า/รายละเอียด<br><p style="font-size: 16px;">Code/Descriptions</p></td>
                                <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">จำนวน<p style="font-size: 16px;">Quantity</p></td>
                                <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">หน่วยละ<p style="font-size: 16px;">Unit Price</p></td>
                                <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">ส่วนลด<p style="font-size: 16px;">Discount</p></td>
                                <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-right: 10px;overflow: hidden;border-right:none;">จำนวนเงิน<p style="font-size: 16px;">Amount</p></td>
                            </tr>
                        </thead>';

                $startIndex = $page * $itemsPerPage;
                $endIndex = min($startIndex + $itemsPerPage, $totaltaxInvoice);

                $catpage = 1;

                for ($lineindex = $startIndex; $lineindex < $endIndex; $lineindex++, $i++) {

                    $data = $taxInvoice['taxInvoiceService'][$i];
                    $servicename = $data->service_name;
                    $maxLengthPerLine = 48; // กำหนดจำนวนอักขระต่อบรรทัด

                    // ตัด $shortservicename เป็นหลายบรรทัด
                    $serviceLines = [];
                    while (mb_strlen($servicename, 'UTF-8') > 0) {
                        $serviceLines[] = htmlspecialchars(mb_substr($servicename, 0, $maxLengthPerLine, 'UTF-8'));
                        $servicename = mb_substr($servicename, $maxLengthPerLine, null, 'UTF-8'); // แก้ไขให้ถูกต้อง
                    }
                    foreach ($serviceLines as $lineindexs => $line) {
                        if ($lineindexs == 0) {

                            // บรรทัดแรก แสดงข้อมูลปกติ
                            $html .= '
                                                <tbody>
                                                <tr>
                                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $counter . '</td>
                                                    <td style="text-align: left;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: auto;padding-left:10px;">' . $line . '</td>
                                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $data->qty . '</td>
                                                    <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->unit_price, 2) . '</td>
                                                    <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->discount, 2) . '</td>
                                                    <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">' . number_format($data->service_price, 2) . '</td>
                                                </tr>';
                            $counter++;
                            $catpage++;

                        } else {
                            $catpage++;
                            // บรรทัดที่เหลือขึ้น <tr> ใหม่ และช่องอื่นๆเว้นว่าง
                            $html .= '
                                                <tr>
                                                    <td style="text-align: center;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                                    <td style="text-align: left;border-right: 1px solid #000;width: auto;height: auto;padding-left:10px;">' . $line . '</td>
                                                    <td style="text-align: center;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                                    <td style="text-align: right;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">&nbsp;</td>
                                                    <td style="text-align: right;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">&nbsp;</td>
                                                    <td style="text-align: right;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">&nbsp;</td>
                                                </tr>';

                        }
                    }
                }

            $emptyRowsNeeded = $rowsPerPage - ($catpage % $rowsPerPage);
            if ($emptyRowsNeeded == $rowsPerPage) {
                $emptyRowsNeeded = 0;  // ไม่ต้องเพิ่มแถวว่าง
            }

            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                                    <tr>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;border-right:none;">&nbsp;</td>
                                    </tr>
                                    ';
                $lineindex++;
                $catpage++;
            }

            $html .= '


                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                            <p style="font-size: 12px;"></p>
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">รวมเป็นเงิน</p>
                                                <p style="font-size: 12px;">Gross Amount</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($grandTotal, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="text-align: left;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">

                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">หักส่วนลด</p>
                                                <p style="font-size: 12px;">Less Discount</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($lessdicount, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>';
            $afterdis = $grandTotal - $lessdicount;
            $html .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">ยอดหลังหักส่วนลด</p>
                                                <p style="font-size: 12px;">After Discount</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($afterdis, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">หักเงินมัดจำ</p>
                                                <p style="font-size: 12px;">Less Deposit</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>0.00</p>
                                            </div>
                                        </td>
                                    </tr>';
            $afterdeposit = $afterdis - 0;
            $html .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">ยอดหลังหักเงินมัดจำ</p>
                                                <p style="font-size: 12px;">After Deposit</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($afterdeposit, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>';
            //ลูกค้าไม่ต้องการแสดง หัก ณ ที่จ่าย
            // if($data->taxinvoice->withholding_tax == 'Y'){ 
            //     $withholding_tax = number_format(($data->taxinvoice->total_amount - $data->taxinvoice->tax)*0.03,2);
            // }else{
            //     $withholding_tax = "-";
            // }
            // $html .= '
            //                         <tr>
            //                             <td colspan="3" style="text-align: center;padding-bottom:-10px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
            //                                 <p style="font-size: 12px;">&nbsp;&nbsp;ผิด ตก ยกเว้น E. & O.E. *** &nbsp;สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน&nbsp;***</p>
            //                             </td>
            //                             <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
            //                                 <div style="line-height: 0.3;padding-bottom:1px;">
            //                                     <p style="font-size: 18px;">จำนวนหัก ณ ที่จ่าย 3.00  %</p>
            //                                     <p style="font-size: 12px;">VAT</p>
            //                                 </div>
            //                             </td>

            //                             <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
            //                                 <div>
            //                                     <p>' . $withholding_tax. '</p>
            //                                 </div>
            //                             </td>
            //                         </tr>';     

            $aftervat = $data->taxinvoice->tax;
            $html .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;padding-bottom:-10px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <p style="font-size: 12px;">&nbsp;&nbsp;ผิด ตก ยกเว้น E. & O.E. *** &nbsp;สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน&nbsp;***</p>
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">จำนวนภาษีมูลค่าเพิ่ม     7.00  %</p>
                                                <p style="font-size: 12px;">VAT</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($aftervat, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>';
            //ลูกค้าไม่ต้องการแสดง หัก ณ ที่จ่าย
            // if($data->taxinvoice->withholding_tax == 'Y'){
            //     $total = $data->taxinvoice->total_amount - $withholding_tax;
            // }else{
            //     $total = $data->taxinvoice->total_amount;
            // }

            $total = $data->taxinvoice->total_amount;
            $html .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                            <p style="font-size: 18px;">(' . $this->convertToThaiText($total) . ')</p>
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">จำนวนเงินรวมทั้งสิ้น</p>
                                                <p style="font-size: 12px;">Total Invoice</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($total, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>

                                ';
            // }
            $html .= '
                        </tbody>
                    </table>
                </div>

                <div style="position: absolute;top:900px;left:39px;width:717px;height:190px;border: 1px solid #000;border-radius: 7px;">
                        <table style="width: 100%;border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 18px;width:35%;height:200px;border-right: 1px solid #000;">
                                    <div style="text-align: center;">
                                        <p style="font-size: 18px;font-weight: bold;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ได้รับสินค้าตามรายการถูกต้องแล้ว</p><br>
                                    </div>
                                    <div>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p style="font-size: 18px;">&nbsp;ผู้รับสินค้า/ Goods Reccived by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p><br>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้ส่งสินค้า/ Delivery by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                    </div>
                                </td>
                                <td style="font-size: 16px;width:35%;height:200px;border-right: 1px solid #000;line-height: 1.5;font-weight: bold;">
                                    <div>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp; &#9744;&nbsp;&nbsp;เงินสด&nbsp;&nbsp;&nbsp;&nbsp;&#9744;&nbsp;&nbsp;เช็ค </p>
                                        <p>&nbsp;&nbsp;เช็คธนาคาร______________สาขา_____________</p>
                                        <p>&nbsp;&nbsp;เลขที่__________________วันที่____/____/____</p>
                                        <p>&nbsp;&nbsp;จำนวนเงิน________________________________</p>
                                        <p>&nbsp;&nbsp;ภาษีหัก ณ ที่จ่าย__________________________</p><br>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;____________________&nbsp;&nbsp;____/____/____</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับเงิน/Collector&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                    </div>
                                </td>
                                <td style="width:30%;text-align: center;">
                                    <div>
                                        <div style="text-align: left;">
                                            <p style="font-size: 12px;">ในนาม&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                                        </div>
                                        <p style="font-weight: bold;margin-left:5px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $taxInvoice->Branches->business_name . '</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับมอบอำนาจ/ Authorized Singature</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้ตรวจเช็คสินค้า/ Approved by</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                </div>';
            $mpdf->WriteHTML($html);

            $mpdf->AddPage();

            $html_copy = '
                <div style="position: absolute;top:40px;left:30px;">
                    <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
                </div>

                <div style="position: absolute;top:36px;left:150px;">
                    <h2>' . $branche_Name . '</h2>
                    <p style="font-size: 20px;">' . $branche_address . '</p>
                    <p style="font-size: 20px;">' . $branche_phone . '</p>
                    <p style="font-size: 20px;">เลขประจำตัวผู้เสียภาษี : &nbsp;&nbsp;' . $branche_tax . '&nbsp;&nbsp;&nbsp;&nbsp;' . $Bheadqurter . ' </p>
                </div>
                <div style="position: absolute;top:95px;right:40px;text-align:center;line-height: 1.7;">
                    <p style="font-size: 24px;background-color: #C0DCC0  ;text-align:center; font-weight: bold;padding:2px 30px -8px 30px;border-radius:20px;"><strong>ใบเสร็จรับเงิน/ใบกำกับภาษี</strong></p>
                    <p style="font-size: 20px;margin-top:3px;"><strong>สำเนา</strong></p>
                </div>

                <div style="position: absolute; left: 38px;top:193px; width: 42.6%;padding-left: 15px;line-height: 1; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
                    <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                        <p style="font-size: 18px;">ลูกค้า:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $taxInvoice->customer_no . '</p>
                        <p style="font-size: 12px;">customer</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;font-weight: bold;">
                        <p style="font-size: 16px;">' . $taxInvoice->customer_name . '</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;font-weight: bold; height: 50px;">
                        <p style="font-size: 16px;">' . $taxInvoice->customer_address . ' ' . $taxInvoice->customer_address2 . '
                        </p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">โทร. <strong>' . $taxInvoice->customer_phone . '</strong></p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">เลขประจำตัวผู้เสียภาษี &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $taxInvoice->customer_tax_id . '</strong></p>
                        <p style="font-size: 12px;">Tax ID</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">อ้างอิง</p>
                        <p style="font-size: 12px;">Reference</p>
                    </div>
                </div>
                <div style="position: absolute;top:295px;left:270px;font-size: 16px;">
                    <div>
                        <p style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $chack . '</p>
                    </div>
                </div>
                <div style="position: absolute;top:312px;left:295px;">
                    <label>สำนักงานใหญ่</label>
                </div>';
            $html_copy .= '

                <div style="position: absolute; right: 38px;top:193px; width: 42.6%;padding-left: 20px;line-height: 0.7; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
                    <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                        <p style="font-size: 18px;">เลขที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . (isset($taxInvoice->TaxInvoiceAllNos) && count($taxInvoice->TaxInvoiceAllNos) > 0 ? $taxInvoice->TaxInvoiceAllNos[count($taxInvoice->TaxInvoiceAllNos)-1]->no : '') . '</strong> </p>
                        <p style="font-size: 12px;">No.</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $taxInvoice->date . '</strong></p>
                        <p style="font-size: 12px;">Date</p>
                    </div>

                    <div style="position: absolute;margin-top:30px;">
                        <p style="font-size: 16px;">เลขที่ใบสั่งขาย</p>
                        <p style="font-size: 12px;">Sales Order No.</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">พนักงานขาย &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-</p>
                        <p style="font-size: 12px;">Salesman</p>
                    </div>
                    <div style="position: absolute;margin-top:-13px;">
                        <p style="font-size: 16px;">เขตการขาย</p>
                        <p style="font-size: 12px;">Territory</p>
                    </div>
                </div>

                <div style="position: absolute;top:720px;left:49px;width:405px;height:120px;">
                    <p style="margin: 0px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $shortRemark . '</p>
                </div>

                <div style="position: absolute;top:344px;margin-top:45px;left:39.5px;width:717px;border: 0.5px solid #000;border-top-left-radius: 7px;border-top-right-radius: 7px;background-color: #C0DCC0"></div>
                <div style="position: absolute;top:836.7px;margin-top:28.7px;left:39.5px;width:422px;border-bottom-left-radius: 7px;background-color: #C0DCC0"></div>
                <div style="position: absolute;top:836.7px;margin-top:28.7px;right:35.5px;width:125.4px;border-bottom-right-radius: 7px;background-color: #C0DCC0"></div>
                <div style="position: absolute;margin-top:350px;left:39px;width:717px;border: 1px solid #000;border-radius: 7px;">
                    <table style="width: 100%;border-collapse: collapse;">
                        <thead>
                            <tr>
                                <td style="width: 5%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-right: 10px;overflow: hidden;">ลำดับ<p style="font-size: 14px;">No.</p></td>
                                <td style="width: 40%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">รหัสสินค้า/รายละเอียด<br><p style="font-size: 16px;">Code/Descriptions</p></td>
                                <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">จำนวน<p style="font-size: 16px;">Quantity</p></td>
                                <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">หน่วยละ<p style="font-size: 16px;">Unit Price</p></td>
                                <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">ส่วนลด<p style="font-size: 16px;">Discount</p></td>
                                <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-right: 10px;overflow: hidden;border-right:none;">จำนวนเงิน<p style="font-size: 16px;">Amount</p></td>
                            </tr>
                        </thead>';
                $startIndex = $page * $itemsPerPage;
                $endIndex = min($startIndex + $itemsPerPage, $totaltaxInvoice);

                $catpage = 1;
                $i = 0;

                for ($lineindex = $startIndex; $lineindex < $endIndex; $lineindex++, $i++) {

                    $data = $taxInvoice['taxInvoiceService'][$i];
                    $servicename = $data->service_name;
                    $maxLengthPerLine = 48; // กำหนดจำนวนอักขระต่อบรรทัด

                    // ตัด $shortservicename เป็นหลายบรรทัด
                    $serviceLines = [];
                    while (mb_strlen($servicename, 'UTF-8') > 0) {
                        $serviceLines[] = htmlspecialchars(mb_substr($servicename, 0, $maxLengthPerLine, 'UTF-8'));
                        $servicename = mb_substr($servicename, $maxLengthPerLine, null, 'UTF-8'); // แก้ไขให้ถูกต้อง
                    }
                    foreach ($serviceLines as $lineindexs => $line) {
                        if ($lineindexs == 0) {

                            // บรรทัดแรก แสดงข้อมูลปกติ
                            $html_copy .= '
                                                <tbody>
                                                <tr>
                                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $counters . '</td>
                                                    <td style="text-align: left;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: auto;padding-left:10px;">' . $line . '</td>
                                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $data->qty . '</td>
                                                    <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->unit_price, 2) . '</td>
                                                    <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->discount, 2) . '</td>
                                                    <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">' . number_format($data->service_price, 2) . '</td>
                                                </tr>';
                            $counters++;
                            $catpage++;

                        } else {
                            $catpage++;
                            // บรรทัดที่เหลือขึ้น <tr> ใหม่ และช่องอื่นๆเว้นว่าง
                            $html_copy .= '
                                                <tr>
                                                    <td style="text-align: center;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                                    <td style="text-align: left;border-right: 1px solid #000;width: auto;height: auto;padding-left:10px;">' . $line . '</td>
                                                    <td style="text-align: center;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                                    <td style="text-align: right;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">&nbsp;</td>
                                                    <td style="text-align: right;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">&nbsp;</td>
                                                    <td style="text-align: right;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">&nbsp;</td>
                                                </tr>';

                        }
                    }
                }

            $emptyRowsNeeded = $rowsPerPage - ($catpage % $rowsPerPage);
            if ($emptyRowsNeeded == $rowsPerPage) {
                $emptyRowsNeeded = 0;  // ไม่ต้องเพิ่มแถวว่าง
            }

            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html_copy .= '
                                    <tr>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                        <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;border-right:none;">&nbsp;</td>
                                    </tr>
                                    ';
                $lineindex++;
                $catpage++;
            }

            $html_copy .= '


                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                            <p style="font-size: 12px;"></p>
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">รวมเป็นเงิน</p>
                                                <p style="font-size: 12px;">Gross Amount</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($grandTotal, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="text-align: left;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">

                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">หักส่วนลด</p>
                                                <p style="font-size: 12px;">Less Discount</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($lessdicount, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>';
            $afterdis = $grandTotal - $lessdicount;
            $html_copy .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">ยอดหลังหักส่วนลด</p>
                                                <p style="font-size: 12px;">After Discount</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($afterdis, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">หักเงินมัดจำ</p>
                                                <p style="font-size: 12px;">Less Deposit</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>0.00</p>
                                            </div>
                                        </td>
                                    </tr>';
            $afterdeposit = $afterdis - 0;
            $html_copy .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">ยอดหลังหักเงินมัดจำ</p>
                                                <p style="font-size: 12px;">After Deposit</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($afterdeposit, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>';

            // if($data->taxinvoice->withholding_tax == 'Y'){
            //     $withholding_tax = number_format(($data->taxinvoice->total_amount - $data->taxinvoice->tax)*0.03,2);
            // }else{
            //     $withholding_tax = "-";
            // }
            // $html_copy .= '
            //                         <tr>
            //                             <td colspan="3" style="text-align: center;padding-bottom:-10px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
            //                                 <p style="font-size: 12px;">&nbsp;&nbsp;ผิด ตก ยกเว้น E. & O.E. *** &nbsp;สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน&nbsp;***</p>
            //                             </td>
            //                             <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
            //                                 <div style="line-height: 0.3;padding-bottom:1px;">
            //                                     <p style="font-size: 18px;">จำนวนหัก ณ ที่จ่าย 3.00  %</p>
            //                                     <p style="font-size: 12px;">VAT</p>
            //                                 </div>
            //                             </td>

            //                             <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
            //                                 <div>
            //                                     <p>' . $withholding_tax. '</p>
            //                                 </div>
            //                             </td>
            //                         </tr>';  
            $aftervat = $data->taxinvoice->tax;
            $html_copy .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;padding-bottom:-10px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <p style="font-size: 12px;">&nbsp;&nbsp;ผิด ตก ยกเว้น E. & O.E. *** &nbsp;สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน&nbsp;***</p>
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">จำนวนภาษีมูลค่าเพิ่ม     7.00  %</p>
                                                <p style="font-size: 12px;">VAT</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($aftervat, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>';
            // if($data->taxinvoice->withholding_tax == 'Y'){
            //     $total = $data->taxinvoice->total_amount - $withholding_tax;
            // }else{
            //     $total = $data->taxinvoice->total_amount;
            // }

            $total = $data->taxinvoice->total_amount;
            $html_copy .= '
                                    <tr>
                                        <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                            <p style="font-size: 18px;">(' . $this->convertToThaiText($total) . ')</p>
                                        </td>
                                        <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                            <div style="line-height: 0.3;padding-bottom:1px;">
                                                <p style="font-size: 18px;">จำนวนเงินรวมทั้งสิ้น</p>
                                                <p style="font-size: 12px;">Total Invoice</p>
                                            </div>
                                        </td>

                                        <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 20px;">
                                            <div>
                                                <p>' . number_format($total, 2) . '</p>
                                            </div>
                                        </td>
                                    </tr>

                                ';
            $html_copy .= '
                        </tbody>
                    </table>
                </div>

                <div style="position: absolute;top:900px;left:39px;width:717px;height:190px;border: 1px solid #000;border-radius: 7px;">
                        <table style="width: 100%;border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 18px;width:35%;height:200px;border-right: 1px solid #000;">
                                    <div style="text-align: center;">
                                        <p style="font-size: 18px;font-weight: bold;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ได้รับสินค้าตามรายการถูกต้องแล้ว</p><br>
                                    </div>
                                    <div>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p style="font-size: 18px;">&nbsp;ผู้รับสินค้า/ Goods Reccived by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p><br>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้ส่งสินค้า/ Delivery by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                    </div>
                                </td>
                                <td style="font-size: 16px;width:35%;height:200px;border-right: 1px solid #000;line-height: 1.5;font-weight: bold;">
                                    <div>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp; &#9744;&nbsp;&nbsp;เงินสด&nbsp;&nbsp;&nbsp;&nbsp;&#9744;&nbsp;&nbsp;เช็ค </p>
                                        <p>&nbsp;&nbsp;เช็คธนาคาร______________สาขา_____________</p>
                                        <p>&nbsp;&nbsp;เลขที่__________________วันที่____/____/____</p>
                                        <p>&nbsp;&nbsp;จำนวนเงิน________________________________</p>
                                        <p>&nbsp;&nbsp;ภาษีหัก ณ ที่จ่าย__________________________</p><br>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;____________________&nbsp;&nbsp;____/____/____</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับเงิน/Collector&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                    </div>
                                </td>
                                <td style="width:30%;text-align: center;">
                                    <div>
                                        <div style="text-align: left;">
                                            <p style="font-size: 12px;">ในนาม&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                                        </div>
                                        <p style="font-weight: bold;margin-left:5px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $taxInvoice->Branches->business_name . '</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับมอบอำนาจ/ Authorized Singature</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้ตรวจเช็คสินค้า/ Approved by</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                </div>';
            $mpdf->WriteHTML($html_copy);
        }
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
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
