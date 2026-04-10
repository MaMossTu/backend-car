<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบวางบิล/ใบแจ้งหนี้</title>
    <style>
        body {
            font-family: "th-sarabun", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }

        .header {
            display: flex;
            justify-content: space-between;
            padding: 20px;
        }

        .header-left {
            text-align: left;
        }

        .header-left1 {
            text-align: left;
            font-size: 20px;
            position: absolute;
            width: 300px;
            top: 200px;
            left: 470px;
        }

        .header-left h1 {
            margin: 0;
            font-size: 24px;
        }

        .header-left p {
            margin: 2px 0;
            font-size: 18px;
        }

        .header-right {
            text-align: right;
            font-size: 20px;
            position: absolute;
            width: 300px;
            top: 77px;
            left: 585px;
        }

        .header-right2 {
            text-align: right;
            font-size: 20px;
            position: absolute;
            width: 300px;
            top: 192px;
            left: 470px;
        }

        .content {
            padding: 20px;
            display: flex;
            justify-content: space-between;
        }

        .content-left {
            text-align: left;
            font-size: 18px;
        }

        .content-right {
            text-align: right;
            font-size: 18px;
        }

        .table-container {
            margin-top: 5px;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            font-size: 18px;
            ก
        }

        th {
            background-color: #f2f2f2;
        }

        .footer {
            margin-top: 5px;
            text-align: left;
            font-size: 18px;
        }

        .footer table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer td {
            border: none;
            text-align: left;
            padding: 8px;
            font-size: 18px;
        }

        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            font-size: 18px;
        }

        .signature-section1 {
            width: 100%;
            text-align: center;
        }

        .signature-section2 {
            width: 100%;
            text-align: center;
        }

        .container {
            display: flex;
            align-items: flex-start;
            text-align: left;
            font-size: 20px;
            position: absolute;
            width: 50px;
            top: 130px;
            left: 80px;
        }

        .container img {
            margin-right: 20px;
            /* Adjust spacing as needed */
            width: 100px;
            /* Adjust image size as needed */
        }

        .text-content {
            max-width: 300px;
            /* Adjust as needed */
        }
    </style>
</head>

<body>
    <table style="width: 100%;">
        <tr>
            <td style="width: 5%; border: none; text-align: left; padding-left: 5px;">
            <img src="{{ $Branch->logo_url ?? '' }}" alt="FIE" align="left" style="width: 100px">

            </td>
            <td style="width: 60%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
            <h2>{{ $Branch->business_name ?? '-' }}</h2>
            <p>
                {{ $Branch->address ?? '' }}
                {{ optional($Branch->SubDistricts)->name_th_prefix ?? '' }}
                {{ optional($Branch->Districts)->name_th_prefix ?? '' }}
                {{ optional($Branch->Provinces)->name_th_prefix ?? '' }}
                {{ $Branch->postal_code ?? '' }}
            </p>
            <p>{{ $Branch->phone ?? '-' }}</p>
            </td>
            <td style="width: 40%; vertical-align: text-top; border: none; text-size: 20px; text-align: right;">
                <p style="font-size: 20px"><strong>ใบแจ้งซ่อม</strong></p>
            </td>
        </tr>
    </table>
    <table style="width: 100%;">
        <tr>
            <td style="width: 60%; border: none; text-align: left; overflow-wrap: break-word; word-break: break-all;">
            <p>ลูกค้า: {{ $WorkOrder->customer_no ?? '-' }} {{ $WorkOrder->customer_name ?? '-' }}</p>
            <p>ที่อยู่: {{ $WorkOrder->customer_address ?? '-' }}</p>
            <p>โทร.: {{ $WorkOrder->customer_phone ?? '-' }}</p>
            <p>ทะเบียน: {{ $WorkOrder->license_plate ?? '-' }}</p>
            </td>
            <td style="width: 40%; border: none; text-align: left; vertical-align: text-top;">
            <p>เลขที่: {{ $WorkOrder->no ?? '-' }}</p>
            <p>วันที่:
                {{ optional($WorkOrder->date) 
                    ? \Carbon\Carbon::parse($WorkOrder->date)->locale('th')->addYears(543)->translatedFormat('d F Y') 
                    : '-' }}
            </p>
            </td>
        </tr>
    </table>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">ลำดับ</th>
                    <th style="width: 60%;">รายการ</th>
                    <th style="width: 10%;">ราคา</th>
                    <th style="width: 10%;">จำนวน</th>
                    <th style="width: 10%;">รวม</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($WorkOrderServices as $WorkOrderService)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td>{{ $WorkOrderService->service_name ?? '-' }}</td>
                        <td>{{ number_format($WorkOrderService->service_price ?? 0, 2) }}</td>
                        <td>{{ $WorkOrderService->qty ?? 0 }}</td>
                        <td>{{ number_format($WorkOrderService->unit_price ?? 0, 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4">รวม</td>
                    <td>{{ number_format($WorkOrder->total ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>
