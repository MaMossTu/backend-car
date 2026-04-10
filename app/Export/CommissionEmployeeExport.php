<?php

namespace App\Export;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class CommissionEmployeeExport implements FromView
{
    protected $employees;
    protected $serviceCounts;
    protected $serviceNames;
    public function __construct($employees, $serviceCounts, $serviceNames)
    {
        $this->employees = $employees;
        $this->serviceCounts = $serviceCounts;
        $this->serviceNames = $serviceNames;
    }

    // Pass the data to the view
    public function view(): View
    {
        return view('export.CommissionEmployee', [
            'employees' => $this->employees,
            'serviceCounts' => $this->serviceCounts,
            'serviceNames' => $this->serviceNames
        ]);
    }
}
