<?php

namespace App\Http\Controllers;

use App\Models\ServiceDocType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ServiceDocTypeController extends Controller
{
    /**
     * แสดงรายการทั้งหมดของ ServiceDocType
     */
    public function index()
    {
        $serviceDocTypes = ServiceDocType::with(['documentType', 'service'])->get();
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $serviceDocTypes);
    }

    /**
     * แสดงข้อมูล ServiceDocType ตาม ID
     */
    public function showID($id)
    {
        $serviceDocType = ServiceDocType::with(['documentType', 'service'])->find($id);
        if (!$serviceDocType) {
            return $this->returnBadRequest('ไม่พบข้อมูลประเภทเอกสารบริการ');
        } else {
            return $this->returnSuccess('Success', $serviceDocType);
        }
    }

    /**
     * สร้าง ServiceDocType ใหม่
     */
    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $serviceDocType = new ServiceDocType();
            $serviceDocType->document_types_id = $request->document_types_id;
            $serviceDocType->services_id = $request->services_id;
            $serviceDocType->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มประเภทเอกสารบริการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $serviceDocType->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('เพิ่มประเภทเอกสารบริการสำเร็จ', $serviceDocType);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    /**
     * อัปเดตข้อมูล ServiceDocType ตาม ID
     */
    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $serviceDocType = ServiceDocType::find($id);
            if (!$serviceDocType) {
                return $this->returnBadRequest('ไม่พบข้อมูลประเภทเอกสารบริการ');
            }

            $serviceDocType->document_types_id = $request->document_types_id;
            $serviceDocType->services_id = $request->services_id;
            $serviceDocType->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขประเภทเอกสารบริการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $serviceDocType->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('แก้ไขประเภทเอกสารบริการสำเร็จ', $serviceDocType);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    /**
     * ลบข้อมูล ServiceDocType ตาม ID
     */
    public function destroy($id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $serviceDocType = ServiceDocType::findOrFail($id);
            $serviceDocType->delete();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'ลบประเภทเอกสารบริการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $serviceDocType->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('ลบประเภทเอกสารบริการสำเร็จ', $serviceDocType);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    /**
     * เรียกดูข้อมูลแบบแบ่งหน้า (Pagination)
     */
    public function getPage(Request $request)
    {
        $length = $request->length ?? 10;
        $order = $request->order ?? [['column' => 0, 'dir' => 'asc']];
        $search = $request->search ?? ['value' => ''];
        $start = $request->start ?? 0;
        $page = intval($start / $length) + 1;

        $columns = [
            'id',
            'document_types_id',
            'services_id',
            'created_at',
            'updated_at',
        ];
        $searchColumns = [
            'id',
        ];

        $query = ServiceDocType::with(['documentType', 'service']);

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
