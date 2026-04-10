<table >
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">จังหวัด</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ประเภท</th>
        <th style="border: 1px solid black; text-align: center; width: 300px;">ชื่อลูกค้า</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่จดทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เรียกเก็บ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ยอดจริง</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">สถานะ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">หมายเหตุ</th>
    </tr>
    @php $No = 1; @endphp
    @foreach ($data as $num => $dataloop)
    <tr>
        <td style="text-align: center;">{{ $No++ }}</td>
        <td>{{ $dataloop['no'] ?? '-' }}</td>
        <td>{{ $dataloop['license_plate'] ?? '-' }}</td>
        <td>{{ $dataloop['name_th'] ?? '-' }}</td>
        <td>{{ $dataloop['inspection_type_name'] ?? '-' }}</td>
        <td>{{ $dataloop['name'] ?? '-' }}</td>
        <td>{{ $dataloop['registration_date'] ?? '-' }}</td>
        <td>0</td> <!-- ยังไม่มีตัวเก็บ -->
        <td>0</td> <!-- ยังไม่มีตัวเก็บ -->
        <td>
            @if (is_array($dataloop) && isset($dataloop['status']))
                {{ $dataloop['status'] === 'overdue' ? 'ค้างชำระ' : ($dataloop['status'] === 'open' ? 'รอชำระ' : '-') }}
            @elseif (is_object($dataloop) && isset($dataloop->status))
                {{ $dataloop->status === 'overdue' ? 'ค้างชำระ' : ($dataloop->status === 'open' ? 'รอชำระ' : '-') }}
            @else
                -
            @endif
        </td>
        <td>{{ $dataloop['comments'] ?? '-' }}</td>
    </tr>
    @endforeach
