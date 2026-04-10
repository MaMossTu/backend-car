<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostController extends Controller
{

    public function index()
    {
        $cost = Cost::all();
        return $cost;
    }

    public function showID($id)
    {
        $Branches = Cost::with('ExpenseType')->find($id);
        if (!$Branches) {
            return $this->returnBadRequest('Branches not found');
        } else {
            return $this->returnSuccess('Success', $Branches);
        }
    }
    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $Item = new Cost;
            $Item->name  = $request->name;
            $Item->price = $request->price;
            $Item->type = $request->type;
            $Item->date = $request->date;
            $Item->branch_id = $request->branch_id;
            $Item->expense_type_id = $request->expense_type_id;
            $Item->detail = $request->input('detail','-');
            $Item->paid_type = $request->input('paid_type',null);
            $Item->employees_id = $request->employees_id;
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
    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $item = Cost::where('id', $id)->first();
            if (!$item) {
                return $this->returnBadRequest('data not found');
            }
            $item->name = $request->input('name');
            $item->price = $request->input('price');
            $item->type = $request->type;
            $item->date = $request->input('date',null);
            $item->branch_id = $request->input('branch_id');
            $item->expense_type_id = $request->input('expense_type_id',$item->expense_type_id);
            $item->detail = $request->input('detail','-');
            $item->paid_type = $request->input('paid_type',null);
            $item->employees_id = $request->input('employees_id',$item->employees_id);
            $item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item);
        } catch (\Throwable $e) {
            DB::rollback();

            return $this->returnErrorData('system error please try again ' . $e, 404);
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

            $Item = Cost::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการลบ',404);
            }
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . "id" . $type . $Item->$id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('system error' . $e, 404);
        }
    }
}
