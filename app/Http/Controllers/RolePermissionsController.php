<?php

namespace App\Http\Controllers;

use App\Models\RolePermissions;
use App\Models\MenuRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = RolePermissions::all();
        return $Item;
    }

    public function showID($id)
    {
        $RolePermissions = RolePermissions::find($id);
        if (!$RolePermissions) {
            return $this->returnBadRequest('RolePermissions not found');
        } else {
            return $this->returnSuccess('Success', $RolePermissions);
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
            $Item = new RolePermissions();
            $Item->permission_id = $request->permission_id;
            $Item->role_id = $request->role_id;
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
            $item = RolePermissions::where('id', $id)->first();
            $item->permission_id = $request->input('permission_id');
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

            $Item = RolePermissions::find($id);
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
            'role_id',
            'permission_id'
        ];

        $searchcolumn = [
        'role_id',
        'permission_id'];

        $query = RolePermissions::select($columns);


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

    public function role_menu_index()
    {
        $Items = MenuRole::with('role', 'menu')->get();

        $result = $Items->groupBy('role_id')->map(function ($group) {
            return [
                'id' => $group->first()->role->id,
                'name' => $group->first()->role->name,
                'menus' => $group->map(function ($item) {
                    return [
                        'id' => $item->menu->id,
                        'name' => $item->menu->name,
                        'key' => $item->menu->key,
                    ];
                })->values()->toArray()
            ];
        })->values();

        return response()->json($result);
    }

    public function role_menu_showID($id)
    {
        $Item = MenuRole::with('role', 'menu')->where('role_id', $id)->get();
        $result = $Item->groupBy('role_id')->map(function ($group) {
            return [
                'id' => $group->first()->role->id,
                'name' => $group->first()->role->name,
                'menus' => $group->map(function ($item) {
                    return [
                        'id' => $item->menu->id,
                        'name' => $item->menu->name,
                        'key' => $item->menu->key,
                    ];
                })->values()->toArray()
            ];
        })->values();

        return response()->json($result[0]);
    }

    public function role_menu_store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            foreach ($request->input('menu_id') as $menu_id) {
                $item = new MenuRole();
                $item->role_id = $request->input('role_id');
                $item->menu_id = $menu_id;
                $item->save();
            }

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    public function role_menu_update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            MenuRole::where('role_id', $id)->forceDelete();

            foreach ($request->input('menu_id') as $menu_id) {
                $item = new MenuRole();
                $item->role_id = $id;
                $item->menu_id = $menu_id;
                $item->save();
            }

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'อัปเดตรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' สำหรับ role_id ' . $id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    public function role_menu_destroy($id)
    {
        $Item = MenuRole::where('role_id', $id)->delete();
        return $this->returnSuccess('ลบรายการสำเร็จ', $Item);
    }
}
