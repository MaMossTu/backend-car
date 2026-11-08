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
    @endphp

    @foreach ($data as $dataloop)
        @if (in_array($dataloop['status'] ?? '', ['finish', 'overdue'], true))
            @php
                $cash = 0;
                $transfer = 0;
                $credit = 0;
                $debt = 0;
                $installments = $dataloop['installments'] ?? [];
                $installmentCount = count($installments);
                foreach ($installments as $index => $installment) {
                    if (($installment['paid_type'] ?? '') === 'paid_cash') {
                        $cash += (float) ($installment['paid_price'] ?? 0);
                    } elseif (($installment['paid_type'] ?? '') === 'paid_tran') {
                        $transfer += (float) ($installment['paid_price'] ?? 0);
                    } elseif (($installment['paid_type'] ?? '') === 'credit') {
                        $credit += (float) ($installment['paid_price'] ?? 0);
                    }
                    if ($index === $installmentCount - 1) {
                        $debt = (float) ($installment['after_price'] ?? 0);
                    }
                }
                $totalCash += $cash;
                $totalTransfer += $transfer;
                $totalDebt += $debt;
                $totalCredit += $credit;
            @endphp

            <tr>
                <td style="border: 1px solid black;">{{ $dataloop['date'] ?? '' }}</td>
                <td style="border: 1px solid black;">{{ $dataloop['no'] ?? '' }}</td>
                <td style="border: 1px solid black; text-align: right;">{{ number_format($cash, 2) }}</td>
                <td style="border: 1px solid black; text-align: right;">{{ number_format($transfer, 2) }}</td>
                <td style="border: 1px solid black; text-align: right;">{{ number_format($credit, 2) }}</td>
                <td style="border: 1px solid black; text-align: right;">{{ number_format($debt, 2) }}</td>
                <td style="border: 1px solid black;">{{ $dataloop['remark'] ?? '' }}</td>
            </tr>
        @endif
    @endforeach

    <tr>
        <td colspan="2" style="border: 1px solid black;"><strong>รวม</strong></td>
        <td style="border: 1px solid black; text-align: right;">{{ number_format($totalCash, 2) }}</td>
        <td style="border: 1px solid black; text-align: right;">{{ number_format($totalTransfer, 2) }}</td>
        <td style="border: 1px solid black; text-align: right;">{{ number_format($totalCredit, 2) }}</td>
        <td style="border: 1px solid black; text-align: right;">{{ number_format($totalDebt, 2) }}</td>
        <td style="border: 1px solid black;"></td>
    </tr>
</table>
