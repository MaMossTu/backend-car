<?php

namespace App\Http\Controllers;

use App\Models\Employees;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeesController extends Controller
{

    public function index()
    {
        $Item = Employees::all();
        return $this->returnSuccess('Success', $Item);

    }
    public function showall_engineer()
    {
        $Item = Employees::where('is_engineer',1)->get();
        return $this->returnSuccess('Success', $Item);

    }

    public function showID($id)
    {
        $Employees = Employees::find($id);
        if (!$Employees) {
            return $this->returnBadRequest('Employees not found');
        } else {
            return $this->returnSuccess('Success', $Employees);
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
            $Item = new Employees;
            $Item->branch_id = $request->branch_id;
            $Item->first_name = $request->first_name;
            $Item->last_name = $request->last_name;
            $Item->phone_number = $request->phone_number;
            $Item->type = $request->type;
            $Item->email = $request->email;
            $Item->hire_day = $request->hire_day;
            $Item->code = $request->code;
            $Item->is_engineer = $request->is_engineer;
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
        try {
            $item = Employees::where('id', $id)->first();
            $item->branch_id = $request->input('branch_id');
            $item->first_name = $request->input('first_name');
            $item->last_name = $request->input('last_name');
            $item->phone_number = $request->input('phone_number');
            $item->email = $request->input('email');
            $item->type = $request->input('type');
            $item->hire_day = $request->input('hire_day');
            $item->code = $request->input('code');
            $item->is_engineer = $request->input('is_engineer');

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

            $Item = Employees::find($id);
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบลูกค้า';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('system error' . $e, 404);
        }
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
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'branch_id'
        ];
        $searchcolumn = [
            'first_name',
            'last_name',
            'email',
            'phone_number'
        ];

        $query = Employees::select($columns);


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
}
