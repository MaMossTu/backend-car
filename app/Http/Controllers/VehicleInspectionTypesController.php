<?php

namespace App\Http\Controllers;

use App\Models\VehicleInspectionTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleInspectionTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = VehicleInspectionTypes::all();
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
            $Item = new VehicleInspectionTypes();
            $Item->type = $request->type;
            $Item->code = $request->code;
            $Item->name = $request->name;
            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Add Data Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    /**
     * Display the specified resource.
     */
    public function showID($id)
    {
        $VehicleInspectionTypes = VehicleInspectionTypes::with('InsuranceTypes')->where('id', $id)->get();
        if (!$VehicleInspectionTypes) {
            return $this->returnBadRequest('VehicleInspectionTypes not found', 'ไม่พบข้อมูล');
        } else {
            return $this->returnSuccess('Success', $VehicleInspectionTypes);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VehicleInspectionTypes $vehicleInspectionTypes)
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
            $Item = VehicleInspectionTypes::find($id);
            if (!$Item) {
                return $this->returnBadRequest('VehicleInspectionTypes not found', 'ไม่พบข้อมูล');
            }
            $Item->name = $request->input('name');
            $Item->type = $request->type;
            $Item->code = $request->code;
            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Update Data Success', $Item);
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
            $Item = VehicleInspectionTypes::find($id);
            if (!$Item) {
                return $this->returnBadRequest('VehicleInspectionTypes not found', 'ไม่พบข้อมูล');
            }
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
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
            'type',
            'code',
            'name'
        ];
        $searchcolumn= ['name'];
        $query = VehicleInspectionTypes::select($columns);


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
    public function filter($id)
    {
        $vehicle = VehicleInspectionTypes::with('InsuranceTypes')->where('id', $id)->get();
        if (!$vehicle) {
            return $this->returnBadRequest('insurance type is not found');
        } else {
            return $this->returnSuccess('Success', $vehicle);
        }
    }
}
