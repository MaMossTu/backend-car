<table>
    <thead>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">#</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่</th>
            <th style="border: 1px solid black; text-align: center; width: 300px;">รายการ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ยอดเงิน</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalAmount = 0;
        @endphp

        @forelse ($data as $index => $cost)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $cost['date'] }}</td>
                <td>{{ $cost['name'] }}</td>
                <td>{{ number_format($cost['price'], 2) }}</td>
            </tr>

            @php
                $totalAmount += $cost['price'];
            @endphp
        @empty
            <tr>
            </tr>
        @endforelse

        <tr>
            <td colspan="3" style="text-align: right; font-weight: bold;">รวม:</td>
            <td style="font-weight: bold;">{{ number_format($totalAmount, 2) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
