<?php

namespace App\Http\Controllers;

use App\Models\Districts;
use App\Models\SubDistricts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubDistrictsController extends Controller
{
    private function applyNameThPrefix(SubDistricts $item): void
    {
        $name = $item->name_th ?? '';
        if ($name === '') {
            return;
        }
        if (preg_match('/^(แขวง|ต\.)/u', $name)) {
            $item->name_th_prefix = $name;

            return;
        }
        $provinceId = (int) Districts::where('id', $item->district_id)->value('province_id');
        $item->name_th_prefix = ($provinceId === 1 ? 'แขวง' : 'ต.') . $name;
    }

    public function index()
    {
        $Item = SubDistricts::all();
        return $Item;
    }
    public function showID($id)
    {
        $SubDistricts = SubDistricts::find($id);
        if (!$SubDistricts) {
            return $this->returnBadRequest('SubDistricts not found');
        } else {
            return $this->returnSuccess('Success', $SubDistricts);
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
            $Item = new SubDistricts();
            $Item->district_id = $request->district_id;
            $Item->name_th = $request->name_th;
            $Item->name_en = $request->name_en;
            $Item->zip_code = $request->zip_code;
            $this->applyNameThPrefix($Item);
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
            $item = SubDistricts::where('id', $id)->first();
            $item->district_id = $request->input('district_id');
            $item->name_th = $request->input('name_th');
            $item->name_en = $request->input('name_en');
            $item->zip_code = $request->input('zip_code');
            $this->applyNameThPrefix($item);
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

            $Item = SubDistricts::find($id);
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
        $districtId = $request->input('district_id');


        $columns = [
            'id',
            'name_th',
            'name_en',
            'zip_code',
            'district_id'
        ];
        $searchcolumn =
            ['name_th',
            'name_en',
            'zip_code'];
        $query = SubDistricts::select($columns)->with(['Districts.Provinces']);

        if (!empty($districtId)) {
            $query->where('district_id', $districtId);
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
}
