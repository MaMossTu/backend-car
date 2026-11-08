<!DOCTYPE html>
<html>
<head>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .no-data {
            text-align: center;
            font-style: italic;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th rowspan="2">#</th>
                <th rowspan="2">ชื่อ</th>
                <th rowspan="2">นามสกุล</th>
                <th rowspan="2">อีเมล</th>
                <th rowspan="2">เบอร์โทร</th>
                <th colspan="{{ count($serviceNames) }}">บริการ</th>
            </tr>
            <tr>
                @foreach ($serviceNames as $serviceName)
                    <th>{{ $serviceName }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['first_name'] }}</td>
                    <td>{{ $row['last_name'] }}</td>
                    <td>{{ $row['email'] }}</td>
                    <td>{{ $row['phone_number'] }}</td>
                    @foreach ($serviceNames as $serviceName)
                        <td>{{ $row['services'][$serviceName] ?? 0 }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($serviceNames) + 5 }}" class="no-data">ไม่มีรายการ</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
