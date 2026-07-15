<?php

namespace App\Exports;

use App\Models\DamagedProduct;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DamagesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private array $filters)
    {
    }

    public function query()
    {
        return DamagedProduct::with(['product', 'supplier', 'purchaseOrder'])
            ->when($this->filters['search'] ?? null, function ($query, $search) {
                return $query->whereHas('product', function ($q) use ($search) {
                    $q->where('ProductName', 'like', "%{$search}%");
                });
            })
            ->when($this->filters['date_from'] ?? null, function ($query, $dateFrom) {
                return $query->whereDate('DateRecorded', '>=', $dateFrom);
            })
            ->when($this->filters['date_to'] ?? null, function ($query, $dateTo) {
                return $query->whereDate('DateRecorded', '<=', $dateTo);
            })
            ->when($this->filters['status'] ?? null, function ($query, $status) {
                return $query->where('Status', $status);
            })
            ->when($this->filters['damage_type'] ?? null, function ($query, $damageType) {
                return $query->where('DamageType', $damageType);
            })
            ->when($this->filters['supplier_id'] ?? null, function ($query, $supplierId) {
                return $query->where('SupplierID', $supplierId);
            })
            ->when($this->filters['po_id'] ?? null, function ($query, $poId) {
                return $query->where('PurchaseOrderID', $poId);
            })
            ->orderByDesc('DamageID');
    }

    public function headings(): array
    {
        return ['ID', 'Date', 'Product', 'Supplier', 'PO#', 'Qty', 'Type', 'Status', 'Description'];
    }

    public function map($damage): array
    {
        return [
            $damage->DamageID,
            optional($damage->DateRecorded)->format('Y-m-d'),
            $damage->product?->ProductName ?? 'N/A',
            $damage->supplier?->SupplierName ?? 'N/A',
            $damage->PurchaseOrderID ?? '',
            $damage->Quantity,
            DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType,
            $damage->Status,
            $damage->Description,
        ];
    }
}
