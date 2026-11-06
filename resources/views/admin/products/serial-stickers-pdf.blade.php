{{-- Serial number stickers: A4 sticker paper, 4 columns x 10 rows, each
     52.5 x 29.7 mm, no page margin. Price-tag layout: product name and SKU
     top left, the selling price large on the right of the SKU, and that piece's
     barcode (Code 128 set B, scanned at the POS) with its serial below. --}}
@php
    $priceText = '₱'.number_format((float) $product->Price, 2);
    $priceSize = match (true) {
        mb_strlen($priceText) <= 7 => '14pt',
        mb_strlen($priceText) <= 9 => '12pt',
        default => '10.5pt',
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Serial Stickers - {{ $product->ProductName }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #000; }
        .page { position: relative; width: 210mm; height: 296.9mm; page-break-after: always; overflow: hidden; }
        .page.last { page-break-after: auto; }
        .sticker { position: absolute; width: 52.5mm; height: 29.7mm; overflow: hidden; }
        .label { position: absolute; left: 1mm; top: 1mm; width: 50.5mm; height: 27.7mm; border: 0.25mm solid #9ca3af; border-radius: 2mm; }
        .name { position: absolute; left: 2mm; top: 1.5mm; width: 46.5mm; font-size: 6pt; line-height: 2.6mm; white-space: nowrap; overflow: hidden; }
        .sku { position: absolute; left: 2mm; top: 6.6mm; font-size: 5.6pt; line-height: 2.6mm; }
        .price { position: absolute; left: 2mm; top: 3.6mm; width: 46.5mm; text-align: right; font-weight: bold; white-space: nowrap; line-height: 6mm; }
        .barcode { position: absolute; left: 2.25mm; top: 11.3mm; width: 46mm; height: 10.8mm; }
        .serial { position: absolute; left: 0; top: 22.6mm; width: 50.5mm; text-align: center; font-size: 6pt; letter-spacing: 0.6pt; }
    </style>
</head>
<body>
@foreach($pages as $pageIndex => $pageSerials)
    <div class="page {{ $loop->last ? 'last' : '' }}">
        @foreach($pageSerials->values() as $index => $serial)
            <div class="sticker" style="left: {{ ($index % 4) * 52.5 }}mm; top: {{ intdiv($index, 4) * 29.7 }}mm;">
                <div class="label">
                    <div class="name">{{ \Illuminate\Support\Str::limit($product->ProductName, 38) }}</div>
                    <div class="sku">{{ $product->SKU }}</div>
                    <div class="price" style="font-size: {{ $priceSize }};">{{ $priceText }}</div>
                    <img class="barcode" src="{{ \App\Services\Code128Barcode::dataUri($serial->SerialNumber) }}" alt="{{ $serial->SerialNumber }}">
                    <div class="serial">{{ $serial->SerialNumber }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
