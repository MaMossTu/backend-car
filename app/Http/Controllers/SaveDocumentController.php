<?php

namespace App\Http\Controllers;

use App\Models\Inspections;
use App\Models\Save_document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SaveDocumentController extends Controller
{

    public function index()
    {
        $Item = Save_document::all();
        return $Item;
    }
    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $inspection = Inspections::find($request->inspection_id);
            if (!$inspection) {
                return $this->returnError('ไม่พบข้อมูลการตรวจสอบ');
            }
            $no = $inspection->no;

            $Item = new Save_document();
            $Item->inspection_id = $request->inspection_id;
            $Item->name = $request->name;

            if ($request->hasFile('document')) {
                $filename = 'document';
                $picname = $request->name;
                $Item->document = $this->save_Document($request, $filename, $picname, $no);
            }

            $Item->save();;

            DB::commit();

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
        if (!$loginBy) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {

            $item = Save_document::where('id', $id)->first();
            if (!$item) {
                return $this->returnError('ไม่พบข้อมูลเอกสาร');
            }
            $inspection_id = $item->inspection_id;
            $no = $inspection->no;
            if (is_null($no)) {
                return $this->returnError('ไม่พบหมายเลขการตรวจสอบ');
            }
            $item->name = $request->input('name');
            if ($request->hasFile('document')) {
                $filename = 'document';
                $picname = $request->input('name');
                if ($item->document) {
                    $oldFilePath = public_path('storage/' . $item->document);
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $item->document = $this->save_Document($request, $filename, $picname, $no);
            }

            $item->save();

            DB::commit();

            // Log the update action
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('ระบบเกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
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

            $Item = Save_document::find($id);
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
}
