<?php

namespace App\Http\Controllers;

use App\Models\VehicleGas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleGasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = VehicleGas::all();
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
        $item = [];
        DB::beginTransaction();
        try{
            foreach($request->gas as $loop){
                $Item = new VehicleGas();
                $Item->vehicle_id = $loop['vehicle_id'];
                $Item->gas_position = $loop['gas_position'];
                $Item->gas_type = $loop['gas_type'];
                $Item->gas_brand = $loop['gas_brand'];
                $Item->gas_number = $loop['gas_number'];
                $Item->gas_weight = $loop['gas_weight'];
                $Item->gas_thick = $loop['gas_thick'];
                $Item->gas_capacity = $loop['gas_capacity'];
                $Item->gas_create = $loop['gas_create'];
                $Item->gas_expire = $loop['gas_expire'];
                $Item->save();
                $item[] = $Item;
            }

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item);
        }catch (\Throwable $e){
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
        $vehicle_id = $request->vehicle_id;

        $columns = [
            'id',
            'vehicle_id',
            'gas_type',
            'gas_position',
            'gas_brand',
            'gas_number',
            'gas_weight',
            'gas_thick',
            'gas_capacity',
            'gas_create',
            'gas_expire',
            'created_at',
            'updated_at',
        ];
        $searchcolumn = [
            'gas_type',
            'gas_model',
            'gas_brand',
            'gas_number',
            'gas_create',
            'gas_expire'
        ];

        $query = VehicleGas::select($columns);

        if (!empty($vehicle_id)) {
            $query->where('vehicle_id', $vehicle_id);
        }

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

    /**
     * Display the specified resource.
     */
    public function showallID(VehicleGas $vehicleGas,$id)
    {
        $Item = VehicleGas::where('vehicle_id',$id)->get();
        return $Item;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VehicleGas $vehicleGas)
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

        try{
            $Item = VehicleGas::find($id);
            $Item->vehicle_id = $request->input('vehicle_id');
            $Item->gas_position = $request->input('gas_position');
            $Item->gas_type = $request->input('gas_type');
            $Item->gas_brand = $request->input('gas_brand');
            $Item->gas_number = $request->input('gas_number');
            $Item->gas_weight = $request->input('gas_weight');
            $Item->gas_thick = $request->input('gas_thick');
            $Item->gas_capacity = $request->input('gas_capacity');
            $Item->gas_create = $request->input('gas_create');
            $Item->gas_expire = $request->input('gas_expire');
            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $Item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        }catch (\Throwable $e){
            DB::rollBack();
            return $this->returnErrorData('System error please try again ' . $e, 404);
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

            $Item = VehicleGas::find($id);
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('Delete success', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('System error' . $e, 404);
        }
    }
}
