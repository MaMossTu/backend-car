<?php

namespace App\Export;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class MonthExport implements FromView
{
    protected $items;
    protected $costs;

    public function __construct($items, $costs)
    {
        $this->items = $items;
        $this->costs = $costs;
    }

    public function view(): View
    {
        return view('export.Month', [
            'items' => $this->items,
            'costs' => $this->costs,
        ]);
    }
}
