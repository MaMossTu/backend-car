<?php

namespace App\Http\Controllers;

use App\Export\CarCheckExport;
use App\Export\CarTaxExport;
use App\Export\CommissionEmployeeExport;
use App\Export\DailyReportExport;
use App\Export\DayExport;
use App\Export\ExpenseExport;
use App\Export\ExpenseReportExport;
use App\Export\GasCheckExport;
use App\Export\Gasreport;
use App\Export\InsuranceExport;
use App\Export\InsuranceReportExport;
use App\Export\MonthExport;
use App\Export\ProfitReportExport;
use App\Export\PrbReportExport;
use App\Export\InsurancesRenewalsReportExport;
use App\Export\EMSReportExport;
use App\Models\AddressEms;
use App\Models\Branches;
use App\Models\Cost;
use App\Models\Customers;
use App\Models\Employees;
use App\Models\Inspections;
use App\Models\Services;
use App\Models\ServiceTransactions;
use App\Models\VehicleGas;
use App\Models\Vehicles;
use App\Models\WorkOrder;
use App\Models\InsurancesRenewals;
use App\Models\VechicleServicesTransactions;
use App\Models\VehicleInspectionTypes;
use App\Models\InspectionVehicles;
use App\Models\TaxInvoiceAllNos;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Format;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;
use Illuminate\Support\Facades\Response;
use DateTime;

use function PHPUnit\Framework\returnSelf;

class ReportToController extends Controller
{
    public function Quotation($id)
    {

        $data = Inspections::with([
            'Branches',
            'Vehicles.VehicleBrands',
            'InspectionVehicles.vehicle.VehicleInspectionTypes',
            'InspectionVehicles.vehicleServiceTransaction.service',
            'Customers.Customer_Addresses.Provinces',
            'Customers.Customer_Addresses.Districts',
            'Customers.Customer_Addresses.SubDistricts',
            'Vehicles',
            'serviceTransactions',
            'Installments'
        ])->where('id', $id)->first();
        // return $data;

        if (!$data) {
            return $this->returnError('ไม่พบข้อมูล');
        }

        $servicename = [];
        $serviceprice = [];
        if ($data->InspectionVehicles) {
            foreach ($data->InspectionVehicles as $inspectionVehicle) {
                $vehicle = $inspectionVehicle->vehicle; // ยานพาหนะที่เกี่ยวข้องกับการตรวจสภาพ
                $inspectionTypes = $vehicle->VehicleInspectionTypes; // ดึงประเภทการตรวจสภาพ
                $licenseplate = $vehicle->license_plate;
                $provinceid = $vehicle->province_id;
                $vehicleprovince = Vehicles::with('Provinces')->where('province_id', $provinceid)->first();
                $provincevehiclename = $vehicleprovince->provinces->name_th_prefix;
            }
            // return $provincevehiclename;
            if ($inspectionVehicle->vehicleServiceTransaction) {
                foreach ($inspectionVehicle->vehicleServiceTransaction as $serviceTransaction) {
                    $servicename[] = $serviceTransaction->service_name;
                    $serviceprice[] = $serviceTransaction->service_price;
                }
            }
        }

        $branchAddress = $data->Branches->address ?? null;
        $branchProvince = $data->Branches->Provinces->name_th_prefix ?? null;
        $branchDistrict = $data->Branches->Districts->name_th_prefix ?? null;
        $branchSubdrict = $data->Branches->SubDistricts->name_th_prefix ?? null;
        $time_open_close = $data->Branches->open_close_time ?? null;
        $open_date = $data->Branches->open_date ?? null;
        $phone = $data->Branches->phone ?? null;
        $branche_image = $data->Branches->logo ?? null;
        $branche_name = $data->Branches->business_name ?? null;
        $branche_tax = $data->Branches->tax_id ?? null;
        $branchpost = $data->Branches->postal_code ?? null;
        // return $branchSubdrict;

        $validprice = $data->validprice ?? null;

        if ($validprice == null || $validprice == 0) {
            $validprice = "0";
        }
        $discount = $data->discount;
        // return $validprice;

        $order_no = $data->no ?? null;

        $customer = $data->customers ?? null;
        $customerName = $customer->name ?? null;
        $customerLName = $customer->lname ?? null;
        $customerno = $customer->no ?? null;
        $phoneNumber = $customer->phone_number1 ?? null;
        $customertax = $customer->tax_id ?? null;
        // return $customer;
        // return $customerName;

        $total_amount = $data->total;
        $discount = $data->discount;
        $remark = $data->remark;

        // ตัดข้อความโดยใช้ substr หรือ mb_substr เพื่อรองรับ UTF-8
        $maxLength = 255; // จำนวนตัวอักษรสูงสุด
        if (mb_strlen($remark, 'UTF-8') > $maxLength) {
            $shortRemark = mb_substr($remark, 0, $maxLength, 'UTF-8') . '...';
        } else {
            $shortRemark = $remark;
        }
        // return $discount;

        // Pull Data Customer Address
        $mainAddress = $data->Customers->Customer_Addresses->firstWhere('is_main', 1);
        $addressObj = $mainAddress ?? $data->Customers->Customer_Addresses->first();
        // return $addressObj;
        $address = $addressObj->address ?? null;
        $provinceName = $addressObj->provinces->name_th_prefix ?? null;
        $districtName = $addressObj->districts->name_th_prefix ?? null;
        $subdistrictName = $addressObj->SubDistricts->name_th_prefix ?? null;
        $postalcode = $addressObj->zip_code ?? null;
        // return $addressObj;
        // $paid = $data->Installments ?? null;
        $paid = "";
        foreach ($data->Installments as $installment) {
            switch ($installment->paid_type) {
                case 'paid_cash':
                    $paid = "เงินสด";
                    break;
                case 'paid_tran':
                    $paid = "เงินโอน";
                    break;
                case 'credit':
                    $paid = "บัตรเครดิต";
                    break;
                default:
                    $paid = "";
            }
        }
        // dd($paid);


        switch ($data->customer_is_headquarter) {
            case 1:
                $headqurter = "สำนักงานใหญ่";
                $chack = "&#9745;";
                break;
            case 0;
                $headqurter = "";
                $chack = "&#9744;";
                break;
            default:
                $headqurter = "";
                $chack = "&#9744;";
                break;
        }
        if (empty($data->branch)) {
            $Bheadqurter = "";
        } else {
            switch ($data->Branches->is_headquarter) {
                case 1:
                    $Bheadqurter = "สำนักงานใหญ่";
                    break;
                case 0;
                    $Bheadqurter = "";
                    break;
                default:
                    $Bheadqurter = "";
                    break;
            }
        }

        //Formatted Date
        $date = $data->date;
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $year = substr($formattedDate, -4);
        $formattedDate = str_replace($year, $year + 543, $formattedDate);
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);

        $appoindate = $data->appoinment;
        $appoinformattedDate = Carbon::parse($appoindate)->locale('th')->translatedFormat('d F Y');
        $year = substr($appoinformattedDate, -4);
        $appoinformattedDate = str_replace($year, $year + 543, $appoinformattedDate);
        $appoinformattedDate = Carbon::parse($appoindate)->locale('th')->translatedFormat('d F Y');
        $appoinformattedDate = str_replace((date('Y') + 543), date('Y') + 543, $appoinformattedDate);
        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ

        $totalAmount = 0;
        $serviceRows = '';
        $id_gen = 0;
        $round = 0;

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

