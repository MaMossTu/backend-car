<table>
    <tr>
        <td colspan="9" style="text-align: center;background-color: #ffff99;">ข้อมูลลูกค้า</td>
    </tr>
    <tr>
        <td style="width: 50px; background-color: #d9ead3; text-align: center;">ลำดับ</td>
        <td style="width: 100px; background-color: #d9ead3; text-align: center;">รหัส</td>
        <td style="width: 300px; background-color: #d9ead3; text-align: center;">ชื่อ</td>
        <td style="width: 250px; background-color: #d9ead3; text-align: center;">นามสกุล</td>
        <td style="width: 200px; background-color: #d9ead3; text-align: center;">อีเมล</td>
        <td style="width: 200px; background-color: #d9ead3; text-align: center;">เบอร์โทร</td>
        <td style="width: 180px; background-color: #d9ead3; text-align: center;">เลขที่ผู้เสียภาษี</td>
        <td style="width: 150px; background-color: #d9ead3; text-align: center;">ประเภทสมาชิก</td>
        <th style="width: 100px; text-align: center;background-color: #d9ead3;">สำนักงานใหญ่หรือไม่</th>
    </tr>
    @php
    $i = 1;
    @endphp
    @foreach($data[0] as $customer)
    <tr>
        <td style="text-align: center;">{{ $i++ }}</td>
        <td style="text-align: center;">{{ $customer['no'] }}</td>
        <td style="text-align: left;">{{ $customer['name'] }}</td>
        <td style="text-align: left;">{{ $customer['lname'] }}</td>
        <td style="text-align: left;">{{ $customer['email'] }}</td>
        <td style="text-align: right;">
            @if(!empty($customer['phone_number1']))
            {{ $customer['phone_number1'] }}
            @if(!empty($customer['phone_number2']))
            , {{ $customer['phone_number2'] }}
            @endif
            @elseif(!empty($customer['phone_number2']))
            {{ $customer['phone_number2'] }}
            @endif
        </td>
        <td style="text-align: right;">{{ $customer['tax_id'] }}</td>
        <td style="text-align: center;">{{ $customer['type'] }}</td>
        <td style="text-align: center;">{{ $customer['is_headquarter'] }}</td>
    </tr>
    @endforeach
</table>
