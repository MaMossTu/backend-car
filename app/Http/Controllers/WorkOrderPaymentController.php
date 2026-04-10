<?php

namespace App\Http\Controllers;

use App\Models\Doc;
use App\Models\Doc_transaction;
use App\Models\DocCustomer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\WorkOrder;
use App\Models\WorkOrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class WorkOrderPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $WorkOrderPayment = WorkOrderPayment::all();
        return $WorkOrderPayment;
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

        try {
            $WorkOrderPayment = new WorkOrderPayment();
            $WorkOrderPayment->work_order_id = $request->work_order_id;

            $before_price = WorkOrderPayment::where('work_order_id', $request->work_order_id)->orderBy('created_at', 'desc')->first();
            if ($before_price == null) {
                $before_price = WorkOrder::where('id', $request->work_order_id)->first()->total_amount;
                if ($before_price === null) {
                    return $this->returnErrorData('ไม่พบข้อมูลใบแจ้งหนี้',404);
                }
            } else {
                $before_price = $before_price->after_price;
            }

            $after_price = $before_price - $request->paid_price;
            if ($after_price <= 0) {
                $after_price = 0;
                $updateWorkOrder = WorkOrder::find($request->work_order_id);
                $updateWorkOrder->status_expend = 'paid';
                $updateWorkOrder->save();
            }
            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $count = WorkOrderPayment::whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->whereRaw("no REGEXP '^FIXP" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวน

            $raw = $request->input('date'); // รับมาจากฟอร์ม
            $dateForDb = $raw ? Carbon::parse($raw)->timezone('Asia/Bangkok')->toDateString() : null; // YYYY-MM-DD

            $no = sprintf("FIXP%s%s/%03d", $year, $month, $count);
            $WorkOrderPayment->no = $no;
            $WorkOrderPayment->paid_type = $request->paid_type;
            $WorkOrderPayment->paid_price = $request->paid_price;
            $WorkOrderPayment->description = $request->description;
            $WorkOrderPayment->before_price = $before_price;
            $WorkOrderPayment->after_price = $after_price;
            $WorkOrderPayment->date = $dateForDb;
            $WorkOrderPayment->save();
            DB::commit();
            return $this->returnSuccess('Data saved successfully', $WorkOrderPayment);
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
        $WorkOrderPayment = WorkOrderPayment::with([
            'workorder',
        ])->find($id);
        if (!$WorkOrderPayment) {
            return $this->returnBadRequest('InvoicePayment not found');
        } else {
            return $this->returnSuccess('Success', $WorkOrderPayment);
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
            $WorkOrderPayment = WorkOrderPayment::find($id);

            if (!$WorkOrderPayment) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $before_price = WorkOrderPayment::where('work_order_id', $WorkOrderPayment->work_order_id)->where('id', '!=', $id)->orderBy('created_at', 'desc')->first();
            if ($before_price == null) {
                $before_price = WorkOrder::where('id', $WorkOrderPayment->work_order_id)->first()->total;
                if ($before_price == null) {
                    return $this->returnError('ไม่พบข้อมูลใบแจ้งหนี้');
                }
            } else {
                $before_price = $before_price->after_price;
            }

            $after_price = $before_price - $request->paid_price;
            if ($after_price <= 0) {
                $after_price = 0;
                $updateWorkOrder = WorkOrder::find($WorkOrderPayment->work_order_id);
                $updateWorkOrder->status = 'paid';
                $updateWorkOrder->save();
            }else{
                $updateWorkOrder = WorkOrder::find($WorkOrderPayment->work_order_id);
                $updateWorkOrder->status = 'unpaid';
                $updateWorkOrder->save();
            }

            $raw = $request->input('date'); // รับมาจากฟอร์ม
            $dateForDb = $raw ? Carbon::parse($raw)->timezone('Asia/Bangkok')->toDateString() : null; // YYYY-MM-DD

            $WorkOrderPayment->paid_type = $request->paid_type;
            $WorkOrderPayment->paid_price = $request->paid_price;
            $WorkOrderPayment->description = $request->description;
            $WorkOrderPayment->before_price = $before_price;
            $WorkOrderPayment->after_price = $after_price;
            $WorkOrderPayment->date = $dateForDb;
            $WorkOrderPayment->save();

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $WorkOrderPayment);
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
            $WorkOrderPayment = WorkOrderPayment::find($id);
            if (!$WorkOrderPayment) {
                return response()->json(['message' => 'ไม่พบข้อมูลการชำระเงิน'], 404);
            }

            $WorkOrderPayment->delete();

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $WorkOrderPayment);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

}
