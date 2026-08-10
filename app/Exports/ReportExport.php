<?php

namespace App\Exports;

use App\Models\DamagedProduct;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\ReportSummaryBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ReportExport implements FromCollection, ShouldAutoSize, WithDrawings, WithEvents, WithHeadings, WithMapping
{
    public function __construct(
        private string $type,
        private Collection $rows,
        private ?string $dateFrom = null,
        private ?string $dateTo = null,
    ) {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('CCTV Express Solution');
        $drawing->setDescription('Company logo');
        $drawing->setPath(public_path('Images/logo.png'));
        $drawing->setHeight(40);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    public function headings(): array
    {
        return match ($this->type) {
            'inventory' => ['Product', 'SKU / Barcode', 'Category', 'Supplier', 'Current Stock', 'Reorder Level', 'Cost Price', 'Selling Price', 'Stock Value', 'Status'],
            'orders' => ['PO Number', 'Date', 'Supplier', 'Product', 'Qty', 'Unit Price', 'Subtotal', 'Status'],
            'returns' => ['Return Ref.', 'Date', 'Invoice', 'Customer', 'Product', 'Qty', 'Reason', 'Type', 'Status', 'Processed By'],
            'damage' => ['Reference', 'Date', 'Product', 'Supplier', 'Qty', 'Damage Type', 'Reason', 'Status'],
            'supplier' => ['Supplier Name', 'Contact Person', 'Contact Number', 'Email', 'Address', 'Total POs', 'Total Purchases', 'Status'],
            default => ['Invoice No', 'Date', 'Customer', 'Payment Method', 'Product', 'Qty', 'Unit Price', 'Discount', 'VAT', 'Total'],
        };
    }

    public function map($row): array
    {
        return match ($this->type) {
            'inventory' => [
                $row->product?->ProductName ?? 'N/A',
                $row->product?->SKU ?: ($row->product?->Barcode ?: 'N/A'),
                $row->product?->category?->CategoryName ?? 'Uncategorized',
                $row->product?->resolveReorderSupplier()?->supplier?->SupplierName ?? 'N/A',
                $row->Quantity,
                $row->ReorderThreshold ?? 0,
                (float) ($row->product?->CostPrice ?? 0),
                (float) ($row->product?->Price ?? 0),
                round($row->Quantity * (float) ($row->product?->CostPrice ?? 0), 2),
                $row->Status,
            ],
            'orders' => [
                $row->PONumber,
                $row->PurchaseDate,
                $row->SupplierName,
                $row->ProductName,
                $row->Quantity,
                (float) $row->UnitPrice,
                (float) $row->Subtotal,
                PurchaseOrder::STATUS_LABELS[$row->Status] ?? ucfirst($row->Status),
            ],
            'returns' => [
                'RET-' . str_pad((string) $row->SalesReturnID, 6, '0', STR_PAD_LEFT),
                $row->ReturnDate,
                $row->ReceiptNumber,
                $row->CustomerName,
                $row->product?->ProductName ?? 'N/A',
                $row->Quantity,
                ucfirst(str_replace('_', ' ', $row->Reason)),
                ucfirst($row->ReturnType ?? 'refund'),
                ucfirst($row->Status),
                $row->ProcessedByName,
            ],
            'damage' => [
                'DMG-' . str_pad((string) $row->DamageID, 6, '0', STR_PAD_LEFT),
                optional($row->DateRecorded)->format('Y-m-d'),
                $row->product?->ProductName ?? 'N/A',
                $row->supplier?->SupplierName ?? 'N/A',
                $row->Quantity,
                DamagedProduct::DAMAGE_TYPES[$row->DamageType] ?? $row->DamageType,
                $row->Description ?: 'N/A',
                ucfirst(str_replace('_', ' ', $row->Status)),
            ],
            'supplier' => [
                $row->SupplierName,
                $row->ContactPerson ?: 'N/A',
                $row->ContactNumber ?: 'N/A',
                $row->Email ?: 'N/A',
                $row->Address ?: 'N/A',
                $row->TotalOrders,
                round((float) $row->TotalAmount, 2),
                ucfirst($row->Status),
            ],
            default => [
                $row->ReceiptNumber,
                $row->BillingDate,
                $row->CustomerName,
                ucfirst($row->PaymentMethod ?? 'N/A'),
                $row->ProductName,
                $row->Quantity,
                (float) $row->UnitPrice,
                $row->Discount,
                $row->VatAmount,
                (float) $row->ItemTotal,
            ],
        };
    }

    // Column indices (1-based) holding a currency amount in the data grid —
    // each gets a currency cell format, and any that also map onto a
    // Report Summary row (via summaryFormulaColumns()) gets a real SUM()
    // formula there instead of a hardcoded value.
    private function moneyColumns(): array
    {
        return match ($this->type) {
            'inventory' => [7, 8, 9],
            'orders' => [6, 7],
            'supplier' => [7],
            'returns', 'damage' => [],
            default => [7, 8, 9, 10],
        };
    }

    private function dateColumns(): array
    {
        return match ($this->type) {
            'sales', 'orders', 'damage', 'returns' => [2],
            default => [],
        };
    }

    // Maps a Report Summary label to the data-grid column its value should
    // be a live =SUM() formula over, instead of the static number
    // ReportSummaryBuilder already computed — kept in sync by hand since
    // both read from the same headings()/map() column order above. Sales'
    // "Total" column holds each line's gross amount (ItemTotal), which is
    // exactly "Subtotal (Gross Sales)" — not "Net Sales" (BillingAmount,
    // a different, non-exported value) — so only the columns that
    // genuinely match a visible grid column get a formula; "Net Sales"
    // keeps the static PHP-computed value instead of a mismatched SUM().
    private function summaryFormulaColumns(): array
    {
        return match ($this->type) {
            'inventory' => ['Total Inventory Value' => 9],
            'orders' => ['Total Amount' => 7],
            'supplier' => ['Total Amount' => 7],
            default => ['Subtotal (Gross Sales)' => 10, 'Discount' => 8, 'VAT' => 9],
        };
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $columnCount = count($this->headings());
                $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);
                $dataRowCount = $this->rows->count();

                // ---- Title block, inserted above the heading/data grid ----
                $period = ($this->dateFrom || $this->dateTo)
                    ? ($this->dateFrom ?: 'Earliest') . ' to ' . ($this->dateTo ?: 'Latest')
                    : 'All Time';

                $titleRows = [
                    ['', 'CCTV EXPRESS TRADING'],
                    ['', 'Point of Sale & Inventory System'],
                    ['', ucfirst($this->type) . ' Report'],
                    [],
                    ['Report Period', $period],
                    ['Generated By', auth()->user()->full_name ?? 'N/A'],
                    ['Generated On', now()->format('F j, Y g:i A')],
                    ['Branch', 'Main Branch'],
                    [],
                    ['FILTERS'],
                    ['Date Range', $period],
                    [],
                ];
                $offset = count($titleRows);

                $sheet->insertNewRowBefore(1, $offset);
                foreach ($titleRows as $i => $cells) {
                    foreach ($cells as $col => $value) {
                        $sheet->setCellValueByColumnAndRow($col + 1, $i + 1, $value);
                    }
                }
                $sheet->mergeCells("B1:{$lastColumnLetter}1");
                $sheet->mergeCells("B2:{$lastColumnLetter}2");
                $sheet->mergeCells("B3:{$lastColumnLetter}3");
                $sheet->getStyle('B1:B3')->getFont()->setBold(true);
                $sheet->getStyle('B1')->getFont()->setSize(14);
                $sheet->getStyle('B1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A5:A8')->getFont()->setBold(true);
                $sheet->getStyle('A10')->getFont()->setBold(true);
                $sheet->getRowDimension(1)->setRowHeight(20);

                // ---- Header row + data ----
                $headerRow = $offset + 1;
                $dataStartRow = $headerRow + 1;
                $dataEndRow = $headerRow + $dataRowCount;

                $sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                $sheet->setAutoFilter("A{$headerRow}:{$lastColumnLetter}{$headerRow}");
                $sheet->freezePane('A' . $dataStartRow);

                if ($dataRowCount > 0) {
                    foreach ($this->moneyColumns() as $moneyCol) {
                        $letter = Coordinate::stringFromColumnIndex($moneyCol);
                        $sheet->getStyle("{$letter}{$dataStartRow}:{$letter}{$dataEndRow}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    foreach ($this->dateColumns() as $dateCol) {
                        $letter = Coordinate::stringFromColumnIndex($dateCol);
                        $sheet->getStyle("{$letter}{$dataStartRow}:{$letter}{$dataEndRow}")
                            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD2);
                    }
                }

                // ---- Report Summary, editable cells with a formula where
                // it maps onto a real column in this type's data grid ----
                $summaryRow = $dataEndRow + 2;
                $sheet->setCellValue("A{$summaryRow}", 'REPORT SUMMARY');
                $sheet->getStyle("A{$summaryRow}")->getFont()->setBold(true);
                $summaryRow++;

                $formulaColumns = $this->summaryFormulaColumns();

                foreach (ReportSummaryBuilder::forType($this->type, $this->rows) as $entry) {
                    $sheet->setCellValue("A{$summaryRow}", $entry['label']);

                    if ($dataRowCount > 0 && isset($formulaColumns[$entry['label']])) {
                        $letter = Coordinate::stringFromColumnIndex($formulaColumns[$entry['label']]);
                        $sheet->setCellValue("B{$summaryRow}", "=SUM({$letter}{$dataStartRow}:{$letter}{$dataEndRow})");
                    } else {
                        $sheet->setCellValue("B{$summaryRow}", $entry['value']);
                    }

                    if ($entry['money']) {
                        $sheet->getStyle("B{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }

                    $summaryRow++;
                }
            },
        ];
    }
}
