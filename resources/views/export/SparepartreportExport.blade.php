<table>
    <tr style="background-color: #ffff99;">
        <td colspan="6" style="text-align: center;background-color: #ffff99;">รายการอะไหล่</td>
    </tr>
    <tr>
        <th style="width: 100px; text-align: center;background-color: #d9d9d9;">ลำดับ</th>
        <th style="width: 300px; text-align: center;background-color: #d9d9d9;">รายละเอียด</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">จำนวน</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">ราคา</th>
        <th style="width: 300px; text-align: center;background-color: #d9d9d9;">คำธิบาย</th>
        <th style="width: 300px; text-align: center;background-color: #d9d9d9;">ประเภท</th>
    </tr>
    @php
    $i = 1;
    @endphp
    @foreach($data[0] as $sparepart)
    <tr>
        <td style="text-align: center;">{{ $i++ }}</td>
        <td style="text-align: left;">{{ $sparepart['name'] }}</td>
        <td style="text-align: center;">{{ $sparepart['qty'] }}</td>
        <td style="text-align: right;">{{ $sparepart['price'] }}</td>
        <td style="text-align: right;">{{ $sparepart['description'] }}</td>
        <td style="text-align: center;">{{ $sparepart['ProductType']['name'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
