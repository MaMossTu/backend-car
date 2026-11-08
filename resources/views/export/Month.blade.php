<table style="border: 1px solid black; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่</th>
            <th style="border: 1px solid black; text-align: center; width: 200px;">บิล</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เงินสด</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">โอน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เครดิต</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ลูกหนี้</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">รายได้รวม</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">รายจ่าย</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">คงเหลือ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalCash = 0;
            $totalTransfer = 0;
            $totalCredit = 0;
            $totalDebt = 0;
            $totalOverall = 0;
            $totalCosts = 0;
            $previousBalance = 0;
        @endphp

        @foreach ($items as $item)
            @if (in_array($item->status, ['finish', 'overdue'], true))
                @php
                    $cash = 0;
                    $transfer = 0;
                    $credit = 0;
                    $debt = 0;
                    $installments = $item->Installments ?? collect();
                    $installmentCount = $installments->count();
                    foreach ($installments as $index => $installment) {
                        if ($installment->paid_type === 'paid_cash') {
                            $cash += (float) $installment->paid_price;
                        } elseif ($installment->paid_type === 'paid_tran') {
                            $transfer += (float) $installment->paid_price;
                        } elseif ($installment->paid_type === 'credit') {
                            $credit += (float) $installment->paid_price;
                        }
                        if ($index === $installmentCount - 1) {
                            $debt = (float) ($installment->after_price ?? 0);
                        }
                    }
                    $billTotal = (float) ($item->total ?? 0);
                    $previousBalance += $billTotal;

                    $totalCash += $cash;
                    $totalTransfer += $transfer;
                    $totalCredit += $credit;
                    $totalDebt += $debt;
                    $totalOverall += $billTotal;
                @endphp
                <tr>
                    <td style="border: 1px solid black; text-align: center;">{{ $item->date }}</td>
                    <td style="border: 1px solid black; text-align: left;">{{ $item->no }}</td>
                    <td style="border: 1px solid black; text-align: right;">{{ number_format($cash, 2) }}</td>
                    <td style="border: 1px solid black; text-align: right;">{{ number_format($transfer, 2) }}</td>
                    <td style="border: 1px solid black; text-align: right;">{{ number_format($credit, 2) }}</td>
                    <td style="border: 1px solid black; text-align: right;">{{ number_format($debt, 2) }}</td>
                    <td style="border: 1px solid black; text-align: right;">{{ number_format($billTotal, 2) }}</td>
                    <td style="border: 1px solid black; text-align: right;">0.00</td>
                    <td style="border: 1px solid black; text-align: right;">{{ number_format($previousBalance, 2) }}</td>
                    <td style="border: 1px solid black;">{{ $item->remark ?? '' }}</td>
                </tr>
            @endif
        @endforeach

        @foreach ($costs as $cost)
            @php
                $previousBalance -= (float) $cost->price;
                $totalCosts += (float) $cost->price;
            @endphp
            <tr>
                <td style="border: 1px solid black; text-align: center;">{{ $cost->date ?? '' }}</td>
                <td style="border: 1px solid black; text-align: left;">{{ $cost->name }}</td>
                <td style="border: 1px solid black; text-align: right;">0.00</td>
                <td style="border: 1px solid black; text-align: right;">0.00</td>
                <td style="border: 1px solid black; text-align: right;">0.00</td>
                <td style="border: 1px solid black; text-align: right;">0.00</td>
                <td style="border: 1px solid black; text-align: right;">0.00</td>
                <td style="border: 1px solid black; text-align: right;">{{ number_format($cost->price, 2) }}</td>
                <td style="border: 1px solid black; text-align: right;">{{ number_format($previousBalance, 2) }}</td>
                <td style="border: 1px solid black;"></td>
            </tr>
        @endforeach

        @php
            $finalBalance = $totalOverall - $totalCosts;
        @endphp
        <tr>
            <td colspan="2" align="center" style="border: 1px solid black;"><strong>รวม</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>{{ number_format($totalCash, 2) }}</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>{{ number_format($totalTransfer, 2) }}</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>{{ number_format($totalCredit, 2) }}</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>{{ number_format($totalDebt, 2) }}</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>{{ number_format($totalOverall, 2) }}</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>{{ number_format($totalCosts, 2) }}</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>{{ number_format($finalBalance, 2) }}</strong></td>
            <td style="border: 1px solid black;"></td>
        </tr>
    </tbody>
</table>
