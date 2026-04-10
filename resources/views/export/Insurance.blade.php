<table >
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">#</th>
        <th style="border: 1px solid black; text-align: center; width: 200px;">เลขใบวางบิล</th>
        <th style="border: 1px solid black; text-align: center; width: 300px;">ชื่อลูกค้า</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 350px;">ประกันภัย</th>
        <th style="border: 1px solid black; text-align: center; width: 200px;">เลขกรรมธรรม์</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่เริ่มต้น</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่สิ้นสุด</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เบี้ย</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">สุทธิ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าบริการ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">หมายเหตุ</th>
    </tr>
    @php $No = 1; @endphp
    @foreach ($data as $num => $dataloop)
    <tr>
        <td style="text-align: center;">{{ $num + 1 }}</td>
        <td>{{ $dataloop['no'] ?? null }}</td>
        <td>{{ $dataloop['name'] ?? null }}</td>
        <td>{{ $dataloop['license_plate'] ?? null }}</td>
        <td>{{ $dataloop['insurance_name'] ?? null }}</td>
        <td>{{ $dataloop['policy_number'] ?? null }}</td>
        <td>{{ $dataloop['date_start'] ?? null }}</td>
        <td>{{ $dataloop['date_end'] ?? null }}</td>
        <td>{{ $dataloop['amount_paid'] ?? null }}</td>
        <td>{{ $dataloop['total'] ?? null }}</td>
        <td>{{ $dataloop['service_price'] ?? null }}</td>
        <td>{{ $dataloop['remark'] ?? null }}</td>
    </tr>
@endforeach

</table>
