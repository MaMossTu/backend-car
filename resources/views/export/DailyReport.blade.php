<table >
    <thead>
    <tr>
        <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ลำดับ</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">เลขที่</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ทะเบียน</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 300px;">ชื่อลูกค้า</th>
        <th colspan="4" style="border: 1px solid black; text-align: center; vertical-align: middle;">บริการ</th>
        <th colspan="4" style="border: 1px solid black; text-align: center; vertical-align: middle;">ค่าธรรมเนียม</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ส่วนลด</th>
        <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">สุทธิ</th>
    </tr>
    <tr>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ตรวจสภาพ</th>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">พ.ร.บ.</th>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ภาษีประจำปี</th>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ประกัน</th>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">อื่น ๆ</th>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">พ.ร.บ.</th>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ภาษีประจำปี</th>
        <th style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">อื่น ๆ</th>
    </tr>
    </thead>
    @php $No = 1; @endphp
    @foreach ($data as $num => $dataloop)
        @php
            $services = [
                'ตรวจสภาพ' => ['service_price_tran' => 0, 'service_price' => 0],
                'พ.ร.บ.' => ['service_price_tran' => 0, 'service_price' => 0],
                'ภาษีประจำปี' => ['service_price_tran' => 0, 'service_price' => 0],
                'ประกัน' => ['service_price_tran' => 0, 'service_price' => 0],
                'Others' => ['service_price_tran' => 0, 'service_price' => 0],
            ];

            foreach ($dataloop['service_transactions'] as $service) {
                if (isset($services[$service['service_name']])) {
                    $services[$service['service_name']]['service_price_tran'] += $service['service_price_tran'];
                    $services[$service['service_name']]['service_price'] += $service['service_price'];
                } else {
                    $services['Others']['service_price_tran'] += $service['service_price_tran'];
                    $services['Others']['service_price'] += $service['service_price'];
                }
            }
        @endphp

        <tr>
            <td>{{ $No++ }}</td>
            <td>{{ $dataloop['inspection_details']['inspection_no'] }}</td>
            <td>{{ $dataloop['inspection_details']['license_plate'] }}</td>
            <td>{{ $dataloop['inspection_details']['customer_name'] }}</td>
            <td>{{ $services['ตรวจสภาพ']['service_price_tran'] }}</td>
            <td>{{ $services['พ.ร.บ.']['service_price_tran'] }}</td>
            <td>{{ $services['ภาษีประจำปี']['service_price_tran'] }}</td>
            <td>{{ $services['ประกัน']['service_price_tran'] }}</td>
            <td>{{ $services['Others']['service_price_tran'] }}</td>
            <td>{{ $services['ภาษีประจำปี']['service_price'] }}</td>
            <td>{{ $services['ประกัน']['service_price'] }}</td>
            <td>{{ $services['Others']['service_price'] }}</td>
            <td>{{ $dataloop['inspection_details']['discount'] }}</td>
            <td>{{ $dataloop['inspection_details']['total'] }}</td>
        </tr>
    @endforeach


</table>
