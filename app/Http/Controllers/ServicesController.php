<?php

namespace App\Http\Controllers;

use App\Models\Services;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceTransactions;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;
use Illuminate\Support\Facades\Validator;

class ServicesController extends Controller
{

    public function index()
    {
        $Item = Services::whereBetween('id', [1, 16])->get();
        return $Item;
    }

    public function indexall()
    {
        $Item = Services::get();
        return $Item;
    }

    public function showID($id)
    {
        $Services = Services::find($id);
        if (!$Services) {
            return $this->returnBadRequest('Services not found');
        } else {
            return $this->returnSuccess('Success', $Services);
        }
    }
    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:service,product',
            'name' => 'required',
            'price' => 'required',
        ],[
            'date.required' => 'กรุณาระบุประเภท',
            'date.in' => 'ประเภทไม่ถูกต้อง',
            'name.required' => 'กรุณาระบุชื่อ',
            'price.required' => 'กรุณาระบุราคา',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        try {
            $Item = new Services();
            $Item->name = $request->name;
            $Item->description = $request->description;
            $Item->price = $request->price;
            $Item->type = $request->type;
            $Item->qty = $request->input('qty', 0);
            $Item->doc_id = $request->doc_id;
            $Item->product_type_id = $request->product_type_id;
            $Item->service_type_id = $request->service_type_id;
            if ($request->hasFile('image')) {
                $picname = str::random(8);
                $name = $Item->name;
                $Item->image = $this->save_item($request->file('image'), $picname, $name);
            }
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
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:service,product',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'qty' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048' // จำกัดขนาดรูปภาพไม่เกิน 2MB
        ], [
            'type.required' => 'กรุณาระบุประเภท',
            'type.in' => 'ประเภทไม่ถูกต้อง',
            'name.required' => 'กรุณาระบุชื่อ',
            'price.required' => 'กรุณาระบุราคา',
            'price.numeric' => 'ราคาต้องเป็นตัวเลข',
            'qty.integer' => 'จำนวนต้องเป็นตัวเลข',
            'image.image' => 'ไฟล์ต้องเป็นรูปภาพ',
            'image.max' => 'ขนาดรูปภาพต้องไม่เกิน 2MB'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        try {
            $item = Services::find($id);
            if (!$item) {
                return response()->json(['message' => 'ไม่พบข้อมูล'], 404);
            }

            $item->name = $request->input('name');
            $item->description = $request->input('description', $item->description);
            $item->price = $request->input('price');
            $item->type = $request->input('type');
            $item->qty = $request->input('qty', $item->qty);
            $item->doc_id = $request->input('doc_id', $item->doc_id);
            // $item->product_type_id = $request->input('product_type_id', $item->product_type_id);
            $item->product_type_id = $request->product_type_id;
            if($request->service_type_id == "null"){
                $item->service_type_id = null;
            }else{
                $item->service_type_id = $request->input('service_type_id', null);
            }

            if ($request->hasFile('image')) {
                if ($item->image && Storage::exists($item->image)) {
                    Storage::delete($item->image);
                }

                $file = $request->file('image');
                $picname = Str::random(8);
                $name = $item->name;
                $item->image = $this->save_item($file, $picname, $name);
            }

            $item->save();
            DB::commit();

            // log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่อีกครั้ง :'.$e, 404);
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
            if (in_array($id, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16])) {
                return $this->returnErrorData('ไม่สามารถลบข้อมูลได้', 404);
            }

            $Item = Services::find($id);
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
    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;
        $type = $request->type;
        $product_type_id = $request->product_type_id;

        $columns = [
            'id',
            'name',
            'description',
            'price',
            'type',
            'qty',
            'image',
            'doc_id',
            'product_type_id',
            'service_type_id',
        ];
        $searchcolumn = [ 'name',
        'description',
        'price'];

        $query = Services::select($columns)->with(['ProductType', 'Doc', 'serviceType']);


        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        if($type){
            $query->where('type',$type);
        }
        if($product_type_id){
            $query->where('product_type_id',$product_type_id);
        }

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
}
