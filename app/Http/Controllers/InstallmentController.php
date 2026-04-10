<?php

namespace App\Http\Controllers;

use App\Models\Inspections;
use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\returnValueMap;

class InstallmentController extends Controller
{
    public function index()
    {
        $check = installment::all();
        return $check;
    }

    public function showID($id)
    {
        $installment = installment::find($id);
        if (!$installment) {
            return $this->returnBadRequest('installment not found');
        } else {
            return $this->returnSuccess('Success', $installment);
        }
    }

    public function showID_by_inspection($id)
    {
        $installment = installment::where('inspection_id',$id)->get();
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
            $inspection = Inspections::find($request->inspection_id);

            if (!$inspection) {
                return $this->returnErrorData('ไม่พบข้อมูล',404);
            }

            $lastInstallmentvat = Installment::where('inspection_id', $request->inspection_id)
                ->where('type','vat')
                ->orderBy('created_at', 'desc') // เรียงลำดับตามวันที่สร้าง
                ->first();
            $lastInstallmentnonvat = Installment::where('inspection_id', $request->inspection_id)
                ->where('type','nonvat')
                ->orderBy('created_at', 'desc') // เรียงลำดับตามวันที่สร้าง
                ->first();
            $Item = new Installment();
            $Item->date = $request->date;
            $Item->inspection_id = $request->inspection_id;
            $Item->paid_type = $request->paid_type;
            $Item->description = $request->description;
            $Item->paid_price = $request->paid_price;
            $Item->type = $request->type;

            if($request->type =='nonvat'){
                if ($lastInstallmentnonvat) {
                    $Item->before_price = $lastInstallmentnonvat->after_price;
                } else {
                    $Item->before_price = $inspection->total_nonvatcal ?? 0;
                }
            }
            if($request->type =='vat'){
                if ($lastInstallmentvat) {
                    $Item->before_price = $lastInstallmentvat->after_price;
                } else {
                    $Item->before_price = $inspection->total_vatcal ?? 0;
                }
            }


            $Item->after_price = $Item->before_price - $Item->paid_price;
            if ($Item->after_price < 0) {
                $Item->after_price = 0;
            }

            // ถ้า after_price น้อยกว่าหรือเท่ากับ 0
            // if ($Item->after_price <= 0) {
            //     $inspection->status = 'finish';
            //     $inspection->save();
            // }
            $Item->save();

            DB::commit();

            // log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->description;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Add Data Success', $Item);
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
            $Installment = installment::find($id);
            if (!$Installment) {
                return $this->returnErrorData('ไม่พบข้อมูล',404);
            }

            // ตรวจสอบว่าเป็น Installment ล่าสุดของใบวางบิลหรือไม่
            // $latestInstallment = Installment::where('inspection_id', $Installment->inspection_id)
            //     ->orderBy('created_at', 'desc')
            //     ->first();

            // if ($latestInstallment->id !== $Installment->id) {
            //     return $this->returnErrorData('สามารถลบได้เฉพาะประวัติการจ่ายเงินล่าสุดของใบวางบิลเท่านั้น',404);
            // }

            // อัปเดตสถานะใบวางบิลเป็น 'แบ่งจ่าย'
            // $inspection = Inspections::find($Installment->inspection_id);
            // if ($inspection) {
            //     $inspection->status = 'overdue';
            //     $inspection->save();
            // }

            // หากไม่มี Installment ใดเหลืออยู่ ให้เปลี่ยนสถานะเป็น 'ค้างจ่าย'
            // $remainingInstallments = Installment::where('inspection_id', $Installment->inspection_id)->count();
            // if ($remainingInstallments == 0) {
            //     $inspection->status = 'unpaid';
            //     $inspection->save();
            // }
            // ลบ Installment
            $Installment->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบลูกค้า';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Installment);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('system error' . $e, 404);
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
            $Item = Installment::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูล Installment', 404);
            }

            $Item->paid_type = $request->paid_type;
            $Item->description = $request->description;

            if ($request->has('paid_price')) {
                $lastInstallment = Installment::where('inspection_id', $Item->inspection_id)
                    ->where('id', '<', $Item->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($lastInstallment) {
                    $Item->before_price = $lastInstallment->after_price;
                } else {
                    $inspection = Inspections::find($Item->inspection_id);
                    $Item->before_price = ($inspection->total) - ($inspection->discount);
                }

                $Item->paid_price = $request->paid_price;
                $Item->after_price = $Item->before_price - $Item->paid_price;

                if ($Item->after_price < 0) {
                    $Item->after_price = 0;
                }
            }

            $Item->save();

            DB::commit();

            // log
            $userId = $loginBy->id;
            $type = 'อัปเดตรายการข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type .'Installment id '.$Item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Update Data Success', $Item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }


