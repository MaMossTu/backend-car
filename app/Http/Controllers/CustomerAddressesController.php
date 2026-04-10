<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddresses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAddressesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $customer_id = $request->customer_id;
        $Item = CustomerAddresses::when($customer_id, function ($query) use ($customer_id) {
            return $query->where('customer_id', $customer_id);
        })->get();
        return $Item;
    }

    public function showID($id)
    {
        $CustomerAddresses = CustomerAddresses::where('id',$id)->with('Customer','Provinces','Districts','SubDistricts')->first();
        if (!$CustomerAddresses) {
            return $this->returnBadRequest('CustomerAddresses not found');
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
            if($request->is_main == 1){
                CustomerAddresses::where('customer_id', $request->customer_id)->update(['is_main' => 0]);
            }

            $Item = new CustomerAddresses;
            $Item->customer_id = $request->customer_id;
            $Item->province_id = $request->province_id;
            $Item->district_id = $request->district_id;
            $Item->subdistrict_id = $request->subdistrict_id;
            $Item->address = $request->address;
            $Item->zip_code = $request->zip_code;
            $Item->is_main = $request->input('is_main',0);

            $Item->load(['Provinces', 'Districts', 'SubDistricts']);

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
            if($request->is_main == 1){
                CustomerAddresses::where('customer_id', $request->customer_id)->update(['is_main' => 0]);
            }

            $item = CustomerAddresses::where('id', $id)->first();
            $item->customer_id = $request->input('customer_id');
            $item->province_id = $request->input('province_id');
            $item->district_id = $request->input('district_id');
            $item->subdistrict_id = $request->input('subdistrict_id');
            $item->address = $request->input('address');
            $item->zip_code = $request->input('zip_code');
            $item->is_main = $request->input('is_main',0);
            $item->load(['Provinces', 'Districts', 'SubDistricts']);

            $item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Update Success', $item);
        } catch (\Throwable $e) {
            DB::rollback();

            return $this->returnErrorData('system error please try again ' . $e, 404);
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

            $Item = CustomerAddresses::find($id);
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
    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;
        $customer_id = $request->customer_id;


        $columns = [
            'id',
            'address',
            'zip_code',
            'is_main',
            'customer_id',
            'province_id',
            'district_id',
            'subdistrict_id',
        ];

        $searchcolum = [
            'address',
            'zip_code'
        ];

        $query = CustomerAddresses::select($columns)
            ->with('Customer','Provinces','Districts','SubDistricts')
            ->when($customer_id, function ($query) use ($customer_id) {
                return $query->where('customer_id', $customer_id);
            });


        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);


        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search,$searchcolum) {
                foreach ($searchcolum as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
}
