<?php

namespace App\Http\Controllers;

use App\Models\Branches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BranchesController extends Controller
{
    /** DB paths are stored as storage/Branches/... — public disk root is storage/app/public */
    private function storagePublicRelativePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return ltrim(preg_replace('#^storage/+#', '', $path), '/');
    }

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
                $oldLogo = $this->storagePublicRelativePath($Item->logo);
                if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                    Storage::disk('public')->delete($oldLogo);
                }
                $name = 'logo';
                $picname = $Item->id;
                $newLogo = $this->saveImage_branchs($request, $name, $picname);
                if ($newLogo) {
                    $Item->logo = $newLogo;
                }
            }

            if ($request->hasFile('logo_under')) {
                $oldLogoUnder = $this->storagePublicRelativePath($Item->logo_under);
                if ($oldLogoUnder && Storage::disk('public')->exists($oldLogoUnder)) {
                    Storage::disk('public')->delete($oldLogoUnder);
                }
                $filename = 'logo_under';
                $picname = $Item->id;
                $newLogoUnder = $this->saveImageunder_branchs($request, $filename, $picname);
                if ($newLogoUnder) {
                    $Item->logo_under = $newLogoUnder;
                }
            }

            $Item->save();

            DB::commit();

            // บันทึก log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item->fresh());
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
                $oldLogo = $this->storagePublicRelativePath($item->logo);
                if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                    Storage::disk('public')->delete($oldLogo);
                }
                $filename = 'logo';
                $picname = $item->id;
                $newLogo = $this->saveImage_branchs($request, $filename, $picname);
                if ($newLogo) {
                    $item->logo = $newLogo;
                }
            }

            if ($request->hasFile('logo_under')) {
                $oldLogoUnder = $this->storagePublicRelativePath($item->logo_under);
                if ($oldLogoUnder && Storage::disk('public')->exists($oldLogoUnder)) {
                    Storage::disk('public')->delete($oldLogoUnder);
                }
                $filename = 'logo_under';
                $picname = $item->id;
                $newLogoUnder = $this->saveImageunder_branchs($request, $filename, $picname);
                if ($newLogoUnder) {
                    $item->logo_under = $newLogoUnder;
                }
            }

            $item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item->fresh());
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
