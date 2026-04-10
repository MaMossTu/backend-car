<?php

namespace App\Http\Controllers;

use App\Models\InsurancesRenewals;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsurancesRenewalsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(InsurancesRenewals $insurancesRenewals)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InsurancesRenewals $insurancesRenewals)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InsurancesRenewals $insurancesRenewals)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InsurancesRenewals $insurancesRenewals)
    {
        //
    }

    public function getPage(Request $request)
    {
        $length = $request->length ?? 10;
        $order = $request->order ?? [['column' => 0, 'dir' => 'asc']];
        $search = $request->search ?? ['value' => ''];
        $start = $request->start ?? 0;
        $page = intval($start / $length) + 1;
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $policy_number_filter = $request->input('policy_number'); // 0 or 1

        $columns = [
            'id',
            'vehicle_id',
            'insurance_names_id',
            'date_start',
            'date_end',
            'amount_paid',
            'stamp',
            'tax',
            'policy_number',
            'policy_status',
            'insurance_renewal_type_id',
            'remark',
            'created_at',
            'updated_at',
        ];
        $searchColumns = [
            'policy_number',
        ];

        $query = InsurancesRenewals::with(['vehicle', 'insuranceName']);

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if ($policy_number_filter !== null) {
            if ($policy_number_filter == 0) {
                $query->whereNull('policy_number');
            } elseif ($policy_number_filter == 1) {
                $query->whereNotNull('policy_number');
            }
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

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
                $No++;
                $item->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
    public function update_policy_number(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $InsurancesRenewals = InsurancesRenewals::findOrFail($id);

            $InsurancesRenewals->policy_number = $request->policy_number;
            $InsurancesRenewals->date_start = $request->date_start;
            $InsurancesRenewals->date_end = $request->date_end;
            $InsurancesRenewals->remark = $request->remark;
            $InsurancesRenewals->insurance_renewal_type_id = $request->insurance_renewal_type_id;
            $InsurancesRenewals->insurance_names_id = $request->insurance_names_id;
            $InsurancesRenewals->save();

            DB::commit();
            return $this->returnSuccess('Success', $InsurancesRenewals);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }

}
