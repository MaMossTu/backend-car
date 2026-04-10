<?php

namespace App\Http\Controllers;

use App\Models\Inventories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = Inventories::all();
        return $Item;
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
        DB::beginTransaction();
        try {
            $Item = new Inventories();
            $Item->description = $request->description;
            $Item->qty = $request->qty;
            $Item->cost = $request->cost;
            $Item->vendor_id = $request->vendor_id;
            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Inventories $inventory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Inventories $inventory)
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
        DB::beginTransaction();
        try {
            $Item = Inventories::find($id);
            $Item->description = $request->input('description');
            $Item->qty = $request->input('qty');
            $Item->cost = $request->input('cost');
            $Item->vendor_id = $request->input('vendor_id');
            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
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
            $Item = Inventories::find($id);
    
            if (!$Item) {
                return $this->returnError('ไม่พบข้อมูลสินค้าที่ต้องการลบ');
            }

            $Item->delete();
    
            DB::commit();
    
            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id: ' . $Item->id;
            $this->Log($userId, $description, $type);
    
            return $this->returnSuccess('ลบข้อมูลสำเร็จ', $Item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }
    
    // public function getPage(Request $request)
    // {
    //     $length = $request->length;
    //     $order = $request->order;
    //     $search = $request->search;
    //     $start = $request->start;
    //     $page = $start / $length + 1;


    //     $columns = [
    //     'id', 'vehicle_inspection_id', 'name', 'price','created_at', 'updated_at'
    //     ];

    //     $query = Inventories::select($columns);


    //     $orderBy = $columns[$order[0]['column']] ?? 'id';
    //     $query->orderBy($orderBy, $order[0]['dir']);


    //     if (!empty($search['value'])) {
    //         $query->where(function ($query) use ($search, $columns) {
    //             foreach ($columns as $column) {
    //                 $query->orWhere($column, 'like', '%' . $search['value'] . '%');
    //             }
    //         });
    //     }


    //     $items = $query->paginate($length, ['*'], 'page', $page);


    //     if ($items->isNotEmpty()) {
    //         $No = (($page - 1) * $length);
    //         foreach ($items as $item) {
    //             $No++;
    //             $item->No = $No;
    //         }
    //     }

    //     return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    // }
}
