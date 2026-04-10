<?php

namespace App\Export;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportCarTaxVehicleExport implements FromView, WithEvents
{
    protected $data;
    protected $tax_vehicle;
    protected $start_date;
    protected $end_date;

    public function __construct(array $data, $tax_vehicle, $start_date = null, $end_date = null)
    {
        $this->data = $data;
        $this->tax_vehicle = $tax_vehicle;
        $this->start_date = $start_date ?? null;
        $this->end_date = $end_date ?? null;
    }

    public function view(): View
    {
        return view('export.ReportCarTaxVehicle', [
            'data' => $this->data,
            'tax_vehicle' => $this->tax_vehicle,

            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestDataRow();
                $highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

                for ($row = 1; $row <= $highestRow; $row++) {
                    for ($col = 1; $col <= $highestCol; $col++) {
                        $cell = $sheet->getCellByColumnAndRow($col, $row);
                        $value = trim((string) $cell->getValue());
                        $coord = $cell->getCoordinate();

                        if (in_array($value, ['สำเร็จ', 'success'], true)) {
                            $sheet->getStyle($coord)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'D1FAE5'],
                                ],
                                'font' => [
                                    'bold' => true,
                                    'color' => ['rgb' => '065F46'],
                                ],
                                'alignment' => [
                                    'horizontal' => 'center',
                                ],
                            ]);
                        } elseif (in_array($value, ['รอรับป้าย', 'complete'], true)) {
                            $sheet->getStyle($coord)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'FFEDD5'],
                                ],
                                'font' => [
                                    'bold' => true,
                                    'color' => ['rgb' => '9A3412'],
                                ],
                                'alignment' => [
                                    'horizontal' => 'center',
                                ],
                            ]);
                        }
                    }
                }
            },
        ];
    }
}
