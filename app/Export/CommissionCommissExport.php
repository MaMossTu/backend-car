<?php

namespace App\Export;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class CommissionCommissExport implements FromView
{
    protected $rows;
    protected $serviceNames;

    public function __construct(array $rows, array $serviceNames)
    {
        $this->rows = $rows;
        $this->serviceNames = $serviceNames;
    }

    public function view(): View
    {
        return view('export.CommissionCommiss', [
            'rows' => $this->rows,
            'serviceNames' => $this->serviceNames,
        ]);
    }
}
