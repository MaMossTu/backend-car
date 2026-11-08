<?php

namespace App\Http\Controllers;

use App\Models\Provinces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProvincesController extends Controller
{
    private function applyNameThPrefix(Provinces $item): void
    {
        $name = $item->name_th ?? '';
        if ($name === '') {
            return;
        }
        $item->name_th_prefix = preg_match('/^จ\./u', $name) ? $name : 'จ.' . $name;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = Provinces::all();
        return $Item;
    }

    public function showID($id)
    {
        $Provinces = Provinces::with('Districts')->where('id', $id)->get();
        if (!$Provinces) {
            return $this->returnBadRequest('Provinces not found');
        } else {
            return $this->returnSuccess('Success', $Provinces);
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
            $Item = new Provinces();
            $Item->name_th = $request->name_th;
            $Item->name_en = $request->name_en;
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
            $item = Provinces::where('id', $id)->first();
            $item->name_th = $request->input('name_th');
            $item->name_en = $request->input('name_en');
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

            $Item = Provinces::find($id);
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
    public function filter_District($id)
    {
        $provinces = Provinces::with('Districts')->where('id', $id)->get();
        // dd($provinces);
        if (!$provinces) {
            return $this->returnBadRequest('District not found');
        } else {
            return $this->returnSuccess('Success', $provinces);
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
            'name_th',
            'name_en'
        ];
        $searchcolumn = [ 'name_th', 'name_en'];

        $query = Provinces::select($columns);


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
