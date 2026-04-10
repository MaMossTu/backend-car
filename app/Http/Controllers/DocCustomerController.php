<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\DocCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocCustomerController extends Controller
{
    public function index()
    {
        $docs = DocCustomer::with(['doc', 'customer'])->get();
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $docs);
    }

    public function indexby_customer($id)
    {
        $customer_id = $id;
        $docs = DocCustomer::with(['doc', 'customer'])
            ->when($customer_id, function ($query) use ($customer_id) {
                return $query->where('customer_id', $customer_id);
            })
            ->get();
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $docs);
    }

    public function showID($id)
    {
        $doc = DocCustomer::with(['doc', 'customer'])->find($id);
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

        if (!$request->docs_id) {
            return $this->returnErrorData('กรุณาเลือกเอกสาร',404);
        }
        if (!$request->customer_id) {
            return $this->returnErrorData('กรุณาเลือกลูกค้าหลังร้าน',404);
        }

        DB::beginTransaction();

        try {
            $doc = new DocCustomer();
            $doc->docs_id = $request->docs_id;
            $doc->customer_id = $request->customer_id;
            $doc->total_qty = $request->total_qty;
            $doc->price = $request->price;
            $doc->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'จับคู่เอกสารลูกค้า';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $doc->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('จับคู่เอกสารลูกค้าสำเร็จ', $doc);
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

        if (!$request->docs_id) {
            return $this->returnErrorData('กรุณาเลือกเอกสาร',404);
        }
        if (!$request->customer_id) {
            return $this->returnErrorData('กรุณาเลือกลูกค้าหลังร้าน',404);
        }

        DB::beginTransaction();

        try {
            $doc = DocCustomer::find($id);
            if (!$doc) {
                return $this->returnBadRequest('data not found');
            }
            $doc->docs_id = $request->docs_id;
            $doc->customer_id = $request->customer_id;
            $doc->total_qty = $request->total_qty;
            $doc->price = $request->price;
            $doc->save();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขสต็อกเอกสาร';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $doc->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('แก้ไขจับคู่เอกสารลูกค้าสำเร็จ', $doc);
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
            $doc = DocCustomer::findOrFail($id);
            $doc->delete();

            DB::commit();

            // Log
            $userId = $loginBy->id;
            $type = 'ลบจับคู่เอกสารลูกค้า';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $doc->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('ลบจับคู่เอกสารลูกค้า', $doc);
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


        $columns = [
            'id',
            'docs_id',
            'customer_id',
            'total_qty',
            'price',
            'created_at',
            'updated_at'
        ];
        $searchColumns = [
            'docs_id',
            'customer_id',
        ];

        $query = DocCustomer::with([
            'doc.documentType',
            'doc.engineer',
            'doc.insuranceName',
            'customer']);

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
