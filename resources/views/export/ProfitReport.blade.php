<table>
    <thead>
        <tr>
            <th style="text-align: center;" rowspan="2">#</th>
            <th style="text-align: center;" rowspan="2">เลขที่</th>
            <th style="text-align: center;" rowspan="2">วันที่</th>
            <th style="text-align: center;" rowspan="2">ทะเบียน</th>
            <th style="text-align: center;" rowspan="2">ชื่อลูกค้า</th>
            <th style="text-align: center;" colspan="13">บริการ</th>
            <th style="text-align: center;" rowspan="2">สุทธิ</th>
        </tr>
        <tr>
            <th>ตรวจสภาพ</th>
            <th>พ.ร.บ LPG</th>
            <th>ภาษีประจำปี</th>
            <th>ค่าปรับภาษี</th>
            <th>บริการต่อภาษี</th>
            <th>ค่าบริการออกพ.ร.บ.</th>
            <th>ประกัน</th>
            <th>ขนส่ง</th>
            <th>LPG</th>
            <th>NGV</th>
            <th>หนังสือรับรองวิศวกร</th>
            <th>อื่นๆ</th>
            <th>EMS</th>
        </tr>
    </thead>
    <tbody>
        @php
            $No = 1;
        @endphp
        @foreach ($data as $item)
            @php
                $serviceTotals = array_fill(1, 13, 0);

                foreach ($item->serviceTransactions as $transaction) {
                    $serviceId = $transaction->service_id;
                    if (isset($servicePrices[$serviceId])) {
                        $serviceTotals[$serviceId] = $servicePrices[$serviceId];
                    }
                }
            @endphp
            <tr>
                <td>{{ $No++ }}</td>
                <td>{{ $item->no }}</td>
                <td>{{ $item->date }}</td>
                <td>{{ $item->license_plate }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $serviceTotals[1] ?? '  ' }}</td>
                <td>{{ $serviceTotals[2] ?? '  ' }}</td>
                <td>{{ $serviceTotals[3] ?? '  ' }}</td>
                <td>{{ $serviceTotals[4] ?? '  ' }}</td>
                <td>{{ $serviceTotals[5] ?? '  ' }}</td>
                <td>{{ $serviceTotals[6] ?? '  ' }}</td>
                <td>{{ $serviceTotals[7] ?? '  ' }}</td>
                <td>{{ $serviceTotals[8] ?? ' ' }}</td>
                <td>{{ $serviceTotals[9] ?? '  ' }}</td>
                <td>{{ $serviceTotals[10] ?? '  ' }}</td>
                <td>{{ $serviceTotals[11] ?? '  ' }}</td>
                <td>{{ $serviceTotals[12] ?? ' ' }}</td>
                <td>{{ $serviceTotals[13] ?? '  ' }}</td>
                <td>{{ array_sum($serviceTotals) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
