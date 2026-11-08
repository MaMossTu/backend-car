<h3 style="text-align:center;">รายการจัดการบิล</h3>
<table border="1" cellpadding="3" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:10px;">
    <thead>
        <tr>
            @foreach ($headings as $heading)
                <th style="text-align:center;">{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $index => $cell)
                    <td @if ($index >= 4 && $index <= 14) style="text-align:right;" @endif>
                        {{ $cell }}
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headings) }}" style="text-align:center;">ไม่มีข้อมูล</td>
            </tr>
        @endforelse
    </tbody>
</table>
