<?php

namespace App\Http\Controllers;

use App\Models\Branches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BranchesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = Branches::all();
        return $Item;
    }
    public function showID($id)
    {
        $Branches = Branches::find($id);
        if (!$Branches) {
            return $this->returnBadRequest('Branches not found');
        } else {
            return $this->returnSuccess('Success', $Branches);
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
            $Item = new Branches;
            $Item->name = $request->name;
            $Item->business_name = $request->business_name;
            $Item->phone = $request->phone;
            $Item->email = $request->email;
            $Item->province_id = $request->province_id;
            $Item->district_id = $request->district_id;
            $Item->subDistrict_id = $request->subDistrict_id;
            $Item->license_number = $request->license_number;
            $Item->postal_code = $request->postal_code;
            $Item->address = $request->address;
            $Item->tax_id = $request->tax_id;
            $Item->open_close_time = $request->open_close_time;
            $Item->open_date = $request->open_date;
            $Item->cal_com = $request->cal_com;
            $Item->approval_letter_number = $request->approval_letter_number;
            $Item->expiration_date = $request->expiration_date;
            $Item->save();

            if ($request->hasFile('logo')) {
                if ($Item->logo && Storage::disk('public')->exists($Item->logo)) {
                    Storage::disk('public')->delete($Item->logo);
                }
                $name = 'logo';
                $picname = $Item->id;
                $Item->logo = $this->saveImage_branchs($request, $name, $picname);
            }

            if ($request->hasFile('logo_under')) {
                if ($Item->logo_under && Storage::disk('public')->exists($Item->logo_under)) {
                    Storage::disk('public')->delete($Item->logo_under);
                }
                $filename = 'logo_under';
                $picname = $Item->id;
                $Item->logo_under = $this->saveImageunder_branchs($request, $filename, $picname);
            }

            $Item->save();

            DB::commit();

            // บันทึก log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
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
            $item = Branches::where('id', $id)->first();
            $item->name = $request->input('name');
            $item->business_name = $request->input('business_name');
            $item->phone = $request->input('phone');
            $item->email = $request->input('email');
            $item->license_number = $request->input('license_number');
            $item->cal_com = $request->input('cal_com');
            $item->subDistrict_id = $request->input('subDistrict_id');
            $item->district_id = $request->input('district_id');
            $item->province_id = $request->input('province_id');
            $item->address = $request->input('address');
            $item->postal_code = $request->input('postal_code');
            $item->tax_id = $request->input('tax_id');
            $item->open_close_time = $request->open_close_time;
            $item->open_date = $request->open_date;
            $item->cal_com = $request->cal_com;
            $item->approval_letter_number = $request->approval_letter_number;
            $item->expiration_date = $request->expiration_date;
            $item->save();

            if ($request->hasFile('logo')) {
                if ($item->logo && Storage::disk('public')->exists($item->logo)) {
                    Storage::disk('public')->delete($item->logo);
                }
                $filename = 'logo';
                $picname = $item->id;
                $item->logo = $this->saveImage_branchs($request, $filename, $picname);
            }

            if ($request->hasFile('logo_under')) {
                if ($item->logo_under && Storage::disk('public')->exists($item->logo_under)) {
                    Storage::disk('public')->delete($item->logo_under);
                }
                $filename = 'logo_under';
                $picname = $item->id;
                $item->logo_under = $this->saveImageunder_branchs($request, $filename, $picname);
            }

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

            $Item = Branches::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการลบ',404);
            }
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


        $columns = [
            'id',
            'name',
            'business_name',
            'phone',
            'logo',
            'email',
            'license_number',
            'cal_com',
            'address',
            'postal_code',
            'tax_id',
            'open_close_time',
            'open_date',
            'cal_com',
            'approval_letter_number',
            'expiration_date',
            'logo_under'
        ];

        $searchcolumn = [
            'name',
            'business_name',
            'phone',
            'logo',
            'email'
        ];
        $query = Branches::select($columns);


        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);


        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search,$searchcolumn) {
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
