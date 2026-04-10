<table >
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">รหัสร้าน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ชื่อลูกค้า</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่ตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขทะเบียนวิศวกร</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">หมายเลขทะเบียนรถ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">จังหวัด</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ยี่ห้อรถ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขใบติดตั้ง</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขต่อถัง</th>
    </tr>
    @php $No = 1; @endphp
    @foreach ($data as $num => $dataloop)
    <tr>
        <td>{{ $dataloop->no ?? null }}</td>
        <td>{{ $dataloop->name ?? null }}</td>
        <td>{{ $dataloop->service_or ?? null }}</td>
        <td>{{ $dataloop->day ?? null }}</td>
        <td>{{ $dataloop->code ?? null }}</td>
        <td>{{ $dataloop->license_plate ?? null }}</td>
        <td>{{ $dataloop->province_name ?? null }}</td>
        <td>{{ $dataloop->brand_name ?? null }}</td>
        <td>{{ $dataloop->install_no ?? null }}</td>
        <td>{{ $dataloop->CHA_no ?? null }}</td>
    </tr>
@endforeach



</table>
