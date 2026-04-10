<?php

namespace App\Export;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class ProfitReportExport implements FromView
{
    protected $data;
    protected $servicePrices;

    public function __construct(Collection $data, array $servicePrices = [])
    {
        $this->data = $data;
        $this->servicePrices = $servicePrices;
    }

    public function view(): View
    {
        return view('export.ProfitReport', [
            'data' => $this->data,
            'servicePrices' => $this->servicePrices,
        ]);
    }
}
