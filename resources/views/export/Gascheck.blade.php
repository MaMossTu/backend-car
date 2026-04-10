<table>
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ลําดับ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
        <th style="border: 1px solid black; text-align: center; width: 300px;">ทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">จังหวัด</th>
        <th style="border: 1px solid black; text-align: center; width: 300px">ชื่อลูกค้า</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่จดทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่ตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ชนิด</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขใบตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ผลตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ค้าง</th>
    </tr>
    @php $No = 1; @endphp
    @foreach ($data as $num => $dataloop)
    @php
        $serviceName = ($dataloop['service_id'] == 9) ? 'LPG' : (($dataloop['service_id'] == 10) ? 'NGV' : '');
    @endphp
    <tr>
        <td style="text-align: center;">{{ $num + 1 }}</td>
        <td>{{ $dataloop['no'] ?? null }}</td>
        <td>{{ $dataloop['license_plate'] ?? null }}</td>
        <td>{{ $dataloop['name_th_prefix'] ?? null }}</td>
        <td>{{ $dataloop['name'] ?? null }}</td>
        <td>{{ $dataloop['registration_date'] ?? null }}</td>
        <td>{{ $dataloop['date'] ?? null }}</td>
        <td>{{ $serviceName ?? null}}</td>
        <td>{{ $dataloop['service_or'] ?? null }}</td>
        <td>{{ $dataloop['result'] ?? null }}</td>
        <td>{{ $dataloop['service_price'] ?? null }}</td>
        <td>{{ $dataloop['status'] ?? null }}</td>
    </tr>
@endforeach


</table>
