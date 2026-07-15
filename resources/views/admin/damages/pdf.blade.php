<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Damage Records</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        p.meta { color: #555; margin-top: 0; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Damage Records</h1>
    <p class="meta">Generated {{ now()->format('Y-m-d H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Product</th>
                <th>Supplier</th>
                <th>PO#</th>
                <th>Qty</th>
                <th>Type</th>
                <th>Status</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($damagedProducts as $damage)
                <tr>
                    <td>{{ $damage->DamageID }}</td>
                    <td>{{ optional($damage->DateRecorded)->format('Y-m-d') }}</td>
                    <td>{{ $damage->product?->ProductName ?? 'N/A' }}</td>
                    <td>{{ $damage->supplier?->SupplierName ?? 'N/A' }}</td>
                    <td>{{ $damage->PurchaseOrderID ?? '-' }}</td>
                    <td>{{ $damage->Quantity }}</td>
                    <td>{{ \App\Models\DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType }}</td>
                    <td>{{ $damage->Status }}</td>
                    <td>{{ $damage->Description }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No damage records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
