<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationService;
use App\Models\Branches;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;


class QuotationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $quotations = Quotation::all();
        return $quotations;
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
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make(request()->all(), [
            'branche_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'payment_term' => 'required|in:cash,credit,tran',
            'date' => 'required|date',
            'valid_until' => 'required|date',
            'remark' => 'nullable|string',
            'total' => 'required|numeric',
            'discount' => 'required|numeric',
            'tax' => 'nullable|numeric',
            'total_amount' => 'required|numeric',
            'type' => 'required|in:vat,nonvat',
            'service' => 'required|array|min:1',
            'service.*.service_id' => 'required|integer',
            'service.*.service_price' => 'required|numeric',
            'service.*.qty' => 'required|numeric',
            'service.*.discount' => 'required|numeric',

        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            if ($request->type == 'vat') {
                $count = Quotation::whereYear('date', date('Y'))
                    ->where('branche_id', $request->branche_id)
                    ->whereMonth('date', date('m'))
                    ->whereRaw("no REGEXP '^QT" . $year . $month . "/[0-9]{3}$'")
                    ->where('type', 'vat')
                    ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

                $no = sprintf("QT%s%s/%03d", $year, $month, $count);
            } else {
                $count = Quotation::whereYear('date', date('Y'))
                    ->where('branche_id', $request->branche_id)
                    ->whereMonth('date', date('m'))
                    ->whereRaw("no REGEXP '^NOQT" . $year . $month . "/[0-9]{3}$'")
                    ->where('type', 'nonvat')
                    ->count() + 1;

                $no = sprintf("NOQT%s%s/%03d", $year, $month, $count);
            }


            $quotation = new Quotation();
            $quotation->branche_id = $request->branche_id;
            $quotation->customer_id = $request->customer_id;

            $quotation->no = $no;
            $quotation->name = $request->name;
            $quotation->payment_term = $request->payment_term;
            $quotation->date = $request->date;
            $quotation->valid_until = $request->valid_until;
            $quotation->remark = $request->remark;
            $quotation->total = $request->total;
            $quotation->discount = $request->discount;
            $quotation->tax = $request->tax;
            $quotation->total_amount = $request->total_amount;
            $quotation->type = $request->type;
            $quotation->save();

            foreach ($request->service as $service) {
                $quotationService = new QuotationService();
                $quotationService->quotation_id = $quotation->id;
                $quotationService->service_id = $service['service_id'];
                $quotationService->service_price = $service['service_price'];
                $quotationService->discount = $service['discount'];
                $quotationService->qty = $service['qty'];
                $quotationService->unit_price = $service['unit_price'];
                $quotationService->save();
            }

            // Log
            $userId = $loginBy->id;
            $type = 'เพิ่มข้อมูล ใบเสนอราคา';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $quotation->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $quotation);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Display the specified resource.
     */
    public function showID($id)
    {
        $quotation = Quotation::where('id', $id)->with('Branches')->with('Customers')->with('quotationServices')->first();
        if (!$quotation) {
            return $this->returnBadRequest('quotation not found');
        } else {
            return $this->returnSuccess('Success', $quotation);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quotation $quotation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make(request()->all(), [
            'branche_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'payment_term' => 'required|in:cash,credit,tran',
            'date' => 'required|date',
            'valid_until' => 'required|date',
            'remark' => 'nullable|string',
            'total' => 'required|numeric',
            'discount' => 'required|numeric',
            'tax' => 'nullable|numeric',
            'total_amount' => 'required|numeric',
            'service' => 'required|array|min:1',
            'service.*.service_id' => 'required|integer',
            'service.*.service_price' => 'required|numeric',
            'service.*.qty' => 'required|numeric',
            'service.*.discount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $quotation = Quotation::find($id);
            if (!$quotation) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }
            $quotation->status = 'rejected';
            $quotation->save();
            $existingBilling = Quotation::where('no', 'like', $quotation->no . '%')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingBilling) {
                $parts = explode('/', $existingBilling->no);

                if (count($parts) == 2) {
                    // (BI6712/001 -> BI6712/001/01)
                    $newNo = $quotation->no . '/01';
                } else {
                    // (BI6712/001/01 -> BI6712/001/02)
                    $currentSubNumber = intval($parts[2]);
                    $newSubNumber = $currentSubNumber + 1;
                    $newNo = $parts[0] . '/' . $parts[1] . '/' . sprintf('%02d', $newSubNumber);
                }
            }

            $newQuotation = new Quotation();
            $newQuotation->branche_id = $request->branche_id;
            $newQuotation->customer_id = $request->customer_id;
            $newQuotation->no = $quotation->no;
            $newQuotation->name = $request->name;
            $newQuotation->payment_term = $request->payment_term;
            $newQuotation->date = $request->date;
            $newQuotation->valid_until = $request->valid_until;
            $newQuotation->remark = $request->remark;
            $newQuotation->total = $request->total;
            $newQuotation->discount = $request->discount;
            $newQuotation->tax = $request->tax;
            $newQuotation->total_amount = $request->total_amount;
            $quotation->type = $request->type;

            $newQuotation->save();

            // เพิ่มข้อมูลบริการใหม่ลงในใบเสนอราคาใหม่
            foreach ($request->service as $service) {
                $quotationService = new QuotationService();
                $quotationService->quotation_id = $newQuotation->id;
                $quotationService->service_id = $service['service_id'];
                $quotationService->service_price = $service['service_price'];
                $quotationService->discount = $service['discount'];
                $quotationService->qty = $service['qty'];
                $quotationService->unit_price = $service['unit_price'];
                $quotationService->save();
            }

            // Log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล ใบเสนอราคา';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $newQuotation->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data updated successfully', $newQuotation);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        try {
            $quotation = Quotation::find($id);
            if (!$quotation) {
                return $this->returnBadRequest('quotation not found');
            }
            $quotation->quotationServices()->delete();
            $quotation->delete();

            $userId = $loginBy->id;
            $type = 'ลบข้อมูล ใบเสนอราคา';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('ลบข้อมูลสำเร็จ', $quotation);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Get paginated results.
     */
    public function getPage(Request $request)
    {
        $perPage = $request->input('length', 10);
        $page = $request->input('start', 0);
        $search = $request->input('search.value', "");
        $sortField = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', 1);

        $search_status = $request->input('search_status', "");
        $type = $request->input('type', "");
        $customer_id = $request->input('customer_id', "");
        $startOfMonth = $request->input('startofmonth', "");
        $endOfMonth = $request->input('endofmonth', "");
        $branche_id = $request->input('branche_id', "");

        $direction = 'DESC';
        if ($sortOrder == -1) {
            $direction = 'ASC';
        }

        $seaclable = ['no'];

        $quotation = Quotation::with(
            'Branches',
            'Customers',
            'quotationServices.service',
        )
            ->where(function ($query) use ($search, $seaclable, $search_status, $customer_id, $type, $startOfMonth, $endOfMonth, $branche_id) {
                if ($search) {
                    foreach ($seaclable as &$s) {
                    $query->orWhere($s, 'LIKE', '%' . $search . '%');
                    }
                    $query->orWhereHas('Customers', function ($subQuery) use ($search) {
                    $subQuery->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('lname', 'LIKE', '%' . $search . '%')
                        ->orWhere(DB::raw("CONCAT(name, ' ', lname)"), 'LIKE', '%' . $search . '%');
                    });
                }
                if (!empty($search_status)) {
                    $query->where('status', $search_status);
                }
                if (!empty($type)) {
                    $query->where('type', $type);
                }
                if (!empty($customer_id)) {
                    $query->whereHas('Customers', function ($subQuery) use ($customer_id) {
                    $subQuery->where('id', $customer_id);
                    });
                }
                if (!empty($search_status)) {
                    $query->where('status', $search_status);
                }
                if (!empty($startOfMonth) && !empty($endOfMonth)) {
                    $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
                }
                if (!empty($branche_id)) {
                    $query->where('branche_id', $branche_id);
                }
            });

        $quotation = $quotation
            ->orderBy($sortField, $direction)
            ->paginate($perPage, ['*'], 'page', ($page / $perPage) + 1);

        return $this->returnSuccess('Success', $quotation);
    }

    public function update_status(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $quotation = Quotation::find($id);
        if (!$quotation) {
            return $this->returnBadRequest('quotation not found');
        }
        try {
            $quotation->status = $request->status;
            $quotation->save();

            // Log
            $userId = $loginBy->id;
            $type = 'อัปเดตสถานะใบเสนอราคา';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $quotation->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $quotation);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    public function pdf(Request $request, $id)
    {
        $quotation = Quotation::with(
            'Branches',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
            'Customers.Customer_Addresses.Provinces',
            'Customers.Customer_Addresses.Districts',
            'Customers.Customer_Addresses.SubDistricts',
            'quotationServices.service',
        )->where('id', $id)->first();
        // dd($quotation);

        if (!$quotation) {
            return $this->returnBadRequest('quotation not found');
        }
        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->first();
            if (!$branche) {
                return $this->returnError('Branches Not Found');
            }
            $branche_Name = $branche->business_name ?? null;
            $branche_address = $branche->address ?? null;
            $branche_open_date_time = $branche->open_close_time ?? null;
            $branche_open_date = $branche->open_date ?? null;
            $branche_phone = $branche->phone ?? null;
            $branche_image = $branche->logo ?? null;
            $branche_is_headquarter = $branche->is_headquarter ?? null;
            $branche_tax = $branche->tax_id ?? null;
        } else {
            $branche_Name = $quotation->Branches->business_name ?? null;
            $branche_address = $quotation->Branches->address ?? null;
            $branche_open_date_time = $quotation->Branches->open_close_time ?? null;
            $branche_open_date = $quotation->Branches->open_date ?? null;
            $branche_phone = $quotation->Branches->phone ?? null;
            $branche_image = $quotation->Branches->logo ?? null;
            $branche_is_headquarter = $quotation->Branches->is_headquarter ?? null;
            $branche_tax = $quotation->Branches->tax_id ?? null;
        }
        // $branch = $quotation->Branches;
        $customer = $quotation->Customers;
        $services = $quotation->quotationServices;

        $is_headquarter = '';
        if ($branche_is_headquarter == 1) {
            $is_headquarter = 'สำนักงานใหญ่';
        }
        $checkbox_null = '<img src="' . url('image/checkbox.png') . '" alt="checkbox" style="width: 12px;">';
        // $checkbox_null = '';
        $checkbox_value = '<img src="' . url('image/checkbox-true.png') . '" alt="checkbox" style="width: 12px;">';


        // $checkbox_null = '';
        // $checkbox_null = '<img src="' . url('storage/Branches/branchs_FIE_Circle_Logo.png') . '" alt="checkbox" style="width: 12px;">';
        // $checkbox_value = '';
        // $checkbox_value = '<img src="' . url('storage/Branches/branchs_FIE_Circle_Logo.png') . '" alt="checkbox" style="width: 12px;">';

        $is_headquarter_cus = $checkbox_null . ' สำนักงานใหญ่';
        if ($customer->is_headquarter == 1) {
            $is_headquarter_cus = $checkbox_value . ' สำนักงานใหญ่';
        }

        $payment_term = '';
        if ($quotation->payment_term == 'cash') {
            $payment_term = 'เงินสด';
        } elseif ($quotation->payment_term == 'credit') {
            $payment_term = 'เครดิต';
        } elseif ($quotation->payment_term == 'tran') {
            $payment_term = 'โอน';
        }

        $startDate = new DateTime($quotation->date);
        $endDate = new DateTime($quotation->valid_until);

        // Calculate the difference
        $totalDays = $startDate->diff($endDate);
        $days = $totalDays->days;

        $TotalAmount = $quotation->total - $quotation->discount;
        // ภาษีมูลค่าเพิ่ม 7%
        $VatAmount = $quotation->tax;
        // ยอดรวมทั้งสิ้น
        $NetAmount = $TotalAmount + $quotation->tax;
        // แปลงจำนวนยอดเป็นตัวอักษร
        $NetAmountText = $this->convertToThaiText(number_format($NetAmount, 2));

        $footvat = '';
        if ($quotation->type === 'vat') {
            $footvat  .= '
                        <tr>
                            <td colspan="2" style="padding-left: 15px; padding-top: 15px; line-height: 0.7; border-top: 1px solid #000;">
                                หมายเหตุ <br><span style="font-size: 12px;">Remark</span>
                            </td>
                            <td colspan="3" style="padding-left: 63px; padding-top: 20px; line-height: 0.7; border-top: 1px solid #000;">
                                รวมเป็นเงิน <br><span style="font-size: 12px;">Subtotal</span>
                            </td>
                            <td style="padding-right: 9px; padding-top: 20px; font-size: 17px; text-align: right; border-left: 1px solid #000; border-top: 1px solid #000;">' . number_format($quotation->total, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" rowspan="3" style="padding-left: 22px;">' . $quotation->remark . '</td>
                            <td colspan="3" style="padding-left: 63px; line-height: 0.7;">
                                <u>หัก</u> &nbsp; ส่วนลด<br><span style="font-size: 12px;">Less Discount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($quotation->discount, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding-left: 63px; line-height: 0.7;">
                                จำนวนเงินหลังหักส่วนลด<br><span style="font-size: 12px;">Total Amount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($TotalAmount, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-left: 63px; line-height: 0.7;">
                                จำนวนภาษีมูลค่าเพิ่ม<br><span style="font-size: 12px;">VAT Amount</span>
                            </td>
                            <td style="text-align: left;">&nbsp; 7.00 %</td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($VatAmount, 2) . '</td>
                        </tr>
                        <tr style="border: 1px solid #000; border-left: none; border-right: none;">
                            <td colspan="2" style="padding: 4.5px; text-align: center;"> (' . $NetAmountText . ') </td>
                            <td colspan="3" style="padding-left: 110px; line-height: 0.7;">
                                จำนวนเงินรวมทั้งสิ้น<br><span style="font-size: 12px;">Net Amount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;"><b>' . number_format($NetAmount, 2) . '</b></td>
                        </tr>';
        } else {
            $footvat .= '
                        <tr>
                            <td colspan="2" style="padding-left: 15px; padding-top: 15px; line-height: 0.7; border-top: 1px solid #000;">
                                หมายเหตุ <br><span style="font-size: 12px;">Remark</span>
                            </td>
                            <td colspan="3" style="padding-left: 63px; padding-top: 20px; line-height: 0.7; border-top: 1px solid #000;">
                                รวมเป็นเงิน <br><span style="font-size: 12px;">Subtotal</span>
                            </td>
                            <td style="padding-right: 9px; padding-top: 20px; font-size: 17px; text-align: right; border-left: 1px solid #000; border-top: 1px solid #000;">' . number_format($quotation->total, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" rowspan="2" style="padding-left: 22px;">' . $quotation->remark . '</td>
                            <td colspan="3" style="padding-left: 63px; line-height: 0.7;">
                                <u>หัก</u> &nbsp; ส่วนลด<br><span style="font-size: 12px;">Less Discount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($quotation->discount, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding-left: 63px; line-height: 0.7;">
                                จำนวนเงินหลังหักส่วนลด<br><span style="font-size: 12px;">Total Amount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($TotalAmount, 2) . '</td>
                        </tr>
                        <tr style="border: 1px solid #000; border-left: none; border-right: none;">
                            <td colspan="2" style="padding: 4.5px; text-align: center;"> (' . $NetAmountText . ') </td>
                            <td colspan="3" style="padding-left: 110px; line-height: 0.7;">
                                จำนวนเงินรวมทั้งสิ้น<br><span style="font-size: 12px;">Net Amount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;"><b>' . number_format($NetAmount, 2) . '</b></td>
                        </tr>
            ';
        }

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun Bold Italic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 12,
        ]);

        $mpdf->SetTitle('ใบเสนอราคา');


        $itemsPerPage = 13;
        $totalItems = count($services);
        $pages = ceil($totalItems / $itemsPerPage);

        $Subtotal = 0;
        $LessDiscount = 0;
        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                // $mpdf->AddPage('L); สำหรับกระดาษแนวนอน
                $mpdf->AddPage();
            }

            $html = '
            <div style="position: absolute;top:40px;left:42px;">
                <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
            </div>
            <div style="position: absolute; left: 170px; top: 4.1%; font-size: 27px;">' . $branche_Name . '</div>
            <div style="position: absolute; left: 170px; top: 7.5%; font-size: 18px;">' . $branche_address . '</div>
            <div style="position: absolute; left: 170px; top: 9.6%; font-size: 18px;">' . $branche_phone . '</div>
            <div style="position: absolute; left: 170px; top: 12.1%; font-size: 18px;">เลขประจำตัวผู้เสียภาษี/ Tax ID &nbsp; ' . $branche_tax . ' &nbsp; ' . $is_headquarter . '</div>
            <div id="page_color" style="font-size: 25px; font-weight: bold; background-color: #c2f0f3; position: absolute; left: 67%; top: 11.2%; width: 207px; border: 1.5px solid #000000; border-radius: 15px;">
            &nbsp; ใบเสนอราคา / Quotation
            </div>
            <div style="position: absolute; left: 9%; top: 14.7%; font-size: 12px;">'. $quotation->customers->no.'</div>
            <div style="position: absolute; left: 77.5%; top: 14.6%; font-size: 18px;">(ต้นฉบับ)</div>


            ';
            //ส่วนของกล่องข้อความด้านซ้าย
            // return $customer;
            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address = $addressObj->address ?? null;
                $provinceName = $addressObj->provinces->name_th_prefix ?? null;
                $districtName = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode = $addressObj->zip_code ?? null;
            } else {
                $address = null;
                $provinceName = null;
                $districtName = null;
                $subdistrictName = null;
                $postalcode = null;
            }
            $html .= '
            <div style="position: absolute; left: 7%; top: 17.1%; width: 336px; height: 182px; border: 1px solid #000; border-radius: 10px; padding: 3px;"></div>
            <div style="position: absolute; left: 9%; top: 17.8%; font-size: 18px; line-height: 0.9;">
                ถึง <br> <span style="font-size: 12px;">To</span> &nbsp;
            </div>
            <div style="position: absolute; left: 14.3%; top: 17.9%; font-size: 18px; line-height: 0.9;">
                ' . $quotation->name . '
            </div>
            <div style="position: absolute; left: 9%; top: 20.3%; font-size: 18px; line-height: 1.3;">
                <b> ' . ($customer->name ?? '') . ' ' . ($customer->lname ?? '') . '<br>
                    ' . $address . '<br>
                    ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . '
            </div>
            <div style="position: absolute; left: 9%; top: 26.8%; font-size: 18px; line-height: 0.6;">
                เลขประจำตัวผู้เสียภาษี <br> <span style="font-size: 12px;">Tax ID</span> &nbsp;
            </div>
            <div style="position: absolute; left: 24.5%; top: 27.1%; font-size: 16px; line-height: 0.6;width:95px;">
            <b> ' . $customer->tax_id . '
            </div>
            <div style="position: absolute; left: 37.3%; top: 27.3%; font-size: 18px; line-height: 0.6;">
                ' . $is_headquarter_cus . '
            </div>
            <div style="position: absolute; left: 9%; top: 29.3%; font-size: 16px; line-height: 0.6;">
                โทร. <br> <span style="font-size: 12px;">Tel.</span> &nbsp;
            </div>
            <div style="position: absolute; left: 13.2%; top: 29.7%; font-size: 15px; line-height: 0.6;">
                ' . $customer->phone_number1 . ' ' . $customer->phone_number2 . '
            </div>
            <div style="position: absolute; left: 9%; top: 31.5%; font-size: 16px; line-height: 0.6;">
                อ้างอิง <br> <span style="font-size: 12px;">Reference.</span> &nbsp;
            </div>


            ';
            //ส่วนของกล่องข้อความด้านขวา
            $html .= '
            <div style="position: absolute; left: 51.5%; top: 17.1%; width: 353px; height: 182px; padding: 3px; border: 1px solid #000000; border-radius: 10px;"></div>
            <div style="position: absolute; left: 54.7%; top: 17.9%; font-size: 17px; line-height: 0.7;">
                เลขที่ใบเสนอราคา <br> <span style="font-size: 12px;">Quotation No.</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 18%; font-size: 19px;">
                <b>' . $quotation->no . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 22.5%; font-size: 17px; line-height: 0.6;">
                วันที่ <br> <span style="font-size: 12px;">Date</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 22.2%; font-size: 17px;">
                <b>' . $this->convertToThaiDate($quotation->date) . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 27%; font-size: 17px; line-height: 0.65;">
                ยืนราคา <br> <span style="font-size: 12px;">Valid Price</span> &nbsp;
            </div>
            <div style="position: absolute; left: 64%; top: 26.8%; font-size: 17px;width:15px;">'. $days .'
            </div>
            <div style="position: absolute; left: 67%; top: 27%; font-size: 17px; line-height: 0.65;">
                วัน <br> <span style="font-size: 12px;">Days</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 27.2%; font-size: 17px; line-height: 0.6;">
                ถึงวันที่ <br> <span style="font-size: 12px;">Valid trill</span> &nbsp;
            </div>
            <div style="position: absolute; left: 80.5%; top: 27.4%; font-size: 16px; line-height: 0.6;">
                <b>' . $this->convertToThaiDate($quotation->valid_until) . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 29.5%; font-size: 17px; line-height: 0.65;">
                เงื่อนไขชำระเงิน <br> <span style="font-size: 12px;">Payment Term</span>
            </div>
            <div style="position: absolute; left: 72%; top: 29.7%; font-size: 17px; line-height: 0.6; width: 100px; ">
                <b> ' . $payment_term . '
            </div>


            <div style="position: absolute; left: 7%; top: 34.7%; font-size: 17px;">
            <b> บริษัทฯ มีความยินดีที่จะเสนอราคาสินค้า ดังต่อไปนี้ : </b>
            </div>

            <style>
                #header td {
                    line-height: 0.87;
                    padding: 7px;
                    padding-top: 10px;
                    text-align: center;
                    font-size: 17px;
                    border-left: 1px solid #000;

                }

                #body td {
                    border: 1px solid #000;
                    font-size: 17px;
                    line-height: 1.4;
                    vertical-align: top;
                }
                #tbody2 tr td {
                    font-size: 18px;

                }

                #tbody3 tr td {
                    font-size: 18px;
                }
                #tbody3 tr td #span {

                }

            </style>
            <div id="page_color" style="width: 90%; height: 3.4%; position: absolute; left: 7%; top: 37%; border: 1px solid #000; border-radius: 10px 10px 0px 0px; background-color: #c2f0f3;"></div>
            <div style="width: 90%; height: 60%; position: absolute; left: 7%; top: 37%; border: 1px solid #000; padding: 0px; border-radius: 10px 10px 10px 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tbody id="tbody1" style="">
                        <tr id="header">
                            <td style="width: 7%; border-left: none;">
                                ลำดับ<br><span style="font-size: 12px;">No.</span>
                            </td>
                            <td style="width: 42%;">
                                รหัสสินค้า/รายละเอียด<br><span style="font-size: 12px;">Code/Descriptions</span>
                            </td>
                            <td style="width: 17%;">
                                จำนวน<br><span style="font-size: 12px;">Quantity</span>
                            </td>
                            <td style="width: 12%;">
                                หน่วยละ<br><span style="font-size: 12px;">Unit Price</span>
                            </td>
                            <td style="width: 8%;">
                                ส่วนลด<br><span style="font-size: 12px;">Discount</span>
                            </td>
                            <td style="width: 14%;">
                                จำนวนเงิน<br><span style="font-size: 12px;">Amount</span>
                            </td>
                        </tr>
                        ';
            // รีเซ็ตยอดรวมสำหรับแต่ละหน้า
            $Subtotal = 0;
            $LessDiscount = 0;
            $TotalAmount = 0;
            $VatAmount = 0;
            $NetAmount = 0;
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {
                // return $services[$i];
                $product = $services[$i];
                // คำนวณราคาสุทธิของสินค้ารายการนั้น ๆ
                // $sum = str_replace(',', '', $product['service_price']);
                $html .= '
                    <tr id="body">
                        <td style="text-align: center; border: none;">' . ($i + 1) . '</td>
                        <td style="text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">' . $product['service']->name . '</td>
                        <td style="text-align: center; border-right: none; border-top: none; border-bottom: none;">' . $product['qty'] . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($product['unit_price'], 2) . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($product['discount'], 2) . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($product['service_price'], 2) . '</td>
                    </tr>';
            }

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                    <tr id="body">
                        <td style="width: 7%; text-align: center; border: none;">&nbsp;</td>
                        <td style="width: 42%; text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">&nbsp;</td>
                        <td style="width: 17%; text-align: center; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 12%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 8%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 14%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                    </tr>';
            }
            $html .= '
                    </tbody>
                    <tbody id="tbody2">
                        <tr>
                            ' . $footvat . '
                        </tr>
                    </tbody>
                    <tbody id="tbody3">
                        <tr style="border-bottom: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: center; line-height: 1.3; padding-right: 40px; padding-top: 10px; font-size: 17px;">
                                กรุณาเซ็นยืนยันการสั่งซื้อด้านล่างนี้ <br> Please sign below for purchasing comfirmation.
                            </td>
                            <td style="font-size: 13px; text-align: center; border-right: none;">
                                &nbsp; <br>
                                ในนาม
                            </td>>
                            <td colspan="3" style="text-align: center; padding-top: 5px; line-height: 1.1; padding-right: 35px; border: none;">
                                ขอแสดงความนับถือ <br>
                                <span><b>' . $branche_Name . '</b></span>
                            </td>
                        </tr>
                        <tr style="border-top: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: left; padding-left: 40px; padding-top: ; line-height: 1.4;">
                                ___________________________ <br>
                                (__________________________) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ____/____/____ <br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ผู้สั่งซื้อสิ้นค้า/Ordered by &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; วันที่/Date
                            </td>

                            <td style="border-right: none;"> </td>
                            <td colspan="3" style="text-align: center; line-height: 1; padding-right: 35px; border: none;">
                                _______________________________ <br>
                                (__________________________) <br>
                                &nbsp;<br>
                                _______________________________ <br>
                                ผู้เตรียมเอกสาร/Prepared by
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            ';
            $mpdf->WriteHTML($html); //BIT9 &nbsp; 28/10/67 &nbsp; 13:24:07 &nbsp; 2 <br>
            $mpdf->AddPage();
            $html_copy = '
            <div style="position: absolute;top:40px;left:42px;">
                <img src="' . $branche_image . '" style="width:110px;height:110px;" alt="Icon">
            </div>
            <div style="position: absolute; left: 170px; top: 4.1%; font-size: 27px;">' . $branche_Name . '</div>
            <div style="position: absolute; left: 170px; top: 7.5%; font-size: 18px;">' . $branche_address . '</div>
            <div style="position: absolute; left: 170px; top: 9.6%; font-size: 18px;">' . $branche_phone . '</div>
            <div style="position: absolute; left: 170px; top: 12.1%; font-size: 18px;">เลขประจำตัวผู้เสียภาษี/ Tax ID &nbsp; ' . $branche_tax . ' &nbsp; ' . $is_headquarter . '</div>
            <div id="page_color" style="font-size: 25px; font-weight: bold; background-color: #C0DCC0; position: absolute; left: 67%; top: 11.2%; width: 207px; border: 1.5px solid #000000; border-radius: 15px;">
            &nbsp; ใบเสนอราคา / Quotation
            </div>
            <div style="position: absolute; left: 9%; top: 14.7%; font-size: 12px;">'. $quotation->customers->no .'</div>
            <div style="position: absolute; left: 77.5%; top: 14.6%; font-size: 18px;">(สำเนา)</div>

            ';
            //ส่วนของกล่องข้อความด้านซ้าย
            $html_copy .= '
            <div style="position: absolute; left: 7%; top: 17.1%; width: 336px; height: 182px; border: 1px solid #000; border-radius: 10px; padding: 3px;"></div>
            <div style="position: absolute; left: 9%; top: 17.8%; font-size: 18px; line-height: 0.9;">
                ถึง <br> <span style="font-size: 12px;">To</span> &nbsp;
            </div>
            <div style="position: absolute; left: 14.3%; top: 17.9%; font-size: 18px; line-height: 0.9;">
                ' . $quotation->name . '
            </div>
            <div style="position: absolute; left: 9%; top: 20.3%; font-size: 18px; line-height: 1.3;">
                <b> ' . ($customer->name ?? '') . ' ' . ($customer->lname ?? '') . '<br>
                    ' . $address . '<br>
                    ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . '
            </div>
            <div style="position: absolute; left: 9%; top: 26.8%; font-size: 18px; line-height: 0.6;">
                เลขประจำตัวผู้เสียภาษี <br> <span style="font-size: 12px;">Tax ID</span> &nbsp;
            </div>
            <div style="position: absolute; left: 24.5%; top: 27.1%; font-size: 16px; line-height: 0.6;width:95px;">
            <b> ' . $customer->tax_id . '
            </div>
            <div style="position: absolute; left: 37.3%; top: 27.3%; font-size: 18px; line-height: 0.6;">
                ' . $is_headquarter_cus . '
            </div>
            <div style="position: absolute; left: 9%; top: 29.3%; font-size: 16px; line-height: 0.6;">
                โทร. <br> <span style="font-size: 12px;">Tel.</span> &nbsp;
            </div>
            <div style="position: absolute; left: 13.2%; top: 29.7%; font-size: 15px; line-height: 0.6;">
                ' . $customer->phone_number1 . ' ' . $customer->phone_number2 . '
            </div>
            <div style="position: absolute; left: 9%; top: 31.5%; font-size: 16px; line-height: 0.6;">
                อ้างอิง <br> <span style="font-size: 12px;">Reference.</span> &nbsp;
            </div>


            ';
            //ส่วนของกล่องข้อความด้านขวา
            $html_copy .= '
            <div style="position: absolute; left: 51.5%; top: 17.1%; width: 353px; height: 182px; padding: 3px; border: 1px solid #000000; border-radius: 10px;"></div>
            <div style="position: absolute; left: 54.7%; top: 17.9%; font-size: 17px; line-height: 0.7;">
                เลขที่ใบเสนอราคา <br> <span style="font-size: 12px;">Quotation No.</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 18%; font-size: 19px;">
                <b>' . $quotation->no . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 22.5%; font-size: 17px; line-height: 0.6;">
                วันที่ <br> <span style="font-size: 12px;">Date</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 22.2%; font-size: 17px;">
                <b>' . $this->convertToThaiDate($quotation->date) . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 27%; font-size: 17px; line-height: 0.65;">
                ยืนราคา <br> <span style="font-size: 12px;">Valid Price</span> &nbsp;
            </div>
            <div style="position: absolute; left: 64%; top: 26.8%; font-size: 17px;width:15px;">'. $days .'
            </div>
            <div style="position: absolute; left: 67%; top: 27%; font-size: 17px; line-height: 0.65;">
                วัน <br> <span style="font-size: 12px;">Days</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 27.2%; font-size: 17px; line-height: 0.6;">
                ถึงวันที่ <br> <span style="font-size: 12px;">Valid trill</span> &nbsp;
            </div>
            <div style="position: absolute; left: 80.5%; top: 27.4%; font-size: 16px; line-height: 0.6;">
                <b>' . $this->convertToThaiDate($quotation->valid_until) . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 29.5%; font-size: 17px; line-height: 0.65;">
                เงื่อนไขชำระเงิน <br> <span style="font-size: 12px;">Payment Term</span>
            </div>
            <div style="position: absolute; left: 72%; top: 29.7%; font-size: 17px; line-height: 0.6; width: 100px; ">
                <b> ' . $payment_term . '
            </div>


            <div style="position: absolute; left: 7%; top: 34.7%; font-size: 17px;">
            <b> บริษัทฯ มีความยินดีที่จะเสนอราคาสินค้า ดังต่อไปนี้ : </b>
            </div>

            <style>
                #header td {
                    line-height: 0.87;
                    padding: 7px;
                    padding-top: 10px;
                    text-align: center;
                    font-size: 17px;
                    border-left: 1px solid #000;

                }

                #body td {
                    border: 1px solid #000;
                    font-size: 17px;
                    line-height: 1.4;
                    vertical-align: top;
                }
                #tbody2 tr td {
                    font-size: 18px;

                }

                #tbody3 tr td {
                    font-size: 18px;
                }
                #tbody3 tr td #span {

                }

            </style>
            <div id="page_color" style="width: 90%; height: 3.4%; position: absolute; left: 7%; top: 37%; border: 1px solid #000; border-radius: 10px 10px 0px 0px; background-color: #C0DCC0;"></div>
            <div style="width: 90%; height: 60%; position: absolute; left: 7%; top: 37%; border: 1px solid #000; padding: 0px; border-radius: 10px 10px 10px 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tbody id="tbody1" style="">
                        <tr id="header">
                            <td style="width: 7%; border-left: none;">
                                ลำดับ<br><span style="font-size: 12px;">No.</span>
                            </td>
                            <td style="width: 42%;">
                                รหัสสินค้า/รายละเอียด<br><span style="font-size: 12px;">Code/Descriptions</span>
                            </td>
                            <td style="width: 17%;">
                                จำนวน<br><span style="font-size: 12px;">Quantity</span>
                            </td>
                            <td style="width: 12%;">
                                หน่วยละ<br><span style="font-size: 12px;">Unit Price</span>
                            </td>
                            <td style="width: 8%;">
                                ส่วนลด<br><span style="font-size: 12px;">Discount</span>
                            </td>
                            <td style="width: 14%;">
                                จำนวนเงิน<br><span style="font-size: 12px;">Amount</span>
                            </td>
                        </tr>
                        ';
            // รีเซ็ตยอดรวมสำหรับแต่ละหน้า
            $Subtotal = 0;
            $LessDiscount = 0;
            $TotalAmount = 0;
            $VatAmount = 0;
            $NetAmount = 0;
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {
                $product = $services[$i];
                // $sum = $product['service']->price * $product['qty'];

                $html_copy .= '
                    <tr id="body">
                        <td style="text-align: center; border: none;">' . ($i + 1) . '</td>
                        <td style="text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">' . $product['service']->name . '</td>
                        <td style="text-align: center; border-right: none; border-top: none; border-bottom: none;">' . $product['qty'] . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($product['unit_price'], 2) . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($product['discount'], 2) . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($product['service_price'], 2) . '</td>
                    </tr>';

            }

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html_copy .= '
                    <tr id="body">
                        <td style="width: 7%; text-align: center; border: none;">&nbsp;</td>
                        <td style="width: 42%; text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">&nbsp;</td>
                        <td style="width: 17%; text-align: center; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 12%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 8%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 14%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                    </tr>';
            }
            $html_copy .= '
                    </tbody>
                    <tbody id="tbody2">
                        <tr>
                            ' . $footvat . '
                        </tr>
                    </tbody>
                    <tbody id="tbody3">
                        <tr style="border-bottom: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: center; line-height: 1.3; padding-right: 40px; padding-top: 10px; font-size: 17px;">
                                กรุณาเซ็นยืนยันการสั่งซื้อด้านล่างนี้ <br> Please sign below for purchasing comfirmation.
                            </td>
                            <td style="font-size: 13px; text-align: center; border-right: none;">
                                &nbsp; <br>
                                ในนาม
                            </td>>
                            <td colspan="3" style="text-align: center; padding-top: 5px; line-height: 1.1; padding-right: 35px; border: none;">
                                ขอแสดงความนับถือ <br>
                                <span><b>' . $branche_Name . '</b></span>
                            </td>
                        </tr>
                        <tr style="border-top: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: left; padding-left: 40px; padding-top: ; line-height: 1.4;">
                                __________________________ <br>
                                (__________________________) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ____/____/____ <br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ผู้สั่งซื้อสิ้นค้า/Ordered by &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; วันที่/Date
                            </td>

                            <td style="border-right: none;"> </td>
                            <td colspan="3" style="text-align: center; line-height: 1; padding-right: 35px; border: none;">
                                _______________________________ <br>
                                (__________________________) <br>
                                &nbsp;<br>
                                _______________________________ <br>
                                ผู้เตรียมเอกสาร/Prepared by
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            ';
            $mpdf->WriteHTML($html_copy);
        }

        // $mpdf->Output();
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers' => 'Accept-Ranges,range',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }
}
