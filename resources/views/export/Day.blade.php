<table>
    <tr>
        <td colspan="7" style="border:1px solid #333; font-size: 22px; font-weight: bold; text-align: center;">
            สรุปประจำวัน
        </td>
    </tr>
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">บิล</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เงินสด</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">โอน</th>
        <th style="border: 1px solid black; text-align: center; width: 300px;">เครดิต</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ลูกหนี้</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">หมายเหตุ</th>
    </tr>

    @php
        $totalCash = 0;
        $totalTransfer = 0;
        $totalDebt = 0;
        $totalCredit = 0;
        $lastNo = null;
    @endphp

    @foreach ($data as $dataloop)
        @if ($dataloop['status'] == 'finish')
            @foreach ($dataloop['installments'] as $installment)
                @php
                    $cash = ($installment['paid_type'] == 'paid_cash') ? $installment['paid_price'] : 0;
                    $transfer = ($installment['paid_type'] == 'paid_tran') ? $installment['paid_price'] : 0;
                    $credit = ($installment['paid_type'] == 'credit') ? $installment['paid_price'] : 0;
                    $debt = $installment['after_price'] ?? 0;

                    $totalCash += $cash;
                    $totalTransfer += $transfer;
                    $totalDebt += $debt;
                    $totalCredit += $credit;
                @endphp

                <tr>
                    <td style="border: 1px solid black;">{{ $lastNo !== $dataloop['no'] ? $dataloop['date'] : '' }}</td>
                    <td style="border: 1px solid black;">{{ $lastNo !== $dataloop['no'] ? $dataloop['no'] : '' }}</td>
                    <td style="border: 1px solid black;">{{ $cash }}</td>
                    <td style="border: 1px solid black;">{{ $transfer }}</td>
                    <td style="border: 1px solid black;">{{ $credit }}</td>
                    <td style="border: 1px solid black;">{{ $debt }}</td>
                    <td style="border: 1px solid black;"></td>
                </tr>

                @php
                    $lastNo = $dataloop['no'];
                @endphp
            @endforeach
        @endif
    @endforeach

    <tr>
        <td colspan="2" style="border: 1px solid black;"><strong>รวม</strong></td>
        <td style="border: 1px solid black;">{{ $totalCash }}</td>
        <td style="border: 1px solid black;">{{ $totalTransfer }}</td>
        <td style="border: 1px solid black;">{{ $totalCredit }}</td>
        <td style="border: 1px solid black;">{{ $totalDebt }}</td>
        <td style="border: 1px solid black;"></td>
    </tr>
</table>