    public function update_(Request $request,$id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }
        $id = $request->route('id');  // ดึง ID จาก URL
        DB::beginTransaction();
        try {
            $latestItem = Installment::where('inspection_id', $id)->orderBy('created_at', 'desc')->first();
            if (!$latestItem) {
                throw new \Exception('ไม่พบข้อมูลรายการล่าสุด');
            }

            $inspectionId = $latestItem->inspection_id;

            if ($inspectionId != null) {
                $inspection = Inspections::find($inspectionId);
                if (!$inspection) {
                    throw new \Exception('ไม่พบข้อมูลการตรวจสอบที่เกี่ยวข้อง');
                }
            } else {
                throw new \Exception('ไม่พบข้อมูลการตรวจสอบที่เกี่ยวข้อง');
            }

            $Item = new Installment();
            $Item->inspection_id = $inspectionId;
            $Item->before_price = $latestItem->after_price ?? $latestItem->before_price;

            $paidPrice = $request->input('paid_price');
            $description = $request->input('description');

            if ($paidPrice !== null) {
                if ($paidPrice > $Item->before_price) {
                    throw new \Exception('จำนวนเงินเกินที่ต้องจ่าย');
                }

                $Item->after_price = $Item->before_price - $paidPrice;

                if ($Item->after_price < 0) {
                    $Item->after_price = 0;
                }
                $Item->paid_price = $paidPrice;
            }

            if ($description !== null) {
                $Item->description = $description;
            }

            $Item->save();

            if ($Item->after_price == 0 && $inspection->status == 'overdue') {
                $inspection->status = 'overdue(success)';
                $inspection->save();
            }

            DB::commit();

            // log
            $userId = $loginBy->id;
            $type = 'เพิ่มข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' ' . $request->input('description');
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }


    public function invoice(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $columns = [
            'inspections.no',
            'inspections.date',
            'vehicles.license_plate',
            DB::raw("CONCAT(customers.name) as customer_name"),
            'services.name as service_name',
            'service_transactions.service_price as price'
        ];

        $query = Installment::select($columns)
            ->join('inspections', 'installments.inspection_id', '=', 'inspections.id')
            ->join('vehicles', 'inspections.vehicle_id', '=', 'vehicles.id')
            ->join('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->join('service_transactions', 'inspections.id', '=', 'service_transactions.inspections_id')
            ->join('services', 'service_transactions.service_id', '=', 'services.id');

        $orderBy = $columns[$order[0]['column']] ?? 'installments.id';
        $query->orderBy($orderBy, $order[0]['dir'] ?? 'asc');

        if (!empty($search['value'])) {
            $searchValue = '%' . $search['value'] . '%';
            $query->where(function ($query) use ($searchValue) {
                $query->where('installments.id', 'like', $searchValue)
                    ->orWhere('inspections.no', 'like', $searchValue)
                    ->orWhere('inspections.date', 'like', $searchValue)
                    ->orWhere('vehicles.license_plate', 'like', $searchValue)
                    ->orWhere(DB::raw("CONCAT(customers.first_name, ' ', customers.last_name)"), 'like', $searchValue)
                    ->orWhere('services.name', 'like', $searchValue)
                    ->orWhere('service_transactions.service_price', 'like', $searchValue);
            });
        }

        $items = $query->get();

        // Group items by inspection id
        $groupedItems = $items->groupBy('id');

        // Format the grouped items
        $formattedItems = $groupedItems->map(function ($group) {
            $firstItem = $group->first();
            return [
                'id' => $firstItem->id,
                'no' => $firstItem->no,
                'date' => $firstItem->date,
                'license_plate' => $firstItem->license_plate,
                'customer_name' => $firstItem->customer_name,
                'services' => $group->map(function ($item) {
                    return [
                        'service_name' => $item->service_name,
                        'price' => $item->price
                    ];
                })->values()
            ];
        });

        $pagination = [
            'current_page' => $page,
            'data' => $formattedItems,
            'total' => $items->count(), // Total number of records
            'per_page' => $length,
            'last_page' => ceil($items->count() / $length)
        ];

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $pagination);
    }

    public function getPaymentInfo($id)
    {
        $Inspections = Inspections::find($id);
        $total = $Inspections->total;
        $paidPrice = Installment::where('inspection_id', $id)->sum('paid_price');
        $data = [
            'total' => $total,
            'paid_price' => $paidPrice,
            'remaining_amount' => $total - $paidPrice
        ];
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }
}
