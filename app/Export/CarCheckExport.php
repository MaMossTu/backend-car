<?php

namespace App\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class CarCheckExport implements FromView
{
    protected $data;
    protected $startDate;
    protected $endDate;

    public function __construct(array $data, $startDate, $endDate)
    {
        $this->data = $data;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        
    }

    public function view(): View
    {
        return view('export.CarCheckReport', [
            'data' => $this->data,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}






