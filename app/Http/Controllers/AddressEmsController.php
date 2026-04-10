<?php

namespace App\Http\Controllers;

use App\Models\AddressEms;
use App\Models\Inspections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AddressEmsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = AddressEms::all();
        return $Item;
    }

    public function showID($id)
    {
        $CustomerAddresses = AddressEms::find($id);
        if (!$CustomerAddresses) {
            return $this->returnBadRequest('AddressEms not found');
        } else {
            return $this->returnSuccess('Success', $CustomerAddresses);
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
            $validator = Validator::make(request()->all(), [
                'ems' => 'required',
                'inspection_id' => 'required|numeric',
                'province_id' => 'required|numeric',
                'district_id' => 'required|numeric',
                'subdistrict_id' => 'required|numeric',
                'address' => 'required',
                'name' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors()->toJson(), 400);
            }
            $ems = Inspections::find($request->inspection_id);
            if (!$ems) {
                return $this->returnErrorData('ไม่พบข้อมูล',404);
            }
            $ems->EMS = $request->ems;
            $ems->save();
            // return $ems->EMS;
            $inspection = AddressEms::where('inspection_id',$request->inspection_id)->first();
            if ($inspection) {
                // return $inspection;
                $inspection->inspection_id = $request->inspection_id;
                $inspection->province_id = $request->province_id;
                $inspection->district_id = $request->district_id;
                $inspection->subdistrict_id = $request->subdistrict_id;
                $inspection->address = $request->address;
                $inspection->name = $request->name;
                $inspection->save();
                if ($inspection->photo) {
                    Storage::delete($inspection->photo);
                }
                $path = $this->saveImage_gas($request, 'photo', $inspection->id);
                $inspection->photo = $path;
                $inspection->save();
            }else{
                $inspection = new AddressEms;
                $inspection->inspection_id = $request->inspection_id;
                $inspection->province_id = $request->province_id;
                $inspection->district_id = $request->district_id;
                $inspection->subdistrict_id = $request->subdistrict_id;
                $inspection->address = $request->address;
                $inspection->name = $request->name;
                $inspection->save();
                if ($inspection->photo) {
                    Storage::delete($inspection->photo);
                }
                $path = $this->saveImage_gas($request, 'photo', $inspection->id);
                $inspection->photo = $path;
                $inspection->save();
            }

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $inspection);
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
            $validator = Validator::make(request()->all(), [
                'ems' => 'required',
                'province_id' => 'required|numeric',
                'district_id' => 'required|numeric',
                'subdistrict_id' => 'required|numeric',
                'address' => 'required',
                'name' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors()->toJson(), 400);
            }
            $ems = Inspections::find($id);
            if (!$ems) {
                return $this->returnErrorData('ไม่พบข้อมูล',404);
            }

            $ems->EMS = $request->ems;
            $ems->save();
            // return $ems->EMS;
            $inspection = AddressEms::where('inspection_id',$ems->id)->first();
            if(!$inspection){
                return $this->returnErrorData('ไม่พบข้อมูล',404);
            }
            $inspection->province_id = $request->province_id;
            $inspection->district_id = $request->district_id;
            $inspection->subdistrict_id = $request->subdistrict_id;
            $inspection->address = $request->address;
            $inspection->name = $request->name;
            $inspection->save();
            if ($inspection->photo) {
                Storage::delete($inspection->photo);
            }
            $path = $this->saveImage_AddressEms($request, 'photo', $inspection->id);
            $inspection->photo = $path;
            $inspection->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $inspection);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }
}