        $itemsPerPage = 14;
        $totalItems = count($serviceprice);
        $pages = ceil($totalItems / $itemsPerPage);
        // return $totalItems;
        $Subtotal = 0;


        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                // $mpdf->AddPage('L); สำหรับกระดาษแนวนอน
                $mpdf->AddPage();
            }

            $html = '
            <div style="position: absolute;top:40px;left:42px;">
                <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
            </div>

            <div style="position: absolute; left: 22%; top: 4.1%; font-size: 27px;">' . $branche_name . '</div>
            <div style="position: absolute; left: 22%; top: 7.5%; font-size: 18px;">' . $branchAddress . ' <br> ' . $phone . '<br> เลขประจำตัวผู้เสียภาษี/ Tax ID &nbsp; ' . $branche_tax . ' &nbsp; ' . $Bheadqurter . '</div>


            <div id="page_color" style="font-size: 25px; font-weight: bold; background-color: #c2f0f3; position: absolute; left: 67%; top: 11.2%; width: 207px; border: 1.5px solid #000000; border-radius: 15px;">
            &nbsp; ใบเสนอราคา / Quotation
            </div>
            <div style="position: absolute; left: 9%; top: 14.7%; font-size: 12px;">' . $customerno . '</div>
            <div style="position: absolute; left: 77.5%; top: 14.6%; font-size: 18px;">(ต้นฉบับ)</div>


            ';
            //ส่วนของกล่องข้อความด้านซ้าย
            $html .= '
            <div style="position: absolute; left: 7%; top: 17.1%; width: 336px; height: 182px; border: 1px solid #000; border-radius: 10px; padding: 3px;"></div>
            <div style="position: absolute; left: 9%; top: 17.8%; font-size: 18px; line-height: 0.9;">
                ถึง <br> <span style="font-size: 12px;">To</span> &nbsp;
            </div>
            <div style="position: absolute; left: 14.3%; top: 17.9%; font-size: 18px; line-height: 0.9;">
                ' . $customerno . '
            </div>
            <div style="position: absolute; left: 9%; top: 20.3%; font-size: 18px; line-height: 1.3;">
                <b> ' . $customerName . '  ' . $customerLName . '<br>
                    ' . $address . '<br>
                    ' . $subdistrictName . '&nbsp;&nbsp; ' . $districtName . '&nbsp;&nbsp; ' . $provinceName . ' &nbsp;&nbsp;' . $postalcode  . '
            </div>
            <div style="position: absolute; left: 9%; top: 26.8%; font-size: 18px; line-height: 0.6;">
                เลขประจำตัวผู้เสียภาษี <br> <span style="font-size: 12px;">Tax ID</span> &nbsp;
            </div>
            <div style="position: absolute; left: 24.5%; top: 27.1%; font-size: 16px; line-height: 0.6;width:95px;">
            <b> ' . $customertax . '
            </div>
            <div style="position: absolute;left: 37.3%; top: 25.7%; font-size: 18px; line-height: 0.6;">
                    <div>
                        <p style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $chack . '</p>
                    </div>
                </div>
            <div style="position: absolute; left: 40.3%; top: 27.3%; font-size: 18px; line-height: 0.6;">
                </b> สำนักงานใหญ่
            </div>
            <div style="position: absolute; left: 9%; top: 29.3%; font-size: 16px; line-height: 0.6;">
                โทร. <br> <span style="font-size: 12px;">Tel.</span> &nbsp;
            </div>
            <div style="position: absolute; left: 13.2%; top: 29.7%; font-size: 15px; line-height: 0.6;">
                ' . $phoneNumber . '
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
                <b>' . $order_no . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 22.5%; font-size: 17px; line-height: 0.6;">
                วันที่ <br> <span style="font-size: 12px;">Date</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 22.2%; font-size: 17px;">
                <b>' . $formattedDate . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 27%; font-size: 17px; line-height: 0.65;">
                ยืนราคา <br> <span style="font-size: 12px;">Valid Price</span> &nbsp;
            </div>
            <div style="position: absolute; left: 64%; top: 26.7%; font-size: 17px;">
                <b>' . $validprice . '<p style="color:white;">1</p>&nbsp;</b>
            </div>
            <div style="position: absolute; left: 67%; top: 27%; font-size: 17px; line-height: 0.65;">
                วัน<br> <span style="font-size: 12px;">Days</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 27.2%; font-size: 17px; line-height: 0.6;">
                ถึงวันที่ <br> <span style="font-size: 12px;">Valid trill</span> &nbsp;
            </div>
            <div style="position: absolute; left: 80.5%; top: 27.4%; font-size: 16px; line-height: 0.6;">
                <b>' . $appoinformattedDate . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 29.5%; font-size: 17px; line-height: 0.65;">
                เงื่อนไขชำระเงิน <br> <span style="font-size: 12px;">Payment Term</span>
            </div>
            <div style="position: absolute; left: 72%; top: 29.7%; font-size: 17px; line-height: 0.6;">
                <b> ' . $paid . '
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
            <div style="width: 90%; height: 59.24%; position: absolute; left: 7%; top: 37%; border: 1px solid #000; padding: 0px; border-radius: 10px 10px 10px 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tbody id="tbody1" style="">
                        <tr id="header">
                            <td style="width: 7%; border-left: none;text-align: center;">
                                ลำดับ<br><span style="font-size: 12px;">No.</span>
                            </td>
                            <td style="width: 46%;text-align: center;">
                                รหัสสินค้า/รายละเอียด<br><span style="font-size: 12px;">Code/Descriptions</span>
                            </td>
                            <td style="width: 17%;text-align: center;">
                                จำนวน<br><span style="font-size: 12px;">Quantity</span>
                            </td>
                            <td style="width: 14%;text-align: center;">
                                หน่วยละ<br><span style="font-size: 12px;">Unit Price</span>
                            </td>
                            <td style="width: 16%;text-align: center;">
                                จำนวนเงิน<br><span style="font-size: 12px;">Amount</span>
                            </td>
                        </tr>
                        ';
            // รีเซ็ตยอดรวมสำหรับแต่ละหน้า
            $Subtotal = 0;

            $TotalAmount = 0;
            $VatAmount = 0;
            $NetAmount = 0;
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {

                // คำนวณราคาสุทธิของสินค้ารายการนั้น ๆ
                $sum = str_replace(',', '', $serviceprice[$i]); //* $product['quantity']
                // ยอดรวมทั้งหมด
                $Subtotal += $sum;
                // ยอดรวมส่วนลดทั้งหมด

                $html .= '
                    <tr id="body">
                        <td style="text-align: center; border: none;">' . ($i + 1) . '</td>
                        <td style="text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">' . $servicename[$i] . '</td>
                        <td style="text-align: center; border-right: none; border-top: none; border-bottom: none;">1</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . $serviceprice[$i] . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($sum, 2) . '</td>
                    </tr>';
            }
            // ยอดรวมหลังหักส่วนลด
            $TotalAmount = $Subtotal - $discount;
            // ภาษีมูลค่าเพิ่ม 7%
            $VatAmount = $TotalAmount * 0.07;
            // ยอดรวมทั้งสิ้น
            $NetAmount = $total_amount;
            // แปลงจำนวนยอดเป็นตัวอักษร
            $NetAmountText = $this->convertToThaiText(number_format($NetAmount, 2));

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                    <tr id="body">
                        <td style="width: 7%; text-align: center; border: none;">&nbsp;</td>
                        <td style="width: 46%; text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">&nbsp;</td>
                        <td style="width: 17%; text-align: center; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 14%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 16%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                    </tr>';
            }
            $html .= '
                    </tbody>
                    <tbody id="tbody2">
                        <tr>
                            <td colspan="2" style="padding-left: 15px; padding-top: 15px; line-height: 0.7; border-top: 1px solid #000;">
                                หมายเหตุ <br><span style="font-size: 12px;">Remark</span>
                            </td>
                            <td colspan="2" style="padding-left: 63px; padding-top: 20px; line-height: 0.7; border-top: 1px solid #000;">
                                รวมเป็นเงิน <br><span style="font-size: 12px;">Subtotal</span>
                            </td>
                            <td style="padding-right: 9px; padding-top: 20px; font-size: 17px; text-align: right; border-left: 1px solid #000; border-top: 1px solid #000;">' . number_format($Subtotal, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-left: 22px;">ทะเบียน ' . $licenseplate . ' ' . $provincevehiclename . '</td>
                            <td colspan="2" style="padding-left: 63px; line-height: 0.7;">
                                <u>หัก</u> &nbsp; ส่วนลด<br><span style="font-size: 12px;">Less Discount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($discount, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-left: 22px;">' . $shortRemark . '</td>
                            <td colspan="2" style="padding-left: 63px; line-height: 0.7;">
                                จำนวนเงินหลังหักส่วนลด<br><span style="font-size: 12px;">Total Amount</span>
                            </td>
                            <td style="padding-right: 9px; padding-bottom: 9px;font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($TotalAmount, 2) . '</td>
                        </tr>
                        <tr>

                        </tr>
                        <tr style="border: 1px solid #000; border-left: none; border-right: none;">
                            <td colspan="2" style="padding: 4.5px; text-align: center;"> (' . $NetAmountText . ') </td>
                            <td colspan="2" style="padding-left: 110px; line-height: 0.7;padding-top: 10px;padding-bottom: 5px;">
                                จำนวนเงินรวมทั้งสิ้น<br><span style="font-size: 12px;">Net Amount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;line-height: 0.7;"><b>' . number_format($NetAmount, 2) . '</b></td>
                        </tr>
                    </tbody>
                    <tbody id="tbody3">
                        <tr style="border-bottom: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: center; line-height: 1.3; padding-right: 40px; padding-top: 15px; font-size: 17px;">
                                กรุณาเซ็นยืนยันการสั่งซื้อด้านล่างนี้ <br> Please sign below for purchasing comfirmation.
                            </td>
                            <td style="font-size: 13px; text-align: center; border-right: none;">
                                &nbsp; <br>
                                ในนาม
                            </td>>
                            <td colspan="3" style="text-align: center; padding-top: 1px; line-height: 1.1; padding-right: 35px; border: none;padding-top: 15px;">
                                ขอแสดงความนับถือ <br>
                                <span><b>' . $branche_name . '</b></span>
                            </td>
                        </tr>
                        <tr style="border-top: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: left; padding-left: 40px; padding-top: ; line-height: 1.4;">
                                __________________________ <br>
                                (__________________________) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ____/____/____ <br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ผู้สั่งซื้อสิ้นค้า/Ordered by &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; วันที่/Date
                            </td>

                            <td style="border-right: none;"> </td>
                            <td colspan="3" style="text-align: center; line-height: 1; padding-right: 35px; border: none; line-height: 1.4;">
                                _______________________________ <br>
                                (__________________________) <br>
                                _______________________________ <br>
                                ผู้เตรียมเอกสาร/Prepared by
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            ';
            $mpdf->WriteHTML($html);
            $mpdf->AddPage();
            $html_copy = '
            <div style="position: absolute;top:40px;left:42px;">
                <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
            </div>

            <div style="position: absolute; left: 22%; top: 4.1%; font-size: 27px;">' . $branche_name . '</div>
            <div style="position: absolute; left: 22%; top: 7.5%; font-size: 18px;">' . $branchAddress . ' <br> ' . $phone . '<br> เลขประจำตัวผู้เสียภาษี/ Tax ID &nbsp; ' . $branche_tax . ' &nbsp; ' . $Bheadqurter . '</div>

            <div id="page_color" style="font-size: 25px; font-weight: bold; background-color: #C0DCC0; position: absolute; left: 67%; top: 11.2%; width: 207px; border: 1.5px solid #000000; border-radius: 15px;">
            &nbsp; ใบเสนอราคา / Quotation
            </div>
            <div style="position: absolute; left: 9%; top: 14.7%; font-size: 12px;">' . $customerno . '</div>
            <div style="position: absolute; left: 77.5%; top: 14.6%; font-size: 18px;">(สำเนา)</div>


            ';
            //ส่วนของกล่องข้อความด้านซ้าย
            $html_copy .= '
            <div style="position: absolute; left: 7%; top: 17.1%; width: 336px; height: 182px; border: 1px solid #000; border-radius: 10px; padding: 3px;"></div>
            <div style="position: absolute; left: 9%; top: 17.8%; font-size: 18px; line-height: 0.9;">
                ถึง <br> <span style="font-size: 12px;">To</span> &nbsp;
            </div>
            <div style="position: absolute; left: 14.3%; top: 17.9%; font-size: 18px; line-height: 0.9;">
                ' . $customerno . '
            </div>
            <div style="position: absolute; left: 9%; top: 20.3%; font-size: 18px; line-height: 1.3;">
                <b> ' . $customerName . ' ' . $customerLName . ' <br>
                    ' . $address . '<br>
                    ' . $subdistrictName . '&nbsp;&nbsp; ' . $districtName . '&nbsp;&nbsp; ' . $provinceName . ' &nbsp;&nbsp;' . $postalcode  . '
            </div>
            <div style="position: absolute; left: 9%; top: 26.8%; font-size: 18px; line-height: 0.6;">
                เลขประจำตัวผู้เสียภาษี <br> <span style="font-size: 12px;">Tax ID</span> &nbsp;
            </div>
            <div style="position: absolute; left: 24.5%; top: 27.1%; font-size: 16px; line-height: 0.6;width:95px;">
            <b> ' . $customertax . '
            </div>
            <div style="position: absolute;left: 37.3%; top: 25.7%; font-size: 18px; line-height: 0.6;">
                    <div>
                        <p style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $chack . '</p>
                    </div>
                </div>
            <div style="position: absolute; left: 40.3%; top: 27.3%; font-size: 18px; line-height: 0.6;">
                </b> สำนักงานใหญ่
            </div>
            <div style="position: absolute; left: 9%; top: 29.3%; font-size: 16px; line-height: 0.6;">
                โทร. <br> <span style="font-size: 12px;">Tel.</span> &nbsp;
            </div>
            <div style="position: absolute; left: 13.2%; top: 29.7%; font-size: 15px; line-height: 0.6;">
                ' . $phoneNumber . '
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
                <b>' . $order_no . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 22.5%; font-size: 17px; line-height: 0.6;">
                วันที่ <br> <span style="font-size: 12px;">Date</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 22.2%; font-size: 17px;">
                <b>' . $formattedDate . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 27%; font-size: 17px; line-height: 0.65;">
                ยืนราคา <br> <span style="font-size: 12px;">Valid Price</span> &nbsp;
            </div>
            <div style="position: absolute; left: 64%; top: 26.7%; font-size: 17px;">
                <b>' . $validprice . '<p style="color:white;">1</p>&nbsp;</b>
            </div>
            <div style="position: absolute; left: 67%; top: 27%; font-size: 17px; line-height: 0.65;">
                วัน <br> <span style="font-size: 12px;">Days</span> &nbsp;
            </div>
            <div style="position: absolute; left: 72%; top: 27.2%; font-size: 17px; line-height: 0.6;">
                ถึงวันที่ <br> <span style="font-size: 12px;">Valid trill</span> &nbsp;
            </div>
            <div style="position: absolute; left: 80.5%; top: 27.4%; font-size: 16px; line-height: 0.6;">
                <b>' . $appoinformattedDate . '</b>
            </div>
            <div style="position: absolute; left: 54.7%; top: 29.5%; font-size: 17px; line-height: 0.65;">
                เงื่อนไขชำระเงิน <br> <span style="font-size: 12px;">Payment Term</span>
            </div>
            <div style="position: absolute; left: 72%; top: 29.7%; font-size: 17px; line-height: 0.6;">
                <b> ' . $paid . '
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
            <div style="width: 90%; height: 59.24%; position: absolute; left: 7%; top: 37%; border: 1px solid #000; padding: 0px; border-radius: 10px 10px 10px 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tbody id="tbody1" style="">
                        <tr id="header">
                            <td style="width: 7%; border-left: none;text-align: center;">
                                ลำดับ<br><span style="font-size: 12px;">No.</span>
                            </td>
                            <td style="width: 46%;text-align: center;">
                                รหัสสินค้า/รายละเอียด<br><span style="font-size: 12px;">Code/Descriptions</span>
                            </td>
                            <td style="width: 17%;text-align: center;">
                                จำนวน<br><span style="font-size: 12px;">Quantity</span>
                            </td>
                            <td style="width: 14%;text-align: center;">
                                หน่วยละ<br><span style="font-size: 12px;">Unit Price</span>
                            </td>
                            <td style="width: 16%;text-align: center;">
                                จำนวนเงิน<br><span style="font-size: 12px;">Amount</span>
                            </td>
                        </tr>
                        ';
            // รีเซ็ตยอดรวมสำหรับแต่ละหน้า
            $Subtotal = 0;

            $TotalAmount = 0;
            $VatAmount = 0;
            $NetAmount = 0;
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {

                // คำนวณราคาสุทธิของสินค้ารายการนั้น ๆ
                $sum = str_replace(',', '', $serviceprice[$i]);
                // ยอดรวมทั้งหมด
                $Subtotal += $sum;
                // ยอดรวมส่วนลดทั้งหมด


                $html_copy .= '
                    <tr id="body">
                        <td style="text-align: center; border: none;">' . ($i + 1) . '</td>
                        <td style="text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">' . $servicename[$i] . '</td>
                        <td style="text-align: center; border-right: none; border-top: none; border-bottom: none;">1</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . $serviceprice[$i] . '</td>
                        <td style="text-align: right; border-right: none; border-top: none; border-bottom: none; padding-right: 10px;">' . number_format($sum, 2) . '</td>
                    </tr>';
            }
            // ยอดรวมหลังหักส่วนลด
            $TotalAmount = $Subtotal - $discount;
            // ภาษีมูลค่าเพิ่ม 7%
            $VatAmount = $TotalAmount * 0.07;
            // ยอดรวมทั้งสิ้น
            $NetAmount = $total_amount;
            // แปลงจำนวนยอดเป็นตัวอักษร
            $NetAmountText = $this->convertToThaiText(number_format($NetAmount, 2));

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html_copy .= '
                    <tr id="body">
                        <td style="width: 7%; text-align: center; border: none;">&nbsp;</td>
                        <td style="width: 46%; text-align: left; border-right: none; border-top: none; border-bottom: none; padding-left: 10px;">&nbsp;</td>
                        <td style="width: 17%; text-align: center; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 14%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                        <td style="width: 16%; text-align: right; border-right: none; border-top: none; border-bottom: none;">&nbsp;</td>
                    </tr>';
            }
            $html_copy .= '
                    </tbody>
                    <tbody id="tbody2">
                        <tr>
                            <td colspan="2" style="padding-left: 15px; padding-top: 15px; line-height: 0.7; border-top: 1px solid #000;">
                                หมายเหตุ <br><span style="font-size: 12px;">Remark</span>
                            </td>
                            <td colspan="2" style="padding-left: 63px; padding-top: 20px; line-height: 0.7; border-top: 1px solid #000;">
                                รวมเป็นเงิน <br><span style="font-size: 12px;">Subtotal</span>
                            </td>
                            <td style="padding-right: 9px; padding-top: 20px; font-size: 17px; text-align: right; border-left: 1px solid #000; border-top: 1px solid #000;">' . number_format($Subtotal, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-left: 22px;">ทะเบียน ' . $licenseplate . ' ' . $provincevehiclename . '</td>
                            <td colspan="2" style="padding-left: 63px; line-height: 0.7;">
                                <u>หัก</u> &nbsp; ส่วนลด<br><span style="font-size: 12px;">Less Discount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($discount, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-left: 22px;">' . $shortRemark . '</td>
                            <td colspan="2" style="padding-left: 63px; line-height: 0.7;">
                                จำนวนเงินหลังหักส่วนลด<br><span style="font-size: 12px;">Total Amount</span>
                            </td>
                            <td style="padding-right: 9px; padding-bottom: 9px;font-size: 17px; text-align: right; border-left: 1px solid #000;">' . number_format($TotalAmount, 2) . '</td>
                        </tr>
                        <tr>

                        </tr>
                        <tr style="border: 1px solid #000; border-left: none; border-right: none;">
                            <td colspan="2" style="padding: 4.5px; text-align: center;"> (' . $NetAmountText . ') </td>
                            <td colspan="2" style="padding-left: 110px; line-height: 0.7;padding-top: 10px;padding-bottom: 5px;">
                                จำนวนเงินรวมทั้งสิ้น<br><span style="font-size: 12px;">Net Amount</span>
                            </td>
                            <td style="padding-right: 9px; font-size: 17px; text-align: right; border-left: 1px solid #000;line-height: 0.7;"><b>' . number_format($NetAmount, 2) . '</b></td>
                        </tr>
                    </tbody>
                    <tbody id="tbody3">
                        <tr style="border-bottom: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: center; line-height: 1.3; padding-right: 40px; padding-top: 15px; font-size: 17px;">
                                กรุณาเซ็นยืนยันการสั่งซื้อด้านล่างนี้ <br> Please sign below for purchasing comfirmation.
                            </td>
                            <td style="font-size: 13px; text-align: center; border-right: none;">
                                &nbsp; <br>
                                ในนาม
                            </td>>
                            <td colspan="3" style="text-align: center; padding-top: 1px; line-height: 1.1; padding-right: 35px; border: none;padding-top: 15px;">
                                ขอแสดงความนับถือ <br>
                                <span><b>' . $branche_name . '</b></span>
                            </td>
                        </tr>
                        <tr style="border-top: none;">
                            <td colspan="2" style="border-right: 1px solid #000; text-align: left; padding-left: 40px; padding-top: ; line-height: 1.4;">
                                __________________________ <br>
                                (__________________________) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ____/____/____ <br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ผู้สั่งซื้อสิ้นค้า/Ordered by &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; วันที่/Date
                            </td>

                            <td style="border-right: none;"> </td>
                            <td colspan="3" style="text-align: center; line-height: 1; padding-right: 35px; border: none; line-height: 1.4;">
                                _______________________________ <br>
                                (__________________________) <br>
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

    public function Invoice(Request $request, $id)            //ใบวางบิล
    {
        $data = Inspections::with([
            'Branches',
            'Vehicles.VehicleBrands',
            // 'Employees.Branches',
            // 'Employees.Branches.Provinces',
            // 'Employees.Branches.Districts',
            'Employees.Branches.SubDistricts',
            'InspectionVehicles.vehicle.Provinces',
            'InspectionVehicles.vehicle.VehicleInspectionTypes',
            'InspectionVehicles.vehicle.Customers.Customer_Addresses.Provinces',
            'InspectionVehicles.vehicle.Customers.Customer_Addresses.Districts',
            'InspectionVehicles.vehicle.Customers.Customer_Addresses.SubDistricts',
            'InspectionVehicles.vehicleServiceTransaction.service',
            'Customers.Customer_Addresses.Provinces',
            'Customers.Customer_Addresses.Districts',
            'Customers.Customer_Addresses.SubDistricts',
        ])->where('id', $id)->first();
        // return $data;

        if (!$data) {
            return $this->returnError('ไม่พบข้อมูล');
        }

        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->with([
                    'Provinces',
                    'Districts',
                    'SubDistricts',
                ])
                ->first();
            if (!$branche) {
                return $this->returnError('Branches Not Found');
            }
            // return $branche;
            $employee_branchAddress = $branche->address ?? null;
            $employee_branchProvince = $branche->Provinces->name_th_prefix ?? null;
            $employee_branchDistrict = $branche->Districts->name_th_prefix ?? null;
            $employee_branchSubdrict = $branche->SubDistricts->name_th_prefix ?? null;
            $time_open_close = $branche->open_close_time ?? null;
            $open_date = $branche->open_date ?? null;
            $phone = $branche->phone ?? null;
            $branche_image = $branche->logo_url ?? null;
            $branche_image1 = $branche->logo ?? null;
            $branche_name = $branche->business_name ?? null;
        } else {
            $employee_branchAddress = $data->Branches->address ?? null;
            $employee_branchProvince = $data->Branches->Provinces->name_th_prefix ?? null;
            $employee_branchDistrict = $data->Branches->Districts->name_th_prefix ?? null;
            $employee_branchSubdrict = $data->Branches->SubDistricts->name_th_prefix ?? null;
            $time_open_close = $data->Branches->open_close_time ?? null;
            $open_date = $data->Branches->open_date ?? null;
            $phone = $data->Branches->phone ?? null;
            $branche_image = $data->Branches->logo_url ?? null;
            $branche_image1 = $data->Branches->logo ?? null;
            $branche_name = $data->Branches->business_name ?? null;
        }

        $vehicle = $data->InspectionVehicles[0] ?? null;
        $vehiclePlate = $vehicle->license_plate ?? null;
        $vehicleProvinces = $vehicle->provinces ?? null;
        $vehicleType = $vehicle->vehicle_inspection_types ?? null;

        $order_no = $data->no ?? null;

        $customer = $data ? $data->InspectionVehicles[0] : null;
        $customerName = $customer->name ?? null;
        $phoneNumber = $customer->phone_number ?? null;

        // Pull Data Customer Address
        $mainAddress = $data->Customers->Customer_Addresses->firstWhere('is_main', 1);
        $addressObj = $mainAddress ?? $data->Customers->Customer_Addresses->first();

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

        //Formatted Date
        $date = $data->date;
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $year = substr($formattedDate, -4);
        $formattedDate = str_replace($year, $year + 543, $formattedDate);
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $id_gen = 0;
        $round = 0;
        $transactionarr =  $data->InspectionVehicles[0]->vehicleServiceTransaction;
        foreach ($transactionarr as $transaction) {

            $serviceName = $transaction->service_name ?? null;
            $servicePrice = $transaction->service_price ?? null;
            $totalAmount += $servicePrice;
            $id_gen += 1;
            $round += 1;
            $serviceRows .= '
                <tr>
                    <td>' . $id_gen . '</td>
                    <td style="text-align: left">' . $serviceName . '</td>
                    <td style="text-align: right">' . number_format($servicePrice, 2) . '</td>
                </tr>';
        }
        for ($i = $round; $i < 7; $i++) {
            $serviceRows .= '
            <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
            </tr>';
        }

        // return $branche_image;
        $totalcal = $data->discount ?? 0;
        $total = $totalAmount - $totalcal ?? 0;


        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);

        $mpdf->SetTitle('ใบเสร็จ');
        $mpdf->AddPage();

        $html = '
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <title>ใบวางบิล/ใบแจ้งหนี้</title>
            <style>
                body {
                    font-family: "th-sarabun", Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #fff;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    padding: 20px;
                }
                .header-left {
                    text-align: left;
                }
                .header-left1 {
                    text-align: left;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 200px;
                    left: 470px;
                }
                .header-left h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .header-left p {
                    margin: 2px 0;
                    font-size: 18px;
                }
                .header-right {
                    text-align: right;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 77px;
                    left: 585px;
                }
                .header-right2 {
                    text-align: right;
                    font-size: 20px;
                    position: absolute;
                    width: 300px;
                    top: 192px;
                    left: 470px;
                }
                .content {
                    padding: 20px;
                    display: flex;
                    justify-content: space-between;
                }
                .content-left {
                    text-align: left;
                    font-size: 18px;
                }
                .content-right {
                    text-align: right;
                    font-size: 18px;
                }
                .table-container {
                    margin-top: 5px;
                    width: 100%;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 4px;
                    text-align: center;
                    font-size: 18px;ก
                }
                th {
                    background-color: #f2f2f2;
                }
                .footer {
                    margin-top: 5px;
                    text-align: left;
                    font-size: 18px;
                }
                .footer table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .footer td {
                    border: none;
                    text-align: left;
                    padding: 8px;
                    font-size: 18px;
                }
                .signature-section {
                    margin-top: 50px;
                    display: flex;
                    justify-content: space-between;
                    font-size: 18px;
                }
                .signature-section1 {
                    width: 100%;
                    text-align: center;
                }
                .signature-section2 {
                    width: 100%;
                    text-align: center;
                }
                .container {
                    display: flex;
                    align-items: flex-start;
                    text-align: left;
                    font-size: 20px;
                    position: absolute;
                    width: 50px;
                    top: 130px;
                    left: 80px;
                }
                .container img {
                    margin-right: 20px; /* Adjust spacing as needed */
                    width: 100px; /* Adjust image size as needed */
                }
                .text-content {
                    max-width: 300px; /* Adjust as needed */
                }
            </style>
        </head>
        <body>
            <table style="width: 100%;">
                    <tr>
                        <td style="width: 5%; border: none; text-align: left; padding-left: 5px;">
                            <img src="' . $branche_image1 . '" alt="FIE" align="left" style="width: 100px">
                        </td>
                        <td style="width: 60%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                            <h2>' . $branche_name . '</h2>
                            <p>' . $employee_branchAddress . '</p>
                            <p>' . $time_open_close . ' น. (' . $open_date . ')</p>
                            <p>' . $phone . '</p>
                        </td>
                        <td style="width: 40%; vertical-align: text-top; border: none; text-size: 20px; text-align: right;">
                            <p style="font-size: 20px"><strong>ใบวางบิล</strong></p>
                            <p>ต้นฉบับ</p>
                        </td>
                    </tr>
            </table>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 60%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
                        <p>ลูกค้า: ' . $customerName . '</p>
                        <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . '</p>
                        <p>โทร.: ' . $phoneNumber . '</p>
                        <p>ทะเบียน: ' . $vehiclePlate . ' ' . $vehicleProvinces . '</p>
                    </td>
                    <td style="width: 40%; border: none; text-align: left; vertical-align: text-top;">
                        <p>เลขที่: ' . $order_no . '</p>
                        <p>วันที่: ' . $formattedDate . '</p>
                        <p>ประเภท: ' . $vehicleType . ' </p>
                    </td>
                </tr>
            </table>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%;">ลำดับ</th>
                            <th style="width: 70%;">รายการ</th>
                            <th style="width: 20%;">จำนวนเงิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                        ' . $serviceRows . '
                        </tr>
                    </tbody>
                </table>
            <div class="footer">
                <table>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ยอดรวม: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($totalAmount, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ส่วนลด: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($totalcal, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid black;" colspan="2"><strong>ยอดสุทธิ: </strong></td>
                        <td style="border-bottom: 1px solid black; text-align: right;">' . number_format($total, 2) . '</td>
                    </tr>
                </table>
            </div>
            </div>
            <div style="
            position: fixed; bottom: 0; width: 100%; padding: 0 20px; margin-top: 20px; overflow: hidden;">
                <div style="float: left; width: 45%;  text-align: center;">
                    <p>จัดเตรียมโดย<br><br><br><br></p>
                    <p>................................................................</p>
                    <p>(................................................................)</p>
                    <p>................./................./.................</p>
                </div>
                <div style="float: right; width: 45%;  text-align: center;">
                    <p>ยอมรับใบวางบิล<br><br><br><br></p>
                    <p>................................................................</p>
                    <p>(................................................................)</p>
                    <p>................./................./.................</p>
                </div>
            </div>
        </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('receipt.pdf', 'S');

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

    public function receipt($id)        //ใบเสร็จ
    {

        // ดึงข้อมูลการตรวจสอบ พร้อมข้อมูลที่เกี่ยวข้อง
        $data = Inspections::with([
            'serviceTransactions.service',
            'Vehicles.Customers',
            'Vehicles.VehicleInspectionTypes',
            'InspectionVehicles.vehicle.Customers',
            'InspectionVehicles.vehicle.VehicleInspectionTypes',
            'InspectionVehicles.vehicleServiceTransaction.service',
            'Employees.Branches',
            'Customers.Customer_Addresses.Provinces',
            'Customers.Customer_Addresses.Districts',
            'Customers.Customer_Addresses.SubDistricts',
        ])->where('id', $id)->first();

        if (!$data) {
            return $this->returnBadRequest('Data Not Found', 404);
        }

        // ดึงข้อมูลลูกค้าและรถยนต์

        $customer = $data->Customers ?? null;
        $customerName = $customer->name ?? null;
        $customerLName = $customer->lname ?? null;
        $phoneNumber = $customer->phone_number1 ?? null;
        $branches_name = $data->employees->Branches->business_name;

        $vehicle = $data->InspectionVehicles[0] ?? null;
        $vehiclePlate = $vehicle->license_plate ? $vehicle->license_plate : null;
        $vehicleType = $vehicle->vehicle_inspection_types ? $vehicle->vehicle_inspection_types : null;
        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $totalAmount = 0;
        $serviceRows = '';
        $round = 0;

        foreach ($data->InspectionVehicles[0]->vehicleServiceTransaction as $transaction) {

            $bill_serviceName = $transaction->service_name ?? $transaction->service->name ?? null;
            $bill_servicePrice = $transaction->service_price ?? null;
            $round += 1;
            $totalAmount += $bill_servicePrice;

            $serviceRows .= '
                <tr>
                    <td>' . $bill_serviceName . '</td>
                    <td class="amount">' . number_format($bill_servicePrice, 2) . '</td>
                </tr>';
        }
        for ($i = $round; $i < 7; $i++) {
            $serviceRows .= '
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>';
        }
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            // 'format' => 'A4',
            // 'default_font_size' => 14,
            'format' => [80, 190], // Width x Height in mm
            'default_font_size' => 10,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->SetTitle('Bill Service');
        $mpdf->AddPage();

        $html = '
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: "th-sarabun", Arial, sans-serif;
                    font-size: 10pt;
                    margin: 0;
                }
                .receipt {
                    width: 100%;
                }
                .header {
                    text-align: center;
                    margin-bottom: 5px;
                }
                .header h1, .header h2, .header h3 {
                    margin: 2px 0;
                }
                .header h1 {
                    font-size: 12pt;
                    font-weight: bold;
                }
                .header h2 {
                    font-size: 10pt;
                    font-weight: bold;
                }
                .header h3 {
                    font-size: 9pt;
                }
                table {
                    width: 100%;
                    margin-top: 5px;
                    border-collapse: collapse;
                }
                th, td {
                    padding: 2px;
                    text-align: left;
                }
                .amount {
                    text-align: right;
                }
                .total {
                    font-size: 12pt;
                    font-weight: bold;
                    text-align: right;
                }
                .footer {
                    text-align: center;
                    margin-top: 5px;
                }
                .footer p {
                    margin: 2px 0;
                    font-size: 9pt;
                }
                hr {
                    border: none;
                    border-top: 1px dashed #000;
                    margin: 5px 0;
                }
            </style>
        </head>
        <body>
        <div class="receipt">
            <div class="header">
                <h1>' . $branches_name . '</h1>';

        if ($customerName) {
            $html .= '
            <h3>ลูกค้า: ' . $customerName . ' ' . $customerLName . '</h3>
            <h3>โทร: ' . $phoneNumber . '</h3>
            ';
        }

        $html .= '
                <h2>ทะเบียน: ' . $vehiclePlate . '</h2>
                <h3>ประเภท: ' . $vehicleType . '</h3>
                </div>

                    <hr>

                    <table>
                        ' . $serviceRows . '
                    </table>

                    <hr>

                    <table>
                        <tr>
                            <td><strong>ยอดรวม</strong></td>
                            <td class="amount"><strong>' . number_format($totalAmount, 2) . '</strong></td>
                        </tr>
                        <tr>
                            <td>ส่วนลด</td>
                            <td class="amount">' . number_format($data->discount, 2) . '</td>
                        </tr>
                        <tr>
                            <td><strong>ยอดสุทธิ</strong></td>
                            <td class="amount"><strong>' . number_format($totalAmount - $data->discount, 2) . '</strong></td>
                        </tr>
                    </table>

                    <hr>

                    <div class="footer">
                        <p>พนักงาน: ___________________________</p>

                        <p><br><br>ขอบคุณที่ใช้บริการ</p>
                    </div>
                </div>
            </body>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
        $pdfContent = $mpdf->Output('receipt.pdf', 'S');

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

    public function Voucher(Request $request, $id)      //ใบสำคัญรับเงิน
    {
        $installments_id = $request->installments_id;
        if (!$installments_id) {
            return $this->returnErrordata('ไม่พบข้อมูล', 404);
        }
        $data = Inspections::with([
            'Branches',
            'Vehicles.VehicleBrands',
            'Vehicles.VehicleInspectionTypes',
            'Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.SubDistricts',
            'InspectionVehicles.vehicleServiceTransaction' => function ($query) {
                $query->where('is_vat', 0);
            },
            'InspectionVehicles.vehicleServiceTransaction.service',
            'Installments',
        ])->where('id', $id)->first();
        // return $data;
        if (!$data) {
            return $this->returnErrordata('Data Not Found', 404);
        }

        $inspectionVehicles = InspectionVehicles::where('inspection_id', $data->id)
            ->get(); // ดึงข้อมูลทั้งหมดจาก InspectionVehicles ที่มี inspection_id ตรงกับ $data->id

        if ($inspectionVehicles->isEmpty()) {
            return $this->returnErrordata('No Vehicle Records Found', 404);
        }
        $vehicleIds = $inspectionVehicles->pluck('vehicle_id')->toArray();

        $vehicles1 = Vehicles::whereIn('id', $vehicleIds)->get();
        // ตรวจสอบผลลัพธ์
        if ($vehicles1->isEmpty()) {
            return response()->json(['vehicles' => null, 'vehicle_inspection_types' => null], 200);
        }
        // return $vehicles1;
        $vehicleInspectionTypes = VehicleInspectionTypes::whereIn('id', $vehicles1->pluck('vehicle_inspection_types_id'))
            ->get();

        // ตรวจสอบผลลัพธ์
        if ($vehicleInspectionTypes->isEmpty()) {
            return response()->json(['vehicles' => null, 'vehicle_inspection_types' => null], 200);
        }
        $codetypes = $vehicleInspectionTypes->pluck('code', 'type')->toArray();
        $codetypeString = '';
        foreach ($codetypes as $type => $code) {
            $codetypeString .= $type . $code;
        }

        $insuranceNames = [];
        $inspectionVehicleIds = [];

        $vehicle = $data->InspectionVehicles[0] ?? null;
        $vehiclePlate = $vehicle->license_plate ? $vehicle->license_plate : null;
        $vehiclePlate_provinces = $vehicle->provinces ? $vehicle->provinces : null;
        $vehicleType = $vehicle->vehicle_inspection_types ? $vehicle->vehicle_inspection_types : null;

        $order_no = $data->no ?? null;

        $customer = $data ? $data->InspectionVehicles[0] : null;
        $customerName = $customer ? $customer->name : null;
        $phoneNumber = $customer ? $customer->phone_number : null;

        $mainAddress = $data->Customers->Customer_Addresses->firstWhere('is_main', 1);
        $addressObj = $mainAddress ?? $data->Customers->Customer_Addresses->first();
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

        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->with([
                    'Provinces',
                    'Districts',
                    'SubDistricts',
                ])
                ->first();
            if (!$branche) {
                return $this->returnError('Branches Not Found');
            }
            $branche_address = $branche->address ?? null;
            $branche_province_name = $branche->Provinces->name_th_prefix ?? null;
            $branche_district_name = $branche->Districts->name_th_prefix ?? null;
            $branche_subdistrict_name = $branche->SubDistricts->name_th_prefix ?? null;
            $branche_postal_code = $branche->postal_code ?? null;
            $branche_open_date_time = $branche->open_close_time ?? null;
            $branche_open_date = $branche->open_date ?? null;
            $branche_phone = $branche->phone ?? null;
            $branche_image = $branche->logo ?? null;
            $branche_logo = $branche->business_name ?? null;
            $branche_logo1 = $data->Branches->logo ?? null;
            $branche_logo_under = $data->Branches->logo_under ?? null;
        } else {

            $branche_address = $data->Branches->address ?? null;
            $branche_province_name = $data->Branches->Provinces->name_th_prefix ?? null;
            $branche_district_name = $data->Branches->Districts->name_th_prefix ?? null;
            $branche_subdistrict_name = $data->Branches->SubDistricts->name_th_prefix ?? null;
            $branche_postal_code = $branche->postal_code ?? null;
            $branche_open_date_time = $data->Branches->open_close_time ?? null;
            $branche_open_date = $data->Branches->open_date ?? null;
            $branche_phone = $data->Branches->phone ?? null;
            $branche_image = $data->Branches->logo ?? null;
            $branche_logo = $data->Branches->business_name ?? null;
            $branche_logo1 = $data->Branches->logo ?? null;
            $branche_logo_under = $data->Branches->logo_under ?? null;
        }

        if ($branche_logo1 != null) {
            $branche_image = '<td style="width: 8%; border: none; text-align: left; vertical-align: middle;" colspan="2"><img src="' . $branche_logo1 . '" alt="FIE" align="left" style="width: 90px;"></td>';
            // $branche_image = 'Not Found';
        } else {
            $branche_image = 'Not Found';
        }

        // Formatted Date
        $date = $data->date;
		foreach($data->Installments as $Installments) {
			if($Installments['id'] == $installments_id){
				$date = $Installments['date'];

			}
		}

        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $yeardate = substr($formattedDate, -4);
        //$formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);
        $formattedDate = str_replace($yeardate, $yeardate + 543, $formattedDate);

        // Format appointment date
        $appointment = $data->appointment;
        $formatappointment = Carbon::parse($appointment)->locale('th')->translatedFormat('j M y');
        $year = substr($formatappointment, -2);
        $formatappointment = str_replace($year, $year + 43, $formatappointment);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $serviceRows = '';
        // $id_gen = 0;
        $round = 0;
        $pageNumber = 1;

        // // ตรวจสอบว่า $data มี property ServiceTransactions และเป็น object
        $transactionarr =  $data->InspectionVehicles[0]->vehicleServiceTransaction;
        $preparedData = [];
        $totalAmount = 0;
        $id_gen = 0;
        $existingServices = [];
        $transection = [];
        if (isset($transactionarr) && is_iterable($transactionarr)) {
            foreach ($transactionarr as $transactions) {
                $transection[] = $transactions->id;
            }

            foreach ($transactionarr as $transaction) {
                $serviceId = $transaction->service_id ?? null;
                $serviceName = $transaction->service_name ?? null;
                $servicePrice = $transaction->service_price ?? 0;
                $transactionId = $transaction->id ?? null;

                // รวมชื่อประกันกับชื่อบริการ (ถ้ามี)
                $insuranceRenewal = InsurancesRenewals::where('vechicle_services_transaction_id', $transactionId)
                    ->with('insuranceName') // ดึงข้อมูลความสัมพันธ์ insuranceName
                    ->first();

                $insuranceName = $insuranceRenewal->insuranceName->name ?? '';
                $nameWithResname = !empty($insuranceName) ? $serviceName . ' ( ' . $insuranceName  . ' )' : $serviceName;
                if($serviceId == 14){
                    $nameWithResname = $serviceName . ' ( ' . $data->remark  . ' )';
                }elseif($serviceId == 15){
                    $nameWithResname = $serviceName . ' ( ' . $data->remark_transport  . ' )';
                }

                // ตรวจสอบว่าบริการนี้เคยเพิ่มไปแล้วหรือยัง
                if (!in_array($serviceName, $existingServices)) {
                    $id_gen++; // เพิ่ม id_gen

                    $preparedData[] = [
                        'id' => $id_gen,
                        'name' => $nameWithResname,
                        'price' => $servicePrice,
                    ];

                    // ป้องกันข้อมูลซ้ำ
                    $existingServices[] = $serviceName;
                    $totalAmount += $servicePrice; // รวมยอด
                }
            }
        }

        $status_paid = 'สถานะไม่รู้จัก';
        $total_paid_price = null;
        $change_bef = null;
        $total_after_price = null;
        foreach ($data->installments as $installments) {
            if ($installments['id'] == $installments_id) {
                $cash_input = $installments['paid_price'];
                if ($installments['after_price'] > 0) {
                    $change = 0;
                } else {
                    $change = $installments['after_price'];
                }

                $statusMessages = [
                    'paid_cash' => 'เงินสด : ',
                    'paid_tran' => 'เงินโอน : ',
                    'credit' => 'บัตรเครดิต : ',
                ];
                if (array_key_exists($installments['paid_type'], $statusMessages)) {
                    $status_paid =  $statusMessages[$installments->paid_type]; //. number_format($data->total, 2)
                } else {
                    $status_paid = 'สถานะไม่รู้จัก';
                }

                $total_paid_price = $installments['paid_price'];
                $change_bef = $installments['before_price'];

                if ($installments['after_price'] < 0) {
                    $total_after_price = 0;
                } else {
                    $total_after_price = $installments['after_price'];
                }
            }
        }

        $change_amount = $total_paid_price - $change_bef;
        $mat = '';
        if (($change_amount) > 0) {
            $mat .= ' ทอน ' . number_format($change_amount, 2) . '</p>';
        }

        $totaldiscount = $data->discount;
        $total = $totalAmount - $totaldiscount;
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-l',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);

        $mpdf->SetTitle('ใบสำคัญรับเงิน');

        $itemsPerPage = 7;
        $totalItems = count($transactionarr);
        $pages = ceil($totalItems / $itemsPerPage);

        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                $mpdf->AddPage();
            }

            $html = '
        <body>
            <div style="width: 49%; float: left; border-right: 1px dashed black; padding-left: 15px;">

                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;width:300px;">
                                    <h2 style="margin: 5px 0; font-size: 18px; line-height: 1.5;width:290px;white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $branche_logo . '</h2>
                                    <p>' . $branche_address . '</p>
                                    ' . ($branche_open_date_time ? '<p>' . $branche_open_date_time . ' น.' . ($branche_open_date ? ' (' . $branche_open_date . ')' : '') . '</p>' : '') . '
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 21px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>สำเนา</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width: 65%;">
                                    <p>ลูกค้า: ' . $customerName . '</p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' ' . $vehiclePlate_provinces . '</p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top; width: 35%;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $codetypeString . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>';
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($preparedData)); $i++) {;
                $data = $preparedData[$i];
                $html .= '
                    <tr>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center;">' . $data['id'] . '</td>
                        <td colspan="4" style="text-align: left; border: 1px solid black; border-collapse: collapse;">' . $data['name'] . '</td>
                        <td style="text-align: right; border: 1px solid black; border-collapse: collapse;">' . number_format($data['price'], 2) . '</td>
                    </tr>';
            }
            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td colspan="4" style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                    </tr>';
            }
            $html .= '
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>1. ' . $status_paid . '' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>_____________</p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p><strong>ส่วนลด</strong></p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totaldiscount, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>(______________)</p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                       <p><strong>ยอดสุทธิ</strong></p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($total, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>ชำระแล้ว</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($total_paid_price, 2) .  $mat;
            $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <td style="widht:40%; text-align: center; vertical-align: center;">
                                    <img src="' . $branche_logo_under . '" alt="Branch Logo" style="width: auto; height: 70px;">
                                </td>
                            </tr>
                        </table>
            </div>

            <div style="width: 49%; float: right;">
                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width:300px;">
                                    <h2 style="margin: 5px 0; font-size: 18px; line-height: 1.5;width:290px;white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $branche_logo . '</h2>
                                    <p>' . $branche_address . '</p>
                                    ' . ($branche_open_date_time ? '<p>' . $branche_open_date_time . ' น.' . ($branche_open_date ? ' (' . $branche_open_date . ')' : '') . '</p>' : '') . '
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 21px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>ต้นฉบับ</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width: 65%;">
                                    <p>ลูกค้า: ' . $customerName . '</p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' ' . $vehiclePlate_provinces . '</p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top; width: 35%;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $codetypeString . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>';
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($preparedData)); $i++) {
                $data = $preparedData[$i];
                $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center;">' . $data['id'] . '</td>
                                        <td colspan="4" style="text-align: left; border: 1px solid black; border-collapse: collapse;">' . $data['name'] . '</td>
                                        <td style="text-align: right; border: 1px solid black; border-collapse: collapse;">' . number_format($data['price'], 2) . '</td>
                                    </tr>';
            }
            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td colspan="4" style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                    </tr>';
            }
            $html .= '
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>1. ' . $status_paid . ' ' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>_____________</p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p><strong>ส่วนลด</strong></p>
                                    </td>
                                    <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($totaldiscount, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>(______________)</p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                       <p><strong>ยอดสุทธิ</strong></p>
                                    </td>
                                    <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                                        <p>' . number_format($total, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; vertical-align: top;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                        <p>&nbsp;</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>ชำระแล้ว</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p>' . number_format($total_paid_price, 2) . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($total_paid_price, 2) .  $mat;
            $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <td style="widht:40%; text-align: center; vertical-align: center;">
                                    <img src="' . $branche_logo_under . '" alt="Branch Logo" style="width: auto; height: 70px;">
                                </td>
                            </tr>
                        </table>
            </div>
        </body>
        </html>';
            // return $html;
            $mpdf->WriteHTML($html);
        }
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
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

    public function billtax_invioce($id)
    {
        $data = Inspections::with([
            'Branches',
            'InspectionVehicles.vehicleServiceTransaction.service',
            'InspectionVehicles.vehicleServiceTransaction' => function ($query) {
                $query->where('is_vat', 1); // ดึงเฉพาะรายการที่ is_vat = 1
            },
            // 'Vehicles.Customers.Customer_Addresses.Provinces',
            // 'Vehicles.Customers.Customer_Addresses.Districts',
            // 'Vehicles.Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.Provinces',
            'Customers.Customer_Addresses.Districts',
            'Customers.Customer_Addresses.SubDistricts',
            'TaxInvoiceAllNos',
            // 'Employees.Branches.Provinces',
            // 'Employees.Branches.Districts',
            // 'Employees.Branches.SubDistricts',
        ])->where('id', $id)->first();

        $TaxInvoiceAllNo = TaxInvoiceAllNos::where('inspection_id', $id)->where('type', 'inspection')->first();
        if (!$TaxInvoiceAllNo) {
            $year = date('y') + 43; // เลขท้ายปีพ.ศ.
            $month = date('m'); // เดือนปัจจุบัน
            $count = TaxInvoiceAllNos::whereYear('date', date('Y'))
                ->where('branche_id', $data->branch_id)
                ->whereMonth('date', date('m'))
                ->whereRaw("no REGEXP '^HS" . $year . $month . "/[0-9]{3}$'")
                ->count() + 1; // จำนวนใบเสนอราคาของเดือนนั้น

            $no = sprintf("HS%s%s/%03d", $year, $month, $count);

            $TaxInvoiceAllNo =  new TaxInvoiceAllNos();
            $TaxInvoiceAllNo->inspection_id = $id;
            $TaxInvoiceAllNo->branche_id = $data->branch_id;
            $TaxInvoiceAllNo->no = $no;
            $TaxInvoiceAllNo->date = carbon::now();
            $TaxInvoiceAllNo->type = 'inspection';
            $TaxInvoiceAllNo->save();
        }

        if (!$data) {
            return $this->returnErrordata('Data Not Found', 404);
        }
        $customer = $data ? $data->InspectionVehicles[0] : null;

        if (!$customer) {
            return $this->returnErrordata('ต้องการข้อมูลลูกค้า', 404);
        }

        $vehicle = $data->InspectionVehicles[0] ?? null;
        $vehiclePlate = $vehicle->license_plate ? $vehicle->license_plate : null;
        $vehicle_provinceName = $vehicle && $vehicle->provinces ? $vehicle->provinces : null;

        $customerNo = $customer ? $customer->no : null;
        $customer_name = $customer ? $customer->name : null;
        $customer_phoneNumber = $customer ? $customer->phone_number : null;
        $customer_taxID = $customer->tax_id ?? null;
        $inspection_no = $data->TaxInvoiceAllNos[count($data->TaxInvoiceAllNos) - 1]->no ?? null;
        // $customer_headquarter = $customer->customers->is_headquarter ?? 0;
        // return $customer_headquarter;
        // return $customer;

        // ดึงข้อมูลที่อยู่และตรวจสอบค่าที่อาจไม่มี
        $customerAddress = $data->Customers->Customer_Addresses[0] ?? null;
        $customer_address = $customerAddress ? $customerAddress->address : null;
        $customer_provinceName = $customerAddress && $customerAddress->provinces ? $customerAddress->provinces->name_th_prefix : null;
        $customer_districtName = $customerAddress && $customerAddress->districts ? $customerAddress->districts->name_th_prefix : null;
        $customer_subdistrictName = $customerAddress && $customerAddress->SubDistricts ? $customerAddress->SubDistricts->name_th_prefix : null;
        $customer_postalcode = $customerAddress ? $customerAddress->zip_code : null;

        // Pull data branche
        $branche = $data->Branches ?? null;
        $branche_name = $branche->business_name ?? null;
        $branche_address = $branche->address ?? null;
        $branche_provinceName = $branche && $branche->provinces ? $branche->provinces->name_th_prefix : null;
        $branche_districtName = $branche && $branche->districts ? $branche->districts->name_th_prefix : null;
        $branche_subdistrictName = $branche && $branche->SubDistricts ? $branche->SubDistricts->name_th_prefix : null;
        $branche_postalcode = $branche ? $branche->postal_code : null;
        $branche_phone = $branche ? $branche->phone : null;
        $branche_tax_id = $branche->tax_id ?? null;
        //Formatted Date
        $date = $data->date;
        $formattedDate = Carbon::parse($date)->format('d/m/') . (Carbon::parse($date)->year + 543 - 2500);

        $offices = '';
        if ($branche && $branche->is_headquarter == 1) {
            $offices = 'สำนักงานใหญ่';
        }

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $serviceRows = '';
        $totalAmount = 0;
        $id_gen = 1;

        // $checkbox_null = '<img src="../../../resources/image/checkbox.png" alt="checkbox" style="width: 12px;">';
        // $checkbox_value = '<img src="../../../resources/image/checkbox-true.png" alt="checkbox" style="width: 12px;">';
        switch ($customer && $customer->is_headquarter) {
            case 1:
                $headqurter = "สำนักงานใหญ่";
                $checkbox_value1 = "&#9745;";
                break;
            case 0;
                $headqurter = "";
                $checkbox_value1 = "&#9744;";
                break;
            default:
                $headqurter = "";
                $chack = "&#9744;";
                break;
        }

        $checkbox_null = "&#9744;";
        $checkbox_value = "&#9745;";

        // $checkbox_null = '<img src="../image/checkbox.png" alt="checkbox" style="width: 15px;">';
        // $checkbox_value = '<img src="../image/checkbox-true.png" alt="checkbox" style="width: 15px;">';

        if ($customer && $customer->is_headquarter == 1) {
            $call_checkbox = '<td style="padding-left: 10px;">เลขประจำตัวผู้เสียภาษี ' . $customer_taxID . '  <span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;"> ' . $checkbox_value1 . '</span><span>&nbsp;สำนักงานใหญ่ <br> <span style="font-size: 10px; font-weight: normal;">Tax ID</span></span></td>';
        } else {
            $call_checkbox = '<td style="padding-left: 10px;">เลขประจำตัวผู้เสียภาษี ' . $customer_taxID . '  <span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;"> ' . $checkbox_value1 . '</span><span>&nbsp;สำนักงานใหญ่ <br> <span style="font-size: 10px; font-weight: normal;">Tax ID</span></span></td>';
        }

        $excludedServiceIds = [8, 9, 10, 11, 12]; // รายการ id ของบริการที่ต้องไม่แสดงสำหรับ vendor
        //elseif (($customerType === 'vendor' || $customerType === 'agent') && !in_array($transaction->service_id, $excludedServiceIds)) {

        foreach ($vehicle->vehicleServiceTransaction as $transaction) {
            $serviceName = $transaction->service->name ?? '-';
            $servicePrice = $transaction->service_price ?? 0;
            $totalAmount += $servicePrice;
            $serviceRows .= "
                <tr>
                    <td style='border: 1px solid black; border-top: none; border-bottom: none; text-align: center; background-color: white;'>{$id_gen}</td>
                    <td style='border: 1px solid black; border-top: none; border-bottom: none; text-align: left; background-color: white;'>{$serviceName}</td>
                    <td style='border: 1px solid black; border-top: none; border-bottom: none; text-align: center; background-color: white;'>1</td>
                    <td style='border: 1px solid black; border-top: none; border-bottom: none; border-right: none; text-align: right; background-color: white;'>" . number_format($servicePrice, 2) . "</td>
                </tr>";
            $id_gen++;
        }

        for ($i = $id_gen; $i <= 15; $i++) {
            $serviceRows .= '
            <tr>
                <td style="border: 1px solid black; border-top: none; border-bottom: none; border-collapse: collapse; background-color: white;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; border-bottom: none; text-align: left; border-collapse: collapse; background-color: white;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; border-bottom: none; border-collapse: collapse; background-color: white;">&nbsp;</td>
                <td style="border: 1px solid black; border-top: none; border-bottom: none; border-right: none; text-align: right; border-collapse: collapse; background-color: white;">&nbsp;</td>
            </tr>';
        }
        $totalServicePrice = $totalAmount;
        $dis = $data->discount ?? 0;

        $amountAfterDiscount = $totalServicePrice - $dis;

        $vatAmount = $totalServicePrice * 0.07;

        $totalAmountWithVat = $amountAfterDiscount + $vatAmount;

        $totalAmountWithVat = abs($totalAmountWithVat); // Ensure the value is positive
        $NetAmountText = $this->convertToThaiText(number_format($totalAmountWithVat, 2));

        foreach ($data->serviceTransactions as $service_total) {
            if (isset($service_total->service_price) && $service_total->status === 1) {
                $totalServicePrice += $service_total->service_price;
            }
        }

        $total = floor($totalAmount);
        $totalcal = $totalAmount - $total;

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 15,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,
            'orientation' => 'P',
        ]);

        $mpdf->SetTitle('ใบกำกับภาษี');
        $mpdf->AddPage();

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
                <body>
                <div id="page_color" style="font-size: 25px; font-weight: bold; background-color: #ff8000; position: absolute; left: 64%; top: 13.2%; width: 207px; border-radius: 15px;">
                    <strong>&nbsp; ใบเสร็จรับเงิน/ใบกำกับภาษี </strong>
                </div>
                <div style="position: absolute; left: 74%; top: 16.3%; font-size: 18px;">(ต้นฉบับ)</div>
                <div style="font-size: 16px; position: absolute; left: 17.3%; top: 14.2%;">
                    เลขประจำตัวผู้เสียภาษี/Tax ID &nbsp; ' . $branche_tax_id . ' &nbsp; ' . $offices . '
                </div>
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <tr>
                            <td style="width: 12%; vertical-align: text-top;" rowspan="5">' . (!empty($branche['logo']) ? '<img src="' . $branche['logo'] . '" alt="FIE" align="left" style="width: 70px; height: 70px;">' : '&nbsp;') . '</td>
                            <td colspan="4" style="font-size: 28px; vertical-align: text-top; width: 58%;"><strong>' . $branche_name . '</strong></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_address . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_phone . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right; font-size: 16px;">&nbsp;</td>
                        </tr>
                    </table>
                    <div style="width: 100%;">
                        <div class="border" style="float: left; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">ลูกค้า ' . (!empty($customerNo) ? $customerNo : '&nbsp;') . ' <br> <span style="font-size: 10px; font-weight: normal;">Customer</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">' . (!empty($customer_name) ? $customer_name : '&nbsp;') . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">' . (!empty($customer_address) ? $customer_address : '&nbsp;') . ' <br> <span style="font-size: 10px; font-weight: normal;">&nbsp;</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">' . (!empty($customer_subdistrictName) ? $customer_subdistrictName : '&nbsp;') . ' '
            . (!empty($customer_districtName) ? $customer_districtName : '&nbsp;') . ' '
            . (!empty($customer_provinceName) ? $customer_provinceName : '&nbsp;') . ' '
            . (!empty($customer_postalcode) ? $customer_postalcode : '&nbsp;') . '
                                    <br> <span style="font-size: 4.7px; font-weight: normal;">&nbsp;</span>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">โทร ' . (!empty($customer_phoneNumber) ? $customer_phoneNumber : '&nbsp;') . ' </td>
                                </tr>
                                <tr id="header1">
                                    ' . (!empty($call_checkbox) ? $call_checkbox : '&nbsp;') . '
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">อ้างอิง <br> <span style="font-size: 10px; font-weight: normal;">Reference</span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ ' . $inspection_no . ' <br> <span style="font-size: 10px; font-weight: normal;">No.</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">วันที่ ' . $formattedDate . ' <br> <span style="font-size: 10px; font-weight: normal;">Date</span></td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ใบสั่งขาย <br> <span style="font-size: 10px; font-weight: normal;">Sales Order No.</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">พนักงานขาย <br> <span style="font-size: 10px; font-weight: normal;">Salesman</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เขตการขาย <br> <span style="font-size: 10px; font-weight: normal;">Territory</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div style="width: 100%; border: 1px solid black; border-radius: 10px; margin-top: 5px; background-color: #ff8000;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 0px; margin-right: -1px; margin-left: -1px;">
                                <tr id="header" style="width: 100%;">
                                    <th style="width: 5%; border-bottom: 1px solid black; border-right: 1px solid black;">ลำดับ <br> <span style="font-size: 10px; font-weight: normal;">No.</span></th>
                                    <th style="width: 60%; border-bottom: 1px solid black; border-right: 1px solid black;">รหัสสินค้า/รายละเอียด <br> <span style="font-size: 10px; font-weight: normal;">Code/Descriptions</span></th>
                                    <th style="width: 14%; border-bottom: 1px solid black; border-right: 1px solid black;">จำนวน <br> <span style="font-size: 10px; font-weight: normal;">Quantity</span></th>
                                    <th style="width: 21%; border-bottom: 1px solid black;">จำนวนเงิน <br> <span style="font-size: 10px; font-weight: normal;">Amount</span></th>
                                </tr>
                                <tbody>
                                    <tr style="background-color: white;">
                                        ' . $serviceRows . '
                                    </tr>
                                </tbody>
                                <div style="width: 100%;">
                                    <tr>
                                        <td style="background-color: white; border-top: 1px solid black;">&nbsp;</td>
                                        <td style="text-align: left; width: 53%; border-top: 1px solid black; padding: 10px; background-color: white;">
                                            <p style="font-size: 16px;"><strong>ทะเบียนรถ ' . $vehiclePlate . ' ' . $vehicle_provinceName . '</strong></p>
                                        </td>
                                        <td style="text-align: left; vertical-align: top; border: 1px solid black; border-bottom: none; width: 32%; background-color: white;" rowspan="2">
                                            <p>รวมเป็นเงิน</p>
                                            <p>หักส่วนลด</p>
                                            <p>ยอดหลังหักส่วนลด</p>
                                            <p>จำนวนภาษีมูลค่าเพิ่ม 7.00%</p>
                                        </td>
                                        <td style="text-align: right; vertical-align: top; width: 15%; background-color: white; border-top: 1px solid black" rowspan="2">
                                            <p>' . number_format($totalServicePrice, 2) . '</p>
                                            <p>' . number_format($dis, 2) . '</p>
                                            <p>' . number_format($amountAfterDiscount, 2) . '</p>
                                            <p>' . number_format($vatAmount, 2) . '</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: center; background-color: white;" colspan="2">
                                            &nbsp;
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: center; width: 53%; border: 1px solid black; font-size: 15px; border-bottom: none; border-left: none; border-right: none;" colspan="2">
                                            <p><strong> (' . $NetAmountText . ') </strong></p>
                                        </td>
                                        <td style="width: 32%; border: 1px solid black; border-top: none; border-bottom: none; background-color: white;">
                                            <p>จำนวนเงินรวมทั้งสิ้น</p>
                                        </td>
                                        <td style="text-align: right; border-top: 1px solid black; vertical-align: top; width: 15%;">
                                            <p><strong>' . number_format($totalAmountWithVat, 2) . '</strong></p>
                                        </td>
                                    </tr>
                                </div>
                            </table>
                        </div>
                    <div class="border1" style="width: 100%; margin-top: 5px;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <tr>
                                <td style="border: none; width: 30%; font-size: 16px; text-align: center; vertical-align: text-top;">
                                    <p><strong>ได้รับสินค้าตามรายการถูกต้องแล้ว</strong></p>
                                    <p>________________ <span>____/____/____</span></p>
                                    <p class="main" style="font-size: 10px;">ผู้รับสินค้า/Goods Received by <span>วันที่/Date</span></p>
                                    <p>________________ <span>____/____/____</span></p>
                                    <p class="main" style="font-size: 10px;">ผู้ส่งสินค้า/Delivery by <span>วันที่/Date</span></p>
                                </td>
                                <td style="padding-left: 5px; text-align: center; border: 1px solid black; border-top: none; border-bottom: none; width: 35%; font-size: 14px; vertical-align: text-top;">
                                    <p><strong>ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></strong><span>&nbsp;เงินสด&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></span><span>&nbsp;เช็ค</span></p>
                                    <p><strong>เช็คธนาคาร___________</strong><span>สาขา___________</span></p>
                                    <p><strong>เลขที่_____________</strong><span>วันที่___/____/______</span></p>
                                    <p><strong>จำนวนเงิน__________________________</p>
                                    <p><strong>ภาษีหัก ณ ที่จ่าย______________________</p>
                                    <p>________________ <span>____/____/____</span></p>
                                    <p style="font-size: 10px;">ผู้รับเงิน/Collector <span>วันที่/Date</span></p>
                                </td>
                                <td style="vertical-align: text-top; border: 1px solid black; border-top: none; border-bottom: none; border-right: none; width: 35%; font-size: 16px; text-align: center;">
                                    <p><strong>' . $branche_name . '</strong></p>
                                    <p>__________________________</p>
                                    <p style="font-size: 10px;">ผู้รับมอบอำนาจ/Authorized Signature</p>
                                    <p>__________________________</p>
                                    <p style="font-size: 10px;">ผู้ตรวจเช็คสินค้า/Approved by</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
        </html>';

        $mpdf->WriteHTML($html);
        $mpdf->AddPage();

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
                <body>
                <div id="page_color" style="font-size: 25px; font-weight: bold; background-color: #C0DCC0; position: absolute; left: 64%; top: 13.2%; width: 207px; border-radius: 15px;">
                    <strong>&nbsp; ใบเสร็จรับเงิน/ใบกำกับภาษี </strong>
                </div>
                <div style="position: absolute; left: 74%; top: 16.3%; font-size: 18px;">(สำเนา)</div>
                <div style="font-size: 16px; position: absolute; left: 17.3%; top: 14.2%;">
                    เลขประจำตัวผู้เสียภาษี/Tax ID &nbsp; ' . $branche_tax_id . ' &nbsp; ' . $offices . '
                </div>
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <tr>
                            <td style="width: 12%; vertical-align: text-top;" rowspan="5">' . (!empty($branche['logo']) ? '<img src="' . $branche['logo'] . '" alt="FIE" align="left" style="width: 70px; height: 70px;">' : '&nbsp;') . '</td>
                            <td colspan="4" style="font-size: 28px; vertical-align: text-top; width: 58%;"><strong>' . $branche_name . '</strong></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_address . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">' . $branche_phone . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="line-height: 1.2; font-size: 16px;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right; font-size: 16px;">&nbsp;</td>
                        </tr>
                    </table>
                    <div style="width: 100%;">
                        <div class="border" style="float: left; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">ลูกค้า ' . (!empty($customerNo) ? $customerNo : '&nbsp;') . ' <br> <span style="font-size: 10px; font-weight: normal;">Customer</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">' . (!empty($customer_name) ? $customer_name : '&nbsp;') . '</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">' . (!empty($customer_address) ? $customer_address : '&nbsp;') . ' <br> <span style="font-size: 10px; font-weight: normal;">&nbsp;</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">' . (!empty($customer_subdistrictName) ? $customer_subdistrictName : '&nbsp;') . ' '
            . (!empty($customer_districtName) ? $customer_districtName : '&nbsp;') . ' '
            . (!empty($customer_provinceName) ? $customer_provinceName : '&nbsp;') . ' '
            . (!empty($customer_postalcode) ? $customer_postalcode : '&nbsp;') . '
                                    <br> <span style="font-size: 4.7px; font-weight: normal;">&nbsp;</span>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">โทร ' . (!empty($customer_phoneNumber) ? $customer_phoneNumber : '&nbsp;') . ' </td>
                                </tr>
                                <tr id="header1">
                                    ' . (!empty($call_checkbox) ? $call_checkbox : '&nbsp;') . '
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">อ้างอิง <br> <span style="font-size: 10px; font-weight: normal;">Reference</span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="border" style="float: right; width: 49.5%;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ ' . $inspection_no . ' <br> <span style="font-size: 10px; font-weight: normal;">No.</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">วันที่ ' . $formattedDate . ' <br> <span style="font-size: 10px; font-weight: normal;">Date</span></td>
                                </tr>
                                <tr  id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">&nbsp;</td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เลขที่ใบสั่งขาย <br> <span style="font-size: 10px; font-weight: normal;">Sales Order No.</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">พนักงานขาย <br> <span style="font-size: 10px; font-weight: normal;">Salesman</span></td>
                                </tr>
                                <tr id="header1">
                                    <td style="padding-left: 10px;">เขตการขาย <br> <span style="font-size: 10px; font-weight: normal;">Territory</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div style="width: 100%; border: 1px solid black; border-radius: 10px; margin-top: 5px; background-color: #C0DCC0;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 0px; margin-right: -1px; margin-left: -1px;">
                                <tr id="header" style="width: 100%;">
                                    <th style="width: 5%; border-bottom: 1px solid black; border-right: 1px solid black;">ลำดับ <br> <span style="font-size: 10px; font-weight: normal;">No.</span></th>
                                    <th style="width: 60%; border-bottom: 1px solid black; border-right: 1px solid black;">รหัสสินค้า/รายละเอียด <br> <span style="font-size: 10px; font-weight: normal;">Code/Descriptions</span></th>
                                    <th style="width: 14%; border-bottom: 1px solid black; border-right: 1px solid black;">จำนวน <br> <span style="font-size: 10px; font-weight: normal;">Quantity</span></th>
                                    <th style="width: 21%; border-bottom: 1px solid black;">จำนวนเงิน <br> <span style="font-size: 10px; font-weight: normal;">Amount</span></th>
                                </tr>
                                <tbody>
                                    <tr style="background-color: white;">
                                        ' . $serviceRows . '
                                    </tr>
                                </tbody>
                                <div style="width: 100%;">
                                    <tr>
                                        <td style="background-color: white; border-top: 1px solid black;">&nbsp;</td>
                                        <td style="text-align: left; width: 53%; border-top: 1px solid black; padding: 10px; background-color: white;">
                                            <p style="font-size: 16px;"><strong>ทะเบียนรถ ' . $vehiclePlate . ' ' . $vehicle_provinceName . '</strong></p>
                                        </td>
                                        <td style="text-align: left; vertical-align: top; border: 1px solid black; border-bottom: none; width: 32%; background-color: white;" rowspan="2">
                                            <p>รวมเป็นเงิน</p>
                                            <p>หักส่วนลด</p>
                                            <p>ยอดหลังหักส่วนลด</p>
                                            <p>จำนวนภาษีมูลค่าเพิ่ม 7.00%</p>
                                        </td>
                                        <td style="text-align: right; vertical-align: top; width: 15%; background-color: white; border-top: 1px solid black" rowspan="2">
                                            <p>' . number_format($totalServicePrice, 2) . '</p>
                                            <p>' . number_format($dis, 2) . '</p>
                                            <p>' . number_format($amountAfterDiscount, 2) . '</p>
                                            <p>' . number_format($vatAmount, 2) . '</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: center; background-color: white;" colspan="2">
                                            &nbsp;
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: center; width: 53%; border: 1px solid black; font-size: 15px; border-bottom: none; border-left: none; border-right: none;" colspan="2">
                                            <p><strong> (' . $NetAmountText . ') </strong></p>
                                        </td>
                                        <td style="width: 32%; border: 1px solid black; border-top: none; border-bottom: none; background-color: white;">
                                            <p>จำนวนเงินรวมทั้งสิ้น</p>
                                        </td>
                                        <td style="text-align: right; border-top: 1px solid black; vertical-align: top; width: 15%;">
                                            <p><strong>' . number_format($totalAmountWithVat, 2) . '</strong></p>
                                        </td>
                                    </tr>
                                </div>
                            </table>
                        </div>
                    <div class="border1" style="width: 100%; margin-top: 5px;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <tr>
                                <td style="border: none; width: 30%; font-size: 16px; text-align: center; vertical-align: text-top;">
                                    <p><strong>ได้รับสินค้าตามรายการถูกต้องแล้ว</strong></p>
                                    <p>________________ <span>____/____/____</span></p>
                                    <p class="main" style="font-size: 10px;">ผู้รับสินค้า/Goods Received by <span>วันที่/Date</span></p>
                                    <p>________________ <span>____/____/____</span></p>
                                    <p class="main" style="font-size: 10px;">ผู้ส่งสินค้า/Delivery by <span>วันที่/Date</span></p>
                                </td>
                                <td style="padding-left: 5px; text-align: center; border: 1px solid black; border-top: none; border-bottom: none; width: 35%; font-size: 14px; vertical-align: text-top;">
                                    <p><strong>ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></strong><span>&nbsp;เงินสด&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></span><span>&nbsp;เช็ค</span></p>
                                    <p><strong>เช็คธนาคาร___________</strong><span>สาขา___________</span></p>
                                    <p><strong>เลขที่_____________</strong><span>วันที่___/____/______</span></p>
                                    <p><strong>จำนวนเงิน__________________________</p>
                                    <p><strong>ภาษีหัก ณ ที่จ่าย______________________</p>
                                    <p>________________ <span>____/____/____</span></p>
                                    <p style="font-size: 10px;">ผู้รับเงิน/Collector <span>วันที่/Date</span></p>
                                </td>
                                <td style="vertical-align: text-top; border: 1px solid black; border-top: none; border-bottom: none; border-right: none; width: 35%; font-size: 16px; text-align: center;">
                                    <p><strong>' . $branche_name . '</strong></p>
                                    <p>__________________________</p>
                                    <p style="font-size: 10px;">ผู้รับมอบอำนาจ/Authorized Signature</p>
                                    <p>__________________________</p>
                                    <p style="font-size: 10px;">ผู้ตรวจเช็คสินค้า/Approved by</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
        </html>';

        $mpdf->WriteHTML($html);
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
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

    public function certificatePDF(Request $request, $id)
    {
        $data = workorder::with(
            'Vehicles',
            'WorkOrderServices',
            'LineItems',
            'Customer',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        )
            ->where('id', $id)
            ->first();


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
            $branche_logo = $branche->business_name ?? null;
        } else {
            $branche_Name = $data->Branches->business_name ?? null;
            $branche_address = $data->Branches->address ?? null;
            $branche_open_date_time = $data->Branches->open_close_time ?? null;
            $branche_open_date = $data->Branches->open_date ?? null;
            $branche_phone = $data->Branches->phone ?? null;
            $branche_image = $data->Branches->logo ?? null;
            $branche_logo = $data->Branches->business_name ?? null;
        }
        // dd($data);
        // return $data;
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
            'default_font_size' => 14,
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 16,
            'margin_bottom' => 5,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
     

        $mpdf->SetTitle('ใบส่งมอบรถ');

        $itemsPerPage = 13;
        $totalItems = count($data->WorkOrderServices ?? []);
        $pages = ceil($totalItems / $itemsPerPage);
        // return $data;

        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                $mpdf->AddPage();
            }

            if($data){
                $cusname = $data->customer->name." ".$data->customer->lname;
            }else{
                $cusname = "-";
            }
            // dd($data->customer_name);
            //<img src="'. $data->Branches->logo_url .'" style="width:115px;height:115px;" alt="Icon">
            $html = '
            <div style="position: absolute;top:40px;left:30px;">
                <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
            </div>
            <div style="position: absolute;top:30px;left:150px;width:450px;line-height: 1;">
                <h2>' . $branche_Name . '</h2>
                <p style="font-size: 20px;">
                    ' . $branche_address . '</p>
                            <p style="font-size: 20px;">' . $branche_phone . '</p>
            </div>
            <div style="position: absolute;top:25px;right:40px;text-align:center;line-height: 2;">
                <p style="font-size: 22px;color: red  ;text-align:center; font-weight: bold;padding:2px 80px -8px 80px;border-radius:20px;"><strong>ต้นฉบับ</strong></p>
                <p style="font-size: 18px;margin-top:5px;"><strong>เลขที่ ' . ($data->no ?? "-") . '</strong></p>
            </div>
            <div style="position: absolute;top:160px;left:40px;padding:4px 236px 1px 236px;font-weight: bold;text-align:center;font-size:24px;background-color: #e8edde ;border: 2px solid #000;">
            ใบส่งมอบรถ/ใบรับเงิน/ใบรับประกัน
            </div>

            <div style="position: absolute;left:40px;top:210px;width:435px;height:100px;padding-left:5px;border: 2px solid #000;">
                <div>ชื่อลูกค้า&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($cusname ?? "-") . '</div>
                <div>ที่อยู่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($data->customer_address ?? "-") . '</div>
                <div>เบอร์โทร&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($data->customer_phone ?? "-") . '</div>
            </div>
            <div style="position: absolute;right:40px;width:255px;height:100px;top:210px;padding-left:5px;border: 2px solid #000;">
                <p>วันที่เอกสาร&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->convertToThaiDate($data->date ?? "-") . '</p>
            </div>
            <div style="position: absolute;left:40px;top:325px;width:709px;height:70px;border: 2px solid #000;">
                <table style="width:100%;margin-top:5px;">
                    <tr>
                        <td style="text-align: center;">ทะเบียน</td>
                        <td style="text-align: center;">ยี่ห้อรถ</td>
                        <td style="text-align: center;">รุ่นรถ</td>
                        <td style="text-align: center;">สีรถ</td>
                        <td style="text-align: center;">เลขตัวรถ</td>
                        <td style="text-align: center;">เลขเครื่อง</td>
                        <td style="text-align: center;">เลขกิโลเมตร</td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">' . ($data->license_plate ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->brand_name ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->model_name ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->color ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->chassis_number ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->engine_number ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->mileage ?? null) . '</td>
                    </tr>
                </table>
            </div>

            <div style="position: absolute;left:40px;top:408px;width:709px;border: 2px solid #000;line-height:2;">
                <table style="width:100%;border-collapse: collapse;">
                    <thead>
                        <tr>
                            <td style="width:8%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #e8edde ;">ลำดับ</td>
                            <td style="width:36%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #e8edde ;">รายละเอียด</td>
                            <td style="width:9%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #e8edde ;">จำนวน</td>
                            <td style="width:12%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #e8edde ;">หน่วยละ</td>
                            <td style="width:12%;text-align: center;border-bottom: 1px solid #000;background-color: #e8edde ;">จำนวนเงิน</td>
                        </tr>
                    </thead>
                    <tbody>';

            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($data->WorkOrderServices ?? null)); $i++) {

                $item = ($data->WorkOrderServices ?? null)[$i];

                $html .= '
                        <tr>
                            <td style="text-align: center;border-right: 1px solid #000;">' . ($i + 1) . '</td>
                            <td style="border-right: 1px solid #000;">' . $item['service_name'] . '</td>
                            <td style="text-align: center;border-right: 1px solid #000;">' . $item['qty'] . '</td>
                            <td style="text-align: right;border-right: 1px solid #000;">' . number_format($item['unit_price'], 2) . '</td>
                            <td style="text-align: right;">' . number_format($item['service_price'], 2) . '</td>
                        </tr>';
            }
            $TotalAmount = ($data->total ?? null);
            $TotalAmountText = $this->convertToThaiText(number_format($TotalAmount, 2));
            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                        <tr>
                            <td style="text-align: center;border-right: 1px solid #000;">&nbsp;</td>
                            <td style="border-right: 1px solid #000;">&nbsp;</td>
                            <td style="text-align: center;border-right: 1px solid #000;">&nbsp;</td>
                            <td style="text-align: center;border-right: 1px solid #000;">&nbsp;</td>
                            <td style="text-align: right;">&nbsp;</td>
                        </tr>';
            }
            $html .= '

                        <tr>
                            <td colspan="3" style="border-top: 1px solid #000;border-right: 1px solid #000;text-align:center;height:30px;">
                                <p>' . $TotalAmountText . '</p>
                            </td>
                            <td rowspan="2" style="border-top: 1px solid #000;text-align:right;border-right: 1px solid #000;height:60px;">ยอดรวม</td>
                            <td rowspan="2" style="border-top: 1px solid #000;text-align:right;height:60px;">' . number_format($TotalAmount, 2) . '</td>
                        </tr>

                        <tr>
                            <td colspan="3" style="border-top: 1px solid #000;border-right: 1px solid #000;text-align:left;padding-left:10px;height:60px;line-height:1.5;">
                                <p>หมายเหตุ&nbsp;&nbsp;' . ($data->remark ?? null) . '</p>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="position: absolute;left:40px;top:910px;width:709px;height:150px;border: 2px solid #000;text-align:center;">
                <table style="border-collapse: collapse;width:100%;">
                    <tr>
                        <td style="width:25%;text-align:center;border-right: 1px solid #000;">&nbsp;</td>
                        <td style="width:20%;text-align:center;border-right: 1px solid #000;">&nbsp;</td>
                        <td style="width:30%;text-align:center;border-right: 1px solid #000;font-size:14px;">ได้รับสินค้าตามรายการข้างต้นไว้เรียบร้อยแล้ว</td>
                        <td style="width:25%;text-align:center;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;height:85px;">&nbsp;</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;height:85px;">&nbsp;</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;height:85px;">&nbsp;</td>
                        <td style="width:auto;text-align:center;height:85px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;">วันที่.....................................</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;">วันที่.....................................</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;">วันที่.....................................</td>
                        <td style="width:auto;text-align:center;">วันที่.....................................</td>
                    </tr>
                    <tr>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;border-top: 1px solid #000;">พนักงาน</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;border-top: 1px solid #000;">ช่างซ่อม</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;border-top: 1px solid #000;">เจ้าของรถ/ผู้รับรถ</td>
                        <td style="width:auto;text-align:center;border-top: 1px solid #000;">ผู้รับเงิน</td>
                    </tr>
                </table>
            </div>
            ';

            $mpdf->WriteHTML($html);
            $mpdf->AddPage();
            $html_copy = '
            <div style="position: absolute;top:40px;left:30px;">
                <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
            </div>
            <div style="position: absolute;top:30px;left:150px;width:450px;line-height: 1;">
                <h2>' . $branche_Name . '</h2>
                <p style="font-size: 20px;">
                    ' . $branche_address . '</p>
                            <p style="font-size: 20px;">' . $branche_phone . '</p>
            </div>
            <div style="position: absolute;top:25px;right:40px;text-align:center;line-height: 2;">
                <p style="font-size: 22px;color: red  ;text-align:center; font-weight: bold;padding:2px 80px -8px 80px;border-radius:20px;"><strong>สำเนา</strong></p>
                <p style="font-size: 18px;margin-top:5px;"><strong>เลขที่ ' . ($data->no ?? null) . '</strong></p>
            </div>
            <div style="position: absolute;top:160px;left:40px;padding:4px 236px 1px 236px;font-weight: bold;text-align:center;font-size:24px;background-color: #C0DCC0 ;border: 2px solid #000;">
            ใบส่งมอบรถ/ใบรับเงิน/ใบรับประกัน
            </div>

            <div style="position: absolute;left:40px;top:210px;width:435px;height:100px;padding-left:5px;border: 2px solid #000;">
                <div>ชื่อลูกค้า&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($cusname ?? null) . '</div>
                <div>ที่อยู่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($data->customer_address ?? null) . '</div>
                <div>เบอร์โทร&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($data->customer_phone ?? null) . '</div>
            </div>
            <div style="position: absolute;right:40px;width:255px;height:100px;top:210px;padding-left:5px;border: 2px solid #000;">
                <p>วันที่เอกสาร&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->convertToThaiDate($data->date ?? null) . '</p>
            </div>
            <div style="position: absolute;left:40px;top:325px;width:709px;height:70px;border: 2px solid #000;">
                <table style="width:100%;margin-top:5px;">
                    <tr>
                        <td style="text-align: center;">ทะเบียน</td>
                        <td style="text-align: center;">ยี่ห้อรถ</td>
                        <td style="text-align: center;">รุ่นรถ</td>
                        <td style="text-align: center;">สีรถ</td>
                        <td style="text-align: center;">เลขตัวรถ</td>
                        <td style="text-align: center;">เลขเครื่อง</td>
                        <td style="text-align: center;">เลขกิโลเมตร</td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">' . ($data->license_plate ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->brand_name ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->model_name ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->color ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->chassis_number ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->engine_number ?? null) . '</td>
                        <td style="text-align: center;">' . ($data->mileage ?? null) . '</td>
                    </tr>
                </table>
            </div>

            <div style="position: absolute;left:40px;top:408px;width:709px;border: 2px solid #000;line-height:2;">
                <table style="width:100%;border-collapse: collapse;">
                    <thead>
                        <tr>
                            <td style="width:8%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #C0DCC0 ;">ลำดับ</td>
                            <td style="width:36%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #C0DCC0 ;">รายละเอียด</td>
                            <td style="width:9%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #C0DCC0 ;">จำนวน</td>
                            <td style="width:12%;text-align: center;border-bottom: 1px solid #000;border-right: 1px solid #000;background-color: #C0DCC0 ;">หน่วยละ</td>
                            <td style="width:12%;text-align: center;border-bottom: 1px solid #000;background-color: #C0DCC0 ;">จำนวนเงิน</td>
                        </tr>
                    </thead>
                    <tbody>';

            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($data->WorkOrderServices ?? null)); $i++) {

                $item = ($data->WorkOrderServices ?? null)[$i];

                $html_copy .= '
                        <tr>
                            <td style="text-align: center;border-right: 1px solid #000;">' . ($i + 1) . '</td>
                            <td style="border-right: 1px solid #000;">' . $item['service_name'] . '</td>
                            <td style="text-align: center;border-right: 1px solid #000;">' . $item['qty'] . '</td>
                            <td style="text-align: right;border-right: 1px solid #000;">' . number_format($item['unit_price'], 2) . '</td>
                            <td style="text-align: right;">' . number_format($item['service_price'], 2) . '</td>
                        </tr>';
            }
            $TotalAmount = ($data->total ?? null);
            $TotalAmountText = $this->convertToThaiText(number_format($TotalAmount, 2));
            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html_copy .= '
                        <tr>
                            <td style="text-align: center;border-right: 1px solid #000;">&nbsp;</td>
                            <td style="border-right: 1px solid #000;">&nbsp;</td>
                            <td style="text-align: center;border-right: 1px solid #000;">&nbsp;</td>
                            <td style="text-align: center;border-right: 1px solid #000;">&nbsp;</td>
                            <td style="text-align: right;">&nbsp;</td>
                        </tr>';
            }
            $html_copy .= '

                        <tr>
                            <td colspan="3" style="border-top: 1px solid #000;border-right: 1px solid #000;text-align:center;height:30px;">
                                <p>' . $TotalAmountText . '</p>
                            </td>
                            <td rowspan="2" style="border-top: 1px solid #000;text-align:right;border-right: 1px solid #000;height:60px;">ยอดรวม</td>
                            <td rowspan="2" style="border-top: 1px solid #000;text-align:right;height:60px;">' . number_format($TotalAmount, 2) . '</td>
                        </tr>

                        <tr>
                            <td colspan="3" style="border-top: 1px solid #000;border-right: 1px solid #000;text-align:left;padding-left:10px;height:60px;line-height:1.5;">
                                <p>หมายเหตุ&nbsp;&nbsp;' . ($data->remark ?? null) . '</p>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="position: absolute;left:40px;top:910px;width:709px;height:150px;border: 2px solid #000;text-align:center;">
                <table style="border-collapse: collapse;width:100%;">
                    <tr>
                        <td style="width:25%;text-align:center;border-right: 1px solid #000;">&nbsp;</td>
                        <td style="width:20%;text-align:center;border-right: 1px solid #000;">&nbsp;</td>
                        <td style="width:30%;text-align:center;border-right: 1px solid #000;font-size:14px;">ได้รับสินค้าตามรายการข้างต้นไว้เรียบร้อยแล้ว</td>
                        <td style="width:25%;text-align:center;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;height:85px;">&nbsp;</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;height:85px;">&nbsp;</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;height:85px;">&nbsp;</td>
                        <td style="width:auto;text-align:center;height:85px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;">วันที่.....................................</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;">วันที่.....................................</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;">วันที่.....................................</td>
                        <td style="width:auto;text-align:center;">วันที่.....................................</td>
                    </tr>
                    <tr>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;border-top: 1px solid #000;">พนักงาน</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;border-top: 1px solid #000;">ช่างซ่อม</td>
                        <td style="width:auto;text-align:center;border-right: 1px solid #000;border-top: 1px solid #000;">เจ้าของรถ/ผู้รับรถ</td>
                        <td style="width:auto;text-align:center;border-top: 1px solid #000;">ผู้รับเงิน</td>
                    </tr>
                </table>
            </div>
            ';

            $mpdf->WriteHTML($html_copy);
        }

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


    public function receiptcarPDF(Request $request, $id)
    {
        $randomData = [];
        $names = ["ท่อก๊าซทั้งระบบ+ท่อทองแดง+ท่อโปโล+สายรัดท่อ", "สวิตซ์GAS", "ชุดสายไฟ", "ECU", "หัวฉีด", "เครื่องวัดความดันก๊าซ", "หัวเติมก๊าซและวาล์ว", "หม้อต้ม", "วาล์วปิด-เปิดถังก๊าซ", "ถัง NGV 100 ลิตร"];

        for ($i = 0; $i < 16; $i++) {
            $name = $names[array_rand($names)]; // Randomly pick a name
            $price = mt_rand(10, 1000); // Random price between 10 and 1000
            $quantity = mt_rand(1, 10); // Random quantity between 1 and 50

            $randomData[] = [
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity
            ];
        }

        $totalAmount = 0;
        $serviceRows = '';
        $id_gen = 0;
        $round = 0;

        $data = workorder::with(
            'Vehicles',
            'WorkOrderServices',
            'LineItems',
            'Customer',
            'Branches.Provinces',
            'Branches.Districts',
            'Branches.SubDistricts',
        )
            ->where('id', $id)
            ->first();

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
        } else {
            $branche_Name = $data->Branches->business_name ?? null;
            $branche_address = $data->Branches->address ?? null;
            $branche_open_date_time = $data->Branches->open_close_time ?? null;
            $branche_open_date = $data->Branches->open_date ?? null;
            $branche_phone = $data->Branches->phone ?? null;
            $branche_image = $data->Branches->logo ?? null;
        }
        // dd($data);
        // return $data;
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
            'format' => 'A4-L',
            'default_font_size' => 10,
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 16,
            'margin_bottom' => 5,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->SetTitle('ใบรับรถ');

        $itemsPerPage = 11;
        $totalItems = count($data->WorkOrderServices ?? []);
        $pages = ceil($totalItems / $itemsPerPage);
        // return $data;
        $Subtotal = 0;

        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                $mpdf->AddPage();
            }
            // return $data;
            //<img src="storage/Branches/branchs_FIE_Circle_Logo.png" style="width:100px;height:100px;" alt="Icon">
            $html = '

        <div style="position: absolute;top:40px;left:60px;">
            <img src="' . $branche_image . '" style="width:100px;height:100px;" alt="Icon">
        </div>
        <div style="position: absolute;top:25px;left:180px;width:370px;line-height: 1;">
            <h2>' . $branche_Name . '</h2>
            <p style="font-size: 16px;">
                ' .  $branche_address . '</p>
            <p style="font-size: 16px;">' . $branche_phone . '</p>
        </div>
        <div style="position: absolute;top:95px;left:400px;text-align:center;line-height: 1.5;border: 1.5px solid #000; width: 137px;">
            <p style="font-size: 16px;text-align:left;padding-left:10px;border-radius:20px;margin-top:0px;">เลขที่&nbsp;&nbsp;&nbsp; ' . ($data->no ?? null) . '</p>
            <p style="font-size: 16px;text-align:left;padding-left:10px;border-radius:20px;margin-top:0px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->convertDateFormat($data->date ?? null) . '</p>
        </div>
        <div style="position: absolute;top:145.5px;left:70px;height:3px;border: 1.5px solid #000;border-left:none;border-right:none;width:470px;"></div>
        <div style="position: absolute;left:320px;top:135px;font-weight: bold;text-align:left;font-size:22px;left:250px;">
            <p style="border-bottom: 2.5px solid #000; display: inline-block;padding-bottom:-9px;">ใบรับรถ/ใบเสนอราคา</p>
        </div>
        <div style="position: absolute;top:205px;left:70px;height:100px;width:470px;border: 1.5px solid #000;padding-top:-10px;padding-left:5px;">
            <p>ชื่อลูกค้า</p>
        </div>
        <div style="position: absolute;top:205px;left:120px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->customer_name ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:70px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>ยี่ห้อ</p>
        </div>
        </div><div style="position: absolute;top:235px;left:120px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->brand_name ?? null) . '</p>
        </div>
        <div style="position: absolute;top:265px;left:70px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>ที่อยู่</p>
        </div>
        <div style="position: absolute;top:265px;left:120px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->customer_address ?? null) . '</p>
        </div>
        <div style="position: absolute;top:205px;left:300px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>เบอร์โทร</p>
        </div>
        <div style="position: absolute;top:205px;left:350px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->customer_phone ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:200px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>รุ่น</p>
        </div>
        <div style="position: absolute;top:235px;left:220px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->model_name ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:260px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>ทะเบียน</p>
        </div>
        <div style="position: absolute;top:235px;left:305px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->license_plate ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:390px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>เลขกิโลเมตร</p>
        </div>
        <div style="position: absolute;top:235px;left:450px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->mileage ?? null) . '</p>
        </div>

        <div style="position: absolute;top:290px;left:70px;width:480px;">
            <p>รายละเอียดสินค้า และบริการ</p>
            <table style="width:100%;border-collapse: collapse;">
                <thead>
                    <tr>
                        <td style="width:8%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">ลำดับ</td>
                        <td style="width:57%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">รายการ</td>
                        <td style="width:10%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">จำนวน</td>
                        <td style="width:15%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">จำนวนเงิน</td>
                    </tr>
                </thead>

                <tbody>';
            $TotalAmount = 0;
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($data->WorkOrderServices ?? [])); $i++) {
                // return $data->WorkOrderServices[$i];
                $item = $data->WorkOrderServices[$i] ?? null;
                $html .= '
                        <tr>
                            <td style="border: 1px solid #000;text-align:center;">' . ($i + 1) . '</td>
                            <td style="border: 1px solid #000;text-align:left;padding-left:5px;">' . ($item['service_name'] ?? '') . '</td>
                            <td style="border: 1px solid #000;text-align:center;">' . ($item['qty'] ?? 0) . '</td>
                            <td style="border: 1px solid #000;text-align:right;padding-right:5px;">' . number_format($item['service_price'] ?? 0, 2) . '</td>
                        </tr>';
            }
            $TotalAmountText = $this->convertToThaiText(number_format($data->total ?? null, 2));
            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                    <tr>
                        <td style="width: 8%; text-align: center;border: 1px solid #000; border-top: none;">&nbsp;</td>
                        <td style="width: 57%; text-align: left; border: 1px solid #000; padding-left: 10px;">&nbsp;</td>
                        <td style="width: 10%; text-align: center; border: 1px solid #000;">&nbsp;</td>
                        <td style="width: 15%; text-align: right; border: 1px solid #000;">&nbsp;</td>
                    </tr>';
            }
            $html .= '
                    <tr>
                        <td colspan="2" style="border: 1px solid #000;text-align:center;">' . $TotalAmountText . '</td>
                        <td style="border: 1px solid #000;text-align:center;">รวม</td>
                        <td style="border: 1px solid #000;text-align:right;padding-right:5px;">' . number_format($data->total ?? null, 2) . '</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="position: absolute;top:575px;left:70px;width:490px;">
            <p><u>หมายเหตุ</u> : ราคาดังกล่าว<u>ยังไม่รวมภาษีมูลค่าเพิ่ม 7 %</u> / ราคานี้มีผลภายใน 30 วัน นับจากวันที่ออกเอกสาร/ราคาของสินค้ารายการ</p>
            <p>พิเศษประจำเดือนสิ้นสุดเมื่อสิ้นเดือนที่ออกเอกสาร/สินค้าจำนวนจำกัด หรืออาจต้องทำการจองสินค้าล้วงหน้า/บริษัทฯขอสงวน</p>
            <p>สิทธิ์ในการเปลี่ยนแปลงราคา/ราคาดังกล่าวเป็นการประเมิน อาจมีการเปลี่ยนแปลงได้/วันประกันสินค้าตามเงื่อนไขของบริษัทฯ</p>
        </div>

        <div style="position: absolute;top:650px;left:70px;width:255px;border: 1px solid #000;height:50px;">
            <p></p>
            <p></p>
            <p>ลงชื่อ(เจ้าของรถ/ผู้รับรถ)</p>
        </div>
        <div style="position: absolute;top:650px;left:330px;width:210px;border: 1px solid #000;height:50px;">
            <p></p>
            <p></p>
            <p>ลงชื่อเจ้าหน้าที่(ผู้เสนอราคา)</p>
        </div>
        <div style="position: absolute;top:705px;left:470px;text-align:center;line-height: 1.5;color:red;">
            <p style="font-size: 18px;text-align:left;padding-left:10px;border-radius:20px;margin-top:0px;">ต้นฉบับ</p>
        </div>
        <div style="position: absolute; top: 0; left: 50%; width: 1px; height: 100%; border-left: 1px dashed black;"></div>
        ';

            $html .= '

        <div style="position: absolute;top:40px;left:580px;">
            <img src="' . $branche_image . '" style="width:100px;height:100px;" alt="Icon">
        </div>
        <div style="position: absolute;top:25px;left:690px;width:370px;line-height: 1;">
            <h2>' . $branche_Name . '</h2>
            <p style="font-size: 16px;">
                ' . $branche_address . '</p>
            <p style="font-size: 16px;">' . $branche_phone . '</p>
        </div>
        <div style="position: absolute;top:95px;left:920px;text-align:center;line-height: 1.5;border: 1.5px solid #000; width: 137px;">
            <p style="font-size: 16px;text-align:left;padding-left:10px;border-radius:20px;margin-top:0px;">เลขที่&nbsp;&nbsp;&nbsp; ' . ($data->no ?? null) . '</p>
            <p style="font-size: 16px;text-align:left;padding-left:10px;border-radius:20px;margin-top:0px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->convertDateFormat($data->date ?? null) . '</p>
        </div>
        <div style="position: absolute;top:145.5px;left:590px;height:3px;border: 1.5px solid #000;border-left:none;border-right:none;width:470px;"></div>
        <div style="position: absolute;left:780px;top:135px;font-weight: bold;text-align:left;font-size:22px;">
            <p style="border-bottom: 2.5px solid #000; display: inline-block;padding-bottom:-9px;">ใบรับรถ/ใบเสนอราคา</p>
        </div>
        <div style="position: absolute;top:205px;left:580px;height:100px;width:470px;border: 1.5px solid #000;padding-top:-10px;padding-left:5px;">
            <p>ชื่อลูกค้า</p>
        </div>
        <div style="position: absolute;top:205px;left:630px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->customer_name ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:585px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>ยี่ห้อ</p>
        </div>
        </div><div style="position: absolute;top:235px;left:630px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->brand_name ?? null) . '</p>
        </div>
        <div style="position: absolute;top:265px;left:585px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>ที่อยู่</p>
        </div>
        <div style="position: absolute;top:265px;left:630px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->customer_address ?? null) . '</p>
        </div>
        <div style="position: absolute;top:205px;left:830px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>เบอร์โทร</p>
        </div>
        <div style="position: absolute;top:205px;left:870px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->customer_phone ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:700px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>รุ่น</p>
        </div>
        <div style="position: absolute;top:235px;left:730px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->model_name ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:790px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>ทะเบียน</p>
        </div>
        <div style="position: absolute;top:235px;left:835px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->license_plate ?? null) . '</p>
        </div>
        <div style="position: absolute;top:235px;left:910px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>เลขกิโลเมตร</p>
        </div>
        <div style="position: absolute;top:235px;left:970px;height:100px;width:470px;padding-top:-10px;padding-left:5px;">
            <p>' . ($data->mileage ?? null) . '</p>
        </div>

        <div style="position: absolute;top:290px;left:580px;width:480px;">
            <p>รายละเอียดสินค้า และบริการ</p>
            <table style="width:100%;border-collapse: collapse;">
                <thead>
                    <tr>
                        <td style="width:8%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">ลำดับ</td>
                        <td style="width:57%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">รายการ</td>
                        <td style="width:10%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">จำนวน</td>
                        <td style="width:15%;border: 1px solid #000;text-align:center;background-color: #c3e8e9;">จำนวนเงิน</td>
                    </tr>
                </thead>
                <tbody>';
            $Subtotal = 0;

            $VatAmount = 0;
            $NetAmount = 0;
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($data->WorkOrderServices ?? [])); $i++) {

                $item = $data->WorkOrderServices[$i] ?? null;
                $html .= '
                     <tr>
                            <td style="border: 1px solid #000;text-align:center;">' . ($i + 1) . '</td>
                            <td style="border: 1px solid #000;text-align:left;padding-left:5px;">' . ($item['service_name'] ?? '') . '</td>
                            <td style="border: 1px solid #000;text-align:center;">' . ($item['qty'] ?? 0) . '</td>
                            <td style="border: 1px solid #000;text-align:right;padding-right:5px;">' . number_format($item['service_price'] ?? 0, 2) . '</td>
                        </tr>';
            }
            $TotalAmountText = $this->convertToThaiText(number_format($data->total ?? null, 2));
            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                    <tr>
                        <td style="width: 8%; text-align: center;border: 1px solid #000;">&nbsp;</td>
                        <td style="width: 57%; text-align: left;border: 1px solid #000; padding-left: 10px;">&nbsp;</td>
                        <td style="width: 10%; text-align: center;border: 1px solid #000;">&nbsp;</td>
                        <td style="width: 15%; text-align: right;border: 1px solid #000;">&nbsp;</td>
                    </tr>';
            }
            $html .= '
                    <tr>
                        <td colspan="2" style="border: 1px solid #000;text-align:center;">' . $TotalAmountText . '</td>
                        <td style="border: 1px solid #000;text-align:center;">รวม</td>
                        <td style="border: 1px solid #000;text-align:right;padding-right:5px;">' . number_format($data->total ?? null, 2) . '</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="position: absolute;top:575px;left:580px;width:490px;">
            <p><u>หมายเหตุ</u> : ราคาดังกล่าว<u>ยังไม่รวมภาษีมูลค่าเพิ่ม 7 %</u> / ราคานี้มีผลภายใน 30 วัน นับจากวันที่ออกเอกสาร/ราคาของสินค้ารายการ</p>
            <p>พิเศษประจำเดือนสิ้นสุดเมื่อสิ้นเดือนที่ออกเอกสาร/สินค้าจำนวนจำกัด หรืออาจต้องทำการจองสินค้าล้วงหน้า/บริษัทฯขอสงวน</p>
            <p>สิทธิ์ในการเปลี่ยนแปลงราคา/ราคาดังกล่าวเป็นการประเมิน อาจมีการเปลี่ยนแปลงได้/วันประกันสินค้าตามเงื่อนไขของบริษัทฯ</p>
        </div>

        <div style="position: absolute;top:650px;left:580px;width:265px;border: 1px solid #000;height:50px;">
            <p></p>
            <p></p>
            <p>ลงชื่อ(เจ้าของรถ/ผู้รับรถ)</p>
        </div>
        <div style="position: absolute;top:650px;left:850px;width:210px;border: 1px solid #000;height:50px;">
            <p></p>
            <p></p>
            <p>ลงชื่อเจ้าหน้าที่(ผู้เสนอราคา)</p>
        </div>
        <div style="position: absolute;top:705px;left:990px;text-align:center;line-height: 1.5;color:red;">
            <p style="font-size: 18px;text-align:left;padding-left:10px;border-radius:20px;margin-top:0px;">สำหรับลูกค้า</p>
        </div>
        <div style="position: absolute; top: 0; left: 50%; width: 1px; height: 100%; border-left: 1px dashed black;"></div>
        ';

            $mpdf->WriteHTML($html);
        }
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

    public function billing_report(Request $request)
    {
        $customerId = $request->input('customer_id', null);
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'customer_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $start_date = Carbon::parse($start_date);
        $end_date = Carbon::parse($end_date);

        $query = Customers::query()
            ->with([
                'inspections' => function ($query) use ($start_date, $end_date) {
                    if ($start_date && $end_date) {
                        $query->whereBetween('date', [$start_date, $end_date]);
                    }
                    $query->with('InspectionVehicles.vehicleServiceTransaction');
                },
            ]);
        if ($customerId) {
            $query->where('id', $customerId); // Filter by customer_id
        }

        $items = $query->get();

        foreach ($items as $item) {
            $inspectionStatus = $item->transaction_status ?? null;
            $customerName = $item->name;
            $customerLName = $item->lname;
        }

        $checks = [
            1 => '&#9745;', // ตรวจสภาพรถ
            2 => '&#9745;', // พ.ร.บ.
            3 => '&#9745;', // ภาษีประจำปี
            4 => '&#9745;', // ค่าปรับภาษีประจำปี
            5 => '&#9745;', // บริการต่อภาษี
            6 => '&#9745;', // ค่าปรับ
            7 => '&#9745;', // ประกัน
            8 => '&#9745;', // ตรวจสภาพ LPG
            9 => '&#9745;', // ตรวจติดตั้ง LPG
            10 => '&#9745;', // ตรวจต่ออายุ LPG
            11 => '&#9745;', // ตรวจสภาพ NGV
            12 => '&#9745;', // ตรวจติดตั้ง NGV
            13 => '&#9745;', // หนังสือรับรองวิศวกร
            14 => '&#9745;', // ขนส่ง
            15 => '&#9745;', // EMS
            16 => '&#9745;' // อื่นๆ
        ];

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);
        $formattedStartDate = Carbon::parse($start_date)->format('d/m/Y');
        $formattedEndDate = Carbon::parse($end_date)->format('d/m/Y');
        $mpdf->SetTitle('Billing_report');
        $mpdf->AddPage();
        $html = '
        <div style="text-align:center;border: 1px solid #000;font-weight: bold;">
            ข้อมูลรายการงานลูกค้า ' . $customerName . ' ' . $customerLName . ' ' . $formattedStartDate . ' ถึง ' . $formattedEndDate . '
        </div>
        <div>
            <table style="width: 100%;border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;width:3%;">ลำดับ</th>
                        <th style="border: 1px solid #000;width:5%;">รหัสบิล</th>
                        <th style="border: 1px solid #000;width:5%;">ทะเบียนรถ</th>
                        <th style="border: 1px solid #000;width:6%;">วันที่</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจสภาพรถ</th>
                        <th style="border: 1px solid #000;width:5%;">พ.ร.บ.</th>
                        <th style="border: 1px solid #000;width:5%;">ภาษีประจำปี</th>
                        <th style="border: 1px solid #000;width:5%;">ค่าปรับภาษีประจำปี</th>
                        <th style="border: 1px solid #000;width:5%;">บริการต่อภาษี</th>
                        <th style="border: 1px solid #000;width:5%;">ค่าปรับ</th>
                        <th style="border: 1px solid #000;width:5%;">ประกัน</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจสภาพ LPG</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจติดตั้ง LPG</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจต่ออายุ LPG</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจสภาพ NGV</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจติดตั้ง NGV</th>
                        <th style="border: 1px solid #000;width:5%;">หนังสือรับรองวิศวกร</th>
                        <th style="border: 1px solid #000;width:5%;">ขนส่ง</th>
                        <th style="border: 1px solid #000;width:5%;">อื่นๆ</th>
                        <th style="border: 1px solid #000;width:5%;">EMS</th>
                        <th style="border: 1px solid #000;width:5%;">สุทธิ</th>
                        <th style="border: 1px solid #000;width:5%;">สถานะ</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($items as $index => $item) {
            foreach ($item->inspections as $key => $data) {
                $inspectionNo = $data->no;
                $inspectionDate = $data->date;
                $inspectionStatus = $data->status;
                $inspectionTotal = $data->total;

                if ($key >= 0) {
                    $html .= '<tr>';
                }
                $html .= '<td style="border: 1px solid #000;text-align:center;">' . ($key + 1) . '</td>'; // ลำดับ
                $html .= '<td style="border: 1px solid #000;text-align: center;">' . $inspectionNo . '</td>'; // รหัสบิล

                // dd($data);
                $serviceChecks = array_fill(1, 16, ''); // Default values for each column

                // Check if there are any InspectionVehicles and loop through them
                if (!empty($data->InspectionVehicles)) {
                    foreach ($data->InspectionVehicles as $service) {
                        $html .= '<td style="border: 1px solid #000;text-align: center;">' . $service->license_plate . '</td>'; // วันที่
                        if (!empty($service->vehicleServiceTransaction)) {
                            foreach ($service->vehicleServiceTransaction as $serviceid) {
                                $servic = $serviceid->service_id;
                                // If the service_id exists in the checks, update the corresponding column
                                if (isset($checks[$servic])) {
                                    $serviceChecks[$servic] = $checks[$servic];
                                }
                            }
                        }
                    }
                }

                $date = $inspectionDate; // วันที่ในรูปแบบ Y-m-d
                $newFormatDate = Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y');
                $html .= '<td style="border: 1px solid #000;text-align: center;">' . $newFormatDate . '</td>'; // วันที่

                for ($i = 1; $i <= 16; $i++) {
                    $check = $serviceChecks[$i]; // Get the value for the current service column
                    $html .= '<td style="text-align: center;border: 1px solid #000;">
                                <p style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $check . '</p>
                              </td>';
                }
                switch ($inspectionStatus) {
                    case "open":
                        $status = "เปิด";
                        break;
                    case "finish";
                        $status = "สำเร็จ";
                        break;
                    case "overdue";
                        $status = "แบ่งจ่าย";
                        break;
                    case "unpaid";
                        $status = "ค้างจ่าย";
                        break;
                    case "cancel";
                        $status = "ยกเลิก";
                        break;
                }

                $html .= '<td style="border: 1px solid #000;text-align: right;">' . number_format($inspectionTotal, 2) . '</td>'; // สุทธิ
                $html .= '<td style="border: 1px solid #000;text-align: center;">' . $status . '</td>'; // สถานะ
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';
        $html .= '</table></div>';

        $mpdf->WriteHTML($html);
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

    public function cal_price_billing_report(Request $request)
    {
        $customerId = $request->input('customer_id', null);
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'customer_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $start_date = Carbon::parse($start_date);
        $end_date = Carbon::parse($end_date);

        $query = Customers::query()
            ->with([
                'inspections' => function ($query) use ($start_date, $end_date) {
                    if ($start_date && $end_date) {
                        $query->whereBetween('date', [$start_date, $end_date]);
                    }
                    $query->with('InspectionVehicles.vehicleServiceTransaction');
                },
            ]);
        if ($customerId) {
            $query->where('id', $customerId); // Filter by customer_id
        }

        $items = $query->get();

        foreach ($items as $item) {
            $inspectionStatus = $item->transaction_status ?? null;
            $customerName = $item->name;
            $customerLName = $item->lname;
        }

        $checks = [
            1 => '&#9745;', // ตรวจสภาพรถ
            2 => '&#9745;', // พ.ร.บ.
            3 => '&#9745;', // ภาษีประจำปี
            4 => '&#9745;', // ค่าปรับภาษีประจำปี
            5 => '&#9745;', // บริการต่อภาษี
            6 => '&#9745;', // ค่าปรับ
            7 => '&#9745;', // ประกัน
            8 => '&#9745;', // ตรวจสภาพ LPG
            9 => '&#9745;', // ตรวจติดตั้ง LPG
            10 => '&#9745;', // ตรวจต่ออายุ LPG
            11 => '&#9745;', // ตรวจสภาพ NGV
            12 => '&#9745;', // ตรวจติดตั้ง NGV
            13 => '&#9745;', // หนังสือรับรองวิศวกร
            14 => '&#9745;', // ขนส่ง
            15 => '&#9745;', // EMS
            16 => '&#9745;' // อื่นๆ
        ];

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);
        $formattedStartDate = Carbon::parse($start_date)->format('d/m/Y');
        $formattedEndDate = Carbon::parse($end_date)->format('d/m/Y');
        $mpdf->SetTitle('Billing_report');
        $mpdf->AddPage();
        $html = '
        <div style="text-align:center;border: 1px solid #000;font-weight: bold;">
            ข้อมูลรายการงานลูกค้า ' . $customerName . ' ' . $customerLName . ' ' . $formattedStartDate . ' ถึง ' . $formattedEndDate . '
        </div>
        <div>
            <table style="width: 100%;border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;width:3%;">ลำดับ</th>
                        <th style="border: 1px solid #000;width:5%;">รหัสบิล</th>
                        <th style="border: 1px solid #000;width:5%;">ทะเบียนรถ</th>
                        <th style="border: 1px solid #000;width:6%;">วันที่</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจสภาพรถ</th>
                        <th style="border: 1px solid #000;width:5%;">พ.ร.บ.</th>
                        <th style="border: 1px solid #000;width:5%;">ภาษีประจำปี</th>
                        <th style="border: 1px solid #000;width:5%;">ค่าปรับภาษีประจำปี</th>
                        <th style="border: 1px solid #000;width:5%;">บริการต่อภาษี</th>
                        <th style="border: 1px solid #000;width:5%;">ค่าปรับ</th>
                        <th style="border: 1px solid #000;width:5%;">ประกัน</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจสภาพ LPG</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจติดตั้ง LPG</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจต่ออายุ LPG</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจสภาพ NGV</th>
                        <th style="border: 1px solid #000;width:5%;">ตรวจติดตั้ง NGV</th>
                        <th style="border: 1px solid #000;width:5%;">หนังสือรับรองวิศวกร</th>
                        <th style="border: 1px solid #000;width:5%;">ขนส่ง</th>
                        <th style="border: 1px solid #000;width:5%;">อื่นๆ</th>
                        <th style="border: 1px solid #000;width:5%;">EMS</th>
                        <th style="border: 1px solid #000;width:5%;">สุทธิ</th>
                        <th style="border: 1px solid #000;width:5%;">สถานะ</th>
                    </tr>
                </thead>
                <tbody>';
        $totals = array_fill(1, 16, 0.00);
        $totalInspectionCount = 0; // ตัวแปรสำหรับนับจำนวน $inspectionTotal
        $totalInspectionSum = 0.00; // ตัวแปรสำหรับผลรวมของ $inspectionTotal

        foreach ($items as $index => $item) {
            // dd($item);
            foreach ($item->inspections as $key => $data) {
                $inspectionNo = $data->no;
                $inspectionDate = $data->date;
                $inspectionStatus = $data->status;
                $inspectionTotal = $data->total;

                // นับ $inspectionTotal
                if (!empty($inspectionTotal)) {
                    $totalInspectionCount++; // เพิ่มจำนวนครั้ง
                    $totalInspectionSum += $inspectionTotal; // สะสมผลรวม
                }

                if ($key >= 0) {
                    $html .= '<tr>';
                }
                $html .= '<td style="border: 1px solid #000;text-align:center;">' . ($key + 1) . '</td>'; // ลำดับ
                $html .= '<td style="border: 1px solid #000;">' . $inspectionNo . '</td>'; // รหัสบิล

                // dd($data);
                $serviceChecks = array_fill(1, 16, ''); // Default values for each column

                // Check if there are any InspectionVehicles and loop through them
                if (!empty($data->InspectionVehicles)) {
                    foreach ($data->InspectionVehicles as $service) {
                        $html .= '<td style="border: 1px solid #000;text-align: center;">' . $service->license_plate . '</td>'; // วันที่
                        if (!empty($service->vehicleServiceTransaction)) {
                            foreach ($service->vehicleServiceTransaction as $serviceid) {
                                $servic = $serviceid->service_id;
                                $servicprice = $serviceid->service_price;
                                // dd($serviceid);
                                // If the service_id exists in the checks, update the corresponding column
                                if (isset($checks[$servic])) {
                                    $serviceChecks[$servic] = number_format($servicprice, 2);
                                    $totals[$servic] += $servicprice;
                                }
                            }
                        }
                    }
                }

                $date = $inspectionDate; // วันที่ในรูปแบบ Y-m-d
                $newFormatDate = Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y');
                $html .= '<td style="border: 1px solid #000;">' . $newFormatDate . '</td>'; // วันที่

                for ($i = 1; $i <= 16; $i++) {
                    $check = $serviceChecks[$i]; // Get the value for the current service column
                    $html .= '<td style="text-align: right;border: 1px solid #000;">
                                <p style="font-size: 18px;">' . $check . '</p>
                              </td>';
                }
                switch ($inspectionStatus) {
                    case "open":
                        $status = "เปิด";
                        break;
                    case "finish";
                        $status = "สำเร็จ";
                        break;
                    case "overdue";
                        $status = "แบ่งจ่าย";
                        break;
                    case "unpaid";
                        $status = "ค้างจ่าย";
                        break;
                    case "cancel";
                        $status = "ยกเลิก";
                        break;
                }

                $html .= '<td style="border: 1px solid #000;text-align: right;">' . number_format($inspectionTotal, 2) . '</td>'; // สุทธิ
                $html .= '<td style="border: 1px solid #000;text-align: center;">' . $status . '</td>'; // สถานะ
                $html .= '</tr>';
            }
        }
        // เพิ่มแถวสำหรับผลรวม
        $html .= '<tfoot>';
        $html .= '<tr>';
        $html .= '<td colspan="4" style="border: 1px solid #000;text-align: center; font-weight: bold;">รวม</td>'; // คอลัมน์แรกสำหรับ "รวม"
        for ($i = 1; $i <= 16; $i++) {
            $total = $totals[$i] > 0 ? number_format($totals[$i], 2) . ' ' : '0.00 ';
            $html .= '<td style="text-align: right;border: 1px solid #000; font-weight: bold;">
                <p style="font-size: 18px;">' . $total . '</p>
              </td>';
        }
        $html .= '<td style="border: 1px solid #000; font-weight: bold;">' . number_format($totalInspectionSum, 2) . '</td>'; // รวมสุทธิ
        $html .= '<td style="border: 1px solid #000; font-weight: bold; text-align: center;">&nbsp;</td>'; // ' . $totalInspectionCount . ' รายการ จำนวน $inspectionTotal
        $html .= '</tr>';
        $html .= '</tfoot>';
        $html .= '</tbody>';
        $html .= '</table></div>';

        $mpdf->WriteHTML($html);
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

    public function Prb_report(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no as bill_number',
            'inspection_vehicles.license_plate',
            'insurance_names.name as insurance_name',
            'insurances_renewals.id as id_prb',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.amount_paid',
            'insurances_renewals.stamp',
            'insurances_renewals.tax',
            'insurances_renewals.policy_number',
            'insurances_renewals.policy_status',
            'insurances_renewals.remark',
            'insurances_renewals.type',
            'customers.phone_number1 as customer_phone_number',
            'inspections.created_at',
            'inspections.updated_at',
        ];

        $query = Inspections::query()
            ->select($columns)
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('insurances_renewals', 'vechicle_services_transactions.id', '=', 'insurances_renewals.vechicle_services_transaction_id')->where('service_id', 2)
            ->leftJoin('insurance_renewal_types', 'insurances_renewals.insurance_renewal_type_id', '=', 'insurance_renewal_types.id')
            ->leftJoin('insurance_names', 'insurances_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('insurances_renewals.deleted_at')
            ->whereNull('insurance_renewal_types.deleted_at')
            ->whereNull('insurance_names.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereNull('inspections.deleted_at')
            ->where('inspections.branch_id',$branchId)
            ->orderBy('inspections.no', 'desc');

        // Apply date filters based on the inspections.date field
        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if ($request->has('insurance_name') && !empty($request->input('insurance_name'))) {
            $query->where('insurance_names.name', 'like', '%' . $request->input('insurance_name') . '%');
        }

        $data = $query->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $html = '
        <thead>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <tr>
                    <td colspan="9" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รายงาน พ.ร.บ. วันที่ ' . $startDate->format('d/m/') . (Carbon::parse($startDate)->year + 543)  . ' ถึง ' . $endDate->format('d/m/') . (Carbon::parse($startDate)->year + 543) . '
                    </td>
                </tr>
                <tr>
                    <th>ลำดับ</th>
                    <th>เลขบิล</th>
                    <th>วันที่</th>
                    <th>ทะเบียน</th>
                    <th>เบอร์โทร</th>
                    <th>ประกันภัย</th>
                    <th>วันที่เริ่มการคุ้มครอง</th>
                    <th>วันที่สิ้นสุดการคุ้มครอง</th>
                    <th>เลขกรรมธรรม์</th>
                </tr>
            </thead>
                <tbody>';

        foreach ($data as $index => $item) {
            $html .= '
            <tr>
                <td align="center">' . ($index + 1) . '</td>
                <td align="center">' . (empty($item->bill_number) ? '-' : $item->bill_number) . '</td>
                <td align="center">' . (empty($item->date) ? '-' : Carbon::parse($item->date)->format('d/m/') . (Carbon::parse($item->date)->year + 543))  . '</td>
                <td align="center">' . (empty($item->license_plate) ? '-' : $item->license_plate) . '</td>
                <td align="center">' . (empty($item->customer_phone_number) ? '-' : $item->customer_phone_number) . '</td>
                <td align="center">' . (empty($item->insurance_name) ? '-' : $item->insurance_name) . '</td>
                <td align="center">' . (empty($item->date_start) ? '-' : Carbon::parse($item->date_start)->format('d/m/') . (Carbon::parse($item->date)->year + 543))  . '</td>
                <td align="center">' . (empty($item->date_end) ? '-' : Carbon::parse($item->date_end)->format('d/m/') . (Carbon::parse($item->date)->year + 543))  . '</td>
                <td align="center">' . (empty($item->policy_number) ? '-' : $item->policy_number) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
        $mpdf->WriteHTML($html);
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

    public function Prb_report_export_excel(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate   = $request->input('end_date')   ? Carbon::parse($request->input('end_date'))->endOfDay()     : null;
        $branchId  = $request->input('branch_id', null);

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no as bill_number',
            'inspection_vehicles.license_plate',
            'insurance_names.name as insurance_name',
            'insurances_renewals.id as id_prb',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.amount_paid',
            'insurances_renewals.stamp',
            'insurances_renewals.tax',
            'insurances_renewals.policy_number',
            'insurances_renewals.policy_status',
            'insurances_renewals.remark',
            'insurances_renewals.type',
            'customers.phone_number1 as customer_phone_number',
            'inspections.created_at',
            'inspections.updated_at',
        ];

        $query = \App\Models\Inspections::query()
            ->select($columns)
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('insurances_renewals', 'vechicle_services_transactions.id', '=', 'insurances_renewals.vechicle_services_transaction_id')
            ->where('service_id', 2)
            ->leftJoin('insurance_renewal_types', 'insurances_renewals.insurance_renewal_type_id', '=', 'insurance_renewal_types.id')
            ->leftJoin('insurance_names', 'insurances_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('insurances_renewals.deleted_at')
            ->whereNull('insurance_renewal_types.deleted_at')
            ->whereNull('insurance_names.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereNull('inspections.deleted_at')
            ->when($branchId, fn($q) => $q->where('inspections.branch_id', $branchId))
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('inspections.date', [$startDate, $endDate]))
            ->when($request->filled('insurance_name'), fn($q) => $q->where('insurance_names.name', 'like', '%'.$request->input('insurance_name').'%'))
            ->orderBy('inspections.no', 'desc');

        $rows = $query->get(); // Collection

        return Excel::download(
            new PrbReportExport($rows, $startDate, $endDate),
            'รายงาน_พ.ร.บ..xlsx'
        );
    }

    public function InsurancesRenewals_report(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.id as inspection_vehicle_id',
            'vechicle_services_transactions.id as vechicle_services_transaction_id',
            'customers.phone_number1 as customer_phone_number',
            'insurance_renewal_types.name as insurance_renewal_type_name',
            'insurances_renewals.id as id_insuran',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.type',
            'insurances_renewals.amount_paid',
            'insurances_renewals.policy_number',
            'insurances_renewals.policy_status',
            'insurances_renewals.insurance_renewal_type_id',
            'insurances_renewals.remark',
            'insurance_names.name',
        ];

        $query = Inspections::query()
            ->select($columns)
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('insurances_renewals', 'vechicle_services_transactions.id', '=', 'insurances_renewals.vechicle_services_transaction_id')->where('service_id', 7)
            ->leftJoin('insurance_renewal_types', 'insurances_renewals.insurance_renewal_type_id', '=', 'insurance_renewal_types.id')
            ->leftJoin('insurance_names', 'insurances_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('insurances_renewals.deleted_at')
            ->whereNull('insurance_renewal_types.deleted_at')
            ->whereNull('insurance_names.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereNull('inspections.deleted_at')
            ->where('inspections.branch_id',$branchId)
            ->distinct();

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        if ($request->has('insurance_name') && !empty($request->input('insurance_name'))) {
            $query->where('insurance_names.name', 'like', '%' . $request->input('insurance_name') . '%');
        }

        $data = $query->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $html = '
        <thead>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <tr>
                    <td colspan="9" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รายงานประกัน วันที่ ' . $startDate->format('d/m/') . (Carbon::parse($startDate)->year + 543)  . ' ถึง ' . $endDate->format('d/m/') . (Carbon::parse($startDate)->year + 543) . '
                    </td>
                </tr>
                <tr>
                    <th>ลำดับ</th>
                    <th>เลขบิล</th>
                    <th>วันที่</th>
                    <th>ทะเบียน</th>
                    <th>เบอร์โทร</th>
                    <th>ประกันภัย</th>
                    <th>วันที่เริ่มการคุ้มครอง</th>
                    <th>วันที่สิ้นสุดการคุ้มครอง</th>
                    <th>เลขกรรมธรรม์</th>
                </tr>
            </thead>
                <tbody>';

        foreach ($data as $index => $item) {
            $html .= '
            <tr>
                <td align="center">' . ($index + 1) . '</td>
                <td align="center">' . ($item->no ?: '-') . '</td>
                <td align="center">' . ($item->date ? Carbon::parse($item->date)->format('d/m/') . (Carbon::parse($item->date)->year + 543) : '-') . '</td>
                <td align="center">' . ($item->license_plate ?: '-') . '</td>
                <td align="center">' . ($item->customer_phone_number ?: '-') . '</td>
                <td align="center">' . ($item->name ?: '-') . '</td>
                <td align="center">' . ($item->date_start ? Carbon::parse($item->date_start)->format('d/m/') . (Carbon::parse($item->date_start)->year + 543) : '-') . '</td>
                <td align="center">' . ($item->date_end ? Carbon::parse($item->date_end)->format('d/m/') . (Carbon::parse($item->date_end)->year + 543) : '-') . '</td>
                <td align="center">' . ($item->policy_number ?: '-') . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
        $mpdf->WriteHTML($html);
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

    public function InsurancesRenewals_report_export_excel(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate   = $request->input('end_date')   ? Carbon::parse($request->input('end_date'))->endOfDay()     : null;
        $branchId  = $request->input('branch_id', null);

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $columns = [
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.id as inspection_vehicle_id',
            'vechicle_services_transactions.id as vechicle_services_transaction_id',
            'customers.phone_number1 as customer_phone_number',
            'insurance_renewal_types.name as insurance_renewal_type_name',
            'insurances_renewals.id as id_insuran',
            'insurances_renewals.date_start',
            'insurances_renewals.date_end',
            'insurances_renewals.type',
            'insurances_renewals.amount_paid',
            'insurances_renewals.policy_number',
            'insurances_renewals.policy_status',
            'insurances_renewals.insurance_renewal_type_id',
            'insurances_renewals.remark',
            'insurance_names.name', // ชื่อบริษัทประกัน
        ];

        $q = \App\Models\Inspections::query()
            ->select($columns)
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->leftJoin('insurances_renewals', 'vechicle_services_transactions.id', '=', 'insurances_renewals.vechicle_services_transaction_id')
            ->where('service_id', 7)
            ->leftJoin('insurance_renewal_types', 'insurances_renewals.insurance_renewal_type_id', '=', 'insurance_renewal_types.id')
            ->leftJoin('insurance_names', 'insurances_renewals.insurance_names_id', '=', 'insurance_names.id')
            ->whereNull('inspection_vehicles.deleted_at')
            ->whereNull('vechicle_services_transactions.deleted_at')
            ->whereNull('insurances_renewals.deleted_at')
            ->whereNull('insurance_renewal_types.deleted_at')
            ->whereNull('insurance_names.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereNull('inspections.deleted_at')
            ->when($branchId, fn($q) => $q->where('inspections.branch_id', $branchId))
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('inspections.date', [$startDate, $endDate]))
            ->when($request->filled('insurance_name'), fn($q) => $q->where('insurance_names.name', 'like', '%'.$request->input('insurance_name').'%'))
            ->orderBy('inspections.no', 'desc')
            ->distinct();

        $rows = $q->get(); // Illuminate\Support\Collection

        return Excel::download(
            new InsurancesRenewalsReportExport($rows, $startDate, $endDate),
            'รายงานประกัน.xlsx'
        );
    }

    public function EMS_report(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $branchId  = $request->input('branch_id', null);
        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no',
            'address_ems.address',
            'address_ems.name',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.vehicle_inspection_types as vehicle_inspection_types',
            'customers.name',
            'inspections.ems',
            'customers.phone_number1 as phone',
            'provinces.id as province_id',
            'provinces.name_th_prefix as province_name',
            'districts.id as district_id',
            'districts.name_th_prefix as district_name',
            'sub_districts.id as subdistrict_id',
            'sub_districts.name_th_prefix as sub_district_name',
            'sub_districts.zip_code as zip_code',
            'vechicle_services_transactions.service_id',
        ];

        $query = Inspections::query()
            ->select($columns)
            ->leftJoin('address_ems', 'inspections.id', '=', 'address_ems.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('provinces', 'address_ems.province_id', '=', 'provinces.id')
            ->leftJoin('districts', 'address_ems.district_id', '=', 'districts.id')
            ->leftJoin('sub_districts', 'address_ems.subdistrict_id', '=', 'sub_districts.id')
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->where('branch_id', $branchId)
            ->where('vechicle_services_transactions.service_id', 16);

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        $data = $query->get();

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $html = '
        <thead>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <tr>
                    <td colspan="9" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รายงาน EMS ข้อมูลลูกค้า วันที่ ' . $startDate->format('d/m/') . (Carbon::parse($startDate)->year + 543)  . ' ถึง ' . $endDate->format('d/m/') . (Carbon::parse($startDate)->year + 543) . '
                    </td>
                </tr>
                <tr>
                    <th>ลำดับ</th>
                    <th>เลขบิล</th>
                    <th>วันที่</th>
                    <th>ทะเบียน</th>
                    <th>ประเภท</th>
                    <th>ชื่อผู้รับ</th>
                    <th>หมายเลขโทรศัพท์</th>
                    <th>หมายเลข EMS</th>
                    <th>ที่อยู่ผู้รับ</th>
                </tr>
            </thead>
                <tbody>';

        foreach ($data as $index => $item) {
            $html .= '
            <tr>
                <td align="center">' . ($index + 1) . '</td>
                <td align="center">' . ($item->no ?: '-') . '</td>
                <td align="center">' . ($item->date ? Carbon::parse($item->date)->format('d/m/') . (Carbon::parse($item->date)->year + 543) : '-') . '</td>
                <td align="center">' . ($item->license_plate ?: '-') . '</td>
                <td align="center">' . ($item->vehicle_inspection_types ?: '-') . '</td>
                <td align="center">' . ($item->name ?: '-') . '</td>
                <td align="center">' . ($item->phone ?: '-') . '</td>
                <td align="center">' . ($item->ems ?: '-') . '</td>
                <td align="center">' . ($item->address ?: '-') . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
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

    public function EMS_report_export_excel(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate   = $request->input('end_date')   ? Carbon::parse($request->input('end_date'))->endOfDay()     : null;
        $branchId  = $request->input('branch_id', null);

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }

        $columns = [
            'inspections.id',
            'inspections.date',
            'inspections.no',
            'address_ems.address',
            'address_ems.name',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.vehicle_inspection_types as vehicle_inspection_types',
            'customers.name as customer_name',
            'inspections.ems',
            'customers.phone_number1 as phone',
            'provinces.id as province_id',
            'provinces.name_th_prefix as province_name',
            'districts.id as district_id',
            'districts.name_th_prefix as district_name',
            'sub_districts.id as subdistrict_id',
            'sub_districts.name_th_prefix as sub_district_name',
            'sub_districts.zip_code as zip_code',
            'vechicle_services_transactions.service_id',
        ];

        $query = \App\Models\Inspections::query()
            ->select($columns)
            ->leftJoin('address_ems', 'inspections.id', '=', 'address_ems.inspection_id')
            ->leftJoin('customers', 'inspections.customer_id', '=', 'customers.id')
            ->leftJoin('provinces', 'address_ems.province_id', '=', 'provinces.id')
            ->leftJoin('districts', 'address_ems.district_id', '=', 'districts.id')
            ->leftJoin('sub_districts', 'address_ems.subdistrict_id', '=', 'sub_districts.id')
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('vechicle_services_transactions', 'inspection_vehicles.id', '=', 'vechicle_services_transactions.inspection_vehicle_id')
            ->when($branchId, fn($q) => $q->where('inspections.branch_id', $branchId)) // ระบุ table ให้ชัด
            ->where('vechicle_services_transactions.service_id', 16);

        if ($startDate && $endDate) {
            $query->whereBetween('inspections.date', [$startDate, $endDate]);
        }

        $rows = $query->get(); // Collection

        return Excel::download(
            new EMSReportExport($rows, $startDate, $endDate),
            'รายงาน_EMS.xlsx'
        );
    }

    public function remind_report(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;


        $columns = [
            'vehicles.id',
            'vehicles.license_plate',
            'customer_name',
            'province_name',
            'phone_number',
            'vehicles.tax_renewal_date',
            'prb_renewals.date_end as prb_expiry_date',
            'insurances_renewals.date_end as insurance_expiry_date',
        ];

        $today     = Carbon::now();
        $ninetyDay = $today->copy()->addDays(90);

        $query = Vehicles::select(
            'vehicles.id',
            'vehicles.license_plate',
            DB::raw("COALESCE(customers.name, 'null') as customer_name"),
            'customers.phone_number1 as phone_number',
            'vehicles.tax_renewal_date',
            DB::raw("(SELECT MAX(date_end) FROM prb_renewals WHERE prb_renewals.vehicle_id = vehicles.id) as prb_expiry_date"),
            DB::raw("(SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id) as insurance_expiry_date"),
            DB::raw("CASE WHEN vehicles.tax_renewal_date BETWEEN '$today' AND '$ninetyDay' THEN true ELSE false END as tax_renewal_date_status"),
            DB::raw("CASE WHEN (SELECT MAX(date_end) FROM prb_renewals WHERE prb_renewals.vehicle_id = vehicles.id) BETWEEN '$today' AND '$ninetyDay' THEN true ELSE false END as prb_expiry_date_status"),
            DB::raw("CASE WHEN (SELECT MAX(date_end) FROM insurances_renewals WHERE insurances_renewals.vehicle_id = vehicles.id) BETWEEN '$today' AND '$ninetyDay' THEN true ELSE false END as insurance_expiry_date_status")
        )
            ->leftJoin('customers', 'vehicles.customer_id', '=', 'customers.id')
            ->leftJoin('prb_renewals', 'vehicles.id', '=', 'prb_renewals.vehicle_id')
            ->leftJoin('insurances_renewals', 'vehicles.id', '=', 'insurances_renewals.vehicle_id')
            ->where(function ($query) use ($today, $ninetyDay) {
                $query->whereBetween('vehicles.tax_renewal_date', [$today, $ninetyDay])
                    ->orWhereBetween('prb_renewals.date_end', [$today, $ninetyDay])
                    ->orWhereBetween('insurances_renewals.date_end', [$today, $ninetyDay]);
            })
            ->havingRaw('tax_renewal_date_status = true OR prb_expiry_date_status = true OR insurance_expiry_date_status = true')
            ->distinct();

        $data = $query->get();
        // return $data;
        $datefor = '';
        foreach ($data as $index => $item) {

            $isTaxExpired = !$item->tax_renewal_date_status; // ถ้า status เป็น false แสดงว่าหมดอายุ
            $isPrbExpired = !$item->prb_expiry_date_status; // ถ้า status เป็น false แสดงว่าหมดอายุ
            $isInsuranceExpired = !$item->insurance_expiry_date_status; // ถ้า status เป็น false แสดงว่าหมดอายุ

            // แสดงผลวันที่สำหรับ tax_renewal_date
            $taxRenewalDate = $item->tax_renewal_date
                ? Carbon::parse($item->tax_renewal_date)->format('d/m/') . (Carbon::parse($item->tax_renewal_date)->year + 543)
                : '-';
            $taxRenewalDisplay = $isTaxExpired
                ? '<strong>' . $taxRenewalDate . '</strong>'
                : $taxRenewalDate;

            // แสดงผลวันที่สำหรับ prb_expiry_date
            $prbExpiryDate = $item->prb_expiry_date
                ? Carbon::parse($item->prb_expiry_date)->format('d/m/') . (Carbon::parse($item->prb_expiry_date)->year + 543)
                : '-';
            $prbExpiryDisplay = $isPrbExpired
                ? '<strong>' . $prbExpiryDate . '</strong>'
                : $prbExpiryDate;

            // แสดงผลวันที่สำหรับ insurance_expiry_date
            $insuranceExpiryDate = $item->insurance_expiry_date
                ? Carbon::parse($item->insurance_expiry_date)->format('d/m/') . (Carbon::parse($item->insurance_expiry_date)->year + 543)
                : '-';
            $insuranceExpiryDisplay = $isInsuranceExpired
                ? '<strong>' . $insuranceExpiryDate . '</strong>'
                : $insuranceExpiryDate;

            // สร้างแถว HTML
            $datefor .= '
                <td align="center">' . $prbExpiryDisplay . '</td>
                <td align="center">' . $insuranceExpiryDisplay . '</td>
                <td align="center">' . $taxRenewalDisplay . '</td>';
        }

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('Document Title');
        $mpdf->AddPage();

        $html = '
        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <td colspan="7" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                        รายงาน ต่ออายุ วันที่ ' . $startDate->format('d/m/') . (Carbon::parse($startDate)->year + 543)  . ' ถึง ' . $endDate->format('d/m/') . (Carbon::parse($startDate)->year + 543) . '
                    </td>
                </tr>
                <tr>
                    <th>ลำดับ</th>
                    <th>ทะเบียน</th>
                    <th>ชื่อลูกค้า</th>
                    <th>เบอร์โทรผู้รับ</th>
                    <th>วันหมดอายุพ.ร.บ.</th>
                    <th>วันหมดอายุประกัน</th>
                    <th>วันหมดอายุภาษี</th>
                </tr>
            </thead>
                <tbody>';
        foreach ($data as $index => $item) {
            $html .= '
            <tr>
                <td align="center">' . ($index + 1) . '</td>
                <td align="center">' . ($item->license_plate ?? '-') . '</td>
                <td align="center">' . ($item->customer_name ?? '-') . '</td>
                <td align="center">' . ($item->phone_number ?: '-') . '</td>
                ' . $datefor . '
            </tr>';
        }
        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
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


    public function tax_report(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate   = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบ');
        }
        // $tax_group_id = array_filter($request->query('tax_group_id', []));
        $tax_group_id = $request->query('tax_group_id', []);
        // dd($tax_group_id);
        // dd ($tax_group_id) ;
        $data = [];
        $data = Inspections::select(
            'inspections.id',
            'inspections.no',
            'inspections.date',
            'inspections.appointment',
            'inspections.total',
            'inspections.paytrue',
            'inspections.tax_vehicle',
            'inspections.remark',
            'inspection_vehicles.license_plate',
            'inspection_vehicles.provinces',
            'inspection_vehicles.type_document',
            'inspection_vehicles.name AS customer_name',
            'inspection_vehicles.phone_number',
            'inspection_vehicles.registration_date',
            'vehicleserviceTransactions.service_price',
            'vehicle_brands.name'
        )
            ->leftJoin('inspection_vehicles', 'inspections.id', '=', 'inspection_vehicles.inspection_id')
            ->leftJoin('vechicle_services_transactions as vehicleserviceTransactions', 'inspection_vehicles.id', '=', 'vehicleserviceTransactions.inspection_vehicle_id')
            ->leftJoin('vehicles', 'inspection_vehicles.vehicle_id', '=', 'vehicles.id')
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->where('vehicleserviceTransactions.service_id', 3)

            ->when($request->has('tax_vehicle') && $request->input('tax_vehicle') !== null, function ($query) use ($request) {
                return $query->where('inspections.tax_vehicle', $request->input('tax_vehicle'));
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('inspections.date', [$startDate, $endDate]);
            });
        if (!empty($tax_group_id)) {
            $data->whereIn('inspections.id', $tax_group_id);
        }
        $data = $data->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'ไม่พบข้อมูล'
            ]);
        }

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 14,
            'fontDir'           => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata'          => $fontData + [
                'th-sarabun' => [
                    'R'  => 'THSarabun.ttf',
                    'I'  => 'THSarabun Italic.ttf',
                    'B'  => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font'      => 'th-sarabun',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'margin_header'     => 5,
            'margin_footer'     => 5,
        ]);

        $mpdf->SetTitle('รายงานนำส่งเพื่อชำระภาษี');
        $mpdf->AddPage();

        $html = '
        <table border="1" style="width:100%; border-collapse: collapse;">
        <thead>
            <tr>
                <td colspan="11" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                รายงานนำส่งเพื่อชำระภาษี ' . $this->convertDateFormat($startDate ?? '') . ' ถึง ' . $this->convertDateFormat($endDate ?? '') . '
                </td>
            </tr>
            <tr>
             <td style="width:8%;text-align: center;">ลำดับ</td>
             <td style="width:8%;text-align: center;">เลขที่</td>
             <td style="width:8%;text-align: center;">ทะเบียน</td>
             <td style="width:8%;text-align: center;">เอกสาร</td>
             <td style="width:8%;text-align: center;">วันที่รับงาน</td>
             <td style="width:8%;text-align: center;">นัดรับป้าย</td>
             <td style="width:8%;text-align: center;">ภาษี</td>
             <td style="width:8%;text-align: center;">เงินเพิ่ม</td>
             <td style="width:8%;text-align: center;">ค่าธรรมเนียม</td>
             <td style="width:8%;text-align: center;">รวม</td>
             <td style="width:8%;text-align: center;">หมายเหตุ</td>
            </tr>
        </thead>
        <tbody>';
        if ($data->isEmpty()) {
            $html .= '<tr><td colspan="11" class="no-data">ไม่มีรายการ</td></tr>';
        } else {
            $index = 1;
            $totalprice = 0;
            $total_service_price = 0;
            $row_count = count($data);
            // $row_count_first_table = 0;
            foreach ($data as $inspection) {
                $type_document_map = [
                    'real' => 'ต้นฉบับ',
                    'copy' => 'สำเนา',
                ];
                $type_document = $inspection->type_document;
                $type_document_th = $type_document_map[$type_document] ?? '-';

                $total_service_price = ($inspection->service_price ?? 0) + 20 + 20;
                $totalprice += $total_service_price;
                $html .= '
                <tr>
                    <td style="text-align: center;">' . $index++ . '</td>
                    <td style="text-align: center;">' . ($inspection->no ?? '-') . '</td>
                    <td style="text-align: center;">' . ($inspection->license_plate ?? '-') . '</td>
                    <td style="text-align: center;">' . $type_document_th . '</td>
                    <td style="text-align: center;">' . $this->convertDateFormat(($inspection->date ?? '-')) . '</td>
                    <td style="text-align: center;">' . $this->convertDateFormat(($inspection->appointment ?? '-')) . '</td>
                    <td style="text-align: right;">' . number_format(($inspection->service_price ?? '-'), 2) . '</td>
                    <td style="text-align: right;">20.00</td>
                    <td style="text-align: right;">20.00</td>
                    <td style="text-align: right;">' . number_format($total_service_price, 2) . '</td>
                    <td style="text-align: center;">' . ($inspection->remark ?? '-') . '</td>
                </tr>';
                // $row_count_first_table++;
            }
            if ($row_count < 5) {
                $remaining_rows = 5 - $row_count;
                for ($i = 0; $i < $remaining_rows; $i++) {
                    $index++;
                    $html .= '
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>';
                }
            }
        }
        $html .= '
        <tr>
            <td colspan="6" style="text-align: right;padding-right: 5px;">รวม</td>
            <td colspan="4" style="text-align: center;">' . number_format($totalprice, 2) . '</td>
            <td></td>

        </tr>';
        $html .= '</tbody></table>';

        // Check if a page break is needed based on the row count of the first table
        if ($row_count >= 17) { // Modify the threshold as needed
            $html .= '<div class="page-break"></div>';
            $mpdf->WriteHTML($html);
            $mpdf->AddPage();
            $html = '
        <table border="1" style="width:100%; border-collapse: collapse;margin-top:20px;">
        <thead>
            <tr>
                <td colspan="11" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                รายงานบันทึกภาษี ' . $this->convertDateFormat($startDate ?? '') . ' ถึง ' . $this->convertDateFormat($endDate ?? '') . '
                </td>
            </tr>
            <tr>
                <td style="text-align: center;">ลำดับ</td>
                <td style="text-align: center;">เลขที่</td>
                <td style="text-align: center;">ทะเบียน</td>
                <td style="text-align: center;">เบอร์โทร</td>
                <td style="text-align: center;">วันรับงาน</td>
                <td style="text-align: center;">เอกสาร</td>
                <td style="text-align: center;">เรียกเก็บ</td>
                <td style="text-align: center;">จ่ายจริง</td>
                <td style="text-align: center;">คืนเงิน</td>
                <td style="text-align: center;">เพิ่มเงิน</td>
                <td style="text-align: center;">หมายเหตุ</td>
            </tr>
        </thead>
        <tbody>';
            //  $table2 .
            if ($data->isEmpty()) {
                $html .= '<tr><td colspan="11" class="no-data">ไม่มีรายการ</td></tr>';
            } else {
                $index2 = 1;
                $paytrueRowCount = 0;
                $totleserviceprice = 0;
                $totlepaytrue = 0;
                $totalDifferenceUnder = 0;
                $totalDifferenceOver = 0;
                foreach ($data as $inspection) {

                    if ($inspection->paytrue && $inspection->paytrue > 0) {
                        $paytrueRowCount++;
                        // $tes[] = $inspection->no;
                        // dd($inspection->no);
                        $type_document_map = [
                            'real' => 'ต้นฉบับ',
                            'copy' => 'สำเนา',
                        ];
                        $type_document = $inspection->type_document;
                        $type_document_th = $type_document_map[$type_document] ?? '-';
                        $totleserviceprice += $inspection->service_price ?? 0;
                        $totlepaytrue += $inspection->paytrue ?? 0;
                        $html .= '
                        <tr>
                            <td style="text-align: center;">' . $index2++ . '</td>
                            <td style="text-align: center;">' . ($inspection->no ?? '-') . '</td>
                            <td style="text-align: center;">' . ($inspection->license_plate ?? '-') . '</td>
                            <td style="text-align: center;">' . ($inspection->phone_number ?? '-') . '</td>
                            <td style="text-align: center;">' . $this->convertDateFormat(($inspection->date ?? '-')) . '</td>
                            <td style="text-align: center;">' . $type_document_th . '</td>
                            <td style="text-align: right;">' . number_format(($inspection->service_price ?? '-'), 2)  . '</td>
                            <td style="text-align: right;">' . number_format(($inspection->paytrue ?? '-'), 2)  . '</td>';

                        if ($inspection->service_price > $inspection->paytrue) {
                            $price = $inspection->service_price - $inspection->paytrue;
                            $totalDifferenceUnder += $price;
                            $html .= '
                    <td style="text-align: right;">' . ($price == 0 ? '-' : number_format($price, 2)) . '</td>
                    <td style="text-align: center;">-</td>';
                        } else if ($inspection->service_price < $inspection->paytrue) {
                            $price = $inspection->paytrue - $inspection->service_price;
                            $totalDifferenceOver += $price;
                            $html .= '
                    <td style="text-align: center;">-</td>
                    <td style="text-align: right;">' . ($price == 0 ? '-' : number_format($price, 2)) . '</td>';
                        } else {
                            $price = $inspection->paytrue - $inspection->service_price;
                            $html .= '
                    <td style="text-align: center;">-</td>
                    <td style="text-align: center;">-</td>';
                        }

                        $html .= '
                    <td style="text-align: center;">' . ($inspection->remark ?? '-') . '</td>
                </tr>';
                    }
                }
                if ($paytrueRowCount <= 5) {
                    $remaining_rows = 5 -  $paytrueRowCount;
                    for ($i = 0; $i < $remaining_rows; $i++) {

                        $html .= '
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>';
                    }
                }
                // หากไม่มีข้อมูลที่ตรงตามเงื่อนไข
                if ($index === 1) {
                    $html .= '<tr><td colspan="11" class="no-data">ไม่มีรายการ</td></tr>';
                }
            }
            $html .= '
        <tr>
            <td colspan="6" style="text-align: right;padding-right: 5px;">รวม</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totleserviceprice, 2) . '</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totlepaytrue, 2) . '</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totalDifferenceUnder, 2) . '</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totalDifferenceOver, 2) . '</td>
            <td></td>
        </tr>
        </tbody>
        </table>
        ';

            $mpdf->WriteHTML($html);
        } else {
            // $mpdf->AddPage();

            // Second table rendering
            $html .= '
        <table border="1" style="width:100%; border-collapse: collapse;margin-top:20px;">
        <thead>
            <tr>
                <td colspan="11" style="border:1px solid #333; font-size: 22px; font-weight: bold;" align="center">
                รายงานบันทึกภาษี วันที่ ' . $this->convertDateFormat($startDate ?? '') . ' ถึง ' . $this->convertDateFormat($endDate ?? '') . '
                </td>
            </tr>
            <tr>
             <td>ลำดับ</td>
             <td>เลขที่</td>
             <td>ทะเบียน</td>
             <td>เบอร์โทร</td>
             <td>วันรับงาน</td>
             <td>เอกสาร</td>
             <td>เรียกเก็บ</td>
             <td>จ่ายจริง</td>
             <td>คืนเงิน</td>
             <td>เพิ่มเงิน</td>
             <td>หมายเหตุ</td>
            </tr>
        </thead>
        <tbody>';
            if ($data->isEmpty()) {
                $html .= '<tr><td colspan="11" class="no-data">ไม่มีรายการ</td></tr>';
            } else {
                $index2 = 1;
                $paytrueRowCount = 0;
                $totleserviceprice = 0;
                $totlepaytrue = 0;
                $totalDifferenceUnder = 0;
                $totalDifferenceOver = 0;
                foreach ($data as $inspection) {

                    if ($inspection->paytrue && $inspection->paytrue > 0) {
                        $paytrueRowCount++;
                        // $tes[] = $inspection->no;
                        // dd($inspection->no);
                        $type_document_map = [
                            'real' => 'ต้นฉบับ',
                            'copy' => 'สำเนา',
                        ];
                        $type_document = $inspection->type_document;
                        $type_document_th = $type_document_map[$type_document] ?? '-';
                        $totleserviceprice += $inspection->service_price ?? 0;
                        $totlepaytrue += $inspection->paytrue ?? 0;
                        $html .= '
                        <tr>
                            <td style="text-align: center;">' . $index2++ . '</td>
                            <td style="text-align: center;">' . ($inspection->no ?? '-') . '</td>
                            <td style="text-align: center;">' . ($inspection->license_plate ?? '-') . '</td>
                            <td style="text-align: center;">' . ($inspection->phone_number ?? '-') . '</td>
                            <td style="text-align: center;">' . $this->convertDateFormat(($inspection->date ?? '-')) . '</td>
                            <td style="text-align: center;">' . $type_document_th . '</td>
                            <td style="text-align: right;">' . number_format(($inspection->service_price ?? '-'), 2)  . '</td>
                            <td style="text-align: right;">' . number_format(($inspection->paytrue ?? '-'), 2)  . '</td>';

                        if ($inspection->service_price > $inspection->paytrue) {
                            $price = $inspection->service_price - $inspection->paytrue;
                            $totalDifferenceUnder += $price;
                            $html .= '
                    <td style="text-align: right;">' . ($price == 0 ? '-' : number_format($price, 2)) . '</td>
                    <td style="text-align: center;">-</td>';
                        } else if ($inspection->service_price < $inspection->paytrue) {
                            $price = $inspection->paytrue - $inspection->service_price;
                            $totalDifferenceOver += $price;
                            $html .= '
                    <td style="text-align: center;">-</td>
                    <td style="text-align: right;">' . ($price == 0 ? '-' : number_format($price, 2)) . '</td>';
                        } else {
                            $price = $inspection->paytrue - $inspection->service_price;
                            $html .= '
                    <td style="text-align: center;">-</td>
                    <td style="text-align: center;">-</td>';
                        }

                        $html .= '
                    <td style="text-align: center;">' . ($inspection->remark ?? '-') . '</td>
                </tr>';
                    }
                }
                if ($paytrueRowCount <= 5) {
                    $remaining_rows = 5 -  $paytrueRowCount;
                    for ($i = 0; $i < $remaining_rows; $i++) {

                        $html .= '
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>';
                    }
                }
                // หากไม่มีข้อมูลที่ตรงตามเงื่อนไข
                if ($index === 1) {
                    $html .= '<tr><td colspan="11" class="no-data">ไม่มีรายการ</td></tr>';
                }
            }
            $html .= '
        <tr>
            <td colspan="6" style="text-align: right;padding-right: 5px;">รวม</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totleserviceprice, 2) . '</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totlepaytrue, 2) . '</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totalDifferenceUnder, 2) . '</td>
            <td style="text-align: right;padding-right: 5px;">' . number_format($totalDifferenceOver, 2) . '</td>
            <td></td>
        </tr>
        </tbody>
        </table>
        ';

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

    public function AllVoucher(Request $request, $id)      //รวมใบสำคัญรับเงิน
    {
        $data = Inspections::with([
            'Branches',
            'Vehicles.VehicleBrands',
            'Vehicles.VehicleInspectionTypes',
            'Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.SubDistricts',
            'Customers.Customer_Addresses.SubDistricts',
            'InspectionVehicles.vehicleServiceTransaction' => function ($query) {
                $query->where('is_vat', 0);
            },
            'InspectionVehicles.vehicleServiceTransaction.service',
            'Installments' => function ($query) {
                $query->where('type', 'nonvat');
            },
        ])->where('id', $id)->first();
        // return $data;
        if (!$data) {
            return $this->returnErrordata('Data Not Found', 404);
        }

        $inspectionVehicles = InspectionVehicles::where('inspection_id', $data->id)
            ->get(); // ดึงข้อมูลทั้งหมดจาก InspectionVehicles ที่มี inspection_id ตรงกับ $data->id

        if ($inspectionVehicles->isEmpty()) {
            return $this->returnErrordata('No Vehicle Records Found', 404);
        }
        $vehicleIds = $inspectionVehicles->pluck('vehicle_id')->toArray();

        $vehicles1 = Vehicles::whereIn('id', $vehicleIds)->get();
        // ตรวจสอบผลลัพธ์
        if ($vehicles1->isEmpty()) {
            return response()->json(['vehicles' => null, 'vehicle_inspection_types' => null], 200);
        }
        // return $vehicles1;
        $vehicleInspectionTypes = VehicleInspectionTypes::whereIn('id', $vehicles1->pluck('vehicle_inspection_types_id'))
            ->get();

        // ตรวจสอบผลลัพธ์
        if ($vehicleInspectionTypes->isEmpty()) {
            return response()->json(['vehicles' => null, 'vehicle_inspection_types' => null], 200);
        }
        $codetypes = $vehicleInspectionTypes->pluck('code', 'type')->toArray();
        $codetypeString = '';
        foreach ($codetypes as $type => $code) {
            $codetypeString .= $type . $code;
        }
        // return $vehicleInspectionTypes;
        // return $codetype;
        $insuranceNames = [];
        $inspectionVehicleIds = [];

        $vehicle = $data->InspectionVehicles[0] ?? null;
        $vehiclePlate = $vehicle->license_plate ? $vehicle->license_plate : null;
        $vehiclePlate_provinces = $vehicle->provinces ? $vehicle->provinces : null;
        $vehicleType = $vehicle->vehicle_inspection_types ? $vehicle->vehicle_inspection_types : null;

        $order_no = $data->no ?? null;

        $customer = $data ? $data->InspectionVehicles[0] : null;
        $customerName = $customer ? $customer->name : null;
        $phoneNumber = $customer ? $customer->phone_number : null;

        $mainAddress = $data->Customers->Customer_Addresses->firstWhere('is_main', 1);
        $addressObj = $mainAddress ?? $data->Customers->Customer_Addresses->first();
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

        if ($request->branche_id) {
            $branche = Branches::where('id', $request->branche_id)
                ->with([
                    'Provinces',
                    'Districts',
                    'SubDistricts',
                ])
                ->first();
            if (!$branche) {
                return $this->returnError('Branches Not Found');
            }
            // return $branche;
            $branche_address = $branche->address ?? null;
            $branche_province_name = $branche->Provinces->name_th_prefix ?? null;
            $branche_district_name = $branche->Districts->name_th_prefix ?? null;
            $branche_subdistrict_name = $branche->SubDistricts->name_th_prefix ?? null;
            $branche_postal_code = $branche->postal_code ?? null;
            $branche_open_date_time = $branche->open_close_time ?? null;
            $branche_open_date = $branche->open_date ?? null;
            $branche_phone = $branche->phone ?? null;
            $branche_image = $branche->logo ?? null;
            $branche_logo = $branche->business_name ?? null;
            $branche_logo1 = $branche->logo ?? null;
            $branche_logo_under = $branche->logo_under ?? null;
        } else {

            $branche_address = $data->Branches->address ?? null;
            $branche_province_name = $data->Branches->Provinces->name_th_prefix ?? null;
            $branche_district_name = $data->Branches->Districts->name_th_prefix ?? null;
            $branche_subdistrict_name = $data->Branches->SubDistricts->name_th_prefix ?? null;
            $branche_postal_code = $branche->postal_code ?? null;
            $branche_open_date_time = $data->Branches->open_close_time ?? null;
            $branche_open_date = $data->Branches->open_date ?? null;
            $branche_phone = $data->Branches->phone ?? null;
            // $branche_image = $data->Branches->logo ?? null;
            $branche_logo = $data->Branches->business_name ?? null;
            $branche_logo1 = $data->Branches->logo ?? null;
            $branche_logo_under = $data->Branches->logo_under ?? null;
        }


        if ($branche_logo1 != null) {
            $branche_image = '<td style="width: 8%; border: none; text-align: left; vertical-align: middle;" colspan="2"><img src="' . $branche_logo1 . '" alt="FIE" align="left" style="width: 90px;"></td>';
            // $branche_image = 'Not Found';
        } else {
            $branche_image = 'Not Found';
        }

        // Formatted Date
        $date = $data->date;
        $formattedDate = Carbon::parse($date)->locale('th')->translatedFormat('d F Y');
        $yeardate = substr($formattedDate, -4);
        //$formattedDate = str_replace((date('Y') + 543), date('Y') + 543, $formattedDate);
        $formattedDate = str_replace($yeardate, $yeardate + 543, $formattedDate);

        // Format appointment date
        $appointment = $data->appointment;
        $formatappointment = Carbon::parse($appointment)->locale('th')->translatedFormat('j M y');
        $year = substr($formatappointment, -2);
        $formatappointment = str_replace($year, $year + 43, $formatappointment);

        // คำนวณยอดรวมและเตรียมข้อมูลการบริการ
        $serviceRows = '';
        // $id_gen = 0;
        $round = 0;
        $pageNumber = 1;

        // // ตรวจสอบว่า $data มี property ServiceTransactions และเป็น object
        $transactionarr =  $data->InspectionVehicles[0]->vehicleServiceTransaction;
        $preparedData = [];
        $totalAmount = 0;
        $id_gen = 0;
        $existingServices = [];
        $transection = [];
        if (isset($transactionarr) && is_iterable($transactionarr)) {
            foreach ($transactionarr as $transactions) {
                $transection[] = $transactions->id;
            }

            foreach ($transactionarr as $transaction) {
                $serviceId = $transaction->service_id ?? null;
                $serviceName = $transaction->service_name ?? null;
                $servicePrice = $transaction->service_price ?? 0;
                $transactionId = $transaction->id ?? null;

                // รวมชื่อประกันกับชื่อบริการ (ถ้ามี)
                $insuranceRenewal = InsurancesRenewals::where('vechicle_services_transaction_id', $transactionId)
                    ->with('insuranceName') // ดึงข้อมูลความสัมพันธ์ insuranceName
                    ->first();

                $insuranceName = $insuranceRenewal->insuranceName->name ?? '';
                $nameWithResname = !empty($insuranceName) ? $serviceName . ' ( ' . $insuranceName  . ' )' : $serviceName;
                if($serviceId == 14){
                    $nameWithResname = $serviceName . ' ( ' . $data->remark  . ' )';
                }elseif($serviceId == 15){
                    $nameWithResname = $serviceName . ' ( ' . $data->remark_transport  . ' )';
                }

                // ตรวจสอบว่าบริการนี้เคยเพิ่มไปแล้วหรือยัง
                if (!in_array($serviceName, $existingServices)) {
                    $id_gen++; // เพิ่ม id_gen

                    $preparedData[] = [
                        'id' => $id_gen,
                        'name' => $nameWithResname,
                        'price' => $servicePrice,
                    ];

                    // ป้องกันข้อมูลซ้ำ
                    $existingServices[] = $serviceName;
                    $totalAmount += $servicePrice; // รวมยอด
                }
            }
        }

        $total_paid_prices = null;
        $change_bef = null;
        $total_after_price = null;

        $statusMessages = [
            'paid_cash' => 'เงินสด : ',
            'paid_tran' => 'เงินโอน : ',
            'credit' => 'บัตรเครดิต : ',
        ];

        $installmentsData = []; // เก็บข้อมูลที่ใช้ในการแสดงผล
        $changeBefore = 0;
        $totalAfterPrice = 0;
        $paidPriceByType = [];
        $totaldiscount = $data->discount;

        // dd ($data->installments);
        // dd ($data);
        if (!empty($data->installments)) {
            $lastTotalAfterPrice = 0;
            $foundNonvat = false;
            foreach ($data->installments as $installment) {
                $paidType = isset($statusMessages[$installment['paid_type']]) ? $statusMessages[$installment['paid_type']] : 'สถานะไม่รู้จัก';
                $paidPrice = $installment['paid_price'];
                // เก็บข้อมูลสำหรับการแสดงผล
                $type = isset($installment['type']) ? $installment['type'] : ''; // กำหนดค่า default หาก type ไม่มี

                // ถ้า type ไม่ใช่ 'nonvat' ให้ข้ามการทำงานนี้
                if ($type !== 'nonvat') {
                    continue;
                } else {
                    $foundNonvat = true;
                }

                // บวกค่าของ paidPrice ตาม paidType
                if (!isset($paidPriceByType[$paidType])) {
                    $paidPriceByType[$paidType] = 0;
                }
                $paidPriceByType[$paidType] += $paidPrice;

                if (isset($paidTypeCount[$paidType])) {
                    $paidTypeCount[$paidType]++;
                } else {
                    $paidTypeCount[$paidType] = 1;
                }

                // คำนวณค่าต่าง ๆ
                $changeBefore += $installment['before_price'];
                // คำนวณยอดคงเหลือหลังการชำระเงิน
                $total_paid_prices += $installment['paid_price'];
                $change_bef += $installment['before_price'];
                $lastTotalAfterPrice = isset($installment['after_price']) ? $installment['after_price'] : 0;
            }
            // if (!$foundNonvat) {
            //     echo "ไม่พบค่า 'nonvat' ในการผ่อนชำระ.";
            // }

            $total_after_price = $lastTotalAfterPrice;
        }


        foreach ($paidPriceByType as $paidType => $totalPaid) {
            $installmentsData[] = [
                'paidType' => $paidType,
                'paidPrice' => $totalPaid,
            ];
        }

        $change_amount =  $total_paid_prices - $change_bef;
        $total = $totalAmount - $totaldiscount;
        $mat = '';
        if (($change_amount) > 0) {
            $mat .= ' ทอน ' . number_format($change_amount, 2) . '</p>';
        }

        $foundIndex0 = false; // ตัวแปรตรวจสอบว่า index 0 มีหรือไม่
        $foundIndex1 = false; // ตัวแปรตรวจสอบว่า index 1 มีหรือไม่
        $foundIndex2 = false; // ตัวแปรตรวจสอบว่า index 2 มีหรือไม่
        $history = ''; // ตัวแปรใหม่สำหรับเก็บ HTML

        foreach ($installmentsData as $index => $installment) {
            $paidType = $installment['paidType'];  // ดึงประเภทการชำระเงิน
            $total_paid_price = $installment['paidPrice'];  // ดึงจำนวนเงินที่ชำระ
            if ($index == 0) {
                $foundIndex0 = true; // ถ้าเจอ index 1 ให้ตั้งค่าเป็น true
            }
            if ($index == 1) {
                $foundIndex1 = true; // ถ้าเจอ index 1 ให้ตั้งค่าเป็น true
            }
            if ($index == 2) {
                $foundIndex2 = true; // ถ้าเจอ index 1 ให้ตั้งค่าเป็น true
            }

            // ตรวจสอบและแสดง `<tr>` ที่ 1 หาก $index = 0
            switch ($index) {
                case 0:
                    $history .= '
                    <tr>
                        <td style="text-align: left; vertical-align: top;" colspan="2">
                            <p>1. ' . $paidType . ' ' . number_format($total_paid_price, 2) . '</p>
                        </td>
                        <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                            <p>_____________</p>
                        </td>
                        <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                            <p><strong>ส่วนลด</strong></p>
                        </td>
                        <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                            <p>' . number_format($totaldiscount, 2) . '</p>
                        </td>
                    </tr>';
                    break;

                case 1:
                    $history .= '
                    <tr>
                        <td style="text-align: left; vertical-align: top;" colspan="2">
                            <p>2. ' . $paidType . ' ' . number_format($total_paid_price, 2) . '</p>
                        </td>
                        <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                            <p>(______________)</p>
                        </td>
                        <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                            <p><strong>ยอดสุทธิ</strong></p>
                        </td>
                        <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                            <p>' . number_format($total, 2) . '</p>
                        </td>
                    </tr>';
                    break;

                case 2:
                    $history .= '
                    <tr>
                        <td style="text-align: left; vertical-align: top;" colspan="2">
                            <p>3. ' . $paidType . ' ' . number_format($total_paid_price, 2) . '</p>
                        </td>
                        <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                            <p>&nbsp;</p>
                        </td>
                        <td style="text-align: right; vertical-align: top;">
                            <p>ชำระแล้ว</p>
                        </td>
                        <td style="text-align: right; vertical-align: top;">
                            <p>' . number_format($total_paid_price, 2) . '</p>
                        </td>
                    </tr>';
                    break;

                default:
                    $history .= '
                    <tr>
                        <td style="text-align: left; vertical-align: top;" colspan="2">
                            <p>&nbsp;</p>
                        </td>
                        <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                            <p>&nbsp;</p>
                        </td>
                        <td style="text-align: right; vertical-align: top;">
                            <p>ชำระแล้ว</p>
                        </td>
                        <td style="text-align: right; vertical-align: top;">
                            <p>' . number_format($total_paid_prices, 2) . '</p>
                        </td>
                    </tr>';
                    break;
            }
        }

        if (!$foundIndex0) {
            $history .= '
            <tr>
                <td style="text-align: left; vertical-align: top;" colspan="2">
                    <p>&nbsp;</p>
                </td>
                <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                    <p>_____________</p>
                </td>
                <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                    <p><strong>ส่วนลด</strong></p>
                </td>
                <td style="border-bottom: 1px solid black; text-align: right; vertical-align: top;">
                    <p>' . number_format($totaldiscount, 2) . '</p>
                </td>
            </tr>';
        }

        if (!$foundIndex1) {
            $history .= '
            <tr>
                <td style="text-align: left; vertical-align: top;" colspan="2">
                    <p>&nbsp;</p>
                </td>
                <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                    <p>(______________)</p>
                </td>
                <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                    <p><strong>ยอดสุทธิ</strong></p>
                </td>
                <td style="border-bottom: 2px double black; text-align: right; vertical-align: top;">
                    <p>' . number_format($total, 2) . '</p>
                </td>
            </tr>';
        }

        if (!$foundIndex2) {
            $history .= '
            <tr>
                <td style="text-align: left; vertical-align: top;" colspan="2">
                    <p>&nbsp;</p>
                </td>
                <td style="text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                    <p>&nbsp;</p>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <p>ชำระแล้ว</p>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <p>' . number_format($total_paid_prices, 2) . '</p>
                </td>
            </tr>';
        }

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-l',
            'default_font_size' => 14,
            'fontDir' => array_merge($fontDirs, [
                base_path() . '/custom/font/directory',
            ]),
            'fontdata' => $fontData + [
                'th-sarabun' => [
                    'R' => 'THSarabun.ttf',
                    'I' => 'THSarabun Italic.ttf',
                    'B' => 'THSarabun Bold.ttf',
                    'BI' => 'THSarabun BoldItalic.ttf',
                ],
            ],
            'default_font' => 'th-sarabun',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 10,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);
        // return $paidTypeCount;

        $mpdf->SetTitle('ใบสำคัญรับเงิน');

        $itemsPerPage = 7;
        $totalItems = count($transactionarr);
        $pages = ceil($totalItems / $itemsPerPage);

        if (!$foundNonvat) {
            $mpdf->AddPage();
            $html = '
            <body>
            <div style="width: 49%; float: left; border-right: 1px dashed black; padding-left: 15px;">

                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;width:300px;">
                                    <h2 style="margin: 5px 0; font-size: 18px; line-height: 1.5;width:290px;white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $branche_logo . '</h2>
                                    <p>' . $branche_address . '</p>
                                    ' . ($branche_open_date_time ? '<p>' . $branche_open_date_time . ' น.' . ($branche_open_date ? ' (' . $branche_open_date . ')' : '') . '</p>' : '') . '
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 21px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>สำเนา</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width: 65%;">
                                    <p>ลูกค้า: ' . $customerName . ' </p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' ' . $vehiclePlate_provinces . '</p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top; width: 35%;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $codetypeString . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>';
            for ($j = 0; $j < 7; $j++) {
                $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td colspan="4" style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                    </tr>';
            }
            $html .= '
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td> ' . $history;

            $html .= '
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p;>รับเงิน ' . number_format($total_paid_prices, 2) . $mat;
            $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>

                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <td style="widht:40%; text-align: center; vertical-align: center;">
                                    <img src="' . $branche_logo_under . '" alt="Branch Logo" style="width: auto; height: 70px;">
                                </td>
                            </tr>
                        </table>
            </div>

            <div style="width: 49%; float: right;">
                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width:300px;">
                                    <h2 style="margin: 5px 0; font-size: 18px; line-height: 1.5;width:290px;white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $branche_logo . '</h2>
                                    <p>' . $branche_address . '</p>
                                    ' . ($branche_open_date_time ? '<p>' . $branche_open_date_time . ' น.' . ($branche_open_date ? ' (' . $branche_open_date . ')' : '') . '</p>' : '') . '
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 21px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>ต้นฉบับ</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width: 65%;">
                                    <p>ลูกค้า: ' . $customerName . '</p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' ' . $vehiclePlate_provinces . '</p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top; width: 35%;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $codetypeString . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>';
            for ($j = 0; $j < 7; $j++) {
                $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td colspan="4" style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                    </tr>';
            }
            $html .= '
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>' . $history;

            $html .= '
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($total_paid_prices, 2) . $mat;
            $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <td style="widht:40%; text-align: center; vertical-align: center;">
                                    <img src="' . $branche_logo_under . '" alt="Branch Logo" style="width: auto; height: 70px;">
                                </td>
                            </tr>
                        </table>
            </div>
        </body>
        </html>';
            $mpdf->WriteHTML($html);
        } else {

            for ($page = 0; $page < $pages; $page++) {
                if ($page > 0) {
                    $mpdf->AddPage();
                }

                $html = '
        <body>
            <div style="width: 49%; float: left; border-right: 1px dashed black; padding-left: 15px;">

                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;width:300px;">
                                    <h2 style="margin: 5px 0; font-size: 18px; line-height: 1.5;width:290px;white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $branche_logo . '</h2>
                                    <p>' . $branche_address . '</p>
                                    ' . ($branche_open_date_time ? '<p>' . $branche_open_date_time . ' น.' . ($branche_open_date ? ' (' . $branche_open_date . ')' : '') . '</p>' : '') . '
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 21px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>สำเนา</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width: 65%;">
                                    <p>ลูกค้า: ' . $customerName . ' </p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' ' . $vehiclePlate_provinces . '</p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top; width: 35%;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $codetypeString . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>';
                for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($preparedData)); $i++) {;
                    $data = $preparedData[$i];
                    $html .= '
                    <tr>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center;">' . $data['id'] . '</td>
                        <td colspan="4" style="text-align: left; border: 1px solid black; border-collapse: collapse;">' . $data['name'] . '</td>
                        <td style="text-align: right; border: 1px solid black; border-collapse: collapse;">' . number_format($data['price'], 2) . '</td>
                    </tr>';
                }
                $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
                for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                    $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td colspan="4" style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                    </tr>';
                }
                $html .= '
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td> ' . $history;

                $html .= '
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p;>รับเงิน ' . number_format($total_paid_prices, 2) . $mat;
                $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>

                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <td style="widht:40%; text-align: center; vertical-align: center;">
                                    <img src="' . $branche_logo_under . '" alt="Branch Logo" style="width: auto; height: 70px;">
                                </td>
                            </tr>
                        </table>
            </div>

            <div style="width: 49%; float: right;">
                        <table style="width: 100%;  margin: 5px;">
                            <tr>
                                ' . $branche_image . '
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width:300px;">
                                    <h2 style="margin: 5px 0; font-size: 18px; line-height: 1.5;width:290px;white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $branche_logo . '</h2>
                                    <p>' . $branche_address . '</p>
                                    ' . ($branche_open_date_time ? '<p>' . $branche_open_date_time . ' น.' . ($branche_open_date ? ' (' . $branche_open_date . ')' : '') . '</p>' : '') . '
                                    <p>' . $branche_phone . '</p>
                                </td>
                                <td style="vertical-align: text-top; border: none; text-align: right; padding: 5px;">
                                    <p style="font-size: 21px;"><strong>ใบสำคัญรับเงิน</strong></p>
                                    <p>ต้นฉบับ</p>
                                    <p style="border: 3px double red;">นัดรับ ' . $formatappointment . '</p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin: 5px;">
                            <tr>
                                <td style="border: none; text-align: left; overflow-wrap: break-word; word-break: break-all; width: 65%;">
                                    <p>ลูกค้า: ' . $customerName . '</p>
                                    <p>ที่อยู่: ' . $address . ' ' . $subdistrictName . ' ' . $districtName . ' ' . $provinceName . ' ' . $postalcode . ' </p>
                                    <p>โทร.: ' . $phoneNumber . ' </p>
                                    <p>ทะเบียน: ' . $vehiclePlate . ' ' . $vehiclePlate_provinces . '</p>
                                </td>
                                <td style="border: none; text-align: left; vertical-align: text-top; width: 35%;">
                                    <p>เลขที่: ' . $order_no . ' </p>
                                    <p>อ้างอิง: </p>
                                    <p>วันที่: ' . $formattedDate . ' </p>
                                    <p>ประเภท: ' . $codetypeString . ' </p>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin: 5px;">
                            <thead>
                                <tr>
                                    <th style="width: 10%; border: 1px solid black; text-align: center;">ลำดับ</th>
                                    <th colspan="4" style="width: 73%; border: 1px solid black; text-align: center;">รายการ</th>
                                    <th style="width: 17%; border: 1px solid black; text-align: center;">จำนวนเงิน</th>
                                </tr>
                            </thead>
                            <tbody>';
                for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, count($preparedData)); $i++) {
                    $data = $preparedData[$i];
                    $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center;">' . $data['id'] . '</td>
                                        <td colspan="4" style="text-align: left; border: 1px solid black; border-collapse: collapse;">' . $data['name'] . '</td>
                                        <td style="text-align: right; border: 1px solid black; border-collapse: collapse;">' . number_format($data['price'], 2) . '</td>
                                    </tr>';
                }
                $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
                for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                    $html .= '
                                    <tr>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td colspan="4" style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                        <td style="border: 1px solid black; border-collapse: collapse;">&nbsp;</td>
                                    </tr>';
                }
                $html .= '
                            </tbody>
                            <tr>
                                <td style="padding-top: 5px; text-align: left; vertical-align: top;" colspan="2">
                                    <p><strong>ประวัติการชำระเงิน</strong></p>
                                </td>
                                <td style="padding-top: 5px; text-align: center; vertical-align: top; border: 1px solid black; border-top: none; border-left: none; border-bottom: none;" colspan="2">
                                    <p><strong>ผู้รับเงิน</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p><strong>ยอดรวม</strong></p>
                                </td>
                                <td style="border-bottom: 1px solid black; padding-top: 5px; text-align: right; vertical-align: top;">
                                    <p>' . number_format($totalAmount, 2) . '</p>
                                </td>' . $history;

                $html .= '
                                <tr>
                                    <td style="text-align: center; vertical-align: top; border: 1px solid black;" colspan="4">
                                        <p>รับเงิน ' . number_format($total_paid_prices, 2) . $mat;
                $html .= '
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">*ค้าง</p>
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <p style="color: red;">' . number_format($total_after_price, 2) . '</p>
                                    </td>
                                </tr>
                            </tr>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <td style="widht:40%; text-align: center; vertical-align: center;">
                                    <img src="' . $branche_logo_under . '" alt="Branch Logo" style="width: auto; height: 70px;">
                                </td>
                            </tr>
                        </table>
            </div>
        </body>
        </html>';
                $mpdf->WriteHTML($html);
            }
        }
        // $mpdf->Output('receipt.pdf', \Mpdf\Output\Destination::INLINE);
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

    public function receiptPDF(Request $request, $id)
    {
        $Invoice = Invoice::where('id', $id)
            ->with([
                'Branches.Provinces',
                'quotation',
                'invoiceServices',
                'invoicePayment',
                'billingInvoice',

            ])->first();


        if (!$Invoice) {
            return response()->json(['message' => 'ไม่พบข้อมูล id ที่ต้องการ']);
        }
        // dd($Invoice['invoiceServices']);

        $invoice_payment_id = $request->invoice_payment_id;
        if(!$invoice_payment_id) {
            return $this->returnError('invoice_payment_id Not Found');
        }
        $invoice_payment = InvoicePayment::where('id', $invoice_payment_id)->first();
        // return $invoice_payment;
        switch ($Invoice->customer_is_headquarter) {
            case 1:
                $headqurter = "สำนักงานใหญ่";
                $chack = "&#9745;";
                break;
            case 0;
                $headqurter = "";
                $chack = "&#9744;";
                break;
            default:
                $headqurter = "";
                $chack = "&#9744;";
                break;
        }

        switch ($Invoice->Branches->is_headquarter) {
            case 1:
                $Bheadqurter = "สำนักงานใหญ่";
                break;
            case 0;
                $Bheadqurter = "";
                break;
            default:
                $Bheadqurter = "";
                break;
        }
        $remark = $Invoice->remark ?? '';
        $checkbox_null = "&#9744;";
        $checkbox_value = "&#9745;";

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
            $branche_tax = $branche->tax_id ?? null;
            $branche_image = $branche->logo ?? null;
            $branche_logo = $branche->business_name ?? null;
        } else {
            $branche_Name = $Invoice->Branches->business_name ?? null;
            $branche_address = $Invoice->Branches->address ?? null;
            $branche_open_date_time = $Invoice->Branches->open_close_time ?? null;
            $branche_open_date = $Invoice->Branches->open_date ?? null;
            $branche_phone = $Invoice->Branches->phone ?? null;
            $branche_tax = $Invoice->Branches->tax_id ?? null;
            $branche_image = $Invoice->Branches->logo ?? null;
            $branche_logo = $Invoice->Branches->business_name ?? null;
        }

        $footvat = '';
        foreach ($Invoice['invoiceServices'] as $data) {
            $grandTotal = $data->invoice->total;
            $lessdicount = $data->invoice->discount;
            $afterdis = $grandTotal - $lessdicount;
            $afterdeposit = $afterdis - 0;
            $aftervat = $data->invoice->tax;
            $total = $data->invoice->total_amount;
        }

        if ($Invoice->type === 'vat') {
            $itemsPerPage = 8;
            $footvat .= '
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 21px;">
                                    <p style="font-size: 12px;">ใบเสร็จรับเงินฉบับนี้จะสมบูรณ์ต่อเมื่อมีลายเซ็นผู้รับมอบอำนาจและลายเซ็นผู้รับเงิน และได้เรียกเก็บเงินตามเช็คเรียบร้อยแล้ว</p>
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;width: auto;height: 21px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">รวมเป็นเงิน</p>
                                        <p style="font-size: 12px;">Gross Amount</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 21px;">
                                    <div>
                                        <p>' . number_format($grandTotal, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 21px;">
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 21px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">หักส่วนลด</p>
                                        <p style="font-size: 12px;">Less Discount</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 21px;">
                                    <div>
                                        <p>' . number_format($lessdicount, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;padding-bottom:1px;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">จำนวนภาษีมูลค่าเพิ่ม 7.00 %</p>
                                        <p style="font-size: 12px;">VAT</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($aftervat, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 21px;">
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 21px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">ยอดสุทธิ</p>
                                        <p style="font-size: 12px;">Net Total</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($total, 2) . '</p>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">ชำระ</p>
                                        <p style="font-size: 12px;">Pay</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($invoice_payment->paid_price, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <p style="font-size: 12px;">ผิด ตก ยกเว้น E. & O.E.</p>
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">ค้าง</p>
                                        <p style="font-size: 12px;">Overdue</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>'.number_format($invoice_payment->after_price, 2).'</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                    <p style="font-size: 18px;">(' . $this->convertToThaiText($invoice_payment->paid_price) . ')</p>
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">จำนวนเงินรวมทั้งสิ้น</p>
                                        <p style="font-size: 12px;">Total Invoice</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($invoice_payment->paid_price, 2) . '</p>
                                    </div>
                                </td>
                            </tr>';
        } else {
            $itemsPerPage = 8;
            $footvat .= '
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                    <p style="font-size: 12px;">ใบเสร็จรับเงินฉบับนี้จะสมบูรณ์ต่อเมื่อมีลายเซ็นผู้รับมอบอำนาจและลายเซ็นผู้รับเงิน และได้เรียกเก็บเงินตามเช็คเรียบร้อยแล้ว</p>
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 15px;">&nbsp;</p>
                                        <p style="font-size: 18px;">รวมเป็นเงิน</p>
                                        <p style="font-size: 12px;">Gross Amount</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($grandTotal, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 5px;">&nbsp;</p>
                                        <p style="font-size: 18px;">หักส่วนลด</p>
                                        <p style="font-size: 12px;">Less Discount</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($lessdicount, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 21px;">
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 21px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">ยอดสุทธิ</p>
                                        <p style="font-size: 12px;">Net Total</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($total, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">ชำระ</p>
                                        <p style="font-size: 12px;">Pay</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($invoice_payment->paid_price, 2) . '</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <p style="font-size: 12px;">ผิด ตก ยกเว้น E. & O.E.</p>
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">ค้าง</p>
                                        <p style="font-size: 12px;">Overdue</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div>
                                        <p>'.number_format($invoice_payment->after_price, 2).'</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center;border: 1px solid #000;border-left:none;border-bottom:none;width: auto;height: 20px;">
                                    <p style="font-size: 18px;">(' . $this->convertToThaiText($invoice_payment->paid_price) . ')</p>
                                </td>
                                <td colspan="2" style="line-height: 0.8;border-top-left-radius: 10px;text-align: left;padding-left:10px;border: 1px solid #000;border-bottom:none;border-top:none;width: auto;height: 20px;">
                                    <div style="line-height: 0.3;padding-bottom:1px;">
                                        <p style="font-size: 18px;">จำนวนเงินรวมทั้งสิ้น</p>
                                        <p style="font-size: 12px;">Total Invoice</p>
                                    </div>
                                </td>

                                <td style="text-align: center;border-top-left-radius: 10px;text-align: right;padding-left:15px;border: 1px solid #000;border-right:none;border-bottom:none;width: auto;height: 20px;">
                                    <div>
                                        <p>' . number_format($invoice_payment->paid_price, 2) . '</p>
                                    </div>
                                </td>
                            </tr>';
        }
        $start = new DateTime($Invoice->date);
        $end = new DateTime($Invoice->due_date);

        $totalday = $start->diff($end);
        $days = $totalday->days;

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
            'default_font_size' => 14,
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 16,
            'margin_bottom' => 5,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->SetTitle('ใบเสร็จรับเงิน');

        $totalItems = count($Invoice['invoiceServices']);
        $pages = ceil($totalItems / $itemsPerPage);

        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                // $mpdf->AddPage('L); สำหรับกระดาษแนวนอน
                $mpdf->AddPage();
            }

            $html = '
    <div style="position: absolute;top:40px;left:30px;">
        <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
    </div>

    <div style="position: absolute;top:30px;left:150px;">
        <h2>' . $branche_Name . '</h2>
        <p style="font-size: 20px;">' . $branche_address . '</p>
        <p style="font-size: 20px;">' . $branche_phone . '</p>
        <p style="font-size: 20px;">&nbsp;</p>
    </div>
    <div style="position: absolute;top:85px;right:40px;text-align:center;line-height: 2;">
        <p style="font-size: 24px;background-color: #ff8000  ;text-align:center; font-weight: bold;padding:2px 80px -8px 80px;border-radius:20px;"><strong>ใบเสร็จรับเงิน</strong></p>
        <p style="font-size: 20px;margin-top:5px;"><strong>ต้นฉบับ</strong></p>
    </div>

    <div style="position: absolute; left: 38px;top:185px; width: 42.6%;padding-left: 15px;line-height: 1; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
        <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
            <p style="font-size: 18px;">ลูกค้า:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $Invoice->customer_no . '</p>
            <p style="font-size: 12px;">customer</p>
        </div>
        <div style="position: absolute;margin-top:-13px;font-weight: bold;">
            <p style="font-size: 16px;">' . $Invoice->customer_name . '</p>
        </div>
        <div style="position: absolute;margin-top:-13px;font-weight: bold; height: 50px;">
            <p style="font-size: 16px;">' . $Invoice->customer_address . ' ' . $Invoice->customer_address2 . '
            </p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">โทร. <strong>' . $Invoice->customer_phone . '</strong></p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">เลขประจำตัวผู้เสียภาษี &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Invoice->customer_tax_id . '</strong></p>
            <p style="font-size: 12px;">Tax ID</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">อ้างอิง</p>
            <p style="font-size: 12px;">Reference</p>
        </div>
    </div>
    <div style="position: absolute;top:295px;left:270px;font-size: 16px;">
        <div>
            <p style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $chack . '</p>
        </div>
    </div>
    <div style="position: absolute;top:312px;left:295px;">
        <label>สำนักงานใหญ่</label>
    </div>';

            $html .= '

    <div style="position: absolute; right: 38px;top:185px; width: 42.6%;padding-left: 20px;line-height: 0.7; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
        <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
            <p style="font-size: 18px;">เลขที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $invoice_payment->no . '</strong> </p>
            <p style="font-size: 12px;">No.</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $this->convertToThaiDate($invoice_payment->date) . '</strong></p>
            <p style="font-size: 12px;">Date</p>
        </div>
        <div style="position: absolute;margin-top: 6px;">
            <p style="font-size: 16px;">&nbsp;</p>
            <p style="font-size: 12px;">&nbsp;</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">เลขที่ใบสั่งขาย</p>
            <p style="font-size: 12px;">Sales Order No.</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">พนักงานขาย &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-</p>
            <p style="font-size: 12px;">Salesman</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">เขตการขาย</p>
            <p style="font-size: 12px;">Territory</p>
        </div>
    </div>

    <div style="position: absolute;top:336px;margin-top:45px;left:39.5px;width:717px;border: 0.5px solid #000;border-top-left-radius: 7px;border-top-right-radius: 7px;background-color:#ff8000;"></div>
    <div style="position: absolute;top:828.7px;margin-top:28.7px;left:39.5px;width:430px;border-bottom-left-radius: 7px;background-color:#ff8000;"></div>
    <div style="position: absolute;top:828.7px;margin-top:28.7px;right:35.5px;width:110px;border-bottom-right-radius: 7px;background-color:#ff8000;"></div>
    <div style="position: absolute;top:380px;left:39px;width:717px;height:505px;border: 1px solid #000;border-radius: 7px;">
        <table style="width: 100%;border-collapse: collapse;">
            <thead>
                <tr>
                    <td style="width: 5%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-left: 10px;overflow: hidden;">ลำดับ<p style="font-size: 14px;">No.</p></td>
                    <td style="width: 45%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">รหัสสินค้า/รายละเอียด<br><p style="font-size: 16px;">Code/Descriptions</p></td>
                    <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">จำนวน<p style="font-size: 16px;">Quantity</p></td>
                    <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">หน่วยละ<p style="font-size: 16px;">Unit Price</p></td>
                    <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">ส่วนลด<p style="font-size: 16px;">Discount</p></td>
                    <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-right:none;height: 30px;border-radius-right: 10px;overflow: hidden;">จำนวนเงิน<p style="font-size: 16px;">Amount</p></td>
                </tr>
            </thead>';

            $service =  $Invoice['invoiceServices'];
            $totalInvoice = count($Invoice['invoiceServices']);
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {
                $data = $service[$i];
                // dd($data);
                $totalitem = ($data->qty * $data->unit_price);
                $servicename = $data->service_name;
                // ตัดข้อความโดยใช้ substr หรือ mb_substr เพื่อรองรับ UTF-8
                $maxLength = 120; // จำนวนตัวอักษรสูงสุด
                $maxLength2 = 80;
                $maxLengthPerLine = 40;

                if (mb_strlen($servicename, 'UTF-8') > $maxLength) {
                    $shortservicename = mb_strcut($servicename, 0, $maxLength, 'UTF-8');
                } else if (mb_strlen($servicename, 'UTF-8') > $maxLengthPerLine) {
                    $shortservicename = mb_strcut($servicename, 0, $maxLength2, 'UTF-8');
                } else {
                    $shortservicename = $servicename;
                }

                $html .= '
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . ($i + 1) . '</td>
                            <td style="text-align: left;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-left:7px;">' . $shortservicename . '</td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . $data->qty . '</td>
                            <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->unit_price, 2) . '</td>
                            <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->discount, 2) . '</td>
                            <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">' . number_format($totalitem, 2) . '</td>
                        </tr>';
            }

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html .= '
                            <tr>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;border-right:none;">&nbsp;</td>
                            </tr>';
            }

            $html .= '
                            <tr>
                                ' . $footvat . '
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="position: absolute;top:890px;left:39px;width:717px;height:190px;border: 1px solid #000;border-radius: 7px;">
                    <table style="width: 100%;border-collapse: collapse;">
                        <tr>
                            <td style="font-size: 18px;width:35%;height:200px;border-right: 1px solid #000;">
                                <div style="text-align: center;">
                                    <p style="font-size: 18px;font-weight: bold;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ได้รับสินค้าตามรายการถูกต้องแล้ว</p><br>
                                </div>
                                <div>
                                    <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                    <p style="font-size: 18px;">&nbsp;ผู้รับสินค้า/ Goods Reccived by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p><br>
                                    <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้ส่งสินค้า/ Delivery by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                </div>
                            </td>
                            <td style="font-size: 16px;width:35%;height:200px;border-right: 1px solid #000;line-height: 1.5;padding-left:10px;">
                                <div>
                                    <p><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></strong><span>&nbsp;เงินสด&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></span><span>&nbsp;เช็ค</span></p>
                                    <p><strong>เช็คธนาคาร___________</strong><span>สาขา___________</span></p>
                                    <p><strong>เลขที่_____________</strong><span>วันที่___/____/______</span></p>
                                    <p><strong>จำนวนเงิน__________________________</p>
                                    <p><strong>ภาษีหัก ณ ที่จ่าย______________________</p>
                                    <p>&nbsp;</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;________________ &nbsp;&nbsp;&nbsp;<span>____/____/____</span></p>
                                    <p style="font-size: 14px;"><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับเงิน/Collector <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</span></strong></p>
                                </div>
                            </td>
                            <td style="width:30%;text-align: center;">
                                <div>
                                    <div style="text-align: left;">
                                        <p style="font-size: 12px;">ในนาม&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                                    </div>
                                    <p style="font-weight: bold;margin-left:5px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $branche_Name . '</p><br>
                                    <p>&nbsp;&nbsp;________________________________</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับมอบอำนาจ/ Authorized Singature</p><br>
                                    <p>&nbsp;&nbsp;________________________________</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้ตรวจเช็คสินค้า/ Approved by</p>
                                </div>
                            </td>
                        </tr>';
            $html .= '</table></div>
        <div style="position: absolute;top:720px;left:60px;width:390px;height:110px;">' . $invoice_payment->description . '</div>';

            $mpdf->WriteHTML($html);
            $mpdf->AddPage();

            $html_copy = '
    <div style="position: absolute;top:40px;left:30px;">
        <img src="' . $branche_image . '" style="width:115px;height:115px;" alt="Icon">
    </div>

    <div style="position: absolute;top:30px;left:150px;">
        <h2>' . $branche_Name . '</h2>
                    <p style="font-size: 20px;">' . $branche_address . '</p>

        <p style="font-size: 20px;">' . $branche_phone . '</p>
        <p style="font-size: 20px;">&nbsp;</p>
    </div>
    <div style="position: absolute;top:85px;right:40px;text-align:center;line-height: 2;">
        <p style="font-size: 24px;background-color: #8ef8a1  ;text-align:center; font-weight: bold;padding:2px 80px -8px 80px;border-radius:20px;"><strong>ใบเสร็จรับเงิน</strong></p>
        <p style="font-size: 20px;margin-top:5px;"><strong>สำเนา</strong></p>
    </div>

    <div style="position: absolute; left: 38px;top:185px; width: 42.6%;padding-left: 15px;line-height: 1; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
        <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
            <p style="font-size: 18px;">ลูกค้า:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $Invoice->customer_no . '</p>
            <p style="font-size: 12px;">customer</p>
        </div>
        <div style="position: absolute;margin-top:-13px;font-weight: bold;">
            <p style="font-size: 16px;">' . $Invoice->customer_name . '</p>
        </div>
        <div style="position: absolute;margin-top:-13px;font-weight: bold; height: 50px;">
            <p style="font-size: 16px;">' . $Invoice->customer_address . ' ' . $Invoice->customer_address2 . '
            </p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">โทร. <strong>' . $Invoice->customer_phone . '</strong></p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">เลขประจำตัวผู้เสียภาษี &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $Invoice->customer_tax_id . '</strong></p>
            <p style="font-size: 12px;">Tax ID</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">อ้างอิง</p>
            <p style="font-size: 12px;">Reference</p>
        </div>
    </div>
    <div style="position: absolute;top:295px;left:270px;font-size: 16px;">
        <div>
            <p style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $chack . '</p>
        </div>
    </div>
    <div style="position: absolute;top:312px;left:295px;">
        <label>สำนักงานใหญ่</label>
    </div>';

            $html_copy .= '
    <div style="position: absolute; right: 38px;top:185px; width: 42.6%;padding-left: 20px;line-height: 0.7; height: 190px;border-radius: 7px;border: 0.5px solid #000;">
        <div style="font-size: 16px;padding-bottom:1px;text-align:left;line-height: 0.7;">
            <p style="font-size: 18px;">เลขที่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $invoice_payment->no . '</strong> </p>
            <p style="font-size: 12px;">No.</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">วันที่&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . $this->convertToThaiDate($Invoice->date) . '</strong></p>
            <p style="font-size: 12px;">Date</p>
        </div>
        <div style="position: absolute;margin-top: 6px;">
            <p style="font-size: 16px;">&nbsp;</p>
            <p style="font-size: 12px;">&nbsp;</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">เลขที่ใบสั่งขาย</p>
            <p style="font-size: 12px;">Sales Order No.</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">พนักงานขาย &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-</p>
            <p style="font-size: 12px;">Salesman</p>
        </div>
        <div style="position: absolute;margin-top:-13px;">
            <p style="font-size: 16px;">เขตการขาย</p>
            <p style="font-size: 12px;">Territory</p>
        </div>
    </div>

    <div style="position: absolute;top:336px;margin-top:45px;left:39.5px;width:717px;border: 0.5px solid #000;border-top-left-radius: 7px;border-top-right-radius: 7px;background-color:#8ef8a1;"></div>
    <div style="position: absolute;top:828.7px;margin-top:28.7px;left:39.5px;width:430px;border-bottom-left-radius: 7px;background-color:#8ef8a1;"></div>
    <div style="position: absolute;top:828.7px;margin-top:28.7px;right:35.5px;width:110px;border-bottom-right-radius: 7px;background-color:#8ef8a1;"></div>
    <div style="position: absolute;top:380px;left:39px;width:717px;height:505px;border: 1px solid #000;border-radius: 7px;">
        <table style="width: 100%;border-collapse: collapse;">
            <thead>
                <tr>
                    <td style="width: 5%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;border-radius-left: 10px;overflow: hidden;">ลำดับ<p style="font-size: 14px;">No.</p></td>
                    <td style="width: 45%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">รหัสสินค้า/รายละเอียด<br><p style="font-size: 16px;">Code/Descriptions</p></td>
                    <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">จำนวน<p style="font-size: 16px;">Quantity</p></td>
                    <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">หน่วยละ<p style="font-size: 16px;">Unit Price</p></td>
                    <td style="width: 10%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;height: 30px;">ส่วนลด<p style="font-size: 16px;">Discount</p></td>
                    <td style="width: 15%;text-align: center;border-bottom: 0.5px solid #000;border-right: 1px solid #000;border-right:none;height: 30px;border-radius-right: 10px;overflow: hidden;border-right:none;">จำนวนเงิน<p style="font-size: 16px;">Amount</p></td>
                </tr>
            </thead>';

            $service =  $Invoice['invoiceServices'];
            $totalInvoice = count($Invoice['invoiceServices']);
            for ($i = $page * $itemsPerPage; $i < min(($page + 1) * $itemsPerPage, $totalItems); $i++) {
                $data = $service[$i];
                $totalitem = ($data->qty * $data->unit_price);
                $servicename = $data->service_name;
                // ตัดข้อความโดยใช้ substr หรือ mb_substr เพื่อรองรับ UTF-8
                $maxLength = 120; // จำนวนตัวอักษรสูงสุด
                $maxLength2 = 80;
                $maxLengthPerLine = 40;

                if (mb_strlen($servicename, 'UTF-8') > $maxLength) {
                    $shortservicename = mb_strcut($servicename, 0, $maxLength, 'UTF-8');
                } else if (mb_strlen($servicename, 'UTF-8') > $maxLengthPerLine) {
                    $shortservicename = mb_strcut($servicename, 0, $maxLength2, 'UTF-8');
                } else {
                    $shortservicename = $servicename;
                }

                $html_copy .= '
                        <tr>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">' . ($i + 1) . '</td>
                            <td style="text-align: left;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-left:7px;">' . $shortservicename . '</td>
                            <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . $data->qty . '</td>
                            <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->unit_price, 2) . '</td>
                            <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;">' . number_format($data->discount, 2) . '</td>
                            <td style="text-align: right;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;padding-right:7px;border-right:none;">' . number_format($totalitem, 2) . '</td>
                        </tr>';
            }

            $emptyRowsNeeded = $itemsPerPage - min($itemsPerPage, $totalItems - $page * $itemsPerPage);
            for ($j = 0; $j < $emptyRowsNeeded; $j++) {
                $html_copy .= '
                            <tr>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;">&nbsp;</td>
                                <td style="text-align: center;border-top-left-radius: 10px;border-right: 1px solid #000;width: auto;height: 33px;border-right:none;">&nbsp;</td>
                            </tr>';
            }

            $html_copy .= '
                            <tr>
                                ' . $footvat . '
                            </tr>';
            $html_copy .= '
                </tbody>
                </table>
            </div>
            <div style="position: absolute;top:890px;left:39px;width:717px;height:190px;border: 1px solid #000;border-radius: 7px;">
                    <table style="width: 100%;border-collapse: collapse;">
                        <tr>
                            <td style="font-size: 18px;width:35%;height:200px;border-right: 1px solid #000;">
                                <div style="text-align: center;">
                                    <p style="font-size: 18px;font-weight: bold;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ได้รับสินค้าตามรายการถูกต้องแล้ว</p><br>
                                </div>
                                <div>
                                    <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                    <p style="font-size: 18px;">&nbsp;ผู้รับสินค้า/ Goods Reccived by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p><br>
                                    <p style="font-size: 18px;">__________________________&nbsp;&nbsp;&nbsp;&nbsp;____/____/____</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้ส่งสินค้า/ Delivery by&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</p>
                                </div>
                            </td>
                            <td style="font-size: 16px;width:35%;height:200px;border-right: 1px solid #000;line-height: 1.5;padding-left:10px;">
                                <div>
                                    <p><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ชำระเงินโดย&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></strong><span>&nbsp;เงินสด&nbsp;&nbsp;<span style="font-family: Arial, sans-serif; font-size: 16px; display: inline-block;">' . $checkbox_null . '</span></span><span>&nbsp;เช็ค</span></p>
                                    <p><strong>เช็คธนาคาร___________</strong><span>สาขา___________</span></p>
                                    <p><strong>เลขที่_____________</strong><span>วันที่___/____/______</span></p>
                                    <p><strong>จำนวนเงิน__________________________</p>
                                    <p><strong>ภาษีหัก ณ ที่จ่าย______________________</p>
                                    <p>&nbsp;</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;________________ &nbsp;&nbsp;&nbsp;<span>____/____/____</span></p>
                                    <p style="font-size: 14px;"><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับเงิน/Collector <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่/Date</span></strong></p>
                                </div>
                            </td>
                            <td style="width:30%;text-align: center;">
                                <div>
                                    <div style="text-align: left;">
                                        <p style="font-size: 12px;">ในนาม&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                                    </div>
                                    <p style="font-weight: bold;margin-left:5px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $branche_Name . '</p><br>
                                    <p>&nbsp;&nbsp;________________________________</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;ผู้รับมอบอำนาจ/ Authorized Singature</p><br>
                                    <p>&nbsp;&nbsp;________________________________</p>
                                    <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ผู้ตรวจเช็คสินค้า/ Approved by</p>
                                </div>
                            </td>
                        </tr>
                    </table>
            </div>
            <div style="position: absolute;top:720px;left:60px;width:390px;height:110px;">' . $invoice_payment->description . '</div>';

            $mpdf->WriteHTML($html_copy);
        }

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
