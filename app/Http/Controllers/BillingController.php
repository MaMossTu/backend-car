<?php
namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\BillingInvoice;
use App\Models\Branches;
use App\Models\Customers;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Billing = Billing::all();
        return $Billing;
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
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make(request()->all(), [
            'branche_id'                    => 'required|integer',
            'customer_id'                   => 'nullable|integer',
            'date'                          => 'required|date',
            'billing_invoices'              => 'required|array|min:1',
            'billing_invoices.*.invoice_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        $resolved = $this->resolveBillingCustomerFromInvoices($request);
        if (! $resolved['ok']) {
            return $this->returnErrorData($resolved['message'], 400);
        }

        DB::beginTransaction();
        try {

            $customer = Customers::where('id', $resolved['customer_id'])
                ->with('Customer_Addresses', 'Customer_Addresses.provinces', 'Customer_Addresses.districts', 'Customer_Addresses.SubDistricts')
                ->first();
            if (! $customer) {
                DB::rollBack();

                return $this->returnErrorData('ไม่พบข้อมูลลูกค้า', 404);
            }

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj  = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address         = $addressObj->address ?? null;
                $provinceName    = $addressObj->provinces->name_th_prefix ?? null;
                $districtName    = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode      = $addressObj->zip_code ?? null;
            } else {
                $address         = null;
                $provinceName    = null;
                $districtName    = null;
                $subdistrictName = null;
                $postalcode      = null;
            }

            $billing             = new Billing();
            $billing->branche_id = $request->branche_id;

            $billing->customer_no             = $customer->no ?? '';
            $billing->customer_name           = trim(($customer->name ?? '') . ' ' . ($customer->lname ?? ''));
            $billing->customer_address        = $address ?? '';
            $billing->customer_address2       = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $billing->customer_phone          = $customer->phone_number1 ?? '';
            $billing->customer_email          = $customer->email ?? '';
            $billing->customer_tax_id         = $customer->tax_id ?? '';
            $billing->customer_is_headquarter = $customer->is_headquarter;

            $year  = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m');      // เดือนปัจจุบัน
            $count = Billing::whereYear('date', date('Y'))
                ->where('branche_id', $request->branche_id)
                ->whereMonth('date', date('m'))
                ->whereRaw("no REGEXP '^BI" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวน

            $no                 = sprintf("BI%s%s/%03d", $year, $month, $count);
            $billing->no        = $no;
            $billing->date      = $request->date;
            $billing->remark    = $request->remark;
            $billing->condition = $request->condition;
            $billing->withholding_tax = in_array($request->withholding_tax, ['Y', 'N'], true)
                ? $request->withholding_tax
                : 'N';
            $billing->total = $request->input('total', 0) ?? 0;

            $billing->save();

            foreach ($request->billing_invoices as $invoice_data) {
                $invoice = $resolved['invoices']->get($invoice_data['invoice_id']);
                if (! $invoice) {
                    DB::rollBack();

                    return $this->returnErrorData('ไม่พบข้อมูลใบแจ้งหนี้', 404);
                }

                $billing_invoice                   = new BillingInvoice();
                $billing_invoice->invoice_id       = $invoice->id;
                $billing_invoice->billing_id       = $billing->id;
                $billing_invoice->invoice_no       = $invoice->no;
                $billing_invoice->invoice_date     = $invoice->date;
                $billing_invoice->invoice_due_date = $invoice->due_date;
                $billing_invoice->invoice_total    = $invoice->total_amount;

                $paid = InvoicePayment::where('invoice_id', $invoice->id)->sum('paid_price');

                $billing_invoice->invoice_paid    = $paid;
                $billing_invoice->invoice_balance = $invoice->total_amount - $paid;

                $billing_invoice->save();
            }

            // Log
            $userId      = $loginBy->id;
            $type        = 'เพิ่มข้อมูล ใบวางบิล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $billing->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data saved successfully', $billing);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    /**
     * ใบแจ้งหนี้ที่ยังไม่ถูกผูกกับใบวางบิลที่ยังไม่ยกเลิก
     */
    public function index_for_billing()
    {
        $invoices = Invoice::where('status', '!=', 'cancel')
            ->whereDoesntHave('billingInvoice', function ($query) {
                $query->whereHas('billing', function ($billingQuery) {
                    $billingQuery->where('status', '!=', 'cancel');
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        return $this->returnSuccess('Success', $invoices);
    }

    /**
     * @return array{ok: bool, message?: string, customer_id?: int, invoices?: \Illuminate\Support\Collection}
     */
    private function resolveBillingCustomerFromInvoices(Request $request): array
    {
        $invoiceIds = collect($request->input('billing_invoices', []))
            ->pluck('invoice_id')
            ->filter()
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty()) {
            return ['ok' => false, 'message' => 'กรุณาเลือกใบแจ้งหนี้อย่างน้อย 1 รายการ'];
        }

        $invoices = Invoice::whereIn('id', $invoiceIds)->get();
        if ($invoices->count() !== $invoiceIds->count()) {
            return ['ok' => false, 'message' => 'ไม่พบข้อมูลใบแจ้งหนี้บางรายการ'];
        }

        $customerIds = $invoices->pluck('customer_id')->filter()->unique();
        if ($customerIds->count() > 1) {
            return ['ok' => false, 'message' => 'ใบแจ้งหนี้ที่เลือกต้องเป็นของลูกค้าเดียวกัน'];
        }

        $fromInvoices = $customerIds->first();
        $requested    = $request->input('customer_id');

        if ($requested && $fromInvoices && (int) $requested !== (int) $fromInvoices) {
            return ['ok' => false, 'message' => 'ลูกค้าที่เลือกไม่ตรงกับใบแจ้งหนี้'];
        }

        $customerId = $requested ?: $fromInvoices;
        if (! $customerId) {
            return ['ok' => false, 'message' => 'ไม่พบข้อมูลลูกค้าจากใบแจ้งหนี้'];
        }

        return [
            'ok'          => true,
            'customer_id' => (int) $customerId,
            'invoices'    => $invoices->keyBy('id'),
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $Billing = Billing::with([
            'Branches',
            'billingInvoice.invoice',
        ])->find($id);
        if (! $Billing) {
            return $this->returnBadRequest('ไม่พบข้อมูล');
        } else {
            return $this->returnSuccess('Success', $Billing);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Billing $billing)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make(request()->all(), [
            'branche_id'                    => 'required|integer',
            'customer_id'                   => 'nullable|integer',
            'date'                          => 'required|date',
            'billing_invoices'              => 'required|array|min:1',
            'billing_invoices.*.invoice_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ข้อมูลไม่ครบหรือไม่ถูกต้อง', 'errors' => $validator->errors()], 400);
        }

        $resolved = $this->resolveBillingCustomerFromInvoices($request);
        if (! $resolved['ok']) {
            return $this->returnErrorData($resolved['message'], 400);
        }

        DB::beginTransaction();
        try {
            $billing = Billing::find($id);
            if (! $billing) {
                return $this->returnErrorData('ไม่พบข้อมูลใบวางบิล', 404);
            }

            $billing->status = 'cancel';
            $billing->save();

            $customer = Customers::where('id', $resolved['customer_id'])
                ->with('Customer_Addresses', 'Customer_Addresses.provinces', 'Customer_Addresses.districts', 'Customer_Addresses.SubDistricts')
                ->first();
            if (! $customer) {
                DB::rollBack();

                return $this->returnErrorData('ไม่พบข้อมูลลูกค้า', 404);
            }

            $mainAddress = $customer->Customer_Addresses->firstWhere('is_main', 1);
            $addressObj  = $mainAddress ?? $customer->Customer_Addresses->first();

            if ($addressObj) {
                $address         = $addressObj->address ?? null;
                $provinceName    = $addressObj->provinces->name_th_prefix ?? null;
                $districtName    = $addressObj->districts->name_th_prefix ?? null;
                $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
                $postalcode      = $addressObj->zip_code ?? null;
            } else {
                $address         = null;
                $provinceName    = null;
                $districtName    = null;
                $subdistrictName = null;
                $postalcode      = null;
            }

            $existingBilling = Billing::where('no', 'like', $billing->no . '%')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingBilling) {
                $parts = explode('/', $existingBilling->no);

                if (count($parts) == 2) {
                    // (BI6712/001 -> BI6712/001/01)
                    $newNo = $billing->no . '/01';
                } else {
                    // (BI6712/001/01 -> BI6712/001/02)
                    $currentSubNumber = intval($parts[2]);
                    $newSubNumber     = $currentSubNumber + 1;
                    $newNo            = $parts[0] . '/' . $parts[1] . '/' . sprintf('%02d', $newSubNumber);
                }
            }

            $newBilling                          = new Billing();
            $newBilling->branche_id              = $request->branche_id;
            $newBilling->customer_no             = $customer->no ?? '';
            $newBilling->customer_name           = trim(($customer->name ?? '') . ' ' . ($customer->lname ?? ''));
            $newBilling->customer_address        = $address ?? '';
            $newBilling->customer_address2       = ($subdistrictName ?? '') . ' ' . ($districtName ?? '') . ' ' . ($provinceName ?? '') . ' ' . ($postalcode ?? '');
            $newBilling->customer_phone          = $customer->phone_number1 ?? '';
            $newBilling->customer_email          = $customer->email ?? '';
            $newBilling->customer_tax_id         = $customer->tax_id ?? '';
            $newBilling->customer_is_headquarter = $customer->is_headquarter;
            $newBilling->no                      = $billing->no;
            $newBilling->date                    = $request->date;
            $newBilling->remark                  = $request->remark;
            $newBilling->condition               = $request->condition;
            $newBilling->total                   = $request->total;

            $newBilling->save();

            foreach ($request->billing_invoices as $invoice_data) {
                $invoice = $resolved['invoices']->get($invoice_data['invoice_id']);
                if (! $invoice) {
                    DB::rollBack();

                    return $this->returnErrorData('ไม่พบข้อมูลใบแจ้งหนี้', 404);
                }

                $billing_invoice                   = new BillingInvoice();
                $billing_invoice->invoice_id       = $invoice->id;
                $billing_invoice->billing_id       = $newBilling->id;
                $billing_invoice->invoice_no       = $invoice->no;
                $billing_invoice->invoice_date     = $invoice->date;
                $billing_invoice->invoice_due_date = $invoice->due_date;
                $billing_invoice->invoice_total    = $invoice->total_amount;

                $paid = InvoicePayment::where('invoice_id', $invoice->id)->sum('paid_price');

                $billing_invoice->invoice_paid    = $paid;
                $billing_invoice->invoice_balance = $invoice->total_amount - $paid;

                $billing_invoice->save();
            }

            // Log
            $userId      = $loginBy->id;
            $type        = 'แก้ไขข้อมูล ใบวางบิล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $newBilling->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data updated successfully', $newBilling);
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
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();
        try {
            $billing = Billing::find($id);
            if (! $billing) {
                return $this->returnErrorData('ไม่พบข้อมูลใบวางบิล', 404);
            }
            $billing->status = 'cancel';
            $billing->billingInvoice()->delete();
            $billing->delete();

            // Log
            $userId      = $loginBy->id;
            $type        = 'ลบข้อมูล ใบวางบิล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $billing->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Data deleted successfully', $billing);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    public function getPage(Request $request)
    {
        $perPage   = $request->input('length', 10);
        $page      = $request->input('start', 0);
        $search    = $request->input('search.value', "");
        $sortField = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', 1);

        $search_status = $request->input('search_status', "");
        $startOfMonth  = $request->input('startofmonth', "");
        $endOfMonth    = $request->input('endofmonth', "");
        $branche_id    = $request->input('branche_id', "");

        $direction = 'DESC';
        if ($sortOrder == -1) {
            $direction = 'ASC';
        }

        $seaclable = ['no', 'quotation.no', 'customer_name'];

        $Billing = Billing::with(
            'Branches',
            'billingInvoice.invoice',
        )
            ->where(
                function ($query) use ($search, $seaclable, $search_status, $startOfMonth, $endOfMonth, $branche_id) {
                    if ($search) {
                        $query->where('no', 'LIKE', '%' . $search . '%')
                            ->orWhere('customer_name', 'LIKE', '%' . $search . '%')
                            ->orWhereHas('billingInvoice.invoice', function ($q) use ($search) {
                                $q->where('no', 'LIKE', '%' . $search . '%');
                            });
                    }
                    if (!empty($search_status)) {
                        $query->where('status', $search_status);
                    }
                    if (!empty($startOfMonth) && ! empty($endOfMonth)) {
                        $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
                    }
                    if (!empty($branche_id)) {
                        $query->where('branche_id', $branche_id);
                    }
                }
            );

        $Billing = $Billing
            ->orderBy($sortField, $direction)
            ->paginate($perPage, ['*'], 'page', ($page / $perPage) + 1);

        return $this->returnSuccess('Success', $Billing);
    }

    public function update_status(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (! isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $Billing = Billing::with('billingInvoice.invoice.invoiceServices')
            ->with('billingInvoice.invoice.invoicePayment')
            ->find($id);
        if (! $Billing) {
            return $this->returnBadRequest('Billing not found');
        }
        try {
            $Billing->status = $request->status;
            $Billing->paid_type = $request->paid_type;
            $Billing->save();
            if ($Billing->status == 'paid' && $Billing->is_paid == 0) {
                foreach ($Billing->billingInvoice as $billingInvoice) {
                    // $invoiceController = new \App\Http\Controllers\InvoiceController();
                    // $invoiceRequest    = new \Illuminate\Http\Request();
                    // $invoiceRequest->replace(['status' => 'paid']);
                    // $invoiceController->update_status($invoiceRequest, $billingInvoice->invoice_id);

                    $totalpaid = 0;

                    if ($billingInvoice->invoicePayment != []) {
                        $totalpaid = $billingInvoice->invoicePayment[count($billingInvoice->invoicePayment) - 1]->after_price;
                    } else {
                        $totalpaid = $billingInvoice->invoice->total_amount;
                    }

                    if ($totalpaid != 0) {
                        $invoicePaymentController = new \App\Http\Controllers\InvoicePaymentController();
                        $paymentRequest           = new \Illuminate\Http\Request();
                        // $paymentRequest->replace([
                        //     'invoice_id'  => $billingInvoice->invoice_id,
                        //     'paid_type'   => $request->paid_type,
                        //     'paid_price'  => $totalpaid,
                        //     'description' => 'Auto payment from Billing',
                        // ]);
                        $paymentRequest->merge([
                            'invoice_id'  => $billingInvoice->invoice_id,
                            'paid_type'   => $request->paid_type,
                            'paid_price'  => $totalpaid,
                            'description' => 'Auto payment from Billing',
                        ]);
                        $invoicePaymentController->store($paymentRequest);
                    }
                }
                $Billing->is_paid = 1;
                $Billing->save();
            }

            // Log
            $userId      = $loginBy->id;
            $type        = 'อัปเดตสถานะ ใบวางบิล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $Billing->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('Success', $Billing);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 404);
        }
    }

    public function pdf(Request $request, $id)
    {

        $Billing = Billing::where('id', $id)
            ->with([
                'Branches',
                'billingInvoice.invoice',
            ])
            ->first();

        if (! $Billing) {
            return response()->json(['message' => 'ไม่พบข้อมูล id ที่ต้องการ']);
        }
        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->first();
            if (! $branche) {
                return $this->returnError('Branches Not Found');
            }
            $branche_Name           = $branche->business_name ?? null;
            $branche_address        = $branche->address ?? null;
            $branche_open_date_time = $branche->open_close_time ?? null;
            $branche_open_date      = $branche->open_date ?? null;
            $branche_phone          = $branche->phone ?? null;
            $branche_image          = $this->branchStoredLogoUrl($branche->logo ?? null);
        } else {
            $branche_Name           = $Billing->Branches->business_name ?? null;
            $branche_address        = $Billing->Branches->address ?? null;
            $branche_open_date_time = $Billing->Branches->open_close_time ?? null;
            $branche_open_date      = $Billing->Branches->open_date ?? null;
            $branche_phone          = $Billing->Branches->phone ?? null;
            $branche_image          = $this->branchStoredLogoUrl($Billing->Branches->logo ?? null);
        }

        if (! $Billing->billingInvoice || $Billing->billingInvoice->isEmpty()) {
            return response()->json(['message' => 'ไม่พบข้อมูลใบวางบิลที่เกี่ยวข้อง']);
        }

        $invoiceIds = $Billing->billingInvoice->pluck('invoice_id')->toArray();

        $hiscountData = InvoicePayment::whereIn('invoice_id', $invoiceIds)
            ->selectRaw('invoice_id, SUM(paid_price) as total_paid')
            ->groupBy('invoice_id')
            ->get()
            ->keyBy('invoice_id');

        $totalpaidcal = 0;
        $hiscount     = [];

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([

            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun Bold Italic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'margin_left'       => 10,
            'margin_right'      => 10,
            'margin_top'        => 15,
            'margin_bottom'     => 5,
            'margin_header'     => 0,
            'margin_footer'     => 0,
        ]);
        $mpdf->SetTitle('ใบวางบิล');

        if (! $Billing || $Billing->customer_no == null) {

            $mpdf->AddPage(); // กำหนดขอบกระดาษ

            $html = '
            <div style="position: absolute; left: 30; top: 30; width: 15%; height: 100%;">
                <img src="' . $branche_image . '" alt="Branch Logo" style="width: 100%; height: auto;">
            </div>

        <div style="margin-bottom: 5px; padding: -25px 120px; line-height: 0.5;">
            <h2>' . $branche_Name . '</h2>
            <p style="font-size: 20px;">' . $branche_address . '</p>
            <p style="font-size: 18px;">' . $branche_phone . '</p>
        </div>
        <div style="position: absolute; left: 38px;top:98px; text-align:center;padding-left: 360px;">
            <p class="highlight" style="background-color: #f587ff ;text-align:center; font-weight: bold;padding:5px 20px -5px 20px; display: inline-block;height: 40px;width: 150px; border-radius: 15px;font-size: 24px;border: 0.2px solid #000;">
                ใบวางบิล
            </p>
        </div>
        <div style="position: absolute; left: 55px;top:102px; text-align:center;padding-left: 440px;padding-bottom: 5px;">
            <p style="color: #000;font-weight: bold;margin-top:30px;padding-left: 15px; display: inline-block;line-height: 30px;font-size: 20px;">
                ต้นฉบับ (สำหรับลูกค้า)
            </p>
        </div>

        <div style="position: absolute; left: 38px;top:175px; width: 42.3%;padding-left: 20px;line-height: 1.8; height: 160px;border-radius: 7px;border: 0.5px solid #000;">
            <p style="font-size: 18px;">ลูกค้า: </p>
            <p style="font-size: 18px;"></p>
            <p style="font-size: 18px;"></p>
            <p style="font-size: 18px;padding-top:65px;">&nbsp;</p>
        </div>
        <div style="position: absolute; right: 38px;top:175px; width: 42.3%;padding-left: 20px;line-height: 2.5; height: 160px;border-radius: 7px;border: 0.5px solid #000;">
            <p>เลขที่ใบวางบิล:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong></strong></p>
            <p>วันที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
            <p>เงื่อนไขการชำระเงิน:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </p>
        </div>

            <div class="invoice-container" style="width:718px;margin-top: 222px;margin-bottom:10px;border-radius: 7px;border: 0.5px solid #000;">
                <table class="curved-table" style="width: 100%;border-collapse: collapse;">
                    <thead>
                        <tr>
                            <td style="text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-radius: 10px;height: 30px;">No.</td>
                            <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">เอกสารที่ใบกำกับ</td>
                            <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันที่</td>
                            <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันครบกำหนด</td>
                            <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">จำนวนเงิน</td>
                            <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">ชำระแล้ว</td>
                            <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;">เงินคงค้าง</td>
                        </tr>
                    </thead>';

            $rowsPerPage = 15;
            $counter     = 1;
            $currentRow  = 0; // แถวปัจจุบัน
            if ($currentRow <= $rowsPerPage || $currentRow >= $rowsPerPage) {

                while ($counter <= 15) {

                    $html .= '
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: left;padding-left: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;width: auto;height: 33px;"></td>
                        </tr>';
                    $counter++;
                    $currentRow++;
                }

                $html .= '

                        <tr>
                            <td colspan="5" style="border: 0.5px solid #000;border-right:none;padding-left: 10px;border-left:none;height: 40px;">
                                <div class="footer1" style="text-align: left;">
                                    <p>(หนึ่งพันเจ็ดสิบบาทถ้วน)</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-left:none;text-align: right; padding-right: 5px;height: 40px;">
                                <div class="footer2">
                                    <p>รวมทั้งสิ้น</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-right:none;text-align: right; padding-right: 5px;height: 40px;">
                                <p></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="7" style="text-align: left; vertical-align: top;width: 100%;height: 70px;padding-top: 10px; padding-left: 10px;border: 1px solid #000;border-left:none;border-right:none;border-top:none;">
                                <div>
                                    <p>หมายเหตุ:</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" style="vertical-align: top;height: 50px;line-height: 1.5;padding-left: 10px;padding-top:10px;padding-bottom: 15px;">
                                <div>
                                    <p>ชื่อผู้รับวางบิล   _________________</p>
                                    <p>วันที่รับเงิน     ____/_____/____</p>
                                    <p>วันที่นัดรับเช็ค  ____/_____/____</p>
                                </div>
                            </td>
                            <td colspan="3" style="vertical-align: top;height: 50px;line-height: 1.2;padding-top:20px;padding-bottom: 15px;">
                                <div>
                                    <p>ในนาม ' . $branche_Name . '</p>
                                    <br>
                                    <p>ชื่อผู้จ่ายเงิน _________________</p>
                                </div>
                            </td>
                        </tr> ';
            }

            $html .= '</tbody></table></div>';

            $mpdf->WriteHTML($html);

            $mpdf->AddPage(); // กำหนดขอบกระดาษ

            $html = '
            <div style="position: absolute; left: 30; top: 30; width: 15%; height: 100%;">
                <img src="' . $branche_image . '" alt="Branch Logo" style="width: 100%; height: auto;">
            </div>
                <div style="margin-bottom: 5px; padding: -25px 120px; line-height: 0.5;">
                    <h2>' . $branche_Name . '</h2>
                    <p style="font-size: 20px;">' . $branche_address . '</p>
                    <p style="font-size: 18px;">' . $branche_phone . '</p>
                </div>
                <div style="position: absolute; left: 38px;top:98px; text-align:center;padding-left: 360px;">
                    <p class="highlight" style="background-color: #87ffb9  ;text-align:center; font-weight: bold;padding:5px 20px -5px 20px; display: inline-block;height: 40px;width: 150px; border-radius: 15px;font-size: 24px;border: 0.2px solid #000;">
                        ใบวางบิล
                    </p>
                </div>
                <div style="position: absolute; left: 55px;top:102px; text-align:center;padding-left: 440px;padding-bottom: 5px;">
                    <p style="color: #000;font-weight: bold;margin-top:30px;padding-left: 15px; display: inline-block;line-height: 30px;font-size: 20px;">
                        สำเนา (สำหรับลูกค้า)
                    </p>
                </div>

                <div style="position: absolute; left: 38px;top:175px; width: 42.3%;padding-left: 20px;line-height: 1.8; height: 160px;border-radius: 7px;border: 0.5px solid #000;">
                    <p style="font-size: 18px;">ลูกค้า:</p>
                    <p style="font-size: 18px;"></p>
                    <p style="font-size: 18px;"></p>
                    <p style="font-size: 18px;padding-top:65px;">&nbsp;</p>
                </div>
                <div style="position: absolute; right: 38px;top:175px; width: 42.3%;padding-left: 20px;line-height: 2.5; height: 160px;border-radius: 7px;border: 0.5px solid #000;">
                    <p>เลขที่ใบวางบิล:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong></strong></p>
                    <p>วันที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                    <p>เงื่อนไขการชำระเงิน:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                </div>

                <div class="invoice-container" style="width:718px;margin-top: 222px;margin-bottom:10px;border-radius: 7px;border: 0.5px solid #000;">
                    <table class="curved-table" style="width: 100%;border-collapse: collapse;">
                        <thead>
                            <tr>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-radius: 10px;height: 30px;">No.</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">เอกสารที่ใบกำกับ</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันที่</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันครบกำหนด</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">จำนวนเงิน</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">ชำระแล้ว</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;">เงินคงค้าง</td>
                            </tr>
                        </thead>';

            $rowsPerPage = 15;
            $counter     = 1;
            $currentRow  = 0; // แถวปัจจุบัน
            if ($currentRow <= $rowsPerPage || $currentRow >= $rowsPerPage) {

                while ($counter <= 15) {

                    $html .= '
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: left;padding-left: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;width: auto;height: 33px;"></td>
                        </tr>';
                    $counter++;
                    $currentRow++;
                }
                $html .= '
                        <tr>
                            <td colspan="5" style="border: 0.5px solid #000;border-right:none;padding-left: 10px;border-left:none;height: 40px;">
                                <div class="footer1" style="text-align: left;">
                                    <p>(หนึ่งพันเจ็ดสิบบาทถ้วน)</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-left:none;text-align: right; padding-right: 5px;height: 40px;">
                                <div class="footer2">
                                    <p>รวมทั้งสิ้น</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-right:none;text-align: right; padding-right: 5px;height: 40px;">
                                <p></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="7" style="text-align: left; vertical-align: top;width: 100%;height: 70px;padding-top: 10px; padding-left: 10px;border: 1px solid #000;border-left:none;border-right:none;border-top:none;">
                                <div>
                                    <p>หมายเหตุ: </p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" style="vertical-align: top;height: 50px;line-height: 1.5;padding-left: 10px;padding-top:10px;padding-bottom: 15px;">
                                <div>
                                    <p>ชื่อผู้รับวางบิล   _________________</p>
                                    <p>วันที่รับเงิน     ____/_____/____</p>
                                    <p>วันที่นัดรับเช็ค  ____/_____/____</p>
                                </div>
                            </td>
                            <td colspan="3" style="vertical-align: top;height: 50px;line-height: 1.2;padding-top:20px;padding-bottom: 15px;">
                                <div>
                                    <p>ในนาม ' . $branche_Name . '</p>
                                    <br>
                                    <p>ชื่อผู้จ่ายเงิน _________________</p>
                                </div>
                            </td>
                        </tr> ';
            }

            $html .= '</tbody></table></div>';

            $mpdf->WriteHTML($html);
        } else {

            $mpdf->AddPage(); // กำหนดขอบกระดาษ

            $html = '
            <style>
                .border {
                    border: 1px solid black;
                    border-top-left-radius: 0.5em;
                    border-top-right-radius: 0.5em;
                    border-bottom-right-radius: 0.5em;
                    border-bottom-left-radius: 0.5em;
                }
                .border1 {
                    border: 1px solid black;
                    border-top-left-radius: 0.5em;
                    border-top-right-radius: 0.5em;
                    border-bottom-right-radius: 0.5em;
                    border-bottom-left-radius: 0.5em;
                }
                .container {
                    border: 1px solid black;
                }
                .one {
                    display: inline-block;
                    line-height: 0.1px;
                    font-size: 8px;
                    vertical-align: top;
                }
                .two {
                    display: table-cell;
                    height: 30px;
                    vertical-align: bottom;
                    font-size: 10px;
                    background-color: red;
                }
                .grid-container {
                    display: grid;
                    column-gap: 5px;
                    grid-template-columns: auto auto;
                    padding: 10px;
                }
                .grid-item {
                    border: 1px solid rgba(0, 0, 0, 0.8);
                    padding: 20px;
                }
                .under{
                    vertical-align: text
                }

                #header th {
                    line-height: 0.65;
                    padding: 7px;
                    padding-top: 10px;
                }
                #header1 td {
                    line-height: 0.70;
                    padding-top: 5px;
                }
            </style>
            <div style="position: absolute; left: 30; top: 30; width: 15%; height: 100%;">
                <img src="' . $branche_image . '" alt="Branch Logo" style="width: 100%; height: auto;">
            </div>
                <div style="margin-bottom: 5px; padding: -25px 120px; line-height: 0.5;">
                    <h2>' . $branche_Name . '</h2>
                    <p style="font-size: 20px;">' . $branche_address . '</p>
                    <p style="font-size: 18px;">' . $branche_phone . '</p>
                </div>
                <div style="position: absolute; left: 38px;top:98px; text-align:center;padding-left: 360px;">
                    <p class="highlight" style="background-color: #f587ff ;text-align:center; font-weight: bold;padding:5px 20px -5px 20px; display: inline-block;height: 40px;width: 150px; border-radius: 15px;font-size: 24px;border: 0.2px solid #000;">
                        ใบวางบิล
                    </p>
                </div>
                <div style="position: absolute; left: 55px;top:102px; text-align:center;padding-left: 440px;padding-bottom: 5px;">
                    <p style="color: #000;font-weight: bold;margin-top:30px;padding-left: 15px; display: inline-block;line-height: 30px;font-size: 20px;">
                        ต้นฉบับ (สำหรับลูกค้า)
                    </p>
                </div>';

            $date  = new DateTime($Billing->date);
            $ndate = $date->format('d/m/y');
            $html .= '
                <div style="width: 100%; padding-top: 60px;">
                        <div class="border" style="float: left; width: 49.5%;height: 140px; padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;line-height:1.3;">
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">ลูกค้า ' . $Billing->customer_no . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">' . $Billing->customer_name . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px; line-height:1.3;">' . $Billing->customer_address . ' ' . $Billing->customer_address2 . ' </td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%; height: 140px;padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ใบวางบิล:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Billing->no . '</strong>  </td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">วันที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $ndate . '</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">เงื่อนไขการชำระเงิน:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($Billing->condition ?? '') . '</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="invoice-container" style="width:718px;margin-top: 10px;margin-bottom:10px;border-radius: 7px;border: 0.5px solid #000;">
                        <table class="curved-table" style="width: 100%;border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <td style="text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-radius: 10px;height: 30px;">No.</td>
                                    <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">เอกสารที่ใบกำกับ</td>
                                    <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันที่</td>
                                    <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันครบกำหนด</td>
                                    <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">จำนวนเงิน</td>
                                    <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">ชำระแล้ว</td>
                                    <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;">เงินคงค้าง</td>
                                </tr>
                            </thead>';

            $totalpaid     = 0;
            $rowsPerPage   = 15;
            $counter       = 1;
            $totalInvoices = count($Billing['billingInvoice']);
            $currentRow    = 0; // แถวปัจจุบัน
            if ($totalInvoices <= $rowsPerPage || $totalInvoices >= $rowsPerPage) {
                foreach ($Billing['billingInvoice'] as $data) {
                    if ($currentRow > 0 && $currentRow % $rowsPerPage == 0) {

                        $html .= '

                            <tr>
                                <td colspan="5" style="border: 0.5px solid #000;border-right:none;padding-left: 10px;border-left:none;height: 40px;">
                                    <div class="footer1" style="text-align: left;">
                                        <p>(' . $this->convertToThaiText($totalpaid) . ')</p>
                                    </div>
                                </td>
                                <td style="border: 0.5px solid #000;border-left:none;text-align: right; padding-right: 5px;height: 40px;">
                                    <div class="footer2">
                                        <p>รวมทั้งสิ้น</p>
                                    </div>
                                </td>
                                <td style="border: 0.5px solid #000;border-right:none;text-align: right; padding-right: 5px;height: 40px;">
                                    <p>' . number_format($totalpaid, 2) . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="7" style="text-align: left; vertical-align: top;width: 100%;height: 70px;padding-top: 10px; padding-left: 10px;border: 1px solid #000;border-left:none;border-right:none;border-top:none;">
                                    <div>
                                        <p>หมายเหตุ: ' . $Billing->remark . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" style="vertical-align: top;height: 50px;line-height: 1.5;padding-left: 10px;padding-top:10px;padding-bottom: 15px;">
                                    <div>
                                        <p>ชื่อผู้รับวางบิล   _________________</p>
                                        <p>วันที่รับเงิน     ____/_____/____</p>
                                        <p>วันที่นัดรับเช็ค  ____/_____/____</p>
                                    </div>
                                </td>
                                <td colspan="3" style="vertical-align: top;height: 50px;line-height: 1.2;padding-top:20px;padding-bottom: 15px;">
                                    <div>
                                        <p>ในนาม ' . $branche_Name . '</p>
                                        <br>
                                        <p>ชื่อผู้จ่ายเงิน _________________</p>
                                    </div>
                                </td>
                            </tr> ';
                        $html .= '</tbody></table></div>';                         // ปิด tbody เดิม
                        $html .= '<div style="page-break-before: always;"></div>'; // เพิ่มการขึ้นหน้าใหม่
                        $html .= '
                        <div style="position: absolute; left: 30; top: 30; width: 15%; height: 100%;">
                            <img src="' . $branche_image . '" alt="Branch Logo" style="width: 100%; height: auto;">
                        </div>
                                    <div style="margin-bottom: 5px; padding: -25px 120px; line-height: 0.5;">
                                        <h2>' . $branche_Name . '</h2>
                                        <p style="font-size: 20px;">' . $branche_address . '</p>
                                        <p style="font-size: 18px;">' . $branche_phone . '</p>
                                    </div>
                                    <div style="position: absolute; left: 38px;top:98px; text-align:center;padding-left: 360px;">
                                        <p class="highlight" style="background-color: #f587ff ;text-align:center; font-weight: bold;padding:5px 20px -5px 20px; display: inline-block;height: 40px;width: 150px; border-radius: 15px;font-size: 24px;border: 0.2px solid #000;">
                                            ใบวางบิล
                                        </p>
                                    </div>
                                    <div style="position: absolute; left: 55px;top:102px; text-align:center;padding-left: 440px;padding-bottom: 5px;">
                                        <p style="color: #000;font-weight: bold;margin-top:30px;padding-left: 15px; display: inline-block;line-height: 30px;font-size: 20px;">
                                            ต้นฉบับ (สำหรับลูกค้า)
                                        </p>
                                    </div>

                                    <div style="width: 100%; padding-top: 60px;">
                        <div class="border" style="float: left; width: 49.5%;height: 140px; padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;line-height:1.3;">
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">ลูกค้า ' . $Billing->customer_no . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">' . $Billing->customer_name . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px; line-height:1.3;">' . $Billing->customer_address . ' ' . $Billing->customer_address2 . ' </td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%; height: 140px;padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ใบวางบิล:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Billing->no . '</strong>  </td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">วันที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $ndate . '</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">เงื่อนไขการชำระเงิน:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($Billing->condition ?? '') . '</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                                    <div class="invoice-container" style="width:718px;margin-top: 10px;margin-bottom:10px;border-radius: 7px;border: 0.5px solid #000;">';
                        $html .= '
                                    <table class="curved-table" style="width: 100%;border-collapse: collapse;">

                                        <thead>
                                            <tr>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-radius: 10px;height: 30px;">No.</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">เอกสารที่ใบกำกับ</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันที่</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันครบกำหนด</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">จำนวนเงิน</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">ชำระแล้ว</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;">เงินคงค้าง</td>
                                            </tr>
                                        </thead>';

                        $totalpaid += $data->invoice_paid;
                        $totalpaid = 0;
                        $html .= '
                                        <tbody style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;">'; // เริ่ม tbody ใหม่
                        $counter = 1;                                                                                                                                       // รีเซ็ตตัวนับเมื่อขึ้นหน้าใหม่

                    }
                    $invoice_id = $data->invoice_id;
                    $hiscount   = isset($hiscountData[$invoice_id]) ? $hiscountData[$invoice_id] : 0;
                    if (! is_numeric($hiscount)) {
                        $hiscount = (float) $hiscount->total_paid; // สมมติว่า 'amount' เป็นฟิลด์ที่เก็บค่าตัวเลข
                    }
                    $iddate       = new DateTime($data->invoice_date);
                    $idddate      = new DateTime($data->invoice_due_date);
                    $idate        = $iddate->format('d/m/y');
                    $duedate      = $idddate->format('d/m/y');
                    $totalpaidcal = $data->invoice_total - $hiscount;
                    $totalpaid += $totalpaidcal;
                    $html .= '
                        <tbody style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;"> // เริ่ม tbody ใหม่
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $counter . '</td>
                            <td style="text-align: left;padding-left: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $data->invoice_no . '</td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $idate . '</td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $duedate . '</td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . number_format($data->invoice_total, 2) . '</td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . number_format($hiscount, 2) . '</td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;width: auto;height: 33px;">' . number_format($totalpaidcal, 2) . '</td>
                        </tr>';
                    $counter++;
                    $currentRow++;
                }
                while ($counter <= 15) {

                    $html .= '
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: left;padding-left: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;width: auto;height: 33px;"></td>
                        </tr>';
                    $counter++;
                    $currentRow++;
                }

                $html .= '

                        <tr>
                            <td colspan="5" style="border: 0.5px solid #000;border-right:none;padding-left: 10px;border-left:none;height: 40px;">
                                <div class="footer1" style="text-align: left;">
                                    <p>(' . $this->convertToThaiText($totalpaid) . ')</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-left:none;text-align: right; padding-right: 5px;height: 40px;">
                                <div class="footer2">
                                    <p>รวมทั้งสิ้น</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-right:none;text-align: right; padding-right: 5px;height: 40px;">
                                <p>' . number_format($totalpaid, 2) . '</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="7" style="text-align: left; vertical-align: top;width: 100%;height: 70px;padding-top: 10px; padding-left: 10px;border: 1px solid #000;border-left:none;border-right:none;border-top:none;">
                                <div>
                                    <p>หมายเหตุ: ' . $Billing->remark . '</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" style="vertical-align: top;height: 50px;line-height: 1.5;padding-left: 10px;padding-top:10px;padding-bottom: 15px;">
                                <div>
                                    <p>ชื่อผู้รับวางบิล   _________________</p>
                                    <p>วันที่รับเงิน     ____/_____/____</p>
                                    <p>วันที่นัดรับเช็ค  ____/_____/____</p>
                                </div>
                            </td>
                            <td colspan="3" style="vertical-align: top;height: 50px;line-height: 1.2;padding-top:20px;padding-bottom: 15px;">
                                <div>
                                    <p>ในนาม ' . $branche_Name . '</p>
                                    <br>
                                    <p>ชื่อผู้จ่ายเงิน _________________</p>
                                </div>
                            </td>
                        </tr> ';
            }

            $html .= '</tbody></table></div>';

            $mpdf->WriteHTML($html);

            $mpdf->AddPage(); // กำหนดขอบกระดาษ

            $html = '
            <div style="position: absolute; left: 30; top: 30; width: 15%; height: 100%;">
                <img src="' . $branche_image . '" alt="Branch Logo" style="width: 100%; height: auto;">
            </div>

            <div style="margin-bottom: 5px; padding: -25px 120px; line-height: 0.5;">
                <h2>' . $branche_Name . '</h2>
                <p style="font-size: 20px;">' . $branche_address . '</p>
                <p style="font-size: 18px;">' . $branche_phone . '</p>
            </div>
            <div style="position: absolute; left: 38px;top:98px; text-align:center;padding-left: 360px;">
                <p class="highlight" style="background-color: #87ffb9  ;text-align:center; font-weight: bold;padding:5px 20px -5px 20px; display: inline-block;height: 40px;width: 150px; border-radius: 15px;font-size: 24px;border: 0.2px solid #000;">
                    ใบวางบิล
                </p>
            </div>
            <div style="position: absolute; left: 55px;top:102px; text-align:center;padding-left: 440px;padding-bottom: 5px;">
                <p style="color: #000;font-weight: bold;margin-top:30px;padding-left: 15px; display: inline-block;line-height: 30px;font-size: 20px;">
                    สำเนา (สำหรับลูกค้า)
                </p>
            </div>

            <div style="width: 100%; padding-top: 60px;">
                        <div class="border" style="float: left; width: 49.5%;height: 140px; padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;line-height:1.3;">
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">ลูกค้า ' . $Billing->customer_no . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">' . $Billing->customer_name . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px; line-height:1.3;">' . $Billing->customer_address . ' ' . $Billing->customer_address2 . ' </td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%; height: 140px;padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ใบวางบิล:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Billing->no . '</strong>  </td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">วันที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $ndate . '</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">เงื่อนไขการชำระเงิน:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($Billing->condition ?? '') . '</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                <div class="invoice-container" style="width:718px;margin-top: 10px;margin-bottom:10px;border-radius: 7px;border: 0.5px solid #000;">
                    <table class="curved-table" style="width: 100%;border-collapse: collapse;">
                        <thead>
                            <tr>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-radius: 10px;height: 30px;">No.</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">เอกสารที่ใบกำกับ</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันที่</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันครบกำหนด</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">จำนวนเงิน</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">ชำระแล้ว</td>
                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;">เงินคงค้าง</td>
                            </tr>
                        </thead>';

            $totalpaid     = 0;
            $rowsPerPage   = 15;
            $counter       = 1;
            $totalInvoices = count($Billing['billingInvoice']);
            $currentRow    = 0; // แถวปัจจุบัน
            if ($totalInvoices <= $rowsPerPage || $totalInvoices >= $rowsPerPage) {
                foreach ($Billing['billingInvoice'] as $data) {
                    if ($currentRow > 0 && $currentRow % $rowsPerPage == 0) {

                        $html .= '

                            <tr>
                                <td colspan="5" style="border: 0.5px solid #000;border-right:none;padding-left: 10px;border-left:none;height: 40px;">
                                    <div class="footer1" style="text-align: left;">
                                        <p>(' . $this->convertToThaiText($totalpaid) . ')</p>
                                    </div>
                                </td>
                                <td style="border: 0.5px solid #000;border-left:none;text-align: right; padding-right: 5px;height: 40px;">
                                    <div class="footer2">
                                        <p>รวมทั้งสิ้น</p>
                                    </div>
                                </td>
                                <td style="border: 0.5px solid #000;border-right:none;text-align: right; padding-right: 5px;height: 40px;">
                                    <p>' . number_format($totalpaid, 2) . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="7" style="text-align: left; vertical-align: top;width: 100%;height: 70px;padding-top: 10px; padding-left: 10px;border: 1px solid #000;border-left:none;border-right:none;border-top:none;">
                                    <div>
                                        <p>หมายเหตุ: ' . $Billing->remark . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" style="vertical-align: top;height: 50px;line-height: 1.5;padding-left: 10px;padding-top:10px;padding-bottom: 15px;">
                                    <div>
                                        <p>ชื่อผู้รับวางบิล   _________________</p>
                                        <p>วันที่รับเงิน     ____/_____/____</p>
                                        <p>วันที่นัดรับเช็ค  ____/_____/____</p>
                                    </div>
                                </td>
                                <td colspan="3" style="vertical-align: top;height: 50px;line-height: 1.2;padding-top:20px;padding-bottom: 15px;">
                                    <div>
                                        <p>ในนาม ' . $branche_Name . '</p>
                                        <br>
                                        <p>ชื่อผู้จ่ายเงิน _________________</p>
                                    </div>
                                </td>
                            </tr> ';
                        $html .= '</tbody></table></div>';                         // ปิด tbody เดิม
                        $html .= '<div style="page-break-before: always;"></div>'; // เพิ่มการขึ้นหน้าใหม่
                        $html .= '
                        <div style="position: absolute; left: 30; top: 30; width: 15%; height: 100%;">
                            <img src="' . $branche_image . '" alt="Branch Logo" style="width: 100%; height: auto;">
                        </div>
                                    <div style="margin-bottom: 5px; padding: -25px 120px; line-height: 0.5;">
                                        <h2>' . $branche_Name . '</h2>
                                        <p style="font-size: 20px;">' . $branche_address . '</p>
                                        <p style="font-size: 18px;">' . $branche_phone . '</p>
                                    </div>
                                    <div style="position: absolute; left: 38px;top:98px; text-align:center;padding-left: 360px;">
                                        <p class="highlight" style="background-color: #87ffb9  ;text-align:center; font-weight: bold;padding:5px 20px -5px 20px; display: inline-block;height: 40px;width: 150px; border-radius: 15px;font-size: 24px;border: 0.2px solid #000;">
                                            ใบวางบิล
                                        </p>
                                    </div>
                                    <div style="position: absolute; left: 55px;top:102px; text-align:center;padding-left: 440px;padding-bottom: 5px;">
                                        <p style="color: #000;font-weight: bold;margin-top:30px;padding-left: 15px; display: inline-block;line-height: 30px;font-size: 20px;">
                                            สำเนา (สำหรับลูกค้า)
                                        </p>
                                    </div>

                                    <div style="width: 100%; padding-top: 60px;">
                        <div class="border" style="float: left; width: 49.5%;height: 140px; padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;line-height:1.3;">
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">ลูกค้า ' . $Billing->customer_no . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px;line-height:1.3;">' . $Billing->customer_name . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px; font-size: 18px; line-height:1.3;">' . $Billing->customer_address . ' ' . $Billing->customer_address2 . ' </td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%; height: 140px;padding-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ใบวางบิล:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Billing->no . '</strong>  </td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">วันที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $ndate . '</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">เงื่อนไขการชำระเงิน:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($Billing->condition ?? '') . '</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                                    <div class="invoice-container" style="width:718px;margin-top: 10px;margin-bottom:10px;border-radius: 7px;border: 0.5px solid #000;">';
                        $html .= '
                                    <table class="curved-table" style="width: 100%;border-collapse: collapse;">

                                        <thead>
                                            <tr>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-radius: 10px;height: 30px;">No.</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">เอกสารที่ใบกำกับ</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันที่</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">วันครบกำหนด</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">จำนวนเงิน</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;border-right: 1px solid #000;height: 30px;">ชำระแล้ว</td>
                                                <td style="text-align: center;border-bottom: 0.5px solid #000;border-top-left-radius: 10px;">เงินคงค้าง</td>
                                            </tr>
                                        </thead>';

                        $totalpaid += $data->invoice_paid;
                        $totalpaid = 0;
                        $html .= '

                                        <tbody style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;">'; // เริ่ม tbody ใหม่
                        $counter = 1;                                                                                                                                       // รีเซ็ตตัวนับเมื่อขึ้นหน้าใหม่
                    }
                    $invoice_id = $data->invoice_id;
                    $hiscount   = isset($hiscountData[$invoice_id]) ? $hiscountData[$invoice_id] : 0;
                    if (! is_numeric($hiscount)) {
                        $hiscount = (float) $hiscount->total_paid;
                    }
                    $iddate       = new DateTime($data->invoice_date);
                    $idddate      = new DateTime($data->invoice_due_date);
                    $idate        = $iddate->format('d/m/y');
                    $duedate      = $idddate->format('d/m/y');
                    $totalpaidcal = $data->invoice_total - $hiscount;
                    $totalpaid += $totalpaidcal;
                    $html .= '
                        <tbody style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;"> // เริ่ม tbody ใหม่
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $counter . '</td>
                            <td style="text-align: left;padding-left: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $data->invoice_no . '</td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $idate . '</td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . $duedate . '</td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . number_format($data->invoice_total, 2) . '</td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . number_format($hiscount, 2) . '</td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;width: auto;height: 33px;">' . number_format($totalpaidcal, 2) . '</td>
                        </tr>';
                    $counter++;
                    $currentRow++;
                }
                while ($counter <= 15) {

                    $html .= '
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: left;padding-left: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;"></td>
                            <td style="text-align: right;padding-right: 10px;border-top-left-radius: 10px;width: auto;height: 33px;"></td>
                        </tr>';
                    $counter++;
                    $currentRow++;
                }

                $html .= '

                        <tr>
                            <td colspan="5" style="border: 0.5px solid #000;border-right:none;padding-left: 10px;border-left:none;height: 40px;">
                                <div class="footer1" style="text-align: left;">
                                    <p>(' . $this->convertToThaiText($totalpaid) . ')</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-left:none;text-align: right; padding-right: 5px;height: 40px;">
                                <div class="footer2">
                                    <p>รวมทั้งสิ้น</p>
                                </div>
                            </td>
                            <td style="border: 0.5px solid #000;border-right:none;text-align: right; padding-right: 5px;height: 40px;">
                                <p>' . number_format($totalpaid, 2) . '</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="7" style="text-align: left; vertical-align: top;width: 100%;height: 70px;padding-top: 10px; padding-left: 10px;border: 1px solid #000;border-left:none;border-right:none;border-top:none;">
                                <div>
                                    <p>หมายเหตุ: ' . $Billing->remark . '</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" style="vertical-align: top;height: 50px;line-height: 1.5;padding-left: 10px;padding-top:10px;padding-bottom: 15px;">
                                <div>
                                    <p>ชื่อผู้รับวางบิล   _________________</p>
                                    <p>วันที่รับเงิน     ____/_____/____</p>
                                    <p>วันที่นัดรับเช็ค  ____/_____/____</p>
                                </div>
                            </td>
                            <td colspan="3" style="vertical-align: top;height: 50px;line-height: 1.2;padding-top:20px;padding-bottom: 15px;">
                                <div>
                                    <p>ในนาม ' . $branche_Name . '</p>
                                    <br>
                                    <p>ชื่อผู้จ่ายเงิน _________________</p>
                                </div>
                            </td>
                        </tr> ';
            }
            $html .= '</tbody></table></div>';

            $mpdf->WriteHTML($html);
        }
        $pdfContent = $mpdf->Output('', 'S');

        $contentLength = strlen($pdfContent);

        $headers = [
            'Content-Type'                  => 'application/pdf',
            'Content-Disposition'           => 'inline; filename=mpdf.pdf',
            'Access-Control-Expose-Headers' => 'Accept-Ranges',
            'Access-Control-Allow-Headers'  => 'Accept-Ranges,range',
            'Accept-Ranges'                 => 'bytes',
            'Content-Length'                => $contentLength,
        ];

        return Response::make($pdfContent, 200, $headers);
    }
}
