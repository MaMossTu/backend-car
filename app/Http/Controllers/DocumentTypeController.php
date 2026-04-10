<?php

namespace App\Http\Controllers;

use App\Models\Document_type;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentTypeController extends Controller
{
    public function index()
    {
        $documentTypes = Document_type::all();
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $documentTypes);
    }

    public function showID($id)
    {
        $documentType = Document_type::find($id);
        if (!$documentType) {
            return $this->returnBadRequest('ไม่พบข้อมูลประเภทเอกสาร');
        } else {
            return $this->returnSuccess('Success', $documentType);
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
            $documentType = new Document_type();
            $documentType->name = $request->name;
            $documentType->description = $request->description;
            $documentType->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มประเภทเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $documentType->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('เพิ่มประเภทเอกสารสำเร็จ', $documentType);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
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
            $documentType = Document_type::findOrFail($id);
            $documentType->name = $request->name;
            $documentType->description = $request->description;
            $documentType->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขประเภทเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $documentType->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('แก้ไขประเภทเอกสารสำเร็จ', $documentType);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
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
            $documentType = Document_type::findOrFail($id);
            $documentType->delete();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'ลบประเภทเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $documentType->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('ลบประเภทเอกสารสำเร็จ', $documentType);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }

    public function getPage(Request $request)
    {
        $length = $request->length ?? 10;
        $order = $request->order ?? [['column' => 0, 'dir' => 'asc']];
        $search = $request->search ?? ['value' => ''];
        $start = $request->start ?? 0;
        $page = intval($start / $length) + 1;

        $columns = [
            'id',
            'name',
            'description',
            'created_at',
        ];
        $searchColumns = [
            'name',
            'description',
        ];

        $query = Document_type::query();

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
