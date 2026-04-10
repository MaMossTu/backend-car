<?php

namespace App\Http\Controllers;

use App\Models\UserPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = UserPosition::all();
        return $Item;
    }
    public function showID($id)
    {
        $UserPosition = UserPosition::find($id);
        if (!$UserPosition) {
            return $this->returnBadRequest('UserPosition not found');
        } else {
            return $this->returnSuccess('Success', $UserPosition);
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
            $id = $request->id;
            $Item = UserPosition::find($id);
            if ($Item) {
                $Item->user_id = $request->user_id;
                $Item->role_id = $request->role_id;
                $Item->department_id = $request->department_id;
                $Item->sub_department_id = $request->sub_department_id;
                $Item->save();
            }else{
                $Item = new UserPosition();
                $Item->user_id = $request->user_id;
                $Item->role_id = $request->role_id;
                $Item->department_id = $request->department_id;
                $Item->sub_department_id = $request->sub_department_id;
                $Item->save();
            }

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการพนักงาน';
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
            $item = UserPosition::where('id', $id)->first();
            $item->user_id = $request->input('user_id');
            $item->role_id = $request->input('role_id');

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

            $Item = UserPosition::find($id);
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
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
            'user_id',
            'role_id'
        ];
        $searchcolumn = ['user_id',
            'role_id'];

        $query = UserPosition::select($columns);


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
