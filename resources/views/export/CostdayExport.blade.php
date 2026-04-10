<table>
    <tr>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 300px;">ชื่อลูกค้า</th>
        <th colspan="16" style="border: 1px solid black; text-align: center; width: 100px;">บริการ</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">ส่วนลด ก่อนคิด vat</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">ส่วนลด หลังคิด vat</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">ส่วนลด non-vat</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">สุทธิ vat</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">สุทธิ non-vat</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; width: 100px;">สุทธิ</th>
    </tr>
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ตรวจสภาพรถ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">พ.ร.บ.</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ภาษีประจำปี</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เงินเพิ่มภาษี</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">บริการต่อภาษี</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าปรับ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ประกัน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ตรวจสภาพ LPG</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ตรวจติดตั้ง LPG</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ตรวจต่ออายุ LPG</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ตรวจสภาพ NGV</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ตรวจติดตั้ง NGV</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">หนังสือรับรองวิศวกร</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ขนส่ง</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">อื่นๆ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">EMS</th>
    </tr>
    <tr>
        <td colspan="26" style="text-align: left;background-color: red;">เงินสด</td>
    </tr>
    @php
        $No = 1;
        $columnTotals = array_fill(0, 16, 0);

        $totalDiscountBeforeVAT = 0;
        $totalDiscountAfterVAT = 0;
        $totalDiscount = 0;
        $totalVatcal = 0;
        $totalNonvatcal = 0;
        $total = 0;
        $allcashtotal = 0;
    @endphp
    @foreach ($data['inspecpaid_cash'] as $num => $dataloop)
        {{-- {{dd ($dataloop)}} --}}
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td style="text-align: center;">{{ $dataloop['no'] ?? '-' }}</td>
            <td style="text-align: center;">{{ $dataloop['license_plate'] ?? '-' }}</td>
            <td style="text-align: center;">{{ $dataloop['name'] ?? '-' }}</td>

            @foreach ($dataloop['services'] as $index => $service)
                <td style="text-align: center;">
                    {{ isset($service->amount) && $service->amount > 0 ? $service->amount : '-' }}
                    @php
                        if (isset($service->amount) && $service->amount > 0) {
                            $columnTotals[$index] = isset($columnTotals[$index])
                                ? $columnTotals[$index] + $service->amount
                                : $service->amount;
                        }
                    @endphp
                </td>
            @endforeach

            {{-- Fill remaining columns with empty values if less than 16 services --}}
            @for ($i = count($dataloop['services']); $i < 16; $i++)
                <td style="text-align: center;">-</td>
            @endfor
            <td style="text-align: right;">
                {{ isset($dataloop['discount_before_vat']) && $dataloop['discount_before_vat'] > 0 ? $dataloop['discount_before_vat'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['discount_after_vat']) && $dataloop['discount_after_vat'] > 0 ? $dataloop['discount_after_vat'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['discount']) && $dataloop['discount'] > 0 ? $dataloop['discount'] : 0 }}</td>

            {{-- Total discount or value calculation --}}
            <td style="text-align: right;">
                {{ isset($dataloop['total_vatcal']) && $dataloop['total_vatcal'] > 0 ? $dataloop['total_vatcal'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['total_nonvatcal']) && $dataloop['total_nonvatcal'] > 0 ? $dataloop['total_nonvatcal'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ (isset($dataloop['total_vatcal']) ? $dataloop['total_vatcal'] : 0) + (isset($dataloop['total_nonvatcal']) ? $dataloop['total_nonvatcal'] : 0) }}
            </td>

            @php
                // สะสมค่าผลรวมของ discount และ total
                $totalDiscountBeforeVAT += $dataloop['discount_before_vat'] ?? 0;
                $totalDiscountAfterVAT += $dataloop['discount_after_vat'] ?? 0;
                $totalDiscount += $dataloop['discount'] ?? 0;
                $totalVatcal += $dataloop['total_vatcal'] ?? 0;
                $totalNonvatcal += $dataloop['total_nonvatcal'] ?? 0;
                $allcashtotal +=
                    (isset($dataloop['total_vatcal']) ? $dataloop['total_vatcal'] : 0) +
                    (isset($dataloop['total_nonvatcal']) ? $dataloop['total_nonvatcal'] : 0);
            @endphp
        </tr>
    @endforeach
    <tr>
        <td colspan="4" style="text-align: right;">ยอดรวม</td>
        @php
            $totalColumn = 0;
        @endphp
        @foreach ($columnTotals as $total)
            <td style="text-align: right;">{{ $total > 0 ? $total : 0 }}</td>
            @php
                $totalColumn += $total > 0 ? $total : 0;
            @endphp
        @endforeach
        <td style="text-align: right;">
            {{ $totalDiscountBeforeVAT > 0 ? $totalDiscountBeforeVAT : 0 }}</td>
        <td style="text-align: right;">
            {{ $totalDiscountAfterVAT > 0 ? $totalDiscountAfterVAT : 0 }}</td>
        <td style="text-align: right;">{{ $totalDiscount > 0 ? $totalDiscount : 0 }}</td>
        <td style="text-align: right;">{{ $totalVatcal > 0 ? $totalVatcal : 0 }}</td>
        <td style="text-align: right;">{{ $totalNonvatcal > 0 ? $totalNonvatcal : 0 }}</td>
        <td style="text-align: right;">{{ $allcashtotal > 0 ? $allcashtotal : 0 }}</td>
    </tr>
    <tr>
        <td colspan="26" style="text-align: left;background-color:blue;">รายการค่าใช้จ่าย</td>
    </tr>
    <tr>
        <td>ลำดับ</td>
        <td colspan="3" style="text-align: center;">รายการ</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>รายจ่าย</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalIncome = 0;
        $totalExpense = 0;
    @endphp
    @foreach ($costs['cashIncome'] as $cashIncome => $cashIncomeloop)
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td colspan="3" style="text-align: left;">{{ $cashIncomeloop['name'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $cashIncomeloop['price'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $cashIncomeloop['price'] ?? ' ' }}</td>
        </tr>
        @php
            $totalIncome += $cashIncomeloop['price'] ?? 0;
        @endphp
    @endforeach
    @foreach ($costs['cashExpense'] as $cashExpense => $cashExpenseloop)
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td colspan="3" style="text-align: left;">{{ $cashExpenseloop['name'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $cashExpenseloop['price'] ?? ' ' }}</td>
            <td style="text-align: center;">{{ $cashExpenseloop['price'] ?? ' ' }}</td>
        </tr>
        @php
            $totalExpense += $cashExpenseloop['price'] ?? 0;
        @endphp
    @endforeach
    @php
        // $netTotal = $allcashtotal + ($totalIncome - $totalExpense);

        if ($totalIncome > $totalExpense) {
            // ถ้า totalIncome มากกว่า totalExpense
            $netTotalcash = $allcashtotal + ($totalIncome - $totalExpense);
        } elseif ($totalIncome < $totalExpense) {
            // ถ้า totalIncome น้อยกว่า totalExpense
            $netTotalcash = $allcashtotal - ($totalExpense - $totalIncome);
        } else {
            // ถ้า totalIncome เท่ากับ totalExpense
            $netTotalcash = $allcashtotal; // ไม่มีการเปลี่ยนแปลง
        }
    @endphp
    <tr style="font-weight: bold; background-color: #f1f1f1;">
        <td colspan="23" style="text-align: right;">รวม</td>
        <td style="text-align: center;">{{ $totalIncome }}</td>
        <td style="text-align: center;">{{ $totalExpense }}</td>
        <td style="text-align: center;">{{ $netTotalcash }}</td>
    </tr>
    <tr>
        <td>ลำดับ</td>
        <td>เลขที่</td>
        <td colspan="2" style="text-align: center;">ชื่อลูกค้า</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalinvoices = 0;

    @endphp
    @php
        $processedNos = [];
        $paidTotalPrice = 0;
        $paidPricetotal = 0;
    @endphp
    @foreach ($invoices1['Invoicepaid_cash'] as $transferinvoices => $transferinvoicesloop)
        @if (!in_array($transferinvoicesloop['no'], $processedNos))
            @php
                $processedNos[] = $transferinvoicesloop['no'];
                // $paidPricetotal = 0;
                $columnTotalstran = [];

            @endphp
            <tr>
                <td style="text-align: center;">{{ $No++ }}</td>
                <td style="text-align: center;">{{ $transferinvoicesloop['no'] ?? ' ' }}</td>
                <td colspan="2" style="text-align: left;">{{ $transferinvoicesloop['name'] ?? ' ' }}</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                @foreach ($transferinvoicesloop['paid_type1']['paid_cash'] as $index => $paidtype)
                    @if ($paidtype['paid_type'] === 'paid_cash')
                        @php
                            // $paidPrice = isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0 ? $paidtype['paid_price'] : '-';
                            // $paidPricetotal += $paidPrice;

                            $paidPrice =
                                isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0
                                    ? $paidtype['paid_price']
                                    : 0;
                            $paidPricetotal += $paidPrice; // สะสมค่าทั้งหมด
                            $columnTotalstran[$index] = isset($columnTotalstran[$index])
                                ? $columnTotalstran[$index] + $paidPrice
                                : $paidPrice;
                        @endphp
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        @if ($loop->remaining > 0)
            </tr>
            <tr>
                <td colspan="24"></td>
            @else
        @endif
    @endif
    @endforeach

    </tr>
    @endif
    @endforeach
    @php
        $paidTotalPrice += $paidPricetotal;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวม</td>
        <td style="text-align: center;">{{ $paidTotalPrice }}</td>
    </tr>
    @php
        $netTotalcash += $paidTotalPrice;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวมสุทธิ</td>
        <td style="text-align: center;">{{ $netTotalcash }}</td>
    </tr>

    <tr>
        <td colspan="26" style="text-align: left;background-color: green;">บัตรเครดิต</td>
    </tr>
    @php
        $No = 1;
        $columnTotalsCredit = array_fill(0, 16, 0);

        $totalDiscountBeforeVATCredit = 0;
        $totalDiscountAfterVATCredit = 0;
        $totalDiscountCredit = 0;
        $totalVatcalCredit = 0;
        $totalNonvatcalCredit = 0;
        $totalCredit = 0;
        $allcredittotal = 0;
    @endphp
    @foreach ($data['inspeccredit'] as $num => $dataloop)
        {{-- {{dd ($dataloop)}} --}}
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td style="text-align: center;">{{ $dataloop['no'] ?? '-' }}</td>
            <td style="text-align: center;">{{ $dataloop['license_plate'] ?? '-' }}</td>
            <td style="text-align: center;">{{ $dataloop['name'] ?? '-' }}</td>

            @foreach ($dataloop['services'] as $index => $service)
                <td style="text-align: center;">
                    {{ isset($service->amount) && $service->amount > 0 ? $service->amount : '-' }}
                    @php
                        if (isset($service->amount) && $service->amount > 0) {
                            $columnTotals[$index] = isset($columnTotals[$index])
                                ? $columnTotals[$index] + $service->amount
                                : $service->amount;
                        }
                    @endphp
                </td>
            @endforeach

            {{-- Fill remaining columns with empty values if less than 16 services --}}
            @for ($i = count($dataloop['services']); $i < 16; $i++)
                <td style="text-align: center;">-</td>
            @endfor
            <td style="text-align: right;">
                {{ isset($dataloop['discount_before_vat']) && $dataloop['discount_before_vat'] > 0 ? $dataloop['discount_before_vat'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['discount_after_vat']) && $dataloop['discount_after_vat'] > 0 ? $dataloop['discount_after_vat'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['discount']) && $dataloop['discount'] > 0 ? $dataloop['discount'] : 0 }}</td>

            {{-- Total discount or value calculation --}}
            <td style="text-align: right;">
                {{ isset($dataloop['total_vatcal']) && $dataloop['total_vatcal'] > 0 ? $dataloop['total_vatcal'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['total_nonvatcal']) && $dataloop['total_nonvatcal'] > 0 ? $dataloop['total_nonvatcal'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ (isset($dataloop['total_vatcal']) ? $dataloop['total_vatcal'] : 0) + (isset($dataloop['total_nonvatcal']) ? $dataloop['total_nonvatcal'] : 0) }}
            </td>

            @php
                // สะสมค่าผลรวมของ discount และ total
                $totalDiscountBeforeVATCredit += $dataloop['discount_before_vat'] ?? 0;
                $totalDiscountAfterVATCredit += $dataloop['discount_after_vat'] ?? 0;
                $totalDiscountCredit += $dataloop['discount'] ?? 0;
                $totalVatcalCredit += $dataloop['total_vatcal'] ?? 0;
                $totalNonvatcalCredit += $dataloop['total_nonvatcal'] ?? 0;
                $allcredittotal +=
                    (isset($dataloop['total_vatcal']) ? $dataloop['total_vatcal'] : 0) +
                    (isset($dataloop['total_nonvatcal']) ? $dataloop['total_nonvatcal'] : 0);
            @endphp
        </tr>
    @endforeach
    <tr>
        <td colspan="4" style="text-align: right;">ยอดรวม</td>
        @php
            $totalColumnSumCredit = 0;
        @endphp
        @foreach ($columnTotalsCredit as $total)
            <td style="text-align: right;">{{ $total > 0 ? $total : 0 }}</td>
            @php
                $totalColumnSumCredit += $total > 0 ? $total : 0;
            @endphp
        @endforeach
        <td style="text-align: right;">
            {{ $totalDiscountBeforeVATCredit > 0 ? $totalDiscountBeforeVATCredit : 0 }}</td>
        <td style="text-align: right;">
            {{ $totalDiscountAfterVATCredit > 0 ? $totalDiscountAfterVATCredit : 0 }}</td>
        <td style="text-align: right;">{{ $totalDiscountCredit > 0 ? $totalDiscountCredit : 0 }}</td>
        <td style="text-align: right;">{{ $totalVatcalCredit > 0 ? $totalVatcalCredit : 0 }}</td>
        <td style="text-align: right;">{{ $totalNonvatcalCredit > 0 ? $totalNonvatcalCredit : 0 }}</td>
        <td style="text-align: right;">{{ $allcredittotal > 0 ? $allcredittotal : 0 }}</td>
    </tr>
    {{-- <tr>
        <td colspan="26" style="text-align: left; background-color: #fbff11;">บัตรเครดิต</td>
    </tr> --}}
    <tr>
        <td colspan="26" style="text-align: left;background-color:blue;">รายการค่าใช้จ่าย</td>
    </tr>
    <tr>
        <td>ลำดับ</td>
        <td colspan="3" style="text-align: center;">รายการ</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>รายจ่าย</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalIncome = 0;
        $totalExpense = 0;
    @endphp
    @foreach ($costs['creditIncome'] as $creditIncome => $creditIncomeloop)
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td colspan="3" style="text-align: left;">{{ $creditIncomeloop['name'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $creditIncomeloop['price'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $creditIncomeloop['price'] ?? ' ' }}</td>
        </tr>
        @php
            $totalIncome += $creditIncomeloop['price'] ?? 0;
        @endphp
    @endforeach
    @foreach ($costs['creditExpense'] as $creditExpense => $creditExpenseloop)
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td colspan="3" style="text-align: left;">{{ $creditExpenseloop['name'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $creditExpenseloop['price'] ?? ' ' }}</td>
            <td style="text-align: center;">{{ $creditExpenseloop['price'] ?? ' ' }}</td>
        </tr>
        @php
            $totalExpense += $creditExpenseloop['price'] ?? 0;
        @endphp
    @endforeach
    @php
        // $netTotal = $allcredittotal - ($totalIncome - $totalExpense);

        if ($totalIncome > $totalExpense) {
            // ถ้า totalIncome มากกว่า totalExpense
            $netTotalcredit = $allcredittotal + ($totalIncome - $totalExpense);
        } elseif ($totalIncome < $totalExpense) {
            // ถ้า totalIncome น้อยกว่า totalExpense
            $netTotalcredit = $allcredittotal - ($totalExpense - $totalIncome);
        } else {
            // ถ้า totalIncome เท่ากับ totalExpense
            $netTotalcredit = $allcredittotal; // ไม่มีการเปลี่ยนแปลง
        }
    @endphp
    <tr style="font-weight: bold; background-color: #f1f1f1;">
        <td colspan="23" style="text-align: right;">รวม</td>
        <td style="text-align: center;">{{ $totalIncome }}</td>
        <td style="text-align: center;">{{ $totalExpense }}</td>
        <td style="text-align: center;">{{ $netTotalcredit }}</td>
    </tr>
    <tr>
        <td>ลำดับ</td>
        <td>เลขที่</td>
        <td colspan="2" style="text-align: center;">ชื่อลูกค้า</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalinvoices = 0;

    @endphp
    @php
        $processedNos = [];
        $paidTotalPrice = 0;
        $paidPricetotal = 0;
    @endphp
    @foreach ($invoices1['Invoicecredit'] as $transferinvoices => $transferinvoicesloop)
        @if (!in_array($transferinvoicesloop['no'], $processedNos))
            @php
                $processedNos[] = $transferinvoicesloop['no'];
                // $paidPricetotal = 0;
                $columnTotalstran = [];

            @endphp
            <tr>
                <td style="text-align: center;">{{ $No++ }}</td>
                <td style="text-align: center;">{{ $transferinvoicesloop['no'] ?? ' ' }}</td>
                <td colspan="2" style="text-align: left;">{{ $transferinvoicesloop['name'] ?? ' ' }}</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                @foreach ($transferinvoicesloop['paid_type1']['credit'] as $index => $paidtype)
                    @if ($paidtype['paid_type'] === 'credit')
                        @php
                            // $paidPrice = isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0 ? $paidtype['paid_price'] : '-';
                            // $paidPricetotal += $paidPrice;

                            $paidPrice =
                                isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0
                                    ? $paidtype['paid_price']
                                    : 0;
                            $paidPricetotal += $paidPrice; // สะสมค่าทั้งหมด
                            $columnTotalstran[$index] = isset($columnTotalstran[$index])
                                ? $columnTotalstran[$index] + $paidPrice
                                : $paidPrice;
                        @endphp
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        @if ($loop->remaining > 0)
            </tr>
            <tr>
                <td colspan="24"></td>
            @else
        @endif
    @endif
    @endforeach

    </tr>
    @endif
    @endforeach
    @php
        $paidTotalPrice += $paidPricetotal;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวม</td>
        <td style="text-align: center;">{{ $paidTotalPrice }}</td>
    </tr>
    @php
        $netTotalcredit += $paidTotalPrice;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวมสุทธิ</td>
        <td style="text-align: center;">{{ $netTotalcredit }}</td>
    </tr>

    <tr>
        <td colspan="26" style="text-align: left;background-color: pink;">เงินโอน</td>
    </tr>
    @php
        $No = 1;
        $columnTotalstran = array_fill(0, 16, 0);

        $totalDiscountBeforeVATtran = 0;
        $totalDiscountAfterVATtran = 0;
        $totalDiscounttran = 0;
        $totalVatcaltran = 0;
        $totalNonvatcaltran = 0;
        $totaltran = 0;
        $alltotaltran = 0;

    @endphp
    @foreach ($data['inspecpaid_tran'] as $num => $dataloop)
        {{-- {{dd ($dataloop)}} --}}
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td style="text-align: center;">{{ $dataloop['no'] ?? '-' }}</td>
            <td style="text-align: center;">{{ $dataloop['license_plate'] ?? '-' }}</td>
            <td style="text-align: center;">{{ $dataloop['name'] ?? '-' }}</td>

            @foreach ($dataloop['services'] as $index => $service)
                <td style="text-align: center;">
                    {{ isset($service->amount) && $service->amount > 0 ? $service->amount : '-' }}
                    @php
                        if (isset($service->amount) && $service->amount > 0) {
                            $columnTotalstran[$index] = isset($columnTotalstran[$index])
                                ? $columnTotalstran[$index] + $service->amount
                                : $service->amount;
                        }
                    @endphp
                </td>
            @endforeach

            {{-- Fill remaining columns with empty values if less than 16 services --}}
            @for ($i = count($dataloop['services']); $i < 16; $i++)
                <td style="text-align: center;">-</td>
            @endfor
            <td style="text-align: right;">
                {{ isset($dataloop['discount_before_vat']) && $dataloop['discount_before_vat'] > 0 ? $dataloop['discount_before_vat'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['discount_after_vat']) && $dataloop['discount_after_vat'] > 0 ? $dataloop['discount_after_vat'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['discount']) && $dataloop['discount'] > 0 ? $dataloop['discount'] : 0 }}</td>

            {{-- Total discount or value calculation --}}
            <td style="text-align: right;">
                {{ isset($dataloop['total_vatcal']) && $dataloop['total_vatcal'] > 0 ? $dataloop['total_vatcal'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ isset($dataloop['total_nonvatcal']) && $dataloop['total_nonvatcal'] > 0 ? $dataloop['total_nonvatcal'] : 0 }}
            </td>
            <td style="text-align: right;">
                {{ (isset($dataloop['total_vatcal']) ? $dataloop['total_vatcal'] : 0) + (isset($dataloop['total_nonvatcal']) ? $dataloop['total_nonvatcal'] : 0) }}
            </td>

            @php
                // สะสมค่าผลรวมของ discount และ total
                $totalDiscountBeforeVATtran += $dataloop['discount_before_vat'] ?? 0;
                $totalDiscountAfterVATtran += $dataloop['discount_after_vat'] ?? 0;
                $totalDiscounttran += $dataloop['discount'] ?? 0;
                $totalVatcaltran += $dataloop['total_vatcal'] ?? 0;
                $totalNonvatcaltran += $dataloop['total_nonvatcal'] ?? 0;
                $alltotaltran +=
                    (isset($dataloop['total_vatcal']) ? $dataloop['total_vatcal'] : 0) +
                    (isset($dataloop['total_nonvatcal']) ? $dataloop['total_nonvatcal'] : 0);
            @endphp
        </tr>
    @endforeach
    <tr>
        <td colspan="4" style="text-align: right;">ยอดรวม</td>
        @php
            $totalColumnSumtran = 0;
        @endphp
        @foreach ($columnTotalstran as $total)
            <td style="text-align: right;">{{ $total > 0 ? $total : 0 }}</td>
            @php
                $totalColumnSumtran += $total > 0 ? $total : 0;
            @endphp
        @endforeach
        <td style="text-align: right;">
            {{ $totalDiscountBeforeVATtran > 0 ? $totalDiscountBeforeVATtran : 0 }}</td>
        <td style="text-align: right;">
            {{ $totalDiscountAfterVATtran > 0 ? $totalDiscountAfterVATtran : 0 }}</td>
        <td style="text-align: right;">{{ $totalDiscounttran > 0 ? $totalDiscounttran : 0 }}</td>
        <td style="text-align: right;">{{ $totalVatcaltran > 0 ? $totalVatcaltran : 0 }}</td>
        <td style="text-align: right;">{{ $totalNonvatcaltran > 0 ? $totalNonvatcaltran : 0 }}</td>
        <td style="text-align: right;">{{ $alltotaltran > 0 ? $alltotaltran : 0 }}</td>
    </tr>
    <tr>
        <td colspan="4" style="text-align: right;">ยอดรวมสุทธิ</td>
        @foreach (range(0, 15) as $index)
            <td style="text-align: right;">
                {{ ($columnTotals[$index] ?? 0) + ($columnTotalsCredit[$index] ?? 0) + ($columnTotalstran[$index] ?? 0) }}
            </td>
        @endforeach
        <td style="text-align: right;">
            {{ $totalDiscountBeforeVAT + $totalDiscountBeforeVATCredit + $totalDiscountBeforeVATtran }}</td>
        <td style="text-align: right;">
            {{ $totalDiscountAfterVAT + $totalDiscountAfterVATCredit + $totalDiscountAfterVATtran }}</td>
        <td style="text-align: right;">{{ $totalDiscount + $totalDiscountCredit + $totalDiscounttran }}</td>
        <td style="text-align: right;">{{ $totalVatcal + $totalVatcalCredit + $totalVatcaltran }}</td>
        <td style="text-align: right;">{{ $totalNonvatcal + $totalNonvatcalCredit + $totalNonvatcaltran }}</td>
        <td style="text-align: right;">{{ $allcashtotal + $allcredittotal + $alltotaltran }}</td>

    </tr>

    {{-- <tr>
        <td colspan="26" style="text-align: left;background-color: #FF2CD1;">เงินโอน</td>
    </tr> --}}
    <tr>
        <td colspan="26" style="text-align: left;background-color:blue;">รายการค่าใช้จ่าย</td>
    </tr>
    <tr>
        <td>ลำดับ</td>
        <td colspan="3" style="text-align: center;">รายการ</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>รายจ่าย</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalIncome = 0;
        $totalExpense = 0;
    @endphp
    @foreach ($costs['transferIncome'] as $transferIncome => $transferIncomeloop)
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td colspan="3" style="text-align: left;">{{ $transferIncomeloop['name'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $transferIncomeloop['price'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $transferIncomeloop['price'] ?? ' ' }}</td>
        </tr>
        @php
            $totalIncome += $transferIncomeloop['price'] ?? 0;
        @endphp
    @endforeach
    @foreach ($costs['transferExpense'] as $transferExpense => $transferExpenseloop)
        <tr>
            <td style="text-align: center;">{{ $No++ }}</td>
            <td colspan="3" style="text-align: left;">{{ $transferExpenseloop['name'] ?? ' ' }}</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">-</td>
            <td style="text-align: center;">{{ $transferExpenseloop['price'] ?? ' ' }}</td>
            <td style="text-align: center;">{{ $transferExpenseloop['price'] ?? ' ' }}</td>
        </tr>
        @php
            $totalExpense += $transferExpenseloop['price'] ?? 0;
        @endphp
    @endforeach
    @php
        // $netTotaltran =  $alltotaltran - ($totalIncome - $totalExpense);

        if ($totalIncome > $totalExpense) {
            // ถ้า totalIncome มากกว่า totalExpense
            $netTotaltran = $alltotaltran + ($totalIncome - $totalExpense);
        } elseif ($totalIncome < $totalExpense) {
            // ถ้า totalIncome น้อยกว่า totalExpense
            $netTotaltran = $alltotaltran - ($totalExpense - $totalIncome);
        } else {
            // ถ้า totalIncome เท่ากับ totalExpense
            $netTotaltran = $alltotaltran; // ไม่มีการเปลี่ยนแปลง
        }
    @endphp
    <tr style="font-weight: bold; background-color: #f1f1f1;">
        <td colspan="23" style="text-align: right;">รวม</td>
        <td style="text-align: center;">{{ $totalIncome }}</td>
        <td style="text-align: center;">{{ $totalExpense }}</td>
        <td style="text-align: center;">{{ $netTotaltran }}</td>
    </tr>
    <tr>
        <td>ลำดับ</td>
        <td>เลขที่</td>
        <td colspan="2" style="text-align: center;">ชื่อลูกค้า</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalinvoices = 0;

    @endphp
    @php
        $processedNos = [];
        $paidTotalPrice = 0;
        $paidPricetotal = 0;
    @endphp
    @foreach ($invoices1['Invoicepaid_tran'] as $transferinvoices => $transferinvoicesloop)
        @if (!in_array($transferinvoicesloop['no'], $processedNos))
            @php
                $processedNos[] = $transferinvoicesloop['no'];
                // $paidPricetotal = 0;
                $columnTotalstran = [];

            @endphp
            <tr>
                <td style="text-align: center;">{{ $No++ }}</td>
                <td style="text-align: center;">{{ $transferinvoicesloop['no'] ?? ' ' }}</td>
                <td colspan="2" style="text-align: left;">{{ $transferinvoicesloop['name'] ?? ' ' }}</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                @foreach ($transferinvoicesloop['paid_type1']['paid_tran'] as $index => $paidtype)
                    @if ($paidtype['paid_type'] === 'paid_tran')
                        @php
                            // $paidPrice = isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0 ? $paidtype['paid_price'] : '-';
                            // $paidPricetotal += $paidPrice;

                            $paidPrice =
                                isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0
                                    ? $paidtype['paid_price']
                                    : 0;
                            $paidPricetotal += $paidPrice; // สะสมค่าทั้งหมด
                            $columnTotalstran[$index] = isset($columnTotalstran[$index])
                                ? $columnTotalstran[$index] + $paidPrice
                                : $paidPrice;
                        @endphp
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        @if ($loop->remaining > 0)
            </tr>
            <tr>
                <td colspan="24"></td>
            @else
        @endif
    @endif
    @endforeach

    </tr>
    @endif
    @endforeach
    @php
        $paidTotalPrice += $paidPricetotal;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวม</td>
        <td style="text-align: center;">{{ $paidTotalPrice }}</td>
    </tr>
    @php
        $netTotaltran += $paidTotalPrice;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวมสุทธิ</td>
        <td style="text-align: center;">{{ $netTotaltran }}</td>
    </tr>

    <tr>
        <td colspan="25" style="text-align: right;">ยอดรวมสุทธิ</td>
        <td style="text-align: right;">{{ $netTotalcash + $netTotalcredit + $netTotaltran }}</td>
    </tr>

    {{-- <tr>
        <td>ลำดับ</td>
        <td>เลขที่</td>
        <td colspan="2" style="text-align: center;">ชื่อลูกค้า</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalinvoices = 0;

    @endphp
    @php
        $processedNos = [];
        $paidTotalPrice = 0;
        $paidPricetotal = 0;
    @endphp
    @foreach ($invoices1['Invoicepaid_cash'] as $transferinvoices => $transferinvoicesloop)
        @if (!in_array($transferinvoicesloop['no'], $processedNos))
            @php
                $processedNos[] = $transferinvoicesloop['no'];
                // $paidPricetotal = 0;
                $columnTotalstran = [];

            @endphp
            <tr>
                <td style="text-align: center;">{{ $No++ }}</td>
                <td style="text-align: center;">{{ $transferinvoicesloop['no'] ?? ' ' }}</td>
                <td colspan="2" style="text-align: left;">{{ $transferinvoicesloop['name'] ?? ' ' }}</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                @foreach ($transferinvoicesloop['paid_type1']['paid_cash'] as $index => $paidtype)
                    @if ($paidtype['paid_type'] === 'paid_cash')
                        @php
                            // $paidPrice = isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0 ? $paidtype['paid_price'] : '-';
                            // $paidPricetotal += $paidPrice;

                            $paidPrice =
                                isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0
                                    ? $paidtype['paid_price']
                                    : 0;
                            $paidPricetotal += $paidPrice; // สะสมค่าทั้งหมด
                            $columnTotalstran[$index] = isset($columnTotalstran[$index])
                                ? $columnTotalstran[$index] + $paidPrice
                                : $paidPrice;
                        @endphp
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        @if ($loop->remaining > 0)
            </tr>
            <tr>
                <td colspan="24"></td>
            @else
        @endif
    @endif
    @endforeach

    </tr>
    @endif
    @endforeach
    @php
        $paidTotalPrice += $paidPricetotal;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวม</td>
        <td style="text-align: center;">{{ $paidTotalPrice }}</td>
    </tr>

    <tr>
        <td>ลำดับ</td>
        <td>เลขที่</td>
        <td colspan="2" style="text-align: center;">ชื่อลูกค้า</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalinvoices = 0;

    @endphp
    @php
        $processedNos = [];
        $paidTotalPrice = 0;
        $paidPricetotal = 0;
    @endphp
    @foreach ($invoices1['Invoicecredit'] as $transferinvoices => $transferinvoicesloop)
        @if (!in_array($transferinvoicesloop['no'], $processedNos))
            @php
                $processedNos[] = $transferinvoicesloop['no'];
                // $paidPricetotal = 0;
                $columnTotalstran = [];

            @endphp
            <tr>
                <td style="text-align: center;">{{ $No++ }}</td>
                <td style="text-align: center;">{{ $transferinvoicesloop['no'] ?? ' ' }}</td>
                <td colspan="2" style="text-align: left;">{{ $transferinvoicesloop['name'] ?? ' ' }}</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                @foreach ($transferinvoicesloop['paid_type1']['credit'] as $index => $paidtype)
                    @if ($paidtype['paid_type'] === 'credit')
                        @php
                            // $paidPrice = isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0 ? $paidtype['paid_price'] : '-';
                            // $paidPricetotal += $paidPrice;

                            $paidPrice =
                                isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0
                                    ? $paidtype['paid_price']
                                    : 0;
                            $paidPricetotal += $paidPrice; // สะสมค่าทั้งหมด
                            $columnTotalstran[$index] = isset($columnTotalstran[$index])
                                ? $columnTotalstran[$index] + $paidPrice
                                : $paidPrice;
                        @endphp
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        @if ($loop->remaining > 0)
            </tr>
            <tr>
                <td colspan="24"></td>
            @else
        @endif
    @endif
    @endforeach

    </tr>
    @endif
    @endforeach
    @php
        $paidTotalPrice += $paidPricetotal;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวม</td>
        <td style="text-align: center;">{{ $paidTotalPrice }}</td>
    </tr>

    <tr>
        <td>ลำดับ</td>
        <td>เลขที่</td>
        <td colspan="2" style="text-align: center;">ชื่อลูกค้า</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>รายรับ</td>
        <td>สุทธิ</td>
    </tr>
    @php
        $No = 1;
        $totalinvoices = 0;

    @endphp
    @php
        $processedNos = [];
        $paidTotalPrice = 0;
        $paidPricetotal = 0;
    @endphp
    @foreach ($invoices1['Invoicepaid_tran'] as $transferinvoices => $transferinvoicesloop)
        @if (!in_array($transferinvoicesloop['no'], $processedNos))
            @php
                $processedNos[] = $transferinvoicesloop['no'];
                // $paidPricetotal = 0;
                $columnTotalstran = [];

            @endphp
            <tr>
                <td style="text-align: center;">{{ $No++ }}</td>
                <td style="text-align: center;">{{ $transferinvoicesloop['no'] ?? ' ' }}</td>
                <td colspan="2" style="text-align: left;">{{ $transferinvoicesloop['name'] ?? ' ' }}</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                @foreach ($transferinvoicesloop['paid_type1']['paid_tran'] as $index => $paidtype)
                    @if ($paidtype['paid_type'] === 'paid_tran')
                        @php
                            // $paidPrice = isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0 ? $paidtype['paid_price'] : '-';
                            // $paidPricetotal += $paidPrice;

                            $paidPrice =
                                isset($paidtype['paid_price']) && $paidtype['paid_price'] > 0
                                    ? $paidtype['paid_price']
                                    : 0;
                            $paidPricetotal += $paidPrice; // สะสมค่าทั้งหมด
                            $columnTotalstran[$index] = isset($columnTotalstran[$index])
                                ? $columnTotalstran[$index] + $paidPrice
                                : $paidPrice;
                        @endphp
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        <td style="text-align: center;">{{ $paidPrice }}</td>
                        @if ($loop->remaining > 0)
            </tr>
            <tr>
                <td colspan="24"></td>
            @else
        @endif
    @endif
    @endforeach

    </tr>
    @endif
    @endforeach
    @php
        $paidTotalPrice += $paidPricetotal;
    @endphp
    <tr>
        <td colspan="25" style="text-align: right;">ผลรวม</td>
        <td style="text-align: center;">{{ $paidTotalPrice }}</td>
    </tr> --}}

</table>
