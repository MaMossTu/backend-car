<?php

namespace App\Http\Controllers;

use App\Models\WorkOrderServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = WorkOrderServices::all();
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
            $Item = new WorkOrderServices();
            $Item->work_order_id = $request->work_order_id;
            $Item->repair_service_id = $request->repair_service_id;
            $Item->price = $request->price;
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
    public function show(WorkOrderServices $workOrderService)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WorkOrderServices $workOrderService)
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
            $Item = WorkOrderServices::find($id);
            $Item->work_order_id = $request->input('work_order_id');
            $Item->repair_service_id = $request->input('repair_service_id');
            $Item->price = $request->input('price');
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
            $Item = WorkOrderServices::find($id);
    
            if (!$Item) {
                return $this->returnError('ไม่พบข้อมูลที่ต้องการลบ');
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

}
