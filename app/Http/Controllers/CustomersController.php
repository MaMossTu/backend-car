<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\formcustomerImport;

class CustomersController extends Controller
{
    private function customerSearchColumns(): array
    {
        return [
            'name',
            'lname',
            'no',
            'email',
            'phone_number1',
            'phone_number2',
            'tax_id',
            'type',
            'is_headquarter',
        ];
    }

    private function applyCustomerSearch($query, string $searchTerm)
    {
        if ($searchTerm === '') {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm) {
            foreach ($this->customerSearchColumns() as $column) {
                $q->orWhere($column, 'like', "%{$searchTerm}%");
            }
        });
    }

    private function getSearchTerm(Request $request): string
    {
        return trim((string) ($request->input('key') ?? data_get($request->input('search'), 'value', '')));
    }
    public function getList(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $type = $request->input('type');
            $searchTerm = $this->getSearchTerm($request);
            $order = $request->input('order', []);
            $columns = [
                'id',
                'no',
                'name',
                'lname',
                'email',
                'phone_number1',
                'phone_number2',
                'tax_id',
                'type',
                'is_headquarter',
            ];

            $query = Customers::with([
                    'Customer_Addresses.Provinces',
                    'Customer_Addresses.Districts',
                    'Customer_Addresses.SubDistricts'
                ])
                ->when($type, function ($query, $type) {
                    return $query->where('type', $type);
                });

            $this->applyCustomerSearch($query, $searchTerm);

            $orderColumnIndex = data_get($order, '0.column');
            $orderDir = strtolower((string) data_get($order, '0.dir', 'asc'));
            $orderBy = $columns[$orderColumnIndex] ?? 'id';

            if (!in_array($orderDir, ['asc', 'desc'], true)) {
                $orderDir = 'asc';
            }

            $items = $query
                ->orderBy($orderBy, $orderDir)
                ->limit($limit)
                ->get();

            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);

        } catch (\Exception $e) {
            return $this->returnErrorData($e->getMessage(), 500);
        }
    }
    public function index()
    {
        
        $Item = Customers::select(
            'id','name','lname', // ฟิลด์อื่น ๆ ที่ต้องการ
            DB::raw("TRIM(CONCAT(COALESCE(name,''),' ',COALESCE(lname,''))) AS name")
        )->get();
        return $this->returnSuccess('Success', $Item);
    }

    public function showall_backoffice()
    {
        $Item = Customers::where('type','agent')->get();
        return $this->returnSuccess('Success', $Item);
    }

    public function showID($id)
    {
        $Customers = Customers::where('id',$id)
            ->with('Customer_Addresses.Provinces')
            ->with('Customer_Addresses.Districts')
            ->with('Customer_Addresses.SubDistricts')
            ->first();
        if (!$Customers) {
            return $this->returnBadRequest('Customers not found');
        } else {
            return $this->returnSuccess('Success', $Customers);
        }
    }

    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $prefix = "";
        if ($request->type == "personal") {
            $prefix = "P-";
        } else if ($request->type == "vendor") {
            $prefix = "CS-";
        } else if ($request->type == "agent") {
            $prefix = "A-";
        }
        $no = IdGenerator::generate([
            'table' => 'customers',
            'field' => 'no',
            'length' => 8,
            'prefix' => $prefix,
            'reset_on_prefix_change' => true
        ]);

        DB::beginTransaction();

        try {
            $Item = new Customers;
            $Item->no = $no;
            $Item->name = $request->name;
            $Item->lname = $request->lname;
            $Item->email = $request->email;
            $Item->phone_number1 = $request->phone_number1;
            $Item->phone_number2 = $request->phone_number2;
            $Item->tax_id = $request->tax_id;
            $Item->type = $request->type;
            $Item->is_headquarter = $request->is_headquarter;
            // $Item->is_backoffice = $request->is_backoffice;
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

        try {
            $item = Customers::where('id', $id)->first();
            $item->name = $request->input('name');
            $item->lname = $request->input('lname');
            $item->email = $request->input('email');
            $item->phone_number1 = $request->input('phone_number1');
            $item->phone_number2 = $request->input('phone_number2');
            $item->tax_id = $request->input('tax_id');
            $item->type = $request->input('type');
            $item->is_headquarter = $request->input('is_headquarter');
            // $item->is_backoffice = $request->input('is_backoffice');
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

            $Item = Customers::find($id);
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
        $start = $request->start;
        $page = $start / $length + 1;

        $type = $request->type;
        $searchTerm = $this->getSearchTerm($request);

        $columns = [
            'id',
            'no',
            'name',
            'lname',
            'email',
            'phone_number1',
            'phone_number2',
            'tax_id',
            'type',
            'is_headquarter',
        ];

        $query = Customers::select($columns)
            ->with('Customer_Addresses.Provinces')
            ->with('Customer_Addresses.Districts')
            ->with('Customer_Addresses.SubDistricts')
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            });


        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        $this->applyCustomerSearch($query, $searchTerm);

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
    public function uploadExcel(Request $request){
        $validator = Validator::make($request->all(),[
            'file' => 'required|file|mimes:xlsx,csv',
        ]);
        if ($validator->failed()){
            return $this->returnBadRequest($validator->errors(), 'Invalid file');
        }

        try {

            // Excel::import(new formcustomerImport, $request->file('file'));
            $import = Excel::import(new formcustomerImport, $request->file('file'));
            $importedData = $import;
            return response()->json(['message' => 'File uploaded and imported successfully!',
        'imported_data' => $importedData,], 200);
        } catch (\Exception $e) {
            return $this->returnBadRequest($e->getMessage(), 'Error uploading file');
        }
    }
}

