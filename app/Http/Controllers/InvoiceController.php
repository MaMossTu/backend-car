<?php
namespace App\Http\Controllers;

use App\Models\Branches;
use App\Models\Customers;
use App\Models\Doc;
use App\Models\DocCustomer;
use App\Models\Doc_transaction;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Quotation;
use App\Models\Services;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $invoices = Invoice::orderBy('id', 'desc')
        ->get();
        return $invoices;
    }
    public function index_unpaid()
    {
        $invoices = Invoice::where('status', '!=', 'paid')
            // ->where('type', '=', 'vat')
            ->orderBy('id', 'desc')
            ->get();
        return $invoices;
    }
    public function index_paid()
    {
        $invoices = Invoice::where('status', '=', 'paid')
            // ->where('type', '=', 'vat')
            ->orderBy('id', 'desc')
            ->get();
        return $invoices;
    }
    public function index_invoice()
    {
        $invoices = Invoice::select('invoices.*')
            // ->leftJoin('tax_invoices', 'invoices.id', '=', 'tax_invoices.invoice_id')
            ->leftJoin('tax_invoices', function($join) {
                $join->on('invoices.id', '=', 'tax_invoices.invoice_id')
                     ->where('tax_invoices.status', '!=', 'rejected');
            })
            ->whereNull('tax_invoices.id')
            ->where('invoices.status', '!=', 'cancel')
            ->where('invoices.type', '=', 'vat')
            ->orderBy('id', 'desc')
            ->get();
        return $invoices;
    }

    public function indexby(Request $request)
    {
        $type_vat    = $request->input('type', '');
        $customer_id = $request->input('customer_id', '');

        $quotations = Invoice::when($type_vat, function ($query, $type_vat) {
            return $query->where('type', $type_vat);
        })->when($customer_id, function ($query, $customer_id) {
            return $query->where('customer_id', $customer_id);
        })->get();

        // if ($type_vat) {
        //     $quotations = $quotations->where('type', $type_vat);
        // }
        // if ($customer_id) {
        //     $quotations = $quotations->where('customer_name', $customer_id);
        // }
        return $this->returnSuccess('Success', $quotations);
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
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make(request()->all(), [
            'branche_id'   => 'required|integer',
            'quotation_id' => 'required|integer',
            'date'         => 'required|date',
            'due_date'     => 'required|date',
            'quantity'     => 'numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $quotation = Quotation::where('id', $request->quotation_id)->with([
                'Customers.Customer_Addresses',
                'quotationServices.service',
            ])->first();
            if (! $quotation) {
                return $this->returnErrorData('ไม่พบข้อมูลใบเสนอราคา', 404);
            }
            if ($quotation->status != 'approved') {
                return $this->returnErrorData('ใบเสนอราคายังไม่อนุมัติหรือถูกสร้างไปแล้ว', 404);
            }

            $quotation->status = 'create';
            $quotation->save();

            $customer = $quotation->Customers;

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj  = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address         = $addressObj->address ?? null;
                $provinceName    = $addressObj->provinces->name_th_prefix ?? null;
                $districtName    = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode      = $addressObj->zip_code ?? null;
            } else {
                $address         = null;
                $provinceName    = null;
                $districtName    = null;
                $subdistrictName = null;
                $postalcode      = null;
            }

            $invoice               = new Invoice();
            $invoice->branche_id   = $request->branche_id;
            $invoice->quotation_id = $request->quotation_id;

            $invoice->customer_no             = $customer->no;
            $invoice->customer_name           = $customer->name . (isset($customer->lname) ? ' ' . $customer->lname : '');
            $invoice->customer_id             = $customer->id;
            $invoice->customer_address        = $address ?? '';
            $invoice->customer_address2       = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $invoice->customer_phone          = $customer->phone_number1 ?? '';
            $invoice->customer_email          = $customer->email ?? '';
            $invoice->customer_tax_id         = $customer->tax_id ?? '';
            $invoice->customer_is_headquarter = $customer->is_headquarter;

            if ($quotation->type == 'vat') {
                $year  = date('y') + 43; // เลขท้ายปีพ.ศ.
                $month = date('m');      // เดือนปัจจุบัน
                $count = Invoice::whereYear('date', date('Y'))
                    ->where('branche_id', $request->branche_id)
                    ->whereMonth('date', date('m'))
                    ->whereRaw("no REGEXP '^IV" . $year . $month . "/[0-9]{3}$'")
                    ->where('type', 'vat')
                    ->count() + 1; // จำนวน

                $no = sprintf("IV%s%s/%03d", $year, $month, $count);
            } else {
                $year  = date('y') + 43; // เลขท้ายปีพ.ศ.
                $month = date('m');      // เดือนปัจจุบัน
                $count = Invoice::whereYear('date', date('Y'))
                    ->where('branche_id', $request->branche_id)
                    ->whereMonth('date', date('m'))
                    ->whereRaw("no REGEXP '^NOIV" . $year . $month . "/[0-9]{3}$'")
                    ->where('type', 'nonvat')
                    ->count() + 1; // จำนวน

                $no = sprintf("NOIV%s%s/%03d", $year, $month, $count);
            }

            $invoice->no       = $no;
            $invoice->date     = $request->date;
            $invoice->due_date = $request->due_date;
            $invoice->remark   = $request->remark;
            $invoice->withholding_tax = $request->withholding_tax;
            $invoice->withholding_tax_percent = $request->withholding_tax_percent;
            $invoice->total        = $quotation->total;
            $invoice->discount     = $quotation->discount;
            $invoice->tax          = $quotation->tax;
            $invoice->total_amount = $quotation->total_amount;
            $invoice->type         = $quotation->type;
            $invoice->withholding_tax = $request->withholding_tax;
            $invoice->withholding_tax_percent = $request->withholding_tax_percent;
            $invoice->save();

            foreach ($quotation->quotationServices as $service) {

                $invoices                = new InvoiceService();
                $invoices->invoice_id    = $invoice->id;
                $invoices->service_id    = $service->service_id;
                $invoices->service_name  = $service->service->name;
                $invoices->qty           = $service->qty;
                $invoices->unit_price    = $service->unit_price;
                $invoices->discount      = $service->discount;
                $invoices->service_price = $service->service_price;
                $invoices->save();
            }

            DB::commit();
            // Log
            $userId      = $loginBy->id;
            $type        = 'เพิ่มข้อมูล ใบแจ้งหนี้';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $invoice->id;
            $this->Log($userId, $description, $type);
            // Return success response

            return isset($transaction) ? $this->returnSuccess('Data saved successfully', ['transaction' => $transaction, 'invoice' => $invoice]) : $this->returnSuccess('Data saved successfully', ['invoice' => $invoice]);
        } catch (\Throwable $e) {
            // Rollback transaction in case of an error
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    /**
     * Display the specified resource.
     */
    public function showID($id)
    {
        $invoice = Invoice::with([
            'Branches',
            'quotation',
            'invoiceServices',
            'billingInvoice',
            'invoicePayment',
        ])->find($id);
        if (! $invoice) {
            return $this->returnBadRequest('ไม่พบข้อมูล');
        } else {
            return $this->returnSuccess('Success', $invoice);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make(request()->all(), [
            'branche_id'   => 'required|integer',
            'quotation_id' => 'required|integer',
            'date'         => 'required|date',
            'due_date'     => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            // ค้นหาใบแจ้งหนี้ที่ต้องการอัปเดต
            $invoice = Invoice::find($id);
            if (! $invoice) {
                return $this->returnErrorData('ไม่พบข้อมูลใบแจ้งหนี้', 404);
            }
            $invoice->status = 'cancel';
            $invoiceNo       = $invoice->no;
            $invoice->save();

            $quotation = Quotation::where('id', $request->quotation_id)->with([
                'Customers.Customer_Addresses',
                'quotationServices.service',
            ])->first();
            if (! $quotation) {
                return $this->returnErrorData('ไม่พบข้อมูลใบเสนอราคา', 404);
            }
            $customer = $quotation->Customers;

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj  = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address         = $addressObj->address ?? null;
                $provinceName    = $addressObj->provinces->name_th_prefix ?? null;
                $districtName    = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode      = $addressObj->zip_code ?? null;
            } else {
                $address         = null;
                $provinceName    = null;
                $districtName    = null;
                $subdistrictName = null;
                $postalcode      = null;
            }

            $existingInvoice = Invoice::where('no', 'like', $invoice->no . '%')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingInvoice) {
                $parts = explode('/', $existingInvoice->no);

                if (count($parts) == 2) {
                    // (BI6712/001 -> BI6712/001/01)
                    $newNo = $invoice->no . '/01';
                } else {
                    // (BI6712/001/01 -> BI6712/001/02)
                    $currentSubNumber = intval($parts[2]);
                    $newSubNumber     = $currentSubNumber + 1;
                    $newNo            = $parts[0] . '/' . $parts[1] . '/' . sprintf('%02d', $newSubNumber);
                }
            }

            $invoice                          = new Invoice();
            $invoice->no                      = $invoiceNo;
            $invoice->branche_id              = $request->branche_id;
            $invoice->quotation_id            = $request->quotation_id;
            $invoice->customer_no             = $customer->no;
            $invoice->customer_name           = $customer->name;
            $invoice->customer_id             = $customer->id;
            $invoice->customer_address        = $address ?? '';
            $invoice->customer_address2       = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $invoice->customer_phone          = $customer->phone_number1 ?? '';
            $invoice->customer_email          = $customer->email;
            $invoice->customer_tax_id         = $customer->tax_id ?? '';
            $invoice->customer_is_headquarter = $customer->is_headquarter;
            $invoice->date                    = $request->date;
            $invoice->due_date                = $request->due_date;
            $invoice->remark                  = $request->remark;

            $invoice->total        = $quotation->total;
            $invoice->discount     = $quotation->discount;
            $invoice->tax          = $quotation->tax;
            $invoice->total_amount = $quotation->total_amount;
            $invoice->type         = $quotation->type;
            $invoice->withholding_tax = $request->withholding_tax;
            $invoice->withholding_tax_percent = $request->withholding_tax_percent;
            $invoice->save();

            InvoiceService::where('invoice_id', $invoice->id)->delete();

            foreach ($quotation->quotationServices as $service) {
                $invoices                = new InvoiceService();
                $invoices->invoice_id    = $invoice->id;
                $invoices->service_name  = $service->service->name;
                $invoices->qty           = $service->qty;
                $invoices->unit_price    = $service->unit_price;
                $invoices->discount      = $service->discount;
                $invoices->service_price = $service->service_price;
                $invoices->save();
            }

            // บันทึก Log การอัปเดตข้อมูล
            $userId      = $loginBy->id;
            $type        = 'แก้ไขข้อมูล ใบแจ้งหนี้';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $invoice->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data updated successfully', $invoice);
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
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        try {
            $invoice = Invoice::find($id);
            if (! $invoice) {
                return $this->returnBadRequest('invoice not found');
            }
            $invoice->invoiceServices()->delete();
            $invoice->delete();

            $userId      = $loginBy->id;
            $type        = 'ลบข้อมูล ใบแจ้งหนี้';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('ลบข้อมูลสำเร็จ', $invoice);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    public function getPage(Request $request)
    {
        $perPage   = $request->input('length', 10);
        $page      = $request->input('start', 0);
        $search    = $request->input('search.value', "");
        $sortField = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', 1);

        $search_status = $request->input('search_status', "");
        $type          = $request->input('type', "");
        $startOfMonth  = $request->input('startofmonth', "");
        $endOfMonth    = $request->input('endofmonth', "");
        $branche_id    = $request->input('branche_id', "");

        $direction = 'DESC';
        if ($sortOrder == -1) {
            $direction = 'ASC';
        }

        $seaclable = ['no', 'customer_name'];

        $Invoice = Invoice::with(
            'Branches',
            'quotation',
            'invoiceServices',
            'invoicePayment',
        )
            ->where(
                function ($query) use ($search, $seaclable, $search_status, $startOfMonth, $endOfMonth, $type, $branche_id) {
                    if ($search) {
                        foreach ($seaclable as &$s) {
                            $query->orWhere($s, 'LIKE', '%' . $search . '%');
                        }
                    }
                    if ($search) {
                        $query->where('no', 'LIKE', '%' . $search . '%')
                            ->orWhere('customer_name', 'LIKE', '%' . $search . '%')
                            ->orWhereHas('quotation', function ($q) use ($search) {
                                $q->where('no', 'LIKE', '%' . $search . '%');
                            });
                    }
                    if (!empty($search_status)) {
                        $query->where('status', $search_status);
                    }
                    if (!empty($startOfMonth) && ! empty($endOfMonth)) {
                        $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
                    }
                    if (!empty($type)) {
                        $query->where('type', $type);
                    }
                    if (!empty($branche_id)) {
                        $query->where('branche_id', $branche_id);
                    }
                }
            );

        $Invoice = $Invoice
            ->orderBy($sortField, $direction)
            ->paginate($perPage, ['*'], 'page', ($page / $perPage) + 1);

        return $this->returnSuccess('Success', $Invoice);
    }

    public function update_status(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $Invoice = Invoice::with('invoiceServices.Services.Doc', 'Customers')->find($id);
        if (! $Invoice) {
            return $this->returnBadRequest('quotation not found');
        }

        try {
            $Invoice->status = $request->status;
            $Invoice->save();

            if ($Invoice->status == 'paid' && $Invoice->is_stock == 0) {
                $customer   = $Invoice->Customers;
                $branche_id = $Invoice->branche_id;
                foreach ($Invoice->invoiceServices as $service) {
                    if ($service->services->doc_id != null) {

                        $docId      = $service->services->doc_id;
                        $qty        = $service->qty;
                        $unit_price = $service->unit_price;
                        $doc        = Doc::where('id', $docId)->first();
                        if ($doc) {
                            if ($customer && $customer->type === 'agent') {
                                $stock = 0;
                                $stock = Doc_transaction::where('branch_id', $branche_id)
                                    ->where(function ($query) {
                                        $query->where('deleted_at', null);
                                    })
                                    ->where(function ($query) use ($docId) {
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
                                if ($stock == null) {
                                    $stock = 0;
                                }
                                $doc_tran                   = new Doc_transaction;
                                $doc_tran->doc_id           = $docId;
                                $doc_tran->branch_id        = $branche_id;
                                $doc_tran->date             = $request->date ?? date('Y-m-d H:i:s');
                                $doc_tran->transaction_type = 'Withdraw';
                                $doc_tran->stock            = $stock;
                                $doc_tran->quantity         = $qty;
                                $doc_tran->balance          = $stock - $qty;
                                $doc_tran->save();

                                $doc_cus = DocCustomer::where('customer_id', $customer->id)
                                    ->where('docs_id', $docId)
                                    ->first();
                                if (! $doc_cus) {
                                    // Create new DocCustomer record
                                    $doc_cus              = new DocCustomer();
                                    $doc_cus->docs_id     = $docId;
                                    $doc_cus->customer_id = $customer->id;
                                    $doc_cus->total_qty   = $qty ?? 0;
                                    $doc_cus->price       = $unit_price ?? 0;
                                    $doc_cus->save();

                                    $doc_tran                   = new Doc_transaction;
                                    $doc_tran->doc_customer_id  = $doc_cus->id;
                                    $doc_tran->date             = $request->date ?? date('Y-m-d H:i:s');
                                    $doc_tran->transaction_type = 'Deposit';
                                    $doc_tran->stock            = $doc_cus->total_qty;
                                    $doc_tran->quantity         = $qty;
                                    $doc_tran->balance          = $stock + $qty;
                                    $doc_tran->save();

                                } else {
                                    // Update existing DocCustomer record
                                    $doc_tran                   = new Doc_transaction;
                                    $doc_tran->doc_customer_id  = $doc_cus->id;
                                    $doc_tran->date             = $request->date ?? date('Y-m-d H:i:s');
                                    $doc_tran->transaction_type = 'Deposit';
                                    $doc_tran->stock            = $doc_cus->total_qty;
                                    $doc_tran->quantity         = $qty ?? 0;
                                    $doc_tran->balance          = ($doc_cus->total_qty) + $qty;
                                    $doc_tran->save();

                                    $doc_cus->total_qty += $qty ?? 0;
                                    $doc_cus->save();
                                }

                                $Invoice->is_stock = 1;
                                $Invoice->save();
                            }
                        }
                    }
                }
            }

            // Log
            $userId      = $loginBy->id;
            $type        = 'อัปเดตสถานะ ใบแจ้งหนี้';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $Invoice->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $Invoice);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    public function pdf(Request $request, $id)
    {
        $Invoice = Invoice::where('id', $id)
            ->with([
                'Branches.Provinces',
                'quotation',
                'invoiceServices',
                'invoicePayment',
                'billingInvoice',

            ])->first();

        if (! $Invoice) {
            return response()->json(['message' => 'ไม่พบข้อมูล id ที่ต้องการ']);
        }
        // dd($Invoice['invoiceServices']);
        // return $Invoice;
        switch ($Invoice->customer_is_headquarter) {
            case 1:
                $headqurter = "สำนักงานใหญ่";
                $chack      = "&#9745;";
                break;
            case 0;
                $headqurter = "";
                $chack      = "&#9744;";
                break;
            default:
                $headqurter = "";
                $chack      = "&#9744;";
                break;
        }

        switch ($Invoice->Branches->is_headquarter) {
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
        $remark = $Invoice->remark ?? '';

        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->first();
            if (! $branche) {
                return $this->returnError('Branches Not Found');
            }
            $branche_Name           = $branche->business_name ?? null;
            $branche_address        = $branche->address ?? null;
            $branche_open_date_time = $branche->open_close_time ?? null;
            $branche_open_date      = $branche->open_date ?? null;
            $branche_phone          = $branche->phone ?? null;
            $branche_tax            = $branche->tax_id ?? null;
            $branche_image          = $branche->logo ?? null;
            $branche_logo           = $branche->business_name ?? null;
        } else {
            $branche_Name           = $Invoice->Branches->business_name ?? null;
            $branche_address        = $Invoice->Branches->address ?? null;
            $branche_open_date_time = $Invoice->Branches->open_close_time ?? null;
            $branche_open_date      = $Invoice->Branches->open_date ?? null;
            $branche_phone          = $Invoice->Branches->phone ?? null;
            $branche_tax            = $Invoice->Branches->tax_id ?? null;
            $branche_image          = $Invoice->Branches->logo ?? null;
            $branche_logo           = $Invoice->Branches->business_name ?? null;
        }

        $footvat = '';
        foreach ($Invoice['invoiceServices'] as $data) {
            $grandTotal   = $data->invoice->total;
            $lessdicount  = $data->invoice->discount;
            $afterdis     = $grandTotal - $lessdicount;
            $afterdeposit = $afterdis - 0;
            $aftervat     = $data->invoice->tax;
            $total        = $data->invoice->total_amount;
        }

        
        if ($Invoice->type === 'vat') {
            if($Invoice->withholding_tax == "Y"){
                $wt = '<tr>
                                    <td colspan="3" style="text-align: center;padding-bottom:1px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <p style="font-size: 12px;">ผิด ตก ยกเว้น E. & O.E.</p>
                                    </td>
                                    <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div style="line-height: 0.3;padding-bottom:1px;">
                                            <p style="font-size: 18px;">จำนวนภาษีหัก ณ ที่จ่าย 3.00 %</p>
                                            <p style="font-size: 12px;">Withholding Tax</p>
                                        </div>
                                    </td>

                                    <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div>
                                            <p>' . number_format((($total-$aftervat)*0.03), 2) . '</p>
                                        </div>
                                    </td>
                                </tr>';
                                // $total = $total - (($total-$aftervat)*0.03); // ลูกค้าไม่ต้องการให้คำนวน หัก ณ ที่จ่าย

            } else{
                $wt = '<tr>
                                    <td colspan="3" style="text-align: center;padding-bottom:1px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <p style="font-size: 12px;">ผิด ตก ยกเว้น E. & O.E.</p>
                                    </td>
                                    <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div style="line-height: 0.3;padding-bottom:1px;">
                                            <p style="font-size: 18px;">จำนวนภาษีหัก ณ ที่จ่าย 3.00 %</p>
                                            <p style="font-size: 12px;">Withholding Tax</p>
                                        </div>
                                    </td>

                                    <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div>
                                            <p>-</p>
                                        </div>
                                    </td>
                                </tr>';
            }
            $wt = ""; // ลูกค้าไม่ต้องการให้แสดง หัก ณ ที่จ่าย
            $itemsPerPage = 8;
            $footvat .= '
                                <tr>
                                    <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                        <p style="font-size: 12px;">ใบเสร็จรับเงินฉบับนี้จะสมบูรณ์ต่อเมื่อมีลายเซ็นผู้รับมอบอำนาจและลายเซ็นผู้รับเงิน และได้เรียกเก็บเงินตามเช็คเรียบร้อยแล้ว</p>
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
                                    <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
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
                                </tr>
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
                                </tr>
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
                                </tr>
                                '.$wt.'
                                <tr>
                                    <td colspan="3" style="text-align: center;padding-bottom:1px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <p style="font-size: 12px;">ผิด ตก ยกเว้น E. & O.E.</p>
                                    </td>
                                    <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div style="line-height: 0.3;padding-bottom:1px;">
                                            <p style="font-size: 18px;">จำนวนภาษีมูลค่าเพิ่ม 7.00 %</p>
                                            <p style="font-size: 12px;">VAT</p>
                                        </div>
                                    </td>

                                    <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div>
                                            <p>' . number_format($aftervat, 2) . '</p>
                                        </div>
                                    </td>
                                </tr>
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
                                </tr>';
        } else {
            $itemsPerPage = 8;
            $footvat .= '
                                <tr>
                                    <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                        <p style="font-size: 12px;">ใบเสร็จรับเงินฉบับนี้จะสมบูรณ์ต่อเมื่อมีลายเซ็นผู้รับมอบอำนาจและลายเซ็นผู้รับเงิน และได้เรียกเก็บเงินตามเช็คเรียบร้อยแล้ว</p>
                                    </td>
                                    <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;width: auto;height: 20px;">
                                        <div style="line-height: 0.3;padding-bottom:1px;">
                                            <p style="font-size: 15px;">&nbsp;</p>
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
                                    <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    </td>
                                    <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div style="line-height: 0.3;padding-bottom:1px;">
                                            <p style="font-size: 5px;">&nbsp;</p>
                                            <p style="font-size: 18px;">หักส่วนลด</p>
                                            <p style="font-size: 12px;">Less Discount</p>
                                        </div>
                                    </td>

                                    <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div>
                                            <p>' . number_format($lessdicount, 2) . '</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    </td>
                                    <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div style="line-height: 0.3;padding-bottom:1px;">
                                            <p style="font-size: 5px;">&nbsp;</p>
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
                                            <p style="font-size: 5px;">&nbsp;</p>
                                            <p style="font-size: 18px;">หักเงินมัดจำ</p>
                                            <p style="font-size: 12px;">Less Deposit</p>
                                        </div>
                                    </td>

                                    <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div>
                                            <p>0.00</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <p style="font-size: 12px;">ผิด ตก ยกเว้น E. & O.E.</p>
                                    </td>
                                    <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div style="line-height: 0.3;padding-bottom:1px;">
                                            <p style="font-size: 5px;">&nbsp;</p>
                                            <p style="font-size: 18px;">ยอดหลังหักเงินมัดจำ</p>
                                            <p style="font-size: 12px;">After Deposit</p>
                                        </div>
                                    </td>

                                    <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                        <div>
                                            <p>' . number_format($afterdeposit, 2) . '</p>
                                        </div>
                                    </td>
                                </tr>
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
                                </tr>';
        }
        $start = new DateTime($Invoice->date);
        $end   = new DateTime($Invoice->due_date);

        $totalday = $start->diff($end);
        $days     = $totalday->days;

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([

            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun Bold Italic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 16,
            'margin_bottom'     => 5,
            'margin_header'     => 0,
            'margin_footer'     => 0,
        ]);

        $mpdf->SetTitle('ใบแจ้งหนี้');

        $totalItems = count($Invoice['invoiceServices']);
        $pages      = ceil($totalItems / $itemsPerPage);

        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                // $mpdf->AddPage('L); สำหรับกระดาษแนวนอน
                $mpdf->AddPage();
            }

            $html = '
        <div style="position: absolute;top:40px;left:30px;">
            <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
        </div>

        <div style="position: absolute;top:30px;left:150px;">
            <h2>' . $branche_Name . '</h2>
            <p style="font-size: 20px;">' . $branche_address . '</p>
            <p style="font-size: 20px;">' . $branche_phone . '</p>
            <p style="font-size: 20px;">เลขประจำตัวผู้เสียภาษี : &nbsp;&nbsp;' . $branche_tax . '&nbsp;&nbsp;&nbsp;&nbsp;' . $Bheadqurter . ' </p>
        </div>
        <div style="position: absolute;top:85px;right:40px;text-align:center;line-height: 2;">
            <p style="font-size: 24px;background-color: #ca40ff  ;text-align:center; font-weight: bold;padding:2px 80px -8px 80px;border-radius:20px;"><strong>ใบแจ้งหนี้</strong></p>
            <p style="font-size: 20px;margin-top:5px;"><strong>ต้นฉบับ</strong></p>
        </div>

        <div style="position: absolute; left: 38px;top:185px; width: 42.6%;padding-left: 15px;line-height: 1; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
            <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                <p style="font-size: 18px;">ลูกค้า:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $Invoice->customer_no . '</p>
                <p style="font-size: 12px;">customer</p>
            </div>
            <div style="position: absolute;margin-top:-13px;font-weight: bold;">
                <p style="font-size: 16px;">' . $Invoice->customer_name . '</p>
            </div>
            <div style="position: absolute;margin-top:-13px;font-weight: bold; height: 50px;">
                <p style="font-size: 16px;">' . $Invoice->customer_address . ' ' . $Invoice->customer_address2 . '
                </p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
                <p style="font-size: 16px;">โทร. <strong>' . $Invoice->customer_phone . '</strong></p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
                <p style="font-size: 16px;">เลขประจำตัวผู้เสียภาษี &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Invoice->customer_tax_id . '</strong></p>
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

        <div style="position: absolute; right: 38px;top:185px; width: 42.6%;padding-left: 20px;line-height: 0.7; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
            <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                <p style="font-size: 18px;">เลขที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Invoice->no . '</strong> </p>
                <p style="font-size: 12px;">No.</p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
                <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $this->convertToThaiDate($Invoice->date) . '</strong></p>
                <p style="font-size: 12px;">Date</p>
            </div>
            <div style="position: absolute;margin-top: 6px;">
                <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $days . ' &nbsp;&nbsp;</p>
                <p style="font-size: 12px;">Credit&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
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
        <div style="position: absolute;top:259px;left:540px;line-height: 0.7;">
            <div>
                <p style="font-size: 16px;">วัน&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ครบกำหนด&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->convertToThaiDate($Invoice->due_date) . '</p>
                <p style="font-size: 12px;">days &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Due Date</p>
            </div>
        </div>

        <div style="position: absolute;top:336px;margin-top:45px;left:39.5px;width:717px;border: 0.5px solid #000;border-top-left-radius: 7px;border-top-right-radius: 7px;background-color:#ca40ff;"></div>
        <div style="position: absolute;top:828.7px;margin-top:28.7px;left:39.5px;width:430px;border-bottom-left-radius: 7px;background-color:#ca40ff;"></div>
        <div style="position: absolute;top:828.7px;margin-top:28.7px;right:35.5px;width:110px;border-bottom-right-radius: 7px;background-color:#ca40ff;"></div>
        <div style="position: absolute;top:380px;left:39px;width:717px;height:505px;border: 1px solid #000;border-radius: 7px;">
            <table style="width: 100%;border-collapse: collapse;">
                <thead>
                    <tr>
                        <td style="width: 5%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-left: 10px;overflow: hidden;">ลำดับ<p style="font-size: 14px;">No.</p></td>
                        <td style="width: 45%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">รหัสสินค้า/รายละเอียด<br><p style="font-size: 16px;">Code/Descriptions</p></td>
                        <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">จำนวน<p style="font-size: 16px;">Quantity</p></td>
                        <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">หน่วยละ<p style="font-size: 16px;">Unit Price</p></td>
                        <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">ส่วนลด<p style="font-size: 16px;">Discount</p></td>
                        <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-right:none;height: 30px;border-radius-right: 10px;overflow: hidden;">จำนวนเงิน<p style="font-size: 16px;">Amount</p></td>
                    </tr>
                </thead>';

            $service      = $Invoice['invoiceServices'];
            $totalInvoice = count($Invoice['invoiceServices']);
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {
                $data = $service[$i];
                // dd($data);
                $totalitem   = ($data->qty * $data->unit_price);
                $servicename = $data->service_name;
                $shortservicename = $data->service_name;
                                         // ตัดข้อความโดยใช้ substr หรือ mb_substr เพื่อรองรับ UTF-8
                $maxLength        = 120; // จำนวนตัวอักษรสูงสุด
                $maxLength2       = 80;
                $maxLengthPerLine = 40;

                // if (mb_strlen($servicename, 'UTF-8') > $maxLength) {
                //     $shortservicename = mb_strcut($servicename, 0, $maxLength, 'UTF-8');
                // } else if (mb_strlen($servicename, 'UTF-8') > $maxLengthPerLine) {
                //     $shortservicename = mb_strcut($servicename, 0, $maxLength2, 'UTF-8');
                // } else {
                //     $shortservicename = $servicename;
                // }

                $html .= '
                            <tr>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . ($i + 1) . '</td>
                                <td style="text-align: left;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-left:7px;">' . $shortservicename . '</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . $data->qty . '</td>
                                <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->unit_price, 2) . '</td>
                                <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->discount, 2) . '</td>
                                <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">' . number_format($totalitem, 2) . '</td>
                            </tr>';
            }

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                                <tr>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;border-right:none;">&nbsp;</td>
                                </tr>';
            }

            $html .= '
                                <tr>
                                    ' . $footvat . '
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="position: absolute;top:890px;left:39px;width:717px;height:190px;border: 1px solid #000;border-radius: 7px;">
                        <table style="width: 100%;border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 18px;width:35%;height:200px;border-right: 1px solid #000;">
                                    <div style="text-align: center;">
                                        <p style="font-size: 18px;font-weight: bold;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ได้รับสินค้าตามรายการถูกต้องแล้ว</p><br>
                                    </div>
                                    <div>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p style="font-size: 18px;">&nbsp;ผู้รับสินค้า/ Goods Reccived by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p><br>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้ส่งสินค้า/ Delivery by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                    </div>
                                </td>
                                <td style="font-size: 16px;width:35%;height:200px;border-right: 1px solid #000;line-height: 1.5;">
                                    <div>
                                        <p>o สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน</p>
                                        <p>o บริษัทฯ จะนำใบเสร็จรับเงินมาเรียกเก็บเงินในภายหลัง</p>
                                        <p>o บริษัทฯ จะคิดดอกเบี้ย 1.25% ต่อเดือน</p>
                                        <p>&nbsp;&nbsp;&nbsp;เมื่อชำระบิลเกินกำหนด</p>
                                        <p>o กรุณาชำระเงินด้วยเช็ค ในนามบริษัทฯ</p>
                                        <p>&nbsp;&nbsp;&nbsp;ขีดผู้ถือและขีดคล่อมเท่านั้น</p>
                                    </div>
                                </td>
                                <td style="width:30%;text-align: center;">
                                    <div>
                                        <div style="text-align: left;">
                                            <p style="font-size: 12px;">ในนาม&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                                        </div>
                                        <p style="font-weight: bold;margin-left:5px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $branche_Name . '</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับมอบอำนาจ/ Authorized Singature</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้ตรวจเช็คสินค้า/ Approved by</p>
                                    </div>
                                </td>
                            </tr>';
            $html .= '</table></div>
            <div style="position: absolute;top:720px;left:60px;width:390px;height:110px;">' . $remark . '</div>';

            $mpdf->WriteHTML($html);
            $mpdf->AddPage();

            $html_copy = '
        <div style="position: absolute;top:40px;left:30px;">
            <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
        </div>

        <div style="position: absolute;top:30px;left:150px;">
            <h2>' . $branche_Name . '</h2>
                        <p style="font-size: 20px;">' . $branche_address . '</p>

            <p style="font-size: 20px;">' . $branche_phone . '</p>
            <p style="font-size: 20px;">เลขประจำตัวผู้เสียภาษี : &nbsp;&nbsp;' . $branche_tax . '&nbsp;&nbsp;&nbsp;&nbsp;' . $Bheadqurter . ' </p>
        </div>
        <div style="position: absolute;top:85px;right:40px;text-align:center;line-height: 2;">
            <p style="font-size: 24px;background-color: #8ef8a1  ;text-align:center; font-weight: bold;padding:2px 80px -8px 80px;border-radius:20px;"><strong>ใบแจ้งหนี้</strong></p>
            <p style="font-size: 20px;margin-top:5px;"><strong>สำเนา</strong></p>
        </div>

        <div style="position: absolute; left: 38px;top:185px; width: 42.6%;padding-left: 15px;line-height: 1; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
            <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                <p style="font-size: 18px;">ลูกค้า:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $Invoice->customer_no . '</p>
                <p style="font-size: 12px;">customer</p>
            </div>
            <div style="position: absolute;margin-top:-13px;font-weight: bold;">
                <p style="font-size: 16px;">' . $Invoice->customer_name . '</p>
            </div>
            <div style="position: absolute;margin-top:-13px;font-weight: bold; height: 50px;">
                <p style="font-size: 16px;">' . $Invoice->customer_address . ' ' . $Invoice->customer_address2 . '
                </p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
                <p style="font-size: 16px;">โทร. <strong>' . $Invoice->customer_phone . '</strong></p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
                <p style="font-size: 16px;">เลขประจำตัวผู้เสียภาษี &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Invoice->customer_tax_id . '</strong></p>
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
        <div style="position: absolute; right: 38px;top:185px; width: 42.6%;padding-left: 20px;line-height: 0.7; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
            <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
                <p style="font-size: 18px;">เลขที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Invoice->no . '</strong> </p>
                <p style="font-size: 12px;">No.</p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
                <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $this->convertToThaiDate($Invoice->date) . '</strong></p>
                <p style="font-size: 12px;">Date</p>
            </div>
            <div style="position: absolute;margin-top: 6px;">
                <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $days . ' &nbsp;&nbsp;</p>
                <p style="font-size: 12px;">Credit&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
            </div>
            <div style="position: absolute;margin-top:-13px;">
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
        <div style="position: absolute;top:259px;left:540px;line-height: 0.7;">
            <div>
                <p style="font-size: 16px;">วัน&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ครบกำหนด&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->convertToThaiDate($Invoice->due_date) . '</p>
                <p style="font-size: 12px;">days &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Due Date</p>
            </div>
        </div>

        <div style="position: absolute;top:336px;margin-top:45px;left:39.5px;width:717px;border: 0.5px solid #000;border-top-left-radius: 7px;border-top-right-radius: 7px;background-color:#8ef8a1;"></div>
        <div style="position: absolute;top:828.7px;margin-top:28.7px;left:39.5px;width:430px;border-bottom-left-radius: 7px;background-color:#8ef8a1;"></div>
        <div style="position: absolute;top:828.7px;margin-top:28.7px;right:35.5px;width:110px;border-bottom-right-radius: 7px;background-color:#8ef8a1;"></div>
        <div style="position: absolute;top:380px;left:39px;width:717px;height:505px;border: 1px solid #000;border-radius: 7px;">
            <table style="width: 100%;border-collapse: collapse;">
                <thead>
                    <tr>
                        <td style="width: 5%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-left: 10px;overflow: hidden;">ลำดับ<p style="font-size: 14px;">No.</p></td>
                        <td style="width: 45%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">รหัสสินค้า/รายละเอียด<br><p style="font-size: 16px;">Code/Descriptions</p></td>
                        <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">จำนวน<p style="font-size: 16px;">Quantity</p></td>
                        <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">หน่วยละ<p style="font-size: 16px;">Unit Price</p></td>
                        <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">ส่วนลด<p style="font-size: 16px;">Discount</p></td>
                        <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-right:none;height: 30px;border-radius-right: 10px;overflow: hidden;border-right:none;">จำนวนเงิน<p style="font-size: 16px;">Amount</p></td>
                    </tr>
                </thead>';

            $service      = $Invoice['invoiceServices'];
            $totalInvoice = count($Invoice['invoiceServices']);
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {
                $data        = $service[$i];
                $totalitem   = ($data->qty * $data->unit_price);
                $servicename = $data->service_name;
                                         // ตัดข้อความโดยใช้ substr หรือ mb_substr เพื่อรองรับ UTF-8
                $maxLength        = 120; // จำนวนตัวอักษรสูงสุด
                $maxLength2       = 80;
                $maxLengthPerLine = 40;

                if (mb_strlen($servicename, 'UTF-8') > $maxLength) {
                    $shortservicename = mb_strcut($servicename, 0, $maxLength, 'UTF-8');
                } else if (mb_strlen($servicename, 'UTF-8') > $maxLengthPerLine) {
                    $shortservicename = mb_strcut($servicename, 0, $maxLength2, 'UTF-8');
                } else {
                    $shortservicename = $servicename;
                }

                $html_copy .= '
                            <tr>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . ($i + 1) . '</td>
                                <td style="text-align: left;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-left:7px;">' . $shortservicename . '</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . $data->qty . '</td>
                                <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->unit_price, 2) . '</td>
                                <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->discount, 2) . '</td>
                                <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">' . number_format($totalitem, 2) . '</td>
                            </tr>';
            }

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html_copy .= '
                                <tr>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                    <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;border-right:none;">&nbsp;</td>
                                </tr>';
            }

            $html_copy .= '
                                <tr>
                                    ' . $footvat . '
                                </tr>';
            $html_copy .= '
                    </tbody>
                    </table>
                </div>
                <div style="position: absolute;top:890px;left:39px;width:717px;height:190px;border: 1px solid #000;border-radius: 7px;">
                        <table style="width: 100%;border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 18px;width:35%;height:200px;border-right: 1px solid #000;">
                                    <div style="text-align: center;">
                                        <p style="font-size: 18px;font-weight: bold;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ได้รับสินค้าตามรายการถูกต้องแล้ว</p><br>
                                    </div>
                                    <div>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p style="font-size: 18px;">&nbsp;ผู้รับสินค้า/ Goods Reccived by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p><br>
                                        <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้ส่งสินค้า/ Delivery by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                    </div>
                                </td>
                                <td style="font-size: 16px;width:35%;height:200px;border-right: 1px solid #000;line-height: 1.5;">
                                    <div>
                                        <p>o สินค้านี้ขายขาดไม่รับคืนหรือเปลี่ยน</p>
                                        <p>o บริษัทฯ จะนำใบเสร็จรับเงินมาเรียกเก็บเงินในภายหลัง</p>
                                        <p>o บริษัทฯ จะคิดดอกเบี้ย 1.25% ต่อเดือน</p>
                                        <p>&nbsp;&nbsp;&nbsp;เมื่อชำระบิลเกินกำหนด</p>
                                        <p>o กรุณาชำระเงินด้วยเช็ค ในนามบริษัทฯ</p>
                                        <p>&nbsp;&nbsp;&nbsp;ขีดผู้ถือและขีดคล่อมเท่านั้น</p>
                                    </div>
                                </td>
                                <td style="width:30%;text-align: center;">
                                    <div>
                                        <div style="text-align: left;">
                                            <p style="font-size: 12px;">ในนาม&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                                        </div>
                                        <p style="font-weight: bold;margin-left:5px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $branche_Name . '</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับมอบอำนาจ/ Authorized Singature</p><br>
                                        <p>&nbsp;&nbsp;________________________________</p>
                                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้ตรวจเช็คสินค้า/ Approved by</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                </div>
                <div style="position: absolute;top:720px;left:60px;width:390px;height:110px;">' . $remark . '</div>';

            $mpdf->WriteHTML($html_copy);
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
}
