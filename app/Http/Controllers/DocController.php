<?php

namespace App\Http\Controllers;

use App\Models\Doc;
use App\Models\Services;
use App\Models\Employees;
use Illuminate\Http\Request;
use App\Models\Insurance_name;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DocController extends Controller
{
    public function index()
    {
        $docs = Doc::all();
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $docs);
    }

    public function showID($id)
    {
        $doc = Doc::with('documentType', 'engineer', 'insuranceName')->find($id);
        if (!$doc) {
            return $this->returnBadRequest('ไม่พบข้อมูลเอกสาร');
        } else {
            return $this->returnSuccess('Success', $doc);
        }
    }

    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        if ($request->engineer_id != null) {
            $engineer = Employees::find($request->engineer_id);
            if (!$engineer || !$engineer->is_engineer) {
                return $this->returnErrorData('ไม่พบพนักงานหรือพนักงานคนนี้ไม่ใช่วิศวกร', 404);
            }
        }

        if ($request->insurance_names_id) {
            $insurance_names = Insurance_name::find($request->insurance_names_id);
            if (!$insurance_names) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }
        }
        if(!$request->type_doc){
            return $this->returnErrorData('กรุณาระบุประเภทเอกสาร', 404);
        }

        DB::beginTransaction();

        try {
            $doc = new Doc();
            $doc->no = $request->no;
            $doc->name = $request->name;
            $doc->document_type_id = $request->document_type_id;
            $doc->engineer_id = $request->engineer_id;
            $doc->insurance_names_id = $request->insurance_names_id;
            $doc->type_vehicle = $request->type_vehicle;
            $doc->type_gas = $request->type_gas;
            $doc->type_job = $request->type_job;
            $doc->type_doc = $request->type_doc;
            $doc->save();

            DB::commit();

            $service = new Services();
            $service->name = $doc->name;
            $service->price = 0;
            $service->type = 'service';
            $service->qty = 0;
            $service->doc_id = $doc->id;
            $service->service_type_id = $request->service_type_id;

            $service->save();

            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $doc->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('เพิ่มเอกสารสำเร็จ', $doc);
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

        if ($request->engineer_id) {
            $engineer = Employees::find($request->engineer_id);
            if (!$engineer || !$engineer->is_engineer) {
                return $this->returnErrorData('ไม่พบพนักงานหรือพนักงานคนนี้ไม่ใช่วิศวกร', 404);
            }
        }

        if ($request->insurance_names_id) {
            $insurance_names = Insurance_name::find($request->insurance_names_id);
            if (!$insurance_names) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }
        }
        if(!$request->type_doc){
            return $this->returnErrorData('กรุณาระบุประเภทเอกสาร', 404);
        }

        DB::beginTransaction();

        try {
            $doc = Doc::find($id);
            if (!$doc) {
                return $this->returnBadRequest('data not found');
            }
            $doc->no = $request->no;
            $doc->name = $request->name;
            $doc->document_type_id = $request->document_type_id;
            $doc->engineer_id = $request->engineer_id;
            $doc->insurance_names_id = $request->insurance_names_id;
            $doc->type_vehicle = $request->type_vehicle;
            $doc->type_gas = $request->type_gas;
            $doc->type_job = $request->type_job;
            $doc->type_doc = $request->type_doc;
            $doc->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $doc->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('แก้ไขเอกสารสำเร็จ', $doc);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
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
            $doc = Doc::findOrFail($id);
            $doc->delete();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'ลบเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $doc->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('ลบเอกสารสำเร็จ', $doc);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function getPage(Request $request)
    {
        $length = $request->length ?? 10;
        $order = $request->order ?? [['column' => 0, 'dir' => 'asc']];
        $search = $request->search ?? ['value' => ''];
        $start = $request->start ?? 0;
        $page = intval($start / $length) + 1;
        $type = $request->type;

        $columns = [
            'id',
            'no',
            'name',
            'document_type_id',
            'engineer_id',
            'insurance_names_id',
            'type_vehicle',
            'type_gas',
            'type_job',
            'type_doc',
            'created_at',
            'updated_at',
        ];
        $searchColumns = [
            'id',
        ];

        $query = Doc::with(['documentType', 'engineer', 'insuranceName']);

        if ($type) {
            $query->where('type_doc', $type);
        }
        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
}
