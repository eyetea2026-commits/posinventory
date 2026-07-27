<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Damage Report #{{ $damage->DamageID }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.meta { color: #555; margin: 2px 0; }
        .section { border-top: 1px solid #ccc; padding-top: 10px; margin-top: 14px; }
        .section h2 { font-size: 13px; text-transform: uppercase; color: #666; margin: 0 0 8px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print</button>

    <h1>Damage Report #{{ $damage->DamageID }}</h1>
    <p class="meta">Date Recorded: {{ optional($damage->DateRecorded)->format('Y-m-d') }}</p>
    <p class="meta">Status: {{ \App\Models\DamagedProduct::STATUS_LABELS[$damage->Status] ?? $damage->Status }}</p>

    <div class="section">
        <h2>Product</h2>
        <div class="row"><span>Product</span><strong>{{ $damage->product?->ProductName ?? 'N/A' }}</strong></div>
        <div class="row"><span>SKU</span><strong>{{ $damage->product?->SKU ?? 'N/A' }}</strong></div>
        <div class="row"><span>Quantity</span><strong>{{ $damage->Quantity }}</strong></div>
        <div class="row"><span>Damage Type</span><strong>{{ \App\Models\DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType }}</strong></div>
    </div>

    <div class="section">
        <h2>Supplier</h2>
        <div class="row"><span>Supplier</span><strong>{{ $damage->supplier?->SupplierName ?? 'N/A' }}</strong></div>
        @if($damage->purchaseOrder)
            <div class="row"><span>Purchase Order</span><strong>{{ $damage->purchaseOrder->PONumber }}</strong></div>
        @endif
    </div>

    <div class="section">
        <h2>Details</h2>
        <p>{{ $damage->Description }}</p>
        @if($damage->InspectionNotes)
            <p><strong>Inspection Notes:</strong> {{ $damage->InspectionNotes }}</p>
        @endif
        @if($damage->WarehouseLocation)
            <p><strong>Warehouse Location:</strong> {{ $damage->WarehouseLocation }}</p>
        @endif
        @if($damage->Remarks)
            <p><strong>Remarks:</strong> {{ $damage->Remarks }}</p>
        @endif
    </div>
</body>
</html>
