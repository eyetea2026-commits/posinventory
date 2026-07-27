<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private string $type, private Collection $rows)
    {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return match ($this->type) {
            'inventory' => ['ID', 'Product', 'Quantity', 'Status'],
            'orders' => ['ID', 'Date', 'Status', 'Supplier'],
            'returns' => ['ID', 'Transaction ID', 'Product', 'Quantity', 'Reason', 'Cashier', 'Status', 'Date'],
            'damage' => ['ID', 'Date', 'Product', 'Supplier', 'Quantity', 'Damage Type', 'Status'],
            'supplier' => ['ID', 'Supplier', 'Status', 'Total Orders', 'Total Amount'],
            default => ['ID', 'Date', 'Amount', 'Customer', 'Payment Method'],
        };
    }

    public function map($row): array
    {
        return match ($this->type) {
            'inventory' => [
                $row->InventoryID,
                $row->product?->ProductName ?? 'N/A',
                $row->Quantity,
                $row->Status,
            ],
            'orders' => [
                $row->PurchaseOrderID,
                $row->PurchaseDate,
                $row->Status,
                $row->supplier?->SupplierName ?? 'N/A',
            ],
            'returns' => [
                $row->SalesReturnID,
                $row->SalesTransactionID,
                $row->product?->ProductName ?? 'N/A',
                $row->Quantity,
                $row->Reason,
                $row->CashierName,
                $row->Status,
                $row->ReturnDate,
            ],
            'damage' => [
                $row->DamageID,
                optional($row->DateRecorded)->format('Y-m-d'),
                $row->product?->ProductName ?? 'N/A',
                $row->supplier?->SupplierName ?? 'N/A',
                $row->Quantity,
                \App\Models\DamagedProduct::DAMAGE_TYPES[$row->DamageType] ?? $row->DamageType,
                $row->Status,
            ],
            'supplier' => [
                $row->SupplierID,
                $row->SupplierName,
                $row->Status,
                $row->TotalOrders,
                $row->TotalAmount,
            ],
            default => [
                $row->BillingID,
                $row->BillingDate,
                $row->BillingAmount,
                $row->CustomerName ?? 'N/A',
                $row->payment?->PaymentMethod ?? 'N/A',
            ],
        };
    }
}
