<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LineItems;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LineItemController extends Controller
{

    public function index()
    {
        $Item = LineItems::all();
        return $Item;
    }

    public function showID($id)
    {
        $installment = LineItems::find($id);
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
            $Services = Services::find($request->service_id);

            if (!$Services) {
                throw new \Exception('ไม่พบข้อมูล Services');
            }
            $stock = $Services->qty;

            $LineItem = new LineItems();
            $LineItem->work_order_id = $request->work_order_id;
            $LineItem->employee_id = $request->employee_id;
            $LineItem->item_id = $request->item_id;
            $LineItem->vendor_id = $request->vendor_id;
            $LineItem->service_id = $request->service_id;
            $LineItem->date = $request->date;
            $LineItem->stock = $stock;
            $LineItem->qty = $request->qty;
            $LineItem->remark = $request->remark;
            // $LineItem->operation = $request->operation;
            $LineItem->type = $request->type;

            if ($request->type === 'withdraw') {
                $LineItem->balance = $stock - $request->qty;
                if ($LineItem->balance < 0) {
                    return $this->returnErrorData('จำนวนสินค้าไม่เพียงพอ', 404);
                }
                $Services->qty = $LineItem->balance;
            } elseif ($request->type === 'deposit') {
                $LineItem->balance = $stock + $request->qty;
                $Services->qty = $LineItem->balance;
            } else {
                throw new \Exception('Invalid type');
            }

            $LineItem->save();
            $Services->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'เพิ่มรายการข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของ item ID: ' . $request->item_id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $LineItem, $Services);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }
        $LineItem = LineItems::find($id);
        if (!$LineItem) {
            return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
        }

        $Services = Services::find($LineItem->service_id);
        if (!$Services) {
            return $this->returnErrorData('ไม่พบข้อมูล Service', 404);
        }
        if ($LineItem->type === 'withdraw') {
            $Services->increment('qty', $LineItem->qty);
        } elseif ($LineItem->type === 'deposit') {
            $Services->decrement('qty', $LineItem->qty);
        }

        DB::beginTransaction();
        try {

            // อัปเดตข้อมูล LineItem
            $LineItem->work_order_id = $request->input('work_order_id');
            $LineItem->employee_id = $request->input('employee_id');
            $LineItem->item_id = $request->input('item_id');
            $LineItem->vendor_id = $request->input('vendor_id');
            $LineItem->service_id = $request->input('service_id');
            $LineItem->stock = $Services->qty;
            $LineItem->date = $request->input('date');
            $LineItem->remark = $request->input('remark');
            $LineItem->type = $request->input('type');
            $LineItem->qty = $request->input('qty');
            // คำนวณยอดคงเหลือใหม่

            if ($request->input('type') == 'withdraw') {
                $LineItem->balance = $Services->qty - $request->input('qty');
                if ($LineItem->balance < 0) {
                    return $this->returnErrorData('จำนวนสินค้าไม่เพียงพอ', 404);
                }
                $Services->qty = $LineItem->balance;
            } elseif ($request->input('type') == 'deposit') {
                $LineItem->balance = $Services->qty + $request->input('qty');
                $Services->qty = $LineItem->balance;
            }

            $LineItem->save();
            $Services->save();
            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->firstname;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $LineItem);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
        }
    }

    public function update_operation(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $Item = LineItems::find($id);

            if (!$Item) {
                throw new \Exception('ไม่พบข้อมูลรายการ');
            }

            $Item->operation = $request->input('operation');

            if ($Item->operation === 'finish') {
                $item = Item::find($Item->item_id);

                if (!$item) {
                    throw new \Exception('ไม่พบข้อมูล item');
                }

                $type = strtolower($Item->type);

                if ($type === 'withdraw') {
                    $Item->balance = $Item->stock - $Item->qty;
                    $item->qty = $Item->balance;
                } elseif ($type === 'deposit') {
                    $Item->balance = $Item->stock + $Item->qty;
                    $item->qty = $Item->balance;
                } else {
                    throw new \Exception('Invalid type: ' . $Item->type);
                }

                $item->save();
            }

            $Item->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ของรายการ ID: ' . $id . ' เป็น ' . $Item->operation;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

    // public function destroy($id)
    // {
    //     $loginBy = Auth()->user();
    //     if (!isset($loginBy)) {
    //         return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $LineItem = LineItems::find($id);
    //         if (!$LineItem) {
    //             return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
    //         }

    //         $Services = Services::find($LineItem->service_id);
    //         if (!$Services) {
    //             return $this->returnErrorData('ไม่พบข้อมูล Service', 404);
    //         }
    //         if ($LineItem->type === 'withdraw') {
    //             $Services->qty += $LineItem->qty;
    //         } elseif ($LineItem->type === 'deposit') {
    //             $Services->qty -= $LineItem->qty;
    //             if ($Services->qty < 0) {
    //                 return $this->returnErrorData('จำนวนสินค้าไม่เพียงพอ', 404);
    //             }
    //         }
    //         $Services->save();
    //         $LineItem->delete();

    //         DB::commit();

    //         //log
    //         $userId = $loginBy->id;
    //         $type = 'ลบข้อมูล';
    //         $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . "id" . $type . $LineItem->$id;
    //         $this->Log($userId, $description, $type);

    //         return $this->returnUpdateReturnData('delete success', $LineItem);
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e, 404);
    //     }
    // }

    public function destroy($id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $LineItem = LineItems::find($id);
            if (!$LineItem) {
                return $this->returnErrorData('ไม่พบข้อมูลรายการ', 404);
            }

            $LineItemAll = LineItems::where('service_id', $LineItem->service_id)
            ->where('created_at', '>', $LineItem->created_at)
            ->get();

            foreach ($LineItemAll as $value) {
                $value->stock += $LineItem->qty;
                $value->balance = $value->stock - $value->qty;
                $value->save();
            }

            $Services = Services::find($LineItem->service_id);
            if (!$Services) {
                return $this->returnErrorData('ไม่พบข้อมูล Service', 404);
            }

            if ($LineItem->type === 'withdraw') {
                $Services->qty += $LineItem->qty;
            } elseif ($LineItem->type === 'deposit') {
                $Services->qty -= $LineItem->qty;
                if ($Services->qty < 0) {
                    return $this->returnErrorData('จำนวนสินค้าไม่เพียงพอ', 404);
                }
            }

            $Services->save();
            $LineItem->delete();

            DB::commit();

            $this->Log(
                $loginBy->id,
                'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการลบ LineItem ID ' . $id,
                'ลบข้อมูล'
            );

            return $this->returnSuccess('ลบข้อมูลสำเร็จ', ['id' => $id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง: ' . $e->getMessage(), 500);
        }
    }

    public function getPage(Request $request)
    {
        $type = $request->type; //Deposit,Withdraw
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = intval($start / $length) + 1;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $selectColumns = [
            'id','work_order_id', 'item_id', 'service_id', 'stock', 'qty', 'balance', 'type', 'date', 'remark',
        ];

        $searchColumns = [
            'item_id', 'operation', 'type', 'date',
        ];

        $query = LineItems::select($selectColumns)->with('service', 'WorkOrder');

        if (!empty($start_date) && !empty($end_date)) {
            $query->whereBetween('date', [$start_date, $end_date]);
        }
        if (!empty($type)) {
            $query->where('type', $type);
        }

        $orderBy = $selectColumns[$order[0]['column']] ?? 'item_id';
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
