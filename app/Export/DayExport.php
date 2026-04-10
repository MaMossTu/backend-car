<?php

namespace App\Export;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class DayExport implements FromView
{
    protected $data;

    public function __construct(array $data)
    {
        
        $this->data = $data;

    }

    public function view(): View
    {
        return view('export.Day', [
            'data' => $this->data,
        ]);
    }
}
