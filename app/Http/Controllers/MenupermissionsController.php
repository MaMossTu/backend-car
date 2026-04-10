<?php

namespace App\Http\Controllers;

use App\Models\Menu_permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MenupermissionsController extends Controller
{

    public function index()
    {
        $Item = Menu_permissions::all();
        return $Item;
    }

    public function showID($id)
    {
        $UserPosition = Menu_permissions::find($id);
        if (!$UserPosition) {
            return $this->returnBadRequest('UserPosition not found');
        } else {
            return $this->returnSuccess('Success', $UserPosition);
        }
    }
    /////////////////////////////////////////////////////////////////////////////////////////////////////
    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $Item = new Menu_permissions();
            $Item->permission_id = $request->permission_id;
            $Item->menu_id = $request->menu_id;
            $Item->role_id = $request->role_id;
            $Item->view = $request->view;
            $Item->save = $request->save;
            $Item->edit = $request->edit;
            $Item->delete = $request->delete;
            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Add Data Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    public function update(Request $request, string $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $Item = Menu_permissions::find($id);
            $Item->permission_id = $request->input('permission_id');
            $Item->menu_id = $request->input('menu_id');
            $Item->role_id = $request->input('role_id');
            $Item->view = $request->input('view');
            $Item->save = $request->input('save');
            $Item->edit = $request->input('edit');
            $Item->delete = $request->input('delete');
            $Item->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $Item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Update Data Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }
    ////////////////////////////////////////////////////////////////////////////////

    public function destroy(string $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {

            $Item = Menu_permissions::find($id);
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบเมนู';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . "id" . $type . $Item->$id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('system error' . $e, 404);
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////
    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;


        $columns = [
            'id',
            'permission_id',
            'role_id',
            'menu_id',
            'view',
            'save',
            'edit',
            'delete'
        ];
        $searchcolumn = [
            'view',
            'save',
            'edit',
            'delete'
        ];

        $query = Menu_permissions::select($columns);


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
