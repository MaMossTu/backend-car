<table >
    <tr>
        <th colspan="11" style="border: 1px solid black;border:1px solid #333; font-size: 18px;" align="center">
            รายการตรวจสภาพรถ {{ \Carbon\Carbon::parse($startDate)->addYears(543)->format('d/m/Y') }} ถึง {{ \Carbon\Carbon::parse($endDate)->addYears(543)->format('d/m/Y') }}
        </th>
    </tr>
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">จังหวัด</th>
        <th style="border: 1px solid black; text-align: center; width: 200px;">ประเภท</th>
        <th style="border: 1px solid black; text-align: center; width: 300px;">ชื่อลูกค้า</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่จดทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่ตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ผลตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">รายละเอียด</th>
    </tr>
    @php 
    $No = 1; 
    
    @endphp
    @foreach ($data as $num => $dataloop)
    @php
        $registrationDate = !empty($dataloop['registration_date']) 
            ? $dataloop['registration_date'] 
            : '-';
        $inspectionDate = !empty($dataloop['date']) 
            ? $dataloop['date'] 
            : '-';
    @endphp
    <tr>
        <td style="border: 1px solid black;text-align: center;">{{ $No++ }}</td>
        <td style="border: 1px solid black;text-align: center;">{{ $dataloop['no'] ?? "-" }}</td>
        <td style="border: 1px solid black;text-align: center;">{{ $dataloop['license_plate'] ?? "-" }}</td>
        <td style="border: 1px solid black;text-align: center;">{{ $dataloop['provinces'] ?? "-" }}</td>
        <td style="border: 1px solid black;">{{ $dataloop['vehicle_inspection_types'] ?? "-" }}</td>
        <td style="border: 1px solid black;">{{ $dataloop['name'] ?? "-" }}</td>
        <td style="border: 1px solid black;text-align: center;">{{ $registrationDate }}</td>
        <td style="border: 1px solid black;text-align: center;">{{ $inspectionDate }}</td>
        <td style="border: 1px solid black;text-align: center;">{{ $dataloop['is_pass'] ?? "-" }}</td>
        <td style="border: 1px solid black;text-align: center;">{{ $dataloop['total_price'] ?? "-" }}</td>
        <td style="border: 1px solid black;">{{ $dataloop['result'] ?? "-" }}</td>
    </tr>
@endforeach
</table>
