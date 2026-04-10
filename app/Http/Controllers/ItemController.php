<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ItemController extends Controller
{

    public function index()
    {
        $Item = Item::all();
        return $Item;
    }

    public function showID($id)
    {
        $installment = Item::find($id);
        if (!$installment) {
            return $this->returnBadRequest('installment not found');
        } else {
            return $this->returnSuccess('Success', $installment);
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
            $Item = new Item();
            $Item->name = $request->name;
            $Item->price = $request->price;
            $Item->QTY = $request->QTY;
            $Item->item_type_id = $request->item_type_id;
            $Item->vendor_id = $request->vendor_id;
            $Item->descriptions = $request->descriptions;
            if ($request->hasFile('image')) {
                $picname = str::random(8);
                $name = $Item->name;
                $Item->image = $this->save_item($request->file('image'), $picname, $name);
            }
            $Item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
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
            $Item = Item::find($id);
            $Item->name = $request->input('name');
            $Item->image = $request->input('image');
            $Item->price = $request->input('price');
            $Item->QTY = $request->input('QTY');
            $Item->item_type_id = $request->input('item_type_id');
            $Item->vendor_id = $request->input('vendor_id',null);
            $Item->descriptions = $request->input('descriptions');

            if ($request->hasFile('image'))
            {
                $file = $request->file('image');
                $picname = str::random(8);
                $name = $Item->name;
                $Item->image = $this->save_item($file, $picname, $name);
            }
            $Item->save();
            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
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
            $Item = Item::find($id);
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . "id" . $type . $Item->$id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }
    public function getPage(Request $request)
{
    $length = $request->length;
    $order = $request->order;
    $search = $request->search;
    $start = $request->start;
    $page = intval($start / $length) + 1;

    $columns = [
        'id',
        'name',
        'QTY',
        'item_type_id',
        'vendor_id',
        'image',
        'price',
        'descriptions',
    ];

    $searchcolumn = [
        'name',
        'QTY'
    ];
    $query = Item::select($columns)->with('itemType:id,name');

    $orderByColumn = $columns[$order[0]['column']] ?? 'name';
    $query->orderBy($orderByColumn, $order[0]['dir']);

    if (!empty($search['value'])) {
        $query->where(function ($query) use ($search, $searchcolumn) {
            foreach ($searchcolumn as $column) {
                $query->orWhere($column, 'like', '%' . $search['value'] . '%');
            }
        });
    }

    $items = $query->paginate($length, ['*'], 'page', $page);

    if ($items->isNotEmpty()) {
        $No = ($page - 1) * $length;
        foreach ($items as $item) {
            $No++;
            $item->No = $No;
        }
    }

    return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
}



}
