@if (in_array($tax_vehicle, ['check', 'wait', 'send'], true))
    <table >
        <tr>
            <td colspan="9" style="text-align: center; font-size: 14px; font-weight: bold;">
                @if ($tax_vehicle === 'send')
                    รายงานนำส่งเพื่อชำระภาษี
                @elseif ($tax_vehicle === 'wait')
                    รายงานรอนำส่ง
                @else
                    รายงานตรวจสอบภาษีรถ
                @endif
                 วันที่ {{ $start_date }} - {{ $end_date }}
            </td>
        </tr>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันรับงาน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เอกสาร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">นัดรับป้าย</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ภาษี</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เงินเพิ่ม</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">รวม</th>
        </tr>
        @php
            $No = 1;
            $total_tax = 0;
        @endphp
        @foreach ($data as $num => $dataloop)
            @php
                $iv0 = $dataloop['inspection_vehicles'][0] ?? null;
                $type_document = '';
                if (($iv0['type_document'] ?? '') == 'copy') {
                    $type_document = 'สำเนา';
                } else {
                    $type_document = 'เล่ม';
                }
                $tax = (float) ($dataloop['_tax'] ?? 0);
                $tax_up = (float) ($dataloop['_tax_up'] ?? 0);
                $total_tax += $tax + $tax_up;
            @endphp
            <tr>
                <td style="border: 1px solid black; text-align: center;">{{ $No++ }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $dataloop['no'] ?? '-' }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $iv0['license_plate'] ?? '-' }}</td>
                <td style="border: 1px solid black; text-align: center;">
                    @if(isset($dataloop['date']))
                        @php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        @endphp
                    @else
                        -
                    @endif
                </td>
                <td style="border: 1px solid black; text-align: center;">{{ $type_document }}</td>
                <td style="border: 1px solid black; text-align: center;">
                    @if(isset($dataloop['appointment']))
                        @php
                            $date = date('d/m/', strtotime($dataloop['appointment']));
                            $year = date('Y', strtotime($dataloop['appointment'])) + 543;
                            echo $date . $year;
                        @endphp
                    @else
                        -
                    @endif
                </td>
                <td style="border: 1px solid black; text-align: center;">{{ $tax }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $tax_up }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $tax + $tax_up }}</td>
            </tr>

        @endforeach
        <tr>
            <td colspan="6" style="border: 1px solid black; text-align: right; font-weight: bold;">รวมทั้งสิ้น</td>
            <td colspan="3" style="border: 1px solid black; text-align: center; font-weight: bold;">{{ $total_tax }}</td>
        </tr>
    </table>
@endif
@if ($tax_vehicle == 'complete' || $tax_vehicle == 'success')
    <table >
        <tr>
            <td colspan="13" style="text-align: center; font-size: 14px; font-weight: bold;">รายงานบันทึกภาษี วันที่ {{ $start_date }} - {{ $end_date }}</td>
        </tr>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เบอร์โทร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันรับงาน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">นัดรับป้าย</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เอกสาร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เรียกเก็บ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">จ่ายจริง</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">คืนเงิน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เพิ่มเงิน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าธรรมเนียม</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">สถานะ</th>
        </tr>
        @php
            $No = 1;
            $total_tax = 0;
            $total_paytrue = 0;
            $total_payup = 0 ;
            $total_paydown = 0 ;
            $total_commission = 0;
            $total = 0;
        @endphp
        @foreach ($data as $num => $dataloop)
            @php
                $iv0 = $dataloop['inspection_vehicles'][0] ?? null;
                $taxVehicle = strtolower(trim((string) ($dataloop['tax_vehicle'] ?? '')));
                if($taxVehicle == 'complete'){
                    $status = 'รอรับป้าย';
                    $statusStyle = 'background-color: #ffedd5; color: #9a3412; font-weight: bold;';
                }elseif($taxVehicle == 'success'){
                    $status = 'สำเร็จ';
                    $statusStyle = 'background-color: #d1fae5; color: #065f46; font-weight: bold;';
                }else{
                    $status = '-';
                    $statusStyle = 'background-color: #e5e7eb; color: #374151; font-weight: bold;';
                }
                $type_document = '';
                if (($iv0['type_document'] ?? '') == 'copy') {
                    $type_document = 'สำเนา';
                } else {
                    $type_document = 'เล่ม';
                }

                $tax = (float) ($dataloop['_tax_billed'] ?? 0);
                $payRefund = (float) ($dataloop['_pay_refund'] ?? 0);
                $payExtra = (float) ($dataloop['_pay_extra'] ?? 0);

                $total_tax += $tax;
                $total_paytrue += (float) ($dataloop['paytrue'] ?? 0);
                $total_commission += (float) ($dataloop['commission'] ?? 0);
                $total_payup += $payRefund;
                $total_paydown += $payExtra;
                $total += (float) ($dataloop['paytrue'] ?? 0) + (float) ($dataloop['commission'] ?? 0);
            @endphp
            <tr>
                <td style="border: 1px solid black; text-align: center;">{{ $No++ }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $dataloop['no'] ?? '-' }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $iv0['license_plate'] ?? '-' }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $iv0['phone_number'] ?? '-' }}</td>
                <td style="border: 1px solid black; text-align: center;">
                    @if(isset($dataloop['date']))
                        @php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        @endphp
                    @else
                        -
                    @endif
                </td>
                <td style="border: 1px solid black; text-align: center;">
                    @if(isset($dataloop['appointment']))
                        @php
                            $date = date('d/m/', strtotime($dataloop['appointment']));
                            $year = date('Y', strtotime($dataloop['appointment'])) + 543;
                            echo $date . $year;
                        @endphp
                    @else
                        -
                    @endif
                </td>
                <td style="border: 1px solid black; text-align: center;">{{ $type_document }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $tax }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $dataloop['paytrue'] ?? 0 }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $payRefund }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $payExtra }}</td>
                <td style="border: 1px solid black; text-align: center;">{{ $dataloop['commission'] ?? 0 }}</td>
                <td style="border: 1px solid black; text-align: center; {{ $statusStyle }}">{{ $status }}</td>
            </tr>

        @endforeach
        <tr>
            <td colspan="7" style="border: 1px solid black; text-align: right; font-weight: bold;">รวม</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;">{{ $total_tax }}</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;">{{ $total_paytrue }}</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;">{{ $total_payup }}</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;">{{ $total_paydown }}</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;">{{ $total_commission }}</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"></td>
        </tr>
        <tr>
            <td colspan="7" style="border: 1px solid black; text-align: right; font-weight: bold;">ยอดรวมจ่าย</td>
            <td colspan="6" style="border: 1px solid black; text-align: center; font-weight: bold;">{{ $total }}</td>
        </tr>
    </table>
@endif
