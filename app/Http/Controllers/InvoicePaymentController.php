<?php

namespace App\Http\Controllers;

use App\Models\Doc;
use App\Models\Doc_transaction;
use App\Models\DocCustomer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoicePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $InvoicePayment = InvoicePayment::all();
        return $InvoicePayment;
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

        // $validator = Validator::make($request->query(), [
        //     'invoice_id' => 'required|integer',
        //     'paid_type' => 'required',
        //     'paid_price' => 'required|numeric',
        // ]);
        // // dd($request);
        // if ($validator->fails()) {
        //     return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        // }

        try {
            $invoicePayment = new InvoicePayment();
            $invoicePayment->invoice_id = $request->invoice_id;

            $before_price = InvoicePayment::where('invoice_id', $request->invoice_id)->orderBy('created_at', 'desc')->first();
            if ($before_price == null) {
                $before_price = Invoice::where('id', $request->invoice_id)->first()->total_amount;
                if ($before_price === null) {
                    return $this->returnErrorData('ไม่พบข้อมูลใบแจ้งหนี้',404);
                }
            } else {
                $before_price = $before_price->after_price;
            }

            $after_price = $before_price - $request->paid_price;
            if ($after_price <= 0) {
                $after_price = 0;
                $updateinvoice = Invoice::with('invoiceServices.services','Customers')->find($request->invoice_id);
                $updateinvoice->status = 'paid';
                $updateinvoice->save();
                if($updateinvoice->status == 'paid' && $updateinvoice->is_stock == 0){
                    $customer   = $updateinvoice->Customers;
                    $branche_id = $updateinvoice->branche_id;
                    foreach ($updateinvoice->invoiceServices as $service) {
                        if ($service->services != null && $service->services->doc_id != null) {
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

                                    $updateinvoice->is_stock = 1;
                                    $updateinvoice->save();
                                }
                            }
                        }
                    }
                }
            }
            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $count = InvoicePayment::whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->whereRaw("no REGEXP '^INP" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวน

            $no = sprintf("INP%s%s/%03d", $year, $month, $count);
            $invoicePayment->no = $no;
            $invoicePayment->date = $request->date ?? date('Y-m-d');

            $invoicePayment->paid_type = $request->paid_type;
            $invoicePayment->paid_price = $request->paid_price;
            $invoicePayment->description = $request->description;
            $invoicePayment->before_price = $before_price;
            $invoicePayment->after_price = $after_price;

            $invoicePayment->save();
            DB::commit();
            return $this->returnSuccess('Data saved successfully', $invoicePayment);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Display the specified resource.
     */
    public function showID($id)
    {
        $InvoicePayment = InvoicePayment::with([
            'invoice',
        ])->find($id);
        if (!$InvoicePayment) {
            return $this->returnBadRequest('InvoicePayment not found');
        } else {
            return $this->returnSuccess('Success', $InvoicePayment);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InvoicePayment $invoicePayment)
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
            'paid_type' => 'required',
            'paid_price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $invoicePayment = InvoicePayment::find($id);

            if (!$invoicePayment) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $before_price = InvoicePayment::where('invoice_id', $invoicePayment->invoice_id)->where('id', '!=', $id)->orderBy('created_at', 'desc')->first();
            if ($before_price == null) {
                $before_price = Invoice::where('id', $invoicePayment->invoice_id)->first()->total_amount;
                if ($before_price == null) {
                    return $this->returnError('ไม่พบข้อมูลใบแจ้งหนี้');
                }
            } else {
                $before_price = $before_price->after_price;
            }

            $after_price = $before_price - $request->paid_price;
            if ($after_price <= 0) {
                $after_price = 0;
                $updateinvoice = Invoice::find($invoicePayment->invoice_id);
                $updateinvoice->status = 'paid';
                $updateinvoice->save();
            }else{
                $updateinvoice = Invoice::find($invoicePayment->invoice_id);
                $updateinvoice->status = 'unpaid';
                $updateinvoice->save();
            }

            $invoicePayment->paid_type = $request->paid_type;
            $invoicePayment->paid_price = $request->paid_price;
            $invoicePayment->description = $request->description;
            $invoicePayment->before_price = $before_price;
            $invoicePayment->after_price = $after_price;

            $invoicePayment->save();

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $invoicePayment);
        } catch (\Exception $e) {
            DB::rollBack();
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

        DB::beginTransaction();
        try {
            $invoicePayment = InvoicePayment::find($id);
            if (!$invoicePayment) {
                return response()->json(['message' => 'ไม่พบข้อมูลการชำระเงิน'], 404);
            }

            $invoicePayment->delete();

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $invoicePayment);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

}
