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
                <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">#</th>
                <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">ชื่อ</th>
                <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">นามสกุล</th>
                <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 100px;">อีเมล</th>
                <th rowspan="2" style="border: 1px solid black; text-align: center; vertical-align: middle; width: 300px">เบอ์โทร</th>
                <th colspan="13" style="border: 1px solid black; text-align: center; vertical-align: middle;">บริการ</th>
            </tr>
            <tr>
                @foreach ($serviceNames as $serviceName)
                    <th style="border: 1px solid black; text-align: center; width: 100px;">{{ $serviceName }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $index => $employee)
                @php
                    $employeeName = $employee->first_name . ' ' . $employee->last_name;
                    $services = $serviceCounts[$employeeName] ?? [];
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $employee->first_name }}</td>
                    <td>{{ $employee->last_name }}</td>
                    <td>{{ $employee->email }}</td>
                    <td>{{ $employee->phone_number }}</td>

                    @foreach ($serviceNames as $serviceId => $serviceName)
                        <td>{{ $services[$serviceId] ?? 0 }}</td>
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
