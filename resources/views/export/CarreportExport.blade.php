<table>
    <tr style="background-color: #ffff99;">
        <td colspan="20" style="text-align: center;background-color: #ffff99;">ข้อมูลรถ</td>
    </tr>
    <tr style="background-color: #d9d9d9;">
        <th style="width: 50px; text-align: center;background-color: #d9d9d9;">ลำดับ</th>
        <th style="width: 100px; text-align: center;background-color: #d9d9d9;">ทะเบียน</th>
        <th style="width: 250px; text-align: center;background-color: #d9d9d9;">ประเภท</th>
        <th style="width: 250px; text-align: center;background-color: #d9d9d9;">ลูกค้า</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">วันที่จดทะเบียน</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">วันที่ยื่นภาษี</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">วันที่ครบกำหนดชำระภาษี</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">เป็นนิติบุคคลหรือไม่</th>
        <th style="width: 120px; text-align: center;background-color: #d9d9d9;">ยี่ห้อ</th>
        <th style="width: 180px; text-align: center;background-color: #d9d9d9;">รุ่น</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">เลขตัวรถ</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">เลขเครื่องยนต์</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">จำนวนที่นั่ง</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">ประเภทเชื้อเพลิง</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">ชนิดเชื้อเพลิง</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">ขนาด (CC)</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">น้ำหนัก (กก.)</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">น้ำหนักบรรทุก</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">เลขไมล์</th>
        <th style="width: 250px; text-align: center;background-color: #d9d9d9;">คำอธิบาย</th>
    </tr>
    @php
    $i = 1;
    @endphp
    @foreach($data[0] as $car)
    <tr>
        <td style="text-align: center;">{{ $i++ }}</td>
        <td style="text-align: center;">{{ $car['license_plate'] ?? '-' }}</td>
        <td style="text-align: center;">{{ ($car['VehicleInspectionTypes']['name'] ?? '') . ' ('.($car['VehicleInspectionTypes']['type'] ?? '') . ($car['VehicleInspectionTypes']['code'] ?? '') . ')' }}</td>
        <td style="text-align: left;">{{ ($car['customers']['name'] ?? '') . ' ' . ($car['customers']['lname'] ?? '') }}</td>
        <td style="text-align: center;">{{ $car['registration_date'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['tax_renewal_date'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['notificate_tax'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['is_corporate'] }}</td>
        <td style="text-align: center;">{{ $car['VehicleBrands']['name'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['VehicleModels']['name'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['chassis_number'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['engine_number'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['seat_count'] ?? '-' }} ที่นั่ง</td>
        <td style="text-align: center;">{{ $car['fuel_type'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['fuel'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['cc'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['weight'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['weight_loaded'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['mileage'] ?? '-' }}</td>
        <td style="text-align: center;">{{ $car['description'] ?? '-' }}</td>
    </tr>
    @endforeach
</table>
