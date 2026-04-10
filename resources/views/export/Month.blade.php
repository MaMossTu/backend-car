<table style="border: 1px solid black; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่</th>
            <th style="border: 1px solid black; text-align: center; width: 200px;">รายการ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เงินสด</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">โอน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ค้างชำระ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">รายได้รวม</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">รายจ่าย</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">คงเหลือ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        @php

        $dailyTotals = [];
        foreach ($items as $item) {
            if ($item->status = 'finish') {
                $dateKey = $item->date;

                if (!isset($dailyTotals[$dateKey])) {
                    $dailyTotals[$dateKey] = [
                        'cash' => 0,
                        'transfer' => 0,
                        'credit' => 0,
                        'debt' => 0,
                        'total' => 0,
                    ];
                }
                foreach ($item->Installments as $index => $installment) {
                    if ($installment->paid_type == 'paid_cash') {
                        $dailyTotals[$dateKey]['cash'] += $installment->paid_price;
                    } elseif ($installment->paid_type == 'paid_tran') {
                        $dailyTotals[$dateKey]['transfer'] += $installment->paid_price;
                    } elseif ($installment->paid_type == 'credit') {
                        $dailyTotals[$dateKey]['credit'] += $installment->paid_price;
                    }
                    if ($index === count($item->Installments) - 1) {
                        $dailyTotals[$dateKey]['debt'] += $installment->after_price;
                    }
                } 
                $dailyTotals[$dateKey]['total'] += $item->total;
            }
        }


        $totalCash = 0;
        $totalTransfer = 0;
        $totalCredit = 0;
        $totalDebt = 0;
        $totalOverall = 0;
        $totalCosts = 0;
        $previousBalance = 0;

        foreach ($dailyTotals as $date => $totals) {
            $currentBalance = $previousBalance + $totals['total'];
            echo '
            <tr>
                <td style="border: 1px solid black; text-align: center;">' . e($date) . '</td>
                <td style="border: 1px solid black; text-align: left;">สรุปประจำวัน</td>
                <td style="border: 1px solid black; text-align: right;">' . ($totals['cash'] > 0 ? number_format($totals['cash'], 2) : '&nbsp;') . '</td>
                <td style="border: 1px solid black; text-align: right;">' . ($totals['transfer'] > 0 ? number_format($totals['transfer'], 2) : '&nbsp;') . '</td>
                <td style="border: 1px solid black; text-align: right;">' . ($totals['credit'] > 0 ? number_format($totals['credit'], 2) : '&nbsp;') . '</td>
                <td style="border: 1px solid black; text-align: right;">' . ($totals['debt'] > 0 ? number_format($totals['debt'], 2) : '&nbsp;') . '</td>
                <td style="border: 1px solid black; text-align: right;">' . ($totals['total'] > 0 ? number_format($totals['total'], 2) : '&nbsp;') . '</td>
                <td style="border: 1px solid black; text-align: right;">' . number_format($currentBalance, 2) . '</td>
                <td style="border: 1px solid black;"></td>
                <td></td>
            </tr>';

            $totalCash += $totals['cash'];
            $totalTransfer += $totals['transfer'];
            $totalCredit += $totals['credit'];
            $totalDebt += $totals['debt'];
            $totalOverall += $totals['total'];
            $previousBalance = $currentBalance;
        }

        foreach ($costs as $cost) {
            $currentBalanceAfterCost = $previousBalance - $cost->price;
            echo '
            <tr>
                <td style="border: 1px solid black;"></td>
                <td style="border: 1px solid black;" class="text-left">' . e($cost->name) . '</td>
                <td style="border: 1px solid black;"></td>
                <td style="border: 1px solid black;"></td>
                <td style="border: 1px solid black;"></td>
                <td style="border: 1px solid black;"></td>
                <td style="border: 1px solid black;"></td>
                <td style="border: 1px solid black; text-align: right;">' . number_format($cost->price, 2) . '</td>
                <td style="border: 1px solid black; text-align: right;">' . number_format($currentBalanceAfterCost, 2) . '</td>
                <td ></td>
            </tr>';
            $totalCosts += $cost->price;
            $previousBalance = $currentBalanceAfterCost;
        }

        $finalBalance = $totalOverall - $totalCosts;
        echo '
        <tr>
            <td colspan="2" align="center" style="border: 1px solid black;"><strong>รวม</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>' . number_format($totalCash, 2) . '</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>' . number_format($totalTransfer, 2) . '</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>' . number_format($totalCredit, 2) . '</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>' . number_format($totalDebt, 2) . '</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>' . number_format($totalOverall, 2) . '</strong></td>
            <td style="border: 1px solid black; text-align: right;"><strong>' . number_format($finalBalance, 2) . '</strong></td>
            <td style="border: 1px solid black; text-align: right;"></td>
        </tr>';
        @endphp
    </tbody>
</table>
