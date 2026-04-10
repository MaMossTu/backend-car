<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\Doc;
use App\Models\Doc_transaction;
use App\Models\DocCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocTransactionController extends Controller
{
    public function index()
    {
        $transactions = Doc_transaction::all();
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $transactions);
    }

    public function showID($id)
    {
        $transaction = Doc_transaction::with(['docCustomers.doc.documentType', 'inspection'])->find($id);
        if (!$transaction) {
            return $this->returnBadRequest('ไม่พบข้อมูลธุรกรรมเอกสาร');
        } else {
            return $this->returnSuccess('Success', $transaction);
        }
    }

    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make($request->all(), [
            // 'doc_id' => 'required|integer',
            // 'customer_id' => 'required|integer',
            // 'inspection_id' => 'nullable|integer',
            'doc_customer_id' => 'nullable|integer',
            'date' => 'required|date',
            'transaction_type' => 'required|in:Deposit,Withdraw',
            'quantity' => 'required|numeric|min:1'
        ],[
            // 'doc_id.required' => 'กรุณาระบุเอกสาร',
            // 'doc_id.integer' => 'รหัสเอกสารต้องเป็นจำนวนเต็ม',
            // 'customer_id.required' => 'กรุณาระบุลูกค้า',
            // 'customer_id.integer' => 'รหัสลูกค้าต้องเป็นจำนวนเต็ม',
            'doc_customer_id.required' => 'กรุณาระบุรหัสเอกสารลูกค้า',
            'doc_customer_id.integer' => 'รหัสลูกค้าต้องเป็นจำนวนเต็ม',
            'date.required' => 'กรุณาระบุวันที่',
            'date.date' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'transaction_type.required' => 'กรุณาระบุประเภทธุรกรรม',
            'transaction_type.in' => 'ประเภทธุรกรรมต้องเป็น Deposit หรือ Withdraw',
            'quantity.required' => 'กรุณาระบุจำนวน',
            'quantity.numeric' => 'จำนวนต้องเป็นตัวเลข',
            'quantity.min' => 'จำนวนต้องไม่น้อยกว่า 1'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        // DB::beginTransaction();

        // try {
        //     $doc = Doc::find($request->doc_customer_id);
        //     if (!$doc) {
        //         return $this->returnBadRequest('ไม่พบข้อมูลเอกสาร');
        //     }
        //     $Customers = Customers::find($request->customer_id);
        //     if (!$Customers) {
        //         return $this->returnBadRequest('ไม่พบข้อมูลลูกค้า');
        //     }
        //     $doc_cus = DocCustomer::where('docs_id', $doc->id)->where('customer_id', $Customers->id)->first();

        //     if (!$doc_cus) {
        //         $doc_cus = new DocCustomer();
        //         $doc_cus->docs_id = $doc->id;
        //         $doc_cus->customer_id = $Customers->id;
        //         $doc_cus->total_qty = 0;
        //         $doc_cus->save();

        //         $total_qty = $doc_cus->total_qty;
        //     }else{
        //         $total_qty = $doc_cus->total_qty;
        //     }

        //     if ($request->transaction_type == 'Deposit') {
        //         $newBalance = $total_qty + $request->quantity;
        //     } else {
        //         if ($total_qty < $request->quantity) {
        //             return $this->returnErrorData('เอกสารไม่เพียงพอสำหรับการเบิก', 404);
        //         }
        //         $newBalance = $total_qty - $request->quantity;
        //     }

        //     $transaction = new Doc_transaction();
        //     $transaction->doc_id = $doc->id;
        //     $transaction->customer_id = $Customers->id;
        //     $transaction->inspection_id = $request->inspection_id;
        //     $transaction->date = $request->date;
        //     $transaction->transaction_type = $request->transaction_type;
        //     $transaction->stock = $total_qty;
        //     $transaction->quantity = $request->quantity;
        //     $transaction->balance = $newBalance;
        //     $transaction->remark = $request->remark;
        //     $transaction->save();

        //     // Update doc total quantity
        //     $doc_cus->total_qty = $newBalance;
        //     $doc_cus->save();

        //     DB::commit();

        //     // Log
        //     $userId = $loginBy->id;
        //     $type = 'บันทึกธุรกรรมเอกสาร';
        //     $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $transaction->id;
        //     $this->Log($userId, $description, $type);

        //     return $this->returnSuccess('บันทึกธุรกรรมสำเร็จ', $transaction);
        // } catch (\Throwable $e) {
        //     DB::rollback();
        //     return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        // }
        DB::beginTransaction();

        try {

            $doc_cus = DocCustomer::where('id', $request->doc_customer_id)->first();
            // if(!$doc_cus){
            //     return $this->returnBadRequest('ไม่พบข้อมูลเอกสารของลูกค้า');
            // }
            $total_qty = 0;
            if($doc_cus){
                $total_qty = $doc_cus->total_qty;
            }

            if ($request->transaction_type == 'Deposit') {
                $newBalance = $total_qty + $request->quantity;
            } else {
                if ($total_qty < $request->quantity) {
                    return $this->returnErrorData('เอกสารไม่เพียงพอสำหรับการเบิก', 404);
                }
                $newBalance = $total_qty - $request->quantity;
            }

            if($request->branch_id){

                $docId = $request->doc_id;
                $total_qty = Doc_transaction::where('branch_id', $request->branch_id)
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
                if($total_qty == null){
                    $total_qty = 0;
                }

                if ($request->transaction_type == 'Deposit') {
                    $newBalance = $total_qty + $request->quantity;
                } else {
                    if ($total_qty < $request->quantity) {
                        return $this->returnErrorData('เอกสารไม่เพียงพอสำหรับการเบิก', 404);
                    }
                    $newBalance = $total_qty - $request->quantity;
                }
            }

            $transaction = new Doc_transaction();
            $transaction->doc_id = $request->doc_id;
            $transaction->branch_id = $request->branch_id;
            if($doc_cus){
                $transaction->doc_customer_id = $doc_cus->id;
            }
            $transaction->inspection_id = $request->inspection_id;
            $transaction->date = $request->date;
            $transaction->transaction_type = $request->transaction_type;
            $transaction->stock = $total_qty;
            $transaction->quantity = $request->quantity;
            $transaction->balance = $newBalance;
            $transaction->remark = $request->remark;
            $transaction->save();

            // Update doc total quantity
            if($doc_cus){
                $doc_cus->total_qty = $newBalance;
                $doc_cus->save();
            }

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'บันทึกธุรกรรมเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $transaction->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('บันทึกธุรกรรมสำเร็จ', $transaction);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    // public function update(Request $request, $id)
    // {
    //     $loginBy = Auth()->user();
    //     if (!isset($loginBy)) {
    //         return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $transaction = Doc_transaction::findOrFail($id);
    //         $doc_cus = DocCustomer::findOrFail($request->doc_customers_id);

    //         // Revert the old transaction
    //         if ($transaction->transaction_type == 'Deposit') {
    //             $doc_cus->total_qty -= $transaction->quantity;
    //         } else {
    //             $doc_cus->total_qty += $transaction->quantity;
    //         }

    //         // Apply the new transaction
    //         if ($request->transaction_type == 'Deposit') {
    //             $newBalance = $doc_cus->total_qty + $request->quantity;
    //         } else {
    //             if ($doc_cus->total_qty < $request->quantity) {
    //                 return $this->returnErrorData('เอกสารไม่เพียงพอสำหรับการเบิก', 404);
    //             }
    //             $newBalance = $doc_cus->total_qty - $request->quantity;
    //         }

    //         // Update transaction
    //         $transaction->doc_customers_id = $doc_cus->id;
    //         $transaction->inspection_id = $request->inspection_id;
    //         $transaction->date = $request->date;
    //         $transaction->transaction_type = $request->transaction_type;
    //         $transaction->stock = $doc_cus->total_qty;
    //         $transaction->quantity = $request->quantity;
    //         $transaction->balance = $newBalance;
    //         $transaction->remark = $request->remark;
    //         $transaction->save();

    //         // Update doc total quantity
    //         $doc_cus->total_qty = $newBalance;
    //         $doc_cus->save();

    //         DB::commit();

    //         // Log
    //         $userId = $loginBy->id;
    //         $type = 'แก้ไขธุรกรรมเอกสาร';
    //         $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $transaction->id;
    //         $this->Log($userId, $description, $type);

    //         return $this->returnSuccess('แก้ไขธุรกรรมสำเร็จ', $transaction);
    //     } catch (\Throwable $e) {
    //         DB::rollback();
    //         return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
    //     }
    // }
    public function update(Request $request, $transaction_id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'doc_customer_id' => 'nullable|integer',
            'inspection_id' => 'nullable|integer',
            'transaction_type' => 'required|in:Deposit,Withdraw',
            'quantity' => 'required|numeric|min:1'
        ],[
            'date.required' => 'กรุณาระบุวันที่',
            'date.date' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'inspection_id.integer' => 'บิลต้องเป็นจำนวนเต็ม',
            'doc_customer_id.integer' => 'รหัสลูกค้าต้องเป็นจำนวนเต็ม',
            'transaction_type.required' => 'กรุณาระบุประเภทธุรกรรม',
            'transaction_type.in' => 'ประเภทธุรกรรมต้องเป็น Deposit หรือ Withdraw',
            'quantity.required' => 'กรุณาระบุจำนวน',
            'quantity.numeric' => 'จำนวนต้องเป็นตัวเลข',
            'quantity.min' => 'จำนวนต้องไม่น้อยกว่า 1'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();

        try {
            $transaction = Doc_transaction::find($transaction_id);
            if (!$transaction) {
                return $this->returnBadRequest('ไม่พบข้อมูลธุรกรรมที่ต้องการอัปเดต');
            }

            // $doc = doc::find($transaction->doc_id);
            // $Customers = Customers::find($transaction->customer_id);

            // if (!$doc) {
            //     return $this->returnBadRequest('ไม่พบข้อมูลเอกสาร');
            // }

            // if (!$Customers) {
            //     return $this->returnBadRequest('ไม่พบข้อมูลลูกค้า');
            // }

            $doc_cus = DocCustomer::where('id', $transaction->doc_customer_id)->first();
            if (!$doc_cus) {
                return $this->returnBadRequest('ไม่พบข้อมูล DocCustomer ที่สอดคล้อง');
            }
            // return $doc_cus;
            $current_total_qty = $doc_cus->total_qty;
            $old_qty = $transaction->quantity;
            $old_type = $transaction->transaction_type;

            if ($old_type == 'Deposit') {
                $current_total_qty -= $old_qty;
            } else {
                $current_total_qty += $old_qty;
            }
            $newType = $request->transaction_type ?? $transaction->transaction_type;
            $newQty  = $request->quantity ?? $transaction->quantity;

            if ($newType == 'Deposit') {
                $newBalance = $current_total_qty + $newQty;
            } else {
                if ($current_total_qty < $newQty) {
                    return $this->returnErrorData('เอกสารไม่เพียงพอสำหรับการเบิก', 404);
                }
                $newBalance = $current_total_qty - $newQty;
            }

            $transaction->inspection_id = $request->inspection_id ?? $transaction->inspection_id;
            $transaction->date = $request->date ?? $transaction->date;
            $transaction->transaction_type = $newType;
            $transaction->stock = $current_total_qty;
            $transaction->quantity = $newQty;
            $transaction->balance = $newBalance;
            $transaction->remark = $request->remark ?? $transaction->remark;
            $transaction->save();

            $doc_cus->total_qty = $newBalance;
            $doc_cus->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'แก้ไขธุรกรรมเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $transaction->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('อัพเดตธุรกรรมสำเร็จ', $transaction);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function destroy(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $data = ['transaction_id' => $id];

        $validator = Validator::make($data, [
            'transaction_id' => 'required|integer'
        ],[
            'transaction_id.required' => 'กรุณาระบุรหัสธุรกรรม',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();

        try {
            $transaction = Doc_transaction::find($id);
            if (!$transaction) {
                return $this->returnBadRequest('ไม่พบข้อมูลธุรกรรมที่ต้องการลบ');
            }

            // $doc = Doc::find($transaction->doc_customer_id);
            // $Customers = Customers::find($transaction->customer_id);

            // if (!$doc) {
            //     return $this->returnBadRequest('ไม่พบข้อมูลเอกสาร');
            // }

            // if (!$Customers) {
            //     return $this->returnBadRequest('ไม่พบข้อมูลลูกค้า');
            // }

            $doc_cus = DocCustomer::where('id', $transaction->doc_customer_id)->first();
            // if (!$doc_cus) {
            //     return $this->returnBadRequest('ไม่พบข้อมูล DocCustomer ที่เกี่ยวข้องกับธุรกรรมนี้');
            // }
            if($doc_cus){
                $current_total_qty = $doc_cus->total_qty;
                $old_qty = $transaction->quantity;
                $old_type = $transaction->transaction_type;

                if ($old_type == 'Deposit') {
                    $reverted_qty = $current_total_qty - $old_qty;
                } else {
                    $reverted_qty = $current_total_qty + $old_qty;
                }

                // อัพเดต DocCustomer
                $doc_cus->total_qty = $reverted_qty;
                $doc_cus->save();
            }

            $transaction->delete();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'ลบธุรกรรมเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('ลบธุรกรรมสำเร็จ', $transaction);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }


    public function getPage(Request $request)
    {
        $length = $request->length ?? 10;
        $order = $request->order ?? [['column' => 0, 'dir' => 'asc']];
        $search = $request->search ?? ['value' => ''];
        $start = $request->start ?? 0;
        $page = intval($start / $length) + 1;
        $transaction_type = $request->transaction_type;
        $branch_id = $request->branch_id;

        $columns = [
            'id',
            'doc_id',
            'customer_id',
            'inspection_id',
            'branch_id',
            'date',
            'transaction_type',
            'quantity',
            'balance',
            'remark',
            'created_at',
            'updated_at'
        ];
        $searchColumns = [
            'remark',
            'transaction_type',
        ];

        $query = Doc_transaction::with(['inspection.Customers','customer','docCustomers.doc','docCustomers.customer','branches','doc']);

        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);
        if($transaction_type){
            $query->where('transaction_type', $transaction_type);
        }
        if($branch_id){
            $query->where('branch_id', $branch_id);
        }
        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
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

    // public function report_branch(Request $request)
    // {
    //     $branch_id = $request->branch_id;
    //     $report = Doc_transaction::with('doc')
    //         ->where('branch_id', $request->branch_id)
    //         ->get();
    //     return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $report);
    // }
    public function report_branch(Request $request)
    {
        $branch_id = $request->branch_id;

        // คำนวณผลรวม quantity แยกตามประเภท transaction
        $balanceQuery = Doc_transaction::select('doc_id')
            ->selectRaw('SUM(CASE
                WHEN transaction_type = "Deposit" THEN quantity
                WHEN transaction_type = "Withdraw" THEN -quantity
            END) as total')
            ->where('branch_id', $branch_id)
            ->groupBy('doc_id');

        // เชื่อมต่อกับเอกสารทั้งหมด
        $docs = Doc::leftJoinSub($balanceQuery, 'balances', function ($join) {
            $join->on('docs.id', '=', 'balances.doc_id');
        })
        ->selectRaw('docs.*, COALESCE(balances.total, 0) as remaining')
        ->get();

        // จัดรูปแบบข้อมูล
        $report = $docs->map(function ($doc) {
            return [
                'doc_id' => $doc->id,
                'doc_name' => $doc->name,
                'remaining' => $doc->remaining,
                'document_info' => $doc
            ];
        });

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $report);
    }

}
